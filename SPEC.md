# SPEC — Smart Field Selector (v1, in-flight)

Branch `feat/field-selector`. PR none yet. Design home `.claude/plans/field-selector.md` (grill-hardened 2026-07-02); this SPEC = v1 scope only (v2 type-priority / v3 Pie Calendar / v-future pick-a-post OUT). Truncate SPEC on merge; migrate load-bearing §V → PHPDoc + CONTEXT.md per lifecycle.

## §G — goal

Replace GB `key`/`ref`/datetime field text inputs with discovery-backed searchable combobox. Two points: (1) give author a registered-field list to pick; (2) escape GB current-post-type-only limit — works in Patterns/Elements/templates. No tag wire-format change.

## §C — constraints

- C1. NO wire-format change. Combobox writes existing `key`/`ref`/`N-key`/datetime keys as plain string, same as today's text input (V1). Decoupled from `use:key,field` combine.
- C2. Registration-time discovery ONLY, never value-time postmeta scan (V5). Target context (Patterns/Elements) has no bound post instance → value-time empty there; broad `$wpdb DISTINCT` = label-less soup. Unregistered-key gap covered by free-text + (v3) injection.
- C3. Offered ⟺ resolvable: endpoint output filtered by same gate `bws_read_field` enforces — exclude `GenerateBlocks_Dynamic_Tag_Security::DISALLOWED_KEYS` (V6). Do NOT hide `_`-protected meta generally (resolver allows it, field-helpers.php:233).
- C4. `edit_posts` cap on REST route (V6).
- C5. ACF calls `function_exists`-guarded (custom-fields plugin optional dependency).
- C6. Nomenclature: labels use "meta"/"option" storage-backend subtype pair; "field" umbrella; NEVER "field or option" (V4, feedback_field_nomenclature).
- C7. No build pipeline/linter — edit PHP + JS directly, test in WP (project CLAUDE.md).
- C8. No server-side transient cache in v1 (premature; `acf_get_fields` object-cached per request). Deferred perf lever.

## §I — surfaces

- I.rest = new `includes/…/field-discovery.php` (path TBD) — REST route `bws-dynamic-tags/v1/fields`; ACF + options-page + term-meta + `register_meta` discovery; kind-keyed envelope; cap + DISALLOWED filter.
- I.js = new `assets/js/field-combo-control.js` — `bws-field-combo` control via `generateblocks.editor.tagSpecificControls`; creatable `ComboboxControl`; per-modal `apiFetch`; client-side kind filter; kind-dynamic label; always-shown scope selector.
- I.enqueue = `bws-gb-dynamic-tags-extensions.php:185` `bws_dynamic_tags_enqueue_editor_assets()` — enqueue `field-combo-control.js` (deps `wp-hooks,wp-element,wp-components,wp-api-fetch,wp-data,wp-i18n`); register REST route on `rest_api_init`.
- I.base = `includes/tags/base-tags.php` — flip shared `use`/`key` block (`:72` etc.) + `ref` (`:692`) `type:'text'`→`'bws-field-combo'` + per-option scope descriptor.
- I.dt = `includes/tags/datetime-tags.php` — flip 6 keys (`key:368`,`timeKey:374`,`startKey:479`,`startTimeKey:485`,`endKey:491`,`endTimeKey:497`).
- I.other = `includes/tags/content-tags.php:35`, `email-tags.php` (`:74`,`:342`), `phone-tags.php` (`:76`,`:616`) — flip standalone `key`.
- I.read = `includes/helpers/field-helpers.php` — `bws_read_field` (`:230`) reader routing + `DISALLOWED_KEYS` gate (`:235`); reference only, not edited (correctness match target).

## §V — invariants

