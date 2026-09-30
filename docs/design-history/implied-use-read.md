# Archive: a field token implies the `use` read — FW-142 (SHIPPED 1.21.0)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md` I3, or the PHPDoc at the enforcing site (`bws_use_effective()` and `BWS_USE_IMPLIED_BY_TOKEN` in `includes/helpers/registration-helpers.php`; the editor twin `assets/js/use-read-control.js`, fed by `bws_use_read_rules()`).

**Provenance.** The build spec for FW-142, decided by grilling on 2026-09-24 and built across three tickets on the `fw-142-implied-use` branch, 2026-09-24 to 2026-09-25: 01 the read rule (`fb2bede`), 02 the `use` select wrapper and stale-token delete (`477ae91`), 03 on-mount normalization (`ab8c4e5`). It lived at `.scratch/fw-142-implied-use/spec.md` and was committed whole after the branch merged as [PR #141](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/pull/141), which is its published form. The helper the spec leaves as "name TBD" shipped as `bws_use_effective()`. Its three ticket files stayed private and were not committed; each ticket's measurements are in its commit body.

---

Status: ready-for-agent (grilled 2026-09-24). Tracker row: `docs/future-work.md` FW-142. Detail home: `.scratch/plans/combined-option-controls.md` §The combined CONTROL.

## Problem

A base tag serializes `use:key|key:foo` on `{{content}}` even though `key:foo` alone already says the read, because `content`'s `use` enum leads with its analog and GB's native select shows that default whenever `use` is absent. Slots already avoid this (a bare `key(x)` is the keyed read). The same control never drops a `key` left behind when `use` leaves key-mode. FW-141 would add a second instance (`use:fixed|fixed:Varsity`), so this lands first, as its own PR.

## Decisions (grill, 2026-09-24)

- **D1 — Read rule is GENERAL.** Absent `use` + a field token present = that token's read. One token→mode map, one row today (`key → key`); FW-141 adds `fixed`. Covers `{{text}}`/`{{image}}` too (no behavior change there, their default is already key-mode). `linkTo`/`linkKey` excluded: FW-20 owns that cluster.
- **D2 — Stale-token drop is IN.** When `use` leaves a mode, the control deletes that mode's field token.
- **D3 — Explicit `use` always wins.** Inference fires only when `use` is absent; an explicit non-matching `use` ignores the field token (today's behavior). An empty token value (`key:`) counts as absent, and an empty `use` (`''`) counts as absent (the `BWS_USE_STRIPPED_DEFAULTS` @invariant: dispatchers canonicalize `''` before branching).
- **D4 — Stored wire normalizes ON MOUNT; no converter entry.** Old and new wire render identically.
- **D5 — One PHP helper owns the rule**, beside `bws_use_stripped_default()` in `registration-helpers.php`. Every `?? 'content'` / `?? 'key'` read site that branches on `use`, the site dispatcher, and the preview route through it. This is the axis's one enforcing site; other docs state the consequence only.
- **D6 — The old wire is LEGACY because current emits no longer serialize redundant tokens**, not because anything was renamed. That is what licenses the mount write (D4).
- **D7 — `{{image}}`'s default is NOT flipped here.** Filed as FW-143. FW-142 rewrites the `BWS_USE_STRIPPED_DEFAULTS` PHPDoc and the tag-reference "Strip-default caveat" to say the stale-key reason no longer holds and the image flip is tracked in FW-143; `{{text}}` stays key-mode on its own merits (primarily a meta read).
- **D8 — Control shape: WRAP, don't replace.** A `tagSpecificControls` filter clones GB's `use` select with the DERIVED value and a wrapped `onChange` (element-in/element-out, as `bws-field-combo` does). `editor-conditional-options.js` evaluates conditions on `use` against the same derived value, which dissolves the `key` `show_if: use:key` circularity. One JS derive function is shared by both. The token→mode map and per-tag stripped default reach JS through an inline built from the PHP owner; no JS copy of either.
- **D9 — Preview reads identically** for implied and explicit mode (through the D5 helper).
- **D10 — Delivery:** branch + PR; this spec is published in the PR body.

## Interfaces

- **PHP:** a helper (name TBD at build, `bws_`-prefixed) taking `(string $tag, array $options): string` that returns the effective `use`: explicit non-empty `use` → it; else the first field token present with a non-empty value → its mode; else `bws_use_stripped_default( $tag )`.
- **JS:** a derive function with the same contract, fed by an inline (`window.bws…`, mirroring `window.bwsChainKinds`) carrying the token→mode map and the per-tag stripped defaults.
- **Write (control):** picking a mode writes `use` only when it differs from what the tokens would imply without it; deletes (`delete`, never `''`) the field token of a mode being left.

## Mount normalization (D4, D6)

| Stored | Becomes | Why |
|---|---|---|
| `{{content use:key\|key:foo}}` | `{{content key:foo}}` | `use` restates what `key` implies |
| `use:excerpt\|key:foo` | `use:excerpt` | stale `key`, render already ignores it (D3) |
| `{{text use:key\|key:foo}}` (hand-typed) | `{{text key:foo}}` | general rule (D1) |
| `{{content use:key}}` (no key) | unchanged | keyed-pending, same as slot `use(key)` |

Every rewrite drops a token the render ignores or that restates the implied mode, so rendered output does not move. Decidable from filter state because every BWS tag registers `'supports' => array()`, so `key` stays in `extraTagParams` (`docs/gb-constraints.md` §Reserved Option Keys, corrected 2026-09-24). A mount write persists only when the author saves the modal.

## Docs to move in the PR

- `docs/editor-controls.md` ~L461 (the on-mount licence rule): widen "legacy" to cover a token current emits no longer write because it is redundant; decidability condition unchanged.
- `docs/editor-controls.md` ~L468: rewrite the `stripDefaultRoot` sentence. Code (`slot-fold-control.js` `stripDefaultRoot`, 389da3d, 2026-08-06) is a WRITE-time strip of a token that restates the default; the doc (a8efe7c, 2026-08-19) claims it is the same reason as the `as-size` back-out. Doc moves toward code (user, 2026-09-24), with a note at the site saying why, per CLAUDE.md's drift rule. Cite it as write-time precedent for redundant-token omission.
- `docs/tag-reference.md` "Strip-default caveat" + `BWS_USE_STRIPPED_DEFAULTS` PHPDoc (D7).
- `docs/tag-reference.md`: state the consequence of the read rule where `use` is catalogued (not the axis, D5).
- `docs/editor-controls.md`: the wrapper mechanism (D8).
- `docs/editor-tag-previews.md` if a row changes (D9 says it should not).
- CHANGELOG entry.

## Tasks

1. PHP helper + route the read sites (`base-tags.php` ~1136/1957/1986/1998, `base-shared.php` ~1602/1815, the site dispatcher ~1828, `preview-helpers.php` ~392/1384 — re-grep at build).
2. Extend `tools/test/use-stripped-default-test.php` to pin the helper (D1, D3 incl. `''` cases).
3. Inline + JS derive function; `use` select wrapper; `editor-conditional-options.js` derived-`use` evaluation; stale-token delete; mount normalization.
4. Node pin for the derive function + wrapper in `tools/test/editor-filter-chain-test.js`.
5. `preview-label-test.php` row: implied vs explicit identical.
6. Docs (above) + CHANGELOG.
7. Testbed: `{{content key:foo}}` renders the field; stored `use:key|key:foo` still renders; update triggers for field-option leaf / stripped-`use`-default / invisible editor control / preview text rows in CLAUDE.md.

## Out of scope

`linkTo`/`linkKey` (FW-20) · the image default flip (FW-143) · the analog rename to `default` (FW-80) · FW-141's `fixed` row (adds itself to the map).
