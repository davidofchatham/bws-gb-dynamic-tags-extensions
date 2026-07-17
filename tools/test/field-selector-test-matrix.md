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
drive the field controls. On the fixture testbed, seed the `tags-core` blueprint
(see [`tools/fixtures/tags-core/README.md`](../fixtures/tags-core/README.md)) —
field/group names in the rows are that blueprint's fixture names (`schema.php` /
`manifest.php` authoritative). On any other instance, substitute your own.

---

## M0 — control renders + discovery

| # | Setup | Expect |
|---|---|---|
| M0.1 | `{{text}}` → use = Meta/Option Field | The **Meta/Option Field Key** input is a searchable combobox, not a text box |
| M0.2 | Open the field combobox | Lists ACF fields, sub-fields, options-page fields, term-meta, registered meta |
| M0.3 | Network tab on editor load | NO runtime request to `/bws-dynamic-tags/v1/fields`; `window.bwsFieldEnvelope` present in page source |
| M0.4 | Direct GET `/wp-json/bws-dynamic-tags/v1/fields` logged OUT | 401 `rest_forbidden` (edit_posts cap, V6) |
| M0.5 | Force the REST fallback (unset `window.bwsFieldEnvelope` in console, reopen a tag) under **plain permalinks** (`?rest_route=`) | Combobox still populates; `apiFetch` path has a leading slash (`/bws-dynamic-tags/v1/fields`), so no 404 to an empty picker |

## M1 — free-text + clear (V11)

| # | Action | Expect |
|---|---|---|
| M1.1 | Type an unregistered key `made_up_key` | Top option **`Use custom key: "made_up_key"`** appears |
| M1.2 | Enter / click that option | Tag serializes bare `key:made_up_key` — no separate "Add" step |
| M1.3 | Pick a real field, then click the ✕ | Value cleared; `key:` omitted from the tag (never a bare `key:`) |
| M1.4 | Save + reload, reopen the tag | The persisted key shows selected (round-trip) |
| M1.5 | Type a custom key that is a **substring of a visible label** (e.g. `city` when "City ('venue_city')" is listed) | **`Use custom key: "city"`** still appears; suppression is exact-key, NOT substring-of-label (V12/B3), so the literal `city` remains committable |
| M1.6 | With a field whose key is `Event_Date` in the list, type the **case-variant** `event_date` | **`Use custom key: "event_date"`** appears (case-sensitive match), and committing serializes `key:event_date` verbatim, NOT `Event_Date`. Meta keys are case-sensitive (B6). |

## M2 — filters (location + type)

| # | Action | Expect |
|---|---|---|
| M2.1 | Filter fields by location = `Post fields › Event Details` | List narrows to that group's fields |
| M2.2 | A repeater / group path segment | Flagged `(repeater)` / `(group)` in the location dropdown |
| M2.3 | Filter fields by type = a specific type (Date/Email/…) | List narrows to that ACF type |
| M2.4 | Filter fields by type = **Loop fields** | Any field with a loop (repeater/flex row) home; a field that ALSO resolves outside a row still shows ("usable in a loop", NOT "row-exclusive") |
| M2.5 | Both filters at once | List = intersection (AND) |
| M2.6 | **Flex breadcrumb (F1).** Fixture ships this: the **Page Builder** group (page) has flex `Blocks` with a `Hero` layout containing `Headline` (name `headline`), AND a second flex `Sidebar` with its own `Hero` + `headline`. Open a base tag's key picker on a page, open the Location filter | The `Headline` sub-field's location path is **`Post fields › Page Builder › Blocks › Hero`** — i.e. it nests under the flex field's own label (`Blocks`), not a bare `Hero`. The second flex confirms the two `Headline`s live under distinct paths (`… › Blocks › Hero` vs `… › Sidebar › Hero`), not collapsed to one `Hero`. (Pure test already asserts the `Blocks › Hero` parent_path; this confirms the Location UI reads it.) |

