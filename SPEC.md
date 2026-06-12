# SPEC — #26 derive try_ slot source/traversal options from base builders

Active spec. Issue #26 (refactor). Plan: `.claude/plans/try-email-phone-and-slot-derivation.md` §#26. Model: CONTEXT.md §L1/L2/L3, ADR 0002.

## §G — goal

Derive try_ slot `src`/`ref`/`srcTermIn` option DEFINITIONS from base builders (`bws_base_source_option`, `bws_base_traversal_options`) → kill hand-maintained inline copies + live drift. Option-surface only; ⊥ resolver change.

## §C — constraints

- C1: editor option-surface ONLY. ⊥ touch resolver, read path, render. Registration-time JSON.
- C2: NARROW scope = `src` + `ref` + `srcTermIn`. ⊥ absorb `key`/`use` (slot-position-branched show_if, carry-forward `same` — not base-derivable; criterion §V4).
- C3: `site` MUST be filtered OUT of derived slot src list (resolver has no site arm → exposed `src:site` = silent current-post wrong-read, ⊥ empty). #32 re-allows per-template.
- C4: registered try_ option JSON for `current`/`ref` slots MUST be byte-identical pre/post (regression gate).
- C5: text domain `'generateblocks'`; all fns `bws_`-prefixed.

## §I — interfaces

- `bws_base_source_option(): array` — base-tags.php:579. src.options = current,ref,site; `_strip_default`.
- `bws_base_traversal_options(): array` — base-tags.php:607. ref (`show_if src:ref`), srcTermIn (`show_if src:not:site`).
- new: `bws_slot_qualify_show_if( array $show_if, int $n, array $sibling_keys ): array` — rewrite sibling-key conditions `k`→`{N}-k` (slot 1 bare); values unchanged.
- mutate: slot loop in `includes/classes/class-tag-template-registry.php` (~:369 src array, :469-476 ref block, :489-498 srcTermIn block).

## §V — invariants

V1: slot src options derive from `bws_base_source_option()['src']['options']`, `site` filtered out, slot≥2 prepend `same` row. ⊥ hand-maintained `$base_source_options` array.
V2: slot `ref`/`srcTermIn` derive from `bws_base_traversal_options()`, re-keyed `{N}-ref`/`{N}-srcTermIn`, show_if requalified via `bws_slot_qualify_show_if`.
V3: derived option `show_if` (base) + slot `show_if_any` ($slot_trigger) MUST coexist — distinct keys, merge preserves both. ⊥ overwrite.
V4: child option base-derivable IFF parent selector never defaults into child-triggering value. `ref` PASSES (src defaults current, never ref). `key`/`use` FAIL (use default→key-mode, slot-1 `not_in:` vs ≥2 `in:` branch) → stay hand-maintained. [criterion; not runtime]
V5: `_strip_default` on derived slot src MUST persist (slot-1 first-option strip).
V6: `site` ∉ derived slot src list until resolver site-arm lands (#32). Filter = wrong-read guard, ⊥ cosmetic.
V7: current/ref slot option JSON byte-identical pre/post derive (C4).
V8: `bws_slot_qualify_show_if` pure — ∀ (show_if, n, sibling_keys) → deterministic array out, no WP/GB symbols. Locally harnessable.
V9: slot-option build extractable as pure fn of (template, base-src-options, base-trav-options) → registration JSON. Enables V7 auto-harness ⊥ by-eye diff. [if extraction clean; else V7 verified manual]

## §T — tasks

id|status|task|cites
T1|.|add `bws_slot_qualify_show_if` helper (sibling-key→`{N}-k` rewrite, slot-1 bare)|V2,I
T2|.|derive slot src from `bws_base_source_option`, filter `site`, prepend `same` ≥2, keep `_strip_default`|V1,V5,V6
T3|.|derive slot ref/srcTermIn from `bws_base_traversal_options`, re-key, requalify show_if, merge $slot_trigger|V2,V3
T4|.|delete inline `$base_source_options` (:369-372) + ref block (:469-476) + srcTermIn block (:489-498)|V1,V2
T5|.|verify: option-JSON diff identical for current/ref slots; `site` now in dropdown-source-of-truth but filtered from slots; slot srcTermIn hidden on slot src:site (derived not:site)|V7,V3
T6|.|editor smoke: try_text/try_content/try_image slot 1+2 render unchanged|V7
T7|.|harness `tools/test/slot-qualify-show-if-test.php` — pure cases: slot1 bare, slot2 `2-src`, sibling-key filter (non-sibling untouched), condition values unchanged. shim `__`. exit 0/1|V8,T1
T8|.|extract pure `bws_build_slot_options($tpl,$base_src,$base_trav)` IF clean → harness `tools/test/slot-options-build-test.php` asserts byte-identical current/ref JSON pre/post (V7 auto-gate). Else fall back to manual T5|V9,V7,T2,T3

## §B — bugs

id|date|cause|fix
