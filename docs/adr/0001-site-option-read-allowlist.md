# Site option reads gated by our own empty-seed allowlist

**Status:** accepted (planned for v1.9.0 `src:site`)

`src:site` lets tags read arbitrary `wp_options` (`use:option`, site `linkTo:key`, and ACF options-page date fields via `get_field(…, 'option')`). We gate every such read through our own `apply_filters( 'generateblocks_dynamic_tags_allowed_options', [] )` check — **seeded empty** — before fetching. A key renders only if a site developer explicitly adds it to that filter. This deliberately diverges from GB Pro's `{{option}}` tag, which ships a small default allowlist (`blogname`, `blogdescription`, `home`, `time_format`, `user_count`) and so renders those keys out of the box.

## Why

- **Discoverable site data does not go through raw keys.** Title, tagline, URLs, and logo are exposed as `use:` enum values backed by WordPress functions (`get_bloginfo`, `home_url`, `get_theme_mod`). The option-key path is a *pure escape hatch* for arbitrary stored options, not the way to fetch common site data. So it needs no convenience defaults — `use:title` is the answer for the site title, not `use:option|key:blogname`.
- **Options are global; an open read surface is information disclosure.** Unlike post meta (entity-scoped), `wp_options` holds settings from every plugin, some sensitive. An ungated read (even into an `href` via `linkTo:key`) leaks them. Empty-seed + explicit opt-in keeps the surface closed by default.

## The trap this records (most important)

`GenerateBlocks_Meta_Handler::get_option()` does **not** enforce the allowlist — it only applies a *blocklist* (`DISALLOWED_KEYS`: passwords, activation keys). The `generateblocks_dynamic_tags_allowed_options` filter lives in GB Pro's `get_post_option` *callback* (`class-register.php:288-296`), upstream of the handler call. **Calling the handler directly skips the allowlist entirely.** Therefore the gate is OUR resolver's responsibility, applied before the handler/`get_field` call — never delegated to the handler. A future maintainer who "simplifies" by relying on the handler to gate would silently open every non-blocklisted option.

## Consequences

- The same gate applies to all three site option-read paths, identically: `use:option` (text/permalink/image/content), site `linkTo:key`, and datetime `get_field($key, 'option')`. Option reads are option reads regardless of which control triggers them.
- A user migrating from GB Pro `{{option key:blogname}}` who writes `{{text src:site|use:option|key:blogname}}` gets empty output until they filter `blogname` in — deliberate; steer them to `use:title`.
- GB Pro is a hard dependency, so the filter name is guaranteed to exist even though we re-`apply_filters` it ourselves (we do not read Pro's private `$allowed_options` array).
- Enforcement lives in code (the `apply_filters` gate) + an `@invariant` PHPDoc on the site resolver. The gate is the `bws_site_allowlist_ok()` helper (`includes/tags/base-tags.php`); all three paths call it — `bws_site_resolve_value()` (use:option), `bws_resolve_link_url()` site `key` branch (`includes/helpers/link-helpers.php`), and `bws_read_field()` `'option'` branch (`includes/helpers/field-helpers.php`). This ADR explains *why* the gate exists so it is not removed.
