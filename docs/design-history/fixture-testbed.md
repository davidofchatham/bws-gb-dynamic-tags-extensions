# Archive: the fixture testbed — design notes (FW-42, deliverable SHIPPED 2026-07-17)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — never as a statement of how the testbed
> works now. For current state read [`docs/testbed.md`](../testbed.md) (operating it),
> [`tools/fixtures/core-structures/README.md`](../../tools/fixtures/core-structures/README.md)
> (the blueprint) and [`docs/testing.md`](../testing.md) (the two test layers).
>
> **Published 2026-09-15.** Three amendments dated that day sit in §Open, recording what the
> intervening two months settled and what they did not. The fixture-page cut this file left open is
> tracked at **FW-97**, and the blueprint-composition question it leaves genuinely live at **FW-133**.

**Status:** Deliverable A SHIPPED 2026-07-17 (commit `8dddc88`): `tools/fixtures/core-structures/`
(schema + manifest + seed + blocks generator + verify smoke test), seeded + verified 10/10 on
`testbed.test`, matrices de-`[SUB]`ed. Render seam spiked and PROVEN 2026-07-16. Tracked as
**FW-42** in `docs/future-work.md`.
**Tooling SHIPPED 2026-07-17:** env-repo `bin/seed.sh <site> <blueprint>...` runner (resolves
blueprint name → repo seed.php on the mount, plugin-blind, list-order); `wp bws render-tag`
command (commit `3bc045f`) — grown from the spike, which is now DELETED (`12365e7`). Command
modes: `--url` (real ambient via `wp()`; read from WP-CLI runtime config since --url is a
global param consumed before the command), `--loop-item=<id>` (synthetic loop row, wins over
ambient), `--porcelain`. Matrix pages re-split BY SOURCE-STATE (matrix-post-meta /
matrix-terms-valid|mixed|junk), families accrete sections in (`52d071d`). Blueprint renamed
tags-core → core-structures (`7f32ec6`).
Remaining: Deliverable B accretion (tag-by-tag), editor-eyeball pass by user, snapshot the
licensed+seeded baseline, degenerate-term-context fixture (tier 3b below).
Build finding: `limit` defaults to 1 in srcTermIn list mode — phone R3.2 was under-specified
(fixed with explicit `limit:5`); first proof the fixture surfaces matrix under-specification.

Goal: a seeded WordPress site that (a) removes the hand-build setup cost currently blocking
manual matrix rows from being run, and (b) gives the editor-side surface a place to be eyeballed.
Runs on the local **wp-litespeed** OpenLiteSpeed/Docker env (see its README). The env lives in
WSL2 (`~/wp-litespeed`) as of 2026-07-30 — run `bin/*.sh` from the WSL shell, or address the
container by name (`docker exec wp-litespeed-litespeed-1 …`), which works from either shell.

---

## Decisions

**Ownership — each plugin owns its fixtures (default), shared schema gets ONE defining
owner.** *(Rescoped 2026-07-16 — multi-plugin realization.)* Fixtures live in plugin repos,
not in the wp-litespeed env repo. The env owns *how to run a WP site*; plugins own *what a
test site contains*. A sibling plugin will want its own, for a domain unrelated to tag matrices.
The original ban on shared fixtures ("junk drawer owned by no one") was right about the env
repo but wrong as a total ban: the junk-drawer risk came from *ownerless location*, not from
sharing. Where two plugins need the same data structures (Tags + Meta Conductor), the shared
fixture is its own **blueprint with a single defining owner** — the schema-defining plugin
defines it; other plugins consume by composing it into their seed list and pinning its
manifest keys, same as any API. A standalone `bws-fixtures` package is allowed but NOT created
until a second consumer actually exists.
**Interaction tests get an owner rule, not shared ownership:** the downstream plugin owns
them (a plugin consuming Tags hosts the Tags×consumer tests in its own repo, composing both
blueprints).
*Consequence:* no cross-repo tracker coupling to manage. FW-3 changing the fixture is a
normal in-repo code-touch trigger. Consuming another plugin's blueprint = pinning its
version/keys — breakage surfaces in the consumer's tests, not a tracker.
*Already wired:* `PLUGINS_ROOT` in the env's `.env` mounts this repo into the container,
so a fixture here is already on the container filesystem.

