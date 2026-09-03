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

Four of these six rows are **first-party GB tags**, which is why they are here: T5.2, T5.4 and
T5.5 come back with the zero intact, T5.3 comes back empty. That is what the guard produces, observed —
what it applies to is decided at `includes/hooks.php`, and this section neither states nor extends
that. Measured 2026-08-24 against GenerateBlocks 2.4.1 / GB Pro 2.7.0.

**T5.1b is the one row here whose subject is not the guard.** A zero that survives the guard has
one more hand-off to survive, the one to GB's output pipeline, and that is decided at
[`gb-output-boundary.php`](../../includes/helpers/gb-output-boundary.php). Added 2026-08-26,
measured against GB Query Enhancements 1.3.0.

| # | Tag (on `/matrix-post-meta/`) | Expected |
|---|---|---|
| T5.1 | `{{text key:bws_zero_probe}}` | renders `0` — must NOT be empty. (`render-tag` shows `0` plus the pad byte; the hooks.php `'0'`→`'0 '` falsy guard fires on both render paths, `verify.php` pins that byte for byte.) |
| T5.1b | `{{text key:bws_zero_probe\|fallback:REPLACED}}` | renders `0`, **not** `REPLACED` — the same field and the same pad byte as T5.1, with a fallback attached. A co-resident extension re-applies `fallback` whenever the output tests `empty()`, and `'0'` is empty to PHP, so this rendered `REPLACED` until the output boundary stopped publishing a consumed `fallback` to `generateblocks_dynamic_tag_output`. **What keeps it from being vacuous is [`fold-test-matrix.md`](fold-test-matrix.md) §F11b.1**, on this same page: a `fallback` that fires on a genuinely empty read. Without a row of that shape somewhere, a `fallback` that had stopped being read at all would also render `0` here. |
| T5.2 | `{{comments_count none:0}}` | renders `0` — GB **core** tag, zero intact. This page has no comments, so the `none` label prints and it is a bare `'0'`. This row is the one that blanks if the guard stops covering GB's own tags. |
| T5.3 | `{{post_meta key:bws_zero_probe}}` | **EMPTY** — same field as T5.1, read through GB's own meta tag, which comes back empty for a zero. Measured: `required:false` does not recover it either. The T5.1/T5.3 disagreement is GB's, not ours. |
| T5.4 | `{{loop_index zeroBased:1}}`, inside a 2-item `staff` query loop | `0` then `1` — **the pin.** GB Pro returns a bare `'0'` on item 1, with no author setup beyond ticking the zero-based checkbox. If the guard stops covering this tag, GB's required-bail takes the whole first item with it. |
| T5.5 | `{{loop_item key:qty}}`, inside the `team_members` repeater loop | `0` then `4` — the second GB Pro tag here, and the one that shows the guard acting per **value**: two rows of one loop, one padded and one untouched. |

A fourth tag not ours reaches the guard, `{{term_count}}` in a term loop, and its row is not here:
it needs a term query loop, so it lives with the loop-matrix rows that now exist
([`loop-test-matrix.md`](loop-test-matrix.md) §QL3, on `/matrix-loops/`). Four measured, four pinned —
the dated enumeration is at the guard's own PHPDoc.

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
archive as ambient context, which no fixture page's GB blocks can supply — state per `docs/testbed.md`. Context: `/author/fixture-author/`.

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
| T8.12 | `{{try_permalink}}` / `{{try_image}}` / `{{try_datetime_single key:event_date}}` | **empty — unchanged**, captured BEFORE (`main@e1bff07`) as well as after. These six families carry no `try_user_fn` and must keep taking the fn-absent fallthrough to the post arm, which is their only route to the no-entity loop read at the foot of the slot loop — the `[ false ]` branch that lets the field read serve itself off the query-loop item. Without the before-capture the row is unfalsifiable — an empty result proves nothing on its own |

