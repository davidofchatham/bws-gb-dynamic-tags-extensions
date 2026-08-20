# Function passthrough tag — `{{call}}`

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

> **ARCHIVED 2026-06-30 — v1 SHIPPED in 1.12.0.** Current-state homes: CHANGELOG
> 1.12.0, `docs/tag-reference.md` §Call tag (schema), CONTEXT.md §Tag structural
> vocabulary (4th-position note) + §Registration-API load order (VC-load), and the
> `@invariant` PHPDoc in `includes/tags/fn-tags.php`. This file is kept ONLY as the
> design rationale + the **§Deferred "v2 ergonomics" cluster** detail home (tracked
> in `docs/future-work.md`). Everything below pre-dates the ship; read the current
> homes for what actually shipped (e.g. the function NEED NOT be post_id-first to
> pass the gate — only `isInternal`; and the registration API loads at plugin top
> level, not init).

**Status:** Likely (design grilled 2026-06-26 via `/grill-with-docs`; v1 scope locked).
**Driver:** athletics `bws_get_game_result($post_id)` — complex return (term-name →
template branch + score formatting) base tags can't assemble; the standalone `tct`
`{{fn.x}}` script **breaks inside a GB Query Loop on an ACF relationship field** (no
post-context, no safety gate).

## Problem

Site-defined helpers (`bws_get_game_result`) return composite display strings base tags
can't produce. **Second corroborating driver (analyzed 2026-06-26): `bws_build_base_title`**
(+ its `_location` / `_outcome` wrappers, Athletics helpers) — same shape, MORE conditional:
multi-taxonomy branch (skip location-indicator if event-type tournament/meet), a
location-type→indicator LOOKUP fn (`bws_get_location_indicator`), show-status-only-for-
non-standard-results (term-membership test), a ref-hop title (`location_cpt[0]`), and
nested re-use of `bws_get_game_result`. The computation+branch layers dominate; `{{join}}`
could only assemble the final markup of already-decided values, and only with many tags +
external GP visibility + precomputed fields. Confirms the pattern: site-specific
taxonomy-conditional + lookup-table helpers are `{{call}}` territory, NOT join/if — one
`{{call bws_build_base_title}}` vs a fragile multi-tag rig. NOT a join/if requirement.

Two existing surfaces both fail in the relationship-loop context:

1. **`tct` script** (`Resources/Scripts/Dynamic Template Tags/template-tags-v2.0.0.php`,
   the `fn` prefix): `{{fn.<name>}}` → `tct_handle_fn` → `call_user_func($value)` with
   **no post_id** (ambient `get_the_ID()`, wrong/empty in the loop) and **no allowlist**
   (only `function_exists()` — any callable, incl. `system`/`unlink`). NB the same
   script DOES gate its `hook` prefix via `tct_allowed_hooks` — that allowlist is our
   design precedent; `fn` is the one un-gated outlier.
2. **Shortcodes** — same ambient-context problem.

Base tags solved loop context via `bws_resolve_post_by_source($options, $instance)`
(`base-tags.php`). `{{call}}` reuses that resolver to inject the loop-correct post_id.

## What `{{call}}` IS (structural class)