## M3 — flat list + merge (the two duplicate scenarios)

| # | Setup (needs the collisions on your instance) | Expect |
|---|---|---|
| M3.1 | Same key, **different** labels (fixture: `name` = "Name" in Team's repeater, "Feature Name" in Product Features') | TWO separate rows, told apart by label |
| M3.2 | Same key, **same** label (fixture: `description` = "Description" in both repeaters) | ONE row; it appears under BOTH location filters it belongs to |
| M3.3 | A field key present in ≥2 field groups (fixture: `contact_email` in Staff Contact + Event Details) | ONE row (not duplicated); shows under each group's location filter |
| M3.4 | Any list | Flat labels — no breadcrumb, no loop-only marker. A field WITH a distinct label shows `Label ('key')`; a field with NO label (label falls back to key) shows the key ONCE (`event_date`, not `event_date ('event_date')`) |
| M3.4a | A list containing underscore-prefixed keys (`_gb_conditions`, `_acf_changed`, etc.) | Those keys are DEMOTED to the bottom of the list (still alphabetical among themselves), below all normal-keyed fields. Not hidden — still selectable/resolvable. A `_`-key that HAS a real label still demotes but keeps its `Label ('_key')` display |
| M3.5 | Pick a row for an **unambiguous** key (one record), save + reopen | Serializes the BARE key; reopen shows the friendly `Label ('key')` row selected (injected even if a filter would hide it, V12) |
| M3.6 | Pick a row for an **ambiguous** key (M3.1's `name`, two different-label records), save + reopen | Combobox shows the **raw key** `name` selected, NOT a guessed label; never auto-asserts which field (V12/B4), so the author re-picks to disambiguate |
| M3.7 | Ambiguous saved key with one of its rows currently **visible** in the active filter | Still shows the raw key, does NOT auto-highlight the visible row (V12) |

## M4 — dynamic label (V4 + location tracking)

| # | Control / filter state | Expect label |
|---|---|---|
| M4.1 | Base `key`, no source, location = All | "Meta/Option Field Key" |
| M4.2 | Location narrowed to `Post fields` | "Post Meta Field" |
| M4.3 | Location narrowed to a group `… › Event Details` | "Event Details Field" |
| M4.4 | `srcTermIn` set (term tag) | Presets location to Term fields → "Term Meta Field" |
| M4.5 | `src:site` | Presets location to Site fields → "Site Option Field" |
| M4.5b | `src:ref` set | Location stays **"All detected fields"** (NOT preset to Post), label stays generic **"Meta/Option Field"**; ref-hop target PT is unknown, so no auto-scope (V3) |
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
| M6.4 | `try_text` slot `ref` (`2-ref`) | Renders the combobox; presets from that slot's `2-src` (`2-srcTermIn`→Term, `2-src:site`→Site, `2-src:ref`→unscoped), independent of slot 1 |
| M6.5 | A `term_` modifier tag (`term_text` / `term_content` / `term_image`), open its **Meta/Option Field Key** control | Renders the combobox (NOT a text box); location presets to **Term fields**, label reads "Term Meta Field". Confirms the modifier-template key flips (base-tags.php text/content/image templates), not just the base tags. (`view_` is an external plugin — not covered here.) |
| M6.6 | Base `{{content}}` (use = Meta/Option Field) and base `{{image}}` (use = Meta/Option Field) key controls | Each renders the combobox with its help nuance intact (content: "renders through the content pipeline"; image: "attachment ID or URL") and NO `src:site` / dot-path text. Confirms the 5 late-caught flips |

## M7 — security (offered ⟺ resolvable, V6)

| # | Action | Expect |
|---|---|---|
| M7.1 | Look for a `DISALLOWED_KEYS` key (e.g. `user_pass`) in any list | Absent — never offered |
| M7.2 | An underscore-protected key that IS resolvable (e.g. `_piecal_*` if present) | Present — resolver allows `_`-protected on frontend |

## M8 — envelope encoding resilience (edge)

| # | Setup | Expect |
|---|---|---|
| M8.1 | An ACF field label / group title with a broken UTF-8 byte (or extreme repeater nesting) that makes `wp_json_encode` return false | Editor still loads; inline emits `window.bwsFieldEnvelope = {};` (empty object, NOT the syntax-error `= ;`), and the control falls back to the REST fetch (`bws_field_discovery_get_envelope_json` false-guard). Hard to force; verify the guard by unit inspection if not reproducible on the instance |
| M8.2 | Name an ACF field label (or group title) literally `Break </script><b>x</b>` and load the block editor | Editor loads normally; NO broken layout, NO injected `<b>` rendered. View source: the inlined `window.bwsFieldEnvelope` shows the `<` escaped (`</script>`), so the label cannot close the inline `<script>`. The field still appears in the picker with its literal label. (B5 `JSON_HEX_TAG` escape.) |

## M9 — registered meta discovery + scope (A / B7 / B8)

The pure harness (`field-discovery-test.php`) covers the dedupe/scope LOGIC on
synthetic envelopes; these rows verify it against LIVE `get_registered_meta_keys`
output. **Fixture:** the `tags-core` blueprint registers all four keys —
`bws_global_note` (global), `bws_page_only` (page-only), `subtitle` (global,
colliding with the ACF `subtitle` field on post), `bws_cat_note`
(`register_term_meta` on category) — see
[`tools/fixtures/tags-core/schema.php`](../fixtures/tags-core/schema.php)
`bws_fixture_tags_core_register_meta()`.

| # | Action | Expect |
|---|---|---|
| M9.1 | Open any base tag's field picker, filter type = All | `bws_global_note` (global) is listed — registered meta is discovered |
| M9.2 | Same picker | `bws_page_only` (subtype-registered to `page`) is ALSO listed — subtype meta is no longer invisible (B8). Before the fix it was absent |
| M9.3 | With the ACF `subtitle` field (on `post`) AND the global registered `subtitle` both defined | BOTH survive: the picker shows the ACF `subtitle` (its richer label/type) and does NOT drop the global registered `subtitle`. Same reach would merge; differing reach keeps both (B7) |
| M9.4 | Sanity: scan the list for junk | NO keys from built-in container subtypes (`revision`, `nav_menu_item`, `attachment` unless you registered any) flood the list — empty subtypes yield no group |
| M9.5 | With the fixture's `register_term_meta( 'category', 'bws_cat_note', … )` active, open a `srcTermIn:category` tag's key picker | `bws_cat_note` appears under term fields (subtype term meta discovered) |

## M10 — reopen selection (V12, post-memoization)

The filtered/options/valueToKey/selectedValue derivation moved into one `useMemo`
this cycle; these confirm the selection behavior is intact under it. (Overlaps
M3.5-3.7; kept here as the focused reopen pass.)

| # | Action | Expect |
|---|---|---|
| M10.1 | Save a tag with a key matching exactly ONE field, reopen | Combobox shows the friendly `Label ('key')` row selected, even if a filter would hide it |
| M10.2 | Save a key that maps to TWO fields with different labels (same key, e.g. `name` = "Name" and "Feature Name"), reopen | Combobox shows the **raw key** `name` selected, NOT a guessed label (V12/B4) |
| M10.3 | Type into the combobox after reopen | Filtering still works on every keystroke (synthetic `Use custom key` appears/disappears as you type) — memoization did not freeze the filter |

## M11 — src:ref scope + label (V3)

| # | Action | Expect |
|---|---|---|
| M11.1 | Set a base tag to `src:ref`, open its `key` picker | Location filter defaults to **"All detected fields"** (NOT preset to Post), control label reads generic **"Meta/Option Field"** — ref-hop target is unknown, so unscoped (V3). Post fields still reachable by choosing them |
