---
name: elme-qa
description: Use when a feature, ported block or bugfix on the new Elme Living storefront (Magento Open Source + Hyvä, this repo) is finished and needs QA before commit or handover, e.g. "QA this", "check if everything is ok", "did I break anything", "перевір чи все ок", "проведи qa", "compare with the old site", or when the ticket needs a changelog and manual QA steps.
---

# Elme QA

QA a finished change the way the business, a senior backend architect and a senior Hyvä frontend
developer would, with evidence from the running site, then hand back a short report and a changelog.
Paths are relative to the repo root.

**REQUIRED BACKGROUND:** `elme-business` (business rules) and, for a ported block,
`elme-port-from-luma`. Detailed checks: `checklists.md` next to this file.

## Process

1. **Scope.** Run `scope.py`, then read the whole diff since the merge-base plus untracked files. Trace
   every hunk to the ticket; an untraceable hunk is a finding. Look at each lint hit `scope.py` prints.
2. **Business context.** Read the `elme-business` section for the area. Note which rules the change
   must meet, and which of them are not built on this instance yet.
3. **Blast radius.** The "Blast radius" section of `checklists.md`, driven by `scope.py`'s output.
4. **Backend.** `smoke.sh` with the pages the feature touches, then the "Senior backend architect"
   checklist. If the diff adds constructor arguments, plugins or preferences, run the DI compile too.
5. **Frontend.** `browse.mjs` at 375 and 1440 (plus 768 / 1025 / 1920 when the layout changes), on
   `both:` targets for a ported block, and on the admin pages involved. Read every screenshot. Then the
   "Senior frontend" and "Admin UI" checklists.
6. **Exercise the feature end to end** as the business uses it: logged out and logged in, the admin
   side, the negative case, and what must stay unchanged. Test data per the "Test data" checklist.
7. **Verify each finding** by reproducing it or reading the code.
8. **Report**, in the shape below.

## Driver

```bash
python3 .claude/skills/elme-qa/scripts/scope.py           # changes, layers, theme overrides, Hyvä lint,
                                                          # PHP references
bash .claude/skills/elme-qa/scripts/smoke.sh / /customer/account/login/
node .claude/skills/elme-qa/scripts/browse.mjs both:/customer/account/login/ /checkout/cart/
node .claude/skills/elme-qa/scripts/browse.mjs --viewports 375,768,1025,1440,1920 both:/
node .claude/skills/elme-qa/scripts/browse.mjs --admin admin:catalog/product/index
```

- `smoke.sh` (~1 min, writes nothing to the DB or the code; it puts back `app/etc/config.php`, which the
  schema dry run rewrites): containers, `php -l`, XML, pending schema and patches via a dry run, whether
  the committed `styles.css` equals a fresh Tailwind build and is what the site serves, HTTP status, and
  new ERROR/CRITICAL lines in `exception.log` / `system.log`.
- `browse.mjs` (~10 s per target): a fresh browser context per target and viewport, screenshots and
  `report.json` in `/tmp/elme-qa/<branch>-<time>/`, own console errors apart from third-party noise,
  failed same-origin requests, real horizontal scroll. `old:` / `both:` targets screenshot the live
  site as the reference: its errors are reported as `ref` and never fail the run. Options are in the
  file header. `--steps` runs a flow after load:

```js
// flow.mjs - ctx = { brand ('new' | 'old' | 'admin'), host, path, viewport, shot(name) }
export default async (page, { brand, shot }) => {
    if (brand === 'old') return null;              // never click through the live site
    await page.click('button[aria-label^="Toggle minicart"]');   // Hyvä header cart button
    await page.waitForTimeout(600);
    await shot('minicart-open');
    return page.getByText('My Cart', { exact: true }).isVisible();
};
```

- DI compile, when step 4 calls for it (~1.5 min). Clear the compiled output afterwards, or developer
  mode keeps using it and ignores later `di.xml` edits:

```bash
bin/clinotty bash -c "php -d memory_limit=-1 bin/magento setup:di:compile"
bin/clinotty bash -c "rm -rf generated/code/* generated/metadata/*"
```

## Report

Two parts, in this order.

**1. To the user, in Ukrainian: a scannable card, about 8 lines, never prose.**

