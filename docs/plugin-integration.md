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

Once registered, `TagTemplateRegistry::generate_all_tags()` iterates every enabled source against every template. Because `ExternalSource::get_context_type()` returns `'post'`, all post-context templates automatically produce external-prefixed tags:

**Content tags** (text/link type):
- `external_title`, `external_excerpt`, `external_permalink`, `external_custom_text`
- `external_post_content` (full rendered block content)

**Image tags** (media type):
- `external_featured_image`, `external_custom_image`

**Date/time tags** (opt-in by default):
- `external_custom_date_single`, `external_custom_date_range`
- `external_custom_datetime_single`, `external_custom_datetime_range`

**Term-extraction tags** (post-context term data):
- `external_term_title`, `external_term_permalink`, `external_term_description`
- `external_term_custom_text`, `external_term_custom_image`

… and any future templates added to the registry.

No manual `GenerateBlocks_Register_Dynamic_Tag` calls are needed for these standard tags.

The external source also:
- Appears in the admin settings page under its own section
- Has its tags individually enabled/disabled per the opt-in/opt-out model
- Respects any `get_source_options()` values on every generated tag

---

## 2. SourceInterface Methods Reference

All methods below are available on `AbstractSource` with the listed defaults. Override only what your source needs to customize.

### Identity

| Method | Return | Default |
|--------|--------|---------|
| `get_source_key(): string` | Registry key + tag prefix | (abstract — must implement) |
| `get_source_label(): string` | Human label in admin UI | (abstract — must implement) |
| `get_tag_prefix(): string` | Tag name prefix | `get_source_key()` |
| `get_title_prefix(): string` | Tag title prefix in GB | `get_source_label()` |
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
| `needs_relationship_field(): bool` | Whether this source requires a `rel` option to resolve | `false` | Return `true` for traversal sources (e.g. `RelatedPost`, `TermRelatedPost`, `SecondRelatedPost`). Signals the try-tag machinery to carry forward `$last_rel`. |
| `get_ui_group(): string` | Admin matrix group for this source | `get_context_type()` | Override when the source should appear in a different group than its context type. `TermRelatedPost` returns `'term'` even though its `context_type` is `'post'`. |

### Options

| Method | Return | Notes |
|--------|--------|-------|
| `get_source_options(): array` | Custom options on every tag | Used for options that apply to every tag the source generates (e.g. source identifier, fallback). Return `array()` if none. |
| `get_effective_source_id(): string` | try-tag source ID | `get_tag_prefix()` — override if you need a different key for try-tag slot assignment. |

---

## 3. Plugin-Specific Tags (No Built-in Template)

If your plugin needs a tag type with no equivalent built-in template, there are two options:

### Option A: Register a new template (preferred)

Adding a template to `TagTemplateRegistry` auto-generates the tag for all sources, including yours:

```php
// In your plugin, at init priority 15 (before generate_all_tags runs at 20):
add_action( 'init', function() {
    if ( ! class_exists( 'BWS\DynamicTags\TagTemplateRegistry' ) ) {
        return;
    }
    \BWS\DynamicTags\TagTemplateRegistry::register_template( array(
        'key'           => 'my_field',          // Appended to source prefix → external_my_field
        'title'         => 'My Field',           // Prepended with source label in GB
        'gb_type'       => null,                 // null = use source's gb_type ('post')
        'supports'      => array( 'source' ),
        'options_fn'    => 'my_plugin_get_my_field_options',
        'core_fn'       => 'my_plugin_my_field_core',
        'context_types' => array( 'post' ),
        'supports_try'  => false,
    ) );
}, 15 );
```

### Option B: Manual GB registration

For truly one-off tags, register directly with GenerateBlocks. Use `SettingsPage::is_tag_enabled()` so the tag respects admin toggles:

```php
use BWS\DynamicTags\Admin\SettingsPage;

add_action( 'init', function() {
    if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
        return;
    }
    if ( SettingsPage::is_tag_enabled( 'external_unique_field', 'external' ) ) {
        new GenerateBlocks_Register_Dynamic_Tag( array(
            'title'    => __( 'External Unique Field', 'my-plugin' ),
            'tag'      => 'external_unique_field',
            'type'     => 'post',
            'supports' => array(),                 // Always include supports — never omit
            'options'  => array(),
            'return'   => 'my_plugin_unique_field_callback',
        ) );
    }
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

If a tag needs to traverse an ACF relationship/post_object field, use the standard `rel` option pattern:

```php
// Registration: merge the standard rel option into your options.
'options' => array_merge( bws_get_relationship_field_options(), $your_other_options ),
'supports' => array(),

