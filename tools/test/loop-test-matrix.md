# Query-loop test matrix (QL-rows)

Integration rows for tags rendered **inside a query loop whose items are not posts** — a loop over terms, a loop over users and a loop over WooCommerce products, all supplied by the co-resident query extension the blueprint declares required (`tools/fixtures/core-structures/env-versions.php`). The QL rows live on `/matrix-loops/` and the §QLP rows on `/matrix-products/`, both as visible GB blocks; see [`docs/testbed.md`](../../docs/testbed.md) for reseeding and the cache bust.

Named for the PROPERTY under test rather than a tag family (precedent:
[`limit-default-test-matrix.md`](limit-default-test-matrix.md)) — the finding is about the loop,
and the same wrongness reaches `{{title}}` and `{{permalink}}` alike.

**These rows cannot be run through `bws render-tag`.** Its `--loop-item` flag fakes a post query
only, so a term or user loop is not producible at any flag combination; a real block render is
the only instrument that reaches them. Rows are grouped by loop shape. Inside a group the
three-read trio keeps its positions (bare, explicit source, the extension's own tag) — that
adjacency is what makes the failure legible — and any further rows follow in
[`tag-reference.md`](../../docs/tag-reference.md) Catalog order.

**Staging pattern = expected-fail → flip on ship** (precedent:
[`context-test-matrix.md`](context-test-matrix.md) C-rows). The bare-tag rows recorded the WRONG
output as the pinned baseline, and the page snapshot
(`tools/test/snapshots/page-matrix-loops.html`) held those leaked values on purpose, so the fix's
diff was proof rather than assertion. **Flipped 2026-08-26**, measured through a real block
render on `/matrix-loops/`; every Expected column below now states the shipped value.

**Why the bare tag WAS wrong.** The extension supplies the loop item's id through
`generateblocks_dynamic_tag_id`, and GB passes that filter no fallback type — so a term id or a
user id overrode our POST fallback and the tag read whatever post carried that number. Term ids
and post ids collide constantly. Ours was the consuming half; the filter is theirs and it cannot
discriminate.

**What replaced it.** A bare tag inside a query loop now resolves the loop ITEM's own entity,
and an item shape nothing recognizes resolves to NOTHING instead of falling through to the
surrounding page. **What decides which entity an item is, is owned by `bws_classify_loop_item()`'s
PHPDoc** (`includes/helpers/field-helpers.php`) and stated nowhere else, this file included.

**These rows do not measure that rule, and reading them as if they did is the trap.** Every QL
row below runs under a real `queryType` string, so a recognizer keyed on the vendor's string
instead of on the item would pass all of them identically. What the rows measure is that the
LOOP'S OWN ENTITY is what each tag reads, on this site, with these extensions. The rule itself is
pinned WP-free in [`loop-item-classify-test.php`](loop-item-classify-test.php) — §C1–§C5 drive
the shapes directly and never mention a query type — and the mapping from a recognized kind onto
a source kind is pinned in [`traversal-pipeline-test.php`](traversal-pipeline-test.php)'s `#123`
section.

**The refusal has no row here, and cannot have one.** Producing an unrecognized item shape needs
a query type no plugin on the reference site supplies, so there is nothing to seed. Add a row
here the day the fixture site gains a loop type whose items are neither posts, terms, users nor
repeater rows. **WooCommerce product loops are that shape already, and they are the accepted cost of
the refusal rather than a future case** — `docs/future-work.md` FW-100 carries the measured item
shape and what a fixture for it would need.

**One consequence of the recognition is NOT reachable from this page, and that is why it went
unnoticed for a pass.** A term or user item now makes `in_loop` true with no post behind it, and
the render sites that skip their own "no entity" bail on that flag reach a post-meta read which
cannot serve it; that read then falls to its TERM-ARCHIVE fallback and returns the SURROUNDING
archive's meta. Reproducing it needs a non-post loop rendered on a term archive at once, and no
fixture page is an archive. Measured through `do_blocks()`/`replace_tags()` on
`/department/support/` instead (2026-08-26): a USER loop returned `October 5, 2030` — that
department's `event_date` — from `{{datetime_single key:event_date}}`. The predicate those sites
now gate on is `bws_loop_item_is_post_or_row()`, pinned in `loop-item-classify-test.php` §C6.

Baselines captured 2026-08-25, flipped 2026-08-26, against GenerateBlocks 2.4.1 / GB Pro 2.7.0 /
GB Query Enhancements 1.3.0 (the set `env-versions.php` records).

**§QL4 and §QL5 are a second axis, added 2026-09-04: loops NESTED inside loops.** Every group above stands on its own, so what they measure is a loop item; those two measure what a loop item's context does to the loop inside it. They were not staged expected-fail — both directions were already correct when the rows were written, and the rows exist to keep them that way.

## QL1 — a query loop over TERMS

Two loops, because the leak had two appearances. The first is over the default `category` term,
where the collision is guaranteed by WordPress itself (Uncategorized is term 1, "Hello world!" is
post 1 on every install), so the leaked read printed a real title from the wrong entity. The
second is over the blueprint's `department` terms, whose ids no post carries — the same leak
landing on nothing. A reader saw an unrelated value in one and an empty row in the other; they
are one defect and they flipped together.

The three reads in the first group AGREED through 1.20.0-pre, and that agreement was the
assertion: a bare tag, an explicit source and the extension's own tag all named the same term. A
row disagreeing with its neighbours was the leak returning. **QL1.2 stopped agreeing in 1.20.0
(FW-39, v20) — see its own row below**, a measured and deliberate consequence of `term` becoming a
PINNING root, not a regression of this group's own mechanism.

| # | Tag (on `/matrix-loops/`) | Expected | Status |
|---|---|---|---|
| QL1.1 | `{{title}}`, inside the `category` loop | `Uncategorized` — the loop's own term. Was `Hello world!`, the post sharing the term id | **PASS** |
| QL1.2 | `{{title src:term}}`, same loop | **EMPTY since 1.20.0** (FW-39, D8) — was `Uncategorized` through 1.20.0-pre. `term` now DECLARES a pinning argument, and an explicit `src:term` with none REFUSES at the factory seam before `TaxonomyTerm::resolve_id()` (the loop-aware call that used to answer this) is ever reached — the same refusal a hand-typed, argument-less PIN gets, because the wire cannot tell the two intents apart. See CONTEXT.md I15's fifth shape and `fold-test-matrix.md` §F20.3 | **PASS** |
| QL1.3 | `{{term_archive_url}}`, same loop | `https://<site>/category/uncategorized/` — the extension's own term tag, correct throughout. Its ARCHIVE URL tag and not its title tag: we register a `term_title` of our own, so that name is a registration collision (spec D4) and which plugin answers depends on load order. This row shows a third-party read landing on the loop's term, not whose registration won | **PASS** |
| QL1.4 | `{{title}}`, inside the `department` loop | `Sales`, `Support`, `Warehouse` — the loop's own terms. Was **EMPTY**: the same leak with no post carrying the id (6/7/8 on the reference site) | **PASS** |
| QL1.4b | `{{term_archive_url}}`, same loop | The loop's own archive URLs — the non-vacuity control for QL1.4. Switched from `{{title src:term}}` in 1.20.0 (v20): that read is QL1.2's own now-empty shape and can no longer distinguish "the loop didn't run" from "the loop ran and this root refused", so the extension's independently-proven tag (QL1.3's mechanism) took over the role | **PASS** |