```
**Вердикт:** ✅ готово / ⚠️ готово з примітками / ❌ не готово

**Знахідки:** немає
- **blocker** · [Foo.php:42](app/code/Elme/X/Foo.php#L42) — що не так → фікс

**Перевірено**
- **375 / 1440** <сторінка або сценарій> → <результат> ([скрін](/tmp/elme-qa/<run>/<file>.png))
- **Паритет** <сторінка> старий vs новий → <збігається / відмінності> ([старий](…), [новий](…))
- **Admin** <меню-шлях> → <результат>

**Не перевірено:** <що> — <чому>
**Тестові дані:** <що створив або змінив> → прибрав
```

- One line per bullet; the **bold** carries the keyword (severity, viewport, area).
- Link every file (`[name:line](relative/path#Lline)`) and the screenshot that proves a "Перевірено"
  line. No bare paths, no backticked file names.
- No findings → `**Знахідки:** немає`, one line. No section with nothing to say.
- Leave out harness retries, selectors and environment noise that did not block a check.
- A business rule the change needs but this instance does not have yet goes under "Не перевірено" with
  the reason, not under findings.
- The changelog block follows directly.

**2. The changelog, in English, for the user to paste into the task:**

```
Dev Branch: <output of git branch --show-current>

TASK: <ticket title>

Change log
- <what changed, as users and content will notice it>

Steps For QA (our manual)
1) <Storefront or Admin>, <site path or admin menu path>: <action>. Expected: <rule>.
```

Short plain sentences, plain ASCII only (hyphens, straight quotes).

- **Change log: 2 to 4 lines**, one fact each, what a user or content person notices.
- **Steps: 4 to 6**, one line each (the checker warns past 160 characters). Cover mobile and desktop,
  logged out and logged in where they differ, the admin side, one negative case and one thing that must
  not have changed.
- **Links:** there is no shared environment URL yet - give the site path (`/customer/account/login`) and
  admin menu paths with `>` (Content > Blocks). Once a shared environment exists, put its full URL here
  and in this file.

The title comes from the ticket in the conversation; with no ticket, use the branch name and say so.

Save the block to `/tmp/elme-qa/changelog.txt`, run
`python3 .claude/skills/elme-qa/scripts/check_changelog.py /tmp/elme-qa/changelog.txt`, fix what it
reports, and paste the block only once it prints `OK`.

## Rationalizations

| Thought | Reality |
|---|---|
| "200 and no console errors, so the page is fine" | An unstyled page is 200 with no errors. Read the screenshot. |
| "I added the classes, it looks right in my browser" | Your browser may hold the old `styles.css`. `smoke.sh` compares with a fresh build and with what is served. |
| "Nothing references it" | Magento wires through XML: `di.xml`, layout, `ui_component`, `hyva.config.json`. Grep those. |
| "The checkout looks unchanged, so my Tailwind fix is broken" | Checkout runs on Luma (fallback). Tailwind never reaches it. |
| "The old site does it, so it works here" | Most Elme B2B behaviour is not built on this instance yet. Check, then say so. |
| "The old site has console errors too" | `old:` is the reference, not under test; compare looks, not its errors. |
| "It's an improvement, so it can stay" | An out-of-scope hunk is a finding; the user decides. |
| "I can't check the admin" | `browse.mjs --admin`. |

## Harness gotchas

- `browse.mjs` loads `playwright-core` from the global `@playwright/mcp` install (`npm root -g`) and its
  browser build in `~/.cache/ms-playwright`. Set `PLAYWRIGHT_CORE` to another `playwright-core` path if
  that install is missing (`npm i -g @playwright/mcp` then `npx playwright install chromium`).
- Containers down → `elmeStart` / the dev-dashboard card. Bart and Shaw share ports 80/443: only one
  Magento stack runs at a time.
- Every page 500s with `Class "…" does not exist` after a branch switch: stale DI config in `var/cache`:
  `bin/clinotty bash -c "rm -rf var/cache/mage--*"`.
- Served `styles.css` differs from the file: `rm -rf pub/static/frontend/Elme var/view_preprocessed/pub/static/frontend/Elme`
  and write a new `pub/static/deployed_version.txt` (no trailing newline, or the CSS URL breaks).
