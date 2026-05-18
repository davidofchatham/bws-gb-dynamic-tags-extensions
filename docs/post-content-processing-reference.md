# Post Content Processing in WordPress Dynamic Tags
## Technical Reference & Lessons Learned

**Context:** BWS Dynamic Tags for GenerateBlocks

> **Historical note.** Earlier sections of this document (now at the bottom under [§Pre-Plugin-Integration History](#pre-plugin-integration-history)) record findings from standalone-script versions before this code was packaged as a plugin. The internal version numbers there (`v1.1.0`–`v1.2.3`) refer to those standalone scripts, **not** the plugin's release versions. Content-rendering inside dynamic tags has been the single most difficult area of the codebase to get right; the history block is kept as a record of approaches tried.
>
> The sections above the history block describe the **current** (plugin v1.6.0) implementation.

---

## Executive Summary

Rendering a post's content from inside a dynamic tag is harder than it looks: nested block rendering, GB query loop setup phases, and cross-post CSS inlining all conspire against a naive `do_blocks()` call. The plugin uses a single primary pipeline with one automatic fallback path for low-memory conditions, plus a recursion-protection stack to cut cycles. This document captures the current shape of that pipeline and the constraints driving it.

---

## Table of Contents

1. [Pipeline overview](#pipeline-overview)
2. [Primary pipeline](#primary-pipeline)
3. [Fallback pipeline](#fallback-pipeline)
4. [Recursion protection](#recursion-protection)
5. [Memory check](#memory-check)
6. [Query loop setup phase detection](#query-loop-setup-phase-detection)
7. [Cross-post inline CSS handling](#cross-post-inline-css-handling)
8. [Safe output (post-pipeline)](#safe-output-post-pipeline)
9. [Debug logging](#debug-logging)
10. [Public API summary](#public-api-summary)
11. [Pre-plugin-integration history](#pre-plugin-integration-history)

---

## Pipeline overview

All `content`-template callbacks (base `content`, `term_content` modifier, `try_content` slots, deprecated wrappers that resolve to content) funnel through one helper:

```
bws_process_post_content( $post_id )
   ├── early returns: invalid post_id, recursion-blocked
   ├── memory below threshold? → bws_process_post_content_fallback()
   └── primary path:
         bws_start_processing_post()
         get_post_field( 'post_content' )
         do_blocks()
         wpautop()
         bws_extract_and_queue_inline_styles()
         bws_sanitize_rich_content()
         bws_end_processing_post()
```

Callback sites wrap the helper with two safeties before calling it:

```php
function bws_content_callback( $options, $block, $instance ) {
    // ... resolve $post_id via source ...
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

    // Strips trunc/case/wpautop/link before GB's output().
    return bws_safe_content_output( $content, $options, $instance );
}
```

There is **no** "processing mode" tag option. Earlier versions exposed Basic/Limited/Full modes; those were removed when the plugin consolidated on a single primary pipeline with automatic fallback.

---

## Primary pipeline

`bws_process_post_content( int $post_id, array $args = array() ): string`

Located in `includes/helpers/content-helpers.php`. Steps in order:

1. **Recursion guard** — `bws_can_process_post_content( $post_id )` returns `false` when the post is already in the stack or stack depth ≥ 3. Returns `''` if blocked.
2. **Memory check** — `bws_has_sufficient_memory()` (≥ 20% free, i.e. usage < 80% of `memory_limit`). On failure: delegate to fallback pipeline and return.
3. **Stack push** — `bws_start_processing_post( $post_id )`.
4. **Read raw content** — `get_post_field( 'post_content', $post_id )`. Returns `''` if empty.
5. **`do_blocks()`** — runs GB and other block renderers. Resolves nested dynamic tags and shortcodes via the standard `render_block` filter chain.
6. **`wpautop()`** — paragraph wrapping. Runs *after* `do_blocks()` so block-emitted HTML is intact when paragraph rules apply.
7. **Inline-style extraction** — `bws_extract_and_queue_inline_styles()` pulls any `<style>` elements GB inlined inside content (see [§Cross-post inline CSS handling](#cross-post-inline-css-handling)) and queues them for `wp_footer`.
8. **Sanitize** — `bws_sanitize_rich_content()` runs `wp_kses_post()` with GB's expanded allowed HTML filter temporarily added.
9. **Stack pop** — `bws_end_processing_post( $post_id )`.

Returns the rendered HTML, or `''` on early exit.

---

## Fallback pipeline

`bws_process_post_content_fallback( int $post_id, array $args = array() ): string`

Used automatically when memory is below threshold at the entry to `bws_process_post_content()`. `do_blocks()` is too expensive to run safely, so dynamic tags inside the content go **unresolved** and we approximate styling from raw block JSON instead. Steps:

1. Read raw content via `get_post_field()`.
2. `bws_extract_css_from_block_comments()` — parses `<!-- wp:... { ... } -->` JSON attributes for any `"css"` property and concatenates them.
3. `bws_strip_block_comments()` — removes the `<!-- wp:... -->` and `<!-- /wp:... -->` delimiters, leaving inner HTML.
4. `bws_strip_dynamic_tags()` — removes any unresolved `{{tag ...}}` placeholders (they would render as literal text without `do_blocks()`).
5. `wpautop()` + `bws_sanitize_rich_content()`.
6. Queues the extracted CSS via `bws_queue_inline_css()` if any was found.

Trade-off: layout is approximate (no block-level rendering), dynamic content inside the post is dropped, but the page still renders rather than crashing on OOM.

---

## Recursion protection

The recursion guard is a single global stack: `$GLOBALS['bws_content_processing_stack']`.

```php
function bws_can_process_post_content( $post_id ) {
    $stack = $GLOBALS['bws_content_processing_stack'] ?? array();
    return ! in_array( $post_id, $stack, true ) && count( $stack ) < 3;
}
```

Rules:
- **Block** when the post is already on the stack (circular reference: A → B → A).
- **Block** when the stack is already at depth 3 (cap on nesting).
- **Allow** in all other cases — including a query loop where each iteration displays its own content (each iteration pushes and pops cleanly).

The stack lives for the duration of one request. `bws_start_processing_post()` appends; `bws_end_processing_post()` removes by value via `array_search` + `array_splice` (not LIFO pop — guards against unbalanced pairings during exception unwinding).

**What the stack does NOT do:** it does not block a post from rendering its own content at the top level. The original "self-reference" check (returning false when `get_the_ID() === $post_id`) was flawed — `setup_postdata()` shifts `get_the_ID()` underneath you, blocking valid query-loop usage. The stack alone is sufficient.

---

## Memory check

`bws_has_sufficient_memory(): bool` returns `true` when current usage is below 80% of `memory_limit`:

```php
function bws_has_sufficient_memory() {
    $limit_str = ini_get( 'memory_limit' );
    if ( '-1' === $limit_str ) {
        return true;
    }
    $limit = wp_convert_hr_to_bytes( $limit_str );
    if ( $limit <= 0 ) {
        return true;
    }
    return ( memory_get_usage( true ) / $limit ) < 0.80;
}
```

`-1` (no limit) and indeterminate limits both pass as "sufficient". Only a positive numeric limit triggers the gate.

Called once at the top of `bws_process_post_content()`. There is no per-step recheck — once primary is chosen, it runs to completion.

---

## Query loop setup phase detection

GB query loops execute their template multiple times per page render. The first call(s) carry the **parent page's** `postId` in `$instance->context`, not the loop item. Rendering content during setup would output the parent page's content as if it were the loop item.

`bws_is_query_loop_setup_phase( $instance ): bool`:

```php
if ( ! isset( $instance->context['queryId'] ) ) {
    return false; // Not in a query loop at all.
}
$context_post_id = $instance->context['postId'] ?? null;
if ( null === $context_post_id ) {
    return true;  // queryId set, postId not — setup.
}
return (int) $context_post_id === (int) get_the_ID();
```

Returns `true` (skip processing) when:
- We're in a query (`queryId` present) AND
- `postId` is missing OR matches the global post (the outer page).

Callers short-circuit with `return ''` when this returns `true`. The "real iteration" calls always have a `postId` distinct from the outer page's `get_the_ID()` and pass through.

---

## Cross-post inline CSS handling

When `do_blocks()` runs against a post **other** than the current page's post — e.g., a related-post `content` tag — GB cannot enqueue block CSS through `wp_head` (which has already fired). It falls back to inlining `<style>` elements directly before each block's HTML.

`wp_kses_post()` then strips the `<style>` tags but leaves the raw CSS text behind, which renders as visible page content. Helpers:

- `bws_extract_and_queue_inline_styles( string $content ): string` — regex-extracts `<style>...</style>` blocks from the content string, concatenates their bodies, and hands them to `bws_queue_inline_css()`. Returns content with the `<style>` elements removed.
- `bws_queue_inline_css( string $css )` — appends to `$GLOBALS['bws_queued_inline_css']` and (on first call) hooks `bws_output_queued_inline_css` to `wp_footer` priority 5.
- `bws_output_queued_inline_css()` — emits the accumulated CSS as one `<style id="bws-dynamic-content-inline-css">` element in the footer, then clears the buffer.

Net effect: cross-post rendered content keeps its block styling, but the CSS moves to one consolidated `<style>` element after the page body — out of the content stream where `wp_kses_post()` was eating it.

---

## Safe output (post-pipeline)

GB's `GenerateBlocks_Dynamic_Tag_Callbacks::output()` applies value-shaping options (`trunc`, `case`, `wpautop`, `link`) that are safe for short text but destructive on rich HTML:

- `trunc` — `substr()` cuts mid-tag, breaking HTML.
- `case` — `strtolower()` corrupts HTML attribute syntax and CSS within `<style>` (if any survived sanitize).
- `wpautop` — pipeline already ran it once; second pass shifts whitespace inside block markup.
- `link` — wrapping rendered HTML inside `<a>` produces invalid markup.

`bws_safe_content_output()` strips these four keys from the options array before calling GB's `output()`, preserving the `generateblocks_dynamic_tag_output` filter hook for third-party compatibility:

```php
function bws_safe_content_output( $content, $options, $instance ) {
    $safe_options = $options;
    unset( $safe_options['trunc'], $safe_options['case'], $safe_options['wpautop'], $safe_options['link'] );
    return GenerateBlocks_Dynamic_Tag_Callbacks::output( $content, $safe_options, $instance );
}
```

Always use this helper for content-template callbacks. Other text-template callbacks (`text`, `title`, etc.) call `output()` directly since their values are short scalars where the standard options are appropriate.

---

## Debug logging

`bws_content_debug( string $message )` is **gated solely** by the admin setting "Enable benchmark logging" (`SettingsPage::is_benchmark_logging_enabled()`). `WP_DEBUG` no longer activates it — that was changed because debug-on-WP_DEBUG bypassed the user-facing toggle entirely.

When enabled, output goes to `error_log()` prefixed `[BWS Content]`.

Benchmark helpers:
- `bws_content_debug_start( int $post_id ): array` — captures `microtime(true)` + `memory_get_usage(true)`; returns empty array when logging disabled.
- `bws_content_debug_end( int $post_id, array $start_data )` — logs `post_id=N time=Xms mem_delta=±Y stack_depth=D`. No-op when `$start_data` is empty.

`bws_process_post_content()` calls these around the primary pipeline body so per-post timing/memory is logged for benchmarking. Recursion-blocked, OOM-fallback, and empty-content paths are logged via plain `bws_content_debug()` calls.

---

## Public API summary

All in `includes/helpers/content-helpers.php`. Guarded with `function_exists()` for safe re-loads.

| Function | Returns | Purpose |
|---|---|---|
| `bws_process_post_content( $post_id, $args = [] )` | `string` | Primary entry point. Full pipeline; auto-fallback on low memory. |
| `bws_process_post_content_fallback( $post_id, $args = [] )` | `string` | Low-memory path. CSS-extraction-from-JSON + dynamic-tag stripping. Called by `bws_process_post_content`; rarely called directly. |
| `bws_can_process_post_content( $post_id )` | `bool` | Recursion + depth check. |
| `bws_start_processing_post( $post_id )` | `void` | Push onto recursion stack. |
| `bws_end_processing_post( $post_id )` | `void` | Pop from recursion stack (by value, not LIFO). |
| `bws_has_sufficient_memory()` | `bool` | < 80% of `memory_limit`. |
| `bws_is_query_loop_setup_phase( $instance )` | `bool` | Detect parent-page-context calls during GB query loop setup. |
| `bws_safe_content_output( $content, $options, $instance )` | `string` | Final output stage; strips destructive GB options before `output()`. |
| `bws_queue_inline_css( $css )` | `void` | Append CSS to `wp_footer`-deferred buffer. |
| `bws_extract_and_queue_inline_styles( $content )` | `string` | Pull `<style>` from content; queue via above. |
| `bws_extract_css_from_block_comments( $content )` | `string` | Fallback-pipeline helper: parse `"css"` from block-JSON. |
| `bws_strip_block_comments( $content )` | `string` | Fallback-pipeline helper. |
| `bws_strip_dynamic_tags( $content )` | `string` | Fallback-pipeline helper: strip unresolved `{{...}}`. |
| `bws_sanitize_rich_content( $content )` | `string` | `wp_kses_post` with GB's expanded allowed HTML temporarily filtered. |
| `bws_content_debug( $message )` | `void` | Gated by benchmark-logging admin toggle. |
| `bws_content_debug_start( $post_id )` | `array` | Capture timing/memory baseline. |
| `bws_content_debug_end( $post_id, $start )` | `void` | Log delta. |

---

## Pre-plugin-integration history

> **Caveat.** Everything below this point describes the **standalone-script** era (versions internally numbered v1.1.0–v1.2.3) before the code was packaged as a plugin. Several mechanisms documented here — three-tier Basic/Limited/Full modes, Query Monitor auto-downgrade, the `processing_level` tag option, the self-reference recursion check — were **removed** when consolidating the pipeline. Kept as record of approaches tried and discoveries that still inform the current design.

### Core technical discoveries (still applicable)

#### 1. Block instance is an object, not an array

GenerateBlocks passes `$instance` as a `WP_Block` object:

```php
// WRONG - Fatal error
$post_id = $instance['context']['postId'];

// CORRECT
$post_id = $instance->context['postId'];
```

#### 2. The `do_blocks()` double-processing problem

Calling `do_blocks()` on content already being rendered by a parent block context triggers duplicate tag evaluations and apparent recursion. The stack guard plus the query-loop-setup-phase check together cover this — there is no longer a separate "limited mode" that skips `do_blocks()`.

#### 3. Query loop renders multiple times

WP query loops render their template 3+ times: setup phases (parent context) + actual iterations (loop item context). Setup phases must be detected and skipped — see [§Query loop setup phase detection](#query-loop-setup-phase-detection) above for the current implementation.

#### 4. `WP_Block` context shape

```
WP_Block {
    name: string              // e.g., 'generateblocks/text'
    parsed_block: array       // Raw block data
    context: array {
        postId: int|null      // Current post in context
        queryId: int|null     // Query loop ID if present
    }
    inner_blocks: array
}
```

### Removed mechanisms

**Three-tier processing modes** (Basic / Limited / Full) — gone. The single primary pipeline now does what "Limited" did (skip the full `the_content` filter chain, run `do_blocks` + `wpautop` only). The fallback pipeline replaced "Basic". "Full" (running the entire `the_content` filter stack) was dropped: it triggered OOM too often and the filters that mattered for rendering were already covered by `do_blocks`.

**Query Monitor auto-downgrade** — gone. QM detection added noise without preventing the issues it was meant to mitigate; once shortcode processing moved inside `do_blocks` via standard filters, the QM-specific edge case stopped mattering.

**`processing_level` tag option** — gone. There is no per-tag mode selector now; all callers go through `bws_process_post_content()`.

**Shortcode processing toggle** — gone. Shortcodes inside block content are handled by `do_blocks()` via the `render_block` filter chain, which is the WordPress-standard path. The standalone version had its own `do_shortcode()` call gated by an option; that was removed.

**Self-reference recursion check** (`get_the_ID() === $post_id` while stack non-empty) — gone. `setup_postdata()` reassigns `get_the_ID()` mid-loop, causing this check to false-positive on legitimate query-loop usage. The stack-membership check alone is sufficient and correct.

### Standalone-era version log

`v1.2.3` — Query loop setup-phase detection added. Replaced ad-hoc context comparisons with a single helper.

`v1.2.2` — Removed flawed self-reference recursion check. Stack-only guard.

`v1.2.1` — Fixed `$instance` array vs object access errors.

`v1.2.0` — Three-tier processing modes (Basic/Limited/Full), Query Monitor detection, memory thresholding for mode selection. **Removed** during plugin integration; superseded by single-pipeline-plus-fallback.

`v1.1.0` — Initial standalone implementation. Basic/Limited/Full modes, stack recursion protection, length truncation.

### Pitfalls that still bite

- **`$instance` is an object.** Property access (`->`) not array access (`[]`).
- **Don't call `do_blocks()` outside the stack guard.** Always gate with `bws_can_process_post_content()` or call via `bws_process_post_content()` which gates internally.
- **Don't skip the query-loop-setup check.** Callers that resolve `$post_id` from `$instance->context['postId']` get wrong content during setup phases without it.
- **Don't pipe content through GB's `output()` directly.** Use `bws_safe_content_output()` — `trunc`/`case`/`wpautop`/`link` corrupt rich HTML.
- **Cross-post `do_blocks` inlines `<style>`.** `wp_kses_post` strips the tags but leaves CSS visible. `bws_extract_and_queue_inline_styles` handles this; don't bypass it.
