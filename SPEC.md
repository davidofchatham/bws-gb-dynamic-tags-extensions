# SPEC — `{{call}}` function-passthrough tag (v1)

Source plan: `.claude/plans/fn-passthrough-tag.md` (grilled `/grill-with-docs` 2026-06-26, v1 locked). New file `includes/tags/fn-tags.php` (parallel to phone/email; distinct concern + security gate). Driver: site helpers (`bws_get_game_result`, `bws_build_base_title`) returning composite display strings base tags can't assemble; the standalone `tct` `{{fn.x}}` script breaks inside a GB Query Loop on an ACF relationship field (no post-context, no allowlist).

**Scope = plugin-side v1 ONLY.** v2 ergonomics cluster (pretty labels, `post_id_arg`, `args:` multi-arg, `arg:` enum, allowlist B/C, shortcode-replacement) → deferred plan file post-ship, NOT §T. Athletics back-compat rebuild → handoff/manual (separate repo `Resources/Scripts`), NOT §T.

## §G — Goal

Add a `{{call}}` tag that binds the loop-correct post entity (L1 post-resolution only) then delegates to an allowlisted, site-defined PHP function, surfacing its returned string verbatim. A fourth structural position beyond base / modifier / join-absorber: post-context-in, opaque-string-out. Fixes the relationship-loop context break the `tct` `{{fn.x}}` script and shortcodes both suffer; gates execution behind a developer-vetted allowlist the `tct` `fn` prefix lacks.

---

## §C — Constraints

