# Tags in core blocks test matrix (CB)

**Standing manual regression suite** for FW-156: a dynamic tag typed into a core block (paragraph, heading, list item, table, button). What the pure harnesses cannot reach is the half this matrix owns: a real front-end render of a core block through `the_content`, GenerateBlocks' own allowlist gate, a loop item reaching a block GenerateBlocks did not author, and the survey over a real database.

> **Re-run trigger:** the three core-block rows in [`CLAUDE.md`](../../CLAUDE.md#update-triggers) (classification, enabled set / allowlist / loop keys, survey). Run the pure harnesses they name FIRST; they own the algorithms. Then this matrix, then `php tools/test/page-snapshots.php`.

**How to run:** every CB1 to CB5 row, and CB6.6 and CB6.7, is a visible block on `/matrix-core-blocks/` (blueprint **v36**, `bin/seed.sh testbed core-structures`). Open the page and compare each label's `->` text with what follows it; the page is the instrument, and `page-snapshots.php` pins the rendered output. Recycle the lsphp workers after a source edit and cache-bust the fetch ([`docs/testbed.md`](../../docs/testbed.md) §Two layers of staleness). `render-tag` cannot reach any of these rows: `$block` is empty there, which is what every guard in this matrix keys on.

Operating the testbed, and why every row must also be a visible block: [`docs/testbed.md`](../../docs/testbed.md).

## Fixture state these rows assume

| Entity | Carries |
|---|---|
| settings `core_blocks` | `core/paragraph`, `core/heading`, `core/list-item`, `core/table`, `core/button` ENABLED (the testbed's baseline; the product ships it empty). `core/verse` and `core/preformatted` are deliberately not |
| page `matrix-core-blocks` | `role` **Director**, `main_line` **(919) 555-0134**, `sms_number` **+19195550134**, `contact_email` **blocks@example.test**, and its own two-row `team_members`: **Carla Diaz** / `Design`, **Dev Patel** / `Platform`. The page's values differ from every row's, so a block that read the page prints the wrong word |
| plain meta on that page | `bws_block_plain` (two paragraphs of bare text), `bws_block_formatted` (already `<p>`-wrapped, with a soft break), `bws_block_inline` (bold, italic and a link, no block tag) |
| staff `jane-partner`, `tom-associate` | `main_line` `(555) 200-3000` and `(555) 200-4000`, the WP_Query loop's items |
| page `matrix-post-meta` | the `team_members` repeater (**Alice Adams** / `Engineering`, **Bob Brown** / `Operations`) CB5.5 reads |
| option `widget_block` | one block widget (key 9001) holding a phone and an email tag, then (blueprint v36) a deprecated tag `post_custom_datetime_single` and an unregistered one `bws_fixture_unregistered`, then (v37) a registered tag with a legacy option, `{{text src:site|fixed:Varsity|fallback_text:none}}`; in no sidebar, so nothing renders it |

Every row's label block is GenerateBlocks text and never carries a literal tag. A row whose tag blanks its block (CB5) leaves the label standing alone, which is the expectation and not a missing fixture.

## CB1: text in each block

An enabled core block fills in a tag; one that is not enabled prints it as typed. Tag: `{{text key:role}}` unless noted.

| # | Host block | Expected | Status |
|---|---|---|---|
| CB1.1 | `core/paragraph`, enabled | `Director` | PASS 2026-10-26 |
| CB1.2 | `core/heading`, enabled | `Director` as a level 3 heading | PASS 2026-10-26 |
| CB1.3 | `core/list-item`, enabled | `Director` as the one bullet | PASS 2026-10-26 |
| CB1.4 | `core/table` cell, enabled | `Director` in the one cell | PASS 2026-10-26 |
| CB1.5 | GB text block (control) | `Director` | PASS 2026-10-26 |
| CB1.6 | `core/verse`, NOT enabled | the tag printed literally, `{{text key:role}}` | PASS 2026-10-26 |
| CB1.7 | `core/preformatted`, NOT enabled | the tag printed literally | PASS 2026-10-26 |

## CB2: loop context

Text tag in a GB text block and in a `core/paragraph` side by side, inside a query loop. A core block that read the page prints `Director` or `(919) 555-0134` instead of the item's value (Invariant 2).

| # | Loop | Tag | Expected (GB text, then core/paragraph, per item) | Status |
|---|---|---|---|---|
| CB2.1 | GB Pro `post_meta` loop over this page's `team_members` | `{{text key:role}}` | `Design` / `Design`, then `Platform` / `Platform` | PASS 2026-10-26 |
| CB2.2 | `WP_Query` loop over Jane Partner then Tom Associate | `{{text use:key\|key:main_line}}` | `(555) 200-3000` / `(555) 200-3000`, then `(555) 200-4000` / `(555) 200-4000` | PASS 2026-10-26 |

CB2.1 is the row the late `uses_context` write exists for (measured before it shipped; the measurement and the GB/WP fact it rests on are in [`gb-constraints.md`](../../docs/gb-constraints.md#a-late-write-to-a-block-types-uses_context-reaches-the-block-at-render-gb-250-wp-713)).

## CB3: `{{content}}` in an inline-only block

Block-level markup is reduced to inline text; a GB container is untouched. Lossy by design ([`tag-reference.md` §Guards by host block](../../docs/tag-reference.md#guards-by-host-block)).

| # | Host block | Tag | Expected | Status |
|---|---|---|---|---|
| CB3.1 | `core/paragraph` | `{{content use:key\|key:bws_block_plain}}` | ONE paragraph: `Plain line one. Plain line two.` (one space between, no nested paragraph) | PASS 2026-10-26 |
| CB3.2 | `core/heading` | same | `Plain line one. Plain line two.` inside the one heading | PASS 2026-10-26 |
| CB3.3 | `core/paragraph` | `{{content use:key\|key:bws_block_formatted}}` | `Formatted one,` newline `with a soft line break. Formatted two.` in one paragraph | PASS 2026-10-26 |
| CB3.4 | `core/paragraph` | `{{content use:key\|key:bws_missing_key\|fallback:Nothing stored.}}` | `Nothing stored.`, a bare run unchanged | PASS 2026-10-26 |
| CB3.5 | `core/paragraph` | `{{content use:key\|key:bws_block_inline}}` | bold, italic and the link all kept | PASS 2026-10-26 |
| CB3.6 | `core/button` | same | the button is hidden: `{{content}}` prints nothing in a button (note N1: its empty wrapper remains) | PASS 2026-10-28 |
| CB3.7 | GB text block (control) | `bws_block_plain` | TWO separate paragraphs, untouched | PASS 2026-10-26 |

## CB4: link-wrapping tags in `core/button`

The same tag sits in the button's text and, in CB4.1 to 4.3, in its `href`. In a GB block each still wraps. The button's own link is not wrapped, and GenerateBlocks then runs `esc_url()` over it ([`gb-constraints.md`](../../docs/gb-constraints.md#generateblocks-runs-esc_url-over-a-url-attribute-after-replacement-gb-250-wp-713)), so a link with no scheme gains `http://`. An author who wants a mail or phone link types the scheme before the tag (CB4.7 to 4.9); the button note on the settings page says so.

| # | Host | Tag (text, and link where noted) | Expected text | Expected rendered `href` | Status |
|---|---|---|---|---|---|
| CB4.1 | `core/button` | `{{text key:role\|linkTo:permalink}}` in both | `Director`, no anchor inside the button | `http://Director` | PASS 2026-10-26 |
| CB4.2 | `core/button` | `{{email key:contact_email}}` in both | `blocks@example.test`, no `mailto:` | `http://blocks@example.test` | PASS 2026-10-26 |
| CB4.3 | `core/button` | `{{phone key:main_line}}` in both | `(919) 555-0134`, no `tel:` | `http://(919)%20555-0134` | PASS 2026-10-26 |
| CB4.4 | GB text (control) | same Link To | `Director` linked to `/matrix-core-blocks/` | | PASS 2026-10-26 |
| CB4.5 | GB text (control) | email | `blocks@example.test` as a `mailto:` link | | PASS 2026-10-26 |
| CB4.6 | GB text (control) | phone | `(919) 555-0134` as a `tel:` link | | PASS 2026-10-26 |
| CB4.7 | `core/button` | email in the text, `mailto:` + email in the link | `blocks@example.test` | `mailto:blocks@example.test` | PASS 2026-10-26 |
| CB4.8 | `core/button` | phone in the text, `tel:` + phone in the link, stored `(919) 555-0134` | `(919) 555-0134` | `tel:(919)%20555-0134` | PASS 2026-10-26 (href measured, not tried on a device) |
| CB4.9 | `core/button` | `{{phone key:sms_number}}` in the text, `tel:` + the same in the link, stored `+19195550134` | `+19195550134` | `tel:+19195550134` | PASS 2026-10-26 |

**Which phone form works.** The phone tag has no option that prints digits or E.164: its display is the stored value verbatim (`bws_phone_render_one()`), so the form a button link gets is whatever the field stores. A stored `+19195550134` gives a clean `tel:+19195550134` (CB4.9). The default formatted value gives `tel:(919)%20555-0134` (CB4.8), valid to GenerateBlocks and WordPress but dependent on the phone app decoding the `%20`. A site that needs the clean form stores the digits.

**N1: a hidden button leaves its wrapper.** CB5.4 hides the `core/button`; the `core/buttons` block around it still prints its empty `<div class="wp-block-buttons ...">`. GenerateBlocks blanks the block whose tag resolved empty and nothing else.

## CB5: `{{table}}` in an inline-only block

Tag: `{{table src:post,<MID>;rows,team_members|A:header(Name);key(name)|B:header(Role);key(role)}}`, `<MID>` the `matrix-post-meta` page id. The tag prints nothing, so GenerateBlocks hides the block.

| # | Host block | Expected | Status |
|---|---|---|---|
| CB5.1 | `core/paragraph` | EMPTY: nothing follows the label | PASS 2026-10-26 |
| CB5.2 | `core/heading` | EMPTY | PASS 2026-10-26 |
| CB5.3 | `core/table` cell | EMPTY: the whole table block is hidden | PASS 2026-10-26 |
| CB5.4 | `core/button` | the button is hidden (note N1: its empty wrapper remains) | PASS 2026-10-26 |
| CB5.5 | GB text block (control) | a 2-row table: Alice Adams / Engineering, Bob Brown / Operations | PASS 2026-10-26 |

The same tag with GenerateBlocks' **Required to render** turned off (`{{table required:false|src:post,<MID>;rows,team_members|…}}`, `required:false` first as the editor writes it). With the removal switched off the block stays, and the refused tag leaves it empty:

| # | Host block | Expected | Status |
|---|---|---|---|
| CB5.6 | `core/paragraph` | the block stays, as an empty paragraph element (`<p class="wp-block-paragraph"></p>`) | PASS 2026-10-27 |
| CB5.7 | `core/table` cell | the table block stays, its one cell empty | PASS 2026-10-27 |
| CB5.8 | `core/button` | the button stays with no text: an empty link inside the buttons wrapper | PASS 2026-10-27 |

## CB6: survey

`bws_core_blocks_survey_run()` counts tag-like strings per block type and reaches `widget_block`, through `bws_block_widget_rows()`, the enumeration the Migration Tool's scan shares. **No page row for the widget itself, by construction**: the seeded block widget is in no sidebar, and the survey and the scan are read off the database rather than a render. CB6.6, CB6.7 and CB6.16 are the visible twins of the three strings it holds beside its registered pair (blueprint v36 and v37), on `/matrix-core-blocks/`. Rows CB6.8 and CB6.10 to CB6.15 exercise admin screens only and say so ("admin-only, no visible block"). Run the survey through the testbed:

```bash
wsl.exe -d Ubuntu -- bash -lc 'cd ~/wp-litespeed && bin/wp.sh testbed eval "echo json_encode( bws_core_blocks_survey_run() );"'
```

| # | Expectation | Status |
|---|---|---|
| CB6.1 | `widgets` reports `registered: 3, unregistered: 2` (the phone and the email tag, then the deprecated and the unregistered tag, then at v37 the `text` tag with a legacy option, in the seeded widget) | PASS 2026-10-29 at v37: `bws_core_blocks_survey_run()` reports `widgets` as `registered: 3, unregistered: 2` (it read `registered: 2, unregistered: 2` at v36, before the v37 line was added); the settings page survey button (browser, testbed) shows the same per-block counts as the command |
| CB6.2 | `core/table` is counted: `registered: 3` (the cells of CB1.4 and CB5.3, and the `table` tag in the CB5 row that holds it: the cell tags are `{"text":1,"table":2}`, see CB6.5) | CORRECTED 2026-10-29: the row said `registered: 2`, which does not match the survey's own tag list for `core/table`, `text` 1 plus `table` 2 (measured by `bws_core_blocks_survey_run()` on the testbed at v36, CB6.5); the wording above is the measured value, and the earlier PASS of 2026-10-26 was against the wrong number. Re-measured 2026-10-29 at v37: `core/table` is `registered: 3, unregistered: 0`, tags `{"text":1,"table":2}`: PASS |
| CB6.3 | The widget's tags are also counted under `core/paragraph`, merged with the page and pattern rows | PASS 2026-10-26 |
| CB6.4 | Blocks that are not enabled are counted too: `core/verse` 1 and `core/preformatted` 1 (CB1.6, CB1.7), which is what the Save prompt needs to warn about | PASS 2026-10-26 |
| CB6.5 | Each cell names its tags with how many times each was found, split into `registered_tags` and `unregistered_tags`: `core/table` is `{"text":1,"table":2}` and none unregistered; `core/paragraph` lists `post_custom_datetime_single` (the pattern's legacy tag) under `unregistered_tags`; the two counts equal the sums of their tag lists | PASS 2026-10-27 (JSON from the command above; re-measured 2026-10-29 at v36: `core/table` is `{"text":1,"table":2}`, `core/paragraph` unregistered_tags is `{"post_custom_datetime_single":3,"bws_fixture_unregistered":2}` (the pattern, the widget and the CB6.6 and CB6.7 page rows), and every count equals its tag sum; the settings-page rendering, wrapping and chevron were looked at in a browser on hargrave.test 2026-10-27, and `core-block-picker-test.php` pins the markup) |
| CB6.6 | Visible on `/matrix-core-blocks/`: a selected `core/paragraph` holding `{{post_custom_datetime_single date_time_field:event_midnight}}` (the widget's deprecated tag) prints the tag as typed | PASS 2026-10-29 (page snapshot re-captured; the page prints `{{post_custom_datetime_single date_time_field:event_midnight}}` literally) |
| CB6.7 | Visible on the same page: a selected `core/paragraph` holding `{{bws_fixture_unregistered key:role}}` (the widget's unregistered tag) prints the tag as typed | PASS 2026-10-29 (page snapshot re-captured; the page prints `{{bws_fixture_unregistered key:role}}` literally) |
| CB6.8 | Scan Content in the Migration Tool lists the seeded widget as "Block widget #9001", placement "Inactive", an edit link to `widgets.php`, the deprecated tag `post_custom_datetime_single` in the row, and no revision note; it has a checkbox and a Migrate button. Through `wp eval`, `TagConverter::scan()` returns a row with `kind: "widget"`, `widget_number: 9001`, `row_id: "widget:9001"` (admin-only, no visible block) | PASS 2026-10-29 (browser, testbed: the row reads "Block widget #9001", "Inactive", links to `widgets.php`, lists `post_custom_datetime_single`, has a checkbox and a Migrate button and no revision note; with the widget assigned to the right sidebar the placement reads "Right Sidebar"; `scan()` through `wp eval` returned `kind: "widget"`, `widget_number: 9001`, `row_id: "widget:9001"`) |
| CB6.9 | After that scan the scan allowlist (`bws_dynamic_tags_scan_allowlist`) lists `post_custom_datetime_single`, and the survey output (CB6.1 to CB6.5) is unchanged apart from the two added strings: the widget rows are the same strings through `bws_block_widget_rows()` (admin-only, no visible block) | PASS 2026-10-29 for the allowlist (`post_custom_datetime_single` is listed after the scan) and for the survey (CB6.1 to CB6.5 above, measured at v36). The allowlist half is not isolated by this fixture, because the pattern and the CB6.6 page row hold that tag too; the widget-only case is pinned by `widget-scan-test.php` WS4 |
| CB6.10 | Migrate on the "Block widget #9001" row (or tick it and Migrate Selected) rewrites the widget: `post_custom_datetime_single` is gone from its `content`, the phone, email and `bws_fixture_unregistered` tags beside it are byte-identical, the v37 `{{text src:site|fixed:Varsity|fallback_text:none}}` has `fallback_text` renamed to `fallback` and its value tag kept, and `_multiwidget` is still in the `widget_block` option. The row reports tags migrated and no "no revision" note (admin-only, no visible block) | PASS 2026-10-29 (browser, Migrate on the row: "1 tags migrated", no revision note; `wp eval` read of `widget_block` shows `{{datetime_single showCurrentYear|showMidnight|key:event_midnight}}` in place of the deprecated tag, the phone, email and `bws_fixture_unregistered` tags unchanged, keys `2,3,4,5,6,_multiwidget,9001`; the backup option is stored with autoload off). Re-measured at v37, 2026-10-29: Migrate on the row in the browser reported "1 tags migrated, 1 option fixes applied" with no revision note, and the stored widget read `{{datetime_single showCurrentYear|showMidnight|key:event_midnight}}` and `{{text src:site|fixed:Varsity|fallback:none}}` with the phone, email and `bws_fixture_unregistered` tags unchanged and `_multiwidget` kept (keys `2,3,4,5,6,_multiwidget,9001`); `verify-migration.php` asserts the same rewrite and passes |
| CB6.11 | After CB6.10 the "Restore block widgets" button shows beside the scan controls with the run's date and "1 widget backed up"; clicking it reverts widget 9001 to exactly the seeded content, reports "1 block widgets restored", and the button disappears. A second Migrate with nothing left to change leaves the previous backup in place (admin-only, no visible block) | PASS 2026-10-29 (browser: the line read "Block widgets were migrated on 2026-10-29 09:49 (1 widget backed up)", the Restore click reported "1 block widgets restored." and the button hid; the stored content then equalled the seed byte for byte and the backup option was gone. The second Migrate was run through `TagConverter::migrate_widgets()` in `wp eval`: `changed: false` and the backup (run id and contents) was unchanged) |
| CB6.12 | Migrate widget 9001, edit its content by hand in Appearance, Widgets, then Restore: the widget keeps the hand edit and the result names "Block widget #9001 (edited since the migration)". With the widget assigned to a sidebar for this check (it is in none by default), its v37 line `Team: Varsity.` renders the same before and after a migrate. The line holding the deprecated and the unregistered name is NOT part of that comparison: it prints its tags as typed before, and after the migrate its datetime tag reads empty in a widget (no post), which drops that paragraph. That change is the known cost of migrating a tag whose source needs a post, not a defect of the migrate (admin-only, no visible block) | Restore half PASS 2026-10-29 (browser Restore click: "0 block widgets restored. Left as they are: Block widget #9001 (edited since the migration).", and the button hid afterward; the migrate and the hand edit were made through `wp eval`, so the edit in the Widgets screen itself is not measured). Render half PASS 2026-10-29 at v37: with the widget moved from the inactive list into the right sidebar (`sidebar-1`, restored afterward), the front end of `/matrix-core-blocks/` printed `Team: Varsity.` before the migrate and `Team: Varsity.` after it (byte-identical paragraph); the migrate was Migrate on the row in the browser ("1 tags migrated, 1 option fixes applied"), and the second paragraph dropped from the page as described. An earlier whole-widget comparison, 2026-10-29, which the wording no longer makes: with the widget in the right sidebar, the seeded widget prints its second paragraph literally (`Next event: {{post_custom_datetime_single date_time_field:event_midnight}}. Ask {{bws_fixture_unregistered key:role}}.`, the deprecated name being unregistered), while the migrated widget drops that whole paragraph from the front end: the migrated `{{datetime_single ... key:event_midnight}}` reads empty on `/matrix-core-blocks/` (`render-tag`), and the paragraph is gone. The first paragraph (phone and email) renders identically both ways. The wording was changed to compare the v37 line only |
| CB6.13 | After the block list's Scan content for tags, its status line ends "; K need updating with the Migration Tool." ("1 needs updating" at one), the clause a link, K being the occurrences, in the blocks the list shows, that the Migration Tool would update (posts, patterns, templates and the widget; retired tag names and option migrations alike; a tag the ownership guard declines is not one), so K is never more than the two counts together; an occurrence in a block the list does not show (a GenerateBlocks block, a block type no longer registered) is in the Migration Tool's list and not in K. Clicking the link scrolls to the Migration Tool's Scan All Content button when no Migration Tool scan has run since the page loaded, and to its results once one has. The picker's Handled by GB and Not handled by GB cells and the counts in the sentence are unchanged (admin-only, no visible block) | PASS 2026-10-29 at b369269 (browser, testbed, admin scripts force-reloaded because the `?ver=` string does not change on an edit): the scan read "39 tags handled by GenerateBlocks and 5 tags it does not handle; 5 need updating with the Migration Tool.", the clause a link to `#bws-migration-tool`; K 5 is no more than the 44 together. Per block, `bws_core_blocks_survey_run()` has `core/paragraph` 5, `generateblocks/media` 1 and `generateblocks/text` 267 (273 site-wide), so the GenerateBlocks blocks, which the list does not show, are in the Migration Tool and not in K. Clicking the link before any Migration Tool scan scrolled to Scan All Content and focused it (button top 540 of a 1044 px viewport); after a Migration Tool scan it scrolled to the results (top 140) |
| CB6.14 | On a site with no migratable tag (after Migrate has updated every row, then a fresh block scan) the status line has no "need updating" clause at all and ends with a full stop after the second count, not "0 need updating"; a page load from the stored scan shows the same, and a page load from a stored scan with K above 0 keeps the clause (admin-only, no visible block) | PASS 2026-10-29 at b369269 (browser, testbed). Reload with K 5 stored: the clause and its link are still there. Then, with no content blanked, `migrate_post()` over every post reported and `migrate_widgets()` on 9001 (through `wp eval`; 3 occurrences declined by the ownership guard, `join`, `text`, `phone`) left `scan()` with 0 `locations` and K 0; a fresh block scan read "42 tags handled by GenerateBlocks and 2 tags it does not handle." with no clause and no link, and so did a reload |
| CB6.15 | Every Migration Tool row that has a migratable tag inside a block, post and the "Block widget #9001" row alike, lists under "Found in:" each occurrence as its block type and position ("core/paragraph #2") and the stored tag. Through `wp eval`, `TagConverter::scan()` rows carry `locations` as `{label, stored}`. One classifier decides both, so no `locations` entry is a tag the ownership guard declines (after Migrate runs, none of the declined `{{join}}`, `{{text}}` or `{{phone}}` strings are listed). K in CB6.13 is the number of `locations` entries whose block type the list shows: equal to the number across the rows when every one is a listed block, otherwise less. A tag outside any block has neither (admin-only, no visible block) | PASS 2026-10-29 at b369269 (browser, testbed): the Migration Tool listed 17 rows, 15 of them with "Found in:" (the others held no migratable occurrence), every entry in the `block/name #N` form with its stored tag, the "Block widget #9001" row showing `core/paragraph #2` and `core/paragraph #3`. `scan()` gave 273 `locations` before Migrate (the per-block `migratable` counts sum to 273) and 0 after, with the 3 declined strings gone from the list, and K as above. Not measured: a row holding a tag outside any block, since no scanned row on this fixture does |
| CB6.16 | Visible on `/matrix-core-blocks/`: a selected `core/paragraph` holding `Team: {{text src:site|fixed:Varsity|fallback_text:none}}` (the widget's v37 line) prints `Team: Varsity` (the full stop belongs to the widget paragraph only); the legacy `fallback_text` option changes nothing while the value is non-empty, and the Migration Tool lists the string (it is migratable) | PASS 2026-10-29 (page snapshot re-captured at v37; the page prints `Team: Varsity` with no full stop; the Migration Tool lists the string under "Matrix: Core Blocks", `core/paragraph #12`) |

Measured 2026-10-26 on the seeded testbed: `core/paragraph` 12 registered and 1 unregistered (the `wp_block` pattern's legacy tag), `core/heading` 3, `core/button` 8 (a tag in the text and in the `href` count separately), `core/list-item` 1, `core/table` 2, `generateblocks/text` 847 and 13, `generateblocks/media` 5. The counts move whenever any fixture page does; the rows above assert reach, not the totals.

The block-widget answer for FW-73 ("does the survey reach `widget_block` at all") is recorded on its [`future-work.md`](../../docs/future-work.md) row. It is a statement about the mechanism on a seeded widget, not about how common tags in block widgets are on real sites.
