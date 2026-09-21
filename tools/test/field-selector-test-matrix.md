# Smart Field Selector — Manual Regression Matrix

**Standing manual regression suite** for the `bws-field-combo` editor control
(v1.13.0) — the integration layer the pure harness can't reach. Rows are anchored
to the field-selector invariants so they stay valid past the SPEC's post-ship
truncation.

> **Re-run trigger:** after any change to `assets/js/field-combo-control.js`, `includes/rest/field-discovery.php`, the enqueue/inline block in the main plugin file, a flip of any `key`/`ref`/datetime-key option to (or from) `bws-field-combo`, either half of the pinned-root narrowing (the `scope` slug on `includes/rest/entity-lookup.php`'s row shapers, and the `window.bwsRootArgKinds` inline the enqueue path emits), or either half of the Location PRESET (the `window.bwsChainKinds` inline, and `predecessorContext()` in `assets/js/slot-fold-control.js` — the seam a chain step hands its successor's picker through).
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
drive the field controls. On the fixture testbed, seed the `core-structures` blueprint
(see [`tools/fixtures/core-structures/README.md`](../fixtures/core-structures/README.md)) —
field/group names in the rows are that blueprint's fixture names (`schema.php` /
`manifest.php` authoritative). On any other instance, substitute your own.

---

## M0 — control renders + discovery

