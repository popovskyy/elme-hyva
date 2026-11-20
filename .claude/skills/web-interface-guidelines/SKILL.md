---
name: web-interface-guidelines
description: Review storefront UI (Hyvä .phtml templates, Alpine components, Tailwind CSS) against the Vercel Web Interface Guidelines - accessibility, focus states, forms, animation, typography, content handling, images, performance, navigation and state, touch, dark mode, i18n, hydration-safety. Use when asked to "review my UI", "check accessibility", "audit UX", "is this page done", or before handing over a ported page.
---

# Web Interface Guidelines review

The rules are in `references/guidelines.md` - a pinned copy of
https://github.com/vercel-labs/web-interface-guidelines (`command.md`, commit 434b7f9, 2026-10-22).
Read that file; do not fetch the live URL (the copy is reviewed and pinned on purpose).

## How to apply it here

The guidelines are written with React examples. Translate them to this stack:

| Guideline says | In Hyvä |
|---|---|
| `onClick` / `onKeyDown` handlers | Alpine `@click` / `@keydown` (CSP-safe: methods, not inline expressions) |
| `<Link>` | `<a href>` |
| `focus-visible:ring-*` | Tailwind `focus-visible:` utilities; never remove the outline from `base/preflight.css` without a replacement |
| `useEffect` / hydration notes | Hyvä renders server-side; private data arrives on `private-content-loaded` - reserve space so it does not shift layout |
| `prefers-reduced-motion` | Tailwind `motion-safe:` / `motion-reduce:`; Alpine `x-transition` respects the CSS you give it |

## Output

Group findings by file, `file:line - issue → fix`, terse, most severe first. Skip rules that cannot
apply to the file. Do not rewrite code during a review unless asked.
