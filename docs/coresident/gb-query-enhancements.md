# GB Query Enhancements, as it touches this plugin

**Facts about somebody else's code.** One file per co-resident plugin; this one is [GB Query Enhancements](https://gbqueryenhancements.com/) (GBQE). It records what GBQE's code does where it meets ours, so that a rule of ours written in response to it can point at a measured fact instead of restating one. It is not a GBQE manual and not a statement of what we do about any of it: **our responses live at their enforcing sites**, named per section below.

Pure GenerateBlocks facts stay in [`gb-constraints.md`](../gb-constraints.md); this file is for what a co-resident extension does with them. Everything here is dated and version-stamped, because none of it is ours to keep true.

**Measured against GBQE 1.3.0** (GB 2.4.1, GB Pro 2.7.1) on the fixture site, which records the same set in [`env-versions.php`](../../tools/fixtures/core-structures/env-versions.php). GBQE is a required active plugin there and [`testbed.md`](../testbed.md) says why.

## It adds query types through `generateblocks.editor.looper.query`

GBQE's Term Query, User Query and Product Query types are contributed to GB's Looper through the `generateblocks.editor.looper.query` JS filter (`build/index.js`, callback `gb-query-enhancements/looper/add-queries`), each backed by its own REST route — `/gb-query-enhancements/get-term-query`, `/gb-query-enhancements/get-user-query`.

**Its term and user hooks put the editor preview CONTEXT OBJECT in a React dependency array** (measured 2026-09-04): `useEffect( …, [ JSON.stringify( query ), enabled, attributes, context, selectedBlock ] )`. The query is compared by value, the context by identity. That context is the object GB hands the hook, which is the object [`generateblocks.editor.preview.context`](../gb-constraints.md#the-editor-preview-context-is-filtered-during-render-and-travels-into-consumers-dependency-arrays) callbacks returned — so **any filter on that hook that builds a fresh object per call makes the dependency change on every render**, and the loop refetches, sets state, and renders again without end. Symptom: a Term Query or User Query loop flickering in the editor, console filling with `@wordpress/data`'s "useSelect hook returns different values when called with the same state and parameters".

GB's own `WP_Query` hook does not depend on the context object, so this is specific to GBQE's two query types.

**Our response** is that our callback returns an identity-stable object; [`editor-preview-context.js`](../../assets/js/editor-preview-context.js)'s docblock owns that rule and [`editor-preview-context-test.js`](../../tools/test/editor-preview-context-test.js) pins it for every file registering on the hook. Shipped 1.6.0, fixed 2026-09-04. **Worth reporting upstream:** comparing `context` by value the way they already compare `query` would make their hooks immune to any filter on GB's, ours included.

## It filters tag rendering in PHP, on every render

Three hooks, enumerated 2026-09-04 (`add_filter`/`add_action` registrations under `includes/`):

| Hook | Where | What it means for us |
|---|---|---|
| `generateblocks_dynamic_tag_id` ×3 | `Term_Query` (priority 99), `WooCommerce_Query` (20), `User_Query` (15) — one `set_dynamic_tag_id()` each | Overrides the resolved entity id, and **GB does not pass `$fallback_type`**, so it cannot tell a term id from a post id: the upstream half of [#123](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/123). See [`gb-constraints.md`](../gb-constraints.md#generateblocks_dynamic_tag_id-is-not-told-which-entity-the-id-was-a-fallback-for). Our response is item-shape recognition at [`bws_classify_loop_item()`](../../includes/helpers/field-helpers.php) |
| `generateblocks_dynamic_tag_output` ×1 | `Dynamic_Tags::filter_output`, priority 99 | Re-applies `$options['fallback']` when output is empty, on OUR tags too, since we route output through GB's `output()`. Our response is the single boundary [`bws_gb_tag_output()`](../../includes/helpers/gb-output-boundary.php), which decides what reaches it |
| `generateblocks_dynamic_tag_replacement` ×1 | `User_Query::alter_replacement`, priority 99 | **Does not reach our tags**: it early-returns unless `$args['tag']` is exactly `user_meta`. Recorded because we hook the same filter at priority 10 ([`includes/hooks.php`](../../includes/hooks.php)), so ours runs first and theirs would see our padded value if the gate ever widened |

## It registers 29 dynamic tags of its own, and four names are ones we would use

`term_title`, `term_description`, `term_archive_url`, `term_count`, plus seven `user_*` and eighteen `product_*` (29 distinct names, enumerated 2026-09-04 in `includes/Dynamic_Tags.php`).

Registration is first-come, so on a site with both plugins the `term_*` names GBQE holds are names our `term_` modifier family stands down from rather than overwriting. That yield is ours, not GB's — [`bws_gb_register_tag()`](../../includes/helpers/gb-registration-boundary.php) and its neighbours own the rule and the report surfaces, and the Tag Name Conflicts block on the settings page shows the outcome per tag.
