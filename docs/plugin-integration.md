# Plugin Integration Guide

How external plugins integrate with the BWS Dynamic Tag Extensions source registry to provide custom-context dynamic tags.

## 1. Registering an External Source

The dynamic tags extension fires a hook after built-in sources are registered. External plugins hook into this to register their own source.

### Implement the source class

Extend `AbstractSource` (strongly preferred) rather than implementing `SourceInterface` directly — it provides sensible defaults for all methods you don't need to customize.

```php
use BWS\DynamicTags\AbstractSource;

class ExternalSource extends AbstractSource {

    public function get_source_key(): string {
        return 'external';                      // Becomes tag prefix: external_*
    }

    public function get_source_label(): string {
        return __( 'External Post', 'my-plugin' );
    }

    // get_context_type() defaults to 'post' via AbstractSource — no override needed.
    // format_id_for_acf() defaults to pass-through — no override needed for post entities.

    public function resolve_id( array $options, $instance ) {
        // Resolve to a WordPress post ID using your plugin's logic.
        $post_id = my_plugin_get_current_post_id( $options, $instance );
        return $post_id ? (int) $post_id : false;
    }

    public function get_source_options(): array {
        // Return any custom options that appear on every generated tag.
        return array(
            'my_option' => array(
                'type'        => 'text',
                'label'       => __( 'My Option', 'my-plugin' ),
                'help'        => __( 'Describe what this option does.', 'my-plugin' ),
                'placeholder' => 'example-value',
            ),
        );
    }
}
```

### Hook into the registry

There are two supported patterns. Use whichever fits your plugin's initialization flow.

**Pattern A — hook the action** (listener must be registered before `plugins_loaded` priority 20):

```php
// Add this at plugin file-load time, not inside any hook callback.
// bws_dynamic_tags_register_sources fires at plugins_loaded priority 20;
// the add_action() call must be reached before that hook fires.
add_action( 'bws_dynamic_tags_register_sources', function() {
    \BWS\DynamicTags\SourceRegistry::register_source( new ExternalSource() );
} );
```

**Pattern B — call `register_source()` directly** at `plugins_loaded` priority < 20:

```php
add_action( 'plugins_loaded', function() {
    \BWS\DynamicTags\SourceRegistry::register_source( new ExternalSource() );
}, 15 );
```

Pattern B is the safer choice if your plugin has complex initialization timing or if the
source may already be registered before the action fires. Both patterns are reflected
accurately in the debug log output.

The `$registry` argument passed to the action is available but not needed — `register_source()` is static.

### What happens automatically

Once registered via `SourceRegistry::register_source()`, the source is available for resolution in tag callbacks and deprecated wrapper registrations.

Base tags (`text`, `image`, `content`, `title`, `permalink`, `datetime_single`, `datetime_range`) are source-agnostic. Users select the source at the tag level using the **Source** dropdown in the GB editor. However, the built-in source dropdown only exposes sources wired into `bws_base_source_option()` — external sources registered via the hook do **not** automatically appear there.

To expose your source to GB editor users, use one of:

