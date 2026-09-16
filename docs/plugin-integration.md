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

Base tags (`text`, `image`, `content`, `title`, `permalink`, `datetime_single`, `datetime_range`) are source-agnostic, and a tag whose source names your key **already resolves through it** — `{{text src:external|key:x}}` renders whether or not anything offers that source in the editor. What registration alone does not do is put a row in the **Source** control, so an author has no way to choose it.

To let authors choose it, opt in as a chain root (below). The older routes remain:

1. **Chain root** (preferred) — one row in the Source control on every base tag and in every folded slot. See [§1a Offering your source as a chain root](#1a-offering-your-source-as-a-chain-root).
2. **Context modifier** (*withdrawn in 1.21.0 — registers nothing*) — `TagTemplateRegistry::register_modifier()` used to create a prefixed tag group (`example_text`, `example_image`, etc.) backed by your source. Superseded by the chain root, which gives your source the whole base-tag surface instead of a copy of it. See [§2](#2-registering-a-context-modifier) for the route out, and [§9](#9-migrating-a-modifier-family-to-a-base-tag) for tags already saved in content.
3. **Manual registration** — register individual GB tags directly and call your source's `resolve_id()` in the callback. See [§4 Plugin-Specific Tags](#4-plugin-specific-tags-no-built-in-template).
4. **Deprecated wrappers only** — if you only need backward-compat wrappers for legacy tag names, `register_source()` makes the source available to `DeprecatedTagRegistry` callbacks without creating any new GB tags. See [§7 Registering Deprecated Tag Wrappers](#7-registering-deprecated-tag-wrappers).

---

## 1a. Offering your source as a chain root

A **chain root** is where a tag's source path starts — `Current Context`, `Site`, a relationship step, and now any registered source that opts in. Once yours does, an author sees it in the Source control on every base tag and in every folded slot (`{{join}}` fields, `try_*` attempts), named by your source label, and the whole base-tag surface applies unchanged: further steps, per-step limits, field pickers, previews.

Two routes, one registry.

### Route A — a source class opts in

Override one method. The dropdown label is `get_source_label()`, the accessor you already implement — there is no second label method.

> **Upgrade note (v1.17.0).** `is_selectable_root()` is declared on `SourceInterface`, so a class implementing the interface **directly** must add it. Extending `AbstractSource` — the documented recommendation — inherits the `false` default and needs no change.

```php
class ExternalSource extends AbstractSource {

    // …get_source_key(), get_source_label(), resolve_id() as above…

    public function is_selectable_root(): bool {
        return true;
    }
}
```

**The precondition for returning `true` is that your source resolves its own id from ambient context** — the request, the queried object, your own state — with no tag option telling it where to look. A source that exists only to back deprecated wrappers, or that needs an option filled in before it can answer, should leave the default `false`.

Offerability is stated rather than derived because the registry keeps entries that can no longer resolve: a `register_source()` call is never deleted just because its resolve logic was retired (see [§7](#7-registering-deprecated-tag-wrappers)). Deriving an authoring list from a registry that keeps its dead would surface them.

> **Upgrade note (v1.20.0).** `get_root_argument()` is declared on `SourceInterface` alongside it, so a class implementing the interface **directly** must add it. Extending `AbstractSource` inherits the no-argument default and needs no change.

#### Declaring a root argument (v1.20.0)

A root that names one specific entity needs an author to say *which*: `term,34`, `post,1692`, or a `roster,engineering` your own plugin understands. Declare that argument beside the offer:

```php
public function get_root_argument(): array {
    return array(
        'label'   => __( 'Department', 'my-plugin' ), // required; what the argument means
        'control' => 'my-plugin-department-picker',   // required; the control that edits it
        'argless' => 'owner-resolves',                // optional; 'refuse' is the default
    );
}
```

The argument is **one opaque token**, and it travels beside the root rather than inside it: the chain reads `roster,engineering`, `bws_fold_chain_root()` still answers `roster`, and `bws_fold_chain_root_arg()` answers `engineering`. Nothing between your declaration and your control interprets the value, so it need not be an ID.

`argless` says what a bare `roster` with no argument means. `refuse`, the default and what a declaring root wants, resolves nothing until an author fills it. `owner-resolves` says your source answers a bare token by a rule of its own; it is not permission to fall back to whatever the page is about, which no root does.

**Two caveats before you ship one.** A declaration missing either required key is dropped and your root is offered as an argless root; a working source is never retired over a bad optional declaration. And **this plugin ships no argument-taking root and no `bws-*` control for one yet**, so the declaration is carried to both authoring surfaces but the control you name has to be one you register yourself.

### Route B — declare a root from a filter, with no class

For the cheap case — you have an entity and a function that finds it — skip the class entirely:

```php
add_filter( 'bws_dynamic_tags_chain_roots', function( $roots ) {
    $roots['external'] = array(
        'label'   => __( 'External Post', 'my-plugin' ),   // required; what authors see
        'context' => 'post',                               // 'post' or 'term'; default 'post'
        'resolve' => 'my_plugin_current_external_id',      // callable( array $options, $instance ): int|false
        'arg'     => array(                                // optional; see Route A (v1.20.0)
            'label'   => __( 'Department', 'my-plugin' ),
            'control' => 'my-plugin-department-picker',
            'argless' => 'owner-resolves',
        ),
    );
    return $roots;
} );
```

**Add the `add_filter()` call at plugin file-load time, not inside a hook callback** — the same deadline §1 states for the registration action, and for the same reason. `SourceRegistry::init()` runs at `plugins_loaded` priority 20, so a listener added on `init` is too late: nothing errors, your root simply never appears.

Each spec is adapted into a registered source and registered normally, so it lands in the same registry the renderer consults. Notes:

- **Everything declared here is a root.** The filter is named for roots, so declaring through it *is* the opt-in — there is no flag to set. A source that should *not* be offerable uses the `bws_dynamic_tags_register_sources` action instead.
- **The filter fires at registry initialisation**, not when the editor builds its dropdown. A row added at enum-build time would exist for the editor and not for the renderer, and the token would quietly fall through to the ambient entity.
- **A key that collides with an already-registered source is ignored**, never merged over it. Class-route registrations win.
- **A spec with no label, or a non-callable `resolve`, is skipped** rather than registered half-formed.
- **A spec's `arg` declaration means exactly what Route A's does**, and is read by the same single gate, so a spec whose declaration is missing a key or carries the wrong shape registers a working root that simply takes no argument rather than being skipped. Label and `resolve` are checked here because a spec failing either has no dropdown row to be at all; a bad argument declaration leaves a perfectly good argless root.
- **The key has to be writable as a `src` token**, so a spec is also skipped when its key is a chain step slug (`refs`, `terms`, `rows`), the slot carry-over sentinel (`same`), or carries a grammar character (`; , ( ) [ ] : |` or whitespace). Any of those would parse back as something other than a root, which would break the guarantee that an offered root resolves. Use a plain identifier — your plugin's own slug is the obvious choice.
- **No `$context` argument.** No tag, block or container exists when this fires. (WordPress passes arguments positionally by registered arity, so one can be added later without breaking existing listeners.)

### What the opt-in does and does not govern

**It governs the dropdown only.** Wire naming your source resolves whether or not it is currently offered — the factory's registry delegation is untouched. That is deliberate: tag strings are hand-editable by design, and flipping the flag off must never blank stored content. It also means removing the opt-in is safe at any time; existing tags go on rendering.

Two further consequences worth knowing:

- **Your rows reach base tags and slots, and nothing else.** They are appended at the chain-root layer, so `term_*`, `try_*`'s own source lists, `{{table}}` and `{{call}}` are unaffected.
- **Your opt-in is the only gate.** No plugin setting sits beside it, and your source's context type does not decide whether it is offered. Through 1.19.x a term-context root also followed the `term_` modifier toggle; since 1.20.0 that toggle governs the deprecated `term_` tag family alone, and no longer reaches the dropdown.

Where a rooted tag cannot resolve in the editor (common when your source reads request state), the preview names it by your registered label:

```
['contact_phone' from External Post]
```

---

## 2. Registering a Context Modifier

> **Withdrawn in 1.21.0. `TagTemplateRegistry::register_modifier()` registers nothing.** It emits a `_doing_it_wrong()` notice and returns; the method itself is deleted in 1.22.0. Nothing you pass it mints tags any more, and no reduced or grace family is produced.

**Register a [chain root](#1a-offering-your-source-as-a-chain-root) instead.** A modifier family was a second copy of the base tags that every new capability had to be built into by hand. A chain root gives your source the whole base-tag surface instead of a copy of it: source paths, per-step limits, field pickers and editor previews, all of it arriving for free as the base tags gain it.

**Tags already saved in content are a separate job from the registration.** [§9 Migrating a Modifier Family to a Base Tag](#9-migrating-a-modifier-family-to-a-base-tag) converts stored `{{example_text}}` wire to base tags rooted at your source in one converter run, and older prefixes registered as plain renames chain into it automatically. §9 is live and unaffected by this withdrawal. Do this before you retire your prefix — an unregistered tag renders its own braces on a published page.

The `bws_dynamic_tags_preview_modifier_map` filter went with the constructor: its only population was prefixes minted here. The preview-text schema itself is [`editor-tag-previews.md`](editor-tag-previews.md).

`TagTemplateRegistry::register_modifier_template()` is **not** affected and is still called by this plugin's own base tags — only the family constructor is withdrawn.

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
| `resolve_id( array $options, $instance ): int\|false` | Entity ID | Override this (or legacy `resolve_post_id()`). **Resolve a STARTING entity, never a traversal.** See the note below. Returning `false` makes the tag render nothing — see [What a non-resolving source renders](#what-a-non-resolving-source-renders). |
| `format_id_for_acf( $id ): int\|string` | ACF object ID | Override when source resolves to a non-post entity. Post sources: pass-through. Term sources: return `"term_{$id}"`. User sources: return `"user_{$id}"`. |

> **A source resolves a starting point; the pipeline does the hops.** Since v1.14.0 a relationship hop is a generic `ref` step the traversal engine runs off whatever entity the source factory resolved, and `register_modifier()`'s `traversal_source_key` has been accepted-but-ignored. A source whose `resolve_id()` reads its own relationship field is therefore doing work the engine already does, from an option key nothing else in the plugin honours — the compiler builds its `refs` step from `ref` alone. In v1.17.0 the four built-in related-post sources (`related_post`, `second_related_post`, `post_term_related_post`, `term_related_post`) were made inert for exactly this reason ([#56](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/56)); their registrations remain, their `get_source_options()` are now empty, and the `rel`/`rel_2` option builders they used are removed. If your plugin ships a source of this shape, it is safe to make it inert too — nothing in this plugin dispatches to it.

### What a non-resolving source renders

Since v1.17.0, a tag naming a source that cannot resolve **renders nothing**, and any fallback the author stated fires. Before that it fell through to the ambient entity, so the tag showed a real value read from whatever the visitor was already looking at.

This is the default and needs no declaration on your part. Two cases reach it:

- `resolve_id()` returns `false` — your source ran and found no entity in this context. A source scoped to some part of the site behaves this way everywhere else, which is correct: it never appears to work by reading someone else's entry.
- The stored tag names a source key that is not registered — your plugin is deactivated, or the key was renamed.

What an author sees depends on the tag:

| Tag family | Result |
|---|---|
| A base tag | Renders empty, or its stated fallback |
| A first-available tag (`try_*`) | Skips that attempt and runs the next one |
| `{{join}}` | Drops that field from the composite, and assembles the rest |
| `{{table}}` | Renders no table at all. Its rows come from the source, so refusing the source leaves nothing to put in one |

Two things follow that are easy to get backwards:

- **Withdrawing your source's offer does not change what stored tags render.** `is_selectable_root()` governs the dropdown only. A tag already naming your source keeps resolving through it.
- **The plugin's own registry failing to load is a different question and still falls through.** That is a fact about this plugin rather than about the author's tag, and refusing on it would blank every tag on the site.

### Default-enabled control

| Method | Return | Default | Notes |
|--------|--------|---------|-------|
| `source_default_enabled(): bool` | Source toggle on/off by default | `true` | Set to `false` for advanced/experimental sources where all tags should be opt-in. All built-in sources default to `true` as of v1.5.0. |
| `tag_default_enabled(): bool` | Per-tag on/off when source toggle is active | `source_default_enabled()` | Override independently when the source is opt-in but all its tags should be on once enabled. |

### Traversal

| Method | Return | Default | Notes |
|--------|--------|---------|-------|
| `needs_relationship_field(): bool` | Whether this source requires a `ref` option to resolve | `false` | **Inert since v1.17.0 — nothing reads it.** Relationship traversal is a generic `ref` step off whatever source the factory resolved, not a property of the source (see below). Kept on the interface so existing implementations keep loading. |
| `get_ui_group(): string` | Admin matrix group for this source | `get_context_type()` | Override when the source should appear in a different group than its context type. `TermRelatedPost` returns `'term'` even though its `context_type` is `'post'`. |

### Authoring surface

| Method | Return | Default | Notes |
|--------|--------|---------|-------|
| `is_selectable_root(): bool` | Whether authors may choose this source as a chain root | `false` | Added v1.17.0. Governs the **dropdown only** — wire naming your source resolves either way. Precondition: the source resolves its own id from ambient context. **Do not** phrase the decision in terms of `needs_relationship_field()`, which is inert and would wrongly pass a wrapper-only registration. See [§1a](#1a-offering-your-source-as-a-chain-root). |
| `get_root_argument(): array` | The argument this root takes, if any: `label` (what it means), `control` (the `bws-*` control that edits it), `argless` (`refuse`, the default, or `owner-resolves`) | `array()` | Added v1.20.0. One opaque token, arity fixed at one, carried to both authoring surfaces without being interpreted. A declaration missing `label` or `control` is dropped and the root is offered argless. See [§1a](#1a-offering-your-source-as-a-chain-root). |

### Options

| Method | Return | Notes |
|--------|--------|-------|
| `get_source_options(): array` | Custom options on every tag | Used for options that apply to every tag the source generates (e.g. source identifier, fallback). Return `array()` if none. |
| `get_effective_source_id(): string` | try-tag source ID | `get_tag_prefix()` — override if you need a different key for try-tag slot assignment. |

---

## 4. Plugin-Specific Tags (No Built-in Template)

If your plugin needs a tag type with no equivalent built-in template, there are two options:

### Option A: Register a new modifier template (preferred)

Adding a template via `register_modifier_template()` registers a base template descriptor. Since 1.21.0 no family constructor consumes it to mint a prefixed group — [§2](#2-registering-a-context-modifier) is withdrawn — but the `try_` constructor produces one `try_`-prefixed tag per registered template, and the converter reads the same list to derive per-template migration entries:

```php
// In your plugin, at init priority 15 (before bws_register_base_tags runs at 20):
add_action( 'init', function() {
    if ( ! class_exists( 'BWS\DynamicTags\TagTemplateRegistry' ) ) {
        return;
    }
    \BWS\DynamicTags\TagTemplateRegistry::register_modifier_template( array(
        'key'           => 'my_field',          // Appended to a constructor's prefix → try_my_field
        'title'         => 'My Field',           // Tag title in the GB tag picker
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
| `bws_get_loop_item_context()` | Detect a GB query-loop item and its shape (a post, a term, a user or a repeater row — see the contract note below) |
| `bws_loop_item_is_post_or_row()` | **(v1.19.0)** True when the loop item is one a post-meta/repeater read can be served from. Ask this, not the in-loop flag, before skipping a "no entity" bail |

**`bws_get_loop_item_context()` — the states a caller can meet (v1.19.0).** The loop item may now hold
a **post**, a **term**, a **user** or a repeater **row**, and a fifth answer says an item is present
that we could not read. `item_kind` (`''|post|term|user|row|unknown`) and `item_id` carry the entity;
`item_post_id` holds a post and only a post, so it is `false` while `in_loop` is `true` for every term
and user loop — **an ordinary state, not an edge case**, and code that reads `item_post_id` as "the
loop's entity" reads a term loop as no loop. `in_loop` means an item is present and nothing more: it
is true for a term, a user and an unreadable item too, none of which a post-meta read can serve, so a
caller about to skip its own "no entity, give up" bail wants `bws_loop_item_is_post_or_row()`
instead. An `unknown` item is *in a loop, unreadable* and is a different answer from *not in a loop*:
the first refuses, the second resolves the ambient entity. Which shape an item IS, is decided at
[`field-helpers.php`](../includes/helpers/field-helpers.php) and by nothing else — the shapes are
listed above as a consequence of that decision, and the predicate assigning one, along with the
residual hole it cannot close, live only there.

**`item_post_id` is an identity, not a permission (v1.19.0).** It names the loop item's post whatever that post's status is, and reading a field off it goes through the same source gate a chain-resolved post passes, so a post the gate refuses reads nothing.

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
| `bws_can_process_post_content( $post_id )` | Returns `true` if post can be processed (not in stack, depth below the limit). |
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
| `bws_prepare_registration_options( $options )` | The registration pass every BWS options array goes through before GB sees it. Three rules: flip the first option value of `_strip_default`-flagged selects to `''` (wire format); stamp `_group`/`_group_lead` for [visual grouping](editor-controls.md#option-grouping-visual); drop the flat source options a chain control absorbed. Called by tag-template registry and base-tag registration. **Renamed in v1.17.0 from `bws_strip_default_select_values()`, no alias** — the old name described the first rule only. |

### Date helpers (`includes/helpers/datetime-helpers.php`)

| Function | Purpose |
|----------|---------|
| `bws_parse_acf_date_value()` | Parse ACF date values with timezone handling |
| `bws_format_single_date_time()` | Format single date/time with year omission and midnight suppression |
| `bws_format_date_range()` | Smart range formatting with redundancy removal |
| `bws_format_time_range()` | Time range with AM/PM consolidation |
| `bws_handle_date_time_fallback()` | Fallback text handling for date tags |

**BREAKING in v1.19.1 — the midnight-suppression option key is `hide_midnight`, was `smart_time`, no alias.** It reaches `bws_format_single_date_time()` and `bws_format_date_range()` in their `$options` array, and `bws_format_time_range()` as its third positional argument. Same value, same meaning: `true` hides a time that carries no information. The old name described two behaviors that have since been separated — AM/PM consolidation no longer rides this flag at all ([#125](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/125)) — so a `smart_time` key is now silently ignored rather than misread. Author-facing surfaces are unaffected: the `showMidnight` tag option and every tag string keep their spelling.

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
- **Deprecated tag mode** — a group-level Keep/Suppress/Disable control for deprecated tags that still register with GenerateBlocks (two settings: one for tags with a migration path, one for those without). This governs future deprecated tag families that keep live registration; it does not gate tags routed only through `DeprecatedTagRegistry` for migration data. There is no per-tag toggle and no `is_deprecated_tag_enabled()` gating call to add to your registration code.

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

- The old tag is recorded in `MigrationRegistry` so the Migration Tool can find and rewrite it. As of 1.14.0 deprecated tags no longer register with GenerateBlocks, so existing content using an old tag string stops rendering until you run the Migration Tool (or the author updates the tag) — the migration path is how that content stays working, not live rendering of the old name.
- The old tag appears in the Removed Tags section of the admin settings page (informational; no per-tag toggle).
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
| `callback` | callable\|string | Yes | PHP callable, historically invoked to render the old tag. As of 1.14.0 it is **not** called for rendering (deprecated tags no longer register with GB); still required for a well-formed entry and future lifecycle use. |
| `since` | string | — | Version string passed to `bws_deprecated_tag_notice()`. |
| `description` | string | — | Overrides the auto-generated GB tag description. Auto-default: `'Deprecated — use "new_tag" instead.'` |
| `source_inject` | string | — | `src` option value injected on conversion (e.g. `'ref'` for a related-post traversal source). Empty string omits the `src` option. |
| `option_renames` | array | — | Map of old option key → new option key applied before serialization. E.g. `['field_key' => 'key']`. |
| `value_renames` | array | — | Map of (post-rename) option key → `[old value => new value]`. Applied after `option_renames`. |
| `fixed_options` | array | — | Key/value pairs always injected during conversion regardless of user options. E.g. `['use' => 'excerpt']`. |
| `datetime_transforms` | bool | — | When `true`, apply the five special-case datetime option transforms during conversion. Default `false`. |
| `prefix_removed` | bool | — | Hand-set. Set `true` once **you** retire this alias generation — moves the entry from the **Deprecated Tags** box to **Removed Tags** on the settings page. Default absent (still Deprecated). See "Alias status and retiring a prefix" below. |

### Converter behavior

The admin **Migration Tool** (separate section on the settings page) scans all non-revision posts for any deprecated tag matching a registered `old_tag`. The results table shows post title + type + per-row issue list (deprecated tags + option migrations). Per-row **Migrate** rewrites stored content via `MigrationRegistry::transform_tag()` — applying `option_renames`, `value_renames`, `combine_options`, `source_inject`, `fixed_options`, `datetime_transforms` in the documented order. **Bulk Migrate Selected** processes the checked rows in sequence with a progress bar.

Posts whose content does not change after transformation are not rewritten. Each migrated post gets a pre-migration `wp_save_post_revision()` snapshot so changes are reversible.

### Alias status and retiring a prefix

Your deprecated aliases are **old names for tags this plugin owns** — e.g. `oldname_title` is an old-prefix alias of a tag whose target is ours. Because the target tag is ours, its status is authoritative here: while the target renders, your alias is a **deprecated** name for it, not a removed one.

The settings page sorts deprecated tags into two boxes:

- **Deprecated Tags** — the alias's target is still live and you have not retired the prefix. This is where a freshly-deprecated alias belongs. Your aliases land here by default.
- **Removed Tags** — informational, migration-only. An alias moves here when you retire its prefix generation by setting `prefix_removed => true` on that entry's registration.

Set `prefix_removed` when you consider an old prefix generation fully retired (for example, two prefix renames later). Existing content still using that name is not broken by the flag — the Migration Tool still finds and rewrites it — the flag only changes which box the entry is filed under and signals "this generation is history, not a currently-recommended deprecation."

Migration available only for deprecated entries that declare `new_tag` plus at least one of `source_inject`, `option_renames`, or `fixed_options`. `DeprecatedTagRegistry::has_migration_path( $old_tag )` returns whether a path exists.

---

## 8. Renaming a Modifier Prefix

> **Withdrawn in 1.21.0, along with [§2](#2-registering-a-context-modifier).** There is no supported way to mint a prefixed tag family any more, so there is no new prefix to rename one to.

If you are here with old notes and a prefix you wanted to move off: **do not rename it — exit it.** [§9 Migrating a Modifier Family to a Base Tag](#9-migrating-a-modifier-family-to-a-base-tag) rewrites your stored `{{oldname_text}}` wire into base tags rooted at your source, in a single converter run, and chains older prefixes into it automatically. That is the same one run a rename would have cost you, and it lands on a tag surface that is still supported.

Registering a plain old-name → new-name wrapper is still a live mechanism in its own right — [§7 Registering Deprecated Tag Wrappers](#7-registering-deprecated-tag-wrappers) owns it, including the Migration Tool's conversion behavior and the `prefix_removed` flag that files an entry under **Removed Tags**. Only the modifier-family framing this section carried is gone.

---

## 9. Migrating a Modifier Family to a Base Tag

Once your source is offered as a [chain root](#1a-offering-your-source-as-a-chain-root), your prefixed tag family and the base tags say the same thing two ways. `{{example_text key:bio}}` and `{{text src:example|key:bio}}` read the same field from the same entity — but only the base tag gets source paths, per-step limits and every capability added since.

One call rewrites your stored tags into base tags rooted at your source:

```php
add_action( 'init', function () {
    bws_register_modifier_root_migrations(
        'example',                            // your retired modifier prefix
        'example',                            // the registered source key to root at
        array( 'since' => '3.4.0' )
    );
}, 21 );
```

That registers one migration entry per **registered modifier template** — the same list `register_modifier()` iterates to mint your tags — so there is no list of tag names to maintain. Call it after templates are registered: the plugin registers them on `init` priority 20, so priority 21 is the natural home, beside your `register_modifier()` call. (Called too early, the template list is empty and `_doing_it_wrong()` says so.)

**Your prefix is supplied, never derived.** Nothing in this plugin knows your family exists; you name it. The root key is usually your source key, but it does not have to be.

**Call order decides where your tags appear in the editor.** `register_modifier()` reads each tag's GB type off that tag's migration entry when it has one. So calling this registrar *before* `register_modifier()` moves the whole family into GenerateBlocks' deprecated group; calling it *after* leaves the family under the `gb_type` you passed and registers only the conversion. Both are supported — pick the one that matches whether you are telling authors to stop reaching for the family yet. The built-in `term_` family registers entries first, for that reason.

### What a converted tag looks like

The rewrite is a whole-string transform, one row per stored shape:

| Your stored tag | Becomes |
|---|---|
| `{{example_text key:bio}}` | `{{text src:example\|key:bio}}` |
| `{{example_text src:current\|key:bio}}` | `{{text src:example\|key:bio}}` — on a modifier tag "current" meant *your* entity |
| `{{example_text src:ref\|ref:office\|key:bio}}` | `{{text src:example;refs,office\|key:bio}}` — the hop becomes a real step |
| `{{example_text srcTermIn:genre\|key:bio}}` | `{{text src:example;terms,genre\|key:bio}}` |
| both sidecars | `{{text src:example;refs,office;terms,genre\|key:bio}}` |
| `{{example_text src:site\|…}}` | `{{text src:site\|…}}`, with `ref` / `srcTermIn` **dropped** |

The last row is the one that can change rendered output, and it is deliberate: under `src:site` the modifier callback returned *before* reading either sidecar, so neither has ever run — while a relationship step now accepts site input, so carrying one through would start executing a hop that never once executed.

A tag-level `limit` is left alone here and absorbed onto the fanning step by the base-tag chain migration in the converter's later pass, so both routes land on identical wire.

After conversion, the retired flat controls (`ref`, `srcTermIn`, the legacy `source` spelling) are gone: the source is stated in exactly one place.

### What it does not do, and what stays true

- **Your tags stay registered.** Migrating is not retiring. Keep `register_modifier()` exactly as it is; retire on your own schedule, and pass `prefix_removed => true` then (see [§8](#alias-status-and-retiring-a-prefix)).
- **Deleting the registration is not the same as retiring it.** If you remove the `register_modifier()` call, any stored tag you did not convert stops being a tag: GenerateBlocks has no name to dispatch and the literal `{{oldname_text}}` string appears in the rendered page. Deleting your `bws_register_modifier_root_migrations()` call in the same release also removes the entries the **Convert** button needs, so those tags can no longer be repaired from the admin screen either. Convert first, confirm nothing is left, then delete — and note that this plugin has no site-wide "where is this tag still used" report to confirm with today, so that confirmation rests on your own knowledge of the content.
- **Nothing is a deadline.** Tags that are never converted go on rendering indefinitely.
- **The converter's reach is the posts table** — every non-revision, non-trashed post, which does include reusable blocks, template parts and theme-element post types. Tags stored in the **options table** (block widgets) are out of its reach and are simply not rewritten.
- **It never overwrites an entry you registered yourself.** A tag name that already has *any* entry is skipped whole, so a hand-written entry for one template keeps its own rules and the generator covers the rest.
- **Older prefixes chain automatically.** If `oldname_text → example_text` is registered as a plain rename ([§8](#8-renaming-a-modifier-prefix)), the converter re-reads the tag name after each rewrite, so `{{oldname_text}}` reaches the base tag in one run. No extra entry.
- **Editor mounts do not migrate.** Option migrations also run when a tag modal opens; a *rename* cannot, because the tag name belongs to the block's parsed tag and is chosen by the picker. This migration is converter-only by construction, not by omission.

### Parameter reference

| Arg | Type | Default | Meaning |
|---|---|---|---|
| `$prefix` | string | — | Your modifier prefix, with or without the trailing underscore. |
| `$root` | string | — | Registered source key migrated tags are rooted at. |
| `since` | string | `''` | Version you deprecated the prefix in. Shown in the settings list. |
| `prefix_removed` | bool | `false` | `true` once you stop registering the family — files the entries under **Removed Tags** instead of **Deprecated Tags**. |

Returns the modifier tag names entries were generated for, in template order.

---

## 10. Reacting to a direct content write

The Tag Converter writes migrated content **straight to the posts table**, deliberately: that
avoids a duplicate revision, a bumped modified date, and every third-party save listener reacting
to a maintenance task as though a human edited content. The cost is that nothing downstream is
told. If you keep a cache derived from `post_content`, this action is how you hear about it.

```php
add_action( 'bws_dynamic_tags_content_written', function ( $post_id, $content ) {
	my_plugin_refresh_cache_for( $post_id, $content );
}, 10, 2 );
```

| Arg | Type | Meaning |
|---|---|---|
| `$post_id` | `int` | Post whose content was rewritten. |
| `$content` | `string` | The new content, exactly as written. |

**Use the `$content` you are handed — do not re-read the post and do not expect a `WP_Post`.** No
post object is passed on purpose. At the point this fires, the object the converter holds carries
*pre-migration* content (it was read before the rewrite), and `clean_post_cache()` has already run.
A listener reaching for the obvious `$post->post_content` would get exactly the stale wire the
action exists to warn about. The ID plus the new string makes the fresh value the only value
available.

**It names the fact, not the cause.** Any future direct write elsewhere in the plugin fires it
truthfully; it is not "the converter migrated something".

**No save hooks fired.** That is the whole reason for the announcement. Do not assume
`save_post`, `wp_after_insert_post` or anything else ran for this write.

**It is an announcement, not a facility.** The plugin does not enumerate or drive anyone's cache,
and its own pattern-cache repair does not depend on this action firing — that reconcile is
content-agnostic and site-wide (see [`tag-reference.md` §Pattern cache reconcile](tag-reference.md#pattern-cache-reconcile)).
