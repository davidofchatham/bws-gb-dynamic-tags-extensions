# Plugin Integration Guide

How another plugin plugs its own data into BWS Dynamic Tag Extensions.

There are two jobs, and most integrations need only the first:

- **Let authors read your entity with the built-in tags.** Offer it as a [chain root](#1-offering-your-source-as-a-chain-root). Your entity then appears in the Source control of every base tag and every folded slot, and gets everything those tags do: further steps, per-step limits, field pickers and editor previews.
- **Ship a tag no built-in tag covers.** Register it with GenerateBlocks yourself ([§3](#3-registering-your-own-tag)).

The rest is housekeeping: [migrating your own old tag names](#4-migrating-your-own-old-tag-names), [hearing about direct content writes](#5-reacting-to-a-direct-content-write) and [diagnostic filters](#6-diagnostic-filters).

---

<a id="1a-offering-your-source-as-a-chain-root"></a>

## 1. Offering your source as a chain root

A **chain root** is where a tag's source path starts: `Current Context`, `Site`, and any registered source that opts in. Once yours does, authors see it in the Source control named by your label, and `{{text src:external|key:bio}}` reads from it.

Two routes land in the same registry. Use the filter route unless you need a class.

### The filter route

For the common case, an entity plus a function that finds it:

```php
add_filter( 'bws_dynamic_tags_chain_roots', function( $roots ) {
    $roots['external'] = array(
        'label'   => __( 'External Post', 'my-plugin' ), // required; what authors see
        'context' => 'post',                             // 'post' or 'term'; default 'post'
        'resolve' => 'my_plugin_current_external_id',    // callable( array $options, $instance, string $arg = '' ): int|false
    );
    return $roots;
} );
```

**Add the `add_filter()` call at plugin file-load time, never inside a hook callback.** The filter fires once, when the source registry initializes at `plugins_loaded` priority 20. A listener added on `init` is too late: nothing errors, and your root never appears.

- **Everything declared here is a root.** Declaring through the filter *is* the opt-in. A source that should not be offered uses the class route.
- **A spec with no label, or a non-callable `resolve`, is skipped.**
- **A key that collides with an already-registered source is ignored**, never merged over it. Class registrations win.
- **The key must parse back as a root.** A key that would read as something else in a source path (a chain step, a reserved word, or one containing tag-grammar punctuation or whitespace) is skipped; `SourceRegistry::is_expressible_root_key()` owns the rule. Use a plain identifier; your plugin's slug is the obvious choice.
- **No `$context` argument.** No tag, block or container exists when the filter fires.

### The class route

Write a class when the source should be registered but *not* offered (for example, one that only exists so a [migration entry](#4-migrating-your-own-old-tag-names) has a `src` value to inject), or when you would rather keep the logic in a class.

Extend `AbstractSource`. Implementing `SourceInterface` directly means implementing every method it declares, including the ones `AbstractSource` gives a default under [§2](#2-source-class-reference).

```php
use BWS\DynamicTags\AbstractSource;

class ExternalSource extends AbstractSource {

    public function get_source_key(): string {
        return 'external';
    }

    public function get_source_label(): string {
        return __( 'External Post', 'my-plugin' );
    }

    public function resolve_id( array $options, $instance ) {
        $post_id = my_plugin_get_current_post_id( $instance );
        return $post_id ? (int) $post_id : false;
    }

    public function is_selectable_root(): bool {
        return true; // Offer it in the Source control. Omit to register without offering.
    }
}
```

Register it from the registration action, added at plugin file-load time for the same reason as the filter:

```php
add_action( 'bws_dynamic_tags_register_sources', function() {
    \BWS\DynamicTags\SourceRegistry::register_source( new ExternalSource() );
} );
```

Calling `register_source()` directly from `plugins_loaded` at any priority below 20 works too.

**Return `true` from `is_selectable_root()` only when the source resolves its own id from ambient context**: the request, the queried object, your own state. A source that needs an option filled in before it can answer should stay unoffered. The flag is stated rather than derived because the registry keeps entries that no longer resolve, so that saved tags naming them keep a defined meaning.

**A source resolves a starting entity, never a traversal.** A relationship hop is a generic `refs` step the traversal engine runs off whatever your source resolved, so a `resolve_id()` that reads its own relationship field duplicates work the engine already does, from an option nothing else honors.

### Root arguments

A root that names one specific entity needs the author to say *which*: `term,34`, `post,1692`, or `roster,engineering` in your plugin's own terms. Declare the argument beside the root.

On a class, override `get_root_argument()` and `resolve_root_argument()`:

```php
public function get_root_argument(): array {
    return array(
        'label'   => __( 'Department', 'my-plugin' ), // required; what the argument means
        'control' => 'my-plugin-department-picker',   // required; the control that edits it
        'argless' => 'owner-resolves',                // optional; 'refuse' is the default
    );
}

public function resolve_root_argument( string $arg, array $options, $instance ) {
    return my_plugin_department_post_id( $arg ); // int|string|false
}
```

On a filter spec, add the same array as `'arg'`. The spec's `resolve` callable then receives the argument as its third parameter.

- **The argument is one opaque token**, carried beside the root: the chain reads `roster,engineering`, the root is `roster`, and your code receives `engineering` verbatim. Nothing in between interprets it, so it need not be an ID.
- **`resolve_root_argument()` is only called with a non-empty argument.** Returning `false` renders nothing; it never falls back to `resolve_id()`.
- **`argless` says what a bare root with no argument means.** `refuse` resolves nothing until an author fills it in. `owner-resolves` hands the bare root to `resolve_id()`, your own rule; it is not permission to fall back to whatever the page is about.
- **A declaration missing `label` or `control` is dropped**, and the root is offered without an argument. A working root is never retired over a bad optional declaration.
- **This plugin ships no control for root arguments.** The control you name has to be one you register.

### What the opt-in governs

**The Source control only.** A saved tag naming your source resolves through it whether or not it is offered, because tag strings are hand-editable and withdrawing the offer must never blank stored content. Removing the opt-in is safe at any time.

- **Your root reaches base tags and folded slots** (`{{join}}` fields, `try_*` attempts), and nothing else: `{{table}}` and `{{call}}` keep their own source lists.
- **Your opt-in is the only gate.** No plugin setting sits beside it.

Where a rooted tag cannot resolve in the editor (common when your source reads request state), the preview names it by your label:

```
['contact_phone' from External Post]
```

### What a non-resolving source renders

A tag naming a source that cannot resolve **renders nothing**, and any fallback the author stated fires. It never falls through to the ambient entity, so a source scoped to part of the site never appears to work elsewhere by reading someone else's entry. Two cases reach this:

- `resolve_id()` returns `false`: your source found no entity in this context.
- The saved tag names a source key that is not registered: your plugin is deactivated, or the key was renamed.

| Tag family | Result |
|---|---|
| A base tag | Renders empty, or its stated fallback |
| A first-available tag (`try_*`) | Skips that attempt and runs the next one |
| `{{join}}` | Drops that field and assembles the rest |
| `{{table}}` | Renders no table; its rows come from the source |

### Letting `src:site` read your options

The built-in `Site` root reads `wp_options` keys through an allowlist. ACF options-page fields and six WordPress defaults (`siteurl`, `blogname`, `blogdescription`, `home`, `time_format`, `user_count`) are readable with no filter. Site title, tagline, URLs and logo are reached through named `use:` values and need no key at all. Add any other key your plugin stores:

```php
add_filter( 'generateblocks_dynamic_tags_allowed_options', static function ( array $allowed ): array {
    return array_merge( $allowed, array( 'my_plugin_settings' ) );
} );
// Now {{text src:site|key:my_plugin_settings.support_email}} resolves.
```

Allow the **root** key of an array option; dot-path subkeys traverse within it. This plugin applies the filter itself, because GB's meta handler does not. Rationale of record: [ADR 0001](adr/0001-site-option-read-allowlist.md).

---

## 2. Source class reference

Methods a class route source writes or overrides. `AbstractSource` supplies every default below.

| Method | Default | Notes |
|---|---|---|
| `get_source_key(): string` | (must implement) | Registry key and `src` token. Same token rules as a key on [the filter route](#the-filter-route). |
| `get_source_label(): string` | (must implement) | Author-facing name in the Source control and editor previews. |
| `resolve_id( array $options, $instance ): int\|false` | (must implement) | The entity id from ambient context, or `false` for none. |
| `get_context_type(): string` | `'post'` | `'term'` when `resolve_id()` returns a term id. Decides what kind of entity the chain starts from. |
| `is_selectable_root(): bool` | `false` | Offer the source in the Source control. See [the class route](#the-class-route). |
| `get_root_argument(): array` | `array()` | Declare a root argument. See [Root arguments](#root-arguments). |
| `resolve_root_argument( string $arg, array $options, $instance ): int\|string\|false` | `false` | Resolve a declared argument. |

---

## 3. Registering your own tag

If no base tag reading from your source does what you need, register the tag with GenerateBlocks directly. GenerateBlocks documents the registration API: [Registering dynamic tags](https://learn.generatepress.com/developer-doc/registering-dynamic-tags/). Before choosing option keys or a tag name, read [`gb-constraints.md`](gb-constraints.md): it records the tag-name prefix rule, the option keys GB reserves and how default values serialize.

Find your entity by calling your registered source's `resolve_id()`, not your own lookup, so your tag and the base tags rooted at your source pick the same entity. Then pass it through `bws_is_source_usable()`, the check base tags apply to every entity they read: it refuses an entity that does not exist, or that the current viewer may not see. A callback calling `resolve_id()` directly gets none of that on its own. The gate takes `array( 'kind' => 'post', 'id' => $id )`, with `'term'` or `'user'` as the kind for those entities.

```php
function my_plugin_unique_field_callback( $options, $block, $instance ) {
    $source  = \BWS\DynamicTags\SourceRegistry::get_source( 'external' );
    $post_id = $source ? $source->resolve_id( $options, $instance ) : false;
    if ( $post_id && ! bws_is_source_usable( array( 'kind' => 'post', 'id' => $post_id ) ) ) {
        $post_id = false;
    }
    $value = $post_id ? get_post_meta( $post_id, 'unique_field_key', true ) : '';

    return GenerateBlocks_Dynamic_Tag_Callbacks::output( $value, $options, $instance );
}
```

Need a relationship hop? Offer the source as a [chain root](#1-offering-your-source-as-a-chain-root) and let the base tags' source paths do it, rather than reading the relationship field in your callback.

### Narrowing who may see an entity

If your plugin decides who may see a post (a membership, an audience, a per-page rule), filter `bws_dynamic_tags_source_visible`. Every check `bws_is_source_usable()` makes, in the base tags and in your own, then applies your rule as well.

```php
add_filter( 'bws_dynamic_tags_source_visible', function ( $ok, $source ) {
    if ( ! $ok || 'post' !== $source['kind'] ) {
        return $ok;
    }
    return $ok && my_plugin_viewer_may_see( (int) $source['id'] );
}, 10, 2 );
```

- **It can only hide.** It runs after the built-in check has passed, so it never sees an entity that check refused, and returning `true` cannot reveal one.
- **Return `$ok && your answer`, and return `$ok` unchanged for anything you do not judge.** WordPress hands each callback the previous one's result, so a callback that returns a bare `true` undoes a refusal another plugin made earlier in the chain.
- **It runs once per entity, for every kind.** Check `$source['kind']` and pass through the kinds you do not handle. `$source` carries the `kind` and the `id`; anything else your rule needs, such as the current page or user, comes from the request.
- **Nothing is cached here.** If your check is costly, memoize it per request yourself.
- **The editor preview runs the same check**, with whatever context the editor's request resolves.
- **A refused post blanks this plugin's tags and nothing else.** Inside a GenerateBlocks query loop, the row for a refused post still renders, along with GenerateBlocks' own tags in it. Keeping the post out of the loop is a job for the loop's query. A `get_field()` or `get_post_meta()` call outside these tags never passes through this filter either.

### Rendering block content

A tag that renders another post's block content should use this plugin's content pipeline rather than calling `do_blocks()` itself. The pipeline guards against recursion, extracts inline CSS, falls back when memory runs short, and unsets the GB options that would corrupt HTML.

```php
function my_plugin_content_callback( $options, $block, $instance ) {
    // Skip GB's query-loop setup pass, which runs before postId is in context.
    if ( bws_is_query_loop_setup_phase( $instance ) ) {
        return '';
    }

    $source  = \BWS\DynamicTags\SourceRegistry::get_source( 'external' );
    $post_id = $source ? $source->resolve_id( $options, $instance ) : false;
    if ( ! $post_id || ! bws_is_source_usable( array( 'kind' => 'post', 'id' => $post_id ) ) ) {
        return '';
    }

    return bws_safe_content_output( bws_process_post_content( $post_id ), $options, $instance );
}
```

| Function | Purpose |
|---|---|
| `bws_process_post_content( $post_id, $args = array() )` | Render a post's block content through the pipeline. Returns `''` on failure. |
| `bws_render_block_content( $raw, $cache_key, $args = array() )` | The same pipeline for markup that is not a post's `post_content` (for example markup stored in an option). Key the cache as `'post:'.$id` or `'option:'.$key`. |
| `bws_safe_content_output( $content, $options, $instance )` | Pass rendered HTML through GB's `output()` without the options that would corrupt it. |
| `bws_is_query_loop_setup_phase( $instance )` | `true` during GB's query-loop setup pass, when `postId` is not yet in context. |

| Filter | Default | Purpose |
|---|---|---|
| `bws_content_memory_threshold` | `0.80` | Fraction of `memory_limit` below which the full render runs. At or above it, rendering falls back to CSS extraction only. |
| `bws_content_max_recursion_depth` | `3` | Content nesting depth beyond which further renders are blocked. |

**`bws_is_source_usable()` and its `bws_dynamic_tags_source_visible` filter, these four functions, the two filters, and the classes and hooks this guide names are the supported surface.** Every other `bws_` function is internal and may change without notice. The pipeline itself: [`post-content-processing-reference.md`](post-content-processing-reference.md).

---


## 4. Migrating your own old tag names

When your plugin renames or retires a tag it shipped, register a **migration entry** so this plugin's Migration Tool can rewrite the old name in saved content:

```php
add_action( 'bws_dynamic_tags_register_sources', function () {
    \BWS\DynamicTags\DeprecatedTagRegistry::register( array(
        'old_tag'        => 'oldname_post_meta',
        'new_tag'        => 'text',
        'since'          => '2.0.0',
        'source_inject'  => 'oldname',                       // src value to add; '' adds none
        'option_renames' => array( 'field_key' => 'key' ),
        'lifecycle'      => 'deprecated',                    // a replacement exists; see below
    ) );
} );
```

**The old name does not render.** A migration entry is a rewrite rule only; nothing registers the old name with GenerateBlocks, so saved tags using it render literally until migrated. Keep registering the old name yourself if it must keep working meanwhile.

**The Migration Tool only rewrites names it can prove are ours to rewrite.** If your plugin still registers the old name with GenerateBlocks, the tool declines it and the scan report says so, until the site owner opts that name in.

| Key | Notes |
|---|---|
| `old_tag` | Required. The tag name to find in content. |
| `new_tag` | Required. The tag name it becomes. |
| `since` | Version the tag was deprecated in, shown on the settings page. |
| `source_inject` | `src` value added on migration. `''` adds none. |
| `option_renames` | Old option key to new option key. |
| `value_renames` | Per (renamed) key, old value to new value. Applied after `option_renames`. |
| `combine_options` | New key to `array( 'when_present' => $flag_key, 'value_from' => $value_key )`: folds a presence flag and a value into one key. Applied before `option_renames`. |
| `fixed_options` | Key/value pairs always added on migration. |
| `transform_callback` | `fn( string $tag_string ): string` that replaces all of the above, for a reshape the declarative keys cannot express. Return the string unchanged when there is nothing to do. |
| `lifecycle` | `'deprecated'` or `'retired'`; any other value raises a `_doing_it_wrong()` notice and is filed as Retired. Files the entry under **Deprecated Tags** or **Retired Tags** on the settings page. `'deprecated'`: a replacement exists and the old tag still works as before. `'retired'`: unsupported. Defaults to `'retired'`, so an entry that does not say is filed as Retired. |

`callback`, `prefix_removed`, `title`, `supports`, `options`, `description` and `gb_type` are accepted for compatibility and unused. In particular a `callback` no longer files an entry under Deprecated; set `lifecycle` instead.

The Migration Tool on the settings page scans every non-revision post, lists the tags each would migrate, and rewrites per post or in bulk. A post whose content would not change is not rewritten, and a revision is saved first where the post type supports revisions. `DeprecatedTagRegistry::has_migration_path( $old_tag )` is `true` for any entry with a non-empty `new_tag`: a pure rename, with every option carried over unchanged, is a complete migration.

---

## 5. Reacting to a direct content write

The Migration Tool writes migrated content **straight to the posts table**, deliberately: no duplicate revision, no bumped modified date, and no third-party save listener treating maintenance as a human edit. The cost is that nothing downstream is told. If you keep a cache derived from `post_content`, this action is how you hear about it.

```php
add_action( 'bws_dynamic_tags_content_written', function ( $post_id, $content ) {
    my_plugin_refresh_cache_for( $post_id, $content );
}, 10, 2 );
```

| Arg | Type | Meaning |
|---|---|---|
| `$post_id` | `int` | Post whose content was rewritten. |
| `$content` | `string` | The new content, exactly as written. |

**Use the `$content` you are handed; do not re-read the post.** No `WP_Post` is passed on purpose: the object the Migration Tool holds carries the content from before the rewrite, and the post cache has already been cleared.

**No save hooks fired.** Do not assume `save_post`, `wp_after_insert_post` or anything else ran for this write.

**It names the fact, not the cause.** Any direct content write in the plugin fires it, not only the Migration Tool. It is an announcement, not a facility: the plugin drives no one's cache, and its own pattern-cache repair does not depend on it ([`tag-reference.md` §Pattern cache reconcile](tag-reference.md#pattern-cache-reconcile)).

---

## 6. Diagnostic filters

Developer switches with no admin control. Each is off unless a filter returns true; `WP_DEBUG` does not turn any on.

```php
add_filter( 'bws_dynamic_tags_benchmark_logging', '__return_true' );
```

| Filter | Effect when true |
|---|---|
| `bws_dynamic_tags_benchmark_logging` | Post content processing logs its time and memory use to the PHP error log, prefixed `[BWS Content]`. Covers post content only, not tag resolution. |
| `bws_dynamic_tags_registration_logging` | Source registration and the `bws_dynamic_tags_register_sources` action log to the PHP error log. |
| `bws_dynamic_tags_show_all_deprecated` | The settings page lists every registered deprecated or retired tag and option, not only those the last scan found in site content. |

---

<a id="2-registering-a-context-modifier"></a>
<a id="9-migrating-a-modifier-family-to-a-base-tag"></a>

## Retired integration routes

**Modifier families.** `TagTemplateRegistry::register_modifier()`, which minted a prefixed copy of the base tags (`example_text`, `example_image`), was deleted in 1.22.0; a leftover call is a fatal error. Offer the source as a [chain root](#1-offering-your-source-as-a-chain-root) instead. Saved `{{example_text}}` tags no longer render and nothing here migrates them, so rewrite them to base tags before retiring your prefix: `{{example_text key:bio}}` becomes `{{text src:example|key:bio}}`. The mapping this plugin applied to its own `term_*` tags is in [`deprecated-tags-options.md`](deprecated-tags-options.md#modifier-prefix--base-tag-with-a-registered-root-1170).

**Internal, not integration surface:** `TagTemplateRegistry::register_modifier_template()` and `bws_register_modifier_root_migrations()`. Both still exist for this plugin's own use and may change without notice.
