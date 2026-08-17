# `{{text}}` Read-Seam Regression Matrix

**Standing manual regression suite** for the base text read seam (`bws_base_text_resolve_value`,
the absorb seam extracted 1.14.1) and the link-wrap gate in its shell callback
(`bws_base_text_callback`). Originated with the 1.14.1 extraction; becomes the re-run pass for
anything that touches the text value path — including `{{join}}` slots once they absorb it.

> **Re-run trigger:** any change to `bws_base_text_resolve_value()`, `bws_base_text_callback()`,
> `bws_wrap_with_link()` / `bws_resolve_link_url()`, or a new absorber of the seam (e.g. the
> `{{join}}` per-slot resolve). Rows target the wrap-gate contract (`link_id` 0 = multi-result =
> never wrap; sentinel `link_id` 1 = site) and the value invariants (`'0'` preserved, list modes
> use text's own `sep`/`limit`).

**How to run:** rows are `render-tag` one-liners against the seeded testbed
(state: `core-structures` blueprint — `bin/seed.sh testbed core-structures`). From the
wp-litespeed env:

```bash
bin/wp.sh testbed bws render-tag '{{TAG}}' --url=https://testbed.test/CONTEXT/ --porcelain
```

Contexts used: `/matrix-post-meta/` (post arm; carries Support + Sales department terms,
`related_staff` → Jane Partner, Tom Associate — jane first) and `/department/support/` (term
archive → term-analog arm). T6 is editor-only (open a block on the testbed editor).

> Verified 2026-07-17 against the 1.14.1 extraction: T1, T3, T4, T5, T7 pass via
> `render-tag`; T6 passes via a direct callback call with a faked preview context.

---

## T1 — post arm + wrap gate

| # | Tag (on `/matrix-post-meta/`) | Expected |
|---|---|---|
| T1.1 | `{{text key:main_line}}` | `(987) 654-3210` — bare value, no anchor |
| T1.2 | `{{text key:main_line\|linkTo:permalink}}` | value wrapped: `<a href="…/matrix-post-meta/">(987) 654-3210</a>` |
| T1.3 | `{{text use:title\|linkTo:permalink\|newTab}}` | page title wrapped, `target="_blank" rel="noopener noreferrer"` present |

## T2 — site arm (sentinel link_id)

Covered by [`src-site-test-matrix.md`](src-site-test-matrix.md) R0.1–R0.2 / R4.7 — re-run those
rows alongside this matrix; the sentinel `link_id = 1` ('site') path lives there. No duplicate
rows here.

## T3 — srcTermIn list mode: multi never wraps, single wraps

| # | Tag (on `/matrix-post-meta/`) | Expected |
|---|---|---|
| T3.1 | `{{text srcTermIn:department\|use:title\|limit:2}}` | `Sales, Support` — text's own `sep` default; term order = WP default (alphabetical by name) |
| T3.2 | `{{text srcTermIn:department\|use:title\|limit:2\|linkTo:permalink}}` | `Sales, Support` — **NO anchor** (multi-result → `link_id` 0 → wrap suppressed) |
| T3.3 | `{{text srcTermIn:department\|use:title\|limit:1\|linkTo:permalink}}` | `Sales` wrapped in the Sales term-archive link (single result → term wrap) |

## T4 — term-analog arm (bare tag on a term archive)

| # | Tag (on `/department/support/`) | Expected |
|---|---|---|
| T4.1 | `{{text key:email}}` | `support@example.test` — term ACF field via the analog arm |
| T4.2 | `{{text key:email\|linkTo:permalink}}` | value wrapped in the Support term-archive link (term entity type) |

## T5 — `'0'` preservation

| # | Tag (on `/matrix-post-meta/`) | Expected |
|---|---|---|
| T5.1 | `{{text key:bws_zero_probe}}` | renders `0` — must NOT be empty. (`render-tag` shows bare `0`; in a real GB block render the hooks.php `'0'`→`'0 '` falsy guard applies downstream.) |

## T6 — editor preview fallback

The shell callback's `bwsEditorPreview` branch — driveable without the editor by
faking the context flag on the instance (`wp eval` on the testbed):

```php
$inst = new stdClass(); $inst->context = array( 'bwsEditorPreview' => true );
bws_base_text_callback( array( 'key' => 'nonexistent_key_xyz' ), array( 'blockName' => 'generateblocks/text' ), $inst );
```

| # | Case | Expected |
|---|---|---|
| T6.1 | empty key, `bwsEditorPreview` on | preview label renders (bracket placeholder, e.g. `['nonexistent_key_xyz']`), NOT blank |
| T6.2 | real key, `bwsEditorPreview` on | real value, no placeholder |
| T6.3 | empty key, no preview context (front end) | empty string (no placeholder leak) |

## T7 — src:ref list mode (1.14.0 fix lives in the moved code)

| # | Tag (on `/matrix-post-meta/`) | Expected |
|---|---|---|
| T7.1 | `{{text src:ref\|ref:related_staff\|use:title}}` | `Jane Partner` — default limit 1, first target only |
| T7.2 | `{{text src:ref\|ref:related_staff\|use:title\|limit:5}}` | `Jane Partner, Tom Associate` — ALL targets listed |
| T7.3 | `{{text src:ref\|ref:related_staff\|use:title\|limit:5\|linkTo:permalink}}` | `Jane Partner, Tom Associate` — **NO anchor** (multi-result) |
| T7.4 | `{{text src:ref\|ref:related_staff\|use:title\|linkTo:permalink}}` | `Jane Partner` wrapped in Jane's staff permalink (single result → post wrap) |

---

## T8 — user-analog arm (bare tag on an author archive, 1.16.0 FW-48 seam half + 1.17.0 `try_` leg)

**render-tag-only exception** (same as T4's archive-context rule): these rows need an AUTHOR
archive as ambient context, which no fixture page's GB blocks can supply — state per CLAUDE.md
§Development. Context: `/author/fixture-author/`.

The exception was **re-examined and KEPT** when the `try_` rows landed (#108). A browsable Block
Element would need a new post type plus display-rule meta the seeder does not handle, and would
put this fix's only evidence on the surface subject to LiteSpeed caching and
`opcache.revalidate_freq` — both of which `render-tag` is exempt from. Worse, an Element rendered
INSIDE the archive loop resolves the loop POST, so its rows would take the post arm and read
correctly while testing a different arm than the one under test. Any future browsable surface has
to sit outside the loop, and the blueprint has to say so.

| # | Tag (on `/author/fixture-author/`) | Expected |
|---|---|---|
| T8.1 | `{{text use:title}}` | `Fixture Author` — display-name analog |
| T8.2 | `{{text key:description}}` | the fixture bio — key-mode user meta read |
| T8.3 | `{{text key:unseeded_key\|fallback:NOPE}}` | `NOPE` — key miss emits the fallback (term-core-shaped) |
| T8.4 | `{{text use:title\|linkTo:permalink}}` | `Fixture Author` wrapped in the author-archive URL (user entity type) |
| T8.5 | `{{join use:title}}` | `Fixture Author` — join slot absorbs the user arm through the seam |
| T8.6 | `{{try_text use:title}}` | `Fixture Author` — the [I6] parity row. A `try_` slot takes its OWN dispatcher's user arm (`try_user_fn`), never the absorb seam T8.5 rides; empty here through 1.16.0 |
| T8.7 | `{{try_text key:description}}` | the fixture bio — key-mode parity, T8.2's twin. Not optional scope: the leg's renderer performs the `get_user_meta()` read, and suppressing it would take code base `{{text}}` does not have |
| T8.8 | `{{try_text A:key(unseeded_key)\|B:use(title)}}` | `Fixture Author` — **attempt fallthrough**: a user key MISS must skip to the next attempt, not consume the tag. Safe because `$eval_opts` strips `fallback`/`fallback_text` before slot options are built; nothing else pins this |
| T8.9 | `{{try_title}}` | `Fixture Author` — second template |
| T8.10 | `{{try_content}}` | the fixture bio — third template |
| T8.11 | `{{try_text use:title\|linkTo:permalink}}` | `<a href="…/author/fixture-author/">Fixture Author</a>` — the arm table's `link:'user'` column's only evidence anywhere |
| T8.12 | `{{try_permalink}}` / `{{try_image}}` / `{{try_datetime_single key:event_date}}` | **empty — unchanged**, captured BEFORE (`main@e1bff07`) as well as after. These six families carry no `try_user_fn` and must keep taking the fn-absent fallthrough to the post arm, which is their only route to the mode-2b flat-repeater-row gate. Without the before-capture the row is unfalsifiable — an empty result proves nothing on its own |

T8.1–T8.6 verified 2026-07-21 (build f6f8d1e). T8.6 flipped and T8.7–T8.12 added + verified
2026-08-17 (#108), all via `render-tag`; T8.12's before-values captured on a stashed tree at
`main@e1bff07`.

**Coverage note.** T8.6–T8.12 are the ONLY pins on the `try_` user leg. The wiring — which
template carries which `try_*_fn`, and the fn-absent fallthrough — lives inside
`generate_base_try_tags()`'s callback closure, which no pure harness reaches: the arm-table
harness sees data, `control-order-test.php` sees registration. Adding a `try_user_fn` to a
seventh template or reordering that fallthrough fails nothing. Accepted for 1.17.0; the
extraction is noted on FW-43's row.

## Fail triage

- **T1.2/T3.3/T4.2/T7.4 value right but unlinked:** shell wrap gate — `link_id`/`link_type` not
  threading out of `bws_base_text_resolve_value` for that arm.
- **T3.2/T7.3 anchor around a joined list:** multi-result branch leaked a non-zero `link_id` —
  the `1 === count($out)` single-result guard regressed.
- **Site rows (src-site R0.2) unlinked:** sentinel `link_id = 1` lost — site arm must return
  `{link_id:1, link_type:'site'}`.
- **T4.x reads a post value on the term archive:** term-analog arm bypassed — factory/ambient
  detection regression, see traversal pipeline (CONTEXT.md L1).
- **T5.1 empty:** `'0'` coerced to empty somewhere in the seam — violates the absorb invariant
  (PHPDoc on `bws_base_text_resolve_value`); check nothing re-decides emptiness before the
  hooks.php `'0'`→`'0 '` guard.
- **T7.2 shows only Jane:** src:ref list regression (the 1.14.0 fix — plural traversal
  `bws_base_post_ids_from_source` not honored).
- **T6.1 blank / T6.3 leaks a placeholder on the front end:** preview fallback moved to the shell
  in 1.14.1 — the `$is_preview` branch must fire ONLY when the resolve returned empty AND the
  context flag is set. Blank T6.1 = branch not reached; T6.3 leak = `$is_preview` true without the
  flag.
