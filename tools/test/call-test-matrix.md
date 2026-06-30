# `{{call}}` Regression Matrix

**Standing manual regression suite** for the `{{call}}` function-passthrough tag (v1.12.0) — not a one-shot plan checklist. Rows are anchored to invariants (VC1–VC-fail), so they stay valid past the SPEC's post-ship truncation.

> **Re-run trigger:** after any change to `includes/tags/fn-tags.php` (`bws_call_get_allowlist`, `bws_call_passes_gate`, `bws_register_call_function`, `bws_call_source_option`, `bws_call_fn_select_options`, `bws_register_call_tag`, `bws_call_build_args`, `bws_call_callback`), the `call` preview branch in `preview-helpers.php`, or the admin allowlist mirror in `class-settings-page.php`.
>
> **Two layers:**
> - **Pure helpers (automated):** `php tools/test/call-tag-test.php` — allowlist normalize (VC-allow), security gate accept/reject internal (VC-gate), argument build (VC-arg). Run first; must be green before manual rows.
> - **Integration (manual, WP):** the R-rows below — registration, source resolution in loops, output, failure buckets, preview, admin mirror. Run on a WP test instance with **GenerateBlocks (Pro)** + **ACF**, per the runtime-debug workflow (TEST instance, never the live/cached site).

**Setup.** In the test theme's `functions.php` (or a snippet), define + allowlist a few functions:

```php
function bws_demo_result( $post_id = null, $arg = 'full' ) {
    if ( ! $post_id ) { $post_id = get_the_ID(); }
    return '<span class="r">' . ( 'short' === $arg ? 'W' : 'Win' ) . ' #' . (int) $post_id . '</span>';
}
function bws_demo_empty( $post_id = null ) { return ''; }          // returns empty
function bws_demo_nonstring( $post_id = null ) { return [ 1, 2 ]; } // returns non-string
function bws_demo_throws( $post_id = null ) { throw new RuntimeException( 'boom: /secret/path' ); }

add_action( 'init', function () {
    bws_register_call_function( 'bws_demo_result' );
    bws_register_call_function( 'bws_demo_empty' );
    bws_register_call_function( 'bws_demo_nonstring' );
    bws_register_call_function( 'bws_demo_throws' );
    // Raw-filter path (must work identically):
    add_filter( 'bws_fn_passthrough_functions', fn( $l ) => $l + [ 'bws_demo_raw' => [] ] );
} );
function bws_demo_raw( $post_id = null ) { return 'raw-ok'; }
```

**How to run:** paste each tag into a GenerateBlocks block, view the rendered front end. `[SUB …]` = substitute a real id/key on your instance.

---

## R0 — registration + allowlist (VC-allow, VC-gate, VC-empty)

| # | Action | Expected |
|---|---|---|
| R0.1 | Fresh install, no functions allowlisted | `fn:` select shows only `— Select a function —`; admin mirror says "No functions allowlisted" |
| R0.2 | `bws_register_call_function( 'bws_demo_result' )` | `bws_demo_result` appears in the `fn:` select AND the admin mirror with `✓ OK` |
| R0.3 | Raw `add_filter` bare-string entry (`'bws_demo_raw'`) | Normalized to the select + mirror identically to the helper path |
| R0.4 | `bws_register_call_function( 'no_such_fn' )` | Returns false; `_doing_it_wrong` notice; NOT added |
| R0.5 | `bws_register_call_function( 'system' )` | Returns false ("Refusing built-in"); NOT added (VC-gate hard gate) |
| R0.6 | Allowlist a function, then delete its definition (leave the entry) | Mirror shows `⚠ Not found`; tag outputs fallback (bucket A) |

## R1 — source resolution in loops (VC1, VC2)

