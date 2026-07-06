# SPEC — Traversal Pipeline Phase 1 (1.14.0-target, in flight)

Branch `feat/traversal-pipeline` (create on build start). Phase 1 of L1-full pair —
plan `.claude/plans/traversal-pipeline.md` (grill-hardened 2026-07-06, Q1-Q8);
Phase 2 = `.claude/plans/context-aware-base-tags.md`. Phase 1b (supports_try vs
sources-as-src-values fork) NOT here — undecided, own release. Truncate on merge
per SPEC lifecycle.

## §G — goal

Kill N×M source-class explosion: source factory (L1) + data-driven traversal
steps replace per-combination classes. Ships two plan-classified defect fixes:
`src:ref` plural (collapse-to-first dies at seam) + term-kind ambient (bare base
tags resolve term on term archives). Everything else byte-identical.

## §C — constraints

- C1. `bws_resolve_field_values()` signature + `string[]` return FROZEN — L1-lite seam, call-sites untouched (V3).
- C2. `bws_resolve_post_by_source()` NOT deleted — 39 call-sites / 7 files; becomes thin wrapper (V4). No mass caller migration this release.
- C3. External source contract unbreakable: `AbstractSource::resolve_id()` + `SourceRegistry` + `register_modifier()` keys. bws-portal-system passes `traversal_source_key` TODAY → accept-but-ignore, never reject (V5). Zero portal changes required for Phase 1.
- C4. Behavior-identical EXCEPT ref-plural (V6) + term ambient (V7). Search/404/date/PTA/latest-home contexts fall through to CURRENT behavior — their kinds are Phase 2.
- C5. Resolved source = flat assoc array, NO class, NO nested payload envelope (grill Q3; S2 no-class precedent) (V2).
- C6. Author kind OUT — no I1 analog column (display name / bio vocabulary undecided). Query-context kinds OUT — option surfaces undesigned.
- C7. NO `supports_try` / `try_view_*` — 1b fork undecided (plan §Migration step 5).
- C8. Editor preview path unchanged — pipeline never runs in preview; preview labels still assemble from options (#21 posture).
- C9. try_ transparency (CONTEXT.md I6) — fork collapse must not alter any try_ output; slot = standalone parity.

## §I — surfaces

- I.engine = `includes/helpers/traversal-pipeline.php` (NEW) — `bws_run_traversal()`, `bws_run_step()`, `bws_resolve_base_source( $options, $instance, $signals = null )` + `bws_capture_ambient_signals()`. Resolved-source typedef PHPDoc lives on `bws_run_traversal()` (single owner). **Factory = pure dispatch on injected `$signals` (T2 grill 2026-07-06): signals array (`queried_kind`/`queried_id`/`is_tax`/`loop`); `$signals=null` → live capture via `bws_capture_ambient_signals()` (the SOLE `is_tax`/`get_queried_object`/loop_ctx read). Injection makes V1/V7 precedence unit-testable — mirrors T1's injectable reader.**
- I.seam = `includes/helpers/field-helpers.php` — `bws_resolve_field_values()` internals rewired to factory + steps; signature frozen.
- I.dispatch = `includes/tags/base-tags.php` — `bws_resolve_post_by_source()` → wrapper; base callbacks gain kind dispatch for term ambient.
- I.registry = `includes/classes/class-tag-template-registry.php` — `make_modifier_callback()` assembles pipelines; `generate_base_try_tags()` parameterized by source factory; `try_core_fn`/`try_term_fn` fork collapses.
- I.sources = `includes/classes/` source classes — base tags stop routing through `RelatedPost`/`TermRelatedPost`/`SecondRelatedPost`/`PostTermRelatedPost`; classes stay registered for deprecated tags.
- I.test = `tools/test/traversal-pipeline-test.php` (NEW) — pure-logic CLI harness.
- I.doc = `docs/plugin-integration.md` — `traversal_source_key` documented no-op-deprecated; CHANGELOG 1.14.0 — unreleased.
- I.readme = `README.md` — L9 headline caveat (term contexts) + "term name*" footnote (L19/26/27) — post-test flip ONLY (T11). User-review-gated prose.

## §V — invariants

- V1. **Factory precedence: `loop_ctx` → `get_queried_object()` → current-post; `$post`/`get_the_ID()` NEVER ambient fallback.** Probe-proven: `$post` carries main query's first row on EVERY results-bearing non-singular context (term archive 48418, search 47955, empty-search 51604). Binds for Phase-1 kinds (post/term/site/meta_row/registry); contexts without a kind fall through to current behavior. Owner: I.engine.
- V2. **Resolved source = flat array `{kind + kind-specific keys}`; unknown/malformed kind → `array()` silent-empty, no crash.** `{'kind'=>'post','id'=>N}` / `{'kind'=>'term','id'=>N}` / `{'kind'=>'meta_row','row'=>arr}` / `{'kind'=>'site'}`. Typedef PHPDoc on `bws_run_traversal()` ONLY. Owner: I.engine.
- V3. **Seam freeze: `bws_resolve_field_values()` signature + raw `string[]` return unchanged; every existing caller renders identically** (except V6 plural). Owner: I.seam.
- V4. **Wrapper compat: `bws_resolve_post_by_source()` returns first post id | false, byte-compatible across all 39 call-sites.** Wrapper callers stay collapse-to-first — plural reaches seam consumers only. Owner: I.dispatch.
- V5. **Portal renders identically with ZERO portal changes:** `base_source_key:'view'` resolves via registry delegation; `ref` step replaces `PortalRelatedPost` traversal; `traversal_source_key` accepted-but-ignored (never rejected, never required). Owner: I.registry.
- V6. **`src:ref` plural: seam consumers read N ref targets; `limit` default 1 → output unchanged unless author set `limit>1`.** Fan-out via ref step, no `bws_extract_post_id` collapse inside pipeline. Owner: I.seam, I.engine.
- V7. **Term ambient: bare base tags on term archive (outside loop) resolve the TERM — analogs per CONTEXT.md I1 (title→name, content→description, permalink→term URL), key reads → `bws_read_term_field()`, ALL base tags incl. text/datetime/email/phone. Explicit options ALWAYS beat ambient.** Non-analog gaps stay honest-empty (image → empty, I1 gap #29). Owner: I.engine, I.dispatch.
- V8. **try_ output byte-identical through fork collapse** — single kind-dispatching `try_core_fn`; slot resolution = standalone tag parity (I6). Owner: I.registry.
- V9. **Engine pure + deterministic: no side effects, empty-step passthrough, short-circuit on first empty step, fan-out preserves document order.** Owner: I.engine.
- V10. **Factory + resolver sit DOWNSTREAM of try_ slot carry-forward — never re-derive analogs or apply slot-position semantics.** `generate_base_try_tags` assembles `$slot_opts` first (`$last_src`/`$last_ref`/`$last_key`/`$last_use` merged for slot ≥2 inherit; registry.php:697-712) THEN calls the resolver. Slot-1 strip-default-first vs slot-≥2 carry-forward (CONTEXT.md I1) is UPSTREAM option-assembly; the factory receives fully-materialized `$slot_opts` + dispatches purely on them (no slot notion) — re-deriving would double-apply. `srcTermIn` read per-slot, NEVER carried. Binds T8 fork collapse: preserve assemble-then-resolve ORDER; factory stays downstream. Owner: I.registry (assembly), I.engine (purity).
- V11. **`src:ref` on a term archive bases the ref-hop on the AMBIENT TERM (term → post[] step), NOT on `$post`.** The term is the ambient resolved source (V7); a ref step hops its relationship field off the term — V7 applied to the ref case, NOT deferred. This FIXES a live leak: today `RelatedPost::resolve_id` reads the rel field from `GenerateBlocks_Dynamic_Tags::get_id($options,'post')` = `get_the_ID()` = the stale first-loop post on an archive (probe 48418), so `src:ref` on an archive currently reads a relationship field off an arbitrary leaked post. Factory does NOT gate term-ambient by src → ref included. Confined to term archives: on singular pages `queried_kind` is null → post base, byte-unchanged (no regression, V3/V4 safe). The deferred parity gap (`traversal-pipeline.md` §Problem) is PINNING a SPECIFIC non-ambient primary (a chosen term/post ID THEN hop) — unrelated to ambient-term-as-base. Owner: I.engine. (Drove B1.)
- V12. **Seam dispatches L2 by resolved-source KIND — factory owns source-selection, per-kind reader owns the read; NO double resolution.** `bws_resolve_field_values`: factory → `{kind,id}` → dispatch `site`→`bws_site_read_option`, `term`→`bws_read_term_field`, `post`→`bws_read_field` with an EXPLICIT `$post_id`. The explicit id triggers the v1.7.1 explicit-wins rule (field-helpers.php:242), bypassing `bws_read_field`'s OWN loop/term-archive inference (lines 271-296) so the factory's resolved source is authoritative — the two resolution layers never fight. `bws_read_field`'s internal fallback stays LIVE for its ~30 non-seam callers (reached via the wrapper, T5) but is inert on the seam path. Owner: I.seam. Regression risk: a term read routed through `bws_read_field` WITHOUT an explicit id would re-derive context and could diverge from the factory — always pass the id / route term→`bws_read_term_field`.

## §T — tasks

| id | st | task | cites |
|----|----|------|-------|
| T1 | x | I.engine file: `bws_run_traversal` + `bws_run_step` (ref, srcTermIn steps) + typedef PHPDoc | V2,V9,I.engine |
| T2 | x | `bws_resolve_base_source()` factory: loop_ctx → queried_object(term) → explicit tokens (site/registry) → current post | V1,V5,V7,V10,I.engine |
| T3 | x | I.test harness: engine fold rows + Q3 shape rows (LANDED T1) + factory precedence fixtures — drive `bws_resolve_base_source` with injected `$signals` (probe truth table: term-archive→term, explicit-src-wins, loop-row-in-archive→row post, search/404 leak-guard, src:ref-gate V11) | V1,V2,V7,V9,V11,I.test,I.engine |
| T4 | x | Rewire `bws_resolve_field_values()` internals → factory + steps; ref plural for seam consumers | V3,V6,V11,V12,I.seam |
| T5 | . | `bws_resolve_post_by_source()` → thin wrapper (factory + steps → first post id). SCOPE-NARROWED (T4 finding): seam NO LONGER calls it (uses factory + `bws_field_values_assemble_steps` directly), so wrapper = PURE back-compat for its ~30 non-seam callers (deprecated-tags.php + site code) — off the value-list read path. Lower risk; preserve V4 byte-compat only. | V4,I.dispatch |
| T6 | . | Base callbacks: kind dispatch for term ambient (analog + key reads); explicit-wins guard | V7,C4,I.dispatch |
| T7 | . | `make_modifier_callback()` assembles pipelines; `traversal_source_key` accept-but-ignore | V5,I.registry |
| T8 | . | `generate_base_try_tags()` parameterized by factory; collapse `try_core_fn`/`try_term_fn`; PRESERVE assemble-$slot_opts-then-resolve order (factory downstream of carry-forward) | V8,V10,C9,I.registry |
| T9 | . | Base-tag dispatch stops routing through N×M classes (`RelatedPost` etc.); classes stay for deprecated tags | C4,I.sources |
| T10 | . | Manual sweep: singular / term archive (ambient!) / loops (2a+2b) / view_ tags / try_ chains / search+404 (unchanged) / ref limit>1; plugin-integration.md + CHANGELOG | V1,V5,V6,V7,V8,I.doc |
| T11 | . | AFTER testing proves V6/V7: update README caveats — flip L9 term-archive limit ("not yet for taxonomy term contexts such as archives" now works), narrow "term name*" footnote, note ref limit now honored. NOT datetime L22-23 (wrapper caller = still first-only, C4). User-facing prose → user review gate + no-em-dash rule. | V6,V7,C4,I.readme |

## §B — bugs

| id | date | cause | fix |
|----|------|-------|-----|
| B1 | 2026-07-06 | LIVE leak (pre-existing): `src:ref` on a term archive reads its relationship field off `get_the_ID()` = the stale first-loop post (probe 48418), not the ambient term — `RelatedPost::resolve_id` bases on GB `get_id($options,'post')`. Factory must base ref on the ambient term (V11). [First mis-diagnosed the fix direction during T3; user corrected — the defect is the leak, not a gate.] | V11 |
