# BWS GenerateBlocks Dynamic Tags Extensions

WordPress plugin extending [GenerateBlocks](https://generateblocks.com/) with source-agnostic dynamic tags for custom field data, taxonomy terms, and date/time formatting.

A single tag (`{{text}}`, `{{image}}`, `{{datetime_single}}`, etc.) handles every traversal via a `src` option (current entity, relationship-field hop, etc.) and an optional `srcTermIn` term-hop. The built-in `term_*` modifier wraps base tags for term-context resolution; external plugins can register additional modifier prefixes via `TagTemplateRegistry::register_modifier()`.

## try_ tags

`try_*` tags (e.g. `try_text`, `try_image`, `try_content`, `try_datetime_single`): use a single tag to try up to 5 source and/or field combinations in sequence and return the first non-empty result. Useful for "try ACF field, fall back to post title; if still empty, fall back to a related post's field" patterns without conditional template logic.

## Requirements

- WordPress 6.5+
- GenerateBlocks Pro (hard dependency — declared in plugin header)
- ACF or any plugin hooking `generateblocks_get_meta_pre_value` for custom-field reads (optional)

## Documentation

- [`docs/tag-matrix.md`](docs/tag-matrix.md) — current tag architecture, options, preview-label schema
- [`docs/deprecated-tags-options.md`](docs/deprecated-tags-options.md) — N×M historical reference + migration tracker
- [`docs/plugin-integration.md`](docs/plugin-integration.md) — external plugin API (`register_modifier()`, deprecated wrappers)
- [`docs/gb-constraints.md`](docs/gb-constraints.md) — GB editor/runtime constraint catalog
- [`CHANGELOG.md`](CHANGELOG.md) — version history
