# GB Query Enhancements, as it touches this plugin

**What GB Query Enhancements (GBQE) does is recorded elsewhere; this file is where that meets our code.** The facts about [GBQE](https://gbqueryenhancements.com/) itself (its query types, the keys on its loop items, its hooks and tags) are owned by `gb-query-enhancements.md` in the shared reference repo, a private repo that this public one cannot link to. Each section below names the fact in a line, then holds what only this plugin can say: the interaction, and **our responses, which live at their enforcing sites**, named per section.

Pure GenerateBlocks facts stay in [`gb-constraints.md`](../gb-constraints.md). Everything here is dated and version-stamped, because none of it is ours to keep true.

**Measured against GBQE 1.3.0** (GB 2.4.1, GB Pro 2.7.1) on the fixture site, which records the same set in [`env-versions.php`](../../tools/fixtures/core-structures/env-versions.php). GBQE is a required active plugin there and [`testbed.md`](../testbed.md) says why.

## Its Term and User query hooks loop on a fresh preview-context object

GBQE's Term Query and User Query hooks put the editor preview context object in a React dependency array, compared by identity. That context is the object [`generateblocks.editor.preview.context`](../gb-constraints.md#the-editor-preview-context-is-filtered-during-render-and-travels-into-consumers-dependency-arrays) callbacks returned, so a callback of ours that built a fresh object per call made the loop refetch and render without end (measured 2026-09-04).

**Our response** is that our callback returns an identity-stable object; [`editor-preview-context.js`](../../assets/js/editor-preview-context.js)'s docblock owns that rule and [`editor-preview-context-test.js`](../../tools/test/editor-preview-context-test.js) pins it for every file registering on the hook. Shipped 1.6.0, fixed 2026-09-04. **Worth reporting upstream:** comparing `context` by value, as they already compare `query`, would make their hooks immune to any filter on GB's, ours included.

## It resolves `current` in nested queries, and plants `postId`

GBQE resolves the literal `current` through GB's query-args filter in both nesting directions, and each of its loop items puts the item's id in bare `postId`: a term id on the Term loop, a user id on the User loop, a product's post id on the Product loop.

**Both resolutions inherit a GB limitation that reads as a defect in ours.** On an inheriting block (`inheritQuery`) GB skips the filter, see [`gb-constraints.md`](../gb-constraints.md#inheritquery-replaces-the-blocks-own-query-and-takes-every-query-args-filter-with-it), so the substitution never happens and the loop iterates the ambient page's main-query rows. A tag of ours inside that loop then reads the containing page, once per outer row, correctly. Measured 2026-09-04 on a live site; nothing of ours is involved in producing it and there is nothing for us to fix.

**What the `postId` shape means for us.** A term id sitting in a key named `postId` is the wrong-entity-plausible-value shape one key over from where it usually appears, and it is inherited by every descendant block until an inner loop overwrites it. We read bare `postId` at exactly one site, `bws_resolve_acf_object_id()` ([`field-helpers.php`](../../includes/helpers/field-helpers.php)), and that read is reached only under a query type none of the three sets, which is what keeps a term id out of an ACF object id today. The condition deciding it is stated at that function; widening it is the change to watch.

The Product loop is the most dangerous of the three to read, not the safest: its `postId` is exactly what the key says, so a rule taking the product id off block context would be right there and wrong-entity on the term and user loops beside it, and the reading site cannot tell them apart. Nothing of ours reads it, and FW-100's recognizer deliberately takes the id off the ITEM instead.

## Its Product Query's loop item names nothing

The item is a 26-key `stdClass` with no class and nothing saying what it is; three keys (`id`, `slug`, `permalink`) are checkable against a WordPress post and the rest are computed values that exist nowhere in the database (measured 2026-09-14).

**Our response** is the object-shaped post arm of [`bws_classify_loop_item()`](../../includes/helpers/field-helpers.php), whose rule is stated at `bws_loop_item_post_id()`'s PHPDoc and nowhere else. The computed values are **not** read by us at all, deliberately: keying on `sku` or `price_html` would be recognizing this plugin's vocabulary rather than a shape, the same objection that rules out keying on its `queryType` string.

**Worth reporting upstream, and the reason this section is longer than the fact:** an item that named its own kind would let every duck-typed arm of that recognizer retire. This is the third vendor record we have reverse-engineered to answer "what is this", and each one is a rule of ours that a change of theirs can silently invalidate.

## Its PHP hooks on tag rendering, and what each means for us

GBQE registers three kinds of hook on tag rendering; the hooks, priorities and gates are in the shared file.

| Hook | What it means for us |
|---|---|
| `generateblocks_dynamic_tag_id` ×3 | GB does not pass `$fallback_type`, so GBQE's callbacks cannot tell a term id from a post id: the upstream half of [#123](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/123). See [`gb-constraints.md`](../gb-constraints.md#generateblocks_dynamic_tag_id-is-not-told-which-entity-the-id-was-a-fallback-for). Our response is item-shape recognition at [`bws_classify_loop_item()`](../../includes/helpers/field-helpers.php) |
| `generateblocks_dynamic_tag_output` ×1 | Re-applies `$options['fallback']` when output is empty, on OUR tags too, since we route output through GB's `output()`. Our response is the single boundary [`bws_gb_tag_output()`](../../includes/helpers/gb-output-boundary.php), which decides what reaches it |
| `generateblocks_dynamic_tag_replacement` ×1 | **Does not reach our tags**: it early-returns unless `$args['tag']` is exactly `user_meta`. Recorded because we hook the same filter at priority 10 ([`includes/hooks.php`](../../includes/hooks.php)), so ours runs first and theirs would see our padded value if the gate ever widened |

## It registers 29 dynamic tags, and four names are ones we used to hold

GBQE holds `term_title`, `term_description`, `term_archive_url` and `term_count`. Our `term_` modifier family used to compete for them and stood down on a site with both plugins; it was unregistered in 1.21.0 ([`deprecated-tags-options.md`](../deprecated-tags-options.md#the-term_-modifier-family-removed-in-v1210)), so no tag of ours claims any of the four now and nothing yields for them. The registration rule itself stands for every other name: [`bws_gb_register_tag()`](../../includes/helpers/gb-registration-boundary.php) and its neighbours own it and the report surfaces, and the Tag Name Conflicts block on the settings page shows the outcome per tag.

### Its `term_title` wire is indistinguishable from what our removed family stored

`term_title` registers with `'type' => 'term'`, so GB renders its own term picker and serializes `id` plus a `tax` sidecar, **the same two keys our `term_` family wrote**, since ours registered `gb_type: 'term'` for the same picker (measured 2026-09-06 on a live site, GBQE 1.3.0). A stored string like `{{term_title id:<term id>|tax:<taxonomy>}}` therefore carries no evidence of which plugin's tag authored it, and no option-vocabulary check can supply that evidence. With GBQE active, our converter leaves stored `term_title` and `term_description` strings alone and offers the per-name choice to claim them; the rule is at the ownership guard, [`bws_converter_tag_ownership()`](../../includes/helpers/converter-ownership.php), and the testbed check in `verify.php` holds that the real GBQE registry reaches it.

Their tag also needs a loop item for the term's name, so such a string rendered outside a term loop produces empty output under their tag even though the id resolves. On a site that wrote it under our family, GBQE being active means those blocks now render through a tag that needs a loop they are not in; without GBQE nothing registers the name and the braces print literally.
