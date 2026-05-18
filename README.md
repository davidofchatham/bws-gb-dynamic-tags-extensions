# BWS GenerateBlocks Dynamic Tags Extensions

WordPress plugin extending [GenerateBlocks Pro](https://generatepress.com/blocks/) with advanced dynamic tags for both standard post/term fields and custom field data. `datetime_single` and `datetime_range` offer custom date/time formatting for date/datetime/time fields, and `try_*` tags allow using the first available (populated) field from an editor-selected list of sources/fields.

The base tags (`{{text}}`, `{{image}}`, `{{datetime_single}}`, etc.) are designed to be source-agnostic, currently tested for both post and loop item contexts. A custom `src` option allows using the current entity or one related by a reference/relational field, and the `srcTermIn` toggle and taxonomy selector allows using a taxonomy term applied to the selected source. 

The `term_*` modifier wraps base tags for term-context resolution using GenerateBlock's built-in taxonomy/term selector; external plugins can register additional modifier prefixes via `TagTemplateRegistry::register_modifier()`.

## try_ tags

`try_*` tags (e.g. `try_text`, `try_image`, `try_content`, `try_datetime_single`): use a single tag to try up to 5 source and/or field combinations in sequence and return the first non-empty result. Useful for "try ACF field, fall back to post title; if still empty, fall back to a related post's field" patterns without conditional template logic.

## Requirements

- WordPress 6.5+
- GenerateBlocks Pro
- ACF or any plugin hooking `generateblocks_get_meta_pre_value` for custom-field reads (optional)

## Documentation

- [`CHANGELOG.md`](CHANGELOG.md) — version history
- [`docs/tag-matrix.md`](docs/tag-matrix.md) — current tag architecture, options, preview-label schema
- [`docs/deprecated-tags-options.md`](docs/deprecated-tags-options.md) — N×M historical reference + migration tracker
- [`docs/plugin-integration.md`](docs/plugin-integration.md) — external plugin API (`register_modifier()`, deprecated wrappers)
- [`docs/gb-constraints.md`](docs/gb-constraints.md) — GB editor/runtime constraint catalog
