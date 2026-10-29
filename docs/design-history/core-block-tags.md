# Archive: dynamic tags in core blocks — FW-156 (SHIPPED, unreleased, PR #156)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.
>
> Lifted whole from `.scratch/plans/core-block-tags.md` (§1–§6 and the three "Measured" sections) when FW-156 merged on 2026-10-28; the plan continues for FW-157 (§7) and stays private. The body is the plan as written: its "nothing built" status line, its OPEN candidate set (§3) and §3a's superseded picker presentation are the record of how the design moved, not current state. The shipped picker is a filterable table, decided 2026-10-27 to 28 in the build spec, which PR #156's body publishes. Current state: `docs/tag-reference.md` §Tags in core blocks, `docs/plugin-integration.md` §7, and the PHPDoc in `includes/helpers/core-block-tags.php`. The paths it cites under `.scratch/` were private when written and are not repointed.

Original title: Plan: dynamic tags in core blocks (FW-156) + the Block Bindings avenue (FW-157)

**Status:** design settled in a grill (user, 2026-10-12/13); nothing built. Candidate set (§3) still open. Decisions 3 and 4 (who enables it, what can be offered) reopened 2026-10-26 around a block-selection UI modeled on GB's Editor Access screen (§3a). FW-157 (§7) is an avenue to evaluate, not a decision.
**Tracker:** FW-156 (this plan §1–§6), FW-157 (§7).
**Origin:** split out of FW-155 on 2026-10-12. The Dynamic Template Tags snippet rendered its tags in every block; GB replaces only inside its own allowlisted blocks, so converted tags in core blocks would print literally.

## 1. GB facts this design rests on

Read 2026-10-12/13 against GB 2.4.1 (2.5.0-rc.2 `class-dynamic-tags.php` has the same lines) and WP 7.0 core. Not measured on the testbed.

- **The gate.** `render_block` filter at priority 10 (`includes/dynamic-tags/class-dynamic-tags.php:25`); `in_array( $block_name, get_allowed_blocks(), true )` at `:377`. Default list `:350-364`: eight `generateblocks/*` blocks, filtered through `generateblocks_dynamic_tags_allowed_blocks`. GB Pro adds accordion and tabs (`generateblocks-pro/includes/general.php:811`). This plugin does not hook the filter today.
- **Own markup only.** Tags are found in `$block['innerHTML']` (`class-register-dynamic-tag.php:113-117`, `:133`), which excludes inner blocks, so a GB wrapper never reaches a nested core block; each core block type has to be on the list itself.
- **Only registered tags.** `find_matches()` builds its regex from the registry keys (`class-register-dynamic-tag.php:91-102`), rechecked at `:147`. An unregistered `{{foo}}` stays literal. Options are not validated; `parse_options()` (`:49-73`) hands the raw pairs to the callback.
- **Empty blanks the block.** `required` defaults true (`:159`); any tag resolving empty returns `''` for the whole block (`:189-191`) unless the tag has `required:false`.
- **The save gate widens too.** `GenerateBlocks_Dynamic_Tag_Security::get_dynamic_tag_enabled_blocks()` reads the same list (`includes/class-dynamic-tag-security.php:257-266`), so an enabled core block type puts its `{{` content under GB's restricted-user save validation (`docs/gb-constraints.md` §GB's dynamic-tag trust model).
- **Suppression.** `should_suppress_dynamic_data()` and the non-trusted block-renderer check blank every tag at the gate (`class-dynamic-tags.php:378-386`) and again in the replacement layer (`class-register-dynamic-tag.php:125-131`). Neither looks at block or tag name.
- **Attributes.** URL attributes get `esc_url`, other attributes `esc_attr`, `on*`/`srcdoc` stripped (`class-register-dynamic-tag.php:283+`). For a user without `unfiltered_html`, kses reads text before the first `:` in a URL attribute as a protocol (`kses.php:2010-2027`): `{{image as:url,full}}` in `src`/`href` saves as `url,full}}`. Text content is unaffected.
- **Loop context.** The looper calls `$query->the_post()` for WP_Query loops (`includes/blocks/class-looper.php:126`) and builds each item with `postId`, `postType`, `generateblocks/queryType`, `loopIndex`, `loopItem` (`:131-141`). Repeater, term and user loops go through `generateblocks_looper_render_loop_items` (`:81`) and set no global. `WP_Block::$available_context` is `protected` (WP 7.0 `class-wp-block.php:58`); `$instance->context` holds only the keys a block type's `usesContext` declares (`:163-166`). Core blocks declare none of GB's, so `bws_get_loop_item_context()` (`field-helpers.php`) sees `in_loop=false` there and our reads fall to ambient: inside a non-WP_Query loop that is the page's post, a leak.
- **The snippet** (v2.0.0, `template-tags-v2.0.0.php:37`) hooked `render_block` at priority 9 with no block-name check and replaced over the full rendered content, inner blocks included.