T8.1–T8.6 verified 2026-07-21 (build f6f8d1e). T8.6 flipped and T8.7–T8.12 added + verified
2026-08-17 (#108), all via `render-tag`; T8.12's before-values captured on a stashed tree at
`main@e1bff07`.

**Coverage note.** T8.6–T8.12 are the ONLY pins on the `try_` user leg. The wiring — which
template carries which `try_*_fn`, and the fn-absent fallthrough — lives inside
`generate_base_try_tags()`'s callback closure, which no pure harness reaches: the arm-table
harness sees data, `control-order-test.php` sees registration. Adding a `try_user_fn` to a
seventh template or reordering that fallthrough fails nothing. Accepted for 1.17.0; the
extraction is noted on FW-43's row. The same gap covers the `try_query_fn` leg (T9 below + `fold-test-matrix.md` §F19 are its pins).

---

## T9 — query-context arm of the absorb seam (bare tag on the five entity-less contexts, 1.19.0 FW-9)

**Mostly render-tag** (T4/T8's archive-context rule) — but unlike T8 these rows DO have a visible surface: the C-element (`context-test-matrix.md`, "VISIBLE SINCE 2026-08-29") renders C-X1 (`{{text use:title}}`) and C-X2 (the `try_text` composition) on every query-context page, and the page snapshots pin them. Context here: `/staff/` (PTA); the per-context VALUES are `context-test-matrix.md`'s business — these rows pin the SEAM's dispatch, one context standing for the five.

| # | Tag (on `/staff/`) | Expected |
|---|---|---|
| T9.1 | `{{text use:title}}` | `Staff` — same value as bare `{{title}}` there (the absorb seam and the title callback must agree) |
| T9.2 | `{{text key:role}}` | empty — key-mode has no entity to read on a query context |
| T9.3 | `{{text key:role\|fallback:NOPE}}` | `NOPE` — the empty claim still routes through the callback's own fallback tail |
| T9.4 | `{{join use:title}}` | `Staff` — a join slot absorbs the query-context arm through the seam |
| T9.5 | `{{text use:title\|linkTo:permalink}}` | `Staff` **unwrapped** — a query context has no link identity (`bws_source_link_identity()` maps it to null), so `linkTo` configures nothing |

Verified 2026-08-29 via `render-tag`. The `try_` half lives in `fold-test-matrix.md` §F19.

## T10 — modifier family (`term_`/`view_`/`fixture_`) `use` dispatch ([#88](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/88))

Different code path from the rest of this file — `register_modifier()`/`make_modifier_callback()`
(class-tag-template-registry.php), not `bws_base_text_resolve_value()`/`bws_base_text_callback()`.
Lives here rather than a third file for the same reason `content-test-matrix.md` §CT7 does: same
tag family this matrix already covers, defect reproduces identically across `term_`/`view_`/
`fixture_` (`term_`/`fixture_` are the two live in this repo). `content-test-matrix.md` §CT7 is
the sibling row set for `{{content}}`'s modifier family — same fix, same commit, same cause.

Through 1.19.1, `register_modifier()` wired `term_text`'s `post_fn`/`term_fn` straight to
`bws_post_custom_text_core`/`bws_term_custom_text_core`, which read `$options['key']` only and
never `$options['use']` — so `use:title` rendered empty on every `term_`/`view_`/`fixture_` text
tag, though the base `{{text}}` tag (which dispatches through the seam this file covers) was
unaffected. Fixed by pointing `term_fn`/`post_fn` at the same `try_text_term_dispatch`/
`try_text_post_dispatch` functions the `try_` family already used correctly.

| # | Tag | Expected |
|---|---|---|
| T10.1 | `{{term_text use:title}}` on `/department/support/` | `Support` — the term's own title. **Before the fix: empty.** Render-tag-only: a term-archive ambient context, same exception as T4 above |
| T10.2 | `{{fixture_text use:title}}` on `/matrix-fixture-roots/` | `Fixture Root Entity` — same defect, the class route. **Before the fix: empty.** **Visible** — [`registered-roots-test-matrix.md`](registered-roots-test-matrix.md) §FR7.1, `blocks.php`'s `matrix_fixture_roots` builder |

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
