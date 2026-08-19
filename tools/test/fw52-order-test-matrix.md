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
- **Visible blocks:** the O1-O3 rows below are generated as browsable/editable GB blocks on
  `matrix-post-meta` (blocks.php `matrix_post_meta` builder, sections `FW-52 O1/O2/O3`).
  `feature_image` (a seeded image attachment, manifest v5) backs the image reads. **O4
  (the `as`+`size` fold) is BUILT (v1.16.0)** but its visible GB blocks are not yet seeded
  — author + reseed them at eyeball time.

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
| O1.1 | `{{image use:key\|key:feature_image\|as:url}}` | `{{image as:url,full\|src:...\|use:key\|key:feature_image}}` | `as` (format) lifts to the FRONT; the composite writes the folded `url,full` on open |
| O1.2 | `{{image key:feature_image\|use:key\|as:alt}}` | `{{image as:alt\|use:key\|key:feature_image}}` | nullary return mode still leads the string |
| O1.3 | `{{image key:feature_image\|use:key\|as:id}}` | `{{image as:id\|use:key\|key:feature_image}}` | same |
| O1.4 | `{{image key:feature_image\|use:key\|as:caption}}` | `{{image as:caption\|use:key\|key:feature_image}}` | same |

**Decisive as-reset case (do this by hand in O1.1):** in the modal, change Return As to
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

## O4 — image `as`+`size` fold (Phase 3 — BUILT v1.16.0)

The `as`+`size` composite (`bws-as-size`) shipped in 1.16.0 (plan §Image `as`+`size`
unification): `size` left GB's native `image-size` support and folds into `as`'s value as a
comma second slot (`as:<mode>[,<size>]`), always-serialized. Pure parse + fold pinned by
`php tools/test/as-size-fold-test.php` (59 cases, incl. §A3-A5 on what the converter reports and in which order). The rows below are the LIVE editor
round-trip that harness can't reach.

**With the fold, O1.1's expected string is now `as:url,full`** (size arg always serialized) —
the O1.1 row below reflects this. O1.2-1.4 (nullary modes) stay bare.

**Visible GB blocks SEEDED** — the `FW-52 O4` section in `blocks.php` (O4.1 media block +
O4.2-O4.5, legacy-split wire on the migration rows) is authored + reseeded on `matrix-post-meta`.

The composite owns the whole `as` widget (mode dropdown + size dropdown) — GB's native
select would corrupt `url,full` on reopen. Verified by opening the block and reading the
live string + interacting with the two dropdowns.

