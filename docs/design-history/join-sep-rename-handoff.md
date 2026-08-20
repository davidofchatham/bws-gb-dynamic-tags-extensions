# Archive: rename `{{join}}` tag-level `sep` → `valueSep` + move `mode` to format group — SHIPPED 1.16.0

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

**Archived 2026-08-19** (found stale during the post-1.17.0 plan-file sweep — the top status line below
still read "CODE NOT WRITTEN" though the rename shipped in 1.16.0). Verified: CHANGELOG 1.16.0 §Changed
carries the rename verbatim per the draft below; `valueSep` is live throughout
`includes/tags/base-tags.php`, `includes/helpers/join-helpers.php`, `includes/helpers/preview-helpers.php`
and `includes/helpers/serialization-order.php`; the flagged-not-blocking annotation-wording question
(line ~67 below) resolved as "keep `(sep: …)`" — confirmed in the shipped
`bws_build_join_preview_label()`. **Read this as the record of how the rename was scoped, not as a
statement of how anything currently works.** Live homes: CHANGELOG 1.16.0; `docs/tag-reference.md` §join;
`docs/adr/0003-join-per-slot-limit-not-sep.md` (Update note); `docs/future-work.md` FW-44.

---

# Handoff: rename `{{join}}` tag-level `sep` → `valueSep` + move `mode` to format group

**Status:** DESIGN LOCKED, docs updated, CODE NOT WRITTEN. Pick up in a fresh session and implement.
**Origin:** FW-52 build (serialization-order normalizer). Surfaced 2026-07-23 while eyeballing the
built normalizer — join's format-group options scatter into the source group.
**Decided by:** user, 2026-07-23. All open questions below are CLOSED — do not relitigate.

---

## The defect

The FW-52 serialization normalizer groups options by a **shared, name-keyed** map
(`includes/helpers/serialization-order.php`, `bws_serialization_order_key_map()`). Join's
**tag-level assembly** options are its `format` group — `mode`, `sep`, `format` — but:

| Join option | Should be | Actually lands | Why |
|---|---|---|---|
| `mode` | format | **source** (unknown-key default) | not in the shared map at all |
| `sep` (assembly) | format | **source**, within-rank 4 | the map's `sep` entry is the **list-mode** separator (a genuine source-group key); join's tag-level `sep` shares the key NAME but is a format concern |
| `format` | format | format ✓ | already mapped to format group |

So only `format` sorts correctly; `mode` and `sep` scatter into the source group. Root cause: the
map keys by BARE option name and is global, but `sep` is **overloaded** — list-mode separator
(source) vs join assembly separator (format) — and `mode` is unmapped.

## The fix (LOCKED)

1. **Add `mode` → format group** to the shared map.
2. **Rename join's tag-level assembly `sep` → `valueSep`** (wire-visible). `valueSep` is
   format-group; the name is reusable (any future composite joining resolved values), user-facing
   clean, avoids "slot", and contrasts with list-mode `sep` (which stays `sep`). Chosen over
   `joinSep` (tag-bound), `fieldSep` (implies "between fields" — a slot value is not a field:
   could be `use:title` analog or a joined list; collides with the field-nomenclature guard),
   `slotSep` (user disliked "slot"), `itemSep` (collides with list-mode item mental model).
3. **Within-format rank** for join's trio: `mode → format → valueSep`. (User: not fussed about
   exact order; `format`/`valueSep` are mutually exclusive via `show_if mode`, so cosmetic.)
4. **NO migration, NO legacy read fallback.** `{{join}}` shipped 1.15.0 (2026-07-20), days old;
   the tag + option are new. Plain rename — an author who set a custom separator re-enters it.
   **This exactly matches the precedent already in 1.16.0** (CHANGELOG line ~12: `fallback_text` →
   `fallback` on join, same "one release old, new option, plain rename, no migration" reasoning).
   Dropping the legacy `?? $options['sep']` fallback is DELIBERATE — a fallback to `sep` would
   **collide with the source-level list `sep`** (the exact overloading we're removing). Do not add
   a fallback.

## CRITICAL scoping guardrail — rename ONLY the tag-level assembly `sep`

There are TWO `sep` keys in the codebase. Rename ONE:

- ✅ **RENAME:** join's **tag-level** assembly separator (`bws_get_join_options()`
  `$options['sep']`, `base-tags.php:954`). Between whole slot values. → `valueSep`.
- ❌ **DO NOT TOUCH:** the **list-mode / source-group** `sep` (base text/title/datetime/email/phone
  `sep`, the `bws_serialization_order_key_map()` `'sep' => array('source',4)` entry, list-mode docs
  at tag-reference lines 157/168-171/573/621, etc.). Joins repeated results of one field/source.
  Stays `sep`.

A slot-1 join wire is `{{join key:a|2-key:b|valueSep: / }}` — the per-slot `limit`/`sep`
(if ever added, see ADR 0003 below) are source-group and unaffected.

---

## Files to change (code — NEXT SESSION)

