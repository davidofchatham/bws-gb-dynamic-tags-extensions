# SPEC — v1.8.0 ContentProcessor extract + helper file split

Active. Two commits, one version. Commit A: mechanical file split. Commit B: C4 Path B class extract.

## §G Goal

Decompose `content-helpers.php` (1785 lines, 6 concerns) into single-concern files. Extract post-content render pipeline into `ContentProcessor` class with string cache key, configurable threshold/depth, public procedural API preserved.

## §C Constraints

- No build pipeline, test suite, linter. Manual WordPress verify only.
- Public API per [docs/plugin-integration.md](docs/plugin-integration.md): `bws_process_post_content`, `bws_can_process_post_content`, `bws_has_sufficient_memory` MUST keep signatures + behavior.
- All functions guarded by `if ( ! function_exists() )` — class needs equivalent `if ( ! class_exists() )` guard.
- Text domain `'generateblocks'`. Function prefix `bws_`. Class prefix none enforced (existing classes: `GenerateBlocks_*` for GB-side, project uses unprefixed `Sources\`).
- PHP version: WordPress minimum (no PHP 8+ only syntax beyond what existing code uses).
- Internal `$GLOBALS['bws_content_processing_stack']` undocumented → safe to rekey int→string.
- Helper file loader at [bws-gb-dynamic-tags-extensions.php:70-73](bws-gb-dynamic-tags-extensions.php#L70-L73) explicit `require_once` list — must update.

## §I Interfaces

Public procedural API (preserved verbatim, wraps class):

| Function | Signature |
|---|---|
| `bws_process_post_content` | `( int $post_id, array $args = [] ): string` |
| `bws_process_post_content_fallback` | `( int $post_id, array $args = [] ): string` |
| `bws_can_process_post_content` | `( int $post_id ): bool` |
| `bws_start_processing_post` | `( int $post_id ): void` |
| `bws_end_processing_post` | `( int $post_id ): void` |
| `bws_has_sufficient_memory` | `(): bool` |
| `bws_extract_and_queue_inline_styles` | `( string $content ): string` |
| `bws_queue_inline_css` | `( string $css ): void` |
| `bws_output_queued_inline_css` | `(): void` |
| `bws_is_query_loop_setup_phase` | `( $instance ): bool` |
| `bws_safe_content_output` | `( string $content, array $options, $instance ): string` |

New internal API (Commit B):

| Function | Signature | Purpose |
|---|---|---|
| `bws_render_block_content` | `( string $raw, string $cache_key, array $args = [] ): string` | Raw-content render entry. Stack keys on `$cache_key`. |

New filters (Commit B):

| Filter | Default | Purpose |
|---|---|---|
| `bws_content_memory_threshold` | `0.80` | Memory fraction below which primary path runs |
| `bws_content_max_recursion_depth` | `3` | Max stack depth before block |

New class:

| Class | File |
|---|---|
| `BWS\DynamicTags\Content\ContentProcessor` | `includes/classes/class-content-processor.php` |

File split (Commit A):

| New file | Functions moved from `content-helpers.php` |
|---|---|
| `includes/helpers/field-helpers.php` | `bws_read_field`, `bws_read_term_field`, `bws_meta_handler_read`, `bws_get_loop_row_context`, `bws_resolve_acf_object_id`, `bws_extract_post_id`, `bws_get_related_posts_data`, `bws_is_valid_meta_key` |
| `includes/helpers/preview-helpers.php` | `bws_build_preview_label`, `bws_build_try_preview_label`, `bws_try_preview_template_label`, `bws_try_preview_field_part`, `bws_try_preview_source_part`, `bws_try_preview_datetime_part`, `bws_wrap_preview_label_with_link` |
| `includes/helpers/link-helpers.php` | `bws_resolve_link_url`, `bws_wrap_with_link`, `bws_get_link_options`, `bws_map_gb_link_option` |
| `includes/helpers/content-helpers.php` (slimmed) | `bws_sanitize_rich_content`, `bws_get_relationship_field_options`, `bws_get_second_relationship_field_options`, `bws_is_query_loop_setup_phase`, `bws_safe_content_output`, `bws_strip_default_select_values` + procedural pipeline wrappers (Commit B) |

## §V Invariants

| ID | Invariant |
|---|---|
| V1 | Same cache_key on stack → `bws_render_block_content` returns `''` (recursion block). |
| V2 | Stack depth ≥ `bws_content_max_recursion_depth` (default 3) → return `''`. |
| V3 | Memory check (`bws_has_sufficient_memory`) runs BEFORE stack push. Fallback path does NOT push. |
| V4 | Stack pop by-value (`array_search` + `array_splice`), NOT LIFO. Guards unbalanced pairs under exception unwind. |
| V5 | Empty raw content → empty return, BUT if stack was pushed, pop still fires. |
| V6 | `memory_limit` = `-1` → `bws_has_sufficient_memory()` returns true. |
| V7 | `wp_convert_hr_to_bytes($limit) <= 0` → `bws_has_sufficient_memory()` returns true (indeterminate = sufficient). |
| V8 | Public procedural API (per §I top table) preserves signatures + return-on-empty + recursion/memory semantics verbatim. |
| V9 | `bws_process_post_content( $post_id )` = `bws_render_block_content( get_post_field('post_content', $post_id), 'post:' . $post_id, $args )`. |
| V10 | Stack cache_key format: `'post:' . $post_id` when `src` resolves to a post entity; `'option:' . $options['key']` when `src:site` (reads wp_options instead of post meta). `src` is the differentiator, not `use`. Commit B uses `'post:'` only; `'option:'` reserved for v1.9.0 src-site. Format documented in `ContentProcessor` PHPDoc; cache_key collisions defeat the guard — callers must pick stable, unique keys per logical entity. |
| V11 | `ContentProcessor` static state is sole source of truth for recursion stack. `$GLOBALS['bws_content_processing_stack']` removed. `bws_content_debug_end()` reads class state for depth log. CHANGELOG v1.8.0 notes removal under "Internal". |
| V12 | Inline-style extraction (`bws_extract_and_queue_inline_styles`) runs AFTER `do_blocks` + `wpautop`, BEFORE `wp_kses_post`. Cross-post `<style>` elements must survive to queue, not be stripped. |
| V13 | `wp_footer` queue hook (`bws_output_queued_inline_css` at priority 5) registers exactly once per request (on first `bws_queue_inline_css` call). |
| V14 | Class methods guarded by `if ( ! class_exists() )` like procedural functions are guarded by `if ( ! function_exists() )` — reload-safe. |
| V15 | File split (Commit A) preserves ALL function signatures + bodies verbatim. Diff = file rename + loader update only. Zero behavior change. |
| V16 | Loader at [bws-gb-dynamic-tags-extensions.php:70-73](bws-gb-dynamic-tags-extensions.php#L70-L73) requires field-helpers.php, preview-helpers.php, link-helpers.php BEFORE content-helpers.php (no direct deps, but stable order). Class file required after helpers. |
| V17 | `bws_content_debug` gated solely by `SettingsPage::is_benchmark_logging_enabled()` — NOT `WP_DEBUG`. (Preserved from current.) |

## §T Tasks

| id | s | task | cites |
|---|---|---|---|
| T1 | x | Commit A: create field-helpers.php, move 8 functions verbatim | V15 |
| T2 | x | Commit A: create preview-helpers.php, move 7 functions verbatim | V15 |
| T3 | x | Commit A: create link-helpers.php, move 4 functions verbatim | V15 |
| T4 | x | Commit A: slim content-helpers.php (remove moved fns, keep pipeline + sanitize + relationship-field opts + query-loop-setup + safe-output + strip-default) | V15 |
| T5 | x | Commit A: update loader bws-gb-dynamic-tags-extensions.php with 3 new require_once | V16 |
| T6 | x | Commit A: manual verify — render page with content tag, try_text tag, datetime tag, preview labels — all unchanged | V15 |
| T7 | x | Commit A: commit "Refactor: split content-helpers.php into field/preview/link helpers (no behavior change)" | V15 |
| T8 | . | Commit B: create includes/classes/class-content-processor.php — ContentProcessor class with static stack, threshold/depth constants, filter hooks | V1,V2,V3,V4,V6,V7,V14 |
| T9 | . | Commit B: implement ContentProcessor::render( $raw, $cache_key, $args ) primary + fallback paths | V1,V2,V3,V5,V12,V13 |
| T10 | . | Commit B: wire procedural wrappers — bws_process_post_content delegates to render() with `'post:'.$post_id` key | V8,V9 |
| T11 | . | Commit B: update bws_content_debug_end to read class stack depth | V11 |
| T12 | . | Commit B: remove all $GLOBALS['bws_content_processing_stack'] reads/writes; verify no remaining refs via grep | V11 |
| T13 | . | Commit B: register filters bws_content_memory_threshold + bws_content_max_recursion_depth | V2 |
| T14 | . | Commit B: PHPDoc on ContentProcessor + bws_render_block_content — document cache_key format + collision warning | V10 |
| T15 | . | Commit B: update loader to require class file after helpers | V16 |
| T16 | . | Commit B: manual verify — same tests as T6 + recursion test (content tag rendering self-referencing post → returns empty, no infinite loop) + memory fallback test (force low memory or filter threshold to 0.0 → fallback path runs) | V1,V2,V3 |
| T17 | . | Commit B: update docs/post-content-processing-reference.md — new class structure, cache_key contract, filters | I |
| T18 | . | Commit B: update docs/plugin-integration.md — note procedural API preserved, mention new filters | I |
| T19 | . | Commit B: CHANGELOG.md v1.8.0 entry — internal refactor, no user-visible change, lists new filters | I |
| T20 | . | Commit B: bump version 1.7.2 → 1.8.0 in plugin header + BWS_DYNAMIC_TAGS_VERSION constant | I |
| T21 | . | Commit B: commit "Refactor: extract ContentProcessor class (C4, issue #3); add threshold/depth filters" | I |
| T22 | . | Post-ship: migrate V1-V14 to PHPDoc on ContentProcessor class; truncate SPEC.md to no-active-spec stub | C |
| T23 | . | Post-ship: close issue #3 with cross-ref to v1.8.0 CHANGELOG | I |
| T24 | . | Post-ship: update memory/project_open_refactors.md — C4 closed, note ContentProcessor file path for src-site followup | I |

## §B Bugs

| id | date | cause | fix |
|---|---|---|---|

(empty — no bugs filed against this spec yet)