| # | Context | Tag | Expected |
|---|---|---|---|
| R1.1 | Current single post | `{{call fn:bws_demo_result}}` | `<span class="r">Win #<current id></span>` |
| R1.2 | GB Query Loop over a **relationship/post-object** field (Mode 2a) | `{{call fn:bws_demo_result}}` (`src:current`) | Each row outputs the **row post's** id — NOT the outer page's (the fix for the ambient `get_the_ID()` break) |
| R1.3 | Related-post hop | `{{call src:ref\|ref:[SUB rel]\|fn:bws_demo_result}}` | Output for the related post's id |
| R1.4 | Flat ACF repeater row (Mode 2b), `src:current` | `{{call fn:bws_demo_result\|fallback:—}}` | `—` (no post entity to bind — documented known limit, bucket B) |
| R1.5 | `fn:` select in editor | (inspect) | Offers **Current** + **In Reference/Relational Field** ONLY — no Site, no taxonomy-term hop (VC2) |

## R2 — output contract (VC3)

| # | Function | Tag | Expected |
|---|---|---|---|
| R2.1 | `bws_demo_result` (returns HTML) | `{{call fn:bws_demo_result}}` | HTML rendered **verbatim, unescaped** (`<span>` is a real element, not entity-encoded text) |
| R2.2 | `bws_demo_empty` (returns '') | `{{call fn:bws_demo_empty\|fallback:none}}` | `none` (empty return → fallback, bucket B, silent) |
| R2.3 | `bws_demo_nonstring` (returns array) | `{{call fn:bws_demo_nonstring\|fallback:none}}` | `none` (non-string → fallback, bucket B) |

## R3 — argument (VC-arg)

| # | Tag | Expected |
|---|---|---|
| R3.1 | `{{call fn:bws_demo_result}}` | `…Win…` (no arg → function default `'full'` fires) |
| R3.2 | `{{call fn:bws_demo_result\|arg:short}}` | `…W…` (arg `short` passed at position 1) |
| R3.3 | `{{call fn:bws_demo_result\|arg:<b>x</b>}}` | arg sanitized (`sanitize_text_field`) before the call — tags stripped |
| R3.4 | (verify) | post id is ALWAYS the first argument; the arg is the second, only when non-empty |

## R4 — failure taxonomy (VC-fail)

| # | Scenario | Public output | Editor preview | Log |
|---|---|---|---|---|
| R4.1 | `fn:` empty | fallback (or '') | `[⚠ No function set]` | — |
| R4.2 | `fn:` not in allowlist (stale ref) | fallback | ⚠ warning (client-side) | — |
| R4.3 | `fn:` exists in tag but function deleted | fallback | ⚠ | — |
| R4.4 | Hand-typed builtin (`fn:phpinfo`) | fallback | ⚠ | — |
| R4.5 | `bws_demo_throws` (throws) | **fallback** | silent | **`error_log` line present** AND `boom: /secret/path` **NOT** on the page (message never leaks) |

## R5 — inert preview (VC-inert)

| # | Tag (in editor) | Expected preview | Must NOT |
|---|---|---|---|
| R5.1 | `{{call fn:bws_demo_result}}` | `[Function: bws_demo_result]` | run `bws_demo_result` (no side effects, no real output) |
| R5.2 | `{{call fn:bws_demo_result\|arg:short}}` | `[Function: bws_demo_result (short)]` | execute |
| R5.3 | `{{call src:ref\|ref:games\|fn:bws_demo_result}}` | `[Function: bws_demo_result from Ref 'games']` | execute |
| R5.4 | `{{call}}` (no fn) | `[⚠ No function set]` | — |

## R6 — admin mirror (VC-select, VC-empty)

| # | Action | Expected |
|---|---|---|
| R6.1 | Open Settings → Tag Extensions → **Call Custom Function** | Read-only list; the desc states the allowlist lives in code, not the DB |
| R6.2 | Allowlisted + valid function | row `✓ OK` |
| R6.3 | Allowlisted but undefined function | row `⚠ Not found (function does not exist)` |
| R6.4 | (verify) | No save control in this section — it is a diagnostic mirror, never config-write |
