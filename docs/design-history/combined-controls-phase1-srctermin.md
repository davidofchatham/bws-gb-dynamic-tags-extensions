# Archive: Combined Custom Controls — Phase 1 (`srcTermIn` term hop)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

**Shipped 1.6.0.** Lifted from the active `combined-option-controls.md` 2026-06-08 (Phase 1 complete; Phase 2/3 remain open there). This is the historical implementation record. The reusable **pattern** it established (two-controls-one-key; control-layer param authority) lives on in the active plan's §Shared notes + §Control-layer param authority — those were NOT archived, since the open phases build on them.

The forced-rename driver: GB hardcodes `tax` as a reserved option name (`DynamicTagSelect.jsx:385-395`, only re-emitted when `'term' === dynamicTagType || tagSupportsTaxonomy`). Cross-source base tags meet neither condition → `tax` is "eaten" on modal reopen, forcing a non-reserved key (`srcTermIn`).

---

## Phase 1 — Term hop: checkbox + taxonomy selector → `srcTermIn:<slug>`

### UX (two controls, unchanged from author POV)

- Checkbox "In taxonomy term" — toggles term-hop on/off
- ComboboxControl "Taxonomy" — picks taxonomy slug from registered public taxonomies
- Both rendered via `generateblocks.editor.tagSpecificControls`
- `show_if` rules unchanged (visible when `src` ∈ {`current`,`ref`})

### Wire format (single serialized key)

- One persisted key: `srcTermIn`
  - Empty / absent → disabled
  - Slug string → enabled + slug (e.g. `srcTermIn:category`)
- Old `tax` key is gone (was reserved/eaten by GB)
- Old `srcTerm` boolean — no longer serialized; lives in component state only

### State model

- `extraTagParams.srcTermIn`: only persisted value. Round-trips through GB.
- Component-local `srcTerm` (bool): UI affordance only. React state inside custom control. Not written to `extraTagParams`. Discarded on modal close.

Lifecycle:

- **Mount**: derive `srcTerm = !! state.srcTermIn`. Combobox value = `state.srcTermIn || ''`.
- **Check box (was unchecked)**: set local `srcTerm = true`. No write to `extraTagParams`. Tag string unchanged. Combobox now visible/enabled awaiting slug.
- **Pick slug**: `setState({ ...state, srcTermIn: slug })`. GB serializes `srcTermIn:slug`.
- **Change slug**: same as above with new slug.
- **Uncheck box**: set local `srcTerm = false` AND `setState({ ...state, srcTermIn: false })`. GB drops key.
- **Modal close + reopen**: component remounts; local `srcTerm` re-derived from `state.srcTermIn` presence. Checkbox-checked-without-slug state does not survive (acceptable — answer (a): incomplete = disabled).

### JS implementation

New control type: `'bws-term-hop'` (composite). Single filter handler renders both checkbox + combobox.

- Companion file: `assets/js/term-hop-control.js` (new), pattern matches existing `image-tag-controls.js`
- Taxonomy list: `wp.data.select('core').getTaxonomies({ per_page: -1 })`, filter `tax.visibility?.public !== false`, map to `{ value: slug, label: name }`
- `useState` for local `srcTerm` boolean, init `Boolean(state.srcTermIn)`

Single PHP option registration produces both controls (not two separate `show_if` options) — simpler `show_if` story.

### PHP

- Replace existing `srcTerm` + `tax` registrations in `bws_base_traversal_options()` with one option:
  ```php
  [
      'id'      => 'srcTermIn',
      'type'    => 'bws-term-hop',
      'label'   => __( 'In taxonomy term', 'generateblocks' ),
      'show_if' => [ 'src' => 'not:' /* whatever current rule is */ ],
  ]
  ```
- Helper: `bws_parse_src_term_in( $options ): string` → trimmed slug or `''`
  ```php
  $v = $options['srcTermIn'] ?? '';
  return is_string( $v ) ? trim( $v ) : '';
  ```
- Pipeline / `bws_resolve_post_by_source()`: branch on `srcTermIn` non-empty → append taxonomy hop with that slug. Drop all branching on `srcTerm` and `tax`.
- Audit source classes (`TermRelatedPost`, `PostTermRelatedPost`, etc.) for any direct reads of `srcTerm`/`tax` → switch to `srcTermIn`.

### Migration

Two paths:

**(a) Existing deprecated-tag entries** (`deprecated-tags.php`) already inject `'srcTerm' => 'true'` via `$srcterm_fixed` and pass `tax:<slug>` through. Update needed:

- Drop `srcTerm` from `$srcterm_fixed` (signal carried by `srcTermIn` presence)
- Add `'tax' => 'srcTermIn'` to `option_renames` for term-source deprecated tags

Two-line change per entry, expressible with existing `MigrationRegistry` primitives (`option_renames`, `fixed_options`).

**(b) Hand-written `srcTerm` + `tax:<slug>` strings** — handled by new `combine_options` migrator primitive:

```php
'combine_options' => array(
    'srcTermIn' => array(
        'when_present' => 'srcTerm',
        'value_from'   => 'tax',
    ),
),
```

Behavior:
- `srcTerm` (any value) + `tax:<slug>` → `srcTermIn:<slug>`, both old keys dropped
- `srcTerm` alone → both keys dropped (incomplete; no-op without `tax`)
- `tax:<slug>` alone → both keys dropped (no-op without checkbox)

`combine_options` runs before `option_renames` so other key renames don't interfere. Reusable for Phase 2 (`from`/`use,key`) and any future combined options.

Both paths run through the standard `MigrationRegistry` — not on every load.

### List-mode compatibility

`limit` / `sep` orthogonal — unchanged. Term hop produces `WP_Term[]` step; downstream list options apply as today.

### Editor preview tool

`.claude/tools/tag-string-preview.html` — updated after PHP/JS landed:

- Replace separate `srcTerm` + `tax` parts with single `srcTermIn` part
- `DEFAULT_ORDER`: `srcTermIn` occupies the slot currently held by `srcTerm` (the `tax` slot removed)
- Permutation generator: emit `srcTermIn:slug` when active; no separate `tax` output

---

**Current state of the shipped control:** see `docs/tag-reference.md` §Custom editor controls (`bws-term-hop` row) + §Source options (`srcTermIn`). Reusable pattern + control-layer authority: active `combined-option-controls.md` §Shared notes / §Control-layer param authority.
