"""Compare the tutor's two-stage behavior with isolated Codex CLI model samples."""
import argparse
import hashlib
import json
import subprocess
import tempfile
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

from tutor import PROMPT, compose, effective_instructions, response_format, valid

MODELS = ('gpt-6-luna', 'gpt-6.1-terra')
INPUT_FIELDS = ('mode', 'question', 'topic', 'assignment', 'excerpt', 'history', 'review',
                'readings', 'lesson', 'neighbors', 'latest_submission', 'previous_results')


def sample(model, effort, instructions, body):
    with tempfile.TemporaryDirectory(prefix='tutor-sample-') as tmp:
        root = Path(tmp)
        workspace = root / 'empty'
        workspace.mkdir()
        schema = root / 'schema.json'
        schema.write_text(json.dumps(response_format(instructions, body)['schema'], ensure_ascii=False))
        prompt = root / 'instructions.txt'
        prompt.write_text(effective_instructions(instructions, body))
        args = ['codex', 'exec', '--ephemeral', '--ignore-user-config', '--skip-git-repo-check',
                '--json', '--color', 'never', '-C', str(workspace), '-s', 'read-only', '-m', model,
                '-c', f'model_instructions_file={json.dumps(str(prompt))}', '-c', f'model_reasoning_effort="{effort}"',
                '-c', 'approval_policy="never"', '-c', 'web_search="disabled"', '-c', 'project_doc_max_bytes=0']
        for feature in ('apps', 'multi_agent', 'shell_tool', 'browser_use', 'computer_use', 'workspace_dependencies'):
            args.extend(['--disable', feature])
        result = subprocess.run([*args, '--output-schema', str(schema), '-'], cwd=workspace,
                                input=json.dumps(body, ensure_ascii=False), text=True,
                                capture_output=True, timeout=240)
        if result.returncode:
            raise RuntimeError(f'{model}: {result.stdout[-1200:]} {result.stderr[-400:]}')
    usage, messages = None, []
    for line in result.stdout.splitlines():
        event = json.loads(line)
        if event['type'] == 'item.completed':
            item = event['item']
            if item['type'] == 'agent_message':
                messages.append(item['text'])
            elif item['type'] != 'reasoning':
                raise RuntimeError('Unexpected tool or item in benchmark: ' + item['type'])
        elif event['type'] == 'turn.completed':
            usage = event['usage']
        elif 'rerout' in event['type'] or event['type'] in ('error', 'turn.failed'):
            raise RuntimeError(f'Unexpected provider event: {event}')
    if usage is None or len(messages) != 1:
        raise RuntimeError('Incomplete model sample')
    return json.loads(messages[0]), usage


def run(pair):
    case, model, effort = pair
    data = {key: case[key] for key in INPUT_FIELDS}
    if "review_basis" in case:
        data["review_basis"] = case["review_basis"]
    if not valid(data):
        raise RuntimeError('Invalid fixture: ' + case['task'])
    calls = []

    def generate(instructions, body):
        started = time.monotonic()
        try:
            result, usage = sample(model, effort, instructions, body)
        except (KeyError, ValueError) as error:
            raise RuntimeError(f'Invalid CLI sample for {model}, {case["task"]}') from error
        calls.append(dict(stage='answer' if instructions == PROMPT else 'check', result=result, usage=usage,
                          seconds=round(time.monotonic() - started, 2),
                          instructions_sha256=hashlib.sha256(effective_instructions(instructions, body).encode()).hexdigest()))
        return result

    shown = compose(data, generate)
    if not calls:
        raise RuntimeError('Case did not exercise the model')
    return dict(task=case['task'], grade=case['grade'], model=model, effort=effort, mode=data['mode'],
                context_sha256=hashlib.sha256(json.dumps(data, ensure_ascii=False).encode()).hexdigest(),
                calls=calls, student_reply=shown)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('contexts', type=Path)
    parser.add_argument('--models', nargs='+', choices=MODELS, default=MODELS)
    parser.add_argument('--efforts', nargs='+', choices=['low', 'medium', 'high'], default=['low'])
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args()
    cases = json.loads(args.contexts.read_text())
    with args.output.open('x') as output, ThreadPoolExecutor(max_workers=4) as pool:
        futures = [pool.submit(run, (case, model, effort))
                   for case in cases for effort in args.efforts for model in args.models]
        for future in as_completed(futures):
            row = future.result()
            print(json.dumps(row, ensure_ascii=False), file=output, flush=True)
            print(row['grade'], row['task'], row['model'], row['effort'], flush=True)


if __name__ == '__main__':
    main()