**Seed is blueprint-composing from day one, not monolithic.** *(Added 2026-07-16.)* One
site seeded with N plugins' blueprints is what interaction tests need; a monolithic
"seed this plugin's site" script is the expensive retrofit. Shape: manifest per blueprint;
seed runner takes a blueprint list. Deliverable A ships as the **first blueprint**, not as
a monolith — the runner/blueprint split costs little now and is the whole multi-plugin
story later. This also strengthens the manifest question under Open: the manifest is the
blueprint's *contract* — the thing consumers pin against.

**A dedicated fixture site, NOT injection into the live clones.** Injected cases live in the
clone's DB; `bin/pull.sh` overwrites the DB on re-pull, so every injected case must be
re-injected — i.e. scripted anyway, but against a polluted non-deterministic base. Also
blunts what clones are FOR (per the env README: "does it survive real data" — injecting
synthetic cases makes it not-real data). Injection stays right for a one-off probe needing
a specific client's field config.

**Browsable, not headless.** Costs three commands already in the env (`site.sh --domains`,
`cert.sh`, `hosts.ps1`). Buys the only test environment the editor surface has ever had —
`bws-field-combo`, `bws-term-hop`, `bws-format-input`, progressive slot disclosure, `show_if`
reveal, `bws_build_preview_label()` output are all React in the GB panel, unreachable from wp-cli.
*Cost:* determinism now means "restored state is deterministic" — `snapshot.sh --restore`
before each automated run is mandatory, not optional.

**Division of labor:** agent seeds and asserts; user browses; snapshot-restore is the boundary.

**No MCP adapter.** The agent-facing surface is shell-shaped (seed, restore, render, assert)
and `bin/wp.sh` already covers it. The human-facing surface is a browser. Neither is an MCP
shape. Playwright (wanted later for editor regression) is browser automation, also not MCP.
Revisit only if a third consumer appears that is neither.
*Note:* the first "no" this session leaned on "the agent already has wp-cli," which assumed
headless. The durable reason is the shape argument above.

**Schema in a mu-plugin + a scratch ACF group in the DB.** Pinned groups (the ones assertions
and future Playwright depend on) in the mu-plugin — survives restore, git-diffable. Plus one
empty "Scratch" group seeded into the DB for free experimentation against the combo control.

**Realistic field naming.** `staff_direct_line`, not `phone_case_3`. Editor legibility is the
stronger constraint and it subsumes the harness's needs — the harness reads keys from a
manifest and doesn't care what they're called; the user judging whether the `bws-field-combo`
location tree (`Post/Term/Site fields › group › container`) is usable needs groups that look
like a real site's. Preview text renders the key (`Ref 'X'`), so key realism affects what's
being judged. Also gives Playwright stable selectors later.

**Four review surfaces:** Explicit (src × use × key-state sweep) / Ambient-contexts (one URL
per precedence tier) / Ambient-precedence (competing contexts nested) / Traps (known-empty
cells with expectation notes). Each surface one job; three stable Playwright targets later.

**`[SUB …]` → real fixture keys** in the two existing matrices. Rows become copy-pasteable.
*Trade:* matrices now assume the fixture site; running them elsewhere means re-substituting.

**Seed owns all fixture-defining code; matrices link.** `phone-test-matrix.md` M9's
`register_post_meta` block (lines ~124-132) and M2.6's flex-field recipe move to the seed and
are replaced by a link. Matrices become pure assertions, no setup prose.