- **`{{call}}` reuses L1 post-resolution ONLY.** Binds the loop-correct post id via `bws_resolve_post_by_source($options, $instance)` (the base-tags resolver), then delegates to an opaque PHP function. NO L2 resolve-field, NO L2b fetch, NO L3 assemble. No resolved field / field value exists; output is whatever PHP returns. Sits OUTSIDE I6/I7 (try_ transparency, list-mode destination gate): output opaque to the read pipeline; no list mode, no composite, no analog, single string.
- **Post-context-only, NOT source-agnostic.** The inverse of the I1/I4 "just works across post/term/site" base-tag spirit. GB type is **`'post'`, NOT `'cross-source'`** — `{{call}}` has no term/site/media/taxonomy editor features and offers only post-yielding sources, so the cross-source type would mislabel it and drag in machinery it rejects. The source menu offers `src:current` + `src:ref` ONLY — both resolve to a POST ID, exactly what a `$post_id`-contract function consumes. `src:site` (wp_options namespace) and `srcTermIn` (terms) are FILTERED OUT (I4 source-level gate): neither is a post id, a `$post_id` function can't consume them, they add no post-binding affordance → fail both I4 arms. Same mechanism as `src:site` off `term_`/`view_` rooting modifiers. **DESIGN NON-GOAL (must be documented):** a future reader must NOT "fix" this by adding term/site sources; the post binding is the entire purpose.
- **Resolves to a POST ID; Mode 2b flat repeater rows are out of scope.** `bws_resolve_post_by_source` returns a post id. Mode 2a loops (relationship / post-object — the row IS a post, `row_post_id`) resolve and are the STATED DRIVER. Mode 2b (flat ACF repeater, no row post entity) returns `false` for `src:current` — there is no post to bind, and the `$post_id` function contract can't consume a bag of row fields. v1 does NOT serve Mode 2b; passing current-repeater-row FIELDS into a function needs a different fn contract (row array / named row fields) + a new src mode → a separate "back up to row-field support" design, deferred. Documented as a known limit, not a bug.
- **Output is string, verbatim, UNESCAPED.** Function MUST return a string; `{{call}}` surfaces it raw. Non-string or empty → `fallback`. **Function owns its own escaping** — real functions return trusted display HTML (`<span>`, `&nbsp;`, `—`); the allowlist (developer-vetted) is the trust boundary; double-escaping would break every real use. Matches `tct` (returns filter output raw into `render_block`).
- **Security gate is security-only, NOT a contract check.** Two checks on every candidate: (1) `function_exists($fn)`; (2) `(new ReflectionFunction($fn))->isInternal() === false` — the hard gate, blocks PHP builtins (`system`/`exec`/`unlink`/eval-likes), reduces surface to site funcs. **No machine contract check** — untyped site functions mean reflection can't distinguish `bws_get_game_result($post_id)` from `get_game_date_time_for_display($format)` (both untyped first param). post_id-first is a DEVELOPER CONVENTION upheld when allowlisting (the same act as vouching the function safe), not machine-verified; a mis-signatured function mis-receives post_id — the developer's responsibility.
- **Single arg `arg:` (singular).** `sanitize_text_field` applied (matches `tct` line 22; preserves `full`/`basic`/`Y-m-d`/`M j, Y`). Passed ONLY when non-empty via `call_user_func_array`, letting the function's own default fire when absent. Collapses behavior-variant proliferation (`full`/`basic` → `arg:` values, not separate named functions). `args:` (plural) RESERVED for v2 multi-arg.
- **post_id injection always position 0** (first param), hardcoded v1. Repointing belongs at registration level (`post_id_arg` meta), NOT a tag-level option — the editor must not know fn signatures, the developer does. **Tag-level `pid:` killed for good.** `post_id_arg` is a v2 seam, no current consumer.
- **Allowlist = Option A (PHP filter source of truth).** Filter `bws_fn_passthrough_functions`, default EMPTY. Trust boundary = file/code access only (no DB-write widening). Precedent: `tct_allowed_hooks`, ADR 0001. **Associative storage from v1:** `[ 'bws_get_game_result' => [] ]`; raw bare-string entries normalized (`'fn'` → `'fn' => []`) on read via `array_is_list` pass. NO `$meta` consumed in v1 (label / `post_id_arg` are v2) — storage future-shaped, usage flat; erases any future associative migration.
- **`{{call}}` ships EMPTY; pure developer-tool.** Plugin ships tag + wiring + gate + failure handling + editor select + admin mirror + `bws_register_call_function` + an EMPTY filter. Site ships the functions + allowlist entries. NO built-ins (no generic post→string fn is universally useful that a base tag doesn't already cover). Security story: the plugin never executes anything it shipped; attack surface = whatever the site developer allowlists (functions they could already call in PHP). Grants editors NO capability the developer didn't already hold in code — a routing convenience, NOT privilege escalation. README must frame "for developers; bring your own function," NOT turnkey.
- **Editor `fn:` is a SELECT** (allowlist-populated, NOT free text — editor needs discovery; matches the controlled-option philosophy). v1 label = raw function name (value = label). Allowlist exposed to JS via the existing `tagSpecificControls` / conditional-options seam. One allowlist, two consumers: editor select + read-only admin mirror.
- **Editor preview is INERT (config-describing, does NOT execute the function).** The EXCEPTION to the plugin's normal value-preview behavior — a deliberate safety refusal: (1) allowlisted functions are vetted for `isInternal`-safety, NOT purity/idempotency, so running them on every editor load/keystroke is unacceptable; (2) the loop-correct post_id doesn't exist at editor-time, so a run would mislead anyway. `editor-tag-previews.md` must state this is an INTENTIONAL inert-preview exception.
- **Registers UNCONDITIONALLY** (first-class tag, no modifier gate). New `includes/tags/fn-tags.php`, wired after `bws_register_phone_tag()`. Target version: minor bump from 1.11.0 (new tag family).

---

## §I — Surfaces

- `includes/tags/fn-tags.php` — NEW.
  - `bws_register_call_function( string $fn, array $meta = [] ): bool` — sugar over raw `add_filter`. Runs the security gate at registration (fail-fast `_doing_it_wrong`); `function_exists` → false + warn; `isInternal` → false + warn; else adds a filter that does `$list[$fn] = $meta` (**last-write-wins** — re-registering with richer meta UPDATES the entry; NOT a `+` union, which would discard the new meta). `$meta` accepted (forward-compat), UNUSED v1. Raw `add_filter` path STILL works (power users / bulk).
  - `bws_register_call_tag()` — registers `tag:call`, `title:'Call Custom Function'`, `type:'post'` (standard post context — NOT `cross-source`; `{{call}}` has no term/site/media/taxonomy features and offers only post-yielding sources). Options: `src` (current/ref ONLY) + `ref` traversal + `fn:` (select, allowlist) + `arg:` + `fallback`. NO `key`, NO `use`, NO list-mode, NO site/srcTermIn.
  - `bws_call_get_allowlist(): array` — reads `bws_fn_passthrough_functions`, normalizes bare-string entries to associative via `array_is_list` pass.
  - `bws_call_passes_gate( string $fn ): bool` — `function_exists($fn) && ! (new ReflectionFunction($fn))->isInternal()`. Used at registration AND defensively at resolve.
  - `bws_call_callback( $options, $block, $instance ): string` — resolve post id via `bws_resolve_post_by_source` (current/ref); gate check (bucket A → fallback + editor-warn surface); build `$args = [$post_id]` + non-empty sanitized `arg`; `call_user_func_array` in `try{}catch(\Throwable)` (#6 → `error_log` always + fallback, exception NEVER to page); non-string/empty return → fallback (bucket B silent).
- `bws-gb-dynamic-tags-extensions.php` — `require_once` after the phone require; `bws_register_call_tag()` after `bws_register_phone_tag()`.
- `includes/helpers/preview-helpers.php` — `call` config-describing preview branch (function name + source segment + `(arg)` + bucket-A warnings; reuse `bws_build_preview_label` warning machinery, the `src:site`-hand-typed precedent #37). INERT — never executes the function.
- `class-settings-page.php` (read-only mirror) — new section BELOW the existing email/phone tag-specific sections in the GB settings subpage. Lists the allowlist + per-entry status (exists? passes gate?). Diagnostic, NOT config. No subpage reshaping this release (a diagnostics tab / WP Wireframe rebuild is deferred, separate consideration).
- `assets/js/editor-conditional-options.js` / `tagSpecificControls` seam — feed the allowlist to the `fn:` select.
- `docs/tag-reference.md` — registry row + I4 source-level gate row (site/srcTermIn off `{{call}}`) under §Qualifying test + the 4th-structural-position note (describe-don't-name). `CONTEXT.md` — 4th-position note added AT SHIP (binds nothing until then). `docs/editor-tag-previews.md` — inert-preview exception. `CHANGELOG.md` `### Added`; `README.md` "for developers" overview.

---

## §V — Invariants

**VC1** `{{call}}` reuses L1 post-resolution ONLY (`bws_resolve_post_by_source`), then delegates to an opaque PHP function. No L2 resolve-field, no L2b fetch, no L3 assemble; no resolved field / field value. Sits OUTSIDE I6/I7 — output is opaque to the read pipeline: single string, no list mode, no composite, no analog.

**VC2** GB type is `'post'` (NOT `'cross-source'`). Source menu offers `src:current` + `src:ref` ONLY — both resolve to a post id. `src:site` + `srcTermIn` are filtered OUT (I4 source-level gate; not a post id → fail both arms). Post-context-only is a STATED NON-GOAL: never additively "fixed" with term/site sources. The resolver is reused AS-IS — non-post sources are simply not OFFERED. Mode 2a loops resolve; Mode 2b flat-repeater rows (no post entity) return false and are an out-of-scope known limit, not a bug.

**VC3** Output is the function's return string, surfaced VERBATIM and UNESCAPED. Non-string or empty return → `fallback`. The function owns its own escaping; the allowlist (developer-vetted) is the trust boundary. `{{call}}` applies no `esc_*` to the return.

**VC-gate** Execution gate is security-only, two checks, run at registration AND defensively at resolve: `function_exists($fn)` AND `(new ReflectionFunction($fn))->isInternal() === false`. No machine contract check exists (untyped functions). post_id-first is a developer convention upheld when allowlisting, never machine-verified.

**VC-arg** `arg:` is singular, `sanitize_text_field`-cleaned, passed as position-1 ONLY when non-empty (via `call_user_func_array`) so the function's own default fires when absent. post_id is ALWAYS position 0, hardcoded v1. Tag-level `pid:` does not exist. `args:` (plural) is reserved, unimplemented.

**VC-allow** Allowlist source of truth is the `bws_fn_passthrough_functions` filter, default EMPTY (file/code-access trust boundary; no DB-write widening). Storage is associative from v1 (`[$fn => $meta]`); bare-string filter entries are normalized to associative on read. `$meta` is stored but UNUSED in v1.

**VC-empty** `{{call}}` ships with an EMPTY allowlist and NO built-in functions. It produces nothing until the site supplies both code and allowlist entries. It grants editors no capability the developer didn't already hold in PHP — routing convenience, not privilege escalation.

**VC-select** The editor `fn:` control is a SELECT populated from the allowlist (never free text), fed via the `tagSpecificControls` / conditional-options seam. The allowlist has exactly two consumers: this select and the read-only admin mirror (diagnostic, never config-write).

**VC-inert** The editor preview is config-describing and NEVER executes the function — a deliberate safety refusal (functions are vetted for `isInternal`-safety only, not purity; loop-correct post_id is absent at editor-time). This is the documented exception to the plugin's value-preview behavior.

**VC-fail** Failure taxonomy, 3 buckets: **Bucket A** (not-in-allowlist / `function_exists` false / fails `isInternal`) → fallback + editor ⚠ warning (config/safety drift, JS-available so warn client-side live), no log. **Bucket B** (post unresolvable / non-string-or-empty return) → fallback, silent (legitimate data-absence). **#6** (function throws/fatals) → catch `\Throwable`, ALWAYS `error_log` (never debug-gated), public output = fallback, the exception message NEVER reaches the page.

---

## §T — Tasks

| id | status | task | cites |
|----|--------|------|-------|
| T1 | x | New file `includes/tags/fn-tags.php`; `require_once` after the phone require in `bws-gb-dynamic-tags-extensions.php`; call `bws_register_call_tag()` after `bws_register_phone_tag()`. `static $registered` guard, unconditional registration. | I.fn-tags |
| T2 | x | `bws_call_get_allowlist(): array` + `bws_call_passes_gate( string $fn ): bool`. Allowlist reads `bws_fn_passthrough_functions`, normalizes bare-string entries to associative (`array_is_list` pass). Gate = `function_exists && ! ReflectionFunction->isInternal`. | VC-gate,VC-allow,I.fn-tags |
| T3 | x | `bws_register_call_function( string $fn, array $meta = [] ): bool` — registration sugar. `function_exists` false → `_doing_it_wrong` + false; `isInternal` true → `_doing_it_wrong` + false; else add a filter doing `$list[$fn] = $meta` (last-write-wins, NOT a `+` union) + bump the allowlist-memo generation + return true. `$meta` stored, unused v1. Raw `add_filter` path documented as still-valid. | VC-gate,VC-allow,I.fn-tags |
| T4 | x | `bws_register_call_tag()` — `tag:call`, `title:'Call Custom Function'`, `type:'post'`. Options: `src` (current/ref ONLY — site/srcTermIn filtered out) + `ref` traversal + `fn:` select (allowlist) + `arg:` + `fallback`. NO `key`/`use`/list-mode. Document the post-context-only NON-GOAL + Mode 2b out-of-scope limit in PHPDoc. | VC1,VC2,VC-select,I.fn-tags |
| T5 | x | `bws_call_callback( $options, $block, $instance ): string` — resolve post id via `bws_resolve_post_by_source` (current/ref); gate-check (bucket A → fallback + warn-surface); `$args=[$post_id]`, append `sanitize_text_field(arg)` iff non-empty; `call_user_func_array` in `try{}catch(\Throwable $e){ error_log(...); return fallback; }`; non-string/empty → fallback. Exception message NEVER returned. Surface output VERBATIM/UNESCAPED. | VC1,VC3,VC-arg,VC-gate,VC-fail,I.fn-tags |
| T6 | x | Editor `fn:` select — populated IN PHP from the allowlist (`bws_call_fn_select_options`); a normal GB `type:select` option. NO JS work (conditional-options JS only does show/hide; the select is server-rendered from the PHP options array). value=label=raw fn name v1. | VC-select,I.fn-tags |
| T7 | x | Read-only admin mirror — new section BELOW the email/phone sections in `class-settings-page.php` (between Phone + Diagnostics); `get_call_allowlist_status()` accessor; list allowlist + per-entry status (exists? passes gate?). No config-write, no subpage reshaping. | VC-empty,VC-select,I.class-settings-page |
| T8 | x | Inert config-describing editor preview (`preview-helpers.php`): `call` branch → `Function: fn` + `(arg)` + source segment + empty-fn ⚠ warning (reuse `bws_build_preview_label` warning machinery). NEVER execute the function. | VC-inert,VC-fail,I.preview-helpers |
| T9 | x | Docs: `tag-reference.md` registry row + I4 source-level gate row + §Call tag section; `CONTEXT.md` 4th-position note; `editor-tag-previews.md` inert-exception section; `CHANGELOG.md` `### Added` (dated `unreleased` until finalized); `README.md` "for developers" overview row. | VC1,VC2,VC-inert,VC-empty,I.tag-reference |
| T10 | x | Version bump 1.11.0 → 1.12.0 (plugin header + `BWS_DYNAMIC_TAGS_VERSION` const + readme stable tag). | — |
| T11 | x | Regression matrix: `src:current` loop-row resolves post id; `src:ref` hop; allowlisted fn returns string verbatim/unescaped; non-string/empty → fallback; `arg:` non-empty passed pos-1, absent → fn default; bucket-A (stale ref / fn gone / hand-edited builtin) → fallback + editor warn; #6 throw → `error_log` + fallback + no message leak; empty allowlist → no options; raw `add_filter` parity with `bws_register_call_function`; inert preview never executes. | VC1,VC2,VC3,VC-arg,VC-gate,VC-fail,VC-inert |

---

## §B — Bugs

| id | date | cause | fix |
|----|------|-------|-----|
