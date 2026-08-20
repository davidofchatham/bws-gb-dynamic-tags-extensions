# Plan: `src:site` Stage C — the `datetime_` try_ slots

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

**Status: COMPLETE. Shipped 1.17.1 (FW-84), and this file archives with it.** The two closures and
the two flag flips landed as written below; base-vs-`try_` byte parity was checked on the testbed
across five shapes (bare site read, custom format over the ACF return format, the range pair,
`linkTo` site-sentinel wrap, and the slot-1-misses fallthrough). Matrix rows are
`src-site-test-matrix.md` §R8, visible as the "Site R8" block group on `/matrix-post-meta/`.

**One thing the build learned that the plan did not predict:** datetime field keys are TAG-LEVEL, so
a fallthrough row varies only the SOURCE and the winning slot reads the one tag-level key from
whichever store it names. `2-key:` does not exist here. The per-slot fold for datetime keys stays
deferred (FW-81).

**What it was before that:**

**~~Status: ONE FAMILY LEFT.~~** Stage A (base-tag `src:site`) shipped 1.9.0. Stage B (#26 derive) +
Stage C for email/phone shipped 1.11.0 (#32). Stage C for text/content/title/permalink/image shipped
1.15.0 as **FW-4**. This stub now holds ONLY `src:site` in the `datetime_single` /
`datetime_range` try_ slots.

**Tracker row:** `docs/future-work.md` FW-84. That row is pointer-only; this is the detail home.

**Rescoped 2026-08-20.** The body through 1.17.0 still claimed four families remained
(datetime/text/image/content) and described a per-family build (items 2-4 below) that FW-4's generic
`try_site_fn` hook made unnecessary. Three of those four shipped without this file being touched.
What is written here now is what the code actually still lacks.

- Stage A build record: [`archive/src-site-unified-source.md`](archive/src-site-unified-source.md)
  (NB: resolver detail there predates B5/B7 Model-B corrections — current behavior is
  `docs/tag-reference.md` §Site Source + `CONTEXT.md` I1-I4, NOT the archived plan body).
- Stage B + email/phone Stage C build record:
  [`docs/design-history/try-email-phone-and-slot-derivation.md`](docs/design-history/try-email-phone-and-slot-derivation.md).
- FW-4 (the five closures) build record: CHANGELOG 1.15.0 + the `try_site_fn` PHPDoc on
  `TagTemplateRegistry`.

**Goal (remaining):** let `src:site` be selectable as a `try_` slot source on `try_datetime_single`
and `try_datetime_range`, so a datetime try_ chain can fall back to a site-wide date field.

## What the two datetime templates lack

Both descriptors in `includes/tags/base-tags.php` carry **no `try_site_fn` and no
`try_allow_site_slot`** — `site` is therefore still filtered out of their derived slot src lists by
the #26 default.

They cannot ride FW-4's documented fallback leg. The registry falls back to `try_core_fn( 0, … )`
when a template declares no `try_site_fn`; for datetime that reads post 0, not the site store.

## What is already built and gets reused

| Piece | Where | Note |
|---|---|---|
| slot-resolver site arm | `generate_base_try_tags()` (#32) | shared; routes `src:site` past srcTermIn, before the post path |
| `try_site_fn` descriptor leg | `TagTemplateRegistry` (FW-4) | generic; the hook the five closures hang on |
| datetime `'option'` object-id fork (DT-1) | `bws_datetime_single_core` / `bws_datetime_range_core` via `datetime-helpers.php` | the base tags already read site date fields this way |
| site link-entity for datetime | `datetime-tags.php` site branch | sentinel `('site', 1)` link-wrap, matching the other families' I6/C9 parity |
| site arm's list + link behavior | `includes/helpers/try-slot-arms.php` | the table already documents the sentinel as applying on the `try_site_fn` leg — no table change needed |

## Remaining work

1. `try_site_fn` on `datetime_single` — closure delegating to `bws_datetime_single_core( 'option', … )`,
   mirroring the base tag's own site branch. **NOT** a closure over `bws_site_resolve_value()`: that
   helper is tag-dispatched and has no datetime arm, and giving it one would duplicate the format /
   ordered-key-list handling the core already owns.
2. `try_site_fn` on `datetime_range` — same, over `bws_datetime_range_core( 'option', … )`.
3. Flip `try_allow_site_slot => true` on both — **only after 1 and 2**, per the guard-rail below.
4. `src-site-test-matrix.md` rows (the FW-4 rows are R7) + the mandatory visible GB blocks on the
   testbed page.

## GUARD-RAIL (still live for these two)

`site` MUST NOT be re-allowed (step 3) before that family's `try_site_fn` exists. The slot-resolver
arm routes `src:site` at the descriptor's site leg; with no `try_site_fn` the registry falls through
to `try_core_fn( 0, … )`, which reads a `$post_id`-based value → wrong/empty. This is a per-family
gate, not the old global "never expose site in any slot" — the other seven families have passed it.

## Why this is NOT FW-3

FW-3's remaining half (b) routes datetime **value reads** through the L1/L2 seam
(`bws_resolve_field_values`) instead of the cores, and is gated on an open decision about
field-object formats through that seam. This row is about which **source** a slot accepts, and the
site route never reaches the seam — `bws_site_resolve_value()` reads `bws_site_read_option()`
directly, and the datetime closures above read the cores directly. FW-3's gate does not gate this.

Soft coupling only: if FW-3 half (b) lands first, closures written against the cores get rewritten
against the seam. FW-81 (the `datetime_single` + `datetime_range` collapse) would make this one
closure instead of two.

## Related deferred threads

- **#28** — `src:site` → ref (resolve an entity from a site-stored relational field); `ref` currently
  suppressed under site as "not wired," not "never."
- Per-slot try_ `use` for site values (multislot-feed decouple) — see `deferred_features.md` (memory).
