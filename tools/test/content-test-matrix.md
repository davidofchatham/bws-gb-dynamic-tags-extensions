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

## §CT7 — modifier family (`term_`/`view_`/`fixture_`) `use` dispatch ([#88](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/88))

Different code path from the rest of this file — `register_modifier()`/`make_modifier_callback()`
(class-tag-template-registry.php), not `bws_base_content_callback()`. Belongs here rather than a
third file because it is the same TAG family this matrix already covers, and the defect it pins
is entity-independent (it reproduces identically on `term_`/`view_`/`fixture_`; term_/fixture_ are
the two live in this repo).

Through 1.19.1, `register_modifier()` wired `term_content`'s `post_fn`/`term_fn` straight to
`bws_post_content_core`/`bws_term_description_core`, which read `$options['type']`, never `use` —
so `use:key` and `use:excerpt` silently rendered the DEFAULT content/description branch instead
of the field or excerpt, and did so wrongly rather than emptily (`text`'s twin of this bug,
[#88](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/88)'s original
report, at least rendered empty). Fixed by pointing `term_fn`/`post_fn` at the same
`try_content_term_dispatch`/`try_content_post_dispatch` functions the `try_` family already used
correctly.

`term_content`'s own description/excerpt happen to be empty on this fixture's Support term either
way (no term description authored, no reachable post carries a manual excerpt from a modifier's
own traversal) — that makes `use:content`/`use:excerpt` non-diagnostic for `term_content` here
(same value before and after the fix), so only the `use:key` row is listed: it is the one branch
this corpus can show actually changing. `CT3` above already covers `use:excerpt` dispatching
correctly on the BASE `{{content}}` tag against a target with real content (Jane Partner) — this
section's job is only the MODIFIER wiring, not re-proving the excerpt core itself.

| # | Tag | Expected |
|---|---|---|
| CT7.1 | `{{term_content use:key\|key:email}}` on `/department/support/` | `support@example.test` — the term's own field. **Before the fix: empty** (the modifier's `use` was never read, so the read fell to the term-description branch, which is empty for this term). Render-tag-only: a term-archive ambient context, same exception as T4 in `text-test-matrix.md` |
| CT7.2 | `{{fixture_content use:key\|key:role}}` on `/matrix-fixture-roots/` | `Fixture Root Role` — same defect, the class route. **Before the fix: empty**, same reason. **Visible** — [`registered-roots-test-matrix.md`](registered-roots-test-matrix.md) §FR7.2, `blocks.php`'s `matrix_fixture_roots` builder |