| File | Change |
|---|---|
| `includes/tags/base-tags.php` ~954 | `bws_get_join_options()`: option key `'sep'` → `'valueSep'`. Label stays "Separator" (user-facing label is fine; only the wire key changes). `show_if` on `mode` unchanged. `mode` and `format` defs unchanged. Registration ORDER: keep control-order `mode → valueSep → format` region as-is (format group renders in control order; the normalizer handles serialize order). |
| `includes/helpers/join-helpers.php` ~118 | `bws_join_assemble()`: read `$options['valueSep']` (drop `sep`, NO fallback). Update the `@param` doc line 107 (`mode, sep, format` → `mode, valueSep, format`). |
| `includes/helpers/preview-helpers.php` ~101 | `bws_build_join_preview_label()`: read `$options['valueSep']`. Update PHPDoc line 93 (`tag-level mode/sep/format/fallback` → `…valueSep…`). The preview annotation `(sep: "X")` → `(sep: "X")` label text — **keep the user-facing word "sep" in the annotation? DECIDE:** the editor annotation currently reads `[Join {fields} (sep: "X")]`. The wire key is now `valueSep` but the annotation is prose. Recommend keep `(sep: "X")` (short, clear) OR change to `(valueSep: "X")` for wire-parity. Flagged, not blocking — user's call at build. |
| `includes/helpers/serialization-order.php` | In `bws_serialization_order_key_map()`: (a) add `'mode' => array('format', 7)`; (b) add `'valueSep' => array('format', 8)`. Do NOT change the existing `'sep' => array('source',4)` (that's list-mode). Ranks 7/8 keep join's keys after the datetime format keys (0-6) — fine, join has no datetime format keys, and cross-tag rank collisions are harmless (a tag never has both). |

## Tests to update (NEXT SESSION)

| Harness | Change |
|---|---|
| `tools/test/join-template-test.php` | Any case feeding `'sep'` to the assemble/preview path → `'valueSep'`. (Template-mode cases use `format`, unaffected; separator-mode cases use the renamed key.) |
| `tools/test/serialization-order-test.php` | Add assertions: a join-shaped key list `[valueSep, mode, key, 2-key, fallback, format]` sorts to format-group-leads with `mode`/`valueSep`/`format` all ahead of source keys. Confirms the scatter is fixed. |
| `tools/test/join-test-matrix.md` | Any row with a tag-level `sep:` → `valueSep:`. **Also regenerate the visible GB blocks** (blocks.php) for any changed row — separator-mode join rows on the testbed carry the wire key (feedback_visible_matrix_rows; missed twice). Reseed + curl front end with `?nocache=$RANDOM`. |

Run after: `php tools/test/join-template-test.php` + `php tools/test/serialization-order-test.php`,
then the join matrix rows against the testbed (`bin/wp.sh testbed bws render-tag`).

## CHANGELOG (NEXT SESSION, no review/approval needed per house rules)

Add a Changed entry under `## [1.16.0] — unreleased`, parallel to the existing `fallback_text →
fallback` rename entry (~line 12). Net-delta note: `{{join}}`'s `sep` shipped in 1.15.0, so this
IS a real delta from a shipped release (not a within-branch introduce+rename → zero-delta case).
Draft (user-facing prose — surface for review per feedback_user_facing_prose_style):

> **`{{join}}`'s separator option is now named `valueSep`.** It shipped in 1.15.0 as `sep`, which
> clashed with the list-mode `sep` used by every other tag for a different job (joining repeated
> results of one field). Because 1.15.0 is one release old and the tag is new, this is a plain
> rename with no migration: a `{{join}}` tag with a custom separator set needs it re-entered.

---

## Downstream: ADR 0003 reframes (update, do NOT delete)

[`docs/adr/0003-join-per-slot-limit-not-sep.md`](../../../docs/adr/0003-join-per-slot-limit-not-sep.md)
rejected threading a per-slot inner `sep` for join v1 **because** a slot-1 bare `sep` would collide
with the tag-level assembly `sep` — and explicitly named renaming the assembly key (`sep → glue`)
as "a wire-visible change not worth taking for an edge affordance" (ADR line 18-20).

**This rename happens anyway, for a DIFFERENT reason** (FW-52 serialization-group correctness, not
the per-slot inner sep). Consequence: **the collision ADR 0003 sidestepped is now gone** — the
tag-level key is `valueSep`, so a per-slot `{N}-sep` is free to add later. The per-slot inner sep
stays DEFERRED (still an edge affordance, no evidence it's wanted), but its blocker dissolved.

ADR 0003 action at build: add a short **Update (1.16.0)** note — the assembly key was renamed to
`valueSep` under FW-52, so the collision rationale no longer applies; per-slot `{N}-sep` becomes a
clean addition if ever wanted. Keep the ADR (the v1 decision + `{N}-limit`-only shipping is still
historically accurate).

---

## Docs ALREADY updated this session (2026-07-23) — do NOT redo

- `docs/tag-reference.md` §join tag-level options table: `sep` → `valueSep`, noted format group.
- `docs/tag-reference.md` §join ADR-0003 reference line: reworded (collision rationale now moot).
- `docs/tag-reference.md` §join table links §Option order + labels each option's group; §Option
  order itself is the cross-tag model (no per-tag enumeration needed — join's table carries it).
- `docs/editor-tag-previews.md` §join assembly annotation: annotation prose reviewed (the `(sep:
  "X")` wording decision is flagged above for the build session).
- This handoff.

## Docs NOT updated (need the code — NEXT SESSION)

- CHANGELOG (draft above).
- ADR 0003 Update note (draft above).

## Docs updated this session, also

- `docs/future-work.md` FW-44 (join per-slot inner `{N}-sep`): blocker `decision:assembly-sep
  rename` marked DISSOLVED — the rename is happening here (→ `valueSep`), so `{N}-sep` no longer
  collides. FW-44 now blocked only on evidence it's wanted, not on the rename.
