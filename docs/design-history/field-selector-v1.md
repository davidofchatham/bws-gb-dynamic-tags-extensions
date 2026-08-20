# ARCHIVE — Smart field selector v1 (shipped 1.13.0, PR #42)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

Frozen design detail for the SHIPPED v1. Current-state homes: `CONTEXT.md` §I8
(cross-cutting invariant), PHPDoc on `assets/js/field-combo-control.js` +
`includes/rest/field-discovery.php`, schema in `docs/tag-reference.md` §Custom
control types, regression guards `tools/test/field-discovery-test.php` +
`tools/test/field-selector-test-matrix.md`. This file = rationale/history; the
code + those homes are authoritative for current behavior. Live plan (v2/v3/
v-future + FU-1/2/3) stays in `.scratch/plans/field-selector.md`.

**Note on evolution:** parts of the design below were SUPERSEDED during build/
review (e.g. the "scope selector" and "grouped list" ideas gave way to the flat
list + two-filter model; per-modal apiFetch gave way to inlined
`window.bwsFieldEnvelope`; dedupe moved from scope-overlap to scope-EQUALITY).
The §List + Filter schema section reflects the shipped model; earlier sections
retain the reasoning that led there. Where they conflict, the code wins.

---

## The decoupling (why this is its own plan)

A smart combobox writes the **existing** `key` option (and `ref`, `N-key`, datetime keys) as a plain string, exactly as today's `type:'text'` input did. No new tokens, no `use:key,field` cram, no PHP parse change, no migration. So field discovery is fully separable from the serialization combine and ships now.

## Goal

Replace GB-native field selection (which the plugin already abandoned — `key` is a plain `text` option) with our own discovery-backed combobox.

**Plan purpose (priority order — grill-confirmed).** The two points that justify this work:
1. **Give the editing author a searchable list of registered fields to choose from** (vs blind-typing a key).
2. **Escape GB's current-post-type-only limitation** — surface fields even in WP Patterns / GP Elements / templates where GB shows nothing.

Everything else (per-modal freshness, type prioritization, Pie Calendar) is secondary. In particular, "avoiding staleness" is a minor side-benefit, NOT a driver.

**Root GB failure (precise):** GB's `SelectMeta` queries `/generateblocks/v1/post-record?postId={current}&load=post` → `get_post_meta()` for the editor's **current post, with NO post-type override**. In a template / GP Element / WP Pattern context the "current post" IS the container (`wp_template`, `wp_block`, GP Elements CPT, …), so GB reads the *container's* meta — empty or wrong for the intended render target. The missing override is the whole bug.

Our design sidesteps the root cause two independent ways:
1. **Read field DEFINITIONS, not a bound post's values.** `acf_get_field_groups()` / `acf_get_fields()` + registered meta exist with no post instance → discovery works in any editor context, container or not.
2. **Provide the override GB lacks** — the location filter lets the author name the target kind/group; NEVER assumes the current post.

---

## Architecture (3 pieces)

### 1. PHP discovery service + REST route `bws-dynamic-tags/v1/fields`

- **Namespace:** `bws-dynamic-tags/v1` (plugin-scoped). First REST route in the plugin.
- **Registration-time, NOT value-time (LOCKED — rationale = instance-availability + quality, NOT `is_protected_meta`, and NOT "post context never exists").** Discovery reads field DEFINITIONS (ACF groups + `register_meta`), never a value-time `postmeta` scan.

  **Context tiers (post context is NOT universally absent):**
  1. **Bound post** — a specific post instance available.
  2. **Post-type known, no instance.**
  3. **Nothing** — blank Element/Pattern.

  **Why value-time rejected across all three:** a value-time scan needs a specific instance (tier 1 only); a broad `$wpdb SELECT DISTINCT meta_key` is label-less/type-less key-soup → rejected on quality. Tiers 2+3 (the contexts this exists to fix) have no instance anyway. Gap = unregistered raw `update_post_meta` keys, covered by free-text + (v3) hardcoded injection.