## 2. Decisions

1. **Purpose.** v1 unblocks FW-155's Site H `core/table` tags; the setting is designed so any block type can be added later.
2. **Opt-in per block type**, never per instance. Everything off on install and upgrade, including sites detected as former snippet users.
3. **Who enables it:** a settings-page checkbox list, plus our own PHP filter applied after the saved setting. **REOPENED 2026-10-26, resolved in the same grill:** one always-shown block list, empty means off, see §3a.
4. **What can be offered:** a fixed list we vouch for on the settings page; the filter can add anything, with no promise from us. A block joins the fixed list only after a browser check shows a hand-typed `{{tag key:x}}` survives the editor's save round trip, plus a testbed render row. **REOPENED 2026-10-26, resolved in the same grill:** every registered block is selectable; "vouched" becomes "classified" (content kind known, browser-tested), and a render-time guard covers block-level tags in classified inline-only blocks, see §3a.
5. **Accepted as GB's behavior**, documented next to the setting: a required tag resolving empty blanks the whole core block; GB's save gate refuses `{{` content in enabled block types from restricted users. Writing `required:false` into converted tags is FW-155's question, not this row's.
6. **No ambient leaks for our tags.** Missing coverage is acceptable; a tag silently reading the page instead of the loop item is not. Each enabled core block type gets GB's loop keys (`generateblocks/loopItem`, `loopIndex`, `queryType`, `queryId`, `queryData`, `postId`, `postType`) added to its `usesContext` through `register_block_type_args`, so our loop logic and `bws_base_read_refused()` work as in GB blocks. GB's and other plugins' tags are not ours to police.
7. **Survey ships in v1** (§4).
8. **One walk.** The survey's parsed-block walk is also the walk FW-155's converter uses to learn a tag's block type; FW-128 is its home.
9. **No release-ordering lock with FW-155:** the converter declines a tag in a block type not enabled and reports it with an "enable this block type first" action.

## 3. Candidate set (OPEN)