**Assert the model, not the implementation, in FW-3/4/5/7/8 zones.** A fixture pinning current
behavior in a tracked-refactor zone is a liability — it converts "known contradiction, tracked,
will change" into "asserted behavior, breaks when fixed." Fixture rows that fail ARE the FW
backlog with dates on them. (`feedback_contradictions_refactorable`.)
- datetime term-ambient → assert **honest-empty** (FW-3's stated current state); this row is
  FW-3(a)'s acceptance test, pre-written.
- Repeater row → assert the base-tag `meta_row` read (the model). Do **NOT** assert the
  datetime/`{{call}}` `false` path — that's the `bws_resolve_post_by_source()` post-id adapter
  FW-3 retires.

**First deliverable: seed the state the EXISTING matrices assume. Accrete the rest tag-by-tag.**
Rationale below under "Scope."

---

## Discoveries (things not to re-derive)

**The existing matrices are only recent-cycle.** `phone-test-matrix.md` (1.10.0) and
`field-selector-test-matrix.md` (1.13.0) are the two most recent things built. text, content,
title, permalink, image, datetime_*, try_*, term_*, `{{call}}` have **no matrix**.
*Consequence:* matrices are a requirements doc for the seed for ~2 tags only. For the rest, the
manifest IS the missing matrix — deriving expectations from `tag-reference.md` tag by tag. That
is spec work wearing a fixture's clothes; don't let it hide inside "fixture work."

**Both existing matrices are in good shape.** Invariant-anchored (survived SPEC truncation),
two-layer (pure harness first, manual rows after), and phone's fail-triage table maps row-groups
to breaking functions. They stay. The fixture does NOT replace or absorb them — it removes their
setup cost.
*`field-selector-test-matrix.md` M2.6 is already marked `DEFERRED — needs the fixture below`.*
The matrix diagnosed this problem before the session did.

**Source axis tests ONCE, through `{{text}}` — IF tags share the factory.** User's framing;
trace confirms it for text/content/title/permalink/image (all via
`bws_base_resolve_source_for_callback()` → `bws_resolve_base_source()`). Kills the
src × use × key-state × tag combinatorics: it's src × use × key-state on `text`, plus a thin
per-tag output check.

**Factory ridership map (traced 2026-07-15):**

| Family | Path | Rides factory? |
|---|---|---|
| text / content / title / permalink / image | `bws_base_resolve_source_for_callback()` → `bws_resolve_base_source()` | **Yes** |
| email / phone | `bws_resolve_field_values()` (L1/L2 seam) | Yes, via seam |
| datetime_single / datetime_range | `bws_resolve_post_by_source()` post-id adapter, both arms | **No** — FW-3 |
| `{{call}}` | same adapter (`fn-tags.php:426`) | **No** — FW-3, documented limit |
| try_ (post + srcTermIn arms) | same adapter (`class-tag-template-registry.php:752-754`, `:837-839`) | **No** — FW-3/5 |
| try_ (term-ambient arm) | `bws_resolve_base_source()` directly (`:816`) | Yes — the only try_ arm that does |
| try_ (site arm) | `$cf(0,…)` → the core fn's own `bws_resolve_field_values()` | via seam; only fires for email/phone (`try_allow_site_slot` set at `email-tags.php:386`, `phone-tags.php:654`) |
| term_ / view_ | hand-builds `{kind,id}` from `SourceRegistry` (`class-tag-template-registry.php:291-294`), feeds `bws_run_traversal()` at `:303` | **No** — rides the traversal engine, bypasses the factory |

**datetime is the only base tag family with no ambient-term arm.** It never resolves a base
source, so on a term archive it gets `false` from the adapter and renders nothing.
`bws_term_datetime_single_core()` exists and is called at `datetime-tags.php:1023` but is only
reachable via `srcTermIn`, never via ambient. (= FW-3's "term-ambient gap".)

**`bws_resolve_base_source()` is FIVE precedence tiers, not three** (`traversal-pipeline.php:244-307`).
The PHPDoc summary ("loop row → ambient term → current post") omits two:
1. `src:site` → terminal, ambient irrelevant
1b. explicit non-current non-ref src → registry source, if it resolves independently
2. loop row (`in_loop` + `row_post_id`) → post
2b. flat repeater row (`in_loop`, no row post id) → **`meta_row` kind**
3. ambient term archive (`queried_kind === 'term'`) → term
3b. **degenerate term context** (`term_context_unresolved`) → **empty array**
4. current post via registry

**Tier 3b (degenerate term context) — deferred with a note, per user.** Conditional tags claim a
taxonomy archive but no `WP_Term` resolves; a bare tag must short-circuit to empty rather than leak
the main query's first post. Cannot be seeded by making a normal term archive — needs a
deliberately broken one. Hardest fixture in the set. Record as a known gap in the fixture README;
not skipped permanently. *(The rule itself lives on `bws_capture_ambient_signals()`'s PHPDoc in
`includes/helpers/traversal-pipeline.php`, which names the signal and the short-circuit.)*

**`src:ref` bases on the ambient entity — it does NOT bypass ambient.**
(`traversal-pipeline.php:259-263`) ref is a *step*, not a base kind. So `src:ref` on a term
archive hops the relationship field **off the term**. Per the comments at `:285-288` that exact
shape was the live leak (probe 48418: `get_the_ID()` = stale first-loop post).
*Consequence:* the two regression pins are ONE fixture, not two — `try_text src:ref` **on a term
archive** is where the v1.7.1 loop-override fix and the probe-48418 ambient leak meet (that leak is
written up in `bws_resolve_base_source()`'s tier-3 comment). Not in a query loop.

**Per-tag output wrinkles (user's assessment):**

| Tag | Wrinkle | Fixture shape |
|---|---|---|
| text / title / permalink | list join + link wrap only | thin — multi-result + `linkTo` cases |
| phone / email | formatted output | the value sets (already in `phone-test-matrix.md` R0–R6) |
| datetime_* | formatted; being revised; still producing rendering surprises | pin the model not the impl (FW-2/3/40/41 churn) |
| content | **visually unassessable** | HTML/CSS-level assertions only — see below |

**Content is harness-only, and has THREE distinct failure modes** (per
`docs/post-content-processing-reference.md` §Cross-post inline CSS handling):
1. Block markup renders — visible garbage, easy.
2. Editor comments leak (`<!-- wp:paragraph -->`) — invisible in rendered output.
3. GB CSS extracted + reinjected — plausible-but-wrong styling.

Mechanism: cross-post `do_blocks()` can't reach `wp_head` (already fired), so GB inlines
`<style>` before each block; `wp_kses_post()` strips the tags but leaves the CSS text, which
renders as visible page content; `extract_and_queue_inline_styles()` pulls them and re-emits as
one `<style id="bws-dynamic-content-inline-css">` at `wp_footer` priority 5.
*The assertion is a literal string* — grep for that id, its position, and the expected selectors
inside. No mental model of correct styling needed. (User: "probably easier to prove with a
harness than eyeball test" — agreed, and the reason is that eyeballing requires knowing what
the styling should have looked like.)
*Content needs `src:ref` or a term-hop to reach cross-post rendering at all* — same-post content
never triggers the inlining. So content fixtures compose with the source-axis fixtures.
*Gap worth closing:* `bws_process_post_content_fallback()` (low-memory path,
`extract_css_from_block_comments()`) is a whole second implementation the doc calls "rarely
called directly" — only runs under production memory pressure where nobody's watching. A fixture
that forces it would exercise otherwise-untested code.

---

## Discovery-mode testing (concept — user raised 2026-07-16)

Tests written **before** a bug, to probe where the model's boundary actually is — not to
validate a known fix. Already present in the matrices, unlabeled: `phone-test-matrix.md` **R2b.2**
asserts a *double prefix* (`tel:+118005551212`) to document the hazard the strip-flag guards;
**R6.1** feeds `+1-987"><script>654-3210`. Nobody found those bugs first — someone asked "what if"
and wrote the row.

**Validation vs discovery — different lifecycles:**

| | Validation | Discovery |
|---|---|---|
| Written | after a bug | before one |
| Asserts | the fix holds | the boundary is where you think |
| Fails means | regression | **you were wrong about the model** |
| Lifecycle | permanent | **graduates** — finds nothing → becomes a validation row; finds something → becomes a bug + its regression test |

Because discovery rows graduate, they are a **queue that drains into the matrix**, not permanent
additions. Keep them in a separate `## Discovery` section per matrix; rows move up into the
numbered groups once they've earned it. Keeps "we believe this" apart from "we verified this" —
the same certainty separation CLAUDE.md enforces elsewhere.

**Why this needs the seed + the render seam.** Discovery is only worth doing if a what-if is
CHEAP. Today it costs a hand-built ACF field or page — which is why `M8.2` (a `</script>` label)
exists but `M2.6` (flex fields) got DEFERRED. Cost gates which questions get asked. With the seed
+ the render seam, a discovery row is a line in a manifest and one CLI call.

**Where discovery pays best: model-vs-code disagreements.** The (now-removed) `image-tags.php:47`
dead code wasn't found by a test — it was found by reading. A discovery row asking "does
`{{image}}` on a term archive read the term?" would have surfaced it, because the answer was *no*
and nobody asked. Unasked questions with known homes: tier 3b, degenerate term context (a
precedence tier never exercised); every `src` value on a tag that routes around the factory.

**"How do I propose a test to chase a bug?"** (user's open question). Three moments:
1. **Propose** — write the row (a tag string + a context + an expected output). Cheap; no design.
2. **Chase** — run it. `wp bws render-tag '<tag>' --url=<context-url>` for value/context questions;
   browse the panel for visual/editor questions.
3. **Resolve** — boundary held → row graduates to validation; broke → file a GH Issue, row becomes
   its regression test.
The render seam is what makes moment 2 cheap for context questions — WITHOUT it, "what does this
tag do on a term archive" needs a hand-built page, and discovery stays as expensive as today.

### Capturing a suspected bug (decided 2026-07-16)

The trigger this section was raised for: **while working, a bug is suspected** — capture a
synthetic reproduction of it into the testbed on the spot, cheaply, to confirm or kill the
suspicion. This is the Propose→Chase→Resolve loop above, applied reactively rather than as
up-front probing. Same lifecycle; the difference is only *when* the row is born.

**Capture unit = a Discovery row, NOT a seed object.** The suspected bug becomes one row in the
owning matrix's `## Discovery` section — a tag string + a context URL + an expected output —
runnable by `wp bws render-tag '<tag>' --url=<context-url>`. No new page/post/term is minted per
suspicion; persisting a fixture entity per repro would turn the seed into a junk drawer of
one-offs (rejected 2026-07-16). The row IS the fixture.

**Seed data only when the repro needs it.** If reproducing the suspicion requires field/post/term
data the seed doesn't already carry, the missing datum is appended to the seed manifest (so the
row survives `snapshot.sh --restore`) — the row still owns the assertion; the manifest owns the
data. Most suspicions reuse existing fixture keys and add nothing to the seed. When a suspicion
can't be reproduced against the fixture at all (needs a specific client's config), it stays an
injection probe, not a fixture row (same boundary as the injection decision under Decisions).

**Which matrix owns the row** = the tag family under suspicion (phone/email → their matrices;
un-matrixed tags → the manifest-as-matrix per the Scope note). Cross-family suspicion → the
matrix whose invariant it stresses.

*Gated on the render seam, like the rest of moment 2.* Until `wp bws render-tag` exists, reactive
capture for context/value questions costs a hand-built page — so this is cheap only once the seam
ships. Editor/visual suspicions are browse-the-panel and don't wait on the seam.

---

## Pinned 2026-07-17 (were Open)

**Target site: dedicated `testbed.test`** — NOT the client clones (real data, restored from
client snapshots). New site via wp-litespeed env's normal new-site path;
env-repo prerequisite before seed.php has a home.

**Licensed baseline via snapshot.** User logs in once on fresh testbed.test, installs +
licenses GP, GB Pro, ACF Pro (Pro available — flex/repeater schema unblocked), then
`snapshot.sh --save` = the baseline. Seeds apply on top; restore never re-does licensing.
**Snapshot saved 2026-07-17 as `full-build-seeded-20260717`** (build baseline + seed together).

**Snapshot vs blueprint — do not let them fork (rule, 2026-07-17).** The snapshot is
point-in-time; the blueprint is live. Coherence rule:
- **Blueprint is the source of truth for fixture DATA.** Growth (new tags/fields/pages) lands
  in `tools/fixtures/core-structures/` + `bin/seed.sh` reseed — NEVER by hand-editing the
  testbed and re-snapshotting. Hand-editing then snapshotting silently forks data away from the
  blueprint; a future agent must not do this.
- **Snapshot's durable value = the LICENSED WP baseline** (GP/GB Pro/ACF Pro install + license
  — hand-work, un-scripted, NOT reproducible from the repo). The seed data baked into it is a
  convenience cache, fully reproducible via `seed.sh`.
- **Growth loop:** edit blueprint → `seed.sh` (idempotent, layers onto the restored baseline)
  → `verify.php`. Re-snapshot ONLY when the baseline changes (plugin update, new license) or you
  want a fresh convenience checkpoint — not per fixture edit.
- Name bundles both halves; if the flow ever splits (restore-baseline-then-seed vs
  restore-fully-seeded), the build half is the irreplaceable one.

**Fixture keys: natural names, NO prefixes** (`fx_`/`tc_` rejected — invented, no convention).
Dedicated site + single-defining-owner rule make prefixes unnecessary. Cross-plugin sharing is
the architecture, not an add-on: the first blueprint holds shared CPTs/taxonomies/field groups;
a consumer blueprint composes on top and adds only its own. Collision rule: manifest lists
the keys a blueprint defines; a later blueprint must not redefine a listed key — reuse via
dependency. Enforcement = trivial manifest-compare script, later, not day one.

## Pinned 2026-07-16 (were Open)

**Location: `tools/fixtures/<blueprint-name>/` in the owning plugin repo.** House-pattern read
confirmed — beside `tools/test/`, next to the matrices that consume it. First blueprint:
`tools/fixtures/core-structures/`.

**Manifest = a PHP file returning an array (`manifest.php`), no parallel doc.** Resolves the
"own doc vs self-documenting seed" question by splitting seed from data: the manifest is pure
data (red-pennable, commentable, git-diffable — the things the doc-table wanted), the seed is
pure mechanism that reads it. One source; no doc to drift (CLAUDE.md single-source rule).
Playwright later consumes the same array (dump to JSON via a one-line wp-cli eval if needed).
- Top-level shape: `blueprint` (name), `version` (int, bumped on breaking key changes —
  what consumers pin), then entity sections: `posts`, `terms`, `term_meta`, `post_meta`,
  `options`, `users` as needed. Section entries keyed by **stable fixture slug**
  (`staff_direct_line`, `fixture-benefit-health`) — the slugs ARE the contract.
- **Manifest owns data; matrices own assertions.** No expectation notes in the manifest
  (already decided under Capturing-a-suspected-bug: the row owns the assertion, the
  manifest owns the data).

**Blueprint anatomy:** `manifest.php` (data contract) + `schema.php` (ACF groups /
`register_*_meta` / CPT+tax registrations — the mu-plugin includes this) + `seed.php`
(idempotent applier: reads manifest, upserts by fixture slug, wp-cli `eval-file`-able).
Schema in `schema.php` not the manifest because it's code (ACF arrays, register calls),
loaded at runtime by the mu-plugin; the manifest stays data-only.

**Runner lives in the env repo: `bin/seed.sh <site> <blueprint>...`.** ~15 lines — loops
`eval-file /plugins/<repo>/tools/fixtures/<blueprint>/seed.php` per blueprint, in list order.
Engine stays plugin-blind: it executes paths, interprets nothing. Applier logic ships WITH
each blueprint for now; extract a shared applier only when the second blueprint makes the
duplication real (same second-consumer rule as the `bws-fixtures` package).
- Mu-plugin installation: `seed.php`'s first job is copying/linking a loader stub into
  `mu-plugins/` that includes the blueprint's `schema.php` off the mount — so schema
  survives `snapshot.sh --restore` and stays git-editable in this repo.

## Open

**GB block generation — PINNED feasible for matrix pages** *(re-pinned 2026-07-17; was
"far from trivial" + snapshot escape hatch — RETIRED for plain matrix rows).* User pulled real
code-editor markup from the hand-built matrix page (`tools/debug/matrix-page-blocks.html`,
committed as reference corpus). Analysis: only ~4 regular shapes cover it —
1. section wrapper (`generateblocks/element` div + `wp:heading`),
2. text row (`generateblocks/text` p, tag string in body — ~90% of rows),
3. media row (tag string duplicated in comment-JSON `htmlAttributes.src` AND rendered
   `<img src>` — the two copies must match; per-block `css` string keyed to `uniqueId`),
4. query/looper nest (query → looper → loop-item → text; fixed skeleton, only query args +
   inner tag vary).
`uniqueId` = 8 hex chars, any unique value. So: small deterministic PHP builder in the
blueprint (e.g. `tools/fixtures/core-structures/blocks.php`: `bws_gb_text_block($tag)`,
`bws_gb_media_block($tag)`, `bws_gb_section($title, $rows)`), page content assembled from
matrix rows by `seed.php`. NOT an agent skill — no LLM needed. The corpus file also carries
`[SUB …]` placeholders = same substitution seam manifest slugs fill; matrix + generator align.
*Residual open (narrowed):* generation complexity only bites for **complex styling/structural
surfaces** (real layouts, styled compositions, editor-eyeball pages beyond flat matrix rows).
For THOSE the old escape hatch stands: hand-build once in the editor, `snapshot.sh --save`.
Corpus file is diff baseline until generator exists, then regenerate-or-delete; shape notes
migrate to PHPDoc on the builder functions at ship.
**Closed in practice 2026-09-15 (user):** generation has been non-problematic since it shipped —
the residual never bit, and the escape hatch was never needed.

**A second plugin's fixtures** — same plugin-owns-its-fixtures pattern (now the default per
the rescoped Ownership decision); when one lands, it's the second blueprint and the first real
test of the composing runner. Different repo, user's call on timing.
**Answered 2026-09-15:** the pattern was adopted broadly rather than by one plugin — five sibling
repos now carry their own `tools/fixtures/<blueprint>/` beside this one. Plugin-owns-its-fixtures
held. What it did NOT settle is the composition question below, which is the live half.

**Blueprint composition mechanics — mostly pinned** (runner + list-order composition + manifest
`version` field, see Pinned section). Remains open: how a consumer *declares* its blueprint
dependencies + pinned versions (a file in the consumer repo? args to seed.sh?), and whether
seed order ever matters beyond list order. Decide no later than the second blueprint.
**Still open 2026-09-15, and the deadline passed unobserved.** Five sibling repos have blueprints;
whether any of them composes another, and by what declaration, is recorded nowhere. This is the one
question the plan leaves genuinely live, and it is tracked from here as **FW-133**.

---

## Render seam — feasibility PROVEN (research + live spike 2026-07-16)

A wp-cli command that renders a tag string **as if on a given URL**, with ambient context REAL,
not faked. This is the keystone: it makes discovery-mode testing (below) cheap, supersedes
`tools/debug/bws-ctx-probe.php`, and is the only way to deliberately drive content's low-memory
fallback path. **Now looks like it comes BEFORE the seed**, not after — it's what makes the seed
testable.

**`wp --url` alone is a TRAP.** `WP_CLI::set_url_params()` sets six `$_SERVER` keys
(`REQUEST_URI` etc.) and stops — it NEVER runs the main query. So `is_tax()` is false and
`get_queried_object()` is null under bare `--url`. A seam built on `--url` alone would silently
test nothing ambient while looking like it works — same failure family as the env README's
Windows-curl / `vhDomain *` traps (passes while testing nothing).

**The fix is one line, with official precedent.** Call `wp()` after bootstrap — it runs
`parse_request()` (reads the `$_SERVER['REQUEST_URI']` that `--url` set) → `query_posts()` →
`register_globals()`, giving a genuinely real `$wp_query`/`is_tax()`/`get_queried_object()`.
wp-cli's own `profile-command` does exactly this (`Profiler.php:572`). No hack we invented.

**The plugin's WP-dependent ambient surface is tiny.** `bws_capture_ambient_signals()`
([traversal-pipeline.php:356-389](../../includes/helpers/traversal-pipeline.php#L356-L389))
reads exactly four WP functions: `is_tax`/`is_category`/`is_tag`/`get_queried_object`. `wp()`
covers all of them. **Loop rows are NOT from WP's `in_the_loop()`** — they come off GB's block
context (`$instance->context['generateblocks/loopItem']`, `field-helpers.php:182-225`). So a
query loop is a `context` array you populate, NOT a WP loop you run → no `template-loader.php`,
no theme output, no global-scope hack. And signals are already injectable for testability:
`bws_resolve_base_source($options, $instance, $signals)` takes them as a 3rd arg (:244-247).

**Render call — GB's own fake-instance shape.** `GenerateBlocks_Register_Dynamic_Tag::replace_tags(
$tag_string, [], $instance )` with `$instance = new stdClass(); $instance->context = [...]`. That
is the exact shape GB ships in its editor REST route (`class-dynamic-tags.php:513-517`).
Sufficient because this repo touches `$instance` **only** through `->context` (grep-confirmed; no
`get_context()`, no attribute reads). **Leave `bwsEditorPreview` unset** or you get preview text,
not real output.

**Shape (~60 lines):**
```php
wp();                               // real query parsed from --url
$instance = new stdClass();
$instance->context = [];            // or populate loopItem/queryType for a synthetic loop row
echo GenerateBlocks_Register_Dynamic_Tag::replace_tags( $tag, [], $instance );
```
```bash
wp bws render-tag '{{image src:current}}' --url=https://fixture.test/fixture_tax/alpha/
#  ^ real is_tax(), real queried term — the exact question that would have caught the
#    (now-removed) image-tags.php:47 dead code
```

**SPIKE RAN AND PASSED (2026-07-16, on a client clone).** Script kept at
`tools/debug/spike-render-seam.php`; run via
`bin/wp.sh <site> eval-file /plugins/bws-gb-dynamic-tags-extensions/tools/debug/spike-render-seam.php --url=https://<site>.test/benefit-type/health/`.
All seven assertions passed:
- Trap confirmed empirically: pre-`wp()`, `is_tax()` false + queried object null under bare `--url`.
- Post-`wp()`: real `is_tax()`, real `WP_Term` (benefit-type:health), `bws_capture_ambient_signals()`
  → `queried_kind:term`, `bws_resolve_base_source()` → `{kind:term, id:28}`.
- Full end-to-end: `replace_tags('{{text key:spike_probe_key}}', [], $fakeInstance)` returned the
  seeded term-meta value off the AMBIENT term. Fake-instance shape (`stdClass` + `->context`) works.
- `send_headers()`/`handle_404()` at CLI: no observed side effects (secondary unknown resolved
  as harmless in practice).
- Gotcha found: `bws_capture_ambient_signals( $instance )` REQUIRES the instance arg (fatals bare).
- Env notes: wpcli container sees `home` = literal `DOMAIN_PLACEHOLDER`; harmless — `wp()` parses
  the request path. `bin/wp.sh <site> eval-file <container-path> --url=<url>` is the whole invocation;
  `PLUGINS_ROOT` mount means the script runs from this repo directly.

**Remaining unknown (NOT spiked, not needed for the seam):** whether real query-LOOP rendering
(vs an explicitly-specified loop row via the `context` array) needs GB's `render_block` pipeline
+ `template-loader.php`. Deferred — the seam's contract is "URL context + explicit loop row,"
which the spike proves sufficient.

---

## Scope — read this before estimating

**Scope GREW across this session; it did not shrink.** (Claude claimed "smaller deliverable"
at one point and was wrong — there was never a baseline to be smaller than.) Turn by turn:
one seed script → + browsable/cert/domains → + realistic ACF groups + review page → + explicit
sweep + ambient contexts → + 5 precedence tiers + 2 regression pins → + precedence-competition
fixtures → + everything the two matrices require (~20 phone values, 2 flex fields with layouts,
3 label collisions, 4 `register_*_meta` calls, a `</script>` label, options page, term
relationship field).

What improved is **definition**, not volume. At turn one "fixture seed" was a hand-wave; the
contents are now enumerable because the matrices enumerate them.

**Two candidate deliverables, different sizes:**

| Deliverable | Contains | Cost sits in |
|---|---|---|
| **A — seed the existing matrices' state** *(chosen first)* | phone + field-selector fixtures only. ~30 field values, flex fields, collisions, registered meta. `[SUB]` → real keys. | the seed |
| **B — seed the whole tag matrix** *(accrete)* | A + every un-matrixed tag, which means first writing what correct IS for each, from `tag-reference.md` | the **manifest** — ~10 tags of new spec work |

A unblocks rows that currently can't be run, and that setup cost recurs every rebuild.
B is the real goal but is mostly spec work; let it accrete tag-by-tag as tags are touched.

**A ships as the first blueprint** (per the composing-seed decision above): its manifest is
the blueprint contract; the runner/blueprint split is in from the start even with one blueprint.

**Rough shape of A:** mu-plugin schema (CPT, taxonomy, ~6 ACF groups incl. 2 flex + 3 repeaters
for collisions, options page, 4 `register_*_meta`) — few hundred lines, mostly ACF array config.
Seed script (~25 posts/terms, phone value set, email trio, nested wp_options, logo, 4+ ambient
context pages) — comparable, more tedious. Manifest. Matrix edits.

---

## Side finding — RESOLVED 2026-07-15, no bug (kept: the reachability method generalizes)

`bws_override_media_ids_for_post_context()` (returned `get_the_ID()` for a tag-name list
including `'image'`) was **dead code, not an ambient-leak violation**. Removed in `00750ee` (1.14.1).

Why it couldn't fire, and the part worth reusing: GB applies `generateblocks_dynamic_tag_id`
**only** in `GenerateBlocks_Dynamic_Tags::get_id()` — called from GB's own built-in callbacks
and from `with_link()`, which early-returns unless `$options['link']` is set. We never set
`link` (we wrap via our own `linkTo`/`linkKey`, precisely because GB's resolves to the current
post, not our `src`). Independently, 8 of the 9 listed names stopped registering in 1.14.0.

**Method note for future traces:** a direct-call grep (`does our code call get_id()?`) returns
zero and is *misleading* — reachability runs through GB's call graph, since we hand control to
`::output()` and GB calls `get_id()` from inside it. Trace what a hook's apply-site is reachable
*from*, not who calls it. Reachability argument now recorded on the
[`gb-constraints.md`](../../docs/gb-constraints.md) filter-hooks row.