A **fourth structural position** beyond CONTEXT.md §Tag structural vocabulary's three
(base / modifier / join's absorber). `{{call}}`:

- reuses **L1 post-resolution ONLY** (binds the loop-correct post entity), then
  **delegates to an opaque PHP function** — no L2 resolve-field, no L2b fetch, no L3
  assemble. There is no resolved field / field value; output is whatever PHP returns.
- sits **OUTSIDE I6/I7** (try_ transparency, list-mode destination gate): its output is
  opaque to the read pipeline — no list mode, no composite, no analog, single string.
- is **deliberately post-context-only, NOT source-agnostic** (the explicit non-goal
  below) — the inverse of the I1/I4 "just works across post/term/site" base-tag spirit.

> **CONTEXT.md is NOT written until `{{call}}` ships** (Q3). CONTEXT.md holds
> *currently-binding* principles; an unbuilt tag binds nothing. At ship: add the
> 4th-position note (describe-don't-name, per the doc's genus discipline) + the I4
> source-level row (site/srcTermIn off `{{call}}`) to `tag-reference.md` §Qualifying
> test. This plan is the home until then.

## Design (v1) — locked

### Tag + syntax
- New tag `{{call}}`. Internal option key `fn:` fine.
- GB syntax: `{{call src:ref|ref:games|fn:bws_get_game_result|arg:basic|fallback:—}}`
- **File:** new `includes/tags/fn-tags.php` (parallel to phone/email; distinct concern
  + security gate).

### Source menu — post-yielding only
- Offers **`src:current` + `src:ref` ONLY**. Both resolve to a **post id** — exactly
  what a `$post_id`-contract function consumes.
- **`src:site` and `srcTermIn` filtered OUT** of the dropdown (I4 source-level gate):
  site resolves to a wp_options namespace, srcTermIn to terms — neither is a post id; a
  `$post_id` function can't consume them; they add no post-binding affordance →
  fail both I4 arms. Same mechanism as `src:site` off `term_`/`view_` rooting modifiers.
- `src:current` is the actual fix for the stated bug (relationship-loop row = Mode 2a,
  `bws_resolve_post_by_source` → `$loop['row_post_id']`). `src:ref` covers a second hop
  (function about a post related to the loop row); cheap since the resolver already does it.
- **Resolver reused AS-IS** — just don't *offer* the non-post sources.

### DESIGN NON-GOAL (document explicitly)
`{{call}}` is **intentionally post-context-only**, NOT source-agnostic like standard
base tags. A future reader must not "fix" it by adding term/site sources — the post
binding is the entire purpose. Record this as a stated non-goal in user docs + PHPDoc.

### Output contract — string, verbatim
- Function MUST return a **string**; `{{call}}` surfaces it **verbatim, UNESCAPED**.
  Non-string or empty → `fallback`.
- **Function owns its own escaping.** Real functions return trusted display HTML
  (`<span>`, `&nbsp;`, `—`); the allowlist (developer-vetted) is the trust boundary.
  Matches the `tct` script (returns filter output raw into `render_block`, no `esc_*`).
  Double-escaping would break every real use.

### Security gate — security-only (NOT a contract check)
Run on every candidate at registration (and defensively at resolve):
1. `function_exists($fn)`
2. `(new ReflectionFunction($fn))->isInternal() === false` — **the hard gate**; blocks
   PHP builtins (`system`/`exec`/`unlink`/eval-likes). Reduces surface to site funcs.

**No machine contract check.** Earlier plan claimed reflection enforces "post_id-first";
**it can't** — your functions are untyped, so reflection cannot distinguish
`bws_get_game_result($post_id)` from `get_game_date_time_for_display($date_format)`
(both untyped first param). So:
- **post_id-first is a DEVELOPER CONVENTION**, upheld when allowlisting — the same act
  as vouching the function is safe to call. Not machine-verified. A mis-signatured
  function mis-receives post_id; that's the (file-access) developer's responsibility.

### Single arg — `arg:`
- Option key **`arg:`** (singular). `sanitize_text_field` applied (matches `tct`
  line 22; preserves `full`/`basic`/`Y-m-d`/`M j, Y`, strips control/HTML noise).
- **Passed ONLY when non-empty**, via `call_user_func_array` — lets the function's own
  default fire when absent (`$date_format='full'` kicks in naturally):
  ```php
  $args = [ $post_id ];
  if ( isset($options['arg']) && '' !== $options['arg'] ) {
      $args[] = sanitize_text_field( $options['arg'] );
  }
  $out = call_user_func_array( $fn, $args );
  ```
- Collapses behavior-variant proliferation: `full`/`basic` become `arg:` values, not
  separate named functions. (Athletics `_short*` wrappers reduce to `arg:short` — see
  §Back-compat.)
- `args:` (plural) **RESERVED** for the future multi-arg single-control.

### post_id injection position
- v1: **always position 0** (first param), hardcoded.
- Repointing belongs at **registration level** (`post_id_arg` meta), NOT a tag-level
  `pid:` option (editor must not know fn signatures; developer does). **Tag-level `pid:`
  killed for good.** `post_id_arg` itself is a documented seam (v2) — no current
  function needs it (all are post_id-first or get reshaped to be). It only ever
  reorders injection for functions that ALREADY take+use a post_id but not first; it
  does NOT conjure post_id-awareness into ambient-reading functions (those need reshape).

### Allowlist — Option A (filter source of truth) + associative storage
- **Source of truth = PHP filter** `bws_fn_passthrough_functions`, default **empty**.
  Trust boundary = file/code access only (no DB-write widening). Precedent:
  `tct_allowed_hooks`, ADR 0001.
- **Associative storage from v1:** `[ 'bws_get_game_result' => [] ]`. Raw-filter bare
  strings normalized (`'fn'` → `'fn' => []`) on read (`array_is_list` pass). NO `$meta`
  values consumed in v1 (label / `post_id_arg` are v2) — storage is future-shaped, usage
  is flat. **This erases any future associative migration.**

### Registration helper — `bws_register_call_function()` (c1, ships v1)
```php
function bws_register_call_function( string $fn, array $meta = [] ): bool {
    if ( ! function_exists( $fn ) ) {
        _doing_it_wrong( __FUNCTION__, "Function '$fn' not found.", '1.x' );
        return false;
    }
    if ( ( new ReflectionFunction( $fn ) )->isInternal() ) {
        _doing_it_wrong( __FUNCTION__, "Refusing built-in '$fn'.", '1.x' );
        return false;
    }
    add_filter( 'bws_fn_passthrough_functions', fn( $l ) => $l + [ $fn => $meta ] );
    return true;
}
```
- Sugar over the raw `add_filter` (raw path STILL works — power users / bulk).
- Runs the security gate at **registration time** (fail-fast via `_doing_it_wrong`),
  feeding the admin mirror. `$meta` accepted now (forward-compat), unused in v1.
- c3 (closure-as-allowlist) REJECTED: kills `{{fn.x}}` interop, neuters `isInternal`,
  blurs the plugin-routes/site-defines boundary.

### Editor `fn:` control — allowlist-populated select
- `fn:` is a **select**, options = the allowlist (NOT free text — editor needs
  discovery; matches the controlled-option philosophy of every other tag).
- v1 label = raw function name (value = label). Pretty labels = v2 (rides the `$meta`
  flip). Allowlist exposed to JS via the existing `tagSpecificControls` /
  conditional-options seam.
- **One allowlist, two consumers:** editor select + read-only admin mirror.

### Admin page — read-only mirror
- Lists the current allowlist + per-entry status (exists? passes gate?). Diagnostic,
  NOT config. Editor discovery without touching trust.

### Failure taxonomy — 3 buckets
| # | Failure | Public output | Editor preview | Log |
|---|---|---|---|---|
| 2 | fn not in allowlist (stale ref) | fallback | **⚠ warning** | — |
| 3 | `function_exists` false | fallback | **⚠ warning** | — |
| 4 | fails `isInternal` gate (hand-edited builtin) | fallback | **⚠ warning** | — |
| 1 | post unresolvable | fallback | silent | — |
| 5 | returns non-string / empty | fallback | silent | — |
| 6 | function throws / fatals | fallback | silent | **ALWAYS `error_log`** |

- **Bucket A (2,3,4)** = config/safety drift → empty + editor warning (reuse the
  `bws_build_preview_label` warning machinery, the `src:site`-hand-typed precedent #37).
  Allowlist is JS-available → warn client-side live.
- **Bucket B (1,5)** = legitimate data-absence → fallback, silent (like a base-tag
  empty field).
- **#6** = catch `\Throwable`, **always** `error_log` (server-side; never debug-gated —
  a function fataling is a real error every time), public output = fallback, **the
  exception message NEVER reaches the page** (no leaking internals/paths). The catch
  exists *because* of the opacity (no base tag try/catches a field read).
  ```php
  try {
      $out = call_user_func_array( $fn, $args );
  } catch ( \Throwable $e ) {
      error_log( sprintf( 'bws {{call}}: %s threw: %s', $fn, $e->getMessage() ) );
      return $options['fallback'] ?? '';
  }
  ```
  (Verbose `BWS_DEBUG_*` instrumentation is separate from this always-on throw log.)

### Editor preview text
- **Config-describing preview** (function name + source segment + `(arg)` + warnings).
  Samples:
  - `{{call fn:bws_get_game_result}}` → `Function: bws_get_game_result`
  - `{{call src:ref|ref:games|fn:bws_get_game_result}}` → `… from Related Post`
  - `…|arg:short}}` → `Function: … (short) from current`
- **`{{call}}` is the EXCEPTION to the plugin's normal value-preview behavior.** MOST
  tags resolve a real value in preview (outside template context). `{{call}}`
  deliberately does **NOT** execute the function to preview — a **safety refusal**:
  (1) allowlisted functions are vetted for `isInternal`-safety, NOT purity/idempotency
  → running them on every editor load/keystroke is unacceptable; (2) the loop-correct
  post_id doesn't exist at editor-time → a run would mislead anyway.
- Doc `editor-tag-previews.md` must state this is an *intentional* inert-preview
  exception (so a contributor doesn't "fix" it to match other tags).

## Distribution / positioning (Q13)

**Pure developer-tool. Ships empty. Produces nothing until the site supplies code.**

- **Plugin ships:** the `{{call}}` tag, resolver wiring, security gate, failure
  handling, editor select, admin mirror, `bws_register_call_function`, an **empty**
  `bws_fn_passthrough_functions`.
- **Site ships:** the functions (theme/snippet) + the allowlist entries.
- **No built-ins** (b rejected): no generic post→string function is universally useful
  that a base tag doesn't already cover; anything else is domain-specific.
- **Security story:** the plugin never executes anything it shipped. The attack surface
  is whatever the site developer allowlists — functions they could already call in PHP.
  **`{{call}}` grants editors NO capability the developer didn't already hold in code.**
  It's a *routing* convenience (loop-correct post-context + an editor surface for a
  file-access-only function), NOT privilege escalation. This is *why* Option A
  (filter-only, file-access) is the correct security model.
- **README/positioning:** must be framed "for developers; bring your own function,"
  NOT a turnkey tag — every other tag works out-of-box, this one doesn't, by design.

## Back-compat rebuild (athletics, verified 2026-06-26)

Existing site functions reach `{{call}}`-readiness with **zero `{{fn.x}}` breakage**.
The `tct` `{{fn.name}}` parser always calls **arg-less**, so every param needs a
default; making post_id the defaulted FIRST param preserves old ambient behavior while
letting `{{call}}` inject a loop-correct id.

**Recipe (per function):**
1. Add `$post_id = null` as the **first** param.
2. `if (!$post_id) $post_id = get_the_ID();` (ambient fallback when called arg-less).
3. Thread `$post_id` into the inner `get_field()` / `get_the_terms()` calls (they read
   ambient today — the actual loop-break).
4. Update internal call sites to pass `$post_id` first (preset args shift right).

**Compat:** `{{fn.x}}` arg-less → defaults fire → identical output. `{{call fn:x|arg:v}}`
→ injected post_id + `arg` 2nd → loop-fixed + variant.

**Athletics findings (the rebuild set):**
- **`Athletics - Dynamic template tag helpers.php` is the ONLY tag-return location.**
  `Athletics - Format game time and date-time fields.php` = ACF save-hooks / bulk /
  Admin-Columns (all already take `$post_id`); NOT tag returns; out of scope.
- That file's first block (lines 1–300) is **commented out** (legacy); live defs are
  post-300. Duplicate-looking defs (`get_game_result` 255 vs 422 etc.) = old vs live.
- Already 2-layer + post_id-threaded internally: public `{{fn}}` wrappers grab ambient
  `get_the_ID()` then delegate to `bws_*` cores **already post_id-first**
  (`bws_build_base_title($post_id,…)`, `bws_get_game_result($post_id=null)`). **Only the
  wrappers + format funcs need reshaping; cores untouched.**
- Format family needs reshape (currently `$format`-first, ambient `get_field`):
  `get_game_date_for_display`, `get_game_date_time_for_display` →
  `($post_id=null, $format='full')`; wrappers `get_game_short_date[_time]_for_display`
  → `($post_id=null)`. Internal call sites to update: lines **143, 149, 166** (in-file;
  no external/template callers per owner).
- **No external positional callers** (owner-confirmed): all arg-bearing calls are
  internal wrappers/collators with preset args; no separate template calls. So **no
  shim/alias layer needed** — reshape callees + update in-file call sites.
- **Variant-collapse bonus:** once `arg:` lands, `_short*` presets become `arg:short` →
  `get_game_short_date_for_display` / `_short_date_time_for_display` reduce to
  registry-only aliases. The proliferation cure the owner built named variants around.

## Deferred — "v2 ergonomics" cluster (all non-breaking; storage already associative)

- **Pretty labels** in the `fn:` select + admin mirror (`$meta['label']`).
- **`post_id_arg`** registration-repoint (`$meta['post_id_arg']`) — for post_id-aware-
  but-not-first functions. Documented seam; no current consumer.
- **Multi-arg `args:`** single-control (CSV/typed serialization; comma-in-value
  escaping — the tag-in-slot landmine; arg-order mapping). Supersedes/extends `arg:`.
- **`arg:` enum-constraint** (`$meta` whitelist of allowed arg values).
- **Allowlist shape B/C** (DB allowlist gated by the PHP filter / DB-alone) — decision
  NOT locked, B/C not excluded; A's filter extends to B non-breakingly (a callback feeds
  DB entries through the same gate).
- **Shortcode-replacement ambition** (arbitrary args / attr parsing) — past v1.

## Relationship to other future work

- **Near-term alternative to `{{if}}`** (future-work row, the THIRD composition verb):
  `{{if}}` would branch a template on a read field value, also driven by
  `bws_get_game_result` (term-name → template). `{{call}}` sidesteps that by letting the
  existing PHP function do the branching. If `{{if}}` ships, they don't supersede each
  other — `{{call}}` stays for genuinely imperative/complex returns.