1. **Context modifier** — call `TagTemplateRegistry::register_modifier()` to create a prefixed tag group (`example_text`, `example_image`, etc.) backed by your source. See [§2 Registering a Context Modifier](#2-registering-a-context-modifier).
2. **Manual registration** — register individual GB tags directly and call your source's `resolve_id()` in the callback. See [§4 Plugin-Specific Tags](#4-plugin-specific-tags).
3. **Deprecated wrappers only** — if you only need backward-compat wrappers for legacy tag names, `register_source()` makes the source available to `DeprecatedTagRegistry` callbacks without creating any new GB tags. See [§7 Registering Deprecated Tag Wrappers](#7-registering-deprecated-tag-wrappers).

---

## 2. Registering a Context Modifier

A context modifier creates a prefixed group of GB tags (`example_text`, `example_image`, etc.) backed by a specific entity resolution strategy. The built-in `term_` modifier is registered this way; external plugins can register their own.

### Implement and register the source(s)

The modifier needs one registered source for direct entity resolution (`base_source_key`). The `src:ref` hop is handled generically off that base source (see the `traversal_source_key` note below) — no second traversal source class is needed as of 1.14.0:

```php
// Register on bws_dynamic_tags_register_sources (or plugins_loaded priority < 20).
add_action( 'bws_dynamic_tags_register_sources', function() {
    // Direct entity resolution (src unset = current context). This is base_source_key.
    \BWS\DynamicTags\SourceRegistry::register_source( new ExampleSource() );
    // The src:ref hop reads the `ref` relationship field on the base entity via a
    // generic step — no ExampleRelatedPostSource needed (1.14.0+).
} );
```

### Call `register_modifier()`

Call `TagTemplateRegistry::register_modifier()` on the `init` hook at priority 21 or later (after `bws_register_base_tags()` runs at priority 20, which populates `$modifier_templates`):

```php
add_action( 'init', function() {
    if ( ! class_exists( 'BWS\DynamicTags\TagTemplateRegistry' ) ) {
        return;
    }
    \BWS\DynamicTags\TagTemplateRegistry::register_modifier( array(
        'prefix'               => 'example',              // Produces example_text, example_image, etc.
        'gb_type'              => 'example-based',        // GB type string for all modifier tags.
        'modifier_label'       => 'example-based',        // Parenthetical in tag title: "Text Fields (example-based)".
        'base_source_key'      => 'example',              // Source key for unset src (direct resolution).
        'traversal_source_key' => '',                     // Accept-but-ignore (1.14.0+): src:ref hops generically off base_source_key. Omit or leave ''.
        'excluded_supports'    => array(),                // Omit to keep 'source' GB entity picker on all tags.
    ) );
}, 21 );
```

### What gets generated

`register_modifier()` iterates every template registered via `register_modifier_template()` and creates one GB tag per template: `{prefix}_{template_key}` (e.g. `example_text`, `example_image`, `example_title`).

Each modifier tag includes a **Source** selector with two entries: current entity (unset) and the traversal hop (`ref`). Traversal sub-options (`ref` field key + `srcTermIn` term-hop control) are included automatically via `bws_base_traversal_options()`. The `src:ref` entry reads the `ref` relationship field on the base entity and hops to the related post — no traversal source class needed (1.14.0+).

**`traversal_source_key` is accepted-but-ignored as of 1.14.0 (traversal pipeline).** The `src:ref` hop is now performed by a generic `ref` step off the modifier's `base_source_key` — the framework resolves your base entity via `base_source_key`, then reads the `ref` relationship field on it (via `bws_get_related_posts_data` for post bases, `bws_read_term_field` for term bases) and hops to the target post. **You no longer need a custom traversal source class.** Register only your `base_source_key` source; the `ref` step handles the relationship traversal generically.

`traversal_source_key` is still accepted (so existing registrations pass it without change) but the framework does not read it at render time — you may drop it from new registrations. A traversal source class you previously registered (e.g. `ExampleRelatedPostSource`) stays harmless if left registered, but is no longer invoked by the modifier callback. (Historical note: pre-1.14.0, a custom traversal source was required when the base entity came from a non-loop context — that resolution now lives in `base_source_key` alone, and the relationship hop is generic.)

### `register_modifier()` parameter reference

| Key | Type | Required | Notes |
|-----|------|----------|-------|
| `prefix` | string | Yes | Tag prefix. Produces `{prefix}_{template_key}` for each template. |
| `gb_type` | string | Yes | GB tag type string for all generated modifier tags (e.g. `'post'`, `'term'`). |
| `modifier_label` | string | — | Parenthetical appended to the tag title (e.g. `'term-based'`). Omit for no parenthetical. |
| `base_source_key` | string | Yes | Source registry key used when `src` is unset (direct resolution). |
| `traversal_source_key` | string | — | **Accept-but-ignore as of 1.14.0.** Previously the source key used for the `src:'ref'` hop; the hop is now a generic `ref` step off `base_source_key`, so this is no longer read at render time. Still accepted for back-compat (existing registrations need no change); may be omitted from new registrations. No custom traversal source class is required. |
| `excluded_supports` | array | — | GB supports to remove from modifier tags. Omit to keep all default supports. |

### Editor preview label registration

`bws_build_preview_label()` (in `includes/helpers/preview-helpers.php`) renders the bracketed placeholder shown in the editor when a tag can't resolve (e.g. `['related_posts' from Example Ref 'rel_post']`). To make your modifier prefix recognized by the preview label builder, hook the `bws_dynamic_tags_preview_modifier_map` filter and add your `prefix_ => Label` entry:

```php
add_filter( 'bws_dynamic_tags_preview_modifier_map', function ( $map ) {
    $map['example_'] = 'Example';
    return $map;
} );
```

Without this, your modifier tags still render normally — only the editor preview text drops the modifier segment. Built-in `term_` is registered internally; external prefixes must opt in via this filter.

For the preview-text schema itself (markers, assembly, warnings, per-template shapes), see [`editor-tag-previews.md`](editor-tag-previews.md).

---

## 3. SourceInterface Methods Reference

All methods below are available on `AbstractSource` with the listed defaults. Override only what your source needs to customize.

### Identity

| Method | Return | Default |
|--------|--------|---------|
| `get_source_key(): string` | Registry key + tag prefix | (abstract — must implement) |
| `get_source_label(): string` | Human label in admin UI | (abstract — must implement) |
| `get_tag_prefix(): string` | Tag name prefix | `get_source_key()` |
| `get_gb_type(): string` | GB tag type | `'post'` |
| `get_context_type(): string` | Which templates apply | `'post'` |

### Resolution

| Method | Return | Notes |
|--------|--------|-------|
| `resolve_id( array $options, $instance ): int\|false` | Entity ID | Override this (or legacy `resolve_post_id()`) |
| `format_id_for_acf( $id ): int\|string` | ACF object ID | Override when source resolves to a non-post entity. Post sources: pass-through. Term sources: return `"term_{$id}"`. User sources: return `"user_{$id}"`. |

### Default-enabled control

| Method | Return | Default | Notes |
|--------|--------|---------|-------|
| `source_default_enabled(): bool` | Source toggle on/off by default | `true` | Set to `false` for advanced/experimental sources where all tags should be opt-in. All built-in sources default to `true` as of v1.5.0. |
| `tag_default_enabled(): bool` | Per-tag on/off when source toggle is active | `source_default_enabled()` | Override independently when the source is opt-in but all its tags should be on once enabled. |

### Traversal

| Method | Return | Default | Notes |
|--------|--------|---------|-------|
| `needs_relationship_field(): bool` | Whether this source requires a `ref` option to resolve | `false` | Return `true` for traversal sources (e.g. `RelatedPost`, `TermRelatedPost`). `SecondRelatedPost` retained internally for deprecated wrappers only — not used in v1.6.0 base/modifier model. Signals the try-tag machinery to carry forward `$last_ref`. |
| `get_ui_group(): string` | Admin matrix group for this source | `get_context_type()` | Override when the source should appear in a different group than its context type. `TermRelatedPost` returns `'term'` even though its `context_type` is `'post'`. |

### Options

| Method | Return | Notes |
|--------|--------|-------|
| `get_source_options(): array` | Custom options on every tag | Used for options that apply to every tag the source generates (e.g. source identifier, fallback). Return `array()` if none. |
| `get_effective_source_id(): string` | try-tag source ID | `get_tag_prefix()` — override if you need a different key for try-tag slot assignment. |

---

## 4. Plugin-Specific Tags (No Built-in Template)

If your plugin needs a tag type with no equivalent built-in template, there are two options:

### Option A: Register a new modifier template (preferred)

Adding a template via `register_modifier_template()` makes it available to all modifier groups (`term_`, plus any external prefix registered via `register_modifier()`) — `register_modifier()` iterates registered modifier templates and produces one tag per (modifier × template) pair:

```php
// In your plugin, at init priority 15 (before bws_register_base_tags runs at 20):
add_action( 'init', function() {
    if ( ! class_exists( 'BWS\DynamicTags\TagTemplateRegistry' ) ) {
        return;
    }
    \BWS\DynamicTags\TagTemplateRegistry::register_modifier_template( array(
        'key'           => 'my_field',          // Appended to modifier prefix → term_my_field, example_my_field
        'title'         => 'My Field',           // Modifier label appended in GB tag picker
        'gb_type'       => null,                 // null = inherit modifier's gb_type
        'supports'      => array(),              // Base tags use custom 'src' option, not GB native 'source' support
        'options'       => array(),              // Or a callable returning option definitions
        'core_fn'       => 'my_plugin_my_field_core',
        'context_types' => array( 'post' ),
    ) );
}, 15 );
```

### Option B: Manual GB registration

For truly one-off tags, register directly with GenerateBlocks. No admin toggle is available for manually registered tags — they are always active:

```php
add_action( 'init', function() {
    if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
        return;
    }
    new GenerateBlocks_Register_Dynamic_Tag( array(
        'title'    => __( 'External Unique Field', 'my-plugin' ),
        'tag'      => 'external_unique_field',
        'type'     => 'post',
        'supports' => array(),                 // Always include supports — never omit
        'options'  => array(),
        'return'   => 'my_plugin_unique_field_callback',
    ) );
}, 20 );
```

> **Important**: Always include `'supports' => array()` even when empty. Omitting it causes a PHP 8 "Undefined array key" warning on every tag render.

### Callbacks for plugin-specific tags

```php
function my_plugin_unique_field_callback( $options, $block, $instance ) {
    $source  = \BWS\DynamicTags\SourceRegistry::get_source( 'external' );
    $post_id = $source ? $source->resolve_id( $options, $instance ) : false;

    if ( ! $post_id ) {
        return '';
    }

    $value = get_post_meta( $post_id, 'unique_field_key', true );
    if ( empty( $value ) ) {
        return '';
    }

    return GenerateBlocks_Dynamic_Tag_Callbacks::output( $value, $options, $instance );
}
```

### Tags with ACF relationship fields

If a tag needs to traverse an ACF relationship/post_object field, prefer the modifier path (`register_modifier()` with a `traversal_source_key`) — the framework handles `src:ref` + `ref` sub-option wiring. For one-off manually registered tags, merge `bws_base_traversal_options()` into options and read `$options['ref']`:

```php
// Registration: merge canonical src + ref options.
'options' => array_merge( bws_base_traversal_options(), $your_other_options ),
'supports' => array(),

// Callback: read ref field key from $options['ref'].
$ref_field_key = $options['ref'] ?? '';
if ( empty( $ref_field_key ) ) {
    return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
}
$related_posts = bws_get_related_posts_data( $post_id, $ref_field_key );
```

The legacy `bws_get_relationship_field_options()` / `bws_get_second_relationship_field_options()` helpers (which emit `rel` / `rel_2` keys) are retained for deprecated wrapper internals only. Do not use them in new tags.

### Tags that render post content

For tags that render full block content from a post, use the processing pipeline and safe output helper:

```php
function my_plugin_content_callback( $options, $block, $instance ) {
    $source  = \BWS\DynamicTags\SourceRegistry::get_source( 'external' );
    $post_id = $source ? $source->resolve_id( $options, $instance ) : false;

    if ( ! $post_id ) {
        return '';
    }

    // Skip during GB query loop setup phase (postId not yet in context).
    if ( bws_is_query_loop_setup_phase( $instance ) ) {
        return '';
    }

    $content = bws_process_post_content( $post_id );

    if ( empty( $content ) ) {
        return '';
    }

    // bws_safe_content_output strips destructive GB options (trunc, case, link, wpautop)
    // before passing to GB's output(), preventing HTML corruption.
    return bws_safe_content_output( $content, $options, $instance );
}
```

---

## 5. Shared Helpers Available

These functions are available once `bws-gb-dynamic-tags-extensions` is active. All are guarded with `function_exists()` so they won't conflict.

### Image helpers (`includes/helpers/image-helpers.php`)

| Function | Purpose |
|----------|---------|
| `bws_get_attachment_data( $id, $return_type, $size )` | Get attachment data by ID |
| `bws_get_meta_image_data( $post_id, $field_key, $return_type, $size )` | Get image from post meta/ACF |
| `bws_process_meta_image_value( $value, $return_type, $size )` | Normalize ACF image return formats |
| `bws_process_acf_icon_picker( $value, $return_type )` | Handle ACF icon picker fields |
| `bws_handle_dashicon_value( $value, $return_type )` | Handle WordPress Dashicon values |
| `bws_get_attachment_id_from_url( $url )` | Reverse-lookup attachment ID from URL |
| `bws_handle_media_fallback( $options, $instance, $return_type, $size )` | Media selector fallback logic |
| `bws_get_image_return_type_options()` | Standard return type option (url/id/alt/caption) |
| `bws_get_meta_image_options()` | Field key + return type options for image tags |

### Field helpers (`includes/helpers/field-helpers.php`)

| Function | Purpose |
|----------|---------|
| `bws_get_related_posts_data( $post_id, $field_key )` | ACF relationship/post_object field resolution |
| `bws_extract_post_id( $post_data )` | Extract post ID from various ACF return formats |
| `bws_is_valid_meta_key( $meta_key )` | Validate meta key format |
| `bws_read_field( $post_id, $key )` | Canonical post-meta/ACF read (routes through `GenerateBlocks_Meta_Handler`) |
| `bws_read_term_field( $term_id, $key )` | Canonical term-meta/ACF read |
| `bws_get_loop_row_context()` | Detect GB list-block loop row (Mode 2a post, Mode 2b flat repeater) |

### Preview helpers (`includes/helpers/preview-helpers.php`)

| Function | Purpose |
|----------|---------|
| `bws_build_preview_label( $options, $tag, $modifier_prefix )` | Build bracketed editor preview label for unresolved tags |
| `bws_build_try_preview_label( $options, $tag, $modifier_prefix )` | Build preview label for try_* fallback chains |
| `bws_wrap_preview_label_with_link( $label, $options, $instance )` | Wrap preview label in `<a>` when `linkTo` resolves |

### Link helpers (`includes/helpers/link-helpers.php`)

| Function | Purpose |
|----------|---------|
| `bws_resolve_link_url( $options, $instance )` | Resolve `linkTo` / `linkKey` to URL |
| `bws_wrap_with_link( $content, $url, $options )` | Wrap content in `<a href>` |
| `bws_get_link_options()` | Standard `linkTo` + `linkKey` option block |

### Content helpers (`includes/helpers/content-helpers.php`)

Procedural API. Most are thin wrappers over `\BWS\DynamicTags\Content\ContentProcessor` (see `includes/classes/content/class-content-processor.php`).

#### Relationship field options

| Function | Purpose |
|----------|---------|
| `bws_get_relationship_field_options()` | **Deprecated-internal only.** Returns `rel` option block; used by `SecondRelatedPost` + deprecated wrapper registrations. New tags should use `bws_base_traversal_options()` (`ref` key). |
| `bws_get_second_relationship_field_options()` | **Deprecated-internal only.** Returns `rel_2` option block for `SecondRelatedPost` second hop. No replacement in v1.6.0 base/modifier model. |
| `bws_base_traversal_options()` | Returns canonical `src` + `ref` + `srcTermIn` option block. Use in modifier registrations and any manually registered tag that needs traversal. |

#### Post content processing pipeline

| Function | Purpose |
|----------|---------|
| `bws_render_block_content( $raw, $cache_key, $args )` | **(new, v1.8.0)** Generic render entry. Use when content isn't a `post_content` fetch (e.g. wp_options-stored markup). Stack keys on `$cache_key` (convention: `'post:'.$id` or `'option:'.$key`). |
| `bws_process_post_content( $post_id, $args )` | Post-content render. Fetches raw and delegates to `bws_render_block_content( $raw, 'post:'.$post_id, $args )`. Returns empty string on failure. |
| `bws_safe_content_output( $content, $options, $instance )` | Strips destructive GB options (`trunc`, `case`, `link`, `wpautop`) before calling `output()`. Always use this for rendered HTML. |
| `bws_can_process_post_content( $post_id )` | Returns `true` if post can be processed (not in stack, depth below cap). |
| `bws_is_query_loop_setup_phase( $instance )` | Returns `true` when GB is setting up a query loop and `postId` is not yet in context. Skip content rendering in this case. |
| `bws_has_sufficient_memory()` | Returns `true` when memory usage is below `bws_content_memory_threshold` (default 0.80) of the PHP limit. |
| `bws_sanitize_rich_content( $content )` | Safe HTML sanitization for displayed content. |

#### Filters

| Filter | Default | Purpose |
|--------|---------|---------|
| `bws_content_memory_threshold` | `0.80` | (v1.8.0) Memory fraction (0.0–1.0) below which the primary render path runs. At or above, callers fall back to a CSS-extraction-only pipeline. |
| `bws_content_max_recursion_depth` | `3` | (v1.8.0) Maximum content stack depth before further pushes are blocked. |
| `generateblocks_dynamic_tags_allowed_options` | GB-parity seed | (v1.9.0) Allowlist of wp_options keys the `src:site` source may read (site option key-mode, site `linkTo:key`, datetime `get_field(…,'option')`). We mirror GB Pro's `get_option` seed exactly: 6 WP defaults (`siteurl`, `blogname`, `blogdescription`, `home`, `time_format`, `user_count`) **plus every registered ACF options-page field** (`array_keys(GenerateBlocks_Pro_Dynamic_Tags_ACF::get_instance()->get_acf_option_fields())` — registration is the opt-in). Same filter name as GB Pro, re-applied by our resolver because `Meta_Handler::get_option()` does not enforce it. Use this filter only to add *non-ACF, non-default* keys. See [§Exposing site option keys](#exposing-site-option-keys-srcsite) and [`docs/adr/0001-site-option-read-allowlist.md`](adr/0001-site-option-read-allowlist.md). |

#### Exposing site option keys (`src:site`)

ACF options-page fields and the six common WP options read out of the box (GB-parity seed) — no filter needed. The filter is only for **arbitrary non-ACF wp_options keys** you want a tag to read. Discoverable site data (title, tagline, URLs, logo) is reached via the named `use:` values instead and needs no key or filter.

```php
// Only needed for a plain wp_options key that is NOT an ACF options field
// and NOT one of the six defaults.
add_filter(
    'generateblocks_dynamic_tags_allowed_options',
    static function ( array $allowed ): array {
        return array_merge( $allowed, [ 'my_plugin_settings' ] );
    }
);
// Now {{text src:site|key:my_plugin_settings.support_email}} resolves.
// (An ACF options-page field like acf_options_event_date already resolves
//  with no filter: {{datetime_single src:site|key:acf_options_event_date}}.)
```

For wp_options arrays, the **root** key is what's allowlisted (`my_plugin_settings`); dot-path subkeys (`.support_email`) traverse within the allowed root. ACF options-page field keys are flat — the whole key is the root. Note there is no `use:option` — `src:site` plus a `key` (with no named `use:` value) is the option read.

### Registration helpers (`includes/helpers/registration-helpers.php`)

| Function | Purpose |
|----------|---------|
| `bws_strip_default_select_values( $options )` | Wire-format helper: flip first option value of `_strip_default`-flagged select options to `''` before GB registration. Called by tag-template registry and base-tag registration. |

### Date helpers (`includes/helpers/datetime-helpers.php`)

| Function | Purpose |
|----------|---------|
| `bws_parse_acf_date_value()` | Parse ACF date values with timezone handling |
| `bws_format_single_date_time()` | Format single date/time with year omission, smart time |
| `bws_format_date_range()` | Smart range formatting with redundancy removal |
| `bws_format_time_range()` | Time range with AM/PM consolidation |
| `bws_handle_date_time_fallback()` | Fallback text handling for date tags |

### Taxonomy helpers (`includes/helpers/taxonomy-helpers.php`)

| Function | Purpose |
|----------|---------|
| `bws_reliable_term_context_detection( $options )` | Multi-fallback term ID detection (archive, loop, option) |
| `bws_get_validated_term( $term_id )` | Validate and retrieve WP_Term object |
| `bws_get_term_field_image_data( $term_id, $taxonomy, $field_key, $return_type, $size )` | Image from term ACF/meta field |
| `bws_get_terms_for_post( $post_id, $options )` | Returns `WP_Term[]` in taxonomy from `$options['tax']` (legacy `taxonomy` accepted as fallback) |
| `bws_post_term_extraction_options()` | Standard `tax` + `fallback` options for post-context term templates |
| `bws_post_term_image_options()` | `tax` + `key` options for post-context term image templates |

---

## 6. Admin Settings Integration

The settings page (v1.6.0+) exposes:

- **Modifier group toggles** — `term_` and `try_` can be enabled/disabled. Modifier groups registered by external plugins via `register_modifier()` do not currently appear in the settings page UI unless you add your own settings integration.
- **Deprecated wrapper toggles** — each entry in `DeprecatedTagRegistry` gets an individual enable/disable checkbox. Use `SettingsPage::is_deprecated_tag_enabled( $tag_name )` to read the current state in your deprecated tag's registration code.

Base tags and manually registered custom tags (Option B) have no admin toggle — they are always active.

Settings are stored in `bws_dynamic_tags_settings`.

---

## 7. Registering Deprecated Tag Wrappers

When an external plugin renames or retires a tag (e.g. an old per-source tag `oldname_post_meta` being replaced by the base `text` tag), the old name must continue to work in existing content. Use `DeprecatedTagRegistry` to register backward-compatible wrappers that forward to the new implementation.

### Register a deprecated wrapper

Call `DeprecatedTagRegistry::register()` on the `bws_dynamic_tags_register_sources` hook
(or any hook before `init` priority 20, which is when tags are registered):

```php
add_action( 'bws_dynamic_tags_register_sources', function () {
    \BWS\DynamicTags\DeprecatedTagRegistry::register( array(
        'old_tag'        => 'oldname_post_meta',          // Deprecated GB tag name
        'new_tag'        => 'text',                       // Base tag replacement
        'title'          => 'Oldname Post Meta',          // GB editor title
        'supports'       => array( 'source' ),
        'options'        => oldname_get_text_options(),   // Optional — omit if no options
        'callback'       => 'oldname_deprecated_post_meta_callback',
        'since'          => '2.0.0',                  // Version when old tag was deprecated
        'source_inject'  => '',                       // Inject source option on convert; '' = omit
        'option_renames' => array( 'field_key' => 'key' ), // Old key → new key
        'value_renames'  => array(),                  // Post-rename key → [old value => new value]
        'fixed_options'  => array(),                  // Always-injected key/value pairs on convert
    ) );
} );
```

### Write the callback

Your callback must emit a deprecation notice and delegate to the new implementation:

```php
function oldname_deprecated_post_meta_callback( $options, $block, $instance ) {
    // Emits _doing_it_wrong() when WP_DEBUG is enabled.
    bws_deprecated_tag_notice( 'oldname_post_meta', 'text', '2.0.0' );

    $source = \BWS\DynamicTags\SourceRegistry::get_source( 'oldname' );
    $id     = $source ? $source->resolve_id( $options, $instance ) : false;

    return oldname_text_core( $id, $options, $instance );
}
```

### What happens automatically

- The old tag is registered with GenerateBlocks so existing content continues to render.
- The old tag appears in the deprecated section of the admin settings page with an individual enable/disable toggle.
- `bws_deprecated_tag_notice()` fires a `_doing_it_wrong()` notice when `WP_DEBUG` is
  enabled, prompting developers to update their templates.
- When a `new_tag` and migration fields (`option_renames`, `fixed_options`, etc.) are configured, the **Convert** button on the settings page rewrites matching tag strings in post content.

### `DeprecatedTagRegistry::register()` parameter reference

| Key | Type | Required | Notes |
|-----|------|----------|-------|
| `old_tag` | string | Yes | The deprecated GB tag name. |
| `new_tag` | string | Yes | Replacement tag name — used in the deprecation notice and as the target tag name in `transform_options()`. |
| `title` | string | — | GB editor title. Defaults to `old_tag` if omitted. |
| `gb_type` | string | — | GB tag type. Always overwritten to `'deprecated'` internally. |
| `supports` | array | — | GB supports array. Defaults to `[]`. |
| `options` | array | — | GB options array. Omit if no options. |
| `callback` | callable\|string | Yes | PHP callable that handles tag output. |
| `since` | string | — | Version string passed to `bws_deprecated_tag_notice()`. |
| `description` | string | — | Overrides the auto-generated GB tag description. Auto-default: `'Deprecated — use "new_tag" instead.'` |
| `source_inject` | string | — | `src` option value injected on conversion (e.g. `'ref'` for a related-post traversal source). Empty string omits the `src` option. |
| `option_renames` | array | — | Map of old option key → new option key applied before serialization. E.g. `['field_key' => 'key']`. |
| `value_renames` | array | — | Map of (post-rename) option key → `[old value => new value]`. Applied after `option_renames`. |
| `fixed_options` | array | — | Key/value pairs always injected during conversion regardless of user options. E.g. `['use' => 'excerpt']`. |
| `datetime_transforms` | bool | — | When `true`, apply the five special-case datetime option transforms during conversion. Default `false`. |

---

## 8. Renaming a Modifier Prefix

When an external plugin renames its context modifier prefix (e.g., from `oldname_` to `newname_`), existing post content still contains the old tag names. The converter handles migration: for each old tag name that maps to a new one, register a deprecated wrapper and the **Convert** button will rewrite stored tags.

### Pattern

For each template your modifier generates, register one deprecated wrapper mapping the old prefixed name to the new prefixed name:

```php
add_action( 'bws_dynamic_tags_register_sources', function () {
    $old_templates = array( 'text', 'image', 'content', 'title', 'permalink',
                            'datetime_single', 'datetime_range' );

    foreach ( $old_templates as $tpl ) {
        \BWS\DynamicTags\DeprecatedTagRegistry::register( array(
            'old_tag'  => 'oldname_' . $tpl,   // Old tag name in stored content
            'new_tag'  => 'newname_' . $tpl,   // New tag name after conversion
            'title'    => 'Oldname ' . ucfirst( $tpl ) . ' (Deprecated)',
            'supports' => array(),
            'callback' => 'my_plugin_passthrough_callback',
            'since'    => '3.0.0',
            // option_renames / fixed_options if any option names also changed
        ) );
    }
} );
```

The passthrough callback resolves via the **new** modifier's source so the old tag continues to render while the migration is pending:

```php
function my_plugin_passthrough_callback( $options, $block, $instance ) {
    bws_deprecated_tag_notice( 'oldname_text', 'newname_text', '3.0.0' );

    $source = \BWS\DynamicTags\SourceRegistry::get_source( 'newname' );
    $id     = $source ? $source->resolve_id( $options, $instance ) : false;

    return newname_text_core( $id, $options, $instance );
}
```

### Converter behavior

The admin **Migration Tool** (separate section on the settings page) scans all non-revision posts for any deprecated tag matching a registered `old_tag`. The results table shows post title + type + per-row issue list (deprecated tags + option migrations). Per-row **Migrate** rewrites stored content via `MigrationRegistry::transform_tag()` — applying `option_renames`, `value_renames`, `combine_options`, `source_inject`, `fixed_options`, `datetime_transforms` in the documented order. **Bulk Migrate Selected** processes the checked rows in sequence with a progress bar.

Posts whose content does not change after transformation are not rewritten. Each migrated post gets a pre-migration `wp_save_post_revision()` snapshot so changes are reversible.

Migration available only for deprecated entries that declare `new_tag` plus at least one of `source_inject`, `option_renames`, or `fixed_options`. `DeprecatedTagRegistry::has_migration_path( $old_tag )` returns whether a path exists.
