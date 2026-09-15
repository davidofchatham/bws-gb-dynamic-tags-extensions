# WooCommerce, as it touches this plugin

**Facts about somebody else's code.** One file per co-resident plugin; this one is [WooCommerce](https://woocommerce.com/). It records what WooCommerce's code does where it meets ours, so that a rule of ours written in response to it can point at a measured fact instead of restating one. It is not a WooCommerce manual and not a statement of what we do about any of it: **our responses live at their enforcing sites**, named per section below.

Pure GenerateBlocks facts stay in [`gb-constraints.md`](../gb-constraints.md), and what the co-resident QUERY plugin does with a product is [`gb-query-enhancements.md`](gb-query-enhancements.md) — the item record a product loop hands us is that plugin's, not WooCommerce's, and the split matters because the two can move independently. Everything here is dated and version-stamped, because none of it is ours to keep true.

**Measured against WooCommerce 11.1.0** on the fixture site, which records the same set in [`env-versions.php`](../../tools/fixtures/core-structures/env-versions.php). WooCommerce is a required active plugin there and [`testbed.md`](../testbed.md) says why.

## A product is a post, and the ids are equal

`product` is an ordinary custom post type and a product's WooCommerce id IS its post id (measured 2026-09-14: for all three fixture products, `$product->get_id()` equals `wp_posts.ID`). Its title is `post_title`, its slug is `post_name`, its description is `post_content` and its permalink is `get_permalink()` on that post — the last one measured too, since it is the claim the recognizer's permalink veto compares against.

**This is the fact FW-100 rests on and also the one that hid the bug it fixed.** Because the ids are equal, a read that leaked a product id into a post lookup returned the right entity by arithmetic coincidence — which is why product loops worked before 1.19.0 and why the 1.19.0 refusal read as a regression rather than as the leak closing. The same coincidence hid the term-id leak ([#123](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/123)) for as long as it did.

**Our response** is that nothing product-shaped enters our vocabulary at all. A recognized product loop item answers the ordinary `post` kind carrying that id, and every consumer downstream is unchanged; what decides whether an item is recognized is owned by [`bws_classify_loop_item()`](../../includes/helpers/field-helpers.php)'s PHPDoc.

## Product meta is protected postmeta, absent from the registered-meta envelope

A product's own fields (`_price`, `_regular_price`, `_sale_price`, `_sku`, `_stock_status`) are ordinary postmeta with an underscore-prefixed key, which makes them PROTECTED in WordPress's sense. Measured 2026-09-14 on the fixture site: all five are stored, all five answer `is_protected_meta()` true, and none is in the registered-meta envelope — `get_registered_meta_keys( 'post', 'product' )` returns three keys and all three belong to an SEO plugin, so WooCommerce contributes NONE of its own.

Two consequences, and only the second is ours. A read BY KEY off a product works exactly as it does off any post — measured on the fixture site's own `product_note`, a plain unprotected key ([`context-test-matrix.md`](../../tools/test/context-test-matrix.md) §C-PROD.3). But the field PICKER offers neither protected nor unregistered keys, so a product's own Woo fields are not pickable and have to be typed. That limitation is tracked at FW-13, which already owns what the picker offers and the permission dimension that goes with it; it is a boundary, not a defect.

## The shop and product-category archives are Woo-templated

`/shop/` and `/product-category/<slug>/` are rendered by WooCommerce's own templates rather than by the theme's ordinary archive template (WooCommerce 11.1.0). For us this is a statement about whether a BLOCK renders on those pages at all, which is a theme question — our own resolution has no post-type gate anywhere in the read path, so a product archive is a post-type archive and a product-category archive is a term archive.

Those two surfaces are deliberately NOT measured (`context-test-matrix.md` §C-PROD states the reason and what rides in their place). Measuring them genuinely is a fixture-theme task.

## Coming-soon mode serves every store URL as a placeholder

A store whose setup wizard never completed has `woocommerce_coming_soon` ON by default, and it serves store URLs — the single product page included — as a launch placeholder to anyone not logged in (measured 2026-09-14: the fixture site's product single rendered "Our store is in the works" and none of its content, while `/matrix-products/` rendered normally).

**Recorded because it is invisible from the surface most likely to be checked.** An ordinary page carrying a product LOOP is not a store URL and renders fine, so a product loop can pass while the product's own page shows no product at all. Our response is fixture state, not code: the blueprint sets the option off, and [`manifest.php`](../../tools/fixtures/core-structures/manifest.php)'s `wp_options` comment says why.

## Two rendering details that churn a page snapshot

Both are chrome, neither is ours, and both are recorded because they cost a day when the ambient product rows first tried to hold a baseline.

The add-to-cart **quantity field's id comes from `uniqid()`**, so it is new on every request rather than on every reseed — the sharpest member of that class, since it fails a baseline captured seconds earlier. Our response is a normalization rule in [`page-snapshots.php`](../../tools/test/page-snapshots.php), pinned at `page-snapshot-normalize-test.php` §P7.4/§P7.5.

The **related-products block is nondeterministic by design**: the data store orders by `RAND()` and WooCommerce shuffles the result again, so the same page reorders its own related list between two consecutive renders. Our response is fixture state — the blueprint's `schema.php` removes the block on the fixture site, and says there why removing it beats teaching the snapshot instrument to stop looking at a region.

## Site-wide chrome that moved 19 snapshot pages

Installing WooCommerce 11.1.0 moved every page in the snapshot baseline (2026-09-14), in a commit that changed no code. Every changed line was Woo chrome: the `sourcebuster` and `wc-order-attribution` footer scripts, the `woocommerce-no-js` body class and the inline script that swaps it, and an `aria-label="Page N"` on every paginated archive from `wc_add_aria_label_to_pagination_numbers()` on `paginate_links_output` — a site-wide filter, not one scoped to Woo's own pages.

**No rendered tag moved, and that was measured rather than assumed:** every changed line was bucketed by category with none left unclassified, and each page's schema line was compared byte-for-byte against its baseline twin. Recorded here because it is the standing answer to "why did 19 pages move", which a gitignored plan file could not keep.
