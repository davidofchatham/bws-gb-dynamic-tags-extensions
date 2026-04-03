# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress plugin that extends **GenerateBlocks** with custom dynamic tags for ACF (Advanced Custom Fields) integration. Provides dynamic content insertion for post content, taxonomy terms, and date/time fields. Pure PHP — no build system, no dependencies beyond WordPress/GenerateBlocks/ACF.

**Version**: 1.4.1

## Architecture

Three PHP files, each self-contained module following the same pattern:

- **deprecated-files/core-tags.php** — Post/media dynamic tags (featured images, meta images, related posts, URLs). 8 registered tags with ~37 utility functions.
- **deprecated-files/date-time-tags.php** — Date/time formatting with smart timezone handling, noon safety buffer, AM/PM consolidation in ranges, and locale-aware output. 2 registered tags.
- **deprecated-files/taxonomy-tags.php** — Taxonomy term tags (name, permalink, description, field image) with multi-fallback term detection. 4 registered tags.

Each file follows this structure:
1. ABSPATH security check + duplicate-load protection via version constant
2. Section headers (`// ===`) grouping related functions
3. Registration function → options functions → callback functions → utility functions
4. `add_action` hooks at file end

## Key Patterns

**Registration**: Each tag uses `new GenerateBlocks_Register_Dynamic_Tag([ ... ])` with title, tag name, type, supports array, options callback, and return callback.

**Callbacks** follow: extract options with defaults → get context (post_id/term_id) → fetch data → apply fallback chain → return via `GenerateBlocks_Dynamic_Tag_Callbacks::output()`.

**Fallback chain**: Primary source → secondary source → media/UI fallback → fallback text → empty string.

**Naming**: All functions prefixed `bws_`. Callbacks are `bws_get_[feature]_callback()`, options are `bws_get_[feature]_options()`.

**Text domain**: All localization strings use `'generateblocks'` (not a custom domain).

**ACF integration**: Always guarded with `function_exists()` checks. Field values retrieved via `get_field()` / `get_field_object()`.

## Dependencies

- **WordPress** (core APIs)
- **GenerateBlocks** plugin (provides `GenerateBlocks_Register_Dynamic_Tag`, `GenerateBlocks_Dynamic_Tags`, `GenerateBlocks_Dynamic_Tag_Callbacks`)
- **ACF** (optional but primary use case — all ACF calls are guarded)

## No Build/Test/Lint Commands

This is a standalone PHP plugin with no build pipeline, test suite, or linter configuration. Development is done by editing PHP files directly and testing in a WordPress environment.
