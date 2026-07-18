# BWS GenerateBlocks Dynamic Tags Extensions

A [GenerateBlocks Pro](https://generatepress.com/blocks/) extension with advanced dynamic tags for both standard post/term fields and custom field data.

## What's different?

### Fewer tags, more sources

Our tags currently work across post, loop item, and taxonomy term archive contexts. The same `{{text key:some_field}}` tag can be used in a post template, a repeater row query loop, or a taxonomy term archive, and it will return the local field of that name in each case! Blog, search, date, author, and post-type archives are not yet supported (they still return from the first post in the query), but we're aiming for full source agnosticism in the future.

Not only can you start from post, loop, and term contexts without changing tags, but you can also pull from a source related to the current context via a reference/relational field (e.g. ACF Relationship fields), or from site-wide data (option fields, logo, and name). You can also use a taxonomy term applied to the current or referenced post as the field source, instead of picking a term manually.

### Unlocked field selector

GB's field selector is post-type-based, so when you're building GP Elements or WP Patterns, you usually can't see the fields that are actually available for what you're working on. Using our tags, starting in v1.13, every meta/option field key input allows you to search by label/name, as well as filter by context, field group, and field type, among all registered fields (including ACF fields and sub-fields, options-page fields, term fields, and post meta fields).

## Cross-source base tags

| Tag | Description | Specific Limitations |
|---|---|---|
| `text` | Return simple meta/option text fields or post title/term name (useful in `try_` tags). | |
| `image` | Return an image from a meta field or the post featured image or site logo field, with return options like GB's (alt text, etc.) and a Media Library fallback image selector. For alt text, it returns `' '` if the alt text field is empty, which means you *don't* have to set `required:false` to avoid the entire image tag being suppressed when the alt text is missing. | Since terms have no native image fields, a field name must be supplied to retrieve images from a term source. |
| `content` | Return post content/term description via a processing pipeline that handles block-rendered content safely, including consolidating block CSS for embedded post content into the page footer. | Since there's no site-wide body/content field, an option field name must be supplied to use this tag with the "site" source. |
| `datetime_single` | Format combined datetime fields or separate date and time fields you want to show as a single date and time. By default, also hides midnight times and the current year. | Single result only; a multi-result source (taxonomy terms or a reference/relationship field) returns just the first. A date *list* is planned. |
| `datetime_range` | Like `datetime_single`, but to format a range from separate start and end date/datetime/time fields. | Single result only (same as `datetime_single`). |
| `email` | Return an email address from meta/option field as a `mailto` link (by default) or as plain text. Validates stored emails (by format) and returns empty if invalid. | |
| `phone` | Return a phone number from meta/option field as a `tel` link (by default) or as plain text. Normalizes stored numbers and allows global country code configuration. | |
| `title` | Return post title/term name or site name. | |
| `permalink` | Return post/term permalink or site URL. | |

## `join` tag to combine fields

Where a `try_` tag returns the first populated field, the `join` tag keeps *all* populated fields and assembles them into one line. Configure up to 10 slots, each reading its own text value (a meta/option field or a title/name, from the current post, a related post, taxonomy terms, or a site option), in either of two modes:

- **Separator mode** joins every non-empty value with a separator string (default `", "`), skipping empties so a missing middle value never leaves a doubled separator.
- **Template mode** places values by position in a format string (tokens `%1`-`%10`), and punctuation attached to an empty value drops with it: an empty bracketed part sheds its brackets, an empty middle part its comma, a missing unit value its mark. One format string renders `Dr. Tom M. Smith Jr., PhD, USN (Ret.)` and collapses cleanly to `Jane Johnson`.

An optional fallback text renders when every slot is empty. Output is plain text with no per-slot links; a stored `0` counts as a real value. For units, use the prime marks `′` (feet) and `″` (inches) rather than straight quotes, which WordPress converts to curly quotes on the front end (`%1′%2″` renders `5′11″`, or `5′` with no inches value).

## First-available tags

`try_*` tags (e.g. `try_text`, `try_image`, `try_content`, `try_datetime_single`, `try_email`, `try_phone`) allow using the first available (populated) field from an editor-selected list of up to five sources/fields. Each slot resolves exactly as the standalone tag would (`try_email` returns a finished `mailto:` link per slot, `try_phone` a `tel:` link), so a contact chain like "personal email → team email → site-wide address" works without multiple blocks and complicated visibility conditions.

**Note:** Currently, only `try_email` and `try_phone` support site option fields.

## `call` tag for custom functions

The `call` tag hands off a post ID to a PHP function and returns its output. I've grouped it with GB's Post tags since it's strictly post-based, unlike the other tags. However, it still allows using a post related to the current context via a reference/relational field, and it can also pass correct post IDs when used within a Post Meta Query Loop on a reference/relational field.

Custom functions must take a post ID as their first argument (or you may get unexpected results), sanitize their own output (HTML is allowed in the return string), and be registered via `bws_register_call_function()`:

```php
add_action( 'init', function () {
    bws_register_call_function( 'my_result' );
} );

function my_result( $post_id, $arg = '' ) {
    return '<span>' . esc_html( get_the_title( $post_id ) ) . '</span>';
}
```

Properly registered functions will appear in the tag's **Function** dropdown for easy access. The optional **Argument** field allows passing a second parameter in addition to the post ID.

A security gate blocks adding PHP built-ins (`system`, `unlink`, `eval`, and the like) or anything that isn't a real function. All functions registered via the filter are shown, along with their security-gate status, on the admin settings page. Manually inserting an unregistered or blocked function will cause the tag to return its fallback text or return empty.

## Context modifiers

The `term_*` modifier wraps base tags, allowing term-context resolution using GenerateBlock's built-in taxonomy/term selector. External plugins can register additional modifier prefixes via `TagTemplateRegistry::register_modifier()`.

## Requirements

- WordPress 6.5+
- GenerateBlocks Pro
- ACF or any plugin hooking `generateblocks_get_meta_pre_value` for meta/option-field reads (optional)

## Documentation

- [`CHANGELOG.md`](CHANGELOG.md) — version history
- [`docs/tag-reference.md`](docs/tag-reference.md) — current tag architecture, options, render order
- [`docs/editor-tag-previews.md`](docs/editor-tag-previews.md) — editor-time tag configuration preview text
- [`CONTEXT.md`](CONTEXT.md) — cross-cutting design invariants (source-analog model, `use`-dispatch, qualifying gate)
- [`docs/deprecated-tags-options.md`](docs/deprecated-tags-options.md) — N×M historical reference + migration tracker
- [`docs/plugin-integration.md`](docs/plugin-integration.md) — external plugin API (`register_modifier()`, deprecated wrappers)
- [`docs/gb-constraints.md`](docs/gb-constraints.md) — GB editor/runtime constraint catalog

## Acknowledgements

Of course, this is completely dependent on the work of [Tom Usborne and the GeneratePress/GenerateBlocks team](https://generatepress.com/about/). But I'm also quite indebted to [Taylor Drayson](https://taylordrayson.com), whose SnippetClub tutorial [How to Create Custom Dynamic Tags in GenerateBlocks 2.0: A Complete Guide](https://snippetclub.com/how-to-create-custom-dynamic-tags-in-generateblocks-2-0-a-complete-guide/) started me down this path. Based on that, I began using Claude to generate my own tag code snippets, and it's grown from there through many, many versions into this plugin!

### Libraries

- In-WordPress update notices and one-click updates are powered by [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) by [Yahnis Elsts](https://github.com/YahnisElsts) (MIT-licensed), bundled at [`libs/plugin-update-checker/`](libs/plugin-update-checker/).