## QL2 — a query loop over USERS

The same mechanism, second item shape, and the rows sit in the same three positions so the two
groups read as one finding. Two author-role fixture users, ordered by display name.

**This group has two reads where QL1 has three, and QL2.2 is a row that says so.** There is no
explicit user source TOKEN — until this fix, user entities were reachable only ambiently, on an
author archive (`context-test-matrix.md` C3) — so QL1.2's middle read still cannot be written
here. The absence was the finding: QL2.1 is what makes a user readable inside a loop at all, and
a user query loop is therefore the plugin's first NON-ambient user source.

| # | Tag (on `/matrix-loops/`) | Expected | Status |
|---|---|---|---|
| QL2.1 | `{{title}}` | `Fixture Author`, `Other Author` — the loop's own users. Was `Sample Page` for the first user and EMPTY for the second, the posts sharing the user ids | **PASS** |
| QL2.2 | *(no tag — a stated absence)* | The row prints its label and nothing else. It is prose on the page for the same reason it is a row here: a group with one read missing looks like a group with a broken row | n/a |
| QL2.3 | `{{user_display_name}}` | `Fixture Author`, `Other Author` — the extension's own user tag, correct throughout, and now the agreement control for QL2.1 | **PASS** |
| QL2.4 | `{{permalink}}` | **EMPTY**, for both users — the one row that did NOT become correct, only honest. The loop's user IS recognized; a user has no permalink of ours to give, which is a deferred author analog reached by a new route rather than a regression. Was the permalink of the post sharing the user id | **PASS** |

