"""Replay the generated contexts against a local OpenAI-compatible chat model."""
import argparse
import json
import statistics
from collections import defaultdict
from pathlib import Path

import requests

from tutor import PROMPT, cards_for, response_format


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("contexts", type=Path)
    parser.add_argument("--endpoint", required=True)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()
    results = defaultdict(list)
    with args.output.open("w") as output:
        for case in json.loads(args.contexts.read_text()):
            data = {k: case[k] for k in ("topic", "question", "assignment", "readings")}
            data.update(mode="hint", review=None, excerpt="", history=[])
            data["cards"] = cards_for(data)
            fmt = response_format(PROMPT, data)
            response = requests.post(args.endpoint, timeout=120, json={
                "messages": [{"role":"system", "content":PROMPT},
                             {"role":"user", "content":json.dumps(data, ensure_ascii=False)}],
                "temperature":0, "seed":42, "max_tokens":320, "response_format":{"type":"json_schema","json_schema":{k:v for k,v in fmt.items() if k != "type"}}})
            response.raise_for_status()
            payload = response.json()
            text = payload["choices"][0]["message"]["content"]
            try:
                draft = json.loads(text)
            except ValueError:
                draft = {}
            refs = draft.get("sources", []) if isinstance(draft, dict) else []
            hit = isinstance(refs, list) and any(x in case["gold"] for x in refs)
            row = dict(grade=case["grade"], task=case["task"], budget=case["budget"],
                       available=bool(case["gold"]), cited=hit, tokens=payload["usage"]["prompt_tokens"], output=text)
            print(json.dumps(row, ensure_ascii=False), file=output, flush=True)
            results[case["budget"]].append(row)
    for budget, rows in sorted(results.items()):
        print(budget, len(rows), sum(r["available"] for r in rows), sum(r["cited"] for r in rows),
              statistics.median(r["tokens"] for r in rows))


if __name__ == "__main__":
    main()

