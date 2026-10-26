# Tags in core blocks test matrix (CB)

**Standing manual regression suite** for FW-156: a dynamic tag typed into a core block (paragraph, heading, list item, table, button). What the pure harnesses cannot reach is the half this matrix owns: a real front-end render of a core block through `the_content`, GenerateBlocks' own allowlist gate, a loop item reaching a block GenerateBlocks did not author, and the survey over a real database.

> **Re-run trigger:** the three core-block rows in [`CLAUDE.md`](../../CLAUDE.md#update-triggers) (classification, enabled set / allowlist / loop keys, survey). Run the pure harnesses they name FIRST; they own the algorithms. Then this matrix, then `php tools/test/page-snapshots.php`.

**How to run:** every CB1 to CB5 row is a visible block on `/matrix-core-blocks/` (blueprint **v34**, `bin/seed.sh testbed core-structures`). Open the page and compare each label's `->` text with what follows it; the page is the instrument, and `page-snapshots.php` pins the rendered output. Recycle the lsphp workers after a source edit and cache-bust the fetch ([`docs/testbed.md`](../../docs/testbed.md) §Two layers of staleness). `render-tag` cannot reach any of these rows: `$block` is empty there, which is what every guard in this matrix keys on.

Operating the testbed, and why every row must also be a visible block: [`docs/testbed.md`](../../docs/testbed.md).

## Fixture state these rows assume

| Entity | Carries |
|---|---|
| settings `core_blocks` | `core/paragraph`, `core/heading`, `core/list-item`, `core/table`, `core/button` SELECTED (the testbed's baseline; the product ships it empty). `core/verse` and `core/preformatted` are deliberately not |
| page `matrix-core-blocks` | `role` **Director**, `main_line` **(919) 555-0134**, `contact_email` **blocks@example.test**, and its own two-row `team_members`: **Carla Diaz** / `Design`, **Dev Patel** / `Platform`. The page's values differ from every row's, so a block that read the page prints the wrong word |
| plain meta on that page | `bws_block_plain` (two paragraphs of bare text), `bws_block_formatted` (already `<p>`-wrapped, with a soft break), `bws_block_inline` (bold, italic and a link, no block tag) |
| staff `jane-partner`, `tom-associate` | `main_line` `(555) 200-3000` and `(555) 200-4000`, the WP_Query loop's items |
| page `matrix-post-meta` | the `team_members` repeater (**Alice Adams** / `Engineering`, **Bob Brown** / `Operations`) CB5.5 reads |
| option `widget_block` | one block widget (key 9001) holding a phone and an email tag; in no sidebar, so nothing renders it |

Every row's label block is GenerateBlocks text and never carries a literal tag. A row whose tag blanks its block (CB5) leaves the label standing alone, which is the expectation and not a missing fixture.

## CB1: text in each block

A selected core block fills in a tag; an unselected one prints it as typed. Tag: `{{text key:role}}` unless noted.

| # | Host block | Expected | Status |
|---|---|---|---|
| CB1.1 | `core/paragraph`, selected | `Director` | PASS 2026-10-26 |
| CB1.2 | `core/heading`, selected | `Director` as a level 3 heading | PASS 2026-10-26 |
| CB1.3 | `core/list-item`, selected | `Director` as the one bullet | PASS 2026-10-26 |
| CB1.4 | `core/table` cell, selected | `Director` in the one cell | PASS 2026-10-26 |
| CB1.5 | GB text block (control) | `Director` | PASS 2026-10-26 |
| CB1.6 | `core/verse`, NOT selected | the tag printed literally, `{{text key:role}}` | PASS 2026-10-26 |
| CB1.7 | `core/preformatted`, NOT selected | the tag printed literally | PASS 2026-10-26 |

## CB2: loop context

Text tag in a GB text block and in a `core/paragraph` side by side, inside a query loop. A core block that read the page prints `Director` or `(919) 555-0134` instead of the item's value (Invariant 2).

| # | Loop | Tag | Expected (GB text, then core/paragraph, per item) | Status |
|---|---|---|---|---|
| CB2.1 | GB Pro `post_meta` loop over this page's `team_members` | `{{text key:role}}` | `Design` / `Design`, then `Platform` / `Platform` | PASS 2026-10-26 |
| CB2.2 | `WP_Query` loop over Jane Partner then Tom Associate | `{{text use:key\|key:main_line}}` | `(555) 200-3000` / `(555) 200-3000`, then `(555) 200-4000` / `(555) 200-4000` | PASS 2026-10-26 |

CB2.1 is the row the late `uses_context` write exists for (measured before it shipped; the measurement and the GB/WP fact it rests on are in [`gb-constraints.md`](../../docs/gb-constraints.md#a-late-write-to-a-block-types-uses_context-reaches-the-block-at-render-gb-250-gb-pro-280-wp-713)).

## CB3: `{{content}}` in an inline-only block

Block-level markup is reduced to inline text; a GB container is untouched. Lossy by design ([`tag-reference.md` §Guards by host block](../../docs/tag-reference.md#guards-by-host-block)).

| # | Host block | Tag | Expected | Status |
|---|---|---|---|---|
| CB3.1 | `core/paragraph` | `{{content use:key\|key:bws_block_plain}}` | ONE paragraph: `Plain line one. Plain line two.` (one space between, no nested paragraph) | PASS 2026-10-26 |
| CB3.2 | `core/heading` | same | `Plain line one. Plain line two.` inside the one heading | PASS 2026-10-26 |
| CB3.3 | `core/paragraph` | `{{content use:key\|key:bws_block_formatted}}` | `Formatted one,` newline `with a soft line break. Formatted two.` in one paragraph | PASS 2026-10-26 |
| CB3.4 | `core/paragraph` | `{{content use:key\|key:bws_missing_key\|fallback:Nothing stored.}}` | `Nothing stored.`, a bare run unchanged | PASS 2026-10-26 |
| CB3.5 | `core/paragraph` | `{{content use:key\|key:bws_block_inline}}` | bold, italic and the link all kept | PASS 2026-10-26 |
| CB3.6 | `core/button` | same | bold and italic kept, the link dropped to plain text (the button is the link) | PASS 2026-10-26 |
| CB3.7 | GB text block (control) | `bws_block_plain` | TWO separate paragraphs, untouched | PASS 2026-10-26 |

## CB4: link-wrapping tags in `core/button`

The same tag sits in the button's text and in its `href`. In a GB block each still wraps.

| # | Host | Tag | Expected text | Expected href | Status |
|---|---|---|---|---|---|
| CB4.1 | `core/button` | `{{text key:role\|linkTo:permalink}}` | `Director`, no anchor inside the button | the bare value | text PASS, href FINDING F1 |
| CB4.2 | `core/button` | `{{email key:contact_email}}` | `blocks@example.test`, no `mailto:` | the bare value | text PASS, href FINDING F1 |
| CB4.3 | `core/button` | `{{phone key:main_line}}` | `(919) 555-0134`, no `tel:` | the bare value | text PASS, href FINDING F1 |
| CB4.4 | GB text (control) | same Link To | `Director` linked to `/matrix-core-blocks/` | | PASS 2026-10-26 |
| CB4.5 | GB text (control) | email | `blocks@example.test` as a `mailto:` link | | PASS 2026-10-26 |
| CB4.6 | GB text (control) | phone | `(919) 555-0134` as a `tel:` link | | PASS 2026-10-26 |

**F1, measured 2026-10-26: the `href` is not the bare value.** The plugin hands back the bare value, and GenerateBlocks then runs `esc_url()` over every URL attribute after replacement (its `class-register-dynamic-tag.php` says so at the attribute-handling comment), and `esc_url()` prefixes `http://` to a value with no scheme. Rendered: `href="http://Director"`, `href="http://blocks@example.test"`, `href="http://(919)%20555-0134"`. WordPress leaves the same literal untouched (measured: a core button with `href="Director"` renders `Director`), so the prefix is GB's, not the block's. The rule's intent (no anchor nested in an anchor, never empty) holds; the exact `href` the ticket text asks for does not. Surfaced for a decision, not worked around: either the spec's "bare value in the href" is restated as "no wrapped anchor in the href", or the rule hands back something a URL attribute survives.

**N1: a hidden button leaves its wrapper.** CB5.4 hides the `core/button`; the unselected `core/buttons` block around it still prints its empty `<div class="wp-block-buttons ...">`. GenerateBlocks blanks the block whose tag resolved empty and nothing else.

## CB5: `{{table}}` in an inline-only block

Tag: `{{table src:post,<MID>;rows,team_members|A:header(Name);key(name)|B:header(Role);key(role)}}`, `<MID>` the `matrix-post-meta` page id. The tag prints nothing, so GenerateBlocks hides the block.

| # | Host block | Expected | Status |
|---|---|---|---|
| CB5.1 | `core/paragraph` | EMPTY: nothing follows the label | PASS 2026-10-26 |
| CB5.2 | `core/heading` | EMPTY | PASS 2026-10-26 |
| CB5.3 | `core/table` cell | EMPTY: the whole table block is hidden | PASS 2026-10-26 |
| CB5.4 | `core/button` | the button is hidden (note N1: its empty wrapper remains) | PASS 2026-10-26 |
| CB5.5 | GB text block (control) | a 2-row table: Alice Adams / Engineering, Bob Brown / Operations | PASS 2026-10-26 |

## CB6: survey

`bws_core_blocks_survey_run()` counts tag-like strings per block type and reaches `widget_block`. **No page row, by construction**: the seeded block widget is in no sidebar, and the survey is read off the database rather than a render. Run it through the testbed:

```bash
wsl.exe -d Ubuntu -- bash -lc 'cd ~/wp-litespeed && bin/wp.sh testbed eval "echo json_encode( bws_core_blocks_survey_run() );"'
```

| # | Expectation | Status |
|---|---|---|
| CB6.1 | `widgets` reports `registered: 2, unregistered: 0` (the phone and the email tag in the seeded widget) | PASS 2026-10-26 |
| CB6.2 | `core/table` is counted: `registered: 2` (the cells of CB1.4 and CB5.3) | PASS 2026-10-26 |
| CB6.3 | The widget's tags are also counted under `core/paragraph`, merged with the page and pattern rows | PASS 2026-10-26 |
| CB6.4 | The unselected blocks are counted too: `core/verse` 1 and `core/preformatted` 1 (CB1.6, CB1.7), which is what the Save prompt needs to warn about | PASS 2026-10-26 |

Measured 2026-10-26 on the seeded testbed: `core/paragraph` 12 registered and 1 unregistered (the `wp_block` pattern's legacy tag), `core/heading` 3, `core/button` 8 (a tag in the text and in the `href` count separately), `core/list-item` 1, `core/table` 2, `generateblocks/text` 847 and 13, `generateblocks/media` 5. The counts move whenever any fixture page does; the rows above assert reach, not the totals.

The block-widget answer for FW-73 ("does the survey reach `widget_block` at all") is recorded on its [`future-work.md`](../../docs/future-work.md) row. It is a statement about the mechanism on a seeded widget, not about how common tags in block widgets are on real sites.
