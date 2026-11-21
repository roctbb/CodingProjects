"""Run the production answer + review path on the exported teaching examples."""
import argparse
import json
import os
from pathlib import Path

from tutor import CHECK, PROMPT, cards_for, effective_instructions, reply, response_format, valid


def input_size(data, encoding):
    context = {**data, "cards": cards_for(data)}
    # Sample the extra checker input; output and reasoning counts require a live provider.
    draft = {"answer":"Проверь один небольшой пример.", "sources":[context["readings"][0]["id"]] if context["readings"] else [], "card":None}
    counts = []
    for instructions, body in [(PROMPT, context), (CHECK, {**context, "draft":draft})]:
        fields = [effective_instructions(instructions, body), json.dumps(body, ensure_ascii=False), json.dumps(response_format(instructions, body), ensure_ascii=False)]
        counts.append(sum(len(encoding.encode_ordinary(s)) for s in fields))
    return {"answer_input":counts[0],"check_input":counts[1]}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("contexts", type=Path)
    parser.add_argument("--models", nargs="+", default=[])
    parser.add_argument("--estimate", action="store_true", help="Count input locally using tiktoken o200k_base.")
    parser.add_argument("--budget", type=int, default=1800)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    encoding = None
    if args.estimate:
        import tiktoken
        encoding = tiktoken.get_encoding("o200k_base")
    elif not args.models or not os.getenv("OPENAI_API_KEY"):
        parser.error("Configure the intended test provider before running.")
    cases = [c for c in json.loads(args.contexts.read_text()) if c["budget"] == args.budget]
    with args.output.open("w") as out:
        for model in (["o200k_base"] if args.estimate else args.models):
            os.environ["GPT_MODEL"] = model
            os.environ["TUTOR_CHECK_MODEL"] = model
            os.environ["TUTOR_REVIEW_MODEL"] = model
            for c in cases:
                data = {k:c[k] for k in ("topic", "question", "assignment", "readings")}
                data.update({k:c[k] for k in ("lesson", "neighbors", "latest_submission", "previous_results", "review_basis") if k in c})
                data.update(mode=c.get("mode", "hint"), review=c.get("review"),
                            excerpt=c.get("excerpt", ""), history=c.get("history", []))
                if not valid(data):
                    raise ValueError("Invalid case: " + c["task"])
                row = {"model":model, "task":c["task"], "grade":c["grade"], "budget":args.budget}
                if encoding:
                    row.update(input_size(data, encoding))
                else:
                    result = reply(data)
                    row.update(result)
                    if "gold" in c:
                        row["cited_expected"] = bool(set(result["sources"]) & set(c["gold"]))
                print(json.dumps(row, ensure_ascii=False), file=out, flush=True)


if __name__ == "__main__":
    main()

