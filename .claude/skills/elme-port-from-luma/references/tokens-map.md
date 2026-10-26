# Old Bart/el LESS variables → Hyvä tokens

Tokens live in `app/design/frontend/Elme/default/web/tailwind/hyva.config.json` → `tokens.values` and
become `--<group>-<key>` CSS variables / Tailwind utilities. Values were extracted from
`m2-bart-group/app/design/frontend/Bart/el/web/css/source/variables/**` (the `@jl-` prefix is a
leftover from James Lane: the values are Elme's own) and checked against computed styles on
https://www.elmeliving.com/ on 2026-10-21. "Uses" = occurrences in the `Bart/el` styles.

## Colours (`color.*` → `bg-*`, `text-*`, `border-*`)

| Token | Value | Old variable(s) | Used for |
|---|---|---|---|
| `primary` | `#3A3935` | `@jl-color-brand-primary` (244 uses), `@heading__color__base` | text, headings, primary button, borders, breadcrumbs |
| `on-primary` | `#FFFFFF` | `@jl-color-brand-primary-alt` | text on primary |
| `secondary` | `#ADBBA1` | `@jl-color-brand-secondary` | top bar, primary button hover, pager border, label accent |
| `tertiary` | `#D4C8B3` | `@jl-color-brand-tertiary` | sand accents |
| `accent` | `#98A8BB` | none - CMS content on the live site | category tiles, banners |
| `accent-dark` | `#68839C` | `@jl-background-color-blue`, `@jl-form-element-input-focus-border-color` | input focus |
| `ink` | `#3A3935` | `@jl-color-brand-primary` | body text (`--color-fg`) |
| `ink-muted` | `#676F78` | `@jl-p-color-primary` (69) | secondary paragraphs |
| `ink-subtle` | `#797D81` | `@jl-background-color-grey5` (68) | paragraphs, choice labels, meta |
| `page` | `#F5F4EF` | `@jl-color-body-background`, `@page__background-color` | page background (`--color-bg`) |
| `surface` | `#FFFFFF` | `@od-product__background` | cards, header |
| `surface-muted` | `#F8F8F8` | `@jl-background-color-primary`, `@jl-state-inactive-color` | inactive / muted panels |
| `sand-light` | `#E1DBD1` | `@jl-background-color-grey7` (12) | warm panels |
| `cool-light` | `#ECEFF1` | `@jl-background-color-grey4` | cool panels |
| `border` | `#CAC9C9` | `@jl-background-color-grey6` (42), `@form-element-input__border-color` | inputs, dividers |
| `border-light` | `#D3D6D9` | `@jl-form-radio-outer-border-color`, grey9 | radios, light borders |
| `divider` | `#DCDCDC` | `@_hr-border-color` | `<hr>` |
| `placeholder` | `#ADB2B8` | `@form-element-input-placeholder__color` | placeholders, disabled text |
| `success` / `success-light` | `#00BC37` / `#C5EAD6` | `@jl-message-success-color`, `@jl-background-color-green` | success messages |
| `olive` | `#677858` | `@jl-background-color-green2` | |
| `warning` | `#F2B20C` | `@jl-low-stock-status` | Low Stock label |
| `error` / `error-strong` / `error-light` | `#FF5942` / `#D82C0D` / `#FFC3B8` | `@jl-error-color`, red3, red1 | validation, required `*` |
| `promo` | `#CB4867` | `@jl-background-color-purple` | sale / promo badge |

## Typography

| Token | Value | Old |
|---|---|---|
| `font-sans` (hyva.config.json `fonts`) | Karla 300-800 | `@font-family-name__base` |
| `font-heading` | DM Serif Display 400 (+ italic) | `@font-family-name__dmserif`, `@heading__font-family__base` |
| `text-base` | 15px / 20px | `@font-size__base`, `@line-height__base` |
| `text-h1` / `h1-desktop` | 27px/35px → 60px | `@h1__font-size`, `@h1__font-size-desktop` |
| `text-h2` / `h2-desktop` | 20px/30px → 28px | `@h2__font-size`, `@od-h2__font-size--desktop` |
| `text-h3` / `h3-desktop` | 18px/20px → 22px | `@h3__*` |
| `text-h4` | 18px/28px | `@h4__*` |
| `text-h5` / `h5-desktop` | 16px/18px → 18px | `@h5__*` |
| `text-h6` | 16px/22px | `@h6__*` |
| `text-label` | 11px/13px | `@form-field-label__font-size` |
| `text-breadcrumbs` | 12px | `@breadcrumbs__font-size` |

All headings are weight 400; `elme/typography.css` sets them, and `elme/prose.css` keeps that scale inside `.prose` (CMS content).

## Layout

| Token | Value | Old |
|---|---|---|
| `breakpoint-sm` | 480px | Magento `@screen__xs` |
| `breakpoint-md` (tablet) | 768px | Overdose `@screen__s` |
| `breakpoint-lg` (desktop) | 1025px | Overdose `@screen__m` |
| `breakpoint-xl` | 1280px | Overdose `@screen__l` |
| `breakpoint-2xl` | 1440px | old `@layout__max-width` |
| `breakpoint-3xl` | 1920px | design frame of the new blocks |
| `container-max-width` | 1920px | was 1440px on the old site |
| `container-gutter` / `-desktop` | 20px / 40px | `@layout-indent__mobile` / `@layout-indent__width` |

## Components

| Token | Value | Old |
|---|---|---|
| `radius-*` | 0 | `@button__border-radius: 0` - Elme is square |
| `form-radius` | 1px | `@form-element-input__border-radius` |
| `form-stroke` | `#CAC9C9` | `@form-element-input__border-color` |
| `form-py` / `form-px` | 14px / 12px | 50px input height (`@form-element-input__height`) |
| `btn-min-height` | 50px | `@od-button__min-height` |
| `btn-padding-x` | 24px | `@button__padding: 8px 24px` |
| `btn-font-size` | 15px | `@button__font-size` |
| `btn-letter-spacing` | 0.5px | `@od-button__letter-spacing` |

Buttons: primary = charcoal, sage on hover; secondary = charcoal outline, fills charcoal on hover
(`components/button.css`, marked `Elme:`).
