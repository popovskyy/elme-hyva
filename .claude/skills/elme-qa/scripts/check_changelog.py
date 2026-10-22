#!/usr/bin/env python3
"""Checks a elme-qa changelog before it is pasted into the task.

    python3 .claude/skills/elme-qa/scripts/check_changelog.py /tmp/elme-qa/changelog.txt

Prints OK or the problems, and exits 1 on any error. Checks the shape (Dev Branch line first, TASK,
Change log bullets, numbered QA steps with an Expected result), that the text is plain ASCII (no long
dashes, guillemets, curly quotes, arrows or ellipsis characters), and that no step points at a local
host or local demo data a tester on the shared environment cannot see. Warns when the block or a line runs long.
"""
import re
import subprocess
import sys

SWAP = {
    '—': '-', '–': '-', '‒': '-', '−': '-',
    '«': '"', '»': '"', '“': '"', '”': '"', '„': '"',
    '‘': "'", '’': "'", '…': '...', '→': 'to', '•': '-', ' ': ' ',
}


def main():
    if len(sys.argv) != 2:
        sys.exit('usage: check_changelog.py FILE')
    lines = open(sys.argv[1], encoding='utf-8').read().splitlines()
    errors, warnings = [], []

    branch = subprocess.run(['git', 'branch', '--show-current'], capture_output=True, text=True).stdout.strip()
    first = next((line for line in lines if line.strip()), '')
    if first != f'Dev Branch: {branch}':
        errors.append(f'first line must be "Dev Branch: {branch}" (got "{first}")')
    if not any(re.match(r'^TASK: \S', line) for line in lines):
        errors.append('missing "TASK: <ticket title>"')

    try:
        log_at = lines.index('Change log')
        steps_at = lines.index('Steps For QA (our manual)')
    except ValueError:
        errors.append('missing the "Change log" or "Steps For QA (our manual)" heading line')
        log_at = steps_at = None

    if log_at is not None and steps_at is not None:
        bullets = [line for line in lines[log_at + 1:steps_at] if line.strip()]
        if not bullets:
            errors.append('"Change log" has no lines')
        for line in bullets:
            if not line.startswith('- '):
                errors.append(f'Change log line must start with "- ": {line[:60]}')
        steps = [line for line in lines[steps_at + 1:] if line.strip()]
        if not steps:
            errors.append('"Steps For QA" has no steps')
        for n, line in enumerate(steps, 1):
            if not re.match(rf'^{n}\) .+Expected: \S', line):
                errors.append(f'step {n} must read "{n}) ... Expected: ...": {line[:60]}')
        if len(bullets) > 4:
            warnings.append(f'{len(bullets)} Change log lines: keep 2 to 4, what users and content notice')
        if len(steps) > 6:
            warnings.append(f'{len(steps)} QA steps: keep 4 to 6, merge the ones that check the same thing')
        for line in bullets + steps:
            if len(line) > 160:
                warnings.append(f'{len(line)} chars, split or cut it: {line[:60]}')

    for i, line in enumerate(lines, 1):
        for col, ch in enumerate(line, 1):
            if ord(ch) > 127:
                hint = f', write {SWAP[ch]!r}' if ch in SWAP else ''
                errors.append(f'line {i} col {col}: {ch!r} is not plain ASCII{hint}')
        if re.search(r'\.local\b|localhost|127\.0\.0\.1', line):
            errors.append(f'line {i}: a local host, which a tester on the shared environment cannot open')
        if re.search(r'\blocally\b', line, re.I):
            errors.append(f'line {i}: local demo data means nothing on the shared environment, state the rule instead')

    for w in warnings:
        print(f'warn  {w}')
    for e in errors:
        print(f'FAIL  {e}')
    if not errors:
        print('OK')
    sys.exit(1 if errors else 0)


if __name__ == '__main__':
    main()
