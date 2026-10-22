# Project skills

Committed so every developer's Claude Code gets the same ones. Update a copied skill by re-copying it
from its source and noting the new version here.

| Skills | Source | Version |
|---|---|---|
| `elme-business`, `elme-port-from-luma`, `elme-qa` | written for this project (`elme-qa` adapted from `bart-qa` in m2-bart-group) | - |
| `hyva-*` (8) | github.com/hyva-themes/hyva-ai-tools | commit in `HYVA_AI_TOOLS_VERSION` |
| `emil-design-eng`, `review-animations` | github.com/emilkowalski/skill | copied 2026-10-22 |
| `web-interface-guidelines` | github.com/vercel-labs/agent-skills + pinned rules from vercel-labs/web-interface-guidelines | rules commit 434b7f9 |

Plugins (not copied, enabled in `.claude/settings.json`, installed from the official marketplace on
first run): `superpowers` (brainstorm → plan → TDD → review workflow), `frontend-design`,
`playwright`.

## Motion and polish in Hyvä

The Emil Kowalski skills use React and Motion (framer-motion) in examples. Here:

- CSS transitions / `@keyframes` and Tailwind (`transition`, `duration-*`, `ease-*`, `motion-safe:`),
  plus Alpine `x-transition` for enter/leave. No Motion/GSAP/React.
- Animate `transform` and `opacity` only; never `transition: all`.
- Every animation has a `motion-reduce:` fallback.
- Durations and easings: once we settle on them, add them as tokens (`--ease-*`, `--duration-*`) in
  `hyva.config.json` instead of repeating values.