- V1. **Combobox is a pure render swap; value semantics unchanged.** Flipping an option `type`→`bws-field-combo` changes only how the control renders; the persisted value stays a plain string round-tripping through `extraTagParams` exactly as the `text` input did. Write via whole-object `setState`; `delete newState[key]` on empty (never `''` — GB serializes bare `key:`). Owner I.js. Orthogonal to `use` strip-default (V acts on `key`, not `use`).
- V2. **Scope axis = resolved-source KIND (post/term/site), the editor-knowable half of L1.** Selector projects the runtime L1 resolved-source kind to editor time from sibling `src`/`ref`/`srcTermIn` tokens — static map, NO L1 call, NO runtime id. `site`'s `'option'` read is a L2b path, NOT a peer axis value (CONTEXT.md §Resolved source, I2). Owner I.js, I.rest.
- V3. **`bws-field-combo` is scope-parameterized per option; `ref` and `key` are DIFFERENT scopes.** `ref` combobox scopes to source-post kind (where the relationship field lives); `key` combobox scopes to the resolved-source kind. Under `src:ref`, `key` target-PT is not reliably known (ref-hop parity unbuilt) → `key`-under-`src:ref` is UNSCOPED (all groups + free-text) in v1. Owner I.js, I.base.
- V4. **Control label tracks resolved-source kind via meta/option subtype pair.** kind post→"Post Meta Field", term→"Term Meta Field", site→"Site Option Field"; unresolved/mixed→"Meta/Option Field" (canonical, source-agnostic-correct fallback). Never "field or option" (feedback_field_nomenclature, CONTEXT.md I5). Owner I.js.
- V5. **Discovery reads field DEFINITIONS, not values.** Sources = ACF groups (`acf_get_field_groups`/`acf_get_fields`) + options-page (`acf_get_options_pages`) + term-meta (taxonomy-location groups) + `get_registered_meta_keys`. No value-time postmeta scan (C2). Owner I.rest.
- V6. **Endpoint = offered ⟺ resolvable, `edit_posts`-gated.** Cap check + filter output through `GenerateBlocks_Dynamic_Tag_Security::DISALLOWED_KEYS` (same gate as `bws_read_field`, field-helpers.php:235). Never offer a key the resolver refuses. Owner I.rest.
- V7. **Envelope keyed by kind; dedupe within (kind,scope) only.** Response `{post:[…],term:[…],site:[…]}`. Same key string across kinds = DIFFERENT fields (different storage/read path) → kept distinct. Within one (kind,scope) bucket, ACF+registered-meta collision → merge, ACF metadata (label+type) wins; ACF-vs-ACF → first-seen deterministic. Owner I.rest.
- V8. **Sub-fields surfaced with correct resolution key.** Group child → `parent_child` composite (stable, resolves via `get_post_meta` everywhere). Repeater/flex child → bare `name`, flagged `context:row` (resolves only in loop-row Mode 2b, field-helpers.php:253-255). Recurse `sub_fields` + flex `layouts[].sub_fields`. Owner I.rest.
- V9. **Control composes with existing `tagSpecificControls` filters.** `bws-field-combo` filter guards `if (!element) return element` so conditional-options (`show_if`→null) hiding wins regardless of filter order; matches `cfg.type==='bws-field-combo'` only (mutually exclusive with `bws-term-hop`). Owner I.js.
- V10. **Scope selector always shown; inference pre-fills only usable content types.** Selector (kind + sub-scope) always visible = the override GB structurally lacks. `getCurrentPostType()` pre-fills sub-scope only when a usable content type; container types (`wp_template`/`wp_template_part`/`wp_block`/`wp_navigation`/GP Elements CPT) leave it unset. Owner I.js.
- V11. **Free-text commits via synthetic option; clear via `allowReset`.** `ComboboxControl` does NOT commit off-list text (WP-source-verified: filter-only input, typed-unmatched discarded on Enter/blur). To commit any typed key in one step (no Add button): capture `onFilterValueChange`, inject a synthetic option `{value:<typed>, label:'Use custom key: "<typed>"'}` when no existing match; selecting it fires `onChange` with the BARE key. Committed value = bare key always (label display-only, NOT "Create"). Clear = built-in `allowReset` (default true) → `onChange(null)` → `delete newState[key]`. NO Add-button footgun (structurally absent). Filtering caveat WP#64056: Combobox matches on `label` — where a real option's `value`≠`label`, put both in the label so typing either matches. Owner I.js.

## §T — tasks

