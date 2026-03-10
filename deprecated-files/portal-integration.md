# Portal System Integration Guide

How `bws-portal-system` integrates with the BWS Dynamic Tag Extensions source registry to provide portal-context dynamic tags.

## 1. Registering a Portal Source

The dynamic tags extension fires a hook after built-in sources are registered. The portal system hooks into this to register its own source.

### Implement the source class

Extend `AbstractSource` (preferred) rather than implementing `SourceInterface` directly — it provides sensible defaults for all 10 methods you don't need to customize.

```php
use BWS\DynamicTags\AbstractSource;

// In bws-portal-system (e.g. includes/integrations/class-dynamic-tags.php)
class PortalSource extends AbstractSource {

    private $detector;
    private $portal_maps;

    public function __construct( $detector, $portal_maps ) {
        $this->detector    = $detector;
        $this->portal_maps = $portal_maps;
    }

    public function get_source_key(): string {
        return 'portal';
    }

    public function get_source_label(): string {
        return __( 'Portal Post', 'bws-portal-system' );
    }

    // get_context_type() defaults to 'post' via AbstractSource — no override needed.

    public function resolve_id( array $options, $instance ) {
        // Primary: detect current portal context.
        $portal_id = $this->detector->get_current_id();

        // Fallback: tag-level fallback option.
        if ( ! $portal_id && ! empty( $options['fallback_portal_id'] ) ) {
            $portal_id = sanitize_text_field( $options['fallback_portal_id'] );
        }

        if ( ! $portal_id ) {
            return false;
        }

        // Map portal ID to WordPress post ID.
        $maps    = $this->portal_maps->get_maps();
        $post_id = $maps['portal_map'][ $portal_id ] ?? null;

        return $post_id ? (int) $post_id : false;
    }

    public function get_source_options(): array {
        return array(
            'fallback_portal_id' => array(
                'type'        => 'text',
                'label'       => __( 'Fallback Portal ID', 'bws-portal-system' ),
                'help'        => __( 'Portal ID to use when no portal context is detected.', 'bws-portal-system' ),
                'placeholder' => 'default-portal',
            ),
        );
    }
}
```

### Hook into the registry

```php
// In bws-portal-system bootstrap (plugins_loaded, priority > 20):
add_action( 'bws_dynamic_tags_register_sources', function() {
    $detector    = PortalDetector::get_instance();
    $portal_maps = PortalMaps::get_instance();

    \BWS\DynamicTags\SourceRegistry::register_source(
        new PortalSource( $detector, $portal_maps )
    );
} );
```

The `$registry` argument passed to the action is available but not needed — `register_source()` is static.

### What happens automatically

Once registered, `TagTemplateRegistry::generate_all_tags()` iterates every enabled source against every template. Because `PortalSource::get_context_type()` returns `'post'`, all post-context templates automatically produce portal-prefixed tags:

- `portal_custom_image`, `portal_custom_text`
- `portal_custom_date_single`, `portal_custom_date_range`
- `portal_custom_datetime_single`, `portal_custom_datetime_range`
- `portal_post_content`
- `portal_featured_image` (if a featured_image template exists for post context)
- … and any future templates added to the registry

No manual `GenerateBlocks_Register_Dynamic_Tag` calls are needed for these standard tags.

The portal source also:
- Appears in the admin settings page under its own section
- Can have its tags individually enabled/disabled
- Respects the `fallback_portal_id` option on every generated tag

## 2. Portal-Specific Tags (No Built-in Template)

If the portal system needs a tag type with no equivalent built-in template, there are two options:

### Option A: Register a new template (preferred)

Adding a template to `TagTemplateRegistry` auto-generates the tag for all sources, including portal:

