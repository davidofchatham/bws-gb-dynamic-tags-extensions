# Archive: Combined Custom Controls — Phase 3 (serialization-order decoupling + `as`+`size` fold)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

**Shipped 1.16.0** (branch `docs/fw-52-serialization-order`; commits `6a4d843` `5565521` `00bcc4b`
`a426db1` `bd29092` + normalizer/registration `96b42fa`, editor fixtures `b91b57d`). Lifted from
the active `combined-option-controls.md` 2026-07-23 (Phase 3 serialization-order half + the image
`as`+`size` fold BUILT; Phase 2 `use`+`key` combine, the link-cluster unification, and datetime
FW-40/41 remain OPEN there). This is the historical implementation + design record for the FW-52
serialization-order pull-forward.

The reusable machinery it built on (the `bws-*` control pattern, `tagSpecificControls` seam,
whole-object `setState`, control-layer param authority) is NOT archived — it lives in the active
plan's §Shared notes + §Control-layer param authority, since the open phases build on it.

## What shipped (current-state homes — read these first)

- **Serialization/control order model:** [`docs/tag-reference.md` §Option order](../../../docs/tag-reference.md#option-order) (canonical orders, the format-front departure, the used/unused GB-native-controls audit) + [`docs/gb-constraints.md` §Serialization order is independent of control order](../../../docs/gb-constraints.md) (the pure GB `post_date` fact + the per-`supports` gating rule).
- **Normalizer:** [`assets/js/serialization-order-normalizer.js`](../../../assets/js/serialization-order-normalizer.js) (JS port) + [`includes/helpers/serialization-order.php`](../../../includes/helpers/serialization-order.php) (canonical KEY_MAP + `bws_serialization_order_sort`, PHP mirror). Harness `tools/test/serialization-order-test.php`.
- **`as`+`size` fold:** [`docs/tag-reference.md` §`as` serialization opt-out + `as`+`size` fold](../../../docs/tag-reference.md) + the `bws-as-size` row in §Custom editor controls. Control `assets/js/as-size-control.js`; parse/labels `bws_parse_as_option` + `bws_get_image_size_options` (`includes/helpers/image-helpers.php`); migration `bws_migrate_image_as_size_fold` (`includes/tags/deprecated-tags.php`). Harness `tools/test/as-size-fold-test.php`.
- **CHANGELOG:** 1.16.0 §Changed (reorder + join `valueSep` rename + as+size fold).
- **Editor-eyeball matrix:** `tools/test/fw52-order-test-matrix.md` (O1–O4 rows) + visible GB blocks in `tools/fixtures/core-structures/blocks.php` (FW-52 O1/O2/O3/O4 sections).

The design record below is retained for RATIONALE (why the normalizer is a transform not a re-sort;
why `as` is a parameterized return type; the net-cost accounting) — not as current state.

---

## Phase 3+ — serialization-order decoupling + control unification

**PULL-FORWARD (decided 2026-07-22):** the serialization-ORDER half was pulled ahead of the Phase
2/3 combines — the highest-leverage lever (governs the modal-vs-string intuition problem), decoupled
from the 1.16.0 read-path convergence. Pull-forward scope = **reorder normalizer + modal-swap**.
The **`as`+`size` image re-key** rode with it (gating-driven, not ordering-driven — `size` is
GB-reserved, outside reorder's reach regardless). The **`link` cluster unification** stayed DEFERRED
(combined-control work that landed in this section only because ordering surfaced it — see the
active plan).

### Grill outcomes (2026-07-22 — `/grill-with-docs`)

The pull-forward was grill-hardened. Resolved:

1. **String-half survives (cut-proposal rejected).** The serialized string IS a real read+edit
   surface: the author edits tag strings manually AND the modal shows the string live as it's
   built. The concrete pain is **multi-slot reset-scatter** — revise an earlier slot, add a key,
   and it appends globally-last, stranded past later slots' keys (verified
   [`gb-constraints.md` §Option Serialization Order](../../../docs/gb-constraints.md#option-serialization-order),
   the "re-editing keeps original slot, new keys append last" rule). So the JS normalizer earns
   its cost; it is NOT droppable in favor of modal-swap alone.

2. **Two orders, two references.**
   - **Modal (render) order → MIRROR GB's native sequence** for GB's own controls. **P2 finding
     (verified `DynamicTagSelect.jsx:679-875`, 2026-07-22):** GB's modal is `source → pickers →
     meta key → Image Size (800) → Date Format (809) → {custom controls} (819) → Link (821) →
     insert`. So the earlier "GB puts ALL format late" premise is **only half-true** for GB-NATIVE
     controls: `size`/`dateFormat`'s slots render EARLY, only `link` late. But a GB native control
     renders only under its registered support — and **the plugin registers `size`/`source`/`link`
     but NOT `'date'`** (see next bullet), so the Date Format slot never fires for our tags. "Mirror
     GB" is forced only for the reserved controls we USE (`size`/`link` positions); the real modal
     decision is where OUR CUSTOM options sit — GB injects all `tagSpecificControls` at ONE point
     (line 819). Our custom-option modal order is entirely ours; GB gives no guidance. That is what
     P1 must define, not "copy GB."
   - **The plugin uses exactly ONE GB-native control (correction 2026-07-22, full audit).** Every
     GB native control gates on a registered `supports` value (`tagSupports`,
     `DynamicTagSelect.jsx:193`, `:269-274`). **The plugin registers empty `supports` arrays**
     (`base-tags.php:69,131,177,211,290,303,320`) EXCEPT `image`/`term_image` = `['image-size']`
     (`:234`), and touches NO `sourcesInOptions` filter (default `[]` → GB never serializes
     `source:`/`id:` for our tags). So GB-native source, meta/`key`, link, taxonomy, dateFormat are
     ALL unused — replaced by custom `src` / `key` / `linkTo` / `srcTermIn` / `as`+format. **`size`
     on `image` is the ONLY reserved-AND-used control** → the only thing outside the normalizer's
     reach — **and only until the `as`+`size` fold lands** (§Image `as`+`size`), which removes
     `'image-size'` support and folds size into custom `as`, after which NO GB-native control
     remains and the reorder governs 100% of the surface. (Lone source exception: `term_*`, GB type
     `'term'`, uses native term source — deprecation-bound.)
   - **String (serialize) order → `format` group LEADS**, differing from modal. The normalizer's
     job is a TRANSFORM of GB/registration order. Because only image's `size` is GB-owned, the
     normalizer governs **essentially the whole option surface** — `as`, the datetime format block
     (~8 custom options, ALL movable), `linkTo`/`linkKey`/`newTab` (custom link group), `sep`, join
     format tokens, and the per-slot custom source/field keys. **Datetime is the PRIME beneficiary,
     not a worry case** — the earlier "dateFormat immovable" claim was WRONG (we don't use GB's
     dateFormat). Likewise `link` is a custom group in the reorder, NOT GB-owned.

3. **Normalizer = TRANSFORM, not full re-sort.** Two operations only: **(a) lift the `format`
   group to the front**, **(b) keep each `N-` slot's keys contiguous**; everything else preserves
   GB/registration order. (a) serves A (`as`-front on image); (b) serves B (multi-slot coherence).
   Neither is a general re-sort.

4. **B (multi-slot coherence) = Strategy 1 (`N-`-prefix block detection).** The normalizer groups
   slot keys by parsing the `N-` prefix (pure JS string parse) + a **hardcoded per-slot canonical
   key order** (`src, ref, srcTermIn, use, key`). Ships near-term, needs NO slot-owning composite.
   - Rejected alternative **Strategy 2 (block-by-composite-identity** — a composite declares the
     keys it owns; normalizer treats that set as an atomic block). Strategy 2 generalizes to the
     link cluster (no `N-` prefix) and an eventual slot-owning composite, but needs an ownership
     channel + the composites to exist. **The eventual shape is UNKNOWN (why this whole area was
     deferred) → do NOT build Strategy 2's architecture speculatively.** S1 serves both near-term
     priorities; S2 is revisited only when a multi-key composite actually builds (honors "2nd
     instance earns the genus", §Tag structural vocabulary).

5. **A (image `as`) = singleton front-pull.** `as` is the never-stripped default (always present).
   Array-order alone can't hold it front: a **reset re-appends `as` at the tail** (the decisive
   spike case, below), so the normalizer is MANDATORY even for the single-key image case.

6. **No re-spike — build for real.** The original image spike (2026-06-06) already proved the
   risky mechanic (whole-object reorder round-trips GB serialization + the as-reset cross-group
   front-pull). Multi-slot `N-` is new-but-low-risk (same reorder, more keys) — a second throwaway
   spike would prove less than the real control. Build it as intended; the real control IS the
   proof. (The two-writer coexistence question in #7 is deferred with S2, so no multi-key spike is
   needed for S1.)

7. **Two-writer coexistence = deferred with S2 (NOT an S1 concern).** When a value-writing
   composite and the order-normalizer both run on one tag, they converge IFF their guards test
   **disjoint properties** (normalizer guard = key-ORDER only; composite guard = VALUE only) AND
   the composite uses **spread-preserve `setState`** (`{...state, key: val}`, never a fresh
   rebuild) so it never perturbs key-order. S1 introduces NO multi-key composite (`srcTermIn` and
   `as` are 1-key → provably non-colliding), so this does not bind until S2. Recorded as C2 below.
   **NOTE: activated early by the as+size composite (grill-outcomes-2 #2) — the first value-writing
   composite — so C2 bound at ship after all.**

**Constraints recorded on build:**

- **C1 (S1):** the normalizer gates **per-tag-name** via `tagSpecificControls`. When a tag later
  gains a block-owning composite (S2), it is REMOVED from the normalizer's gate — normalizer and
  composite never both order the same tag's keys. S1's hardcoded canonical key-order is per-tag and
  **removable** (delete the tag's entry when S2 takes over). This keeps S1 from becoming the thing
  S2 must fight (the stranded-`tax`-token trap, in ordering form).
- **C2 (activated at ship, S2 contract):** value-writing composites MUST spread-preserve `setState`;
  a composite owns the order *within* its own key-block, the normalizer owns order *between* blocks
  — disjoint scopes → convergent.

**Prerequisites (all resolved before build):**

- **P1 (canonical order authored):** [`tag-reference.md` §Option order](../../../docs/tag-reference.md#option-order) — the group model (`format`/`link`/`source`/`fallback`) + per-slot canonical key order (`src, ref, srcTermIn, use, key`). DONE 2026-07-22.
- **P2 — DONE (2026-07-22).** GB modal order verified (`DynamicTagSelect.jsx:679-875`): `size` +
  `dateFormat` render EARLY (adjacent to source), only `link` late; custom controls inject at one
  point (line 819).
- **P3 — RESOLVED 2026-07-23 (grill-outcomes-2 #6):** no questionable area blocks the build — every
  custom option renders as one registration-ordered block at line 819; term_'s dual-source split is
  pre-existing and deferred.

### Grill outcomes 2 (2026-07-23 — `/grill-with-docs`, build-start open questions)

Resolved the three plan Open questions + P3 + the historical-unwind scope. Vocabulary and canonical
order sharpened against GB's own `post_date` tag (verified `class-dynamic-tags.php:97`
`supports:['date','link','source']` — GB's one tag carrying BOTH a format control and link).

1. **Mechanism = per-tag normalizer, full stop (not fold-into-composite, not hybrid).** Fold-into-
   composite is unavailable at FW-52 ship time — the pull-forward lands the reorder AHEAD of the
   Phase 2 combines, so there are no composites to fold into. Hybrid is not two coexisting
   mechanisms; it's normalizer-now with C1-managed migration if/when a composite later takes a tag
   over. Single invisible per-tag-name normalizer via `tagSpecificControls`, spike-proven mechanic.

2. **as+size composite CO-SHIPS with FW-52 (same release), built normalizer-FIRST then composite.**
   Normalizer is tag-agnostic infrastructure (independently testable on a non-image tag —
   datetime, ~8 movable format options); as+size is image-family-specific (composite + migration
   `transform_callback` + `bws_get_image_size_options()` + React size-stash). Sequential within one
   release, not simultaneous. This **activates C2 NOW** (the as+size composite is the first
   value-writing composite) — so C2's coexistence contract binds at ship: composite spread-preserves
   `setState` (VALUE guard), normalizer guards key-ORDER only, disjoint → convergent, re-entrancy
   guard prevents loop.

3. **Group-order source = all-JS-hardcoded for S1** (slot key order AND the `format`-group key set).
   No PHP→JS `serialize_group` localize plumbing. The normalizer is a TRANSFORM not a full sort, so
   it needs little data; building localization infra for it is over-engineering. Revisit PHP-derived
   only if S2 ever makes ordering permanent infrastructure. (Build note: a PHP mirror
   `serialization-order.php` was authored anyway to back the pure harness — it is a test-parity
   mirror of the hardcoded JS map, not a localize channel.)

4. **S1 is the STANDING solution, not presumptively disposable.** S1 is replaced only if/when a
   concrete need forces S2 — which may never come. C1's per-tag removability is a *capability* (a
   tag CAN leave the gate when it gains a block-owning composite), NOT a plan to dismantle S1.

5. **term_ / view_ INCLUDED in the normalizer gate** (they ride base-tag sorting for their custom
   options; GB-native term source legitimately leads, which is the intuitive position anyway).
   Two build-time verifications gated enabling them: (a) GB destructures `tax`/`id`/`source` out of
   `extraTagParams` before the custom control's `setState` sees them (if true, the whole-object
   rebuild never touches native keys → the stranded-`tax` trap can't fire); (b) `view_*` is external
   — the normalizer gates by tag-NAME in editor JS regardless of registrant, so confirm view_ tag
   names are enumerable at editor time.

6. **Canonical SERIALIZATION order = `format → source(per-slot) → link → fallback`.** Link moved
   AFTER source (source-relative: `linkTo:post/term` links the entity, `linkKey` reads a field off
   it — GB-endorsed, its `post_meta`/`post_date` serialize `source → field → link`). Format-front is
   the SOLE deliberate departure from GB's convention — GB's `post_date` serializes `link` BEFORE
   `dateFormat` (format-LAST); we invert to format-FIRST for manual-edit copy-visibility. The
   `link` group is defined by ROLE not control-set: it covers the `linkTo`/`linkKey`/`newTab`
   cluster (entity-link) OR the `noLink`+`subject` set (email/phone own-anchor mailto/tel) — one
   set per tag, both rank as `link`. Within the email/phone set the canonical order is
   `subject → noLink`.

7. **Canonical CONTROL order = `source → format → link → fallback`, diverges from serialization BY
   DESIGN.** Format renders EARLY in the panel (matching GB's `post_date` — Date Format ABOVE Link
   To, verified screenshot 2026-07-23), the inverse of the serialization order. GB itself decouples
   the two — its `post_date` modal renders Date Format ABOVE Link To yet serializes `link` before
   `dateFormat`. FW-52 formalizes what GB does accidentally. Vocabulary LOCKED: **"serialization
   order"** (not "string order") + **"control order"** (not "modal order").

8. **tag-reference option order is CANONICAL; registration + JS conform to it, discrepancies are
   raised (doc wins), not silently followed.** The normalizer sorts BY the tag-reference §Option
   order section. The known drift (link registered after fallback in text, `base-tags.php:89` — a
   fossil of the past "help") was superseded by #6's canonical order; code moved to match.

9. **Registration-standardization is IN FW-52 scope (the historical unwind).** Today ONE lever
   (registration/PHP option-array order) drove BOTH control order AND serialization seeding — the
   coupling FW-52 breaks. A past attempt reordered control GROUPS in registration to "help"
   serialization, coupling the two and leaving registration in a state clean for neither (the
   line-89 fossil). FW-52 unwinds that: **standardize registration to canonical CONTROL order
   (source-first), then the normalizer produces canonical SERIALIZATION order (format-front)
   independently.** **Structural guarantee (verified `DynamicTagSelect.jsx:27-114`):** control-render
   reads the `options` object (registration order, one block at line 819), serialize reads the
   `extraTagParams` object (insertion order) — TWO DIFFERENT OBJECTS, so the levers are independent.
   The `tagSpecificControls` filter fires PER-OPTION and swaps a control's *rendering* in place
   (never its slot) → zero escapees; the normalizer renders no control slot at all (it's a
   `setState` reorder). **Sequencing:** registration-standardization + normalizer land ATOMICALLY
   (standardizing registration alone would churn saved tags' serialization until the normalizer
   lands); as+size composite follows (#2).

**Build phases (one release):** (1) standardize registration to canonical control order + (2) the
per-tag serialization normalizer — ATOMIC; then (3) as+size composite.

### Motivation

The grouping model serializes formatting (`as`, format, link) *early* in the tag string — by design,
so e.g. `as:url` is visible up front when copying a tag. But render order followed the same
registration order, forcing formatting controls *above* source selection in the panel, which is
non-intuitive (author wants source/field before formatting). Goal: **decouple serialize order from
render order** so formatting can be string-early but modal-late.

### Two independent levers (confirmed)

GB has **no serialize hook** — the tag-string serializer is a bare
`Object.entries(extraTagParams).forEach(...)` (string order = object insertion order). So a
post-serialize string filter is impossible. Order is driven entirely from the `extraTagParams`
object. Two separate levers:

| Lever | Mechanism | Scope |
|---|---|---|
| **Control (render) order** | PHP option-array sequence in registration | per-tag, free — author the array in control order |
| **Serialization order** | JS normalizer rebuilds whole `extraTagParams` in canonical order inside `setState` | tag-name gated via `tagSpecificControls` |

Built-ins (`source`/`key`/`size`/`dateFormat`/`tax`/`required`) always push *first* regardless (GB
code order, `DynamicTagSelect.jsx:514-555`) — but the plugin uses essentially none of them (audit
above), so the normalizer governs the whole custom-option surface.

### Spike result (PROVEN — 2026-06-06, image tag)

Throwaway probe (deleted post-spike): a priority-20 `tagSpecificControls` handler attached to
image's `fallback` media-picker, rendering no UI, running `setState(reorder(state))` in `useEffect`
with a re-entrancy guard (rewrite only if key-order changed).

- ✓ Whole-object `setState` reorder **round-trips through GB serialization** — no clobber of sibling native controls, no render loop.
- ✓ Console: `as,key,src → as,src,key` (canonicalized within present keys).
- ✓ **Decisive case:** remove `as` token then reset → GB appends it last in `extraTagParams` → reorder pulls it back to lead → string serializes `as:` first. Cross-group front-pull confirmed.
- Untested-at-spike (low-risk, same-sort, since proven by the real build): multi-option format reflow density (datetime ~8 options) and stripped-default absent-key tolerance.

### Group model (descriptive names)

[`tag-reference.md` §Option order](../../../docs/tag-reference.md#option-order) defines the four-group
structure + the two-order split + canonical orders. Descriptive names (code uses these, not magic
numbers):

- **`format`** — global formatting, applies to assembled result: `as`, format/sep options. Serialize-early, control-late.
- **`link`** — `linkTo` + dependent `linkKey` + `newTab` (entity-link) OR `noLink`+`subject` (email/phone). Role-based group slot. The composite-UNIFICATION of the cluster is a combined-control question, DEFERRED with Phase 2/3 (see active plan).
- **`source`** — per-slot: source selector + secondary (`ref`/`srcTermIn`/`limit`/`sep`) + field (`use`/`key`). Repeated per try_ slot.
- **`fallback`** — global `fallback`, once, last.

Tracking: per-option **group membership only** (set on the shared option-builder, inherited by all
tags). Within-group order = registration order. Multi-slot sort key needs `slot` (parse from `N-`
prefix) so each slot's `source` block stays contiguous; `format`/`link`/`fallback` are global
(slot 0 / ∞).

---

## Image `as` + `size` unification — BUILT v1.16.0 (commit `a426db1`, 2026-07-23)

Built + live-verified (show_if gate, mode-flip stash, migration round-trip via wp eval 6 cases,
harness `as-size-fold-test.php` 15/15). What differed from the sketch below: no `,,` interior problem
needed a toggle (nullary modes are bare — no arg slot — so interior gaps are structurally impossible
without one); the size stash + show_if gate are the composite's own render logic as designed.

### (Design record — GATING-driven, NOT ordering-driven)

**`size` does NOT need to move for the reorder mechanism.** `size` is GB-reserved → GB serializes it
in its fixed built-in block ahead of all custom options regardless. The reorder control only governs
order among custom options. So the serialization-order pull-forward needed zero `size` work.

The `as`+`size` re-key was earned by ONE thing: **conditional visibility.** `size` is consumed by
exactly one return type — `as:url` (verified: `bws_get_attachment_data()`
[image-helpers.php:101-120](../../../includes/helpers/image-helpers.php#L101) touches `$size` only
in the `url`/`default` case; `id`/`alt`/`caption` return the bare datum, size ignored). Today `size`
showed unconditionally — a size control on `as:alt` is dead UI. To gate it, `size` had to leave GB's
native `image-size` support and become an option we own.

#### The frame — `as` is a PARAMETERIZED return-type selector

`as` is NOT a flat destination enum. It selects a **return type**; most return types are **nullary**
(`id`/`alt`/`title`/`caption` — take no argument), but `url` is **unary** — it takes a `size`
**argument**. Size **changes WHICH url** (`medium` vs `full` = different `src`), so size is a
*parameter of the url return*, not an independent option. `as:url,medium` reads as `url(medium)`.
This makes the fold the **natural shape, not a convenience** — a parameterized return type carries
its argument in its own value. **Corollary: `size` exists IFF the return type is `url`.** The
nullary-mode-plus-size state (`as:alt|size:medium`) is **unrepresentable** in the fold — correctly,
it was always dead (ignored at render).

Migrated model notes on ship:
- **CONTEXT.md near [I7]:** `as` = parameterized return-type selector; `url` takes a `size` arg; the arg is **destination-invisible** (url is attribute-slot at ANY size) → **[I7] list-mode gates on the MODE sub-slot only**, never the whole `as` value.
- **tag-reference.md §`as` serialization opt-out:** wire schema `as:<mode>[,<size>]`, size enum + `full` default, always-serialized.

#### Decisions

- **Wire format — folded, always-serialized:** size folds INTO `as`'s value as a comma second slot; ALWAYS present.
  ```
  {{image as:url,full}}      // url(full)   — default size STILL serialized (always-serialize, no strip)
  {{image as:url,medium}}    // url(medium)
  {{image as:alt}}           // alt         — nullary return, no arg slot, bare mode
  ```
  Composite writes bare `mode` for nullary returns, `mode,size` for `url`. Size sub-slot present iff `url`.
- **Always-serialize dissolves the sub-value strip problem.** Size never stripped (parallel to `as`) → NO conditional "strip `,full` tail" logic. Do NOT reuse `_strip_default` for the size sub-slot.
- **Composite OWNS the `as` widget.** `as` value `url,full` is not a valid GB `select` entry → GB's native select would blank/corrupt it on reopen. Composite renders BOTH the mode dropdown AND the size dropdown and owns the whole `as` token. Cost: lose GB's free `as` select + free size ComboboxControl.
- **Pretty size labels — UPGRADE over GB.** GB slug-munges (`medium_large` → "Medium large", first-occurrence-only) and ignores `image_size_names_choose`. Since we own the control, enumerate labels via `apply_filters('image_size_names_choose', [...])` (WP's own media-modal source — respects theme/plugin custom-size labels), backfill unlabeled sizes from `get_intermediate_image_sizes()` with `ucwords(str_replace(['-','_'],' ',$slug))`. `full` guaranteed present. Helper `bws_get_image_size_options()`.
- **show_if is hand-coded, not declarative.** Size sub-widget shows when `mode === 'url'` inside the composite's own render (`show_if` tests whole option values, can't gate a comma sub-slot).
- **Size stash across mode-flip — DECIDED B.** Flipping `url→alt` drops the size arg (nullary return has no arg slot — correct-by-construction). To avoid the editor papercut, the composite stashes last-picked size in React state and restores it on return to `url`. **Wire stays model-pure** — `as:alt` serializes nullary, the stash is editor-only, never touches serialization.
- **Read-side dual-key (render safety):** folded read parses `as` for the size sub-slot, with `?? $options['size'] ?? 'full'` legacy fallback per core. Legacy `size:` stays readable at render. **Parity by construction:** the fold parse lives in the SHARED cores (`bws_featured_image_core` / `bws_custom_image_core`), which both standalone `image` and `try_image` slots dispatch to → [I6] slot-standalone parity holds without any try_ machinery change.
- **Migration (reopen safety) — MANDATORY, value-conditional fold via `transform_callback`.** Dual-key read protects render only, NOT the editor round-trip. Once `image-size` leaves the supports array GB no longer owns `size:`; old `size:medium` becomes an **orphan token** (the stranded-reserved-token trap that ate `tax`). The rewrite is value-conditional:
  - `as` absent → treat as `url` (recovered default), fold → `as:url,<size>`
  - `as:url` present → fold → `as:url,<size>`
  - `as:` **nullary** (`alt`/`id`/`title`/`caption`) + `size:` present → **DROP `size`** (was dead at render), emit bare `as:<mode>`
  - no `size:` → no change
  Registered per image tag variant (`image`, `term_image`, `try_image`). Uses a per-entry `transform_callback` (the `combine_options` primitive can't express "append conditional on current value, sometimes dropping"). **Extract a shared `fold_into` primitive when datetime FW-41 lands** (its `key:date,time` fold is the 2nd concrete instance). We do BOTH: dual-key read (render) + migration (reopen).

  > **POST-SHIP CORRECTION (2026-07-23) — "reopen safety" is Tag-Converter-only; an editor-open fold is ARCHITECTURALLY IMPOSSIBLE.** The `transform_callback` runs solely through `TagConverter` (`wp_ajax_bws_migrate_tags`), which transforms the RAW STRING before GB parses it — that is why it reaches the orphan. It does NOT run on editor-open, and a control-side mount-fold cannot substitute: **`size` is GB-reserved, and GB destructures it out of `parsedTag.params` into its own private `imageSize` React state** (`DynamicTagSelect.jsx:392`, `:443` — both UNGATED on `image-size` support) **and re-serializes it ungated** (`:541`). The `tagSpecificControls` filter context is hardcoded to `{state: extraTagParams, setState: setExtraTagParams}` (`:112`), so a custom control can neither read nor clear `imageSize`. Consequence on open: the composite parses only `as` (shows the DEFAULT size, ignoring the orphan), the normalizer merely reorders, and GB keeps re-emitting the stray `size:`. Verified against GB 2.2.1 source, 2026-07-23, after a live testbed repro. The converter fold itself is correct (harness `as-size-fold-test.php` 15/15, incl. `{{image as:url|size:medium}}` → `{{image as:url,medium}}`). Pinned by matrix row O4.6 (negative on-open control).
- **FW-52 coexistence — DECIDED A.** FW-52 reorder owns `as` POSITION in `extraTagParams`; the fold composite owns `as` VALUE and stays order-agnostic — writes `as:url,medium` at any position, the normalizer re-sorts. Both are whole-object `setState` writers; the re-entrancy guard lets them coexist. Clean value/order split.

**Net cost of fold (all accepted):** own the `as` widget + own the size widget (but ship BETTER
labels than GB) + hand-coded size gate + React-state size stash + `transform_callback` migration.
**Bought:** return-mode + size visible together in one lead token that correctly models `url` as a
parameterized return type; strictly simpler try_image (one fewer per-slot key); pretty size labels
GB never had.