// Callback: read the relationship field key from $options['rel'].
$rel_field_key = $options['rel'] ?? '';
if ( empty( $rel_field_key ) ) {
    return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
}
$related_posts = bws_get_related_posts_data( $post_id, $rel_field_key );
```

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

## 4. Shared Helpers Available

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

### Content helpers (`includes/helpers/content-helpers.php`)

#### Data retrieval

| Function | Purpose |
|----------|---------|
| `bws_get_related_posts_data( $post_id, $field_key )` | ACF relationship/post_object field resolution |
| `bws_extract_post_id( $post_data )` | Extract post ID from various ACF return formats |
| `bws_extract_text_field( $post_id, $field_name )` | Get text content from post fields or meta |
| `bws_is_valid_meta_key( $meta_key )` | Validate meta key format |
| `bws_sanitize_rich_content( $content )` | Safe HTML sanitization for displayed content |

#### Relationship field options

| Function | Purpose |
|----------|---------|
| `bws_get_relationship_field_options()` | Returns `rel` option for first-hop ACF relationship field. |
| `bws_get_second_relationship_field_options()` | Returns `rel_2` option for second-hop ACF relationship field. |

#### Post content processing pipeline

| Function | Purpose |
|----------|---------|
| `bws_process_post_content( $post_id, $args )` | Full render pipeline: validates post → recursion guard → memory check → `do_blocks()` → `wpautop()` → sanitize. Returns empty string on failure. |
| `bws_safe_content_output( $content, $options, $instance )` | Strips destructive GB options (`trunc`, `case`, `link`, `wpautop`) before calling `output()`. Always use this for rendered HTML. |
| `bws_can_process_post_content( $post_id )` | Returns `true` if post can be processed (not in stack, sufficient memory). |
| `bws_is_query_loop_setup_phase( $instance )` | Returns `true` when GB is setting up a query loop and `postId` is not yet in context. Skip content rendering in this case. |
| `bws_has_sufficient_memory()` | Returns `true` when memory usage is below 80% of the PHP limit. |

### Date helpers (`includes/helpers/date-helpers.php`)

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
| `bws_get_terms_for_post( $post_id, $options )` | Returns `WP_Term[]` in taxonomy from `$options['taxonomy']` |
| `bws_post_term_extraction_options()` | Standard `taxonomy` + `fallback_text` options for post-context term templates |
| `bws_post_term_image_options()` | `taxonomy` + `field_key` options for post-context term image templates |

---

## 5. Admin Settings Integration

Plugin tags automatically appear in the settings page when:
1. The source is registered via the hook
2. Auto-generated tags respect the per-tag toggles without any additional code
3. Manually registered tags use `SettingsPage::is_tag_enabled( 'tag_name', 'source_key' )` to check the toggle

**Default-enabled behaviour:**
- The source toggle defaults to `source_default_enabled()` on the source class (`true` for all built-in sources as of v1.5.0).
- Individual tag defaults within an enabled source are controlled by `tag_default_enabled()` (defaults to `source_default_enabled()`). Override `tag_default_enabled()` independently when the source should be opt-in but all its tags should be on once enabled.
- Individual templates can override the per-tag default via `default_enabled_map` in the template definition.

Settings are stored in `bws_dynamic_tags_settings` under the `'tags'` key.

---

## 6. Registering Deprecated Tag Wrappers

When an external plugin renames a tag (e.g. `portal_post_meta` → `portal_custom_text`),
the old name must continue to work in existing content. Use `DeprecatedTagRegistry` to
register backward-compatible wrappers that forward to the new implementation.

### Register a deprecated wrapper

Call `DeprecatedTagRegistry::register()` on the `bws_dynamic_tags_register_sources` hook
(or any hook before `init` priority 20, which is when tags are registered):

```php
add_action( 'bws_dynamic_tags_register_sources', function () {
    \BWS\DynamicTags\DeprecatedTagRegistry::register( array(
        'old_tag'    => 'portal_post_meta',       // Deprecated GB tag name
        'new_tag'    => 'portal_custom_text',      // Replacement (shown in notice only)
        'source_key' => 'portal',                  // Must match a registered SourceInterface key
        'title'      => 'Portal Post Meta',        // GB editor title
        'gb_type'    => 'post',                    // GB tag type
        'supports'   => array( 'source' ),
        'options'    => portal_get_text_options(),  // Optional — omit if no options
        'callback'   => 'portal_deprecated_post_meta_callback',
        'is_related' => false,                     // true = appears under related_tags in UI
        'since'      => '2.0.0',                   // Version when old tag was deprecated
    ) );
} );
```

### Write the callback

Your callback must emit a deprecation notice and delegate to the new implementation:

```php
function portal_deprecated_post_meta_callback( $options, $block, $instance ) {
    // Emits _doing_it_wrong() when WP_DEBUG is enabled.
    bws_deprecated_tag_notice( 'portal_post_meta', 'portal_custom_text', '2.0.0' );

    $source = \BWS\DynamicTags\SourceRegistry::get_source( 'portal' );
    $id     = $source ? $source->resolve_id( $options, $instance ) : false;

    return portal_custom_text_core( $id, $options, $instance );
}
```

### What happens automatically

- The old tag is registered with GenerateBlocks so existing content continues to render.
- The old tag appears in the admin settings page under the `portal` source group with a
  "(Deprecated)" suffix.
- The admin toggle respects the same enable/disable flow as all other tags — disabled by
  default only if the user explicitly turns it off.
- `bws_deprecated_tag_notice()` fires a `_doing_it_wrong()` notice when `WP_DEBUG` is
  enabled, prompting developers to update their templates.

### `DeprecatedTagRegistry::register()` parameter reference

| Key | Type | Required | Notes |
|-----|------|----------|-------|
| `old_tag` | string | Yes | The deprecated GB tag name. |
| `new_tag` | string | Yes | Replacement name (only used in the deprecation notice). |
| `source_key` | string | Yes | Source group key in the settings UI. Must match a registered `SourceInterface` key for the tag to appear in settings. |
| `title` | string | — | GB editor title. Defaults to the `old_tag` value if omitted. |
| `gb_type` | string | — | GB tag type. Defaults to `source_key`. Use `'media'` for image tags (required for GB to show the media library controls). |
| `supports` | array | — | GB supports array. Defaults to `[]`. |
| `options` | array | — | GB options array. Omit if no options. |
| `callback` | callable\|string | Yes | PHP callable that handles tag output. |
| `is_related` | bool | — | Reserved for internal routing. Defaults to `false`. |
| `since` | string | — | Version string passed to `bws_deprecated_tag_notice()`. |
| `description` | string | — | Overrides the auto-generated GB tag description. Auto-default: `'Deprecated — use "new_tag" instead.'` |
