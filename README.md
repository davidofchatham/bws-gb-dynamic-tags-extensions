# BWS GenerateBlocks Dynamic Tags Extensions

WordPress plugin extending [GenerateBlocks Pro](https://generatepress.com/blocks/) with advanced dynamic tags for both standard post/term fields and custom field data. 

The tags are designed to be source-agnostic (currently tested for both post and loop item contexts). A custom *Source* selector allows allows you to use a source related to the current context by a reference/relational field, or site-wide data (for option fields, logo, and name). You can also use a taxonomy term applied to the current-context post or referenced post as the field source, instead of by manually picking a term directly.

| Tag | Description |
|---|---|
| `text` | Return simple meta/option text fields or post title/term name* (useful in `try_` tags). |
| `image` | Return an image from a meta field or the post featured image or site logo field, with return options like GB's (alt text, etc.) and a Media Library fallback image selector. |
| `content` | Return post content/term description* via a processing pipeline that handles block-rendered content safely, including consolidating block CSS for embedded post content into the page footer. |
| `datetime_single` | Format combined datetime fields or separate date and time fields you want to show as a single date and time. By default, also hides midnight times and the current year. |
| `datetime_range` | Like `datetime_single`, but to format a range from separate start and end date/datetime/time fields. |
| `email` | Return an email address from meta/option field as a `mailto` link (by default) or as plain text. |
| `title` | Return post title/term name.* |
| `permalink` | Return post/term permalink.* |

\* Not yet tested in term context without `term_` prefix.

**Note:** As of now, meta/option field names must be supplied manually (there's no dropdown selector).

## try_ tags

`try_*` tags (e.g. `try_text`, `try_image`, `try_content`, `try_datetime_single`) allow using the first available (populated) field from an editor-selected list of up to five sources/fields. Useful for "try ACF field, fall back to post title; if still empty, fall back to a related post's field" patterns without conditional template logic.

## Context modifiers

The `term_*` modifier wraps base tags for term-context resolution using GenerateBlock's built-in taxonomy/term selector; external plugins can register additional modifier prefixes via `TagTemplateRegistry::register_modifier()`.

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
