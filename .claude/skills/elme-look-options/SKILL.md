---
name: elme-look-options
description: >
  Use before changing how an Elme storefront block looks, when the user wants to
  choose a direction first. Triggers: "show me options", "make an artifact",
  "show how it could look", "I'll pick", "make examples", "looks bad, show
  options", "don't build it yet, let me pick" (also when asked in Ukrainian). Builds a standalone HTML page in
  docs/design with two or three real variants (phone and desktop), then stops
  until they name one. Do not edit theme templates in the same step.
---

# Look options

The user picks the look. The theme changes only after that.

Do not edit `.phtml`, Tailwind, `content_types.xml`, or layout until they name
a variant (a number, or a mix such as "2 on the phone, 3 on desktop"). The
page you build is a throwaway chooser. It is not part of the storefront.

## 1. Read the block that exists

Find the template, the fields that feed it, and the real labels and images on
https://elme-hyva.local (or the homepage HTML). Every variant uses that same
content. Do not invent products, prices, or category names.

Colours and type come from `app/design/frontend/Elme/default/web/tailwind/hyva.config.json`
(`tokens.values` and `fonts`). Load the fonts listed there (Figtree) with relative
`@font-face` paths into the theme `web/fonts/` files. Do not add a new palette.

## 2. Write one page

`docs/design/<slug>-options.html`

Photos, if the block has them, go in `docs/design/<slug>/` and are referenced
relatively (`<slug>/vessels.jpg`). Copy them off the local site. Do not hotlink.

The page is in English (repo files are English ASCII), even when the user writes in
Ukrainian. Store labels stay as they are on the site.
Quiet chrome: the variants are the only thing to judge. Number them `1.`, `2.`,
`3.` so the user can answer with a number.

Two or three variants. They must be different ideas, not the same tile with a
new colour. Each one is shown twice:

- phone, 390px wide
- wide screen, 1280px wide

Same items, same order, in every frame. If the real block scrolls, the frames
scroll too (drag on the phone, arrows on the wide screen, arrows hidden when
nothing overflows). Long labels wrap without changing the image size.

One sentence under each title says what is different. No uppercase eyebrows,
no numbered steps that are not a sequence, no extra marketing.

Open the page in the browser and check phone and wide frames before handing it
over. Say the file path and that you are waiting for a number.

## 3. After they choose

Change only the look of the existing block. Keep its fields, links, and
behaviour (swipe, arrows, empty state). Use tokens, not new hex values in the
template. If a utility is not enough, add a partial under `web/tailwind/elme/`
and rebuild Tailwind (`hyva-compile-tailwind-css`), then flush the Magento cache.

Delete the HTML chooser and its photos once the look is in the theme. Do not wire it into a layout.
