# Site option reads gated by a GB-Pro-parity allowlist

**Status:** accepted (v1.9.0 `src:site`). Supersedes the original "empty-seed" formulation — see [Correction](#correction-empty-seed-was-a-misread-of-gb-pro).

`src:site` lets tags read `wp_options` (site option key-mode, site `linkTo:key`, and ACF options-page date fields via `get_field(…, 'option')`). We gate every such read through `apply_filters( 'generateblocks_dynamic_tags_allowed_options', $seed )` before fetching, where `$seed` **mirrors GB Pro's `get_option` callback exactly**. A key renders only if its root segment is in the seed or added by the filter.

## The seed (matches GB Pro `class-register.php:268-291`)

```php
$seed = [ 'siteurl', 'blogname', 'blogdescription', 'home', 'time_format', 'user_count' ];

// Every registered ACF options-page field — registration IS the opt-in.
if ( class_exists( 'GenerateBlocks_Pro_Dynamic_Tags_ACF' ) ) {
    $seed = array_merge(
        $seed,
        array_keys( GenerateBlocks_Pro_Dynamic_Tags_ACF::get_instance()->get_acf_option_fields() )
    );
}

$allowed = apply_filters( 'generateblocks_dynamic_tags_allowed_options', $seed );
```

So out of the box: the six common WP options **and every ACF options-page field** read without any manual filter. Arbitrary *non-ACF, non-default* wp_options keys still require an explicit `add_filter`.

## Why a gate at all

- **Options are global; an open read surface is information disclosure.** Unlike post meta (entity-scoped), `wp_options` holds settings from every plugin, some sensitive. An ungated read (even into an `href` via `linkTo:key`) would leak them. The allowlist keeps the *arbitrary-key* surface closed.
- **But ACF-registered option fields are already intentional surface.** A developer who registers an ACF field on an options page has declared that data renderable; GB Pro treats registration as the opt-in, and we match that so `src:site` is not gratuitously stricter than the `{{option}}` tag it replaces.

## The trap this records (most important)

`GenerateBlocks_Meta_Handler::get_option()` does **not** enforce the allowlist — it only applies a *blocklist* (`DISALLOWED_KEYS`: passwords, activation keys). The `generateblocks_dynamic_tags_allowed_options` filter lives in GB Pro's `get_option` *callback* (`class-register.php:268-296`), upstream of the handler call. **Calling the handler directly skips the allowlist entirely.** Therefore the gate is OUR resolver's responsibility, applied before the handler/`get_field` call — never delegated to the handler. A maintainer who "simplifies" by relying on the handler to gate would silently open every non-blocklisted option.

## Consequences

- The same gate (`bws_site_allowlist_ok()`, `includes/tags/base-tags.php`) applies to all three site option-read paths, identically: site option key-mode (`bws_site_resolve_value()`, text/permalink/image/content), site `linkTo:key` (`bws_resolve_link_url()` site branch, `includes/helpers/link-helpers.php`), and datetime `get_field($key, 'option')` (`bws_read_field()` `'option'` branch, `includes/helpers/field-helpers.php`). Option reads are option reads regardless of which control triggers them.
- A user migrating from GB Pro `{{option key:blogname}}` who writes `{{text src:site|key:blogname}}` now gets the same result — `blogname` is in the parity seed. (Common site data is still better fetched via the named `use:` values — `use:title` etc. — which need no key and no allowlist.)
- GB Pro is a hard dependency, so the filter name and the `GenerateBlocks_Pro_Dynamic_Tags_ACF` class are guaranteed present. We re-`apply_filters` the same filter and re-derive the same ACF seed rather than reading Pro's private state, so our gate stays in lockstep with Pro's.
- Enforcement lives in code (the seed + `apply_filters` gate) + an `@invariant` PHPDoc on `bws_site_allowlist_ok()`. This ADR explains *why* the gate exists, and why the seed is GB-parity rather than empty, so neither is removed.

## Correction: empty seed was a misread of GB Pro

The original version of this ADR specified an **empty** seed ("a pure escape hatch; a dev opts each key in"), believing GB Pro's allowlist was a small static default we were deliberately tightening. That was based on a secondhand reading of `class-register.php` that missed the `array_merge( $allowed_options, $acf_keys )` — GB Pro **auto-allows every registered ACF options-page field**. The empty seed therefore blocked all ACF option fields, and `{{text src:site|key:<acf_options_field>}}` returned empty on the front-end and in the editor (SPEC §B2). Verified against GB Pro 2.6.0-beta.2 source. The seed is now GB-parity; "empty seed" is retired.
