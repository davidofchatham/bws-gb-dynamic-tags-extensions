# Traversal Pipeline — Conceptual Design (Phase 1 SHIPPED 1.14.0)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

**Phase 1 shipped 2026-07-07** (branch `feat/traversal-pipeline`) — the source factory
(`bws_resolve_base_source`) + data-driven step engine (`bws_run_traversal`/`bws_run_step`) replacing
N×M source-class dispatch, plus two plan-classified defect fixes (`src:ref` plural, term-kind ambient).
See CHANGELOG 1.14.0, `docs/tag-reference.md`, `CONTEXT.md` §I9.

**Consolidated 2026-08-19** (post-1.17.0 plan-file sweep): a separate live stub,
`.claude/plans/traversal-pipeline.md`, existed since ship solely to track what shipped and where each
remaining item went; it added nothing this banner doesn't now say, and its filename collided with this
one, which had already caused two `future-work.md` rows (FW-7, FW-8) to cite this file's
§Post-Phase-1 convergence via a path that resolved to the OTHER file. Deleted; every inbound citation
repointed here. As of that date:

- **Phase 2** (remaining #19 context kinds — author shipped 1.15.0, blog/search/date/post-type still
  open) → **FW-9**, detail home `.claude/plans/context-aware-base-tags.md` (not this file).
