# BWS GenerateBlocks Dynamic Tags Extensions

WordPress plugin extending [GenerateBlocks Pro](https://generatepress.com/blocks/) with advanced dynamic tags for both standard post/term fields and custom field data.

The tags are designed to be source-agnostic (currently tested for both post and loop item contexts). A custom *Source* selector lets you retrieve data not only from the current context, but also from a source related via a reference/relational field (e.g. ACF Relationship fields), or from site-wide data (option fields, logo, and name). You can also use a taxonomy term applied to the current or referenced post as the field source, instead of picking a term manually.

| Tag | Description | Limitations |
|---|---|---|
| `text` | Return simple meta/option text fields or post title/term name* (useful in `try_` tags). | Not yet tested in term context without `term_` prefix. |
| `image` | Return an image from a meta field or the post featured image or site logo field, with return options like GB's (alt text, etc.) and a Media Library fallback image selector. For alt text, it returns `' '` if the alt text field is empty, which means you *don't* have to set `required:false` to avoid the entire image tag being suppressed when the alt text is missing. | |
| `content` | Return post content/term description via a processing pipeline that handles block-rendered content safely, including consolidating block CSS for embedded post content into the page footer. | Not yet tested in term context without `term_` prefix. |
| `datetime_single` | Format combined datetime fields or separate date and time fields you want to show as a single date and time. By default, also hides midnight times and the current year. | |
| `datetime_range` | Like `datetime_single`, but to format a range from separate start and end date/datetime/time fields. | |
| `email` | Return an email address from meta/option field as a `mailto` link (by default) or as plain text. Validates stored emails (by format) and returns empty if invalid. | |
| `phone` | Return a phone number from meta/option field as a `tel` link (by default) or as plain text. Normalizes stored numbers and allows global country code configuration. | |
| `title` | Return post title/term name* or site name. | Not yet tested in term context without `term_` prefix. |
| `permalink` | Return post/term permalink* or site URL. | Not yet tested in term context without `term_` prefix. |

**Note:** As of now, meta/option field names must be supplied manually (there's no dropdown selector).

## try_ tags

`try_*` tags (e.g. `try_text`, `try_image`, `try_content`, `try_datetime_single`, `try_email`, `try_phone`) allow using the first available (populated) field from an editor-selected list of up to five sources/fields. Useful for "try ACF field, fall back to post title; if still empty, fall back to a related post's field" patterns without conditional template logic.

Each slot resolves exactly as the standalone tag would — `try_email` returns a finished `mailto:` link per slot, `try_phone` a `tel:` link — so a contact chain like "personal email → team email → site-wide address" works without multiple blocks and visibility conditions.

**Note:** `try_email` and `try_phone` support a site-field slot, but the other `try_*` tags do not yet offer site fields.

## Context modifiers

The `term_*` modifier wraps base tags for term-context resolution using GenerateBlock's built-in taxonomy/term selector; external plugins can register additional modifier prefixes via `TagTemplateRegistry::register_modifier()`.

**Note:** `term_*` tags read from the term (or a related post), not from site fields — for a site-wide contact address use `try_email`/`try_phone` (site slot) or `{{email src:site}}`.

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