| id | st | task | cites |
|----|----|------|-------|
| T1 | x | REST route `bws-dynamic-tags/v1/fields` on `rest_api_init`; `edit_posts` cap; DISALLOWED_KEYS filter | V5,V6,I.rest,I.enqueue |
| T2 | x | Discovery: ACF groups+fields → derive kind+scope from `location`; recurse sub_fields (group composite / repeater bare-name context:row); options-page (kind site); term-meta (taxonomy loc); `get_registered_meta_keys` | V5,V7,V8,I.rest |
| T3 | x | Kind-keyed envelope `{post,term,site}`; dedupe within (kind,scope), ACF-metadata-wins | V7,I.rest |
| T4 | x | `field-combo-control.js`: `ComboboxControl` + synthetic-option free-text commit (`onFilterValueChange`→`Use custom key: "X"`) + `allowReset` clear→`onChange(null)`, per-modal `apiFetch`, grouped render, `if(!element)return` guard, `cfg.type==='bws-field-combo'` match | V1,V9,V11,I.js |
| T5 | x | Client-side kind filter + always-shown scope selector (kind + sub-scope); pre-fill from sibling `src`/`ref`/`srcTermIn`, else container-aware `getCurrentPostType()` | V2,V3,V10,I.js |
| T6 | x | Kind-dynamic label (meta/option subtype pair; static fallback) | V4,I.js |
| T7 | x | Enqueue `field-combo-control.js` (deps wp-hooks,wp-element,wp-components,wp-api-fetch,wp-data,wp-i18n) | I.enqueue |
| T8 | x | Flip base `use`/`key` block + `ref` to `bws-field-combo` w/ per-option scope descriptor (`key`-under-src:ref unscoped) | V1,V3,I.base |
| T9 | x | Flip datetime ×6 keys + content/email/phone `key` to `bws-field-combo` | V1,I.dt,I.other |
| T10 | . | Manual WP test: post/term/site scope, Pattern/Element context, free-text custom-key commit (synthetic option, Enter, no Add), clear ✕, `show_if` compose, round-trip persist | V1,V9,V10,V11 |
| T11 | x | New harness `tools/test/field-discovery-test.php` — standalone, ACF-shimmed fixtures; assert location→kind+scope, sub-field flatten (group composite `parent_child` / repeater bare-name+`context:row` / recurse sub_fields+layouts), dedupe within (kind,scope) ACF-wins, DISALLOWED_KEYS filter, envelope shape. Pure-logic only (no REST/JS). | V5,V6,V7,V8 |
| T12 | x | New `tools/test/field-selector-test-matrix.md` — manual integration rows: synthetic-option commit, clear ✕→onChange(null), scope-selector tracking sibling src/ref/srcTermIn, Pattern/Element context, show_if compose, round-trip persist, DISALLOWED refusal | V9,V10,V11 |
| T13 | x | CLAUDE.md update-triggers row: field-discovery change → run `field-discovery-test.php` (mirror phone/preview harness rows) | . |
| T14 | x | Revert DEV filemtime cache-bust on `field-combo-control.js` enqueue → `BWS_DYNAMIC_TAGS_VERSION` before ship | I.enqueue |
| T15 | x | Option-label schema — SUPERSEDED by the fully-flat list decision (2026-07-03): no in-row breadcrumb, no loop-only marker; the two filters (location + type incl. "Loop fields") carry that meaning. Merge is (kind,key,label); location paths flag `(repeater)`/`(group)`; label tracks active group/kind. | I.js |

## §B — bugs

| id | date | cause | fix |
|----|------|-------|-----|
| B1 | 2026-07-03 | Combobox options carried duplicate `value`s (same field key across ACF groups / cross-kind all-view); ComboboxControl keys its list by value → "two children with same key", list re-mounts/resets on scroll | dedupeByValue after building options (first-seen wins; flat select commits one key so lossless) |
| B2 | 2026-07-03 | dedupeByValue `seen={}` plain object → field keys matching Object.prototype props (`toString`,`constructor`,`hasOwnProperty`) inherited truthy → silently dropped from the list | `Object.create(null)` seen map; `String(value)` coerce |
