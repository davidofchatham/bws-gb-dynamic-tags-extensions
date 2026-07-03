# Smart Field Selector — Manual Regression Matrix

**Standing manual regression suite** for the `bws-field-combo` editor control
(v1.13.0) — the integration layer the pure harness can't reach. Rows are anchored
to the field-selector invariants so they stay valid past the SPEC's post-ship
truncation.

> **Re-run trigger:** after any change to `assets/js/field-combo-control.js`,
> `includes/rest/field-discovery.php`, the enqueue/inline block in the main plugin
> file, or a flip of any `key`/`ref`/datetime-key option to (or from)
> `bws-field-combo`.
>
> **Two layers:**
> - **Discovery logic (pure, automated):** `php tools/test/field-discovery-test.php`
>   — kind/scope derive, sub-field flatten, dedupe (ACF-vs-registered collapse,
>   ACF-vs-ACF keep-both, sub-field bare-name collision), DISALLOWED gate, envelope
>   shape. Run first; must be green before the manual rows.
> - **Control / integration (manual, WP):** the M-rows below. Run on a WP test
>   instance with **GenerateBlocks (Pro)** + **ACF**, per the runtime-debug
>   workflow (TEST instance, never the live/cached site).

**How to run:** add a GenerateBlocks block, add a dynamic tag, open its config, and
drive the field controls. `[SUB …]` = a real field on your instance.

---

## M0 — control renders + discovery

| # | Setup | Expect |
|---|---|---|
| M0.1 | `{{text}}` → use = Meta/Option Field | The **Meta/Option Field Key** input is a searchable combobox, not a text box |
| M0.2 | Open the field combobox | Lists ACF fields, sub-fields, options-page fields, term-meta, registered meta |
| M0.3 | Network tab on editor load | NO runtime request to `/bws-dynamic-tags/v1/fields`; `window.bwsFieldEnvelope` present in page source |
| M0.4 | Direct GET `/wp-json/bws-dynamic-tags/v1/fields` logged OUT | 401 `rest_forbidden` (edit_posts cap, V6) |

## M1 — free-text + clear (V11)

| # | Action | Expect |
|---|---|---|
| M1.1 | Type an unregistered key `made_up_key` | Top option **`Use custom key: "made_up_key"`** appears |
| M1.2 | Enter / click that option | Tag serializes bare `key:made_up_key` — no separate "Add" step |
| M1.3 | Pick a real field, then click the ✕ | Value cleared; `key:` omitted from the tag (never a bare `key:`) |
| M1.4 | Save + reload, reopen the tag | The persisted key shows selected (round-trip) |

## M2 — filters (location + type)

| # | Action | Expect |
|---|---|---|
| M2.1 | Filter fields by location = `Post fields › [SUB group]` | List narrows to that group's fields |
| M2.2 | A repeater / group path segment | Flagged `(repeater)` / `(group)` in the location dropdown |
| M2.3 | Filter fields by type = a specific type (Date/Email/…) | List narrows to that ACF type |
| M2.4 | Filter fields by type = **Loop fields** | Only fields that resolve exclusively in a repeater/flex row |
| M2.5 | Both filters at once | List = intersection (AND) |

## M3 — flat list + merge (the two duplicate scenarios)

| # | Setup (needs the collisions on your instance) | Expect |
|---|---|---|
| M3.1 | Same key, **different** labels (e.g. `name` = "Name" in one repeater, "Feature Name" in another) | TWO separate rows, told apart by label |
| M3.2 | Same key, **same** label (e.g. `description` in two repeaters) | ONE row; it appears under BOTH location filters it belongs to |
| M3.3 | A field key present in ≥2 field groups | ONE row (not duplicated); shows under each group's location filter |
| M3.4 | Any list | Flat labels `Label ('key')` — no breadcrumb, no loop-only marker in the row |
| M3.5 | Pick any row (incl. a duplicate-key row) | Serializes the BARE key; reopen shows it selected |

## M4 — dynamic label (V4 + location tracking)

| # | Control / filter state | Expect label |
|---|---|---|
| M4.1 | Base `key`, no source, location = All | "Meta/Option Field Key" |
| M4.2 | Location narrowed to `Post fields` | "Post Meta Field" |
| M4.3 | Location narrowed to a group `… › [SUB group]` | "[SUB group] Field" |
| M4.4 | `srcTermIn` set (term tag) | Presets location to Term fields → "Term Meta Field" |
| M4.5 | `src:site` | "Site Option Field" |
| M4.6 | Datetime key controls | Keep static labels ("Start Date/Time Field Key" etc.) — NOT the kind pair |
| M4.7 | `ref` (relationship key) | Static "Relationship Field Key" |

## M5 — context independence (the GB blind spot)

| # | Context | Expect |
|---|---|---|
| M5.1 | Edit a WP Pattern (`wp_block`) | Field list still populates (GB's own selector would be empty) |
| M5.2 | Edit a GP Element | Field list still populates |
| M5.3 | Base tag in a template, location = All | Default "All detected fields" — NOT auto-assumed to be a post |

## M6 — composition + try_ per-slot

| # | Setup | Expect |
|---|---|---|
| M6.1 | An option with a `show_if` that hides `key` (e.g. use = Title) | The whole field control is hidden (composes with conditional-options) |
| M6.2 | `try_text` — each slot's **Meta/Option Field Key** | Renders the combobox (not a text box) |
| M6.3 | `try_text` slot 2 with `2-srcTermIn` set | That slot's label = "Term Meta Field Key", location presets Term — independent of slot 1 |
| M6.4 | `try_text` slot `ref` (`2-ref`) | Renders the combobox, presets from `2-src` |

## M7 — security (offered ⟺ resolvable, V6)

| # | Action | Expect |
|---|---|---|
| M7.1 | Look for a `DISALLOWED_KEYS` key (e.g. `user_pass`) in any list | Absent — never offered |
| M7.2 | An underscore-protected key that IS resolvable (e.g. `_piecal_*` if present) | Present — resolver allows `_`-protected on frontend |
