# Post Content Processing in WordPress Dynamic Tags
## Technical Reference & Lessons Learned

**Version:** 1.2.3  
**Date:** February 2026  
**Context:** BWS Dynamic Tags for GenerateBlocks/GeneratePress

---

## Executive Summary

Post content processing in WordPress is complex due to nested block rendering, query loop setup phases, and memory-intensive content filtering. This document captures critical learnings from debugging and optimizing `post_content`, `related_post_content`, and `portal_post_content` dynamic tags.

---

## Table of Contents

1. [Core Technical Discoveries](#core-technical-discoveries)
2. [Processing Modes & Memory Management](#processing-modes--memory-management)
3. [Recursion & Safety Protection](#recursion--safety-protection)
4. [Query Loop Context Handling](#query-loop-context-handling)
5. [Block Instance Structure](#block-instance-structure)
6. [Best Practices](#best-practices)
7. [Common Pitfalls](#common-pitfalls)
8. [Debugging Strategies](#debugging-strategies)

---

## Core Technical Discoveries

### 1. Block Instance is an Object, Not an Array

**Critical Discovery:**  
GenerateBlocks passes `$instance` as a `WP_Block` object, not an array.

```php
// ❌ WRONG - Fatal error
$post_id = $instance['context']['postId'];

// ✅ CORRECT
$post_id = $instance->context['postId'];
```

**Impact:** Any code treating `$instance` as an array will crash. Always use object property access.

### 2. The `do_blocks()` Double-Processing Problem

**Problem:**  
Calling `do_blocks()` when content is already being rendered by a parent block (like GeneratePress Dynamic Content) causes nested block rendering, triggering duplicate tag evaluations.

**Symptom:**  
The same `{{post_content}}` tag fires multiple times with the same post ID, creating recursion attempts.

**Solution:**  
Remove `do_blocks()` from processing modes when content is already in a block rendering context.

```php
// ❌ CAUSES NESTED RENDERING
function bws_get_limited_post_content( $post_id, $process_shortcodes = false ) {
    $content = get_the_content();
    $content = do_blocks( $content ); // Re-renders blocks already being rendered
    return $content;
}

// ✅ SAFE - No nested rendering
function bws_get_limited_post_content( $post_id, $process_shortcodes = false ) {
    $content = get_the_content();
    $content = wpautop( $content ); // Just formatting
    return $content;
}
```

### 3. Query Loop Has Setup Phases

**Critical Discovery:**  
WordPress Query Loop blocks render their template **3+ times**:

1. **Setup/Initialization Phase(s)** - Context matches parent page or is missing
2. **Actual Iteration** - Context contains the loop item's post ID

**Log Evidence:**
```
Call 1: context=2810, global=2810 [Setup - parent page]
Call 2: context=not set, global=2810 [Setup - no context]  
Call 3: context=72195, global=72195 [Real iteration - loop item]
Call 4: context=72203, global=72203 [Real iteration - loop item]
```

**Solution:**  
Detect and skip setup phases to avoid processing the parent page's content instead of loop items.

```php
// Detect query loop setup phase
$context_post_id = isset( $instance->context['postId'] ) ? $instance->context['postId'] : null;
$global_post_id = get_the_ID();

// Skip if context matches global (setup phase) and we're in a query
if ( $context_post_id && $context_post_id === $global_post_id && 
     isset( $instance->context['queryId'] ) ) {
    return ''; // Skip setup, only process during actual iteration
}
```

---

## Processing Modes & Memory Management

### Three-Tier Processing System

**Basic Mode (Recommended Default):**
- Raw content + `wpautop()`
- Optional shortcode processing (off by default)
- Optional block comment stripping
- ~1-5MB memory footprint
- Safe for nested content

**Limited Mode (Use with Caution):**
- ~~Includes `do_blocks()`~~ **REMOVED** - causes nested rendering
- Core WordPress formatting (wptexturize, wpautop, etc.)
- Optional shortcode processing
- ~10-50MB memory footprint
- Risk of memory exhaustion with complex content

**Full Mode (High Risk):**
- Complete `the_content` filter pipeline
- Includes all plugins' content filters
- Optional shortcode processing override
- ~50-500MB memory footprint
- **Frequently causes memory exhaustion**
- Only use when explicitly needed and tested

### Memory Protection Strategy

**Three-Layer Protection:**

1. **Environment Detection:**
```php
// Auto-downgrade to Basic mode if Query Monitor active
if ( bws_is_query_monitor_active() ) {
    return 'basic';
}

// Check memory constraint
if ( ( memory_get_usage(true) / wp_convert_hr_to_bytes(ini_get('memory_limit')) ) > 0.5 ) {
    return 'basic';
}
```

2. **Pre-Processing Memory Check:**
```php
function bws_has_sufficient_memory() {
    $memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
    $current_usage = memory_get_usage( true );
    
    // Require at least 20% free (allow up to 80% usage)
    return ( $current_usage / $memory_limit ) <= 0.8;
}
```

3. **Stack Depth Limits:**
```php
// Maximum 3 levels of nesting
if ( count( $bws_content_processing_stack ) >= 3 ) {
    return false;
}
```

### Shortcode Processing Considerations

**Rule:** Shortcodes are **disabled by default** and should only be enabled when:
1. Explicitly requested by user
2. NOT in Query Monitor context (prevents debug panel corruption)
3. Memory sufficient for additional processing

```php
// Shortcode protection
if ( $process_shortcodes && ! bws_is_query_monitor_active() ) {
    $content = do_shortcode( $content );
}
```

---

## Recursion & Safety Protection

### The Processing Stack

**Global tracker prevents infinite loops:**

```php
// Global stack
$GLOBALS['bws_content_processing_stack'] = [2810, 72195];

// Before processing
if ( in_array( $post_id, $bws_content_processing_stack, true ) ) {
    return false; // Already processing this post
}

// During processing
bws_start_processing_post( $post_id );  // Adds to stack
// ... process content ...
bws_end_processing_post( $post_id );    // Removes from stack
```

### Why Self-Reference Check Was Removed

**Original (flawed) approach:**
```php
// ❌ WRONG - Blocks legitimate query loop usage
if ( count( $stack ) > 0 && get_the_ID() === $post_id ) {
    return false; // Blocks valid scenarios
}
```

**Problem:**  
- `setup_postdata()` changes what `get_the_ID()` returns
- Blocks query loop items from displaying their own content
- Redundant with recursion check

**Correct approach:**  
Use only the stack recursion check - it's sufficient.

### Recursion Scenarios Correctly Handled

✅ **Allowed:**
- Page displays its own content (top-level, stack empty)
- Query loop items each display their own content
- Post A displays Post B's content

❌ **Blocked:**
- Post A tries to display Post A while already processing A
- Post A → Post B → Post A (circular reference)
- More than 3 levels of nesting

---

## Query Loop Context Handling

### Block Context Priority

**Always check multiple sources in priority order:**

```php
// 1. Block context (query loop provides this)
if ( isset( $instance->context['postId'] ) && $instance->context['postId'] ) {
    $post_id = absint( $instance->context['postId'] );
}

// 2. GenerateBlocks method (source selection)
if ( ! $post_id ) {
    $post_id = GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
}

// 3. Fallback to global post
if ( ! $post_id ) {
    $post_id = get_the_ID();
}
```

### Query Loop Setup Phase Detection

**Key indicators of setup vs iteration:**

| Phase | `context['postId']` | `get_the_ID()` | `context['queryId']` |
|-------|---------------------|----------------|----------------------|
| Setup | Matches parent OR missing | Parent page ID | Present |
| Iteration | Loop item ID | Loop item ID | Present |
| Outside Loop | Not set | Current page | Not set |

**Detection logic:**

```php
$context_post_id = isset( $instance->context['postId'] ) ? $instance->context['postId'] : null;
$global_post_id = get_the_ID();
$in_query = isset( $instance->context['queryId'] );

// Skip setup phase
if ( $in_query && ( ! $context_post_id || $context_post_id === $global_post_id ) ) {
    return ''; // Setup phase - don't process
}
```

### Why Setup Phases Exist

WordPress renders query loop templates during:
1. **Block parsing** - Understanding template structure
2. **Placeholder rendering** - Editor preview
3. **Query setup** - Before iteration begins
4. **Actual iteration** - Real content rendering

Only #4 should actually process content.

---

## Block Instance Structure

### WP_Block Object Properties

```php
WP_Block {
    name: string              // e.g., 'generateblocks/text'
    parsed_block: array       // Raw block data
    context: array {          // Inherited context
        postId: int|null      // Current post in context
        queryId: int|null     // Query loop ID if present
        // ... other context
    }
    inner_blocks: array       // Nested blocks
    // ... other properties
}
```

### Accessing Context Safely

```php
// ✅ Safe access with fallback
$post_id = isset( $instance->context['postId'] ) ? 
    absint( $instance->context['postId'] ) : null;

// ✅ Check multiple context properties
$in_query = isset( $instance->context['queryId'] );
$has_post_context = isset( $instance->context['postId'] );

// ❌ Don't assume context exists
$post_id = $instance->context['postId']; // May error if context missing
```

---

## Best Practices

### 1. Processing Mode Selection

**Default to Basic:**
```php
'default' => 'auto',  // Auto-selects Basic in most cases
```

**Only use Limited/Full when:**
- Specific visual/functional requirements demand it
- Thoroughly tested with representative content
- Memory limits confirmed adequate
- Not in nested rendering contexts

### 2. Always Implement Safety Checks

**Required checks before processing:**
```php
// 1. Valid post ID
if ( ! $post_id ) return $fallback;

// 2. Can process (not in recursion)
if ( ! bws_can_process_post_content( $post_id ) ) return $fallback;

// 3. Memory available
if ( ! bws_has_sufficient_memory() ) return $fallback;

// 4. Not in query setup phase
if ( $in_query_setup ) return '';
```

### 3. Comprehensive Debug Logging

**Log at decision points:**
```php
bws_content_debug( sprintf(
    "Processing %d: mode=%s, stack=[%s], context=%s",
    $post_id,
    $mode,
    implode(',', $stack),
    $context_post_id ?? 'none'
) );
```

**What to log:**
- Post ID source (context, method, fallback)
- Processing mode selected
- Stack state before/after
- Memory usage at checkpoints
- Setup phase detection

### 4. Graceful Degradation

**Never fail fatally:**
```php
// ✅ Return fallback or empty string
if ( $problem ) {
    bws_content_debug( "Problem: $description" );
    return $fallback_text;
}

// ❌ Don't throw exceptions or return null
if ( $problem ) {
    throw new Exception( "Fatal error" );
}
```

### 5. Content Sanitization

**Always sanitize output:**
```php
// Use GenerateBlocks' expanded allowed HTML
add_filter( 'wp_kses_allowed_html', 
    [ 'GenerateBlocks_Dynamic_Tags', 'expand_allowed_html' ], 10, 2 );
$content = wp_kses_post( $content );
remove_filter( 'wp_kses_allowed_html', 
    [ 'GenerateBlocks_Dynamic_Tags', 'expand_allowed_html' ], 10, 2 );
```

---

## Common Pitfalls

### 1. Treating $instance as Array
```php
// ❌ Fatal error
$post_id = $instance['context']['postId'];

// ✅ Correct
$post_id = $instance->context['postId'];
```

### 2. Using do_blocks() in Nested Context
```php
// ❌ Causes duplicate rendering
function limited_content( $post_id ) {
    $content = get_the_content();
    return do_blocks( $content ); // Already being rendered!
}

// ✅ Correct
function limited_content( $post_id ) {
    $content = get_the_content();
    return wpautop( $content ); // Just formatting
}
```

### 3. Processing Query Setup Phases
```php
// ❌ Processes parent page instead of loop items
if ( $in_query ) {
    return bws_get_content( $post_id );
}

// ✅ Skip setup phases
if ( $in_query && $context_matches_global ) {
    return ''; // Skip, only process during iteration
}
```

### 4. Insufficient Memory Checks
```php
// ❌ No protection
return apply_filters( 'the_content', $content );

// ✅ Check first
if ( ! bws_has_sufficient_memory() ) {
    return bws_get_basic_content( $post_id );
}
```

### 5. Missing Recursion Protection
```php
// ❌ No stack tracking
function get_content( $post_id ) {
    return process_content( $post_id );
}

// ✅ Track processing
function get_content( $post_id ) {
    bws_start_processing_post( $post_id );
    $content = process_content( $post_id );
    bws_end_processing_post( $post_id );
    return $content;
}
```

### 6. Assuming Source is Set
```php
// ❌ May be empty
$source = $options['source'];

// ✅ Check with fallback
$source = $options['source'] ?? 'not set';
```

### 7. Shortcodes Without Protection
```php
// ❌ Can corrupt Query Monitor
if ( $process_shortcodes ) {
    $content = do_shortcode( $content );
}

// ✅ Check environment
if ( $process_shortcodes && ! bws_is_query_monitor_active() ) {
    $content = do_shortcode( $content );
}
```

---

## Debugging Strategies

### 1. Comprehensive Call Logging

**Template for debug messages:**
```php
bws_content_debug( sprintf(
    "=== TAG CALLED === Post: %d, Context: %s, Global: %d, Stack: [%s], QueryID: %s",
    $post_id,
    $context_post_id ?? 'none',
    get_the_ID(),
    implode( ', ', $stack ),
    isset( $instance->context['queryId'] ) ? 'yes' : 'no'
) );
```

### 2. Backtrace for Call Source

**When you need to know WHERE a tag is called from:**
```php
$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
$call_chain = array_map( fn($t) => $t['function'] ?? '?', $backtrace );
bws_content_debug( "Call chain: " . implode( ' -> ', $call_chain ) );
```

### 3. Block Information Logging

**Understanding which blocks trigger tags:**
```php
bws_content_debug( sprintf(
    "Block: %s | ID: %s | Attrs: %s",
    $block['blockName'] ?? 'unknown',
    $block['attrs']['uniqueId'] ?? 'no-id',
    json_encode( $block['attrs'] ?? [] )
) );
```

### 4. Memory Usage Tracking

**Monitor memory at key points:**
```php
$mem_before = memory_get_usage( true );
$content = process_content( $post_id );
$mem_after = memory_get_usage( true );
$mem_delta = $mem_after - $mem_before;

bws_content_debug( sprintf(
    "Memory: %s → %s (Δ %s)",
    size_format( $mem_before ),
    size_format( $mem_after ),
    size_format( $mem_delta )
) );
```

### 5. Stack State Visualization

**Before/after comparisons:**
```php
function bws_start_processing_post( $post_id ) {
    global $bws_content_processing_stack;
    $bws_content_processing_stack[] = $post_id;
    
    bws_content_debug( sprintf(
        "⬇ START %d | Stack: [%s] | Depth: %d",
        $post_id,
        implode( ', ', $bws_content_processing_stack ),
        count( $bws_content_processing_stack )
    ) );
}

function bws_end_processing_post( $post_id ) {
    // ... remove from stack ...
    
    bws_content_debug( sprintf(
        "⬆ END %d | Stack: [%s] | Depth: %d",
        $post_id,
        implode( ', ', $bws_content_processing_stack ),
        count( $bws_content_processing_stack )
    ) );
}
```

### 6. Conditional Debug Output

**Show context in debug mode:**
```php
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    return sprintf(
        '<div style="border:2px solid red; padding:10px;">
            <strong>DEBUG:</strong><br>
            Post ID: %d<br>
            Context: %s<br>
            Global: %d<br>
            Stack: [%s]<br>
            Reason: %s
        </div>',
        $post_id,
        $context_post_id ?? 'none',
        get_the_ID(),
        implode( ', ', $stack ),
        $reason
    );
}
```

---

## Related Post Content Considerations

**Additional complexity:**
```php
// Must handle:
// 1. Relationship field resolution
$related_posts = bws_get_related_posts_data( $source_post_id, $field_key );

// 2. Multiple post processing with limits
if ( count( $related_posts ) > $limit ) {
    $related_posts = array_slice( $related_posts, 0, $limit );
}

// 3. Memory scaling
if ( 'basic' === $mode && $limit > 2 ) {
    $limit = 2; // Auto-reduce in constrained environments
}

// 4. Each related post needs stack protection
foreach ( $related_posts as $related_post ) {
    if ( ! bws_can_process_post_content( $related_post->ID ) ) {
        continue; // Skip, don't fail
    }
    
    bws_start_processing_post( $related_post->ID );
    $content = process_content( $related_post->ID );
    bws_end_processing_post( $related_post->ID );
}
```

---

## Version History & Migration Notes

### v1.2.3 - Query Loop Setup Phase Detection
- Added detection for query loop setup vs iteration phases
- Prevents duplicate processing of parent page content
- Improved context-aware post ID resolution

### v1.2.2 - Recursion Protection Enhanced
- Removed flawed self-reference check
- Improved stack-based recursion detection
- Added per-post recursion depth tracking (optional)
- Enhanced debug logging with stack visualization

### v1.2.1 - Block Instance Type Fix
- Corrected `$instance` from array to object access
- Fixed fatal errors in context reading
- Added type checking to debug logging

### v1.2.0 - Memory Management & Processing Modes
- Implemented three-tier processing system
- Added Query Monitor detection
- Memory constraint checking
- Removed `do_blocks()` from Limited mode
- Added shortcode protection

### v1.1.0 - Initial Implementation
- Basic/Limited/Full processing modes
- Stack-based recursion protection
- Content sanitization
- Length truncation

---

## Testing Checklist

### Essential Test Cases

**1. Single Post Content Display**
- [ ] Page displays its own content (top-level)
- [ ] Content renders without errors
- [ ] Memory usage stays under 80%
- [ ] No recursion warnings in logs

**2. Query Loop Usage**
- [ ] Loop items display their own content (not parent page)
- [ ] No duplicate processing during setup phases
- [ ] Each iteration processes correctly
- [ ] Stack clears properly after loop

**3. Nested Content**
- [ ] Post A displays Post B's content (2 levels)
- [ ] Post A → B → C (3 levels, maximum)
- [ ] Post A → B → C → D blocked (exceeds depth limit)
- [ ] Circular references blocked (A → B → A)

**4. Memory Constraints**
- [ ] Query Monitor active = auto Basic mode
- [ ] High memory usage = degraded mode
- [ ] Memory exhaustion prevented
- [ ] Graceful fallback when memory insufficient

**5. Related Posts**
- [ ] Multiple related posts processed
- [ ] Limit respected
- [ ] Memory scaling works
- [ ] Each post gets stack protection

**6. Edge Cases**
- [ ] Empty content returns fallback
- [ ] Invalid post ID returns fallback
- [ ] Missing ACF fields return fallback
- [ ] No fatal errors under any scenario

---

## Key Takeaways

1. **WP_Block is an object** - Use `->` not `[]`

2. **Query loops render multiple times** - Detect and skip setup phases

3. **`do_blocks()` causes nested rendering** - Avoid in nested contexts

4. **Stack-based recursion protection is sufficient** - Don't add flawed self-reference checks

5. **Memory management is critical** - Three-tier modes with auto-detection

6. **Always check multiple post ID sources** - Context → Method → Fallback

7. **Debug logging is essential** - Log decisions, not just results

8. **Graceful degradation over failures** - Return fallback, never crash

9. **Processing modes have trade-offs** - Basic is safe, Full is risky

10. **Test with representative content** - Memory usage varies widely

---

## Quick Reference Commands

### Enable Debug Logging
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
// Check: /wp-content/debug.log
```

### Check Current Stack
```php
error_log( 'Stack: ' . print_r( $GLOBALS['bws_content_processing_stack'], true ) );
```

### Force Processing Mode
```php
// In tag options
'processing_level' => 'basic' // or 'limited', 'full'
```

### View Block Context
```php
error_log( 'Context: ' . print_r( $instance->context, true ) );
```

---

**Last Updated:** February 18, 2026  
**Maintainer:** BWS Development Team  
**Related:** GenerateBlocks Dynamic Tags, Portal Content System