## QL3 — a preserved zero in a term loop (not the leak)

Rides this page because it needs a term query loop and nothing else in the blueprint has one. It
is deliberately not in the leak groups: a reader should not have to decide whether a zero is the
leak.

`{{term_count}}` on an empty term returns a bare `'0'` and reaches the falsy-replacement guard in
[`includes/hooks.php`](../../includes/hooks.php) — the fourth tag not ours measured doing so,
and the last of the four to be pinned. What that guard applies to is decided at the guard; this
section neither states nor extends it.

The loop runs with `hide_empty` OFF, which is what reaches the unstaffed `Workshop` term at all,
and the staffed departments loop with it as the non-vacuity control. A zero beside real counts is
the guard working; a column of nothing is a loop that did not run.

| # | Tag (on `/matrix-loops/`) | Expected | Status |
|---|---|---|---|
| QL3.1 | `{{title src:term}}` | **EMPTY since 1.20.0** (FW-39, D8) — was `Sales`, `Support`, `Warehouse`, `Workshop` (the identity for the count beside each) through 1.20.0-pre. Same cause as QL1.2: `term` now declares a pinning argument, so this argument-less `src:term` REFUSES at the factory seam. Left unfixed rather than given QL1.4b's `{{term_archive_url}}` fix: TRIED, and it does not carry over because this is the SECOND `department`-taxonomy term loop on the page and GBQE's own tag needs a `taxonomy` key its loop item does not carry here — a discovered, unexplained GBQE gap on a second same-taxonomy loop, not a D8 consequence. The row is split (label/tag) so it stays visible with nothing after the colon, rather than vanishing whole; QL3.2's own counts remain the non-vacuity control, though a tied count (6, 6) can no longer say which department it belongs to | **PASS** |
| QL3.2 | `{{term_count}}` | real counts for the three staffed departments, then `0` for `Workshop`, which is assigned to no post. The zero survives GB's block-kill only because of the guard; without it the whole row disappears | **PASS** |

## QL4 — a POST query nested inside a TERM query loop

