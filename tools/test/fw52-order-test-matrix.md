# FW-52 serialization-order test matrix

Editor-time checks for FW-52 (decouple serialization order from control order). Unlike
the other matrices, these rows are **NOT front-end render checks** — the serialization
order is a property of the SAVED TAG STRING, re-ordered by the editor-JS normalizer when
a tag is opened in the GB modal. Output is unchanged. So each row is verified by
**opening the block in the GB editor and reading the tag string**, not by curling the
page.

- **Pure algorithm** (group ranks, format-front, N- slot contiguity, as-reset front-pull):
  `php tools/test/serialization-order-test.php` (18 cases, no WP). That harness pins the
  reorder contract; this matrix confirms the LIVE editor round-trip the harness cannot
  reach.
- **Visible blocks:** the O-rows below are generated as browsable/editable GB blocks on
  `matrix-post-meta` (blocks.php `matrix_post_meta` builder, sections `FW-52 O1/O2/O3`).
  `feature_image` (a seeded image attachment, manifest v5) backs the image reads.

**How to check a row:** open `/matrix-post-meta/` in the block editor, select the block,
open its dynamic-tag modal, and read the tag string shown live. It should match the
**Expected string** column (the normalizer re-sorted the authored, scrambled input). The
canonical serialization order is `format → source(per-slot, contiguous) → link →
fallback`; within a source slot `src → ref → srcTermIn → limit → sep → use → key`. See
[`docs/tag-reference.md` §Option order](../../docs/tag-reference.md#option-order).

Reseed before checking: `bin/seed.sh testbed core-structures` (from the wp-litespeed env),
then hard-refresh the editor (not the cached front end).

## O1 — image `as` front-pull

| Row | Authored (scrambled) input | Expected string on open | What it proves |
|---|---|---|---|
| O1.1 | `{{image use:key\|key:feature_image\|as:url}}` | `{{image as:url\|src:...\|use:key\|key:feature_image}}` | `as` (format) lifts to the FRONT though authored/registered control-late |
| O1.2 | `{{image key:feature_image\|use:key\|as:alt}}` | `{{image as:alt\|use:key\|key:feature_image}}` | nullary return mode still leads the string |
| O1.3 | `{{image key:feature_image\|use:key\|as:id}}` | `{{image as:id\|use:key\|key:feature_image}}` | same |
| O1.4 | `{{image key:feature_image\|use:key\|as:caption}}` | `{{image as:caption\|use:key\|key:feature_image}}` | same |

**Decisive as-reset case (do this by hand in O1.1):** in the modal, change Return type to
another mode and back to URL (or clear it and reset). GB re-appends `as` LAST in the
object; on the next render the normalizer must pull it back to lead. Confirm the string
still shows `as:` first.

## O2 — multi-slot `try_text` contiguity

| Row | Authored (scrambled) input | Expected string on open | What it proves |
|---|---|---|---|
| O2.1 | `{{try_text 3-use:title\|key:name_first\|use:key\|2-src:site\|2-use:key\|2-key:blogname\|3-src:current}}` | `{{try_text use:key\|key:name_first\|2-src:site\|2-use:key\|2-key:blogname\|3-src:current\|3-use:title}}` | each slot's keys group contiguously; slots ascend (1- then 2- then 3-) |
| O2.2 | `{{try_text use:key\|2-src:site\|2-use:title\|key:name_last}}` | `{{try_text use:key\|key:name_last\|2-src:site\|2-use:title}}` | a slot-1 key authored globally-last (`key:name_last`) rejoins its slot-1 siblings (reset-scatter fix) |

**Reveal check (editor-only, no string):** slots 2+ appear progressively as earlier slots
are configured; slot 2+ `N-key` needs `N-use` set (a bare `N-key:` renders empty — that is
a config gap, not a fallthrough bug; see FW-51).

## O3 — datetime format-front + link after source

| Row | Authored (scrambled) input | Expected string on open | What it proves |
|---|---|---|---|
| O3.1 | `{{datetime_single key:event_datetime\|linkTo:permalink\|as:date\|format:F j, Y\|fallback:TBA}}` | `{{datetime_single as:date\|format:F j, Y\|src:...\|key:event_datetime\|linkTo:permalink\|fallback:TBA}}` | format block leads; link after source; fallback last |
| O3.2 | `{{datetime_range startKey:event_start_date\|endKey:event_end_date\|linkTo:permalink\|as:date\|rangeSep:–}}` | `{{datetime_range as:date\|rangeSep:–\|src:...\|startKey:event_start_date\|endKey:event_end_date\|linkTo:permalink}}` | format block (`as`, `rangeSep`) leads; start/end keys in source; link after |

(`src:...` = whatever base source token is present, or none when the default `current` is
stripped. The point of the row is the RELATIVE order, not the presence of `src`.)

## Notes

- The exact `src` token may be absent when the default `current` source is stripped at
  registration (`bws_strip_default_select_values`) — the expected strings above show
  `src:...` where a non-default source would sit; a bare `{{image}}` with default source
  simply omits it, and the surrounding order still holds.
- These rows do not need term/ref state, so they live on `matrix-post-meta` (the current
  post carries `feature_image` + `event_*` + `name_*`).
- If a row's live string does NOT match, first confirm the normalizer script loaded
  (`bws-dynamic-tags-order-normalizer` in the editor page source) and that
  `serialization-order-test.php` still passes — a pure-harness pass with a live mismatch
  points at the JS port or the `bws-`-type gate, not the algorithm.