```php
// In bws-portal-system, at init priority 15 (before generate_all_tags runs at 20):
add_action( 'init', function() {
    if ( ! class_exists( 'BWS\DynamicTags\TagTemplateRegistry' ) ) {
        return;
    }
    \BWS\DynamicTags\TagTemplateRegistry::register_template( array(
        'key'           => 'portal_status',       // Appended to source prefix → portal_portal_status
        'title'         => 'Portal Status',        // Prepended with source label
        'gb_type'       => null,                   // null = use source's gb_type ('post')
        'supports'      => array(),
        'options_fn'    => 'bws_get_portal_status_options',
        'core_fn'       => 'bws_portal_status_core',
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
    if ( SettingsPage::is_tag_enabled( 'portal_unique_field', 'portal' ) ) {
        new GenerateBlocks_Register_Dynamic_Tag( array(
            'title'    => __( 'Portal Unique Field', 'bws-portal-system' ),
            'tag'      => 'portal_unique_field',
            'type'     => 'post',
            'supports' => array(),                 // Always include supports — never omit
            'options'  => array(),
            'return'   => 'bws_get_portal_unique_field_callback',
        ) );
    }
}, 20 );
```

> **Important**: Always include `'supports' => array()` even when empty. Omitting it causes a PHP 8 "Undefined array key" warning on every tag render.

### Callbacks for portal-specific tags