- **Cap:** `edit_posts`.
- **Security gate — offered ⟺ resolvable (LOCKED).** Filter output through the same gate `bws_read_field` enforces (exclude `DISALLOWED_KEYS`); do NOT hide `_`-protected meta (resolver allows on frontend). One gate, both layers agree. *(Shipped: unified as `bws_field_key_disallowed()`, case-sensitive.)*
- **Response envelope:** keyed by resolved-source kind — `{ post:[…], term:[…], site:[…] }`.
- **Sources:** ACF fields (`acf_get_field_groups` NO post_type filter → per group `title`+`location`→kind+scope + `acf_get_fields`; sub-fields recursed); ACF options-page fields (kind site); core registered meta (`get_registered_meta_keys` — *shipped: per-subtype over `''`+all post types/taxonomies so subtype-registered keys are offered, B8*); Pie Calendar (v3).
- **kind** ∈ post|term|site from ACF location param family (`post_type`→post, `taxonomy`→term, `options_page`→site). **scope** = matched values, candidate-level (ACF rules AND/OR — say "candidate", never "exactly these render").
- **Dedupe:** *(shipped model, V7)* merge same-name within one kind ONLY at scope EQUALITY (`bws_field_discovery_scopes_equal`) = truly one field; DIFFERING reach (global `[]` vs scoped) → KEEP BOTH (the global key must survive on post types the scoped one doesn't reach — flat per-kind envelope can't partition; overlap-merge dropped the global envelope-wide, B4/B7). ACF-vs-ACF → keep both. ACF-vs-registered same-name+equal-scope → merge, ACF metadata wins.

### 2. JS control `bws-field-combo`

