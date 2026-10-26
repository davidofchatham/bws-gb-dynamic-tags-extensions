# `{{content}}` Entity + Arm Regression Matrix

**Standing manual regression suite** for the `{{content}}` base tag: which ENTITY each arm
lands on, and — the reason this file exists — whether a hopped read's INNER dynamic tags
resolve against the entity that was hopped to or against the ambient page
([#58](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/58)).

> **Re-run trigger:** any change to `bws_base_content_callback()` (base-tags.php), the content
> cores (`bws_post_content_core` / `bws_post_excerpt_core`, content-tags.php), the rendering
> pipeline (`bws_process_post_content`, `ContentProcessor::render`), or the context swap
> `bws_with_post_context()` (content-helpers.php). The pipeline's pure transforms are owned by
> `php tools/test/inline-css-pipeline-test.php` (run it first — it is the cheap gate); rows here
> assert only what needs real WP state.

**How to run:** rows are `render-tag` one-liners against the seeded testbed
(state: `core-structures` blueprint — `bin/seed.sh testbed core-structures`). From the
wp-litespeed env:

```bash
bin/wp.sh testbed bws render-tag '{{TAG}}' --url=https://testbed.test/matrix-content/ --porcelain
```

Context used: `/matrix-content/` — carries `related_staff` (Jane Partner FIRST, then Tom
Associate), Support+Sales department terms, and field values deliberately DISTINCT from the
staff singles' (`main_line` `(321) 555-0100`, `name_first` `Pagefirst`, `name_last` `Pagelast`).
That contrast is the whole instrument: jane's own content renders her join rows off HER
`name_*`, so a row reading `Pagefirst, Pagelast` means the hop leaked back to the ambient page.

**Also browsable.** The seed builds these rows as visible GB blocks on `/matrix-content/`
(`blocks.php`: `matrix_content` builder). Eyeball the front end with a cache bust —
`curl -sk "https://testbed.test/matrix-content/?nocache=$RANDOM"`.

**Why its own page.** A hopped `{{content}}` renders another entity's whole block set inline.
On a shared matrix page two entities' values land on one screen and neither is legible, so the
content family gets a page whose only job is the entity contrast.

**No bare `{{content}}` row, by design.** It would recurse into the page being rendered;
`ContentProcessor`'s stack guard answers `''`. That guard is the recursion tests' subject, not
this matrix's — a row here would assert the guard, not the entity.

---

## §CT1–CT3 — a hopped read lands on the TARGET entity

The defect these pin is the bad-shaped one: before the context swap every row rendered
PLAUSIBLE content (jane's block structure) carrying the WRONG values (the ambient page's), so
nothing on screen looked broken. Read the VALUES, never the structure.

| # | Tag | Expected |
|---|---|---|
| CT1 | `{{content src:ref\|ref:related_staff}}` | Jane's blocks. Her `J1` row reads **`Jane, Johnson`** — her own `name_first`/`name_last`. `Pagefirst, Pagelast` there is #58 back |
| CT2 | `{{content src:refs,related_staff}}` | Byte-identical to CT1 — the chain spelling reaches the same arm |
| CT3 | `{{content src:ref\|ref:related_staff\|use:excerpt}}` | Jane's content trimmed, HER values, and the read-more anchor points at `https://testbed.test/staff/jane-partner/`. The link is a separate property from the text: `excerpt_more` reads the global post, so it broke and fixed with the swap |

> **The link half of CT3 is not decoration.** An excerpt whose text is right and whose
> read-more sends the reader to the wrong post is the same class of failure as the values —
> plausible, silent. Check the `href`, not just the prose.

## §CT4–CT5 — the field-read arms

Neither arm renders blocks, so neither can drift on entity the way CT1–CT3 did. They are here
so the callback's arms are covered in one place rather than scattered across family matrices.

| # | Tag | Expected |
|---|---|---|
| CT4 | `{{content src:ref\|ref:related_staff\|use:key\|key:main_line}}` | `(555) 200-3000` — jane's line, not the page's `(321) 555-0100` |
| CT5 | `{{content srcTermIn:department\|use:key\|key:blurb}}` | `Sales handles quotes, renewals and the annual customer roadshow.` — the first usable SOURCE is Sales (WP returns terms by name), and Sales is the one carrying a blurb. Support carries none, so a read in ASSIGNMENT order would render empty: since the 2026-08-21 determinism reversal (ADR 0007) this row pins the ORDER, not a skip — a collapsing tag reads the first usable source and renders its empty field as empty |

## §CT6 — the ambient contrast

| # | Tag | Expected |
|---|---|---|
| CT6 | `{{text key:main_line}}` | `(321) 555-0100` — the page's own value |

> CT6 is what makes CT1 non-vacuous. If the context swap ever leaked outward (restore missed,
> exception path), an ambient read would start returning jane's values and CT6 is the row that
> says so. Keep it beside the hop rows, not on another page.

## §CT8 — a field token implies the read (FW-142)

An absent `use` beside a field token reads through that token's mode, so `key` alone is the keyed read; an explicit `use` still wins over a stale `key` beside it. Only `content` moves: its stripped default is the analog, so before FW-142 CT8.1 rendered jane's whole content. Visible on `/matrix-content/` (CT8 section).

| # | Tag | Expected |
|---|---|---|
| CT8.1 | `{{content src:ref\|ref:related_staff\|key:main_line}}` | `(555) 200-3000`, jane's line. Her whole content here is the pre-FW-142 read |
| CT8.2 | `{{content src:ref\|ref:related_staff\|use:key\|key:main_line}}` | Byte-identical to CT8.1 (and CT4): the old redundant wire keeps rendering |
| CT8.3 | `{{content src:ref\|ref:related_staff\|use:excerpt\|key:main_line}}` | Byte-identical to CT3: the explicit `use` wins, the stale `key` is ignored |
| CT8.4 | `{{text src:ref\|ref:related_staff\|use:title\|key:main_line}}` | `Jane Partner`: explicit wins on `{{text}}` too |
| CT8.5 | `{{image src:post,<tom>\|use:featured\|key:feature_image\|as:id}}` | Empty (no featured image seeded), where `{{image src:post,<tom>\|key:feature_image\|as:id}}` reads the attachment id. Pinned at Tom, the one staff single carrying `feature_image`; `<tom>` is his seeded post id |

## §CT9 — always block-level (FW-144)

`{{content}}` always prints block-level markup: a non-empty value with no block-level tag is wrapped in paragraphs (`wpautop`), a value with any block-level tag is left exactly as the source gave it. The wrap sits at the exit of `bws_base_content_resolve_value()`, so every row above that prints a bare value (CT3, CT4, CT5, CT8.1–CT8.3) now prints it inside `<p>`; the strings quoted there are the text inside. What counts as block-level is `bws_markup_has_block_level_tag()`, harnessed by `php tools/test/content-block-markup-test.php`.

Fixture (blueprint v33): the `department-annex` term (two-paragraph description with `<strong>`), a manual excerpt on this page, and `bws_block_plain` / `bws_block_formatted` post meta on this page. Visible on `/matrix-content/` (CT9 section); ids are resolved at build time, so the tag strings below carry `<annex>` / `<this page>`.

| # | Tag | Expected |
|---|---|---|
| CT9.1 | `{{content src:term,<annex>}}` | `<p>The Annex handles overflow orders.</p>` newline `<p>It shares the <strong>Warehouse</strong> dock and is open on weekdays.</p>`; the unwrapped build printed one run of text |
| CT9.2 | `{{try_content src:term,<annex>}}` | Byte-identical to CT9.1: the `try_` family resolves through the same seam |
| CT9.3 | `{{content src:post,<this page>\|use:excerpt}}` | `<p>A hand-written excerpt.</p>` |
| CT9.4 | `{{content use:key\|key:bws_block_plain}}` | `<p>Plain line one.</p>` newline `<p>Plain line two.</p>` |
| CT9.5 | `{{content use:key\|key:bws_block_formatted}}` | Exactly as stored, `<p>Formatted one,` newline `with a soft line break.</p>` newline `<p>Formatted two.</p>`. **No `<br />` inserted**: measured 2026-10-26, `wpautop` over this value adds one, which is why a value carrying a block tag is skipped rather than passed through it |
| CT9.6 | `{{content use:key\|key:bws_missing_key\|fallback:Nothing stored.}}` | `<p>Nothing stored.</p>`: the stated fallback is part of the seam's value |
| CT9.7 | `{{content use:key\|key:bws_missing_key}}` | Empty, never an empty paragraph |

**Render-tag only (ambient context, measured 2026-10-26 before and after):**

| Context | Tag | Before | After |
|---|---|---|---|
| `/department/sales/` | `{{content}}`, `{{try_content}}` | the description, bare | the description in `<p>` |
| `/author/fixture-author/` | `{{content}}` | the bio, bare | the bio in `<p>` |
| `/staff/` (post type archive) | `{{content}}` | `<p>…</p>` | unchanged |
| any | `{{content src:site\|use:key\|key:organization_address}}` | `<p>…<br />…</p>` | unchanged |
| any | `{{content src:ref\|ref:related_staff}}` (post content) | blocks | unchanged |

The author bio arrived bare before this change, contrary to the FW-144 spec's reading of the code; it is wrapped now by the same rule.
