# Archive: fixed-text read, author-entered text as a tag's output — FW-141 (SHIPPED 1.21.0)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md` I2, `docs/editor-tag-previews.md`, or the PHPDoc at the enforcing site (`BWS_USE_IMPLIED_BY_TOKEN` in `includes/helpers/registration-helpers.php`; `bws_fold_parse_slot()` in `includes/helpers/slot-fold.php`; `bws_contact_read_raw()` and `bws_try_post_has_source()` in `includes/helpers/field-helpers.php`).

**Provenance.** The build spec for FW-141, decided by grilling on 2026-09-28 and 2026-09-29 and built across six tickets on the `fw-141-fixed-read` branch, 2026-09-29 to 2026-09-30: 01 `{{text}}` (`5ddcbb1`), 02 `{{join}}` slots (`24bc177`), 03 `try_text` attempts (`84fc575`), 04 `{{email}}` / `{{phone}}` (`a3a7946`), 05 `try_email` / `try_phone` attempts (`c0e1848`, `78a6210`, `4962d77`), 06 the release docs sweep and review fixes. It lived at `.scratch/fw-141-fixed-read/spec.md` and was committed after the branch merged as [PR #142](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/pull/142), which is its published form. The spec body below is unchanged apart from the title line; the §Amendments and §Verification sections are the ones the PR body added (there between §Decisions and §Interfaces), reproduced as published and moved to the end. Its ticket files stayed private and were not committed; each ticket's measurements are in its commit body.

---

Status: ready-for-agent (grilled 2026-09-28 / 2026-09-29). Tracker row: `docs/future-work.md` FW-141. Detail home before this spec: `.scratch/plans/if-option.md` §Prerequisite + §Grill 2026-09-25 (Q9a/Q9b, G8, G10).

## Problem

Nothing shipped lets an author type a tag's OUTPUT. `fallback` is text entry but fires only when nothing resolves; every `use` value reads something. Wanted: a read whose value is the author's text, shown once per resolved source. Standalone it gives link text (`{{text fixed:Read more|linkTo:permalink}}` over related posts) and a fixed address or number per source; gated by FW-27's `when` it gives the driving case, a word shown when a boolean is true as one item of a `{{join}}`. FW-27 and FW-141 are built separately (either PR first) and ship in the same release.

## Decisions

- **D1 — Wire.** Base tag `fixed:Varsity`, NO brackets; `:` and `|` escape as `\:` / `\|`, written by `bws-format-input` (GB's `parse_options()` unescapes). Slot `fixed(Varsity)`; `fixed` joins `BWS_FOLD_FREEFORM` so the slot emit/parse applies the same `\:`/`\|` escape and the balance-aware bracket scope makes `,`/`;` inert. Known limits, same as `format` today: no `{`/`}`, no unbalanced closing bracket of the wrapping pair, no literal `\:`/`\|`.
- **D2 — Read rule.** `fixed => fixed` joins `BWS_USE_IMPLIED_BY_TOKEN`, AFTER `key`. No `use` is ever written beside it. `key` and `fixed` NEVER COEXIST on the wire (user, 2026-09-29): the editor never writes both, and a stored/hand-typed pair normalizes on mount to the effective read's token alone (the FW-142 D4 mount write). Until normalized, the read resolves `key` (map order). Serialization rank: beside `key` in the source group (user, 2026-09-29). Explicit `use` wins (FW-142 D3): `B:use(title);fixed(x)` is a title read, `fixed` ignored.
- **D3 — Stale-token strip.** Selecting any other `use` strips `fixed`. Base tags: FW-142's `normalize()` in `use-read-control.js` already deletes an implied token whose mode differs, generic over the map (verify, no new code expected). Slots: the slot-row control drops `fixed(…)` when the read changes; the slot emitter writes only the chosen read's token.
- **D4 — Reach.** `{{text}}`, `{{email}}`, `{{phone}}` base tags; `{{join}}` slots (all text today: the parser recognizes `BWS_FOLD_TYPES` tokens but nothing consumes a slot type at render); `try_text`, `try_email`, `try_phone` slots. OUT: `{{table}}` (unshipped, flat `{N}-use`, own enum), datetime (FW-81 reshapes its read), `content`, `image`, `title`, `permalink`, `term_text` (unregistered 1.21.0).
- **D5 — `{{email}}` / `{{phone}}` gain a `use` axis.** Enum: Meta/Option Field (`key`) + the fixed row; `BWS_USE_STRIPPED_DEFAULTS` rows `email => key`, `phone => key` (`control-order-test.php` requires enum ⇔ row). Brings them into `bws_use_effective()`, the `use` wrapper, stale-token delete and preview.
- **D6 — Labels, per family (deliberate divergence, user).** The `use` row AND the `fixed` input share one label: "Fixed Text", "Fixed Email", "Fixed Phone Number" — parallel to "Fallback Text" / "Fallback Email" / "Fallback Phone Number". No "Key"-style suffix: what is typed IS what shows. Help text states the contrast with fallback (shown per result vs only when nothing resolves); wording surfaced for review at build (new user-facing prose, no em dashes).
- **D7 — Cardinality.** Emits once per resolved source, exactly as any read: list mode repeats it, joined by `sep`, `limit` applies. One word = `limit:1`.
- **D8 — No source = empty.** The `fixed` branch runs AFTER source resolution; nothing resolved → empty → `fallback` fires, a `try_` attempt moves on, a join slot drops. `src:current` always resolves.
- **D9 — `linkTo`.** Works unchanged: the callback wraps when `link_id` is non-zero, which D8's ordering preserves.
- **D10 — Text finishing.** `{{text}}` / text slots: `sanitize_text_field` (the `fallback` precedent), then `bws_gb_tag_output()` so GB's `trunc`/`replace`/`trim`/`case`/`wpautop` apply. No raw-HTML path.
- **D11 — Email / phone finishing = fallback's.** Fixed value goes through the family finisher (`bws_email_finish_values` / `bws_phone_finish_values`): `is_email` + obfuscation + `mailto:` + `subject` (VE4); normalize + `tel:` (VP4); `noLink` honored. Invalid → empty → fallback.
- **D12 — Slot that doesn't offer it.** A `fixed(…)` token on a slot whose read cannot be fixed is preserved and ignored, like any unoffered option; never an error.
- **D13 — Preview.** Text: `[“Varsity”]`; join slot `B: “Varsity”`. The missing-meta-key warning (`preview-helpers.php` ~1439) excludes a fixed read. Email/phone preview parts show the fixed value the same way.
- **D14 — FW-59 not touched.** Base-tag bracketing is retired (see FW-59 row); `fixed` is not born under it.
- **D15 — Delivery.** Own branch + PR (FW-142 pattern); this spec is the PR body.

## Interfaces

- `BWS_USE_IMPLIED_BY_TOKEN` gains `'fixed' => 'fixed'`; `BWS_USE_STRIPPED_DEFAULTS` gains `email`, `phone` rows; `window.bwsUseRules` picks both up via `bws_use_read_rules()` with no JS change.
- `bws_get_text_field_options()` (and new email/phone leaves or equivalent) gain the `fixed` enum row + a `fixed` option (`bws-format-input`, `show_if: use:fixed` evaluated on the derived `use`).
- `bws_fold_parse_slot()`: a `fixed` case producing a fixed read kind; `bws_fold_emit_slot()` writes `fixed(…)` for it.
- Resolve seams: `bws_base_text_resolve_value`, `bws_base_email_resolve_value`, `bws_base_phone_resolve_value`, and the try_ per-item dispatchers (`bws_try_text_*_dispatch`, email/phone equivalents) branch on effective `use === 'fixed'` after source resolution.

## Tasks

1. PHP read rule: map row + stripped-default rows; `use-stripped-default-test.php` pins (`fixed` implied, `key`+`fixed` → key, explicit `use` wins, empty `fixed:` absent).
2. Registration: enum rows + `fixed` input on text/email/phone leaves, per-family labels; `control-order-test.php`, `slot-options-build-test.php`.
3. Resolve: `fixed` branch in the three seams + try_ dispatchers (D7–D11).
4. Slot grammar: `BWS_FOLD_FREEFORM` + parse/emit case; `slot-fold-test.php`, `slot-fold-twin-test.php` (escape round-trip with `:` `|` `,` `;` `()`).
5. Editor: confirm base-tag stale strip via `normalize()`; slot-row text input in `slot-fold-control.js` + stale drop on read change; `editor-filter-chain-test.js`, `slot-fold-repeater-test.js`, `slot-fold-picker-seam-test.js`.
6. Preview: D13; `preview-label-test.php`; `editor-tag-previews.md` rows.
7. Testbed: visible matrix rows (text/join/try_text/email/phone, incl. list mode, no-source → fallback, `linkTo`, invalid email/phone) in `blocks.php`, reseed, curl, re-capture page snapshots; matrices link.
8. Docs: `tag-reference.md` (`use` values for text/email/phone, `fixed` key, consequence of the read rule only), `CONTEXT.md` I2 sentence (`fixed` is the one `use` value that reads nothing, G8), README `**[UNRELEASED]**`, CHANGELOG.
9. Update-trigger rows to run: field-option leaf / slot-read, stripped-`use`-default, folded-slot grammar, folded-slot control, folded-slot registration, text read-seam, phone, preview text, option control order, serialization order (new key rank beside `key`, D2).

## Out of scope

`{{table}}` columns (gets `fixed` when it moves to the shared leaf; its layout flag is renamed `fixedLayout` to free the name, `table-tag.md` Settled #47) · datetime / content / image fixed reads · FW-27 `when` · FW-59 (closed) · typed join slots.

## Amendments made while building

Each of these was decided by the author during review. The code, CHANGELOG and docs follow the amended form.

- **D2 (explicit `use` wins) applies to the flat wire only.** On a slot, `use(key)` and `use(fixed)` are the pending states (read chosen, field or text not yet entered) and yield to the `key(…)` or `fixed(…)` token beside them. The editor never writes either pairing; only a hand-edited wire reaches it. The comment in `bws_fold_parse_slot` states the difference.
- **D3 / D12: a `fixed(…)` token beside a different read is dropped on the next save,** not preserved as an ignored option. D3's emitter rule (write only the chosen read's token) wins over D12's wording, so a stale token cannot linger unseen.
- **D3 needed code.** `use-read-control.js` returned early on an empty `use`, which skipped the stale-token drop. The spec expected `normalize()` to need no change.
- **D13: the join preview keeps its existing shape,** `[Join “Varsity”]`, with no slot letter (no join slot has one). In template mode a fixed slot's text prints bare inside the format's own quotes: `[Join “Varsity ('name_last')”]`.
- **D13 addition: a "fixed … not entered" warning** on the base and slot previews when the text is empty.
- **`try_email` / `try_phone` gained a per-slot read (`try_per_slot_use`).** A slot can now be fixed while its neighbors read fields, as on `try_text`. This changes the serialized form of two existing tags, so an unmigrated flat slot is read under its own era (`try_flat_era_per_slot_use`) and keeps rendering exactly as before; the Migration Tool converts it. Recorded in the CHANGELOG.
- **The text leaf's `$fixed` opt-in was removed** once every consumer passed true.
- **Shared helpers extracted:** `bws_contact_read_raw`, `bws_try_post_has_source`, `bws_fixed_noun`.
- **Fixture:** `env-versions.php` records the `active` plugin swap (`bws-portal-system` to `site-views`), and the baseline caught up rows deleted by earlier commits. Three F10 rows changed value with the cause unmeasured; tracked as FW-145.

## Verification

Every harness the trigger list names, run in one pass on the branch tip: 41 pure PHP and node harnesses, 0 failures, including use-stripped-default (90), slot-options-build (191), control-order (296), slot-fold (408), slot-fold-twin (387), preview-label, try-slot-loop, try-fixed-dispatch, fold-migration, fold-chain-compile, traversal-pipeline, editor-filter-chain, slot-fold-repeater and slot-fold-picker-seam. Page snapshots against the testbed: PASSED, 23 pages, after the J34–J37 and E2.7 baseline additions.

Not covered: no `linkTo` row for `{{join}}`, `{{email}}` or `{{phone}}`, because those tags have no `linkTo` (email and phone use `noLink`).