- `ComboboxControl` (bundled): searchable single-value select. NOT natively creatable (WP-source-verified: filter-only input, typed-unmatched discarded on Enter/blur). Free-text commit BUILT via synthetic option.
- **Clear button — FREE** via `allowReset` (default true) → `onChange(null)` → `delete newState[key]`. NO "Add" button in ComboboxControl → GB's forget-to-click footgun structurally impossible.
- *(Shipped: envelope INLINED as `window.bwsFieldEnvelope` via `wp_add_inline_script`, assembled once per editor load — NOT per-modal apiFetch. This removed a 30-40s head-of-line block where `/fields` queued behind GB's dynamic-tag-replacement REST swarm. apiFetch fallback if the global is absent.)*
- **Dynamic label** tracks resolved-source kind via meta/option subtype pair: post→"Post Meta Field", term→"Term Meta Field", site→"Site Option Field"; unresolved→"Meta/Option Field". *(Shipped also: label names the active Location group — "Client Details Field"; `labelPrefix` prepends e.g. "URL".)*
- Writes the persisted key as a plain string (whole-object `setState`, `delete` on empty, never `''`).
- Composes with `editor-conditional-options.js` (`if (!element) return element`).

#### Free-text mechanism — synthetic option (LOCKED)

- Capture the filter string via `onFilterValueChange`; when it matches no existing option, inject `{ value:<typed>, label:'Use custom key: "<typed>"' }`.
- Committed value = bare key always; label display-only (NOT "Create").
- Filtering caveat WP#64056: Combobox filters on `label` → put both label + key in the display label so typing either matches.
- *(Shipped hardening: custom-key suppression is EXACT-key + case-SENSITIVE, B3/B6 — substring-of-label or case-insensitive made real keys uncommittable.)*

### 3. PHP option flip (`type:'text'` → `type:'bws-field-combo'`) + enqueue

Flipped: shared `use`/`key` block (base text/content/image/title/permalink) + `content`/`email`/`phone` standalone `key` + `ref` + datetime ×6 (`key`/`timeKey`/`startKey`/`startTimeKey`/`endKey`/`endTimeKey`) + `linkKey` + their `N-` per-slot try_ equivalents. Enqueue `field-combo-control.js` (deps wp-hooks, wp-element, wp-components, wp-api-fetch, wp-data, wp-i18n).

---

## Sub-fields (group / repeater / flexible) — surfaced, NOT skipped

Resolver already supports both (`bws_read_field`):

| Sub-field type | Resolution key written | Resolves via |
|---|---|---|
| **group** child | `parent_child` (ACF composite, stable, no index) | `get_post_meta` everywhere |
| **repeater / flexible** child | bare child `name` | Mode 2b row read `loop_item[$key]` — needs a query loop |
| top-level | `name` | normal |

Endpoint recurses `sub_fields` (+ flex `layouts[].sub_fields`), emitting `name` (composite for group / bare for repeater), `parent_path` breadcrumb, `context_hint` (`field`/`row`).

---

## Source-aware field scope (the editor-time kind projection)

**Frame:** the tag modal authors a read target (source + key). Sibling `src`/`ref`/`srcTermIn` author the source half; the field selector authors the key half. So the selector's scope is NOT the editor's current post — it is the **resolved-source KIND** the source half implies. At editor time there is no resolved source (no bound id — id is runtime L1 only); what IS editor-knowable is the resolved-source **kind**, a static property of the `src:` choice.

| Sibling source | Resolved-source kind | L2b read path |
|---|---|---|
| (none) base | `post` | `get_post_meta` |
| `src:ref`+`ref:FIELD` | `post` | `get_post_meta` (hopped) |
| `srcTermIn:tax` | **`term`** | `get_term_meta` |
| `src:site` | **`site`** | `get_field(key,'option')` |

*(Shipped: `presetKind(state,optionKey)` — safe-token-only preset; `src:ref` returns null → unscoped, since ref-hop target PT isn't statically known. NO `getCurrentPostType`, NO scope selector, NO `wp-data` dep. Slot-prefix aware: `2-key` reads `2-src`/`2-srcTermIn`. Hopped-to-PT scoping for `key` under `src:ref` = v2, gated on ref-hop parity.)*

### Not blocked by L1-lite / L1-full

Editor-time DISCOVERY, orthogonal to runtime resolution. Reads sibling tokens from `extraTagParams`, maps token→kind via a static table — no L1 call, no dependency on L1 maturity.

---

## List + Filter schema (LOCKED 2026-07-03 — the SHIPPED model)

Superseded the earlier "grouped by ACF field group + merge with in:groups label" model (ACF-group headings need one row per group, but ComboboxControl keys by value + commits it → per-group rows need decoupled values that DON'T match the bare persisted key on reopen). Flat dissolves the knot.

### List schema

- **Flat, NOT grouped by ACF field group.** Field group is a filter axis, never a list heading. No headings, no in-row breadcrumb, no loop-only marker — the two filters carry location/type.
- **Alphabetized by label.**
- **Merge identity = (kind, key, label)** joined with `UNIT_SEP` (U+001F, named const, NOT a literal control char, NOT `''`). Same key + same label = one row (shows under every location it belongs to, paths accumulated); same key + different label ("Name" vs "Feature Name") = distinct rows.
- **Serialization honesty:** group child serializes as composite `groupkey_fieldkey` (`get_post_meta`); repeater/flex child as BARE `fieldkey` (Mode 2b), index never serialized.
- **Reopen (V12):** serialized value = bare key, which may map to MANY records → resolve vs ALL records; single match → friendly "Label ('key')" row; ambiguous/unknown → neutral passthrough showing the RAW key (assert nothing).

### Filter schema — TWO selectors, AND-composed

- **Filter 1 — Location** (flat path-strings, prefix-match): `All detected fields` / `Post fields` / `Post fields › Group` / `… › Repeater (repeater)`. Container fields flagged `(repeater)`/`(group)`/`(flexible)`. Preset safe-token-only, else All. *(Shipped as SelectControl; searchable-combobox is FU/v2.)*
- **Filter 2 — Field type** (plain SelectControl): `All field types` / `Loop fields` / ACF types.
- Both ABOVE the field combobox, side-by-side (Flex row). Labels "Filter fields by location" / "Filter fields by type".

---

## Filter location: client-side (LOCKED)

Fetch ALL groups, filter/reorder in JS. Payload small; kind/type switching instant (no round-trip); JS sees each group's kind+scope for honest "candidate for X" UI.

---

## Research basis (2026-06-30)

**ACF API:**
- `acf_get_field_groups($filter)` → group arrays with `title`, `location`, `key`. Post-type filter candidate-level.
- `acf_get_fields($group['key'])` → `name`, `label`, `type`, `return_format?`. Types: email, date_picker, date_time_picker, time_picker, relationship, post_object, image, url, textarea, wysiwyg, true_false.
- Sub-fields under `sub_fields` (+ flex `layouts[].sub_fields`).
- `acf_get_options_pages()` + `options_page` location; read with `'option'`.
- `get_registered_meta_keys($object_type, $subtype)` — ACF fields NOT there by default (complementary).

---

## Perf history (shipped resolution)

v1 originally planned per-modal apiFetch, then a server transient. Both dropped: the ACF enumeration measured ~13ms (not the bottleneck); the 30-40s wait was browser head-of-line blocking (`/fields` queued behind GB's tag-replacement swarm). Resolution = INLINE the envelope as `window.bwsFieldEnvelope` at enqueue (no runtime request at all), assembled fresh each editor load (always current, no cache to invalidate). `block_editor_rest_api_preload_paths` was tried first and silently dropped our route — the explicit inline is guaranteed.

## Test harnesses

- `tools/test/field-discovery-test.php` — pure-logic unit harness (ACF-shimmed fixtures; kind/scope derive, sub-field flatten, dedupe keep-both, DISALLOWED gate, envelope shape). Discovery fns are pure helpers taking ACF arrays as args so the harness drives them directly.
- `tools/test/field-selector-test-matrix.md` — manual integration rows (control render, free-text/clear, filters, both merge scenarios, dynamic label, Pattern/Element context, try_ per-slot, DISALLOWED).
- CLAUDE.md update-trigger row added.
- NO JS unit harness (repo has no JS build/test pipeline; manual matrix covers the control).