- **Primary-source + ref-hop parity** → **FW-32**, detail home `docs/design-history/ref-hop-parity.md`
  (promoted out of the old stub; still open, unrelated to this file's own archival).
- **§Post-Phase-1 convergence below** → **FW-7** (still open) and **FW-8** (still open, rides FW-33).
- **Seam return-shape convergence** (FW-49 + FW-3(b) + FW-48 halves) — SHIPPED 1.16.0. Full record:
  `docs/design-history/traversal-convergence-fw49.md`. FW-3's field-object-formats residue and FW-48's
  factory `post→author` hop remain open elsewhere.
- **`src:site` slot for remaining try_ tags** (FW-4) — SHIPPED 1.15.0. Ledger row in
  `docs/future-work.md`, CHANGELOG 1.15.0.
- **try_ fork collapse** (FW-5) — RETIRED, merged into FW-43.

**Read the rest of this file as the record of Phase 1's design, not as a statement of how anything
currently works.** Live homes for Phase 1 itself: CHANGELOG 1.14.0, `docs/tag-reference.md`,
`CONTEXT.md` §I9.

---

# Traversal Pipeline — Conceptual Design

**Status: Conceptual. High-level by intent — implementation details deferred until prerequisites land.**

**Phase 1 of the L1-full pair** (Phase 2 = `context-aware-base-tags.md`): builds the source factory + step engine; ships alone, existing kinds only. **Behavior-identical EXCEPT two plan-classified defect-fixes (grill 2026-07-06, Q1+Q8):** (1) the `src:ref` collapse-to-first fix ships WITH the engine as its acceptance feature — `limit:1` default keeps existing tags identical, only `src:ref`+`limit>1` changes (broken-first-only → working); unblocks #30/#28. (2) **term-kind ambient ships in Phase 1** (the first #19 kind — probe-backed signal, I1 term analog column already decided, term readers exist, zero new options): bare base tags on term archives resolve the TERM (title→name, content→description, permalink→term URL, **and key reads → term meta — applies to ALL base tags**, text/datetime/email/phone included, per I1 "keyed by nature in ALL contexts"). Both classified bug-fix by §Migration/Compat. Remaining #19 kinds (author/date/search/PTA/latest-home — each needs option-surface design) stay Phase 2. Ownership split: THIS plan owns HOW the factory dispatches (§Base Resolution ambient-detection contract); the context plan owns WHAT each kind resolves to. On ship, this file archives whole; Phase 2 continues.

**Relationship to the source-resolution model (2026-06-12 grill):** this plan is **L1-full** — the deep form of the L1 source-resolution seam (CONTEXT.md §L1/L2/L3, ADR 0002). The source factory (§Base Resolution) IS L1 (resolve source); the `ref`/`srcTermIn` steps are L1 traversal; `bws_read_field` is L2. The **N×M source-class explosion this plan kills is the same "L1 not shared" smell** as the `bws_email_resolve_addresses`/`bws_phone_resolve_numbers` clones the try_email/phone work extracts. **Sequencing:** the shallow seam (**L1-lite** — shared resolve-read fn + post/term/site kinds, no class retirement) ships first via #32 try_email/phone; this plan deepens that same shared fn's internals into the factory+pipeline WITHOUT changing its call-sites (consumers written against the L1-lite seam survive untouched — the seam is the interface, the pipeline fills it in). See §Sequencing.

**History:** First drafted during v1.6.0 development. Updated post-1.6.0 to reflect shipped naming (`srcTermIn` replaces the `tax` step option after collision with GB's reserved key) and to integrate the modifier-composition use case (`try_view_*`, `try_term_*`) discovered while scoping portal plugin try_ support. Vocabulary reconciled 2026-06-12 to the resolved-source model (typed entity → resolved source, variable payload).

---

## Problem

Source classes conflate two concerns:

- **Base resolution** — where does the read start? (current post, current term, current portal view, future: loop-meta-row, user)
- **Traversal** — hop from that resolved source to another (relationship field, taxonomy term)

This produces O(N×M) source class growth: each new traversal path combination requires a new class (`RelatedPost`, `TermRelatedPost`, `SecondRelatedPost`, `PostTermRelatedPost`, `TermRelatedPost`, …). Adding a second hop, or composing modifier prefixes (`view_` × `try_`) without a registry, multiplies further.

A second, related problem: try_ slot dispatch hardcodes `bws_resolve_post_by_source()` as the per-slot resolver. Modifier-rooted try_ tags (`try_view_*`, `try_term_*`) have no entry point because slot resolution can't be parameterized by modifier base context.

**Parity gap (user-facing — the requirement the structural refactor serves):** today `src:ref` hops only from **ambient context** — `RelatedPost::resolve_id` hardcodes the base as the current post (`get_id($options,'post',$instance)`, class-related-post.php:52). Full parity requires selecting a **primary source** (current / a *specific* term / a *specific* post / pinned resource) AND THEN a ref-hop off **that chosen primary**, not the current-context-only fusion. This is the base-resolution-vs-traversal decoupling below, stated as its acceptance test: "pick primary, then hop." **UX-open:** the option construction has long been unresolved — likely a **separate ref-step option exposed per-`src`-value** (a ref hop only shown/meaningful for certain primary sources), not one global `ref` field. This is the same decoupling as the pinned-resource source (`src:term,<ID>`-style, NOT final) — "select a specific primary" IS pinned-resource selection; "ref from that primary" is the composed step. Two faces of one refactor.

---

## Design principle: start-from-current-context

The pipeline preserves the same model regardless of where the tag renders. Whatever the **ambient context** is at render time — post, term archive, portal view, GB list-block loop item (post or meta row), user — that is what the pipeline starts from.

Tag authoring stays uniform: `{{text key:bio}}` means "read `bio` from the current thing, whatever the current thing is." The pipeline runner detects the ambient context once at entry and produces the base resolved source. Steps then operate generically.

This shifts current kind detection (today scattered: `bws_get_loop_row_context()`, term-archive checks in some callbacks but not others, portal context inside `view_*` modifier dispatch) into one place: the source factory.

---

## Resolved sources (ADR 0002)

Every pipeline step operates on a **resolved source** (CONTEXT.md §Language; this plan's original "typed entity" term IS the resolved source — renamed 2026-07-06 grill Q2, "entity" is on the §Language _Avoid_ list):

```
{ kind: 'post'|'term'|'user'|'meta_row'|'site'|<query-context>, payload: <varies by kind> }
```

- `post`, `term`, `user` — payload is an integer id.
- `meta_row` — payload is the row's flat associative array (GB list block "loop item" when source is a meta/repeater field). No stable integer id — reads operate on array keys.
- `site` — payload is the `wp_options` namespace (or ACF `'option'` object-id for datetime). No traversal input (terminal for `ref`/`srcTermIn` steps).
- query-context kinds (`search`/`date`/`pta`/`404` — see `context-aware-base-tags.md`) — payload is the query/date/search data; no field-read, no traversal.

**Payload varies by kind — this is ADR 0002 (variable-payload resolved source, NOT a uniform `{type,id}`).** The plan already half-had this (`id: int|array`); ADR 0002 makes it the explicit contract. _Avoid_: "typed entity with id" as if id were universal.

**Representation (grill 2026-07-06, Q3): flat assoc array, kind + kind-specific keys — no class, no nested payload envelope.**

```php
array( 'kind' => 'post', 'id' => 123 )
array( 'kind' => 'term', 'id' => 34 )
array( 'kind' => 'meta_row', 'row' => array( /* flat row */ ) )
array( 'kind' => 'site' )   // namespace implicit; no payload key
```

Matches the S2 no-class precedent (src:site) + house array idiom; serializable for
debug logging. Documented ONCE as a PHPDoc typedef-style block on
`bws_run_traversal()` (single owner). Steps read `$source['kind']` and switch;
malformed/unknown kind → step returns `array()` (silent-empty, no crash).

Steps consume one resolved source, produce zero or more of the same or different kind. Entity-kinds (post/term/user/meta_row) are traversable; site + query-context kinds are terminal.

---

## Step Types (data-driven)

Two built-in step types, expressed as PHP arrays:

```php
// Hop via ACF relationship/post_object field
[ 'type' => 'ref', 'field' => 'related_posts' ]
// post     → post[]    (ACF field on post; ID passed directly)
// term     → post[]    (ACF field on term; 'term_' . $id passed to ACF)
// user     → post[]    (ACF field on user; 'user_' . $id passed to ACF)
// meta_row → post[]    (read field from row array; coerce ID list)

// Hop to taxonomy terms (option key 'srcTermIn' in tag string;
// pipeline step type stays 'srcTermIn' for symmetry)
[ 'type' => 'srcTermIn', 'slug' => 'category' ]
// post → term[]  (get_the_terms)
// term → []      (invalid input type; produces empty, short-circuits)
// (Pipeline-internal step type; tag option is also `srcTermIn`. v1.6.0 collapsed
//  legacy `srcTerm` checkbox + `tax` slug into one combined option after `tax`
//  collided with GB's reserved key.)
```

Input/output types:

| Step | Valid input | Output |
|------|-------------|--------|
| `ref` | post, term, user, meta_row | post[] |
| `srcTermIn` | post | term[] |

ACF ID-prefix logic (`'term_' . $id`, `'user_' . $id`) moves into step execution. No longer a source-class concern.

---

## Pipeline Engine

Pure function — no side effects, no registry:

```php
// Pseudocode
function bws_run_traversal( array $base_sources, array $steps ): array {
    $sources = $base_sources;
    foreach ( $steps as $step ) {
        $next = [];
        foreach ( $sources as $source ) {
            $next = array_merge( $next, bws_run_step( $step, $source ) );
        }
        if ( empty( $next ) ) {
            return []; // short-circuit on empty
        }
        $sources = $next;
    }
    return $sources; // flat list; type = output type of last step
}
```

Fan-out is natural — multiple results from one step feed all subsequent steps. `limit`/`sep` applied by the caller on the final source list, not inside the pipeline.

---

## Base Resolution

Sources shrink to one responsibility: return a resolved source. No traversal inside `resolve_id()`.

- `CurrentPost` → `{ kind: 'post', id: $current_post_id }`
- `TaxonomyTerm` → `{ kind: 'term', id: $current_term_id }`
- `PortalSource` (external, bws-portal-system) → `{ kind: 'post', id: $current_view_post_id }`
- Future `CurrentUser` → `{ kind: 'user', id: $current_user_id }`
- GB list-block loop-item context (currently detected via `bws_get_loop_row_context()`) → `{ kind: 'post', id: $row_post_id }` for post-source loops; `{ kind: 'meta_row', id: $row_array }` for meta-source loops.

The source factory is the single point that interprets ambient context — **it IS L1 (resolve source)**. Issues #19 (context-aware resolution) and the loop-item dispatch already in `bws_get_loop_row_context()` collapse into this factory. **#19 = growing this factory with context kinds** (author/date/search/...); `context-aware-base-tags.md` is the #19 coverage roadmap on this same factory. Implicit-source resolution (bare `{{title}}` on a term archive) = the factory detecting ambient context and returning the right resolved source.

### Ambient-detection contract (probe-verified 2026-07-06)

Runtime probe (`{{bws_ctx_probe}}`, GP Elements at `generate_before_header` /
`generate_header` / `generate_before_main_content` + GB Query Loops, across
singular / term archive / search / 404 / posts-page / latest-home) settled the
factory's detection design. These are observed facts, not assumptions:

**Phase 1 kind coverage (grill Q8, option B):** post / term / site / meta_row
(+ external registry sources, e.g. view). The precedence invariant below BINDS
in Phase 1 for these kinds — including queried-object term detection. Query-
context kinds (search/404/date/PTA/latest-home) join per-kind in Phase 2, each
bringing its option surface; until a kind exists, its contexts fall through to
current behavior.

**Precedence (the factory's dispatch order — §V-candidate invariant):**

1. **`loop_ctx.in_loop`** (via `bws_get_loop_row_context()`) → resolved source =
   the row (`row_post_id` / meta_row). Loop context DOES reach tags inside
   Element-rendered GB Query Loops (`generateblocks/loopItem` + `loopIndex`
   present in `$instance->context` there).
2. **else `get_queried_object()` non-null** → entity kind by object class
   (`WP_Term` → term, `WP_Post` → post, `WP_User` → user, `WP_Post_Type` → pta).
3. **else query-context kind by conditional** (`is_search()` / `is_404()` /
   `is_date()` / latest-posts home…). `queried_object === null` + conditional is
   the reliable discriminator.
4. **`$post` / `get_the_ID()` is NEVER an ambient fallback — loop-scoped only.**

**Why rule 4 (the `$post` leak):** on every non-singular context *with results*,
`$post` carries the main query's FIRST row at Element render time — term archive
(term "Member" but `$post` = first benefit post), results-search AND empty
search (`s=""` = match-all → still leaks), static posts page (`$post` = first
post, not the assigned page). Only zero-result contexts (404) leave `$post`
null. A `$post` fallback therefore fails silent-confident (renders a plausible
real title from the wrong entity) exactly where context-awareness matters most.
Search is the sharpest case: `queried_object` null + `$post` populated co-occur.

**Supporting facts:**

- `get_queried_object()` is **hook-stable** (correct from `generate_before_header`,
  the earliest GP Element hook) and **loop-stable** (unchanged across GB Query
  Loop rows — the factory may read it lazily, no pre-loop capture needed).
- GB's `$instance->context['postId']` mirrors stale `$post` on non-singular
  contexts and is absent in one pre-loop wrapper render state — NOT a usable
  identity cross-check. Use `loop_ctx.row_post_id` + `get_queried_object()` only.
- The GB Query Loop wrapper renders in ≥2 pre-loop context states (with/without
  `postId`); both have no `loopItem`, so rule 1 correctly falls through.

---

## Pipeline Assembly

Callbacks (or a shared dispatch function) assemble steps from options inline — no TraversalRegistry needed. Steps are at most a few PHP arrays; the overhead of a registry is not justified.

```php
// Example: base-tag callback assembles from $options
$steps = [];
if ( 'ref' === ( $options['src'] ?? '' ) ) {
    $steps[] = [ 'type' => 'ref', 'field' => $options['ref'] ?? '' ];
}
if ( ! empty( $options['srcTermIn'] ) ) {
    $steps[] = [ 'type' => 'srcTermIn', 'slug' => $options['srcTermIn'] ];
}
// Run pipeline
$sources = bws_run_traversal( [ $base_source ], $steps );
```

---

## Modifier composition

Modifier prefixes (`term_`, `view_`, future `user_`) become source factory choices, not separate dispatch trees.

`register_modifier()` already declares `base_source_key` (source resolver for the modifier's starting context). Under the pipeline:

- `term_*` modifier source factory = `TaxonomyTerm` → `{kind: 'term', id}`
- `view_*` modifier source factory = `PortalSource` → `{kind: 'post', id}` (portal post type)
- Default (no prefix) source factory = ambient detection (post / term / loop-row, per §Base Resolution)

Modifier callbacks assemble identical pipelines as base callbacks. The only difference is which factory produces the base resolved source. `traversal_source_key` (currently a separate "ref hop from this modifier" source class) disappears — the `ref` step does that work generically.

### Try_ as a modifier composition

`try_` orchestration (5 slots, carry-forward, srcTermIn per slot, fallback) is independent of which modifier roots the chain. Under the pipeline:

- Slot = `(steps[])` assembled from slot options (`src`, `ref`, `srcTermIn`).
- Slot 1 base source = modifier's source factory (default = ambient detection).
- Slot N (N>1) inherits prior slot's steps[] when blank, overrides when set.
- One `try_core_fn` per template, dispatching on output kind (`post` / `term` / `meta_row`) — the current `try_core_fn` vs `try_term_fn` fork in `register_modifier_template` collapses.

This unblocks `try_view_*`, `try_term_*`, etc., as a near-free drop-out of the refactor: parameterize the try_ generator by source factory; modifier descriptors opt in via `'supports_try' => true`.

---

## Typed Sources

A typed source may declare `base_source_kind(): string`. The pipeline engine validates that the first step's valid input kinds include the declared base kind. Invalid → empty, no crash.

Base tags declare no type → no pre-validation → silent empty on mismatch at render time. This is acceptable and matches current behavior.

Editor-side: typed sources can suppress incompatible step options in the UI (e.g. don't offer `srcTermIn` step on a user source). Base tags cannot reliably do this — loop item context is ambiguous (GB uses "loop item" for both meta arrays and relationship field arrays).

---

## Impact on Existing Source Classes

| Class | Fate |
|-------|------|
| `CurrentPost` | Stays; already no traversal; simplifies slightly. Becomes one ambient-detection branch in the source factory. |
| `TaxonomyTerm` | Stays; already no traversal; ACF ID-prefix logic moves to engine. |
| `PortalSource` | **EXTERNAL — not ours.** Lives in bws-portal-system (`includes/integrations/class-portal-source.php`), extends our `AbstractSource`, self-registers via `SourceRegistry::register_source()` + `register_modifier(['base_source_key'=>'view'])`. Phase 1 MUST NOT break the external source contract (`AbstractSource::resolve_id()` + both registries) — the factory wraps a registered external source's `resolve_id()` as `{kind:'post', id}`. Contract doc: `docs/plugin-integration.md`. |
| `RelatedPost` | Superseded by `CurrentPost` + `ref` step. |
| `TermRelatedPost` | Superseded by `TaxonomyTerm` + `ref` step. |
| `SecondRelatedPost` | Superseded by `CurrentPost` + `ref` + `ref` steps. |
| `PostTermRelatedPost` | Superseded by `CurrentPost` + `srcTermIn` + `ref` steps. |

Existing source classes kept as deprecated for non-base tags still referencing them. Base-tag dispatch stops routing through them.

## Post-Phase-1 convergence (surfaced during build, NOT Phase 1)

Two duplications became visible once the factory + seam landed (T1-T4). Both are
genuine convergence opportunities but touch the wide read path / term-detection
blast radius Phase 1 deliberately fenced out. Tracked in `docs/future-work.md`;
capture here so the build context isn't lost:

- **Collapse `bws_read_field` internal resolution (field-helpers.php:271-296) into
  the factory.** The seam already bypasses `bws_read_field`'s own loop/term-archive
  inference via an explicit id (V12). Once the wrapper (T5) routes its ~30 callers
  through the factory too, that inference duplicates the factory everywhere and
  can retire. Deferred: touches the 30-caller read path — a Phase-1 blowout.
- **Fold `bws_reliable_term_context_detection` (taxonomy-helpers, 5-tier) into
  `bws_capture_ambient_signals`.** Two term-detection impls now coexist; the
  factory's capture is the intended home. Deferred: the 5-tier helper backs
  `TaxonomyTerm::resolve_id` + `term_` modifiers — folding mid-refactor widens
  blast radius. Natural to do alongside the `term_` deprecation glide-path.

**Considered + declined for Phase 1 (2026-07-06): dropping the deprecated tags
early to retire the source classes.** Does NOT simplify Phase 1. Two surfaces,
neither helps: (a) the rename wrappers in `deprecated-tags.php`
(`term_name→term_title`, `related_post_url→related_post_permalink`, image
renames) delegate to CURRENT tags, not to the N×M source classes — dropping them
touches nothing structural. (b) The `related_post_*` / `post_term_related_post_*`
families DO use the source classes, but they are a separate in-flight effort on
branch `deprecated-tag-removal` (plan `currently-deprecated-tags-work-quiet-snail.md`,
stale base) with a "re-add registry-only after" step — pulling that in means
merging a stale branch + resolving its drift + coordinating the re-add = MORE
scope/risk, blows the Phase 1 fence. And the classes are cheap while registered
(inert no-op; factory already bypasses them on the seam path). Retiring the
source classes is the clean FOLLOW-UP once Phase 1 lands + the `related_post_*`
removal proceeds on its own release — then those families become `factory + ref
step` and the classes drop together.

---

## Function surface (grill 2026-07-06, Q4)

| Function | Fate |
|---|---|
| `bws_resolve_base_source( $options, $instance ): array` | **NEW — the source factory.** L1 entry; returns ONE base resolved source (Q3 shape). Ambient detection (probe contract) + explicit-token handling + external-source (`SourceRegistry`) delegation live here. |
| `bws_run_traversal( array $sources, array $steps ): array` + `bws_run_step()` | **NEW — the engine.** `bws_run_traversal` owns the Q3 resolved-source typedef PHPDoc. |
| `bws_resolve_field_values()` | **Signature + `string[]` return FROZEN** (the L1-lite seam — call-sites unchanged). Internals → factory + steps + traversal + L2 read per resolved source. **Q1 ref plural fix lands here.** |
| `bws_resolve_post_by_source()` | **Thin compat wrapper, NOT deleted** (39 call-sites / 7 files) — factory + steps → first post id \| false. Callers migrate opportunistically; formal deprecation later. |

**Accepted consequence:** wrapper callers (datetime, fn-tags, template-registry)
stay collapse-to-first in Phase 1; the plural fix reaches seam consumers only.
#30 (datetime list mode) = moving datetime onto the seam — a Phase-1.5 rider,
not wrapper surgery.

## Migration Path

**Release slicing (grill 2026-07-06, Q5): Phase 1 ships steps 1–4 in ONE
release; step 5 = Phase 1b (own release, can follow immediately — portal is
ours, parallel work fine); step 6 opportunistic.** Rationale: 3–4 are the
payoff (N×M retirement + fork collapse) and share one manual-test sweep with
1–2; step 5 ships NEW user-facing tags (`try_view_*`) + a coordinated (tiny)
bws-portal-system release — its own CHANGELOG story. **Portal is NOT stranded
by Phase 1:** `base_source_key:'view'` resolves through the factory's
`SourceRegistry` delegation, the `ref` step replaces `PortalRelatedPost`'s
traversal, and `traversal_source_key` is **accepted-but-ignored** (NOT
removed) — `view_*` tags render identically with zero portal changes; one
release of registered-but-unused `PortalRelatedPost` is the only dead weight.
Portal's 1b diff: drop `traversal_source_key` + `PortalRelatedPost`
registration, add `'supports_try' => true`. `plugin-integration.md` documents
`traversal_source_key` as no-op-deprecated in Phase 1.

1. Build pipeline engine (`bws_run_traversal`, `bws_run_step`) + source factory.
2. Rewrite `bws_resolve_post_by_source()` (base-tags.php) to delegate to the factory + pipeline.
3. Modifier callbacks (`TagTemplateRegistry::make_modifier_callback`) assemble pipelines instead of branching on `src` and `srcTermIn`. `traversal_source_key` accepted-but-ignored (see above).
4. `generate_base_try_tags()` parameterized by modifier descriptor's source factory. `try_core_fn` / `try_term_fn` fork collapses to single kind-dispatching `try_core_fn`.
   — **Phase 1 release boundary —**
5. [Phase 1b — **SHAPE UNDECIDED, do NOT bake try_view_ in** (grill 2026-07-06)] Two competing shapes, decide before 1b:
   - **(a) Prefix fan-out:** `register_modifier()` accepts `'supports_try' => true`; portal opts in (+ drops the retired key + `PortalRelatedPost`); `try_view_*` tag sets register automatically.
   - **(b) Sources-as-src-values (user-preferred direction):** registered sources (incl. `view`) become base-tag `src` enum values — `{{text src:view}}` — and try_ slots inherit via #26 slot-option derivation. **NO new tag sets at all.** Precedent: site went exactly this route (`src:site` + `try_allow_site_slot`, never a `site_` prefix family); memory already anticipates `src:view` as a base option (term-deprecation path). Dissolves the prefix explosion at the TAG level, not just the class level; also reframes `view_`'s own future (rooting modifiers deprecate like `term_`).
   - **Phase 1 is identical under both** — factory + `SourceRegistry` delegation + engine is the shared substrate. I4 source-gate + UX (src dropdown gains "Current View" only when portal active; GB native-source exclusion) get worked at 1b decision time.
6. Long-term: deprecated wrappers (`related_post_*` etc.) migrate or retire.

## Test harness (grill 2026-07-06, Q7)

`tools/test/traversal-pipeline-test.php` — pure-logic CLI test, no WP (pattern:
`field-discovery-test.php` / `preview-label-test.php`). Covers:

- **Engine fold:** fan-out (1 source × step → N), chained steps, short-circuit on
  empty, empty step-list passthrough, 4096-sanity none needed — keep it lean.
- **Q3 shape:** unknown/malformed kind → `array()` (silent-empty, no crash).
- **Factory precedence table:** the probe's truth table as fixture rows with
  stubbed signals (loop_ctx set/unset × queried-object kinds × conditionals) —
  makes the §V precedence invariant EXECUTABLE. Signal reads must be injectable
  (factory takes a signals array internally or a test seam) — design detail for
  SPEC §I.
- Update trigger row goes into CLAUDE.md §Update triggers on ship.

---

## What Does Not Change

- `SourceRegistry` — still used for modifier registration; entries shrink.
- `MigrationRegistry` / `TagConverter` — unchanged.
- Option names (`src`, `ref`, `srcTermIn`, etc.) — orthogonal to pipeline shape. Renames continue to be tracked in `docs/deprecated-tags-options.md`.
- Editor preview labels — assembly continues to read tag options; pipeline doesn't run in preview path. (Issue #21 lazy-resolve refactor is independent.)

---

## Out of Scope (this design)

- Combined/merged option controls (UI concern, separate decision).
- External custom traversal step types (not needed at this time).
- Editor warning for base tags (loop context not reliably typed in editor).

---

## Sequencing (within the L1 issue cluster)

The cluster shares the L1 source-resolution seam. Two sizes of L1 work:

- **L1-lite (the seam):** shared resolve-source + read-field fn, variable payload, post/term/site kinds. Additive; no source-class retirement. **SHIPPED 1.11.0 via #32** as `bws_resolve_field_values` (field-helpers.php — extracted the email/phone resolve-clones; handles src:site + srcTermIn term-hop list + single post/term). Defines the interface consumers call. NB: `src:ref` plural-source (lists from a ref hop) is NOT yet fixed — the seam currently collapses ref to one target; that plural fix is still pending (rider precondition for #30/#28, see below).
- **L1-full (this plan):** source factory + data-driven traversal steps; retires N×M source classes; collapses `try_core_fn`/`try_term_fn` fork. Deepens L1-lite's internals, same call-sites.

```
#26 SHIPPED 1.11.0 (option-surface; derive slot options, filter site)
  └─ #32 try_email/phone SHIPPED 1.11.0 — built L1-LITE seam (bws_resolve_field_values; post/term/site), extracted resolve-clones
       ├─ #28 src:site→ref — rides L1-lite + one ref step                            [pending]
       └─ #30 datetime list mode — rides L1-lite PLURAL-SOURCE FIX (ref no longer    [pending — plural fix NOT in 1.11.0]
       │     collapse-to-first); srcTermIn term-hop list already works post-#32
  └─ [L1-FULL: this plan] — grows L1-lite shared fn → factory + steps (call-sites unchanged)
       ├─ retires RelatedPost / TermRelatedPost / SecondRelatedPost / PostTermRelatedPost
       ├─ collapses try_core_fn / try_term_fn fork → single kind-dispatching fn
       ├─ #23 nested-ID bug — MIS-SCOPED here (grill Q6): GB Pro query-block bug (set_query_data
       │     uses get_the_ID(), ignores loopItem); fix = render_block_data shim, pipeline-independent.
       │     Our loop_ctx already resolves the row correctly. OUT of this plan.
       └─ #19 context-aware — grows factory with context kinds (coverage roadmap = context-aware-base-tags.md)
```

**Key ordering facts:**
- **L1-lite is NOT throwaway under L1-full** — it defines the shared-fn boundary consumers call; L1-full swaps the fn's internals (factory+pipeline), call-sites survive. Confirmed at code level: `bws_read_field` (L2) is the read half; the pipeline deepens resolve (L1), not the read contract.
- **traversal-pipeline (L1-full structural) and #19 (L1-full coverage) land adjacent.** The pipeline builds the factory; #19 fills its context kinds. Doing #19 without the pipeline = building context-detection into the scattered dispatch this plan consolidates (§Design principle). Doing the pipeline without #19 = factory with only post/term/loop kinds. Build the factory, then grow its kinds.
- **#30 + #28 are L1-lite riders** — they need the plural-source fix / site kind, not the full pipeline. Can land between L1-lite and L1-full. **L1-lite SHIPPED (1.11.0) but the `src:ref` plural-source fix did NOT** — `bws_resolve_field_values` does srcTermIn term-hop lists, but ref still collapses to one target. So #30's "ref no longer collapse-to-first" precondition is still open; the seam exists, the plural fix is the next increment on it.

## Cross-references

- **CONTEXT.md §L1/L2/L3 + ADR 0002** — the source-resolution model. This plan = L1-full. "Typed entity" = resolved source.
- **`context-aware-base-tags.md`** (#19) — the coverage roadmap that grows this plan's source factory with context kinds. Lands adjacent to this plan.
- **`docs/design-history/try-email-phone-and-slot-derivation.md`** (#32, SHIPPED 1.11.0) — built the L1-lite seam (`bws_resolve_field_values`) this plan deepens. Archived.
- **Issue #19** (Q5/Q7 — base tag resolution in non-post contexts) — source factory subsumes term-archive + user-context dispatch.
- **Issue #23** (nested GB Pro post_meta L3+ ID inheritance) — **NOT this plan's to fix (grill 2026-07-06, Q6).** GB Pro's `set_query_data` resolves `meta_key_id:current` via global `get_the_ID()`, ignoring loop context — L3's QUERY never iterates, our callbacks never run; our `loop_ctx` already reports the correct row. Fix = a `render_block_data` shim (per the issue), independent work item.
- **Issue #21** (Editor preview: resolve-then-label) — CLOSED v1.6.2 (per memory); preview path still doesn't run the pipeline.
- `try_` modifier composition (sibling to `view_`/`term_`) — design captured here; not a separate plan. Lands as side-effect of step 4–5.
- `docs/plugin-integration.md` §`register_modifier()` — will need update to document `supports_try` flag and that `traversal_source_key` is no longer required once pipeline lands.
