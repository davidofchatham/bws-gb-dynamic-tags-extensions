# WooCommerce, as it touches this plugin

**Facts about somebody else's code.** One file per co-resident plugin; this one is [WooCommerce](https://woocommerce.com/). It records what WooCommerce's code does where it meets ours, so that a rule of ours written in response to it can point at a measured fact instead of restating one. It is not a WooCommerce manual and not a statement of what we do about any of it: **our responses live at their enforcing sites**, named per section below.

Pure GenerateBlocks facts stay in [`gb-constraints.md`](../gb-constraints.md), and what the co-resident QUERY plugin does with a product is [`gb-query-enhancements.md`](gb-query-enhancements.md) — the item record a product loop hands us is that plugin's, not WooCommerce's, and the split matters because the two can move independently. Everything here is dated and version-stamped, because none of it is ours to keep true.

**Measured against WooCommerce 11.1.0** on the fixture site, which records the same set in [`env-versions.php`](../../tools/fixtures/core-structures/env-versions.php). WooCommerce is a required active plugin there and [`testbed.md`](../testbed.md) says why.

**Only what this repo depends on is kept here.** Three sections below are cited from this repo's tracker (FW-100) and fixture README, so a private path would dangle for everyone else; they stay. The wider set of WooCommerce facts, including the ones other projects found, lives in the author's private shared reference notes (no path, by design). A fact new to this plugin goes there first and is restated here only when this plugin's code, fixture or tracker depends on it.

## A product is a post, and the ids are equal

`product` is an ordinary custom post type and a product's WooCommerce id IS its post id (measured 2026-09-14: for all three fixture products, `$product->get_id()` equals `wp_posts.ID`). Its title is `post_title`, its slug is `post_name`, its description is `post_content` and its permalink is `get_permalink()` on that post — the last one measured too, since it is the claim the recognizer's permalink veto compares against.

**This is the fact FW-100 rests on and also the one that hid the bug it fixed.** Because the ids are equal, a read that leaked a product id into a post lookup returned the right entity by arithmetic coincidence — which is why product loops worked before 1.19.0 and why the 1.19.0 refusal read as a regression rather than as the leak closing. The same coincidence hid the term-id leak ([#123](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/123)) for as long as it did.

**Our response** is that nothing product-shaped enters our vocabulary at all. A recognized product loop item answers the ordinary `post` kind carrying that id, and every consumer downstream is unchanged; what decides whether an item is recognized is owned by [`bws_classify_loop_item()`](../../includes/helpers/field-helpers.php)'s PHPDoc.

## Coming-soon mode serves every store URL as a placeholder

A store whose setup wizard never completed has `woocommerce_coming_soon` ON by default, and it serves store URLs — the single product page included — as a launch placeholder to anyone not logged in (measured 2026-09-14: the fixture site's product single rendered "Our store is in the works" and none of its content, while `/matrix-products/` rendered normally).

**Recorded because it is invisible from the surface most likely to be checked.** An ordinary page carrying a product LOOP is not a store URL and renders fine, so a product loop can pass while the product's own page shows no product at all. Our response is fixture state, not code: the blueprint sets the option off, and [`manifest.php`](../../tools/fixtures/core-structures/manifest.php)'s `wp_options` comment says why.

## Two rendering details that churn a page snapshot

Both are chrome, neither is ours, and both are recorded because they cost a day when the ambient product rows first tried to hold a baseline.

The add-to-cart **quantity field's id comes from `uniqid()`**, so it is new on every request rather than on every reseed — the sharpest member of that class, since it fails a baseline captured seconds earlier. Our response is a normalization rule in [`page-snapshots.php`](../../tools/test/page-snapshots.php), pinned at `page-snapshot-normalize-test.php` §P7.4/§P7.5.

The **related-products block is nondeterministic by design**: the data store orders by `RAND()` and WooCommerce shuffles the result again, so the same page reorders its own related list between two consecutive renders. Our response is fixture state — the blueprint's `schema.php` removes the block on the fixture site, and says there why removing it beats teaching the snapshot instrument to stop looking at a region.