**⚠ ON-OPEN vs CONVERTER — the fold does NOT happen on editor-open for a legacy `size:`
(finding 2026-07-23).** `size` is GB-RESERVED: GB destructures it out of `parsedTag.params`
into its OWN private `imageSize` state (`DynamicTagSelect.jsx:392,443`, ungated on support)
and re-serializes it (`:541`, also ungated). Our `bws-as-size` composite writes only
`extraTagParams` — it can NEITHER see nor delete GB's `imageSize`. So on open, a legacy
`{{image size:medium|as:url}}` renders `as:url` in the composite (size shows the *default*
`full`, ignoring the orphan) and GB keeps re-emitting the stray `size:medium`. The fold is
therefore **Tag-Converter-only** (the converter transforms the raw string BEFORE GB parses,
so it reaches the orphan). The `transform_callback` rows below are exercised through the
converter, NOT by opening the block. Editor-open only REORDERS (moves the tokens; `size`
ranks format,1 so it leads) — it does not fold. Tracked as
[issue #53](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/53).

**⚠ AND THE SAME VERDICT FOR THE BARE-`as:url` COMPLETION, by a different route (1.17.1).**
A tag saved before the fold spells the url return as a bare `as:url`; the canonical token carries
its size, so `bws_migrate_image_as_bare_url()` completes it to `as:url,full` (O4.8). That one
touches only `as`, our own option, so a mount effect in the composite IS reachable — it was built
and backed out. In the editor a legacy split tag is INDISTINGUISHABLE from a size-less one (the
filter receives only `{ state: extraTagParams, setState }`), so writing `url,full` on mount would
pin such a tag's render to `full` while the read seam had been resolving it at `medium`. What makes
the converter safe is ORDER — the fold entry is registered first, so a tag matching both never
reaches the completion — and the editor has nothing to order against. See
[`docs/editor-controls.md` §Why the image composite does NOT migrate on mount](../../docs/editor-controls.md).

| Row | Authored / action | Result | Path | What it proves |
|---|---|---|---|---|
| O4.1 | `{{image as:url,medium\|use:key\|key:feature_image}}` | `{{image as:url,medium\|src:...\|use:key\|key:feature_image}}` on open | editor open | already-folded `as` value survives; whole token leads (format group) |
| O4.2 | `{{image as:url\|use:key\|key:feature_image}}` (size arg absent) | composite renders url + size `full`; string writes `as:url,full` **once the author touches a control** (mount does not write) | editor open | default size arg (`full`) is the composite's rendered value; serialized on next edit. Mount-writing is REFUSED, and 1.17.1 re-decided it deliberately: see the second ⚠ above. The converter completes this tag (O4.8) |
| O4.3 | `{{image as:alt\|use:key\|key:feature_image}}` | `{{image as:alt\|use:key\|key:feature_image}}` | editor open | nullary return — NO size sub-slot (bare mode); size dropdown hidden in modal |
| O4.4 | migration: `{{image as:url\|size:medium\|use:key\|key:feature_image}}` (legacy split) | `{{image as:url,medium\|use:key\|key:feature_image}}` | **Tag Converter** (NOT on open — see ⚠ above) | `transform_callback` folds legacy `size:` into `as`; orphan `size:` token gone |
| O4.5 | migration: `{{image as:alt\|size:large\|key:feature_image\|use:key}}` (dead size on nullary) | `{{image as:alt\|use:key\|key:feature_image}}` | **Tag Converter** (NOT on open) | legacy `size:` on a nullary mode is DROPPED (was dead at render) |
| O4.6 | on-open of a legacy split: open `{{image size:medium\|as:url\|...}}` in the editor, do NOT run the converter | `size:medium` SURVIVES (reordered to lead), composite shows size `full` | editor open (negative) | pins the GB-private-`imageSize` limitation: open-fold is impossible; converter required |
| O4.7 | migration NEGATIVE: a page holding `{{image as:url,full\|src:refs,…\|use:featured}}`, `{{try_image as:url,full\|2-as:url}}`, `{{image as:alt}}`, `{{image as:url,medium\|…}}` — every shape already canonical — scan only | NOT listed at all | **Tag Converter** (scan) | the converter reports only work it will do. Pre-1.17.1 `as` was in the fold entry's `match_any_options`, so every image tag was listed for a fold the callback declines and the page relisted after every run |
| O4.8 | migration: a page holding a bare `{{image as:url\|src:refs,…\|use:featured}}` AND a legacy split `{{image as:url\|size:medium\|use:key\|key:feature_image}}` (plus the two unreportable shapes) — scan, migrate, RESCAN | listed with BOTH labels, migrate reports `option_count: 2`; the bare one becomes `as:url,full`, the split one becomes **`as:url,medium`**; rescan does NOT list the page | **Tag Converter** (scan → migrate → scan) | the completion entry writes the canonical token, AND entry ORDER holds: the fold is registered first, so a tag matching both keeps its authored size instead of being overwritten with `full` |

**Size-visible-only-on-`url` gate (editor-only, no string — do by hand in O4.1):** in the
modal, the size dropdown shows while Return As is URL. Change Return As to `alt` (or any
nullary) — the size dropdown must DISAPPEAR (hand-coded `show_if` inside the composite, not
declarative). Change back to URL — it reappears. Confirm `as:alt` in the string carries no
size, and the string never shows an interior `,,`.

**Size stash across mode-flip (editor-only, no serialized change — do by hand in O4.1):**
set size to `medium` on URL. Flip Return As URL→alt→URL. The size dropdown must RESTORE
`medium` (React-state stash, plan decision B), NOT reset to `full`. This is an editor
papercut guard only — the wire stays model-pure (`as:alt` serialized nullary during the
flip; nothing size-related persists while off `url`). A saved `{{image as:alt}}` reopened
shows the default `full` in the (then-hidden-until-url) size control — no stash survives a
reload, correct-by-construction.

**Fixture note:** these reuse the O1 `feature_image` attachment (manifest v5) on
`matrix-post-meta`. O4.1-O4.3 are seeded as visible GB blocks (the `FW-52 O4` section in
`blocks.php`, authored in scrambled order like O1); O4.4/O4.5/O4.6 carry the LEGACY split
wire (`size:` separate) — they are the converter round-trip (and, for O4.6, the negative
on-open control). Run the Tag Converter to exercise O4.4/O4.5; open the block WITHOUT
converting to reproduce O4.6.

**O4.7 and O4.8 are a stated exception to the visible-blocks mandate** (`docs/testbed.md` §MANDATORY —
also make them VISIBLE): what O4.7 pins is the ABSENCE of a row on the admin screen, and O4.8 has to
migrate its own content to get there. On the fixture page that would consume O4.4/O4.5/O4.6's
legacy wire and leave them unrepeatable without a reseed. So both run against a THROWAWAY DRAFT they
create and delete — the same posture as `verify-datetime-migration.php` — via
`TagConverter::scan()` / `migrate_post()` under `wp eval-file`, not by clicking the button.

Run 2026-08-19 on `testbed` (plugin 1.17.1). O4.7: not listed. O4.8: both labels, `option_count: 2`,
`as:url,full` and `as:url,medium` written, rescan clean.

MUTATION-verified in the same session, twice, because the two rows fail differently:

- Putting `as` back in the FOLD entry's `match_any_options` relisted the page after migrating, with
  `{{try_image}}` joining it — the reported 1.17.0 symptom exactly.
- Registering the COMPLETION entry before the fold turned `{{image as:url\|size:medium\|…}}` into
  `as:url,full`, silently losing the authored size, and dropped the fold's label from the scan. That
  is the whole reason the order is stated rather than incidental, and `as-size-fold-test.php` §A5
  fails on the same mutation (2 assertions) — so this row is the integration half of a pinned rule,
  not its only guard.

The PHP twin of the reporting rule is `as-size-fold-test.php` §A3/§A4 (match ⇔ change, shape by
shape); these rows are the half no harness reaches, because only here does the SCAN read stored
content and decide what to print.

## Notes

- The exact `src` token may be absent when the default `current` source is stripped at
  registration (`bws_prepare_registration_options`) — the expected strings above show
  `src:...` where a non-default source would sit; a bare `{{image}}` with default source
  simply omits it, and the surrounding order still holds.
- These rows do not need term/ref state, so they live on `matrix-post-meta` (the current
  post carries `feature_image` + `event_*` + `name_*`).
- If a row's live string does NOT match, first confirm the normalizer script loaded
  (`bws-dynamic-tags-order-normalizer` in the editor page source) and that
  `serialization-order-test.php` still passes — a pure-harness pass with a live mismatch
  points at the JS port or the `bws-`-type gate, not the algorithm.
