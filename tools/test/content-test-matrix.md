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
| CT5 | `{{content srcTermIn:department\|use:key\|key:blurb}}` | `Sales handles quotes, renewals and the annual customer roadshow.` — support carries NO blurb, so this also pins that the term walk skips an empty term rather than stopping at it |

## §CT6 — the ambient contrast

| # | Tag | Expected |
|---|---|---|
| CT6 | `{{text key:main_line}}` | `(321) 555-0100` — the page's own value |

> CT6 is what makes CT1 non-vacuous. If the context swap ever leaked outward (restore missed,
> exception path), an ambient read would start returning jane's values and CT6 is the row that
> says so. Keep it beside the hop rows, not on another page.