| # | Setup | Expect |
|---|---|---|
| M0.1 | `{{text}}` → use = Meta/Option Field | The **Meta/Option Field Key** input is a searchable combobox, not a text box |
| M0.2 | Open the field combobox | Lists ACF fields, sub-fields, options-page fields, term-meta, registered meta |
| M0.3 | Network tab on editor load | NO runtime request to `/bws-dynamic-tags/v1/fields`; `window.bwsFieldEnvelope` present in page source |
| M0.4 | Direct GET `/wp-json/bws-dynamic-tags/v1/fields` logged OUT | 401 `rest_forbidden` (the `edit_posts` capability, V6) |
| M0.5 | Force the REST fallback (unset `window.bwsFieldEnvelope` in console, reopen a tag) under **plain permalinks** (`?rest_route=`) | Combobox still populates; `apiFetch` path has a leading slash (`/bws-dynamic-tags/v1/fields`), so no 404 to an empty picker |
| M0.6 | As a user who can `edit_posts` but NOT author dynamic data (Author or Contributor role, or an admin with `generateblocks_user_can_author_dynamic_data` filtered false), load the block editor | Page source carries `window.bwsFieldEnvelope = {};` — present and EMPTY, never absent. A direct GET of `/wp-json/bws-dynamic-tags/v1/fields` as that user is 403. No `apiFetch` to that route fires on load: the empty global is what stops the fallback turning the gate into a console error on every editor load (the route's own PHPDoc carries the reachability measurement, and `docs/gb-constraints.md` §Dynamic data is suppressed by POST SOURCE the GB facts behind it). GB itself gives this user no tag builder, so no picker is reachable to populate |

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
| M3.4 | Any list | Flat labels — no breadcrumb, no loop-only marker. A field WITH a distinct label shows `Label (Type, 'key')`; a field with NO label (label falls back to key) shows the key ONCE, with only what the row has not already said (`event_date (Date)`, never `event_date (Date, 'event_date')`). **VERIFIED, user 2026-08-14** |
| M3.4b | Any list, reading the `(Type, 'key')` group | ONE bracket group, not two: the LABEL is what an author scans for, so it keeps the front of the row, and the type and key are both facts ABOUT that field. The type is spelt ONE way and DERIVED from the field definition — never hand-written into an ACF label. `relationship` reads `Relationship`, `post_object` reads `Post Object`, `date_time_picker` reads `Date & Time`. A type with no map entry TITLE-CASES its slug (`page_link` → `Page Link`), so a third-party or newly-added ACF type is presentable with no code change — check one if your instance has any. **Registered meta carries no type, so those rows read `Label ('key')` as before**, never an empty slot; the fixture's `plain_meta_date` is one. A merged record reached through two homes with different types joins them (`Mixed (Text / Number, 'mixed')`). **VERIFIED, user 2026-08-14** |
| M3.4c | Type `post object` into the combobox | The list narrows to post object fields, because the type joins the row's search text. This is a free affordance of the annotation, not a second filter — the **Filter fields by type** select is still the precise instrument, and the two must agree. **VERIFIED, user 2026-08-14** |
| M3.4d | Any list, checking ORDER | Still alphabetical by field LABEL, NOT clustered by type — the sort key does not read the annotation. Two relationship fields called `Alpha` and `Zeta` stay either side of a text field called `Middle`. **VERIFIED, user 2026-08-14** |
| M3.4a | A list containing underscore-prefixed keys (`_gb_conditions`, `_acf_changed`, etc.) | Those keys are DEMOTED to the bottom of the list (still alphabetical among themselves), below all normal-keyed fields. Not hidden — still selectable/resolvable. A `_`-key that HAS a real label still demotes but keeps its `Label ('_key')` display |
| M3.5 | Pick a row for an **unambiguous** key (one record), save + reopen | Serializes the BARE key; reopen shows the friendly `Label ('key')` row selected (injected even if a filter would hide it, V12) |
| M3.6 | Pick a row for an **ambiguous** key (M3.1's `name`, two different-label records), save + reopen | Combobox shows the **raw key** `name` selected, NOT a guessed label; never auto-asserts which field (V12/B4), so the author re-picks to disambiguate |
| M3.7 | Ambiguous saved key with one of its rows currently **visible** in the active filter | Still shows the raw key, does NOT auto-highlight the visible row (V12) |

## M4 — dynamic label (V4 + location tracking)

| # | Control / filter state | Expect label |
|---|---|---|
**The label always ends in "Key".** It names the FIELD and then the control — "Term Meta Field Key", "Client Details Field Key". Between 1.13.0 and 1.21.0 the dynamic path dropped that noun while the static label kept it, which put the string "Meta/Option Field" directly under the `use` select's own VALUE of the same name, one a chosen option and one the next control's label. M4.1 had carried the right form all along and M4.2/M4.4/M4.5 the wrong one; the suffix is restored and every row below states it.

| M4.1 | Base `key`, no source, location = All | "Meta/Option Field Key" |
| M4.2 | Location narrowed to `Post fields` | "Post Meta Field Key" |
| M4.3 | Location narrowed to a group `… › Event Details` | "Event Details Field" |
| M4.4 | `srcTermIn` set (term tag) | Presets location to Term fields → "Term Meta Field Key" |
| M4.5 | `src:site` | Presets location to Site fields → "Site Option Field Key" |
| M4.5b | `src:ref` set | Location presets to **"Post fields"**, label reads **"Post Meta Field Key"** — a ref hop lands on a post and the plugin forces that kind. The target's post TYPE is still unknown, so nothing narrows to one post type (M11) |
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
| M6.5 | A `term_` modifier tag (`term_text` / `term_content` / `term_image`), open its **Meta/Option Field Key** control | Renders the combobox (NOT a text box); location presets to **Term fields**, label reads "Term Meta Field Key". Confirms the modifier-template key flips (base-tags.php text/content/image templates), not just the base tags. (`view_` is an external plugin — not covered here.) |
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
output. **Fixture:** the `core-structures` blueprint registers all four keys —
`bws_global_note` (global), `bws_page_only` (page-only), `subtitle` (global,
colliding with the ACF `subtitle` field on post), `bws_cat_note`
(`register_term_meta` on category) — see
[`tools/fixtures/core-structures/schema.php`](../fixtures/core-structures/schema.php)
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

## M11 — src:ref scope + label

| # | Action | Expect |
|---|---|---|
| M11.1 | Set a base tag to `src:ref`, open its `key` picker | Location filter presets to **"Post fields"**, control label reads **"Post Meta Field Key"**. A ref hop lands on a post and the plugin forces that kind, so saying so withholds nothing the render is not already committed to. **This REVERSED in 1.21.0** — it read "All detected fields" / "Meta/Option Field" from 1.13.0, on a reason about the target's post TYPE being unknown, which is a different axis from the kind the filter states. Term and site fields are no longer offered here, because a post read cannot reach them; widen to "All detected fields" and free text still commits any key |
| M11.2 | Same tag, but authored as a chain (`In Reference/Relational Field`) | Identical to M11.1 — which is the whole of what you can observe. **A flat `src:ref` cannot be seen in the editor at all:** `BaseSrcMountMigrator` commits the fold from a mount effect, so the panel has already been rewritten to `refs,<field>` by the time it paints. The flat arm exists for a stack where the chain grammar failed to load, is harness-only (`field-combo-control-test.js` §F15.6/§F15.11), and no row here drives it |
| M11.3 | Any `src:ref` tag, then narrow to a single post TYPE | Narrowed, since 1.21.0 — the post types come off the relationship field's own config rather than off the wire. **M15 owns this axis**; the row stays here because this is where it was recorded as missing |

---

## M12 — root-argument narrowing (FW-39 D22)

A root with an argument is the one source whose entity KIND and entity are both known before a render, so it is the one root the picker can narrow against. `current` and every other argless root cannot, and that asymmetry is the design, not a gap (D22). The narrowing rides the discovery envelope's EXISTING per-field `scope` plus the entity's own lookup — **if a row here fails, check that the entity row carries a `scope` slug and that `window.bwsRootArgKinds` is present in the page source** before suspecting the picker.

M12.1 and M12.2 are the side-by-side pair: same tag, same field list underneath, one with a selected entity and one without.

Verified 2026-09-18 after the kind narrowing landed, in two halves, because they are not the same evidence. **M12.3 / M12.9 / M12.10 / M12.11 / M12.12 were driven in the editor**, which is the only way to see the modal, the two filter selectors, reopen-after-change and the per-slot and `{{join}}` paths. **M12.1 / M12.2 / M12.5 / M12.13 were measured headless** — the shipped control rendered against this testbed's own `bws_field_discovery_collect()` envelope — so what they establish is the LIST, and the row counts they quote are that measurement's.

| # | Action | Expect |
|---|---|---|
| M12.1 | Base `{{text}}`, Source = **Current Context**, open the `key` picker | The UNNARROWED list — `contact_email` (Staff Contact, `staff`), `event_date` (Department Event Date, `department`) and every other discovered field are all offered together |
| M12.2 | Same tag, Source = **Term**, select a `department` term (e.g. Support), reopen the `key` picker | NARROWED to the six `department`-scoped fields — `Department Blurb ('blurb')`, `Department Charter ('charter')`, `Department Event Date ('event_date')`, `Department Lead ('dept_lead')`, `Email ('email')`, `Phone ('phone')` — plus any field whose group has no location scope AND is of kind `term`, which on this site is the one globally registered term meta key, `Search Engine Optimization ('slim_seo')`. **SEVEN rows, nothing else.** The `staff`-scoped fields (`contact_email`, `related_staff`) are GONE, and since 1.21.0 so are the unscoped POST ones (M12.12). The `slim_seo` row comes from an installed plugin rather than from the fixture, so check the envelope before reading its absence as a failure |
| M12.3 | Change the selection to a term of a DIFFERENT taxonomy, reopen the picker | The list re-narrows to that taxonomy's fields with no page reload — the scope is derived per render, never cached against the first selection |
| M12.4 | Same tag, Source = **Post**, select a `staff` post (e.g. Jane Partner) | NARROWED to the `staff`-scoped fields — `Contact Email ('contact_email')`, `Related Staff ('related_staff')` — and the `department`-only fields are gone. Same rule, other kind |
| M12.5 | Select a post of a type NO field group is scoped to (a `bws_cv_probe`, say) | Only the unscoped fields OF KIND POST are offered — the globally registered post meta: `bws_global_note`, `subtitle`, `slim_seo`, and the two underscore keys demoted to the bottom. NOT the full list, and not an error: an entity with no fields of its own is the answer the narrowing exists to give. Free-typing a key still commits |
| M12.6 | Select a term, then add a `refs` step after it, and open the STEP's field picker | Narrowed to the selected term's taxonomy — the step's field is read off that term, which is the entity just before it |
| M12.7 | Add a SECOND step after a `refs` step and open ITS field picker | Not narrowed to an ENTITY — a `refs` argument names the field stepped THROUGH, so WHICH post it lands on is still unknown at parse time, and that is the axis this section is about. Since 1.21.0 it IS narrowed to the field's allowed post TYPES, which is a different axis and M15's (M15.4). A `rows` predecessor names an entity outright, M13.9 |
| M12.8 | With a selected entity and a `refs` step, open the tag's own `key` picker | UNNARROWED — the read applies to the step's target, not to it |
| M12.9 | `try_text` slot 2: select a term on slot 2 while slot 1 names a POST | Slot 2's field picker narrows to slot 2's taxonomy, independently of slot 1 (the same per-slot independence M6.3 asserts for the location preset) |
| M12.10 | `{{join}}` field with a selected entity, open its field picker | Narrows identically to the base tag's — one rule, all three containers (D11) |
| M12.11 | Select a term, then DELETE that term in another tab and reopen the picker | The list is UNNARROWED, never empty: a selection that will not resolve must not hide every scoped field, because a transient failure and "this taxonomy has no fields" would look identical |
| M12.12 | Source = **Term**, select any term, and look for the globally registered POST meta `bws_global_note` (or `subtitle`) | NOT offered. **CHANGED in 1.21.0** — from 1.20.0 both were offered under a term, because a group with no scope of its own was read as reaching any kind. Every discovery group carries a kind and an empty `scope` means any subtype of THAT kind, so a term read cannot reach a post-kind field and the picker stops offering one. Nothing else narrows: free text still commits any key |
| M12.13 | Source = **Term**, select a term of a taxonomy no field group targets (a `post_tag`, say) | ONE row, `Search Engine Optimization ('slim_seo')` — M12.5's rule on the term side. `department`'s six fields are absent, and so is every post-kind field; the list is short rather than empty because the site carries exactly one globally registered term meta key |

---

## M13 — Location preset from a chain's terminal repeater (FW-74)

A source chain ending on **In Repeater Rows** names the exact home of every field the read can reach, so the Location filter opens there instead of on the kind root. It is a STARTING VIEW, not a lock — both selectors stay visible and widen back, which is what separates this from the `{{table}}` column auto-scope (M2/§F8), where the filters are hidden because the scope IS the filter.

The recognition is machine-readable at both ends: the chain is parsed through the shipped grammar, and whether the tail's argument names a repeater is asked of the discovery envelope's own container record. So a step type this matrix never mentions presets correctly the day it is added, and a repeater nobody discovered presets nothing rather than opening on an empty list.

| # | Action | Expect |
|---|---|---|
| M13.1 | Base `{{text}}` on a post, Source = **Current Context › In Repeater Rows** with `team_members` chosen, open the `key` picker | Location filter reads **"Post fields › Team › Team Members (repeater)"**, and the list holds that repeater's sub-fields only (`Name`, `Description`, `Role`, …). The control label reads **"Team Members Field Key"** |
| M13.2 | Widen the Location filter back to "All detected fields" | The full list returns — the preset is a starting view, and both selectors were visible the whole time |
| M13.3 | Add a step BEFORE the repeater step (e.g. Post → In Repeater Rows) | Unchanged from M13.1: the preset comes off the chain's TAIL, which is the step the read applies to |
| M13.4 | Chain the NESTED repeater — `duty_roster` then `shifts` (page fixture) — and open the `key` picker | Presets to **"Post fields › Duty Roster › Duty Roster › Shifts"**, offering `Day` and `Hours`. Nesting needs no special case: the tail is still a repeater and its children still hang one segment below it |
| M13.5 | Set the tail step to **In Reference/Relational Field** or **In Taxonomy Term** instead | Location presets to the KIND that step produces — "Post fields" and "Term fields" respectively (M14) — never to a repeater path. Neither argument names a container, so this section's rule declines and the kind rule answers |
| M13.6 | Hand-type a repeater key that no field group defines | No preset, and the FULL list — never an empty view. Free-typing the sub-field key still commits |
| M13.7 | `try_text` slot 2: give slot 2 a repeater chain while slot 1 has none | Slot 2's picker presets off slot 2's own chain (the per-slot independence M6.3 and M12.9 assert on the other two axes) |
| M13.8 | `{{join}}` field with a repeater chain, open its field picker | Presets identically to the base tag's — the fold containers hand the picker their terminal step, so one rule serves all three (D11) |
| M13.9 | Chain **In Repeater Rows** `duty_roster`, then add a SECOND **In Repeater Rows** step and open THAT step's own field picker | Offers only the repeaters nested inside `duty_roster` (`shifts`), not every repeater on the site. The predecessor names what it resolved to, so it is handed over at any position — contrast M12.7, where a `refs` predecessor names nothing and the picker stays wide |

---

## M14 — the kind preset follows the CHAIN (FW-13 nibble)

The Location filter's kind preset asks what `bws_fold_chain_resolution()` asks: the tail step's produced kind, or the root's where that answers at parse time. Both maps come from PHP on `window.bwsChainKinds`, assembled by `bws_fold_wire_vocabulary()` from the same constants the render seam dispatches on — **if a row here fails, check that global is present in the page source** before suspecting the picker.

Before 1.21.0 the derivation read the LEGACY FLAT keys only. `srcTermIn` has been dropped at registration since 1.17.0, so the only term preset in the plugin sat on wire that can no longer be authored, while `terms,<tax>` — the chain spelling that replaced it — presetted nothing. M14.1 and M14.2 are that pair.

| # | Action | Expect |
|---|---|---|
| M14.1 | ~~Open a tag saved before 1.17.0 carrying a flat `srcTermIn`~~ | **NOT RUNNABLE — do not attempt.** The mount migrator folds `srcTermIn` into a `terms` step from a `useEffect`, so the panel never paints the flat state; "open it without touching the source" is not a thing an author can do. The flat arm is for a stack where the chain grammar failed to load, and it is pinned there instead (`field-combo-control-test.js` §F15.1/§F15.11). Row kept, struck, because the obvious test to write here is one that cannot work |
| M14.2 | Base `{{text}}`, Source = **Current Context › In Taxonomy Term** with a taxonomy chosen | Identical to M14.1. This is the regression the flat-only derivation left behind |
| M14.3 | Source = **Site** | "Site fields" / "Site Option Field Key", as before. It used to match by a literal equality on the token and now comes off the root map; the visible answer is unchanged |
| M14.4 | Source = **In Reference/Relational Field** with a field chosen | "Post fields" / "Post Meta Field Key" — no slug is exempt from the map. See M11.1 for the reversal this was |
| M14.5 | Source = a selected **Term** or **Post** | Label reads "Term/Post Meta Field Key", and the Location filter stays on the ALL row, which since 1.21.0 reads **"All available fields"** rather than "All detected fields" (M14.8). The entity's own scope narrowing (M12) has already answered which fields are readable, and since 1.21.0 it binds the kind too (M12.12), so a kind preset on top would only restate it. On a selection that will not resolve the narrowing stands down and the list stays wide (M12.11); a kind preset would go on filtering there, at exactly the moment the author has least to go on |
| M14.6 | `try_text` slot 2 with a different source kind from slot 1 | Slot 2 presets off slot 2's chain (per-slot independence, as M6.3 / M12.9 / M13.7) |
| M14.7 | Source = **Term**, but leave the term picker EMPTY, then select one | Location stays on the ALL row in BOTH states, and the label reads "Term Meta Field Key" in both. The test is what the root IS, not whether it is filled. Until 1.21.0 the gate read the RESOLVED argument, so the empty state presetted "Term fields" and choosing a term dropped it back — the filter loosened as the author supplied information. The ALL row's own NAME does change across the two, and that is M14.8's axis, not this one: it tracks the pool, where the filter tracks the root |
| M14.8 | Watch the first Location option across M14.7's two states | Reads **"All detected fields"** with the picker empty and **"All available fields"** once a term is selected. A narrowed pool holds records the filter's own options can never reach, so the ALL row claiming the site is a claim the list beside it contradicts — and an author reads that row as the way back, which this narrowing is not. The `value` is `__all_locations` in both states: a rename, not a state, and nothing that reads the active filter sees it. Stands down again when the narrowing does (M12.11 / §F13.11b), so the row is honest in every state rather than warning in one. **A `refs` tail renames the row too and does NOT display it:** that source presets Location to "Post fields" (M14.4), so its ALL row reads only inside the open dropdown — where the author goes looking for the way back, which is the position that matters. A closed selector reading "Post fields" under a narrowed refs pool is M14.4 working, not this row failing. Pinned `field-combo-control-test.js` §F13.11/§F13.11b; the refs-tail half is §F16.1b/§F16.4b, matrix M15.5 |

---

## M15 — the refs-tail narrowing to post TYPES (FW-13)

M11.1 / M14.4 take a `refs` tail as far as kind `post`. This is the axis under it: the step's argument names a relationship or post object, and THAT FIELD declares which post types it can land on. The editor cannot derive them — a `refs` argument is the field stepped through, not what it reaches — so they arrive stamped on each discovery record as `ref_types`, the same way `repeater_key` arrives for a `rows` step. **If a row here fails, check that the envelope's ACF entries carry `ref_types` before suspecting the picker.**

**This narrowing is LOOSE by design where the field is.** A relationship allowing three post types offers all three types' fields, and a field resolving for one of them and not another is the honest state — the type genuinely varies per target. What it stops offering is fields of a post type the step provably cannot reach.

Measured 2026-09-18, HEADLESS: the shipped control rendered against this testbed's own `bws_field_discovery_collect()` envelope, which is what the row counts below are. That establishes the LIST and the Location options; it does not exercise the modal, the filter selectors or reopen-after-change, and the editor half of M15.1 and M15.4 has NOT been driven.

| # | Action | Expect |
|---|---|---|
| M15.1 | Base `{{text}}`, Source = **In Reference/Relational Field**, field `related_staff` (allowed post type: `staff`), open the `key` picker | NARROWED to 56 rows from the 189 the same tag offers with an unrecognized field (M15.3). `Contact Email ('contact_email')` and `Role ('role')` survive — Staff Contact is scoped `page`+`staff` — and so do the unscoped post-kind keys `bws_global_note` and `slim_seo`. `Duty Roster ('duty_roster')` (`page` only), `Team Members ('team_members')` (`post`+`page`) and every `mc_*` field are GONE |
| M15.2 | Same tag, field `mc_related_items` instead (allowed post type: `mc_section`) | NARROWED harder — 14 rows, the three MC groups plus the unscoped registered post meta. A different field on the same tag gives a different list, which is what makes M15.1 a narrowing rather than a coincidence |
| M15.3 | Same tag, hand-type a field key no discovery found | The full POST-kind list, 189 rows — never empty. A field whose config cannot be read keeps every post type, which is the honest answer; the kind is still bound (M15.5b) |
| M15.4 | Field `post_ids` (seven allowed post types) | The UNION — 106 rows. `Duty Roster` is back (`page` is allowed), `Contact Email` and the MC fields are still there, and the `portal` / `product` / `gp_elements`-scoped registered meta is not. Wider than M15.1, still not M15.3 |
| M15.5 | With M15.1 set, widen the Location filter back to its ALL row — which reads **"All available fields"** here, not "All detected fields" (M14.8 owns that wording) | Still no term or site field anywhere in the list. The narrowing is the POOL, not the view: the Location filter's own options offer `Post fields` and nothing but `Post fields` roots, so widening it cannot reach another kind. (Widening DOES restore every post-kind home the narrowed pool still holds, which is the M13.2 property on this axis) |
| M15.5b | Same, but with M15.3 set — the unrecognized field key, where nothing narrows by TYPE | Identical on the kind axis: 189 rows before and after widening, and still no term or site root in the Location options. The KIND gate does not ride on the types being known — a relationship step lands on a post whatever field it steps through. Until this landed the gate ran only where types WERE known, so this case kept 16 unreadable term and site fields one widening click away, behind a preset that is a starting view. Pinned `field-combo-control-test.js` §F16.4b; the contrast is M12.1, where an argless source legitimately keeps all three kinds |
| M15.6 | Add a SECOND step after the `refs` step and open THAT step's field picker | Narrowed by the FIRST step's allowed post types — the successor's picker is handed `refs,<field>` by the chain control, the same hand-off `rows` gets (M13.9). This is the half M12.7 says is not an ENTITY narrowing; it is a TYPE one |
| M15.7 | `{{join}}` field, or `try_text` slot 2, with the same `refs` chain | Narrows identically to the base tag's — one rule, all three containers (D11), because the fold hands its read picker the terminal step spelled as the base tag's `src` spells it |
| M15.8 | A `terms` tail instead of a `refs` one | Untouched by this — a `terms` argument is a taxonomy and the step produces a term, which is the KIND axis M14.2 already answers. A post-type narrowing reaching it would be filtering term records by post-type slugs |