```php
function bws_get_portal_unique_field_callback( $options, $block, $instance ) {
    $source  = \BWS\DynamicTags\SourceRegistry::get_source( 'portal' );
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

### Portal tags with ACF relationship fields

If a portal tag needs to traverse an ACF relationship/post_object field, use the standard `rel` option pattern:

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

### Portal tags that render post content

For tags that render full block content from a portal post, use the processing pipeline and safe output helper:

```php
function bws_get_portal_content_callback( $options, $block, $instance ) {
    $source  = \BWS\DynamicTags\SourceRegistry::get_source( 'portal' );
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

## 3. Shared Helpers Available

These functions from `bws-gb-dynamic-tags-extensions` are available once the plugin is active. All are guarded with `function_exists()` so they won't conflict.

### Image helpers (`includes/helpers/image-helpers.php`)

| Function | Replaces in portal system |
|----------|--------------------------|
| `bws_get_attachment_data()` | `DynamicTags::get_attachment_data()` and `Generate::get_attachment_data()` |
| `bws_get_meta_image_data()` | `DynamicTags::get_meta_image_data()` and `Generate::get_meta_image_data()` |
| `bws_process_meta_image_value()` | `DynamicTags::process_meta_image_value()` and `Generate::process_meta_image_value()` |
| `bws_process_acf_icon_picker()` | `DynamicTags::process_acf_icon_picker()` and `Generate::process_acf_icon_picker()` |
| `bws_handle_dashicon_value()` | `DynamicTags::handle_dashicon_value()` and `Generate::handle_dashicon_value()` |
| `bws_get_attachment_id_from_url()` | URL-to-attachment-ID reverse lookup |
| `bws_handle_media_fallback()` | Media selector fallback logic |
| `bws_get_image_return_type_options()` | `DynamicTags::get_image_options()` / `Generate::get_image_options()` |
| `bws_get_meta_image_options()` | `DynamicTags::get_meta_image_options()` / `Generate::get_meta_image_options()` |

### Content helpers (`includes/helpers/content-helpers.php`)

#### Data retrieval

| Function | Purpose |
|----------|---------|
| `bws_get_related_posts_data( $post_id, $field_key )` | ACF relationship/post_object field resolution |
| `bws_extract_post_id( $post_data )` | Extract post ID from various ACF return formats |
| `bws_extract_text_field( $post_id, $field_name )` | Get text content from post fields or meta |
| `bws_is_valid_meta_key( $meta_key )` | Validate meta key format |
| `bws_sanitize_rich_content( $content )` | Safe HTML sanitization for displayed content |

#### Relationship field option

| Function | Purpose |
|----------|---------|
| `bws_get_relationship_field_options()` | Returns the standard `rel` option array for relationship field selection. Merge into tag options when the tag needs to traverse an ACF relationship or post_object field. |

#### Post content processing pipeline

| Function | Purpose |
|----------|---------|
| `bws_process_post_content( $post_id, $args )` | Full render pipeline: validates post → recursion guard → memory check → `do_blocks()` → `wpautop()` → sanitize. Returns empty string on failure. |
| `bws_safe_content_output( $content, $options, $instance )` | Strips destructive GB options (`trunc`, `case`, `link`, `wpautop`) before calling `GenerateBlocks_Dynamic_Tag_Callbacks::output()`. Always use this for rendered HTML content. |
| `bws_can_process_post_content( $post_id )` | Returns true if the post can be processed (not already in the stack, sufficient memory). |
| `bws_is_query_loop_setup_phase( $instance )` | Returns true when GB is setting up a query loop and `postId` is not yet in context — skip content rendering in this case. |
| `bws_has_sufficient_memory()` | Returns true when memory usage is below 80% of the PHP limit. |

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
| `bws_reliable_term_context_detection()` | Multi-fallback term ID detection |
| `bws_get_validated_term()` | Validate and retrieve term object |
| `bws_get_term_field_image_data()` | Image from term custom fields |

## 4. Admin Settings Integration

Portal tags automatically appear in the settings page when:
1. The portal source is registered via the hook
2. Auto-generated tags respect the per-tag toggles without any additional code
3. Manually registered tags use `SettingsPage::is_tag_enabled( 'tag_name', 'source_key' )` to check the toggle

Tags default to enabled (opt-out model). Settings are stored in `bws_dynamic_tags_settings` under the `'tags'` key.

## 5. Migration Checklist

When migrating `bws-portal-system` to use the source registry:

- [ ] Create `PortalSource` class extending `AbstractSource`
- [ ] Implement `get_source_key()`, `get_source_label()`, `resolve_id()`, `get_source_options()`
- [ ] Register it via `bws_dynamic_tags_register_sources` hook (priority > 20 on `plugins_loaded`)
- [ ] Remove manual `GenerateBlocks_Register_Dynamic_Tag` calls for standard template tags (they are auto-generated)
- [ ] Replace duplicated private methods with shared helper calls:
  - `$this->get_attachment_data()` → `bws_get_attachment_data()`
  - `$this->get_meta_image_data()` → `bws_get_meta_image_data()`
  - `$this->process_meta_image_value()` → `bws_process_meta_image_value()`
  - `$this->process_acf_icon_picker()` → `bws_process_acf_icon_picker()`
  - `$this->handle_dashicon_value()` → `bws_handle_dashicon_value()`
- [ ] Update callbacks to call `$source->resolve_id()` (not the old `resolve_post_id()`)
- [ ] For any remaining portal-specific tags: wrap registration with `SettingsPage::is_tag_enabled()`
- [ ] Add `bws-gb-dynamic-tags-extensions` as a dependency check before registration
- [ ] Keep portal-specific logic in portal system (see below)
- [ ] Replace `bws_portal_is_valid_meta_key()` calls with shared `bws_is_valid_meta_key()`
- [ ] For relationship-based tags: switch from `'supports' => array('meta')` to `bws_get_relationship_field_options()` merged into options, reading field key from `$options['rel']`
- [ ] For content-rendering tags: use `bws_process_post_content()` + `bws_safe_content_output()` + `bws_is_query_loop_setup_phase()` guard

### What stays in bws-portal-system

These are portal-specific and should NOT move to the shared plugin:
- Portal detection logic (`PortalDetector`)
- Portal maps (`PortalMaps`)
- Query Loop portal filtering
- Elements URL rewriting
- Fallback portal ID option

### What moves to bws-gb-dynamic-tags-extensions

These belong in the shared dynamic tags plugin and have been implemented as of v4.1.0:
- ~~Post content rendering with `the_content` filter~~ — **Done**: `bws_process_post_content()` in content-helpers.php, `post_content` tag in content-tags.php
- CSS extraction/handling for rendered content — **Done**: `bws_extract_css_from_block_comments()` in content-helpers.php
