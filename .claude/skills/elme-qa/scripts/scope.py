#!/usr/bin/env python3
"""Change inventory for an Elme QA pass (Magento Open Source + Hyvä).

Lists every file the branch changes against its base (commits since the merge-base, plus staged,
unstaged and untracked work), groups them by layer, says which Hyvä parent file a theme file
overrides, lints changed templates and CSS against the project's Hyvä rules, and for changed PHP
classes lists who else references them.

    python3 .claude/skills/elme-qa/scripts/scope.py              # report
    python3 .claude/skills/elme-qa/scripts/scope.py --list php   # paths only: php|xml|phtml|css|js|all

BASE=<ref> overrides the base branch (default: the first of origin/main, main, origin/master,
master that exists). Before the first commit everything untracked counts as changed.
"""
import os
import re
import subprocess
import sys

ROOT = subprocess.run(['git', 'rev-parse', '--show-toplevel'], capture_output=True, text=True).stdout.strip()
os.chdir(ROOT)

THEME = 'app/design/frontend/Elme/default'
PARENT = 'vendor/hyva-themes/magento2-default-theme'
LUMA = 'vendor/magento/theme-frontend-luma'
# app/etc/config.php is rewritten by every local bootstrap (module order, 2FA toggle); generated
# Tailwind output is rebuilt by npm run build. Neither is part of a change under QA.
IGNORE = ('var/', 'pub/', 'generated/', '.playwright-mcp/', 'node_modules/', 'app/etc/', 'vendor/',
          f'{THEME}/web/tailwind/generated/', f'{THEME}/web/fonts/generated/')

# Template / CSS rules from CLAUDE.md and the elme-port-from-luma skill. Each hit is a line to look at,
# not automatically a bug.
LINT = [
    (r'\.phtml$', r'#[0-9a-fA-F]{3,8}\b(?![^<]*\?>)', 'hex colour in a template: use a colour token (bg-primary, text-ink-muted...)'),
    (r'\.phtml$', r'\b[a-z-]+-\[\d+(\.\d+)?px\]', 'arbitrary px value: add a token in hyva.config.json instead'),
    (r'\.phtml$', r'x-magento-init|data-mage-init|require\(\[|jQuery|\$\(\s*[\'"]', 'Luma JS (RequireJS/jQuery/mage-init) does not exist in Hyvä'),
    (r'\.phtml$', r'x-data="\s*\{', 'inline x-data object: not CSP-compatible, register a component function'),
    (r'\.phtml$', r'@(click|change|input|submit|keydown)[.\w-]*="[^"]*(=|!|\(\s*[\'"\d])', 'inline Alpine expression (assignment, negation or argument): not CSP-compatible, call a method'),
    (r'\.phtml$', r'x-model=', 'x-model is not available in Alpine CSP: use :value + @input'),
    (r'\.phtml$', r'<\?=\s*\$(block|product|item|_?[a-z]+)->get\w+\([^)]*\)\s*\?>', 'unescaped output: wrap in $escaper->escapeHtml/escapeHtmlAttr/escapeUrl'),
    (r'\.phtml$', r'ObjectManager::getInstance|->helper\(', 'ObjectManager / helper in a template: use a ViewModel'),
    (r'\.(phtml|css)$', r'transition:\s*all|transition-all\b', 'transition all: list the properties (transform, opacity)'),
    (r'\.(phtml|css)$', r'outline(-none|:\s*none)', 'outline removed: make sure a focus-visible style replaces it'),
]


def git(*args):
    return subprocess.run(['git', *args], capture_output=True, text=True).stdout


def has_head():
    return subprocess.run(['git', 'rev-parse', '--verify', '-q', 'HEAD'], capture_output=True).returncode == 0


def base_ref():
    if os.environ.get('BASE'):
        return os.environ['BASE']
    for ref in ('origin/main', 'main', 'origin/master', 'master'):
        if subprocess.run(['git', 'rev-parse', '--verify', '-q', ref], capture_output=True).returncode == 0:
            return ref
    return None


def changed_files(base):
    files = {}
    merge_base = ''
    if base and has_head():
        merge_base = git('merge-base', 'HEAD', base).strip()
        if merge_base:
            for line in git('diff', '--name-status', f'{merge_base}...HEAD').splitlines():
                status, *paths = line.split('\t')
                files[paths[-1]] = status[0]
    for line in git('status', '--porcelain', '--untracked-files=all').splitlines():
        status, path = line[:2].strip() or 'M', line[3:]
        path = path.split(' -> ')[-1]
        files[path] = 'A' if status == '??' else status[0]
    files = {p: s for p, s in files.items() if not p.startswith(IGNORE)}
    return merge_base, dict(sorted(files.items()))


def layer(path):
    rules = [
        (r'/etc/db_schema(_whitelist)?\.(xml|json)$', 'schema'),
        (r'/Setup/Patch/', 'data/schema patch'),
        (r'/etc/(adminhtml/|frontend/)?di\.xml$', 'DI (preferences/plugins)'),
        (r'/etc/.*\.xml$', 'module config xml'),
        (r'/ui_component/.*\.xml$', 'admin UI component'),
        (r'/layout/.*\.xml$', 'layout xml'),
        (r'\.phtml$', 'template'),
        (r'/web/tailwind/.*\.(css|json)$', 'Tailwind source / tokens'),
        (r'/web/css/styles\.css$', 'compiled theme CSS'),
        (r'\.css$', 'CSS'),
        (r'\.(js|mjs)$', 'JS'),
        (r'\.php$', 'PHP'),
        (r'^\.claude/', 'Claude skills / settings'),
        (r'\.csv$', 'i18n / data csv'),
    ]
    for pattern, name in rules:
        if re.search(pattern, path):
            return name
    return 'other'