The factory's precedence is already pinned without a site: `traversal-pipeline-test.php`'s `V1 loop item wins over ambient term` drives the same branch with injected signals. What that harness cannot reach is what WordPress and GB actually PUT in `$instance->context` when loops nest — block context is inherited, and the co-resident extension plants `termId` plus a post-shaped `postId` on every term item ([`coresident/gb-query-enhancements.md`](../../docs/coresident/gb-query-enhancements.md#it-resolves-current-through-gbs-query-args-filter-and-plants-three-context-keys)). These rows are that composition, and only a real block render produces it.

The inner query selects on the extension's `current` token in a `tax_query` clause, which the extension substitutes from the outer row's `termId`. That substitution rides `generateblocks_query_wp_query_args`, which GB skips entirely under `inheritQuery` — neither block here sets that attribute, and one gaining it would empty these rows rather than fail them ([`gb-constraints.md`](../../docs/gb-constraints.md#inheritquery-replaces-the-blocks-own-query-and-takes-every-query-args-filter-with-it)).

**The corpus is the #85 contrast pair, and the CROSSING is the assertion.** Fixture Root Entity carries Sales alone, Fixture Ref Target carries Warehouse alone, so no expected value on this page repeats. A nested read that resolved its container therefore prints THE SAME VALUE TWICE, which is visible; a corpus of equal values would pass whichever entity the inner loop resolved. `manifest.php`'s `post_terms` carries the note saying so.

| # | Tag (on `/matrix-loops/`) | Expected | Status |
|---|---|---|---|
| QL4.1 | `{{title}}`, in the OUTER term item beside the nested query | `Sales`, `Warehouse` — the outer loop ran, and nesting did not move its own reads | **PASS** |
| QL4.2 | `{{title}}`, inside the INNER post loop | `Fixture Root Entity` under Sales, `Fixture Ref Target` under Warehouse — the inner loop's own item. The outer department name here would be the inherited-context leak | **PASS** |

## QL5 — a TERM query nested inside a POST query loop

Not QL4 inverted for symmetry's sake: the threat is a different one. The outer post loop has called `the_post()`, so `get_the_ID()` inside the inner term loop returns a REAL, PLAUSIBLE post. QL4's container is a term, which no post-shaped fallback can answer with — so a regression there prints something obviously wrong, while a regression here prints a staff name where a department belongs and looks like working output. That is the [I15] shape, and it is why both directions are pinned rather than one.

The inner query selects with the extension's `current` token in `object_ids`, resolved from `postId` context — the mirror of QL4's `tax_query` substitution, and the only other place the token is exercised by any fixture.

The outer loop is restricted by `post_name`, not by id: the ids are whatever the seed produced, the names are the blueprint's. Same rule as QL1.1's slug restriction and for the same reason.

| # | Tag (on `/matrix-loops/`) | Expected | Status |
|---|---|---|---|
| QL5.1 | `{{title}}`, in the OUTER post item beside the nested query | `Fixture Ref Target`, `Fixture Root Entity` — the non-vacuity control, and why the inner values below are crossed against QL4's rather than repeated | **PASS** |
| QL5.2 | `{{title}}`, inside the INNER term loop | `Warehouse` under Fixture Ref Target, `Sales` under Fixture Root Entity — the inner loop's own term. The outer staff name here would be the live `get_the_ID()` fallback winning | **PASS** |

## QLP — a query loop over WooCommerce PRODUCTS: the shape we refuse (FW-100)

The third item shape, and the first one recognition REFUSES. Every group above ends with our tag reading the loop's own entity; this one ends with it reading nothing, because a product record satisfies no arm of the recognizer. These rows are the visible half of FW-100, and they are seeded against that refusal ON PURPOSE — the same staging pattern the QL groups used one recognition rule earlier, so whatever rule ships has a measured BEFORE to diff against.

**This group lives on `/matrix-products/`, not on `/matrix-loops/`.** WooCommerce's chrome was already in the snapshot baseline when these rows landed, so a new page added a snapshot file and moved none of the existing ones; and QL3's own note records an unexplained GBQE gap that appears on a SECOND loop of the same kind on one page, which a fourth loop beside three others would have been measuring alongside its own subject. `manifest.php`'s `page-matrix-products` entry carries both reasons.

**The item shape, MEASURED end to end 2026-09-14** (a real `WooCommerce_Query` loop on `/matrix-products/`, read through `gb_query_enhancements_wc_loop_item_context` — not off the extension's source, which is all any earlier record rested on). The item is a plain `stdClass` carrying 26 keys: `id`, `name`, `slug`, `type`, `permalink`, `price_html`, `regular_price`, `sale_price`, `sku`, `stock_status`, `stock_quantity`, `average_rating`, `review_count`, `image_id`, `gallery_image_ids`, `add_to_cart_url`, `add_to_cart_text`, `short_description`, `on_sale`, `featured`, `weight`, `length`, `width`, `height`, `categories`, `tags`. `bws_classify_loop_item()` answered `unknown:0` on every one of the three. The id key is lower-case `id` and pairs with nothing the way `term_id` pairs with `taxonomy`, which is why the record is refused; what decides that is owned by that function's PHPDoc and is not restated here.

**These rows do not measure the recognition rule, and reading them as if they did is the QL trap restated.** The loop runs under the extension's real `queryType` string (`WooCommerce`), so a recognizer keyed on that string instead of on the item's shape would flip every row below and look correct. The rows measure the refusal's user-visible consequence, nothing more.

**Two non-vacuity rows, from two different vendors**, because every row of ours here is expected empty and a loop that never ran would look identical from the page. QLP1.3 proves the query returned products; QLP1.4 proves the loop iterated over them.

| # | Tag (on `/matrix-products/`) | Expected | Status |
|---|---|---|---|
| QLP1.1 | `{{title}}` | **EMPTY** — the refusal, and the row the page exists for. Should become `Adjustable Desk Riser`, `Cable Management Kit`, `Workshop Tool Chest` (matching QLP1.3's order) once a recognition rule ships; that flip is what the change is measured by | **PASS (empty as staged)** |
| QLP1.2 | `{{permalink}}` | **EMPTY** — the same refusal on a URL read. Kept beside QLP1.1 because it separates a product from the USER shape: QL2.4 is empty PERMANENTLY (a user has no permalink of ours to give), while a product IS a post and has one, so this row becoming non-empty is a positive result and QL2.4's would not be | **PASS (empty as staged)** |
| QLP1.3 | `{{product_sku}}` | `BWSFX-DR-01`, `BWSFX-CM-02`, `BWSFX-TC-03` — the extension's own product tag, and the identity for the two rows above. This row present with QLP1.1 absent is the refusal; this row absent too is a loop that never ran | **PASS** |
| QLP1.4 | `{{loop_index}}` | `1`, `2`, `3` — GB's own loop index. Not redundant with QLP1.3: that one proves the query returned products, this proves the looper's inner block rendered per item | **PASS** |