Starting point: `core/paragraph`, `heading`, `list-item`, `table`, `button`, `quote`. `core/image` is out of FW-156 and goes to §7. Since the 2026-10-26 grill (§3a) this is the CLASSIFIED set, not the selectable one: v1 classifies paragraph, heading, list-item, table and button (the guard's map and the browser checks cover these); quote stays selectable and unclassified.

Known cost for an attribute candidate (a button's `href`): kses mangles any tag with options for restricted authors (§1).

## 3a. Block-selection UI (REOPENED 2026-10-26)

**Presentation superseded 2026-10-27:** the list's presentation (one scrolling list, the "N selected" counter, per-row note paragraphs) is replaced by a filtered, sortable table; see `core-block-tags/spec.md` §Interfaces → Picker presentation and tickets 09 to 13. The behavior decisions below (classification, guards, survey scope and the one Save confirmation, flat option, loop keys) stand.

Decisions 3 and 4 settled a fixed checkbox list. The user has asked that the setting instead let authors SELECT blocks, and pointed at GB's own block-selection screen as the model: the Editor Access profile editor (`wp-admin/admin.php?page=generateblocks-editor-access&edit=new`, "Control which block inserter entries this profile can add"). Read from a screenshot on 2026-10-26, not from source and not measured; which GB edition ships it is not yet checked.

What the screen does:

- **Three radio cards** choose the mode: All blocks, Selected blocks only, No new blocks. Only the middle one reveals the list.
- **The list:** a "Search insertable blocks" field with an "N selected" counter, an advisory line, then a scrollable two-column checkbox grid grouped under a plugin heading (GENERATEBLOCKS). Each row carries the block title and either its name (`generateblocks/text`) or "Variation of `generateblocks/text`" (Headline, Button, Container, Grid, Image, Shape, Query).
- **Advisory, not blocking:** a note that inner blocks of an allowed block are allowed automatically, and an amber warning when a block that matters is unselected ("Paragraph isn't selected, pressing Enter won't create new text blocks for these users").
- **Dirty-state bar:** "Unsaved changes", Discard, Save.

Grill answers so far (user, 2026-10-26):

- **Settled: no mode cards.** The list is always shown; an empty selection means off. The cards were only in the screenshot for context.
- **Settled: one row per real block name, no variation rows.** A row for a block with variations says so in a short line. GB's own server-side enforcement does the same (a variation id allows its parent block), see the Editor Access section of the shared GenerateBlocks Pro reference, 2026-10-26.
- **Settled: every registered block is selectable; the guard is at render.** Keep the testing burden small without letting authors shoot themselves in the foot. A block-level tag in a core block we have classified as inline-only is guarded by block name in its callback shell (the `bws_tag_blocked_on_media_block()` precedent, below): `{{table}}` returns empty, and `{{content}}` strips its block-level markup instead (the "revises" bullet, below). A block we have not classified is unconstrained and carries a "not tested" marker on its row. The classified set is the small one we browser-test, so the testing burden stays there.
- **Settled: the saved option is one flat array of block names** (`core/paragraph`), not validated against what is registered today, so a deactivated plugin's selection survives. Our PHP filter runs over it afterwards (decision 3).
- **Settled: the guard's rule has one owner and `{{call}}` is unconstrained.** One new function beside `bws_tag_blocked_on_media_block()` owns the block-name-to-content-kind map, called only from the `{{content}}` and `{{table}}` shells; every other site states the consequence. `{{call}}` is unconstrained in every core block (its shape cannot be classified).
- **Correction (user, 2026-10-26): a button has both visible text and a URL.** The v1 classified set is paragraph, heading, list-item, table and button; quote stays unclassified. A button is two slots in one block, text and a `href` attribute, and a callback cannot tell which slot it is rendering in (it gets options and the parsed block, no position), so the rule for a button has to hold for both. Its text sits inside the button's own `<a>`, so an `<a>` from a link wrap is invalid in the text and broken in the `href`.
- **Settled: the button rule is to drop the `<a>` wrap and keep the value.** In `core/button`, a tag that wraps its output in `<a>` (link wrap via `linkTo`, the default-on `mailto:`/`tel:` wrap on `{{email}}`/`{{phone}}`) emits the bare value instead, in both the text and the `href`; it does not return empty, because GB blanks the whole block when a required tag resolves empty. Block-level tags in a button are refused like any inline-only block. The same blanking applies to the inline-only refusal (a `{{content}}` in a paragraph blanks the paragraph), which is GB's behavior per decision 5 and is documented beside the setting.
- **Settled (revises the Q4 refusal for `{{content}}`): `{{content}}` strips block-level markup in a classified inline-only block; `{{table}}` is still refused.** The strip runs on the final value, so no source-kind knowledge is needed. `{{content}}` always prints block-level markup (FW-144 wraps a bare value at the seam exit), so the strip sees wrapped output: a bare run of text comes out unchanged, and already-inline output passes unchanged. It runs in the two callback shells that have `$block`, the base `{{content}}` callback and the `try_` shell for content slots; never in `bws_base_content_resolve_value()` or the cores, because the seam never sees `$block` (the reason the email and phone media guards sit in their shells). One function owns it, reading the same block-name map as the `{{table}}` refusal (Q6). The inline-tag keep-list is not owned here: FW-144 adds the one function that owns the inline/block vocabulary for its wrap check, and the strip reuses it, adding only its own `a`-in-`core/button` exception. Rules: keep inline tags only (`strong`, `em`, `b`, `i`, `span`, `code`, `sub`, `sup`, `mark`, `small`, `del`, `ins`, `cite`, `q`, `abbr`, `br`, and `a` except in `core/button`); drop every other tag and keep its text; drop images and figures whole; turn a block boundary (`</p>`, `</div>`, `</li>`, headings, blockquotes) into one space, trimmed at both ends, with no `<br>`. It is lossy by design and the row's note says so. To confirm in a browser: that each kept tag survives in `core/button` text.
- **Settled: `{{table}}` stays a refusal** in an inline-only block (it blanks the block, decision 5); returning just its caption was considered and left out, cheap to add later. A caption-less refusal and a captioned one behave the same.
- **Settled: a classified container carries a row hint**, `core/list` only in v1 ("put tags in List Item"); unclassified blocks show only "not tested".
- **Settled, handed to FW-155: the converter declines a block-level tag whose target block is classified inline-only**, with its own report line, rather than converting it into something that renders empty. FW-155 owns the wording and the reason enum.
- **Settled: one survey confirmation at Save.** It lists only newly enabled blocks that have registered-tag counts from the last scan; per-row counts show after a scan, with a quiet "no scan run yet" hint above the list. Still advisory, never a gate. This replaces §4's per-type confirmation on enabling.
- **Settled, to be measured: loop keys by one late pass.** One pass on `init` after all blocks register merges GB's loop keys (decision 6) into every selected block type's registered `uses_context`, over the saved-and-filtered set; no `register_block_type_args` hook. Not yet verified that a late write reaches the block at render; the first testbed check (§5, a core block inside a Pro repeater) proves it.
- **Moved out of this plan (user, 2026-10-26): the `{{content}}` tag description is handled separately.** It is not an FW-156 item.

Facts read 2026-10-26 for the content-model axis (a census agent, code read, not measured; the `tag-reference.md` catalog was not consulted):

- **Block-level output:** `{{content}}` (post body through `do_blocks`, `wpautop`, kses) and `{{table}}` (a `<table>`, region `<div>` when captioned; its own description already says not to put it in a `<p>` or `<span>`). `{{call}}` returns the allowlisted function's string verbatim and unescaped, so its shape is whatever that function emits (unchecked per function).
- **Inline markup:** a link wrap (`<a>`) on text, title, datetime and `try_*` when `linkTo` is set; `{{email}}` and `{{phone}}` wrap in `<a>` by default.
- **Plain text:** permalink, datetime, join. `{{image}}` is a URL or id string for an attribute slot (its title, alt and caption modes are text).
- **Callbacks see the block.** GB calls `( $options, $block, $instance )`; `$block['blockName']` is the only block-name source we use. The one existing block-name refusal is `bws_tag_blocked_on_media_block()` (`link-helpers.php`), called from the top of the email, phone and `call` callback shells and from the `try_*` media guard. `bws_base_read_refused()` is not the home: it has no `$block` and is about source kind, not block name. `{{content}}` and `{{table}}` have no block-name guard today.

Where it fits our setting, and where it does not:

- **Modes.** Settled above.
- **The advisory line inverts.** GB allows inner blocks automatically; for us each block type must be on the list itself (§1, "Own markup only"), so our note says the opposite. The survey's confirmation (§4) is the natural home for the "this type has N registered tags in your content" advisory.
- **Variations.** GB's gate is on block NAME (`docs/gb-constraints.md`), so a variation row (Headline) would have to resolve to its base block name (`generateblocks/text`, already allowed). Open whether to list variations at all, list only real block names, or dedupe by name.
- **Which blocks the list shows.** A selectable list reopens decision 4: either it shows only the vouched set, or it shows every registered block with the unvouched ones marked ("no promise from us"). The browser-check gate and the restricted-user save refusal (decision 5) have to stay visible next to whatever is selectable.
- **Our PHP filter** still applies after the saved selection (decision 3's second half is untouched).
- **Settings-page prose** for the selection UI goes to the user for review (§6).

## 4. Survey

- **Run:** on demand. Walks parsed blocks and reports each tag-like `{{…}}` string by block type, split into registered and unregistered (only registered ones change when a type is enabled).
- **Covers:** `post_content` of every post type, drafts included, revisions excluded: reusable blocks (`wp_block`), `wp_template`/`wp_template_part`, GB Pro patterns. Plus block widgets (`widget_block`). Classic Text/HTML widgets never pass through `render_block`, so they are out. Reaching block widgets also answers FW-73's "does a site have tags there".
- **Advisory, never a gate.** Enabling a type asks for confirmation when the last scan found registered-tag matches in it (with the count) or when no scan has run.

## 5. Tests to leave behind

- Browser check per candidate block (decision 4); FW-140 asks the same for `core/table`.
- Testbed: a core block inside a Pro repeater renders the row, not the page (decision 6; the repeater's loop-item context is inferred, not measured).
- Visible matrix rows per enabled block type, per `docs/testbed.md`.
- Save-gate scope for an enabled core block: ticket `core-block-tags/12` (a `verify.php` pin; the scope is post-wide, read from GB source, not yet measured).

## 6. Not decided here

- What the editor shows: no modal, no editor tag configuration preview, no mount migration (FW-154) for a tag typed into a core block.
- Settings-page wording; user-facing prose goes for review.

## Measured 2026-10-26: late uses_context write

A late write (init, priority 99) to a registered block type's `uses_context` reaches the block instance at render. GB Pro post_meta repeater: core/paragraph read the page value before the write, the row values after it (matching a generateblocks/text control). WP_Query loop: row values render even without the write. Decision: the single init pass ships; the register_block_type_args fallback is not needed. Script: .scratch/core-block-tags/measure/02-late-write.php.

## Measured 2026-10-26: core/button text round trip (browser, testbed editor, WP 7.1.3)

wp.blocks createBlock -> serialize -> parse on core/button: every inline tag on the kept list (strong, em, b, i, span, code, sub, sup, mark, small, del, ins, cite, q, abbr, br, plus s and kbd) survives in the text with the block valid; a tag string in `url` (`mailto:{{email key:contact_email}}`) survives. A nested `<a>` in button text makes the block INVALID, which confirms dropping `a` from {{content}} in a button.

## Measured 2026-10-26: settings block list (browser)

Search filters rows; counter tracks checks; "Unsaved changes" appears and clears when the selection returns to the saved one; scan runs, refreshes row counts in place, replaces the no-scan hint with "Last scan"; Save with a newly selected counted block shows the in-page notice (focus moves to "Save anyway"), "Go back" hides it; Save with no change submits and the selection survives reload. Aside, not ours: at a 439px-wide window the WP admin bar overlaps the page-header Save button, so a click there can miss.