def theme_reach(path):
    """What a theme file overrides, and whether a module view file is shadowed by the theme."""
    if path.startswith(f'{THEME}/'):
        rel = path[len(THEME) + 1:]
        if rel.startswith('web/tailwind/') or rel == 'web/css/styles.css':
            return ''
        if os.path.exists(f'{PARENT}/{rel}'):
            return f'overrides Hyvä {PARENT}/{rel} (diff it against the parent after a Hyvä upgrade)'
        return 'Elme-only theme file'
    m = re.match(r'^app/code/([^/]+)/([^/]+)/view/(frontend|base)/(templates|web|layout)/(.+)$', path)
    if m:
        vendor, module, _, kind, rel = m.groups()
        if os.path.exists(f'{THEME}/{vendor}_{module}/{kind}/{rel}'):
            return f'OVERRIDDEN by {THEME}/{vendor}_{module}/{kind}/{rel}: the storefront may not see this change'
        if kind == 'templates':
            return 'module template: Tailwind classes count only if the module is in hyva.config.json / hyva-source'
    return ''


def lint(path):
    hits = []
    try:
        lines = open(path, encoding='utf-8', errors='ignore').read().splitlines()
    except OSError:
        return hits
    for file_re, line_re, note in LINT:
        if not re.search(file_re, path):
            continue
        for n, line in enumerate(lines, 1):
            if re.search(line_re, line):
                hits.append(f'{path}:{n}  {note}\n        {line.strip()[:140]}')
    return hits


def php_references(path):
    try:
        src = open(path, encoding='utf-8', errors='ignore').read()
    except OSError:
        return None, []
    ns = re.search(r'^namespace\s+([^;]+);', src, re.M)
    cls = re.search(r'^(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)', src, re.M)
    if not (ns and cls):
        return None, []
    fqcn = f'{ns.group(1)}\\{cls.group(1)}'
    out = subprocess.run(
        ['grep', '-rlF', fqcn, 'app/code', 'app/design', '--include=*.php', '--include=*.xml', '--include=*.phtml'],
        capture_output=True, text=True).stdout.split()
    return fqcn, [p for p in out if p != path]


def main():
    kinds = {'php': ('.php',), 'xml': ('.xml',), 'phtml': ('.phtml',), 'css': ('.css',),
             'js': ('.js', '.mjs'), 'all': ('',)}
    base = base_ref()
    merge_base, files = changed_files(base)

    if len(sys.argv) > 2 and sys.argv[1] == '--list':
        suffixes = kinds[sys.argv[2]]
        for path, status in files.items():
            if status != 'D' and path.endswith(suffixes) and os.path.exists(path):
                print(path)
        return

    where = f'base {base}  merge-base {merge_base[:10]}' if merge_base else 'no commits yet: every untracked file counts'
    print(f"branch {git('branch', '--show-current').strip()}  {where}")
    print(f'{len(files)} changed file(s)\n')

    groups = {}
    for path, status in files.items():
        groups.setdefault(layer(path), []).append((status, path))
    for name in sorted(groups):
        print(f'## {name}')
        for status, path in groups[name][:60]:
            reach = theme_reach(path)
            print(f"  {status} {path}" + (f"\n      -> {reach}" if reach else ''))
        if len(groups[name]) > 60:
            print(f'  ... +{len(groups[name]) - 60} more')
        print()

    warnings = []
    for path in files:
        if path.endswith('/etc/db_schema.xml'):
            wl = path.replace('db_schema.xml', 'db_schema_whitelist.json')
            if wl not in files:
                warnings.append(f'{path} changed but {wl} did not')
    # Tailwind scans .phtml and layout .xml (tailwind-source.css @source), so both can add classes.
    templates_or_tw = [p for p in files if p.endswith('.phtml') or '/web/tailwind/' in p or '/layout/' in p]
    if templates_or_tw and f'{THEME}/web/css/styles.css' not in files:
        warnings.append('templates or Tailwind sources changed but web/css/styles.css did not: run npm run build '
                        'and commit the CSS (new classes are missing otherwise)')
    if any(p.startswith('app/code/') and p.endswith('/registration.php') and s == 'A' for p, s in files.items()):
        warnings.append("new module: its line ('Vendor_Module' => 1) must be in app/etc/config.php, or the next "
                        'module:disable writes 0 and setup:upgrade drops its tables')
    if warnings:
        print('## WARNINGS')
        for w in warnings:
            print(f'  ! {w}')
        print()

    hits = [h for path, status in files.items()
            if status != 'D' and not path.endswith('/web/css/styles.css') for h in lint(path)]
    print('## Hyvä / project rules lint (look at each, not every hit is a bug)')
    for h in hits[:40]:
        print(f'  ? {h}')
    if len(hits) > 40:
        print(f'  ... +{len(hits) - 40} more')
    if not hits:
        print('  none')
    print()

    print('## PHP classes changed -> other files referencing them')
    for path, status in files.items():
        if path.endswith('.php') and status != 'D':
            fqcn, refs = php_references(path)
            if fqcn:
                print(f'  {fqcn}: {len(refs)} reference(s)')
                for ref in refs[:12]:
                    print(f'      {ref}')
                if len(refs) > 12:
                    print(f'      ... +{len(refs) - 12} more')


if __name__ == '__main__':
    main()
