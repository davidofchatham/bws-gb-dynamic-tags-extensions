# Limits bound usable results, then usable sources — the determinism reversal

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> the gate's own PHPDoc (`bws_source_gate()`, `includes/helpers/traversal-pipeline.php`) owns the
> deciding rule; [`CONTEXT.md` I19](../../CONTEXT.md) owns the invariant; [`docs/adr/0007-a-limit-counts-usable-sources.md`](../adr/0007-a-limit-counts-usable-sources.md)
> is the accepted decision; [`docs/tag-reference.md` §List mode](../tag-reference.md) owns the schema.
> Check THOSE first.

**RETIRED whole, 2026-08-21, when 1.18.0 built every phase of it.** Committed unedited rather than
split: what shipped is everything the file says except the two threads extracted to the tracker
(FW-88, the dormant populated opt-in; FW-89, the visibility filter hook that ships on consumer
request). The file therefore SUPERSEDES ITSELF IN PLACE in several rows — the §SETTLED index marks
each one, and the 2026-08-21 reversal at §S47 onwards reverses the axis the earlier half argues for.
That is the record working: a reader who needs to know why field population is no part of source
selection needs the argument that put it there as much as the one that took it out.

**Read the §SETTLED index first.** Roughly a third of its rows carry a SUPERSEDED or AMENDED marker,
and the sections they point at were not rewritten when they did.

**Note on paths.** Citations here name `.scratch/plans/` and `SPEC.md` because that is where things
lived while this was written. They are not corrected; see §Cross-link rules.

Provenance: grill session 2026-08-19, opened on [#118](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/118)
("the Limit results control is a dead no-op on content/permalink/image"). The premise was falsified
twice during the session and the work is now considerably larger than the issue it started from.
NOTHING BELOW IS BUILT. No code, doc or issue has been changed on the strength of this file.

**2026-08-21 UPDATE:** slice A HAD been built on `feat/first-usable` (nothing released) when a
determinism defect reopened the core rule. The reversal is recorded at §S47–§S51 (end of file);
the header sentence above records the 2026-08-19 state and stays.

**What changed shape.** #118 reads as an editor-surface cleanup. It is actually the visible corner of
a semantic defect: **a limit currently bounds candidates examined, not results produced.** That is
wrong at three sites, one of which is a behaviour regression shipped in 1.17.0.

---

## §SETTLED

| # | Decision | Section |
|---|---|---|
| S1 | ~~A limit bounds USABLE OUTPUTS; "usable" is the strongest test computable at that step~~ **SUPERSEDED 2026-08-21** — a limit bounds usable SOURCES (resolvable × exists × visible), one criterion at every position | §S47 / §S48 |
| S2 | ~~Intermediate step: usable = valid entity (exists, not trashed). Terminal step / tag-level: usable = non-empty read~~ **SUPERSEDED 2026-08-21** — no terminal non-empty arm; one rule per position | §S48 |
| S3 | The residual (a valid intermediate entity whose subtree is empty still spends budget) is DOCUMENTED, not fixed — lookahead rejected | §The residual |
| S4 | The capability is keyed on the tag's DISPOSITION toward a plural source list, NOT on list mode | §The capability |
| S5 | Not keyed on `supports_list` / §List mode's ❌ column — `{{table}}` breaks that proxy, and "list" is acquiring a third sense | §The capability |
| S6 | Flag lives on the template record beside `supports_try` / `is_image` / `try_list_options`; unprefixed | §The capability |
| S7 | Working name `collapses_to_first` — ACCEPTED PROVISIONALLY, and now inaccurate (see OPEN O1) | §The capability |
| S8 | The control is SUPPRESSED on collapsing tags, not reworded | §The control |
| S9 | Suppression must be an explicit conditional in the control, not an omitted vocabulary | §The control |
| S10 | Stored `limit(N)` is untouched by suppression — the grammar owns serialization | §The control |
| S11 | The field configuration note's consequence clause is FALSE on collapsing tags and gets fixed | §The field note |
| S12 | The collapse fact does NOT go in the field note — wrong axis, and two silent holes | §The advisory |
| S13 | A group-end advisory, conditional on a fanning step, carries it — and covers `terms` for free | §The advisory |
| S14 | Work splits: semantics + suppression first, advisory second | §Passes |
| S15 | Tag descriptions across all tags are SEPARATE work, not a rider here | §Owed elsewhere |
| S16 | ONE token, consumer-dependent meaning — `limit(N)` = N usable. No second `take(N)` token | §Q13 the wire's meaning |
| S17 | ~~Usable = the tag's per-source output is non-empty. `{{table}}` defines "empty row" in ITS OWN plan~~ **SUPERSEDED 2026-08-21** — usable is a property of SOURCES; population is no part of it | §S47 |
| S18 | The LAST step's limit stays PER-INPUT — **stands, satisfied differently 2026-08-21**: the engine applies every step limit per input; no grouping machinery | §S48 |
| S19 | "Displayable" is VIEWER-RELATIVE (current user can read), not a fixed status allowlist | §Q16 what displayable means |
| S20 | Gate at the ENGINE as an INJECTED PREDICATE on `bws_run_traversal` (default = the real gate), NOT the L2 read and NOT inline in the pure coercers | §S20 corrected — the gate is injected, not inlined |
| S21 | Posts get status + capability; terms and users get an existence check only | §Q16 what displayable means |
| S22 | LOOSENED 2026-08-20 — filterable BY CONSTRUCTION; the hook ships when a consumer needs it, not mandatorily in pass 1 | §S20 corrected |
| S23 | Capability gating everywhere incl. front end — a deliberate divergence from GB's REST-only posture | §Q17 caching, and what GB does |
| S24 | A collapsing tag ignores EVERY step limit, at EVERY position — STANDS; "first usable" re-read per §S47 (first usable SOURCE, output even if the read is empty) | §S24 + §S47 |
| S25 | ~~The terminal limit rides PROVENANCE on the resolved source; collectors group by it. NOT a predicate into the engine~~ **SUPERSEDED 2026-08-21** — terminal limit is an ordinary engine-applied resolution bound; the exists/visible gate IS an engine predicate (S20-corrected's scoping) | §S48 |
| S26 | ~~All three output changes ship as FIXES.~~ **AMENDED 2026-08-21** — term-route + visibility ship as CHANGED riding the notice; email/phone + slot-limit binding stay Fixed. No strict-mode toggle, no opt-in phase (unchanged) | §S26 + §S49 |
| S27 | The displayable filter is RESTRICT-ONLY, enforced structurally (AND-composed). A loosening override is a SEPARATE hook that does NOT ship until someone reports needing it | §S27 the filter contract |
| S28 | ~~Build sequence A → B → C. Release grouping deliberately NOT decided~~ **SUPERSEDED 2026-08-21** — A-rework + B together as 1.18.0; C retired | §S49 |
| S29 | The flag is `takes_first_usable`. `{{table}}` declares it explicitly false rather than taking the default | §The capability |
| S34 | Change 1 is SUBTRACTIVE IN MARKUP (count-based link gate) and rides the Upgrade Notice with change 3. Fixing the loss = FW-85, deferred | §S26 compatibility |
| S33 | The email/phone site-branch WIRE-STRING COMPARE is fixed IN SLICE A, with a regression pin — no GitHub issue | §S33 the two surviving wire compares |
| S31 | `try_` slots are CHAIN-FOLDED and group identically to a base tag — no exempt arm. Their `$slot_max` IS the constrained base tag's tag-level `limit` | §S31 try_ is the same shape |
| S32 | The selector is extracted FROM `try_`'s emit loop — extraction STANDS; its job NARROWS 2026-08-21 to the N-bounded source read + the dormant populated seam | §S32 + §S47 |
| S35 | For TERMS the intermediate test is VACUOUS — only `get_the_terms` makes a term source, so “exists” cannot fail. Usability is a property of (term × the read), never of the term | §S35 for terms the intermediate test is vacuous |
| S36 | Any TOUCH (migration OR editor mount) moves the tag-level `limit` onto steps and DELETES the key — three survival cases aside. The two spellings are one tag at two times | §S36 any touch moves the tag-level limit onto steps |
| S37 | The multi-fan flat→chain conversion is INFORMATION-LOSING (global slice vs per-input step limit). `1` on non-last fanning steps is the only spelling that keeps the output COUNT right, and is exact on the default case | §S37 the multi-fan conversion is information-losing |
| S38 | `tag-reference.md` says BOTH things (:264 vs :331). Not doc-vs-itself on the rule — :264 states the INTERMEDIATE criterion in the TERMINAL position, and is stale in one clause. Ordinary scope drift; the doc wins. §Why this is a restoration keeps its LABEL leg only | §S38 the owner doc says both things |
| S39 | Resolved source stays OUTCOME-DEFINED (ADR 0002 untouched) — stands. ~~Three levels~~ **FOUR levels 2026-08-21**: **resolvable** → **exists** → **visible** → non-empty read. `usable` stays MODEL vocabulary, never user-facing (unchanged) | §S50 |
| S40 | Homes: `resolvable`/`visible` → `CONTEXT.md` §Language; `usable` → `tag-reference.md` §List mode; plus **I19** and **ADR 0007** (companion to 0005 — 0005 owns WHERE a limit is stated, 0007 WHAT IT COUNTS) | §S39/S40 the vocabulary |
| S41 | A stated limit applies to the STEP IT IS STATED ON, not to the chain. `try_`'s floating "last step that pins one" is REJECTED. A bound's observable meaning changing when a step is appended is correct, not a defect | §S41/S42 a limit applies to its own step |
| S42 | `try_` is right on ONE axis of four — collect-then-slice ORDER. Floating-bound fix stands; ~~its cross-entity `break` waits for C~~ **the C-half DISSOLVED 2026-08-21** — `$slot_max` is the slot's tag-level (GLOBAL) analogue, current behaviour correct | §S41/S42 + §S48 |
| S43 | The TERMINAL STEP limit and the TAG-LEVEL `limit` were one row in §S25 and are two: the step limit is PER-INPUT, the tag-level one is GLOBAL — and global there is a preserved HOLDOVER, not a design position. Nothing new inherits it | §S25 where each limit is applied |
| S44 | ~~A collapsing tag's POST branch takes the first SOURCE where the term branch takes the first USABLE one — A's second defect~~ **POLARITY FLIPPED 2026-08-21** — the post branch was CORRECT; the term branch's first-non-empty search is the defect (and the subtractive change) | §S47 |
| S45 | ~~Release grouping: **A alone; B and C together**~~ **SUPERSEDED 2026-08-21** — A-rework + B ship together as 1.18.0; C retired; one Upgrade Notice, two consequences | §S49 |
| S46 | ~~Model work ships SPLIT~~ **SUPERSEDED 2026-08-21** — ADR, CONTEXT vocabulary and the `tag-reference.md` reconcile all land in 1.18.0; no on-purpose disagreement window | §S49 / §S50 |
| S30 | The TAG-LEVEL key is in scope — control-retired (#62), not deprecated (ADR 0005: no deprecation path for the read in 1.x). Covers the explicit `limit:N` AND the flat-spelling default of `1` | §What is broken 1b |
| S47 | 2026-08-21 — usable is a property of SOURCES (resolvable × exists × visible); the populated arm removed everywhere; collapsing tags read the FIRST usable source even if the read is empty; populated-search = dormant seam + deferred opt-in (FW row); try_ within-slot follows base, across-slot first-populated stands | §S47 |
| S48 | 2026-08-21 — ONE gate at EVERY step: exists (and visible when shipped) as the injected engine predicate, before each hop's limit slice; invisible entities cannot be stepping stones; slice C RETIRED (dissolved by redefinition) | §S48 |
| S49 | 2026-08-21 — release regrouping: A-rework + B ship together as 1.18.0; one Upgrade Notice carries term-route subtraction + unpublished-content stop; 1.17.1 folds into 1.18.0 (never deployed); version stays 1.18.0 | §S49 |
| S50 | 2026-08-21 — FOUR levels: resolvable → exists → visible → non-empty; "usable" keeps its name, redefined; ADR 0007 revised IN PLACE and RENAMED to "a limit counts usable sources", citations repointed same edit | §S50 |
| S51 | 2026-08-21 — test surface: §F15 rewritten (F15.4 inverts, F15.7 becomes positive pin), exists/visible rows + draft/private fixtures, harnesses re-cased per section | §S51 |
| S52 | 2026-08-21 — INTEGRATION DONE: blueprint v13 seeds the gate corpus (`matrix-gate` + draft/private/published staff + a seed-time-deleted id in plain meta); §F15/§F16/§F17 and the ten-row byte-identity spot-run all MEASURED on the testbed; `verify.php` renders both viewer arms | §S52 |
| S53 | 2026-08-21 — per-step limit LABELS shipped in the same release: the noun is authored on the step record (`steps[<slug>].limitLabel`), never derived from `produces`; generic label is the fallback | §S53 |

## §OPEN

| # | Question | Why it is open |
|---|---|---|
| ~~O1~~ | ~~What is the flag called?~~ | **CLOSED by S29** — `takes_first_usable`. `single_result` stays rejected: it is a defined output-shape term describing an INSTANCE of output, not a template capability, and reusing it would make one word mean two things at two scopes |
| ~~O2~~ | ~~What is "valid"?~~ | **CLOSED by S19** — viewer-relative, not a status allowlist |
| ~~O3~~ | ~~Where does the gate land?~~ | **CLOSED by S20** — source coercion, forced by S2 |
| ~~O4~~ | ~~Terms and users?~~ | **CLOSED by S21** — existence only, neither carries a publication status |
| ~~O5~~ | ~~Does the terminal-step rule need the ADR 0005 compatibility care?~~ | **CLOSED 2026-08-20 — YES, and for a reason the plan had not found.** Not the compatibility principle but the count-based LINK GATE, which ADR 0005 names in the same bullet. See §S26 + S34 |
| ~~O6~~ | ~~Does `{{table}}` declare the flag explicitly?~~ | **CLOSED by S29** — explicitly false, with a comment. `{{table}}` is precisely the tag a list-mode-keyed proxy would have caught by accident (S5), so leaving it silent would repeat that failure shape inside the new mechanism |
| ~~O11~~ | ~~Release grouping~~ | **CLOSED 2026-08-20 by S45** — A alone, B+C together. The deciding argument was not A's cost but S34: changes 1 and 3 share one Upgrade Notice, and splitting B from C means two notices with subtractive content in consecutive releases |
| ~~O7~~ | ~~How is the per-input terminal limit implemented?~~ | **CLOSED 2026-08-20.** The predicate-vs-provenance half was already answered by S25; what remained was WHERE the widening lands, and the code answers it: provenance is destroyed in `bws_base_source_ids_of_kind()`, one shared function above the fold. See §S32. (§Q18 never existed — the S16–S23 anchors dangle, see §Dangling anchors) |
| ~~O8~~ | ~~What is the displayable filter's contract?~~ | **CLOSED 2026-08-20.** `(bool $ok, array $source)` — grounded in the only known consumer, whose own predicate takes a post id and derives everything else. RESTRICT-only per S27. See §S20 corrected |
| ~~O12~~ | ~~Does the dedupe question ride this plan?~~ | Nothing dedupes the fan. Shipped behaviour, but S1 promotes a duplicate from spending a CANDIDATE slot to spending a RESULT slot. **CLOSED 2026-08-20 — OUT OF SCOPE, tracker row.** Dedupe ACROSS inputs destroys the provenance grouping S18/S25 need; dedupe WITHIN one input cannot reach the motivating case. See §O12 |
| ~~O9~~ | ~~Caching guidance~~ | **CLOSED 2026-08-20** — `tag-reference.md` beside the displayable gate, and nowhere else. It is guidance for someone debugging a cache, not a warning for someone taking an upgrade, and the Upgrade Notice has no room (S34 gave it two consequences already). If it needs a second home, README's honest-limits register fits before the notice does |
| ~~O10~~ | ~~Which slice ships first~~ | **CLOSED 2026-08-20 — A, on firmer ground than when this row was written.** A now fixes TWO live defects, and the second (S44, the post-side collapse) makes an `{{image}}` render on sites where it currently shows nothing. Still additive, still no Upgrade Notice, still delivers #118 |

---

## Dangling anchors

**S16, S17 and S18 have NO authoritative section.** Their §SETTLED rows point at §Q13 / §Q14 / §Q15,
and S19–S23 point at §Q16 / §Q17. No §Q section exists in this file. S19–S23's substance survives
inside §S27, §S26 and §What GB does; S16–S18's exists **only as the index row**.

That inverts what the index is for. `CLAUDE.md` §Long-lived plan files: *"The index is pointers, never
content; the sections stay authoritative."* Here three settled decisions are content in the index and
nothing else — exactly the failure the practice is supposed to prevent.

Not fixed by inventing sections after the fact: the rows say what was decided, and re-deriving the
reasoning would be writing history rather than recording it. Flagged so the next reader knows the row
IS the record for those three, and does not go looking.

## The rule

> A step's limit bounds its **usable** outputs, where "usable" is the strongest test computable at
> that step.
>
> - **Intermediate step** — usable = a valid entity (exists, not trashed).
> - **Terminal fanning step, and the tag-level `limit`** — usable = produces a non-empty read.

**These are two different QUANTITIES, not one quantity measured at two precisions** (reframed
2026-08-20, S41). An intermediate limit bounds how far a fan SPREADS — a resolution bound. A terminal
limit bounds how many results RENDER — an output bound. §S25 has the table, and the shipped `try_`
seam states the same split in one line: per-step limits "ride the emitted wire and bound the ENGINE's
hops", while the last one is "the slot's ITEM bound, which the container slices by"
(`slot-fold.php:160`).

The epistemic framing this paragraph used to carry — *the test strengthens because more is known* —
is WITHDRAWN. It reads as a rule of convenience, and it invites the question of why the terminal
position deserves an exception. It deserves no exception; it bounds a different thing.

**A LIMIT APPLIES TO THE STEP IT IS STATED ON, NOT TO THE CHAIN** (user, 2026-08-20; ADR 0005's own
sentence). That settles what happens when a step is appended after a bounded one: the number stays
where the author put it and goes on bounding that step, which now spreads rather than renders. The
alternative — letting the bound FLOAT to whichever step pinned one last, which is what `try_` does
today — lets a number stated on `terms` bound the output of `refs`. See S41.

Stated the other way round, which is the clearer form:

> The bound counts only what will be output. Anything filtered out — invalid OR empty — never
> consumed budget in the first place.

**"Terminal fanning step" is just "the last step".** Every step type fans: `BWS_FOLD_STEP_TYPES` is
`refs`/`terms`/`entries` and `BWS_FOLD_FANNING_SLUGS` is the same three, called "The FANNING family
... in full" (`slot-fold-compile.php:65`, `slot-fold.php:101`). The qualifier can be dropped.

This is one rule, not a special case per tag. Everything below falls out of it.

### Why this is a restoration, not a redesign (user, 2026-08-20)

1. **Both labels always said "result".** The retired tag-level control was *Result Limit*
   (`tag-reference.md:678`); the step control is *Limit results*, helped by "Maximum number of
   results". The control has promised output count on both spellings since the beginning — the CODE
   drifted from the LABEL. Consequence: this is a CHANGELOG **Fixed**, not a **Changed**, and #118's
   "the help text actively implies the control governs output count" was right that the help is not
   the thing in error.
2. **Pagination corollary.** A query paginated at 3 over 10 results, where the 2nd is not displayable
   (draft/trashed/private), should show 3 items on page 1 and 3 pages — not 2 items. WP behaves this
   way because the non-displayable post was never in the population. The bound applies to a
   population already filtered to what can be output, which is also an argument for resolving O3
   toward the SOURCE-COERCION gate rather than the L2 read.

   **SCOPE (2026-08-20): this corollary reaches the VISIBLE gate only, not the terminal read test.**
   WP filters non-displayable posts out of the population; it does NOT skip a post whose ACF field is
   blank. So it argues for S19–S21 and says nothing about counting non-empty reads. The plan leaned
   on it for both. The terminal test stands on the two-quantities argument in §The rule instead.

## What is broken

**1. Tag-level `limit` slices before it skips.** `bws_collect_value_list()` (`field-helpers.php`)
does `foreach ( array_slice( $items, 0, $limit ?: null ) ...)` and only then `if ( '' === $value )
{ continue; }`. So `limit:3` over `[empty, empty, value, value]` renders ONE value.

**This is doc/code drift, and the doc is right — but the doc says BOTH things, 67 lines apart**
(found 2026-08-20, S38). `tag-reference.md:331` states the order this plan wants ("empty items are
skipped, the list is sliced to `limit` and joined with `sep`"). `tag-reference.md:264` states the
shipped order deliberately, in the section preamble that owns the rule, and names its consequence:
*"`limit` bounds the resolved-source list, once, before the read … so `limit:3` can print two."*

:264 is the line that changes, and it is stale in exactly ONE clause — see S38. Per CLAUDE.md
§Documentation ownership the code still moves; what does NOT survive is citing :331 as though the doc
spoke with one voice.

**The predicate is already in the right function.** `bws_collect_value_list()` holds BOTH halves —
the slice and the `'' === $value` skip — three lines apart. So the collector half of A is an
ORDERING swap, not a rule to invent, and the collector already agrees with S2 about what usable
means. The three first-usable loops need no predicate change at all (`if ( '' !== $result )
{ return $result; }` is S1 already); their bug is entirely upstream, in the limit that never lets
them reach candidate 2.

**1b. The tag-level key is IN SCOPE — it is control-retired, not deprecated.** #62 removed the
control from every tag; the READ is permanent by explicit decision. ADR 0005: *"There is no
deprecation path for the tag-level read, in 1.x. Neither the explicit `limit:N` read nor the
flat-spelling default of `1` is scheduled for removal. The population is unenumerable by
construction."* `deprecated-tags-options.md:179` cites the same distinction as precedent — the
SPELLING is closed to new authoring, the READ is not deprecated.

Excluding it would leave `limit:3` and `limit[3]` counting differently on the same site, which is what
ADR 0004's readability commitment forbids; and unmigrated flat wire has NO step limits, so a rule that
skips the tag-level key reaches none of those sites.

**S36 makes that argument much stronger than “on the same site”.** The two spellings are the SAME TAG
at two points in time: any touch converts one into the other and deletes the key. So a rule that
covers only the step spelling means **touching a tag changes its output** — an author edits an
unrelated field, the block re-serializes, and the number starts counting something else. That is the
1.17.0 regression's exact shape (defect 3), and excluding the tag-level key would build a second one
on purpose.

**ADR 0005 names TWO reads, and the rule applies to both:** the explicit `limit:N` *and the
flat-spelling default of `1`*. So flat `{{text src:ref|ref:related}}` carrying no limit key at all
today renders the first related post's value even when empty; under S1 it renders the first NON-EMPTY
one. That is the list-mode sibling of the collapsing-tag regression, it is additive, and its
population is every unmigrated flat tag on every site — larger than the explicit-key case. Widens
slice C.

**2. Step limits bound candidates at traversal time.** `bws_run_traversal` applies
`array_slice( $produced, 0, $step_limit )` per input source, before any read. Correct for
intermediate steps under S2; wrong for the terminal one.

**3. 1.17.0 narrowed the term search on collapsing tags — a shipped regression.**

- Flat wire compiles to steps with no limit: `bws_fold_chain_from_options()` builds legacy steps as
  `'limit' => null` (`slot-fold-compile.php`). So a flat `{{image}} srcTermIn:category` searched ALL
  the post's categories for one holding an image.
- The conversion stamps one on: `bws_fold_chain_apply_legacy_limit()` does
  `$value = $explicit ? (int) $raw : 1;` with **no tag or template gate** (`slot-fold.php`).
- Result: `terms,category limit[1]` searches ONE term where the flat tag searched all.

> Post in three categories, only the third holds a term image. Pre-1.17.0 renders that image;
> post-conversion renders nothing.

The rule being carried forward was the flat `limit` default of 1, which governed LIST-MODE SLICING.
These three tags have no list mode, so it was never operative for them — applying it converted a
compatibility rule into a live narrowing.

**3b. The multi-fan case is NOT a sibling of this defect** — see §S37. The stamp writes `1` on
every non-last fanning step, which reads as an over-narrowing and is not one: it is the only
spelling that keeps the OUTPUT COUNT right under per-input step limits.

**5. THE POST BRANCH OF A COLLAPSING TAG TAKES THE FIRST SOURCE, NOT THE FIRST USABLE ONE**
(found 2026-08-20; the plan had never mentioned it — `grep` found no reference). The three tags are
asymmetric:

| Branch | What it does |
|---|---|
| term | `foreach ( bws_base_term_ids_from_source(…) ) { if ( '' !== $result ) { return $result; } }` — **first usable** |
| post | `bws_base_post_id_from_source()` → `bws_first_post_id_from_sources( $sources )` — **first source**, read once |

> `{{image}} src:refs,offices` where office 1 has no image renders NOTHING, even when office 2 has one.

Same tag, same shape, opposite behaviour by what the chain resolves to. **This is A's second defect and
the reason A needs the extraction** (§S28) — the selector at N=1 is what the post branch is missing.

Already sanctioned as a refactor: `CONTEXT.md` §Language says *"`ref` collapsing to one target
(`bws_extract_post_id`) contradicts the fanning-source model → fix the code, don't model around it."*
`bws_first_post_id_from_sources()` is that collapse's surviving instance.

**Under S1 defect 3 fixes itself.** `terms` is the terminal fanning step, so its limit bounds non-empty
reads: `limit[1]` becomes "the first category that has an image", which is first-usable. The stamp
stops being a narrowing and becomes correct. **No render-side special case and no strip migration are
needed** — both were scoped during the session and withdrawn when the general rule absorbed them.

**4. `try_`'s slot limit — RIGHT ON ONE AXIS OF FOUR, and the one it is right on is the precedent
for S1** (narrowed 2026-08-20 after E1–E4; the heading here said "NOT BROKEN" and that was wrong on
three axes — §Corrections owed 9). The surviving claim is narrow and exact: **`try_` collects before
it slices, and the base does not.**

```php
foreach ( $ids as $entity_id ) {
    $rendered = bws_try_normalize_items( $render_fn( $entity_id, $slot_opts, $inst ) );
    foreach ( $rendered as $it ) { $items[] = $it; ... }
    if ( $slot_max && count( $items ) >= $slot_max ) break;   // stop at N USABLE
}
$shown = array_slice( $items, 0, $slot_max ?: null );
```

(`class-tag-template-registry.php`, the slot dispatch's shared emit.) `bws_try_normalize_items()`
drops empties BEFORE the count, so `$slot_max` bounds rendered non-empty items — never candidates
examined. That is S1, shipped, with a comment naming the choice: *"COLLECT-then-slice, not
slice-then-collect."*

Two consequences the plan was written without:

- **`bws_collect_value_list()` slices BEFORE the read and `try_` slices AFTER it.** Defect 1 above is
  the base half of a split the codebase already resolved correctly on the other side.
- **The `break` IS §S24's deferred short-circuit**, built, with its unlimited case handled
  (`$slot_max 0` never breaks early). A ships with a pattern to copy, not to invent — which is what
  S32 turns on.

## S36 — any TOUCH moves the tag-level limit onto steps and deletes the key

**User, 2026-08-20.** Migration (converter) and mount (editor) both do it, through one shared
function and its twin: `bws_fold_chain_apply_legacy_limit()` (`slot-fold.php:1160`) and
`fold.applyLegacyLimit()` (`slot-fold-migrate.js:366`). Both callers pass `consume = true` at depth 0
— `slot-fold-migrate.php:433`, `deprecated-tags.php:1378`, and the JS `delete out.limit`.

What a touch does:

| Tag-level `limit` | Chain fans? | Steps after | Key after |
|---|---|---|---|
| `3`, explicit | yes | all but last get `1`; last gets `3` | **deleted** |
| absent | yes | all but last get `1`; last gets `1` | nothing to delete |
| `0` / `-1`, explicit | yes | none written (unlimited) | **deleted** |
| any | no fanning step | unchanged | **survives** |
| any | author already stated a step limit | unchanged — the author's own limits win | **survives** |
| `> BWS_FOLD_MAX_SAFE_LIMIT` | yes | unchanged | **survives** — named in the source as “the one acknowledged hole in #62's promise” |

**Consequence for this plan: the two spellings are all but mutually exclusive, and the tag-level
population DRAINS.** A touched tag has step limits and no key; an untouched tag has the key and no
step limits. They coexist only in the three survival rows. And the drain is not a campaign anyone
runs — opening a block in the editor is enough — so the surviving tag-level population is exactly
“tags nobody has opened since 1.17.0”, shrinking on its own.

Three things follow:

1. **S30 stands, on a better argument.** See §What is broken 1b — the two spellings are one tag at two
   times, so a step-only rule makes a TOUCH change output.
2. **Slice C's population is smaller than §What is broken 1b claims, and shrinking.** “Every
   unmigrated flat tag on every site” is right today and less right every week. Argues for sizing C
   on the CORRECTNESS of the two spellings agreeing, not on how many sites carry the flat one.
3. **A touch is a behaviour-change event this plan must not ADD to.** The conversion already diverges
   in one accepted case (S37: explicit N>1 over a sparse first input — §The residual). Whatever A
   ships must not widen that: before-touch and after-touch reads of the same authored tag have to
   agree everywhere they agree today. That is the pin `fold-migration-test.php` should carry.

## S37 — the multi-fan heuristic is correct; its axis is owned elsewhere

**Cut to a pointer 2026-08-20 (Q2).** This section re-derived from code what three committed sources
already own, which is the failure §Long-lived plan files warns about, landing on docs rather than on
the plan (§Corrections owed 8).

The heuristic — **`1` on every unstated fanning step, `N` on the last** — is correct, and:

- **Axis:** `bws_fold_chain_apply_legacy_limit()`'s PHPDoc (`slot-fold.php`). It states the
  `∏ limitₙ` multiply rule and names both rejected alternatives.
- **Decision:** [ADR 0005](../../docs/adr/0005-limits-are-stated-where-the-source-is-stated.md), the
  rejected-options list — *"Migration puts `N` on the LAST fanning step and `1` on every earlier one
  (#59), because per-step limits are per-input and multiply."*
- **Known incompleteness:** `tag-reference.md` §List mode, the section titled **"Known
  migration-fidelity gap: two fanning steps, explicit `limit:N > 1`"** — the parent-major spill,
  with **zero live instances** confirmed across the surveyed sites
  (`docs/design-history/per-step-limit.md` §Site survey). No verification is owed; it was done.

**The one thing not stated at any of those three, and the reason this section survives at all:** the
intermediate `1` is what keeps **S18** safe on converted wire. S18 holds the last step's limit
per-input, which would multiply output on a multi-fan chain — except the `1` collapses the input count
to one, so per-input and global coincide. Delete the `1` and S18 breaks on every converted multi-fan
tag.

**Where it is irrelevant:** content / permalink / image. S24 retires every step limit on a collapsing
tag, so the stamp reaches nothing there — a stronger repair of defect 3 than S1's reinterpretation of
`limit[1]`.

## The residual

A valid intermediate entity whose subtree yields nothing still spends budget:
`refs,office limit[2];terms,category` where office 1 is a real office with no categories gives
categories from office 2 only, having spent one of two on an office that contributed nothing.

Closing it needs LOOKAHEAD — evaluating each candidate's downstream chain before bounding — which
inverts the forward pipeline for a case needing two fanning steps AND a sparse intermediate. Rejected
(S3). The author's words at that step ("at most 2 offices") are satisfied; it is the later step's
emptiness they did not anticipate, and the terminal rule already handles that.

## S35 — for TERMS the intermediate test is vacuous by construction

**Exactly one thing in the engine produces a `term` resolved source:**
`bws_pipeline_terms_to_sources()` (`traversal-pipeline.php`), fed only by
`get_the_terms( post, taxonomy )`. `refs` hardcodes `'kind' => 'post'` and never emits a term;
`terms` accepts `post` as its only input kind (`BWS_TRAVERSAL_STEP_INPUT_KINDS`).

So every term source in a chain came out of a live `WP_Term` **in the same request**. It cannot be
deleted, trashed, or in an unregistered taxonomy. With S21 (terms carry no publication status),
S2's intermediate test — *exists, not trashed* — **has no failing case for terms**.

**Recorded as VACUOUS, not as verified.** A review pass called the term handling correct. It is —
but because the test is unfalsifiable, not because anything checks and gets the right answer. The
contingency is one sentence long (*`refs` never emits a term*) and a future step resolving a STORED
term id — the obvious `term_refs` shape — ends it with nothing in the code noticing. S20's injected
predicate is where that check lands when it does; S21's existence-only rule is already right and
stays.

**There is no unusable term, full stop.** At the terminal step usability is a property of
**(term × the read this tag performs)**, never of the term. One post in categories A, B, C:

| Chain | Is A usable? | Because |
|---|---|---|
| `{{image}} src:current;terms,category` | no | A has no `term_photo` |
| `{{permalink}} src:current;terms,category` | **yes** | `get_term_link()` is non-empty for every live term |
| `{{content}} src:current;terms,category` | depends | reads the term DESCRIPTION |
| `{{content}} use:key key:blurb src:current;terms,category` | depends, differently | reads `blurb` |

Same term, same request, four different usable-sets. This is why the rule is stated per-STEP and
never per-KIND, and it is the whole answer to “what would an unusable term source be”: nothing, on
its own.

**Do not file the residual here.** In `src:current;terms,category;refs,sponsor` a category with no
`sponsor` is a valid entity with an EMPTY SUBTREE — §The residual, deliberately unfixed (S3). It
reads like an unusable term and is not one.

## S24 — collapsing tags ignore all limits

**Rule:** a collapsing tag applies NO step limit at any position. It takes all results and outputs the
first usable one.

**Why not "terminal step only".** A first pass concluded that only the TERMINAL step's limit is inert
on a collapsing tag, because an intermediate limit still bounds valid entities and can change output:
`{{image}} src:refs,office limit[1];terms,category` stops at office 1, so if office 1's categories
hold no image and office 2's do, the tag renders nothing. That reasoning is correct about the
MECHANISM and wrong about the OBJECTIVE (user, 2026-08-20):

> Only one result CAN be returned, so instead of assigning intermediate steps a limit of 1 and/or
> allowing the author to limit it, which may skip usable results, take ALL the results and output the
> first usable one.

A bound that can only ever subtract usable results from a tag that returns one result is not a
control, it is a trap. So the limit is not applied, and the control is not offered — on any step.

**Consequences:**

- S8's suppression is PER-TAG, not per-step. No position-dependent reveal, no flicker as steps are
  added or removed.
- No intermediate limit can exclude the only usable branch.
- Stamped `limit(N)` on a collapsing tag's wire is DEAD — see the note under §What is broken about
  whether migration should stop writing it and/or strip what it has written.
- Collapsing tags need no provenance and no grouping, so they drop out of O7 entirely.

**Implementation note — short-circuit, do not expand.** "Take all results" must not be read as *fully
expand the chain, then pick*: on `refs,office;terms,category` that is a term query per office before
a single image is read. The correct shape is a depth-first walk that stops at the first usable
result, so a wide fan costs one branch rather than the cartesian product. An OPTIMISATION, not a
correctness requirement — deferrable, but it should not be discovered as a performance surprise after
shipping.

## S27 — the filter contract

**Restrict-only, enforced by construction:** `$ok = $ok && (bool) apply_filters( … )`. A future caller
cannot loosen the gate even by misunderstanding it. Convention would not hold that line; AND-composition
does.

**Who actually loses content under restrict-only**, which is why S26 survives the loss of its hatch:

- Anonymous visitors seeing draft/private content — that is the leak, not a use case.
- Members seeing private content — UNAFFECTED. The gate is viewer-relative (S19), so a member who can
  read it still sees it.
- **Custom post statuses** — the one honest constituency. A site with a non-public custom status
  ("archived", "internal") that it deliberately surfaces through a relationship tag loses it, because
  `current_user_can( 'read_post' )` says no for anonymous visitors.

**The custom-status case probably wants an ADMIN PAGE OPTION rather than a filter** (user,
2026-08-20): a site-owner-facing allowlist of statuses to treat as displayable. It is discoverable by
the person who actually loses content — a filter is not — and it is bounded to statuses rather than
being a general capability bypass. House precedent exists: ADR 0001 is a site-option read allowlist,
and the settings page is already there (`class-settings-page.php`). Design constraint if it is built:
it declares statuses PUBLICLY DISPLAYABLE; it must not bypass `read_post` for private or
password-protected content generally.

Neither the loosening hook nor the admin option ships in pass 1. Name the custom-status case in the
Upgrade Notice, and build the hatch when someone hits it.

## S26 — compatibility

FOUR rendered-output changes land on live sites, with different risk:

| | Change | Direction (VALUES) | Direction (MARKUP) |
|---|---|---|---|
| 1 | Limits count usable results | ADDITIVE — a tag asking for 3 and showing 1 now shows 3 | **SUBTRACTIVE — the tag STOPS BEING A LINK** |
| 2 | Collapsing tags ignore limits (S24) | ADDITIVE — tags rendering empty start rendering content | none — output stays single-result, so the gate still passes |
| 3 | Displayable gate (S19-S21) | SUBTRACTIVE — draft, private and trashed content stops rendering | follows the values |
| 4a | A limit bounds only its OWN step — `try_`'s bound stops floating (S41), **slice A** | ADDITIVE — an appended step stops being bounded by a number written on an earlier one | none |
| 4b | A `try_` slot's bound applies PER INPUT, not across entities (S42), **slice C** | ADDITIVE — a multi-fanning slot renders more items | none |

**Rows 4a/4b were added 2026-08-20 (S42) and are `try_`-only.** Listed separately from row 1 because
they move independently — the engine fix in row 1 would produce neither. Split into a and b because
they ship in DIFFERENT SLICES (see S42): 4a needs no grouping and rides A, 4b needs S25's per-input
provenance and waits for C. Both are additive and neither touches markup, so neither carries an
Upgrade Notice obligation — they are here because this table is the plan's inventory of what MOVES,
not only of what needs announcing. Population is small and recent: folded slots shipped in 1.17.0
(#104), so hand-written multi-fanning `try_` wire is days old.

**The MARKUP column was added 2026-08-20 and it changes row 1's answer.** An earlier version of this
table had one Direction column, classified 1 and 2 as ADDITIVE, and concluded from that classification
that neither needed a notice. Row 1 is additive in VALUES and subtractive in MARKUP, and the
classification could not carry that.

The mechanism is the **count-based single-result link gate** at the end of `bws_collect_value_list()`:

```php
$count = count( $values );                       // AFTER empties are dropped
'link' => 1 === $count ? $values[0]['link'] : null,
```

Today `limit:3` over `[empty, empty, v, v]` slices to three, drops two empties, and reaches count 1 —
so it link-wraps. Under S1 it yields two usable values, count 2, and the wrap is gone. **The
population that loses its link is EXACTLY the population change 1 fixes** — a tag showing fewer
results than it asked for because some were empty. `try_` carries the same count-based gate
(`1 === count( $shown )`) but is already S1-correct, so nothing moves there.

**ADR 0005 already priced this loss, and rejected a whole option partly over it.** Its "Accept the
output change with an Upgrade Notice (rejected)" bullet: *"~110 authored instances … would begin
rendering extra results silently, with no author present. **The link gate is count-based, so those
tags would also stop being links.** A notice cannot substitute for serialization; it can only
accompany it."*

**One asymmetry lets change 1 ship anyway, and it must be stated rather than assumed.** ADR 0005
rejected the notice because a BETTER option existed there — era-selected defaults preserved behaviour
without touching stored wire. No such option exists here: the code does something the label never
promised, and no serialization trick turns a slice-then-drop into a drop-then-slice. So the notice is
**the best available remedy, not a substitute for a better one.** That distinction is the whole
licence, and citing ADR 0005 without it reads as citing a remedy that ADR found insufficient.

**All three ship as FIXES.** No strict-mode toggle (it would couple three unrelated changes to one
switch), no opt-in phase (shipping a disclosure gate default-off leaves exposed sites exposed until
someone reads release notes, and they are the least likely to).

Row 2 remains covered by ADR 0005's own precedent — 1.17.0's `limit:0` change also moved rendered
output and shipped plain, on the reasoning that "a clamp discarding a written value [is] not a
designed semantic". Row 3 is not covered by that reasoning: the old behaviour was not a discarded
value, it was content someone can see today. **Row 1 is covered for its values and NOT for its
markup**, which is why it moved.

**CHANGES 1 AND 3 BOTH RIDE THE UPGRADE NOTICE (S34, user 2026-08-20).** Row 2 does not.

**The Upgrade Notice is therefore LOAD-BEARING, not a courtesy** — `readme.txt`
`== Upgrade Notice ==`, 300 characters, led with an attention-grabber, naming the visible consequence
in author terms rather than the mechanism (memory `feedback_upgrade_notice_mechanism`). It now has TWO
consequences to name inside 300 characters — content disappearing (3) and links disappearing (1) —
which is a copy constraint worth knowing before the copy is written, not after.

**Fixing the loss instead of disclosing it is DEFERRED, not rejected — FW-85.** `CONTEXT.md` [I12]
already anticipates per-item wrapping (*"the per-value links remain available in `values` for future
per-item wrapping"*). It is a designed feature riding a bug fix if taken here, and it carries its own
open question (which value gets the link when `sep` has joined them into one string), so it is
tracked rather than folded in.

**The escape hatch does not exist, and that is now final.** This decision was taken assuming the S22
filter could restore prior behaviour. The user intends that filter to allow ADDITIONAL restriction
only, not loosening (2026-08-20), which removes the hatch. ~~See O8.~~ O8 is closed — the contract is
`(bool $ok, array $source)`, AND-composed, and S22 is loosened so the hook need not even ship in
pass 1 (§S20 corrected). Nothing there restores prior behaviour. The custom-status constituency's
answer stays the admin-page allowlist above, unbuilt until reported.

**[#77](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/77) is the disclosure
CHANNEL these three changes want, and it is not built.** It exists precisely for "a release changed
what a tag renders, and there is no migration to run" — two surfaces (editor preview, upgrade-scan
list) and two lifecycles (`standing`, `announcement`), deliberately kept off `option_migrations`
because the converter's report and its run must stay the same predicate. Two things follow:

- Its "Explicitly NOT callers" section names the **limit-0** change on the grounds that the migrator
  reapplies the bound, so nothing is left to detect. **Changes 1 and 2 here are the opposite case** —
  no wire changes at all, so there is nothing to detect either, but for the reverse reason: the tag's
  configuration was always correct and the CODE moved. Whether that is an `announcement` caller or
  another non-caller is genuinely undecided, and #77's own framing does not settle it.
- **Change 3 has no in-product surface without #77.** The Upgrade Notice is the only channel that
  ships today, it is Updates-page-only and 300 characters, and it fires BEFORE the upgrade — nobody
  who upgrades unattended ever sees it. That is the whole exposure of the subtractive change reaching
  authors through a notice they may never read.

**Do NOT merge #77 with S13's group-end advisory.** Different axes: #77 is release-scoped and
FROM-version-gated (a fresh install never sees it), S13's is a permanent statement about how this tag
reads its chain and fires forever. One retires, the other does not.

## S25 — where each limit is applied

Under S1/S2 the two limits are not the same kind of thing, and the split follows from that:

| | What it bounds | Scope | Applied |
|---|---|---|---|
| Intermediate step limit | how far a fan spreads — a RESOLUTION bound | per input | `bws_run_traversal`, as today |
| TERMINAL step limit | how many results render — an OUTPUT bound | **per input** (S18, confirmed 2026-08-20) | the collector, after reading |
| TAG-LEVEL `limit` | how many results render in total | **GLOBAL** | the collector, after reading |

**The last two rows were ONE row until 2026-08-20, and conflating them was a real error (S43).** A
limit stated on the terminal STEP is per-input, because it is stated on a step and a step limit is
per-input (#72, ADR 0005). The tag-level `limit` is stated on **no step at all**, so under the same
rule it bounds what it IS stated on — the tag's whole output. It is global.

**Global there is a preserved HOLDOVER, not a design position** (user, 2026-08-20). It is what the flat
era meant — S37's whole analysis is that the flat tag-level limit was a global slice over the
flattened walk, which is exactly why no per-step spelling reproduces it. Nothing NEW inherits it:
ADR 0005 already reserves "at most N overall" as *"a possible future **designed** feature rather than
this key."*

Each resolved source carries the input that produced it; collectors group by that and take N usable
per group. Collapsing tags need neither (S24).

**THERE ARE THREE CONSUMERS, NOT TWO — `try_` IS ONE (user, 2026-08-20).** An earlier reading of this
file had `try_` bounding a different quantity and needing no grouping. That is wrong, and the reason it
looked right is worth recording so it is not re-derived: the emit's own comment declines to route
through `bws_collect_value_list()`, but it declines over a SECOND multiplicity (one entity yielding
several finished strings), not over grouping.

A `try_` slot has the base tag's structure exactly. `$slot_opts['src']` is chain wire on EVERY slot
since #104, including one recovered from legacy flat keys; a folded slot may pin a per-step
`limit(N)` inside its own chain; and `$slot_max` — resolved through `bws_clamp_limit()` off the seam's
reported era — is that slot's analogue of a base tag's tag-level `limit`. The slot then runs
`bws_base_*_ids_from_source()`, i.e. the same whole-chain traversal. So S18 applies to `try_`
unchanged, and the arm table's existing `list` column is where per-arm applicability already lives.

`try_content` / `try_permalink` / `try_image` still drop out, by the same S24 route as their base tags.

**This is not a compromise preserving a fragile seam — it removes an existing one.** Today the engine
applies a policy about RESULTS while claiming only to resolve SOURCES. B gives away the bound that
never belonged to it and keeps the one that does. The comment at the skip site writes itself: *the
engine applies resolution bounds; output bounds belong to the reader.*

**Rejected: a usability predicate passed into the engine.** It would make the engine own the
resolution bound AND the terminal read AND the read-count policy — more concentrated than the status
quo, not less. Three concrete costs beyond the principle: consumers that never read
(`bws_wrapper_ref_steps()`, `{{call}}`) would pass a null predicate and force a branch; ACF's
formatting filters would fire during traversal; and `traversal-pipeline-test.php` becomes
higher-order, losing the purity that makes it the cheap harness.

**SCOPE OF THAT REJECTION — it is about the USABILITY predicate ONLY, not the DISPLAYABILITY gate
(2026-08-20).** The two are different questions and take opposite answers; §S20 corrected has the
argument. All three costs above were re-checked against the displayability gate and do not hold:
`bws_wrapper_ref_steps()` and `{{call}}` WANT that gate rather than passing a null (#120's exposure
reaches them), a capability check fires no ACF formatting filters, and an INJECTED predicate is the
thing that KEEPS the harness pure rather than the thing that costs it. Read this paragraph as scoped
to read-count policy, which is what it was written about.

**The one real point in its favour, recorded because it may return:** S24's short-circuit depth-first
optimisation is NATURAL in a read-driven engine and must be built separately under this decision. If
wide fans on collapsing tags turn out to be common in real sites, that calculus shifts.

**ADR 0002 note.** Its rationale is that a resolved source "carries what its read needs", and
provenance is what the LIMIT needs. The stretch is acknowledged: ADR 0002's actual decision was
against a uniform typed `{kind, id}` and for per-kind variability, and provenance is orthogonal to
both — "where this source came from" is part of what a resolved source is, not a foreign passenger.
Named rather than glossed.

## O12 — the fan does not dedupe, and S1 changes what a duplicate costs

Terms are only ever fanned INTO, from posts, never produced by `refs`/`entries` (S35). So the term
count at a step is (input posts) × (terms per post) — and nothing removes repeats:

- `bws_run_traversal` does `array_push( $next, ...$produced )`.
- `bws_base_source_ids_of_kind()` appends raw.
- No `array_unique` anywhere in `traversal-pipeline.php`, `base-shared.php` or `field-helpers.php`.

> `{{image}} src:current;refs,offices limit[2];terms,region`
> refs → `[Office1, Office2, Office3]`, sliced per input → `[Office1, Office2]`
> terms,region per office → Office1 `[North]`, Office2 `[North, South]`
> resolved: **`[North, North, South]`**

Terms shared across related posts is the ORDINARY case — regions, brands, categories — not an
exotic one.

| Consumer | Today | After S1 |
|---|---|---|
| First-usable (content / permalink / image) | repeat read, same answer — harmless | unchanged |
| List fold (text / title / datetime term arms) | North renders twice | North renders twice |
| Budget | the duplicate spends a CANDIDATE slot | the duplicate spends a **RESULT** slot |

**The last row is why this is open rather than pre-existing.** The duplicate ships today and S1 does
not create it. S1 changes the UNIT the author is counting: `limit:3` means “look at 3” today and
“show me 3” after, and spending one of three on a region already on screen is a different complaint
from spending one of three lookups. `{{text}} src:current;refs,offices;terms,region limit[1]` with
`limit:3` can put all three on one region.

**Open:** in scope, or explicitly out with a tracker row.

Against fixing it here: deduping is a behaviour change to RESOLUTION, and S25's
resolution-vs-output split says resolution is not this plan's axis.

For fixing it here: the first thing S1's new counting does on a common chain is count the same term
twice, and S1 is the change that makes an author notice.

**But the shapes do not line up.** Dedupe ACROSS inputs destroys the provenance grouping S18/S25
depend on, so the only safe form is dedupe WITHIN one input — which does not reach the example
above, where the repeat comes from two different offices. The case that motivates the fix is exactly
the case the safe form cannot touch. That probably settles it as out-of-scope-with-a-row; it needs a
decision, not more analysis.

## S20 corrected — the gate is INJECTED, not inlined

Decided 2026-08-20. S20 as first written said "gate at source coercion
(`bws_pipeline_*_to_sources()`)". That placement is withdrawn. The gate is an **optional predicate
parameter on `bws_run_traversal`, defaulting to the real capability check.**

**Why the original placement fails.** All three coercers are PURE — no WP symbols. And
`tools/test/traversal-pipeline-test.php` REQUIRES the real file, carrying only `WP_Post`/`WP_Term`
shims, on a stated premise:

> *No WordPress required. `bws_run_traversal` / `bws_run_step` are a pure fold … by injecting a stub
> `$reader` (SPEC §V9 — engine pure/deterministic).*

Putting `current_user_can()` inside those three functions forces the harness to shim a capability
call, i.e. to test a FAKE gate. That is the one thing the cheap harness exists not to do — and it is
the same cost §S25 cited when rejecting a predicate parameter, except worse: **a parameter can be
injected, a global cannot.** The plan was rejecting the testable shape and mandating the untestable
one on identical grounds.

| | Placement | Harness purity | Filter retrofit |
|---|---|---|---|
| a | Inline `current_user_can` in the three coercers (S20 as written) | **BROKEN** — needs a capability shim | High — thread a hook through three pure fns |
| **b** | **Predicate parameter on `bws_run_traversal`, default = the real gate** | **Preserved** — harness injects | **Near zero** — one line in the default |
| c | Inside `bws_pipeline_default_reader()`, the already-impure injected seam | Preserved | Near zero |

**(b) chosen.** (c) was tempting because the reader is already the impure boundary, but it returns RAW
field data — ACF shapes, `WP_Term[]`, repeater rows — before coercion. Filtering post status there
means re-deriving ids from the very shapes `bws_pipeline_ref_to_posts()` exists to normalise. Wrong
layer.

**S2 is still satisfied.** The gate runs inside the engine, per step, BEFORE the next step's limit
slices — which is what S2 forced and what a gate at the L2 read could not give. Only the placement
inside the engine moved, not the position in the pipeline.

### S22, loosened

The filter no longer has to ship in pass 1 (user, 2026-08-20). The reason it once had to is now gone,
and this is the load-bearing half of the change:

> **The "insert it now so it doesn't have to be inserted later" argument was a consequence of the
> placement, not an independent fact.** Under (a), retrofitting a hook means threading it through
> three pure functions and shimming it in the harness. Under (b), the default predicate is a single
> function and `apply_filters` is one line inside it.

So: **filterable BY CONSTRUCTION; the hook ships when a consumer needs it.** Restrict-only when it
does (S27, unchanged — `$ok = $ok && (bool) apply_filters( … )`).

### O8's answer — the contract

`(bool $ok, array $source)`. Grounded rather than guessed: the only known consumer is the sister
Portal System, whose predicate is

```php
VisibilityChecker::is_post_visible( int $post_id, ?string $portal_id, string $post_type ): bool
```

It derives `$portal_id` from ambient request context itself and `$post_type` from the id, and already
exposes its own `bws_portal_is_post_visible` hook — so composition is filter-to-filter and **the
minimum it needs from us is the post id.** A resolved source carries `kind` + `id`, which covers it
with nothing to spare.

That also CONFIRMS S21 rather than merely coexisting with it: `is_post_visible` is post-only, so a
terms/users existence check leaves the known consumer nothing it wanted and could not get.

**Not settled here:** whether the hook is per-source (fires once per candidate) or per-list (fires
once with the whole candidate list). Per-source is the simpler contract and matches the consumer's
signature; per-list would let a consumer batch its own queries. Decide when the hook is actually
built — which, post-loosening, is not pass 1.

## S31 — `try_` is the same shape, and is the superset

Established 2026-08-20, against the code.

**Grouping.** §S25 carries it: a `try_` slot is chain-folded, so it groups exactly like a base tag.
No exempt arm.

**The output bound.** `$slot_max` is the constrained base tag's tag-level `limit`, reached the same
way and defaulted the same way — by the SLOT's own spelling, reported by the seam because only the
seam still sees the era. S30 puts the tag-level key in scope; the same reasoning puts `$slot_max` in
scope, and `try_` is already compliant there (§What is broken 4).

**Where `try_` is a SUPERSET, and why it matters to S32.** Two multiplicities stack on a `try_` slot;
only one exists on a base list fold.

| Multiplicity | base list folds | `try_` slots |
|---|---|---|
| chain fans → many entities | yes | yes |
| one entity → many finished items | **no** — `bws_collect_value_list`'s `$render` is 1:1 by signature | **yes** — `bws_try_normalize_items( string\|array )` |

**The second one has NO LIVE PRODUCER today, and that was checked rather than assumed.** Surveying
every `try_core_fn` / `try_term_fn` / `try_site_fn`: text, content, title, permalink and image return
a string on all three arms. Email and phone coerce their post and term arms to 0-or-1
(`$raw = ( is_scalar( $raw ) && '' !== (string) $raw ) ? array( (string) $raw ) : array();`). Their
SITE arm returns `bws_*_finish_values( bws_resolve_field_values( … ) )`, which is one value per
resolved source and can only ever DROP (an invalid address), never split.

And the site ARM fires only on a ROOT-ONLY `src:site` chain: the arm keys on what the chain resolves
TO, so `src:site;refs,staff` (live since FW-10, 1.17.0) resolves `post` and takes the post arm. A
root-only site chain is singular and cannot fan.

**So entity count == item count on every reachable path today.** Two consequences:

- A proposed §OPEN row — does a group limit count ENTITIES or ITEMS — was WITHDRAWN, not deferred.
  Nothing currently distinguishes the two readings. Recorded because the question is well-formed and
  will become real the moment any dispatcher returns N>1 for one entity.
- The extraction (S32) can take `try_`'s 1:N-tolerant shape at no present cost, and inherits the
  answer for free if a producer ever lands.

## S32 — the extraction runs FROM `try_`, not toward it

`try_`'s emit loop already implements "walk candidates, render, drop empties, stop at N usable". The
base list folds do not. So the shared selector is LIFTED from `try_` and the base folds become
consumers — not the reverse.

House precedent is exact: FW-49 borrowed datetime's shape rather than supplying one, because datetime
was structurally ahead (`docs/design-history/per-step-limit.md`). Same move, same reason.

**What generalises and what does not:**

| | Generalises? | Why |
|---|---|---|
| **Selection** — walk, render, drop empties, stop at N usable | **Yes** | `try_` has it. The three `takes_first_usable` loops are the same function at N=1. FW-43's planned `bws_select_first_value()` IS that function. Three private copies of one rule is the drift the "require the real file" harness rule exists to stop. |
| **Grouping** — N per input (S18) | **A WRAPPER, not a parameter** | The selector stays provenance-blind, so the three collapsing loops and any future flat-stream consumer use it unchanged. |

**This does NOT re-open the FW-71 rejection.** `try-slot-arms.php`'s PHPDoc records that generalising
the ARM TABLE into a shared base-side map was considered and rejected — *"it moves the boundary past
what the harvest/replay instrument was built to bound."* That is about the kind→arm dispatch. The
selector is DOWNSTREAM of the arms and kind-blind: it never asks what kind an entity is. Different
seam, and the rejection's reason does not reach it.

**Where the widening lands (the closed half of O7).** Provenance is destroyed in
`bws_base_source_ids_of_kind()` (`base-shared.php`), whose signature is `ResolvedSource[] → int[]`. It
runs the whole chain, keeps `$src['id']`, discards the rest. That is ONE shared function above all
fourteen consumers, which is what makes this cheaper than a per-call-site change. It gains a
pair-returning sibling (`array{id, from}`) for the eight grouped list sites; the three collapsing
loops and the three `try_` arms keep `int[]`.

**A wholesale `ResolvedSource[]` return was REJECTED.** `try_`'s `$ids` is a union of that output and
two locally-made sentinels — `[0]` for the site store (which carries a namespace, not an id, ADR 0002)
and `[false]` for the mode-2b flat repeater row (which has no id by construction). Neither can be
expressed as a resolved source, so the wholesale shape forces `try_` to fabricate fakes or unwrap
immediately. That cost is independent of any slice boundary.

## S33 — the two surviving wire compares

Found 2026-08-20 while checking whether `try_`'s site arm can fan. **Fixed in slice A by decision
(user); no GitHub issue** — CLAUDE.md §Bugs, a bug found and fixed in the same change is a fourth copy
of a record three places already hold.

**The defect.** `bws_try_email_post_dispatch()` and `bws_try_phone_post_dispatch()` select their site
branch by comparing raw wire:

```php
if ( 'site' === ( $options['src'] ?? '' ) ) {
```

`$slot_opts['src']` is CHAIN WIRE on every slot since #104. `bws_fold_emit_chain()` renders a
root-only site chain as the bare string `site` — one segment, no arg, no `limit`, no `extra` — so the
compare holds on machine-emitted wire today. It stops holding the moment the root step carries any
decoration (`site,limit[2]`, an `extra` token, or anything a future emit learns to write). Machine
paths never write those; **hand-edited wire can, and ADR 0004 makes hand-edited wire mean what it
says.**

**Why it is worse than a broken read.** These two templates register no `try_site_fn`, so the site arm
reaches them through the documented FW-4 fallback leg, `try_core_fn( 0, … )`. With the compare
failing, execution falls into the POST branch and calls `bws_read_field( $key, $instance, 0 )` — and a
falsy id does not stop that read, it infers the loop row and then the queried term. The slot renders a
**plausible value from the wrong entity**, and because a `try_` slot is selecting, a read that
succeeds STOPS the fallback chain: later attempts never run.

That is `CONTEXT.md` **[I15]** exactly, aggravating clause included. It is also the pattern FW-71
deleted — `try-slot-arms.php`'s PHPDoc opens by naming it (*"FOUR hand-written arms stood here, each
testing the flat source token directly"*). Email and phone sat outside FW-71's scope, so two copies
survived, re-deriving from the wire what the arm dispatch already decided.

**The fix.** The site arm knows it is the site arm. Either pass that fact into the dispatcher instead
of re-testing `src`, or register a real `try_site_fn` for both templates and retire the fallback leg
for them. Both are small; the second removes the fallback leg's only two users, which is the tidier
end state but widens the diff.

**The pin is not optional and is the reason this needs no issue.** A row in
`tools/test/try-slot-arms-test.php` §A4 asserting that a DECORATED root-only site chain still takes the
site arm's read. Without it, nothing fails when this regresses — which is CLAUDE.md's own "nothing
pins it" filing trigger, and satisfying the trigger is what makes not filing correct rather than
merely convenient.

**Scope note — the set is CLOSED, verified 2026-08-20.** Both directions swept:

```
grep -rn --include=*.php "=== *( *\$\(options\|opts\|slot_opts\)\['src'\]" includes/
grep -rn --include=*.php -E "\[.src.\] *(===|==|!==) *'" includes/
```

First returns exactly `email-tags.php:281` and `phone-tags.php:548`. Second returns nothing. There is
no third site and no reverse-order spelling. Re-run both before calling A done — the sweep is cheap
and the failure is silent.

## S38 — the owner doc says both things, and only ONE CLAUSE is stale

`tag-reference.md` §List mode contradicts itself 67 lines apart:

- **:264** — *"`limit` bounds the **resolved-source list**, once, before the read — the last step's
  output. It never bounds values: the read is one value per resolved source with empties dropped
  afterwards, so `limit:3` can print two."*
- **:331** — *"empty items are skipped, the list is sliced to `limit` and joined with `sep`."*

§What is broken 1 cited :331 and invoked "doc wins on drift". :264 is the same doc, in the preamble
that OWNS the rule, stating the shipped order deliberately and naming its consequence — the stronger
statement, and the one a reviewer finds.

**It is not doc-versus-itself on the RULE. It is scope drift** (user, 2026-08-20). Take the model's
own reading of "resolved source" and the clauses come apart:

| Clause | Verdict |
|---|---|
| "bounds the resolved-source list" | **TRUE, and it is §The rule's INTERMEDIATE criterion** — a limit over resolved sources is a limit over valid, visible entities |
| "once, **before the read**" | **STALE** — the terminal position applies its bound after |
| "so `limit:3` can print two" | stale, a consequence of the clause above |

So :264 states the INTERMEDIATE criterion in the position where the TERMINAL one belongs, and :331
states the terminal one. Neither says which position it describes. Ordinary drift of SCOPE — the doc
wins, the code moves, no decision needed. The `feedback_label_divergence_is_a_decision` route (doc rows
disagreeing → ASK) does NOT apply, because they do not disagree about the rule.

**What survives of §Why this is a restoration:** the LABEL leg only. Both controls have always said
*results* (*Result Limit*, *Limit results*, "Maximum number of results"), and that is the user-facing
contract. The DOC leg is withdrawn — the doc's clearest sentence backs the code. CHANGELOG stays
**Fixed** on the label argument alone.

**Owed:** :264 and :331 reconcile in ONE edit, shipping with B+C (S46).

## S39/S40 — the vocabulary, and where each term lands

**Resolved source stays OUTCOME-DEFINED. ADR 0002 is untouched** (user, 2026-08-20). The sharpening
that would have made validity part of resolved-source-hood was examined and declined: the engine
emitting `{kind:'post', id:999}` for a deleted post is correct, and that source fails a later test.

**A stale reference is real, and it is the steady state.** WP does not clean postmeta when a post is
deleted, and the refs arm ends in an unconditional `get_post_meta()` fallback with `bws_extract_post_id`
accepting any `is_numeric` value — **no existence check on the path**. Reachable whenever ACF is absent,
the field is not an ACF relationship, or a non-ACF handler stored the ids.

**This is the MIRROR of S35.** For terms the intermediate test is vacuous (only `get_the_terms()` makes
a term source). For posts it has real failing cases and they are ordinary. S2's intermediate clause
earns its keep entirely on the post side.

Three levels, two of them newly named:

| Level | Term | Stale ref | Live post, empty field |
|---|---|---|---|
| L1 emitted a bound | **resolvable** | yes | yes |
| the bound names a live entity this viewer may read | **visible** (S19–S21) | **no** | yes |
| the read produced something | *non-empty read* — no noun coined | no | **no** |

`visible` over `displayable`: the repo's ~10 existing "visible/invisible" usages are all *is this seen
by a person* (an author, a maintainer), which is the same concept aimed at a different person — the
identical "consistent adjective, different subjects" argument that made `resolvable` acceptable despite
three existing uses. The one outlier, "invisible to `scan()`", is machine detection about the Migration
Tool's reach, a different axis with no confusion risk. `visible` also carries S19's viewer-relativity
in the word, where "displayable" sounds like a property of the thing. `addressable` was unavailable —
taken by link identity (`CONTEXT.md:164`).

**`usable` is MODEL VOCABULARY and must never appear in user-facing copy.** The 1.17.0 Upgrade Notice
already shipped *"unusable sources output nothing"* in the L1 sense, and notices are frozen like the
CHANGELOG. The user-facing word is **results** — which both controls already say. The two senses never
meet, provided `usable` stays out of help text. That gets an explicit `_Avoid_` line, the device
`CONTEXT.md`'s glossary already uses.

**Homes (S40):**

| Term | Home | Why |
|---|---|---|
| `resolvable` | `CONTEXT.md` §Language | property of a resolved source |
| `visible` | `CONTEXT.md` §Language | property of a resolved source |
| `usable` | `tag-reference.md` §List mode | property of what a LIMIT counts, not of a source |

Plus **`I19` — *a limit bounds usable results*** (I18 is currently highest). Cross-cutting: the
collector, the engine, `try_`, every list fold. It USES the terms and links the schema, per the rule
that invariants never define. And **ADR 0007**, companion to 0005 — 0005 owns WHERE a limit is stated,
0007 owns WHAT IT COUNTS. Different axis, same family; 0007 must distinguish itself from 0005's
rejected-options list, and the sharp point there is that **S26's notice has nothing to accompany**:
0005 rejected a notice as a SUBSTITUTE for serialization, and the 1.17.0 notice ACCOMPANIED one, which
0005 explicitly permits. S1 changes render behaviour with no wire change. That is what 0007 justifies.

`:127`'s "present but unusable" is neither level above — it is a RESOLUTION failure (unregistered root,
unknown step slug, a source resolving nothing), so it becomes **"non-resolving"**, the phrasing its own
outbound link already uses (`plugin-integration.md#what-a-non-resolving-source-renders`).

## S41/S42 — a limit applies to its own step; what that makes of `try_`

**S41 (user, 2026-08-20):** *a stated limit applies to the step it is stated on, not to every step.*
ADR 0005's own sentence, applied one level down. The alternative examined and REJECTED was `try_`'s
spelling — "the LAST step that PINS a limit" (`slot-fold.php:160`) — which lets a number stated on
`terms` bound the output of `refs`.

The consequence, stated so it is not re-litigated: when a step is appended after a bounded one, the
bound's OBSERVABLE meaning changes — `terms,category limit[3]` means *3 categories with a value* alone,
and *3 categories* once `;refs,sponsor` follows. **That is correct, not a defect.** The limit always
bounded 3 usable outputs OF ITS OWN STEP; what changed is what counts as a usable output of that step
once something reads it downstream. One rule, position-relative test — which is what S1's "strongest
test computable at that step" already says.

**S42 — `try_` is right on ONE axis of four.** Worked against one fixture (E1–E4, 2026-08-20):

| Axis | `try_` today | Verdict |
|---|---|---|
| collect-then-slice ORDER | correct | **the donor.** The fix for defect 1, and all §S32 legitimately extracts |
| bound came from a PINNED STEP limit | broken | the same number already truncated candidates in `bws_run_traversal` before the loop saw them. `try_` cannot recover what the engine discarded — a shared defect with the base, fixed engine-side |
| `break` accumulates ACROSS entities | **global** | defect under S18 (per-input, confirmed) |
| bound FLOATS to the last pinned step | floats | defect under S41 |

```php
if ( $slot_max && count( $items ) >= $slot_max ) { break; }   // $items is CROSS-ENTITY
```

**So `try_` is more patient than donor.** Both its own defects are ADDITIVE — per-input renders more
items on a multi-fanning slot; a non-floating bound leaves the appended step unbounded where a foreign
number bounded it. The population is small and recent: folded slots shipped in 1.17.0 (#104).

**THE TWO DO NOT SHIP TOGETHER — amended 2026-08-20 during the seam sketch (user took the split).**
Q15 put both in A. Working the seams showed only one of them is A-sized:

| Defect | Needs | Slice |
|---|---|---|
| bound FLOATS to the last pinned step (S41) | a fix in the slot's limit selection — no grouping | **A** |
| `break` accumulates ACROSS entities | per-input grouping at **N>1**, which is S25's provenance machinery | **C** |

A's selector is **N=1**: a collapsing tag wants one result TOTAL, not one per input. `try_`'s slot
bound is N. So the cross-entity fix cannot ride A's seam — building it there means building C's
grouping inside A, which is §S28 option 3 (merge A and C) arriving by the back door.

`try_` therefore carries the cross-entity defect for one more release. Acceptable because it carries
it TODAY and A does not worsen it — A's engine change removes the truncation that currently masks how
often the bound is reached, which if anything makes the remaining defect easier to observe.

**S26's fourth row splits with them.** The A half is the floating bound; the C half is per-input.

## The capability

**Name: `takes_first_usable`** (S29, superseding the provisional `collapses_to_first` in S7 — which
named an element-0 positional take, the very mechanism S24 removed).

**What it asserts:** this tag emits at most ONE result, selected as the first USABLE one, whatever the
chain produces. True today of exactly `content`, `permalink`, `image`.

Naming it for the SELECTION rather than the cardinality is deliberate: the mechanism is load-bearing,
because limits are inert precisely BECAUSE selection scans for the first usable one. A reader of a
cardinality-shaped name (`single_output`) would still reasonably expect a limit to bound the scan.
`single_result` stays rejected — it is a defined output-shape term for an INSTANCE of output, not a
template capability (`{{email}}` is single-result on one post and list mode across a term's posts).

**`{{table}}` declares it explicitly false**, with a comment, rather than taking the default — it is
precisely the tag a list-mode-keyed proxy would have caught by accident, so silence there would repeat
that failure shape inside the new mechanism.

**Not keyed on list mode.** Three independent reasons:

- `{{table}}` is not list mode (it repeats rows, joins nothing) and its `rows` step limit is the most
  load-bearing limit in the plugin. It has no row in §List mode today, so a `supports_list`-keyed
  suppression misses it by OMISSION — the day someone adds the honest row, suppression lands on it.
- `try_list_options` already exists as a per-template boolean (text, title, email, phone) and is NOT
  the §List mode column — `datetime_single` is list mode and sets no such flag. The existing flag is
  a `try_`-scoped registration switch, not the output-shape fact.
- "List" is acquiring a third sense (`sep`-joined string / `{{table}}` row repeat / a possible
  `ol`/`ul` output mode on base tags). Staking the suppression on that word stakes it on the term
  most likely to move.

**Home.** The template record, beside `supports_try` / `is_image` / `leading_options` /
`try_per_slot_key` / `try_per_slot_use` / `try_list_options` / `try_use_no_key_values`. Unprefixed —
the `try_` prefix marks flags only the `try_` constructor consumes, and this one is consumed by both
the base registration and the slot builder.

`bws_register_base_tags()` (`base-tags.php:47-680`) contains BOTH the five base registrations and the
`register_modifier_template()` calls, so both halves read one declaration in one scope.

As the enforcing site, the flag's docblock is the one place allowed to NAME the axis; every other
mention states its consequence (CLAUDE.md §Documentation ownership).

## The control

**Suppressed, not reworded.** Under S1 the control is genuinely inert on these tags: any N of 1 or
more selects the same single usable result. This is inertness by construction, not the earlier
prefix-truncation argument (which was wrong — see §Corrections owed).

**Two surfaces, both already have a seam:**

- Base tags — `bws_build_src_chain_option()` is called ONCE at `base-tags.php:60` and merged into
  every base tag. All three collapsing tags need the SAME variant, so this is one extra build call,
  not a restructure.
- `try_` slots — `steps` is already documented as "a CAPABILITY list, and since #104 it is the BASE
  TAG'S" (`class-tag-template-registry.php:563`), threaded per-template into
  `bws_build_fold_slot_options()`. The flag rides the same route, covering `try_content` /
  `try_permalink` / `try_image` and correctly leaving `{{join}}` and `{{table}}` alone.

**S9 — an explicit conditional is required.** `var limitCfg = conf.limitOption || {}`
(`slot-fold-control.js:849`) tolerates an absent config but still RENDERS. Omitting the vocabulary
alone yields an unlabelled text box, which is worse than today.

**S10 — stored wire is untouched.** Serialization lives in the grammar, not the control:
`slot-fold-grammar.js` emits `limit[N]` from the step object whenever set and never consults
`limitOption`. The control preserves `limit` across slug changes ("keep `limit` always (it bounds the
step, not the arg)", `slot-fold-control.js:747-749`). ADR 0004 satisfied.

**Precedent:** #62 unregistered the TAG-LEVEL limit control on every chain-authoring tag while keeping
the key read — "removing a control never removes an option (ADR 0004; GB seeds state from the tag
string, not the registry)" (`base-tags.php:88-96`). This is the same move at step scope, narrowed to
three tags.

**Container-invariance (#95) is not touched.** #95 governs the control's label and help. Suppression
adds no label variant; it declines to offer the control. (A REWORD would have added a third help
variant on a new axis — the existing two are chosen by chain state via
`bws_fold_chain_fanning_steps()`; a consumer-chosen third would be different in kind.)

## The field note

The field configuration note (`bws_field_discovery_field_note()`, `includes/rest/field-discovery.php`;
owner doc `editor-controls.md` §Field configuration note) has a **pre-existing falsehood** on
collapsing tags. Its consequence clause, on cases 4 and 6:

> *The first stored entry will be the only result while this field is single-entry; **all entries will
> be results if it is reconfigured as multiple-entry**.*

The bolded half is false on `{{content}}` / `{{permalink}}` / `{{image}}` — reconfiguring to
multiple-entry changes nothing, the tag still renders one result. Independent of the limit control;
the collapse is there today.

The derivation rule that places the clause has the same tag-level exception:

> **The consequence clause rides single-entry, not bidirectionality.** ... **Relationship cases get no
> analogue: no collapse there, so overflow simply renders.**

"No collapse there" is true of the FIELD and false of these TAGS.

**S11 — fix scope:** remove the false half. The note keeps its own domain (what ACF does and does not
enforce); it does NOT acquire the collapse fact (S12).

## The advisory

**S12 — the collapse fact does not belong in the field note.** Two reasons:

- **Axis.** The note's stated domain is "what ACF does and does not enforce about how many entries
  that field can hold". The collapse is a fact about the consuming TAG. The note is derived per-field
  on a REST envelope with no tag identity (`register_rest_route` takes `'args' => array()`), so
  routing a tag fact through it makes an ACF-enforcement note stand proxy for something it does not
  decide — [#119](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/119)'s class.
- **Coverage — two silent holes.** The note cannot reach either:
  1. Relationship field, no ACF max, not bidirectional returns `null`. The code names it: *"No limit
     to state, and no format-time collapse — so a non-bidirectional relationship field has nothing
     noteworthy at all."* That premise is true of the field and false of the tag, and it is the most
     ordinary fanning relationship field there is.
  2. A `terms` step has NO field key — it is a taxonomy select built from `get_taxonomies()`. No
     field-discovery envelope exists, so no note can attach. `{{image}}` fanning across a taxonomy is
     completely unannotated.

**S13 — a group-end advisory carries it.** One line at the end of the `source` visual group,
conditional on a fanning step existing. Covers `refs`, `terms` and both silent holes, stated once per
chain rather than once per step.

- The condition is FREE: `bws_fold_chain_fanning_steps()` is the shipped predicate for "does an
  earlier step actually fan", already reached in the editor through its twin.
- The control is the established `bws-*` pattern through `tagSpecificControls`.
- It needs a row in `bws_option_visual_groups()` — non-optional. An ungrouped control spliced BETWEEN
  two grouped ones splits the visual box, and `control-order-test.php` §1 catches it only because it
  now walks the full option sequence (`registration-helpers.php:229-246`).
- It changes `control-order-test.php` expected sequences for three tags — the one non-pure harness.

**Tag descriptions are NOT this surface.** `gb-constraints.md:317` calls them "help text shown below
tag in selector UI"; the user reports they render in the PANEL, below the tag picker and above the
controls. Either way they are unconditional, so they cannot carry a fanning-conditional advisory.
They are the right home for an unconditional shape statement ("Returns one image URL") and are
separate work (S15).

## S28 — build sequence

**A → B → C.** Release grouping is NOT decided; the user will pick the release point after seeing what
each costs (2026-08-20).

| | Slice | Contains | Depends on |
|---|---|---|---|
| A | Collapsing-tag fix | the capability + S24 + the POST-SIDE first-usable fix (§What is broken 5) + the selector EXTRACTION + S8 suppression on both surfaces + the field-note falsehood (S11) + the two wire compares (S33) + `try_`'s FLOATING BOUND (S41 — its cross-entity `break` stays in C, S42) | the extraction, **which it performs** |
| B | Displayable gate | S19-S23 (S20 as CORRECTED — injected predicate), Upgrade Notice, `gb-constraints.md` + `tag-reference.md` entries. **The filter itself is now OPTIONAL here** (S22 loosened) — build the predicate seam, ship the hook when a consumer asks | nothing |
| C | The general limit rule | S1/S2 + S25 provenance + collector grouping + the migration change + the ADR | benefits from B, does not require it |

**Why A first:** smallest coherent unit; fixes a regression that is live on every site converted since
1.17.0; purely ADDITIVE, so no Upgrade Notice and a simple release; and it delivers #118's outcome,
which is where this started.

**A IS NOT INDEPENDENT OF C, and the coupling is RESOLVED (2026-08-20).** The table originally said A
depended on nothing. Under S32 it depends on the selector extraction, and §What is broken 5 says why
in concrete terms: A's post branch takes the FIRST source where it must take the first USABLE one, and
that loop is the selector at N=1. Three ways were on the table; **option 1 is taken — the extraction
moves INTO A**, which grows A, keeps it additive, and hands C a built selector. The alternatives were
a fourth private copy of the rule (briefly, by intent — the weakest form of that argument), and
merging A with C, which gives up the small additive release that is A's whole justification.

**RELEASE GROUPING (S45, closes O11): A ALONE; B AND C TOGETHER.** The argument is S34 — change 1 and
change 3 BOTH ride the Upgrade Notice. Split B from C and you write two notices carrying subtractive
content in consecutive releases, inside a 300-character cap already holding two consequences; ship
them together and one notice covers both. It also collapses note 2 below into a single event: "usable"
reaches its final meaning once, so A's matrix rows are revised once rather than twice. Against it: B+C
is a large release. For it: S28's own table already says C *"benefits from B"*, and they share the
notice, the semantics and the vocabulary.

**MODEL WORK SHIPS SPLIT (S46, from Q19).** ADR 0007 and the `CONTEXT.md` vocabulary (`resolvable`,
`visible`, I19) go with **A** — an ADR records a decision rather than an implementation, and the
vocabulary is needed to write A's commit messages and matrix rows coherently. The `tag-reference.md`
reconcile (:264/:331, :127, `usable` in §List mode) goes with **B+C**, because that doc states current
schema and correcting :264 early would put a false statement in the authoritative place. Between the
two releases **I19 and `tag-reference.md:264` disagree on purpose** — I19 carries the target,
`CONTEXT.md`'s reading posture explains why a live contradiction is a tracked refactor. I19 says that
out loud rather than leaving it to be discovered.

**WHAT SHIPPING DOES TO THIS FILE.** CLAUDE.md §Spec lifecycle ("A PLAN COMMITS WHEN IT IS FINISHED,
NOT WHEN IT SHIPS") means a phase boundary is a documented move, not a filing decision: **something
from this plan gets committed to `docs/design-history/` when a slice ships, and the live plan stays
private with what remains.** Which sections that is gets derived AT THE SHIP, against what actually
shipped — not pre-decided here. An earlier version of this paragraph enumerated the boundary in
advance; A has since grown (the post-side fix and `try_`'s defects are S1/S2 territory, not
collapsing-tag territory), which is exactly why a boundary drawn early goes stale. The rule that does
NOT change: a lifted record states what shipped and points here for what did not.

**A HAS A SPEC (2026-08-20):** `.scratch/first-usable/spec.md`. Buildable subset only — this plan
stays the reasoning, and the spec cites it rather than restating it. The spec's seam sketch is what
produced the S42 split above.

**Two things A's release notes must state rather than let users discover:**

1. **A does not fix list-mode tags.** `{{text}}` asking for 3 and getting 1 because two were empty
   stays broken until C. A fixes only tags that return one result.
2. **"Usable" means NON-EMPTY in A and tightens to VALID-AND-NON-EMPTY when B lands.** A's matrix rows
   get revised at that point. That is the price of shipping A first; the alternative (B then A) buys
   final semantics at the cost of leaving the regression live another release.

## Passes

**Pass 1 — semantics + suppression.** The rule (S1/S2) at its three sites, the capability, suppression
on both surfaces, the field note's false half removed. Leads with the 1.17.0 regression fix, not the
control cleanup.

**Pass 2 — the advisory.** The group-end note, which is where `terms` and the two silent holes get
covered. Panel-layout change with its own doc and matrix obligations; wants eyeballing on the testbed
rather than bundling.

## What GB does, and where we diverge

Checked against GB 2.3.0 before filing anything (the user asked; I had not).

**GB gates the REST surface only.** Every `read_post` check in GB is REST-scoped:

- `GenerateBlocks_Meta_Handler::get_meta()` — the path `bws_meta_handler_read()` routes through —
  gates under `defined( 'REST_REQUEST' ) && REST_REQUEST && ! current_user_can( 'manage_options' )`,
  checking `read_post` for `get_post_meta` and `is_protected_meta` for post/term
  (`class-meta-handler.php:261-278`). On a front-end render `REST_REQUEST` is undefined, so it never
  fires.
- `get_dynamic_tag_replacements()` gates the context post and any explicit `id:` option on
  post/author/media tags (`class-dynamic-tags.php:513`, `:579`); a post-fetch REST endpoint gates at
  `:727`.
- Nothing in GB's front-end replace path checks status or capability at all.

**`GenerateBlocks_Dynamic_Tag_Security` is NOT this.** It is a save-time and REST-time meta-KEY
allowlist — `DISALLOWED_KEYS` is `post_password`, `password`, `user_pass`, `user_activation_key`.
Nothing to do with post status.

So GB's posture is deliberate: **gate the editor/API surface, leave front-end rendering to the
author's judgement.** Our front-end behaviour currently matches GB's own.

**Two things still distinguish our exposure**, and they are why S19/S23 stand:

1. GB's tags take an EXPLICIT `id:` — the author named that post. Ours TRAVERSE a field: the author
   chose the field, the site chose the ids. Unpublished entries arrive without anyone deciding they
   should.
2. Gating the front end is therefore a DELIBERATE DIVERGENCE from the platform, not a defect fix.

**Doc obligation that follows** (CLAUDE.md §Documentation ownership): GB's REST-only gating is a pure
GB fact and belongs in `gb-constraints.md`; our decision to gate the front end too is our response and
belongs in `tag-reference.md`.

## Corrections owed

Claims made during the session that were wrong. The first is recorded on a surface outside this file.

1. **On #118:** *"No step limit, at any depth, can change which entity survives the collapse."* True
   only of POST-resolving chains. All three tags have a term branch that searches for the first
   NON-EMPTY (`base-tags.php` — content 1146-1155, permalink 1331-1339, image 1398-1405), and a step
   limit truncates the candidate list before that search. The comment also named the destination axis
   without citing its owner, `CONTEXT.md` I7 — a non-owner naming an axis, which is the defect
   CLAUDE.md describes.
2. **In session:** *"Intermediate steps can't bound results."* Wrong — an intermediate step does read,
   to produce the next entities. What it cannot know is whether the FINAL read will be non-empty.
   S2 is the corrected statement.
3. **In session, 2026-08-20:** *"`try_` bounds ITEMS over one flat stream and deliberately wants no
   grouping."* Wrong. A `try_` slot is chain-folded (#104) and groups like a base tag — §S31. The emit
   comment that looked like a refusal declines `bws_collect_value_list()` over a SECOND multiplicity,
   not over grouping. Caught by the user.
4. **In session, 2026-08-20:** *"the fold's `never inspects an item` contract is the obstacle to
   provenance."* Wrong function. Provenance is destroyed in `bws_base_source_ids_of_kind()`, one layer
   above — §S32.
5. **In session, 2026-08-20:** a proposed §OPEN row asking whether a group bound counts entities or
   items. WITHDRAWN before it was written: nothing reachable distinguishes them (§S31).
6. **S20 as first written** — *"gate at source coercion (`bws_pipeline_*_to_sources()`)"*. Withdrawn
   2026-08-20: those three functions are pure and the cheap harness requires the real file, so an
   inline `current_user_can()` would make it test a shimmed gate. Corrected to an injected predicate
   (§S20 corrected). The plan had rejected the TESTABLE shape and mandated the untestable one on the
   same stated ground, which is why §S25's rejection paragraph now carries an explicit scope note.

7. **In session, 2026-08-20:** *“defect 3b — the stamp writes `1` on every non-last fanning step, so
   a touched multi-fan tag searches only the first ref; a narrowing regression needing its own
   decision.”* Wrong, and wrong in a way worth keeping: the alternative is an OVER-delivery, and no
   faithful spelling exists because the tag-level slice is global while step limits are per-input.
   The heuristic is the correct lossy choice — §S37. Caught by the user, who wrote it.

8. **In session, 2026-08-20:** §S37, which re-derived from code what THREE committed sources already
   owned — the axis at `bws_fold_chain_apply_legacy_limit()`'s PHPDoc, the decision at ADR 0005:64, and
   the known incompleteness at `tag-reference.md` §"Known migration-fidelity gap", which had already
   confirmed ZERO live instances. The section asked for a survey that was done. This is the
   re-derivation failure §Long-lived plan files warns about, landing on DOCS rather than on the plan —
   worth noting because the warning as written points only at the plan.
9. **In this file, until 2026-08-20:** §What is broken 4's heading, *"NOT BROKEN — `try_`'s slot
   limit"*. Wrong on three axes of four (S42). The claim that survives is only that `try_` collects
   before it slices.
10. **In session, 2026-08-20:** a recommendation to adopt `try_`'s "last step that PINS a limit" as the
   terminal rule, on the ground that it defuses the append objection. REJECTED by the user, correctly:
   it lets a number stated on one step bound another step's output, which is ADR 0005's rule inverted.
   The append behaviour is not an objection to answer — it is the rule working (S41).

## Owed elsewhere

**DONE 2026-08-20:**

- ✅ **[#120](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/120) filed** —
  the disclosure (draft/private content rendering to front-end visitors), carrying S19-S23, S27 and
  the compatibility stance. Summary and Scope written against what GB actually does, not the first
  draft's "nothing anywhere gates".
- ✅ **#118 corrected** — the inertness claim narrowed to post-resolving chains, I7 cited as the axis
  owner, the fix restated as suppression falling out of S1/S24, `takes_first_usable` named, and the
  #95 invariance argument walked back to the accurate one.
- ✅ **#93 commented** — O2/O3/O4 answered on the record, the disclosure half split out to #120, and
  the issue's own `limit` observation connected to the settled rule.

**STILL OWED:**

- **A bug issue for the limit defect itself** — limit-bounds-candidates plus the 1.17.0 term-search
  regression. Not filed yet: it is the subject of slices A and C, so whether it needs a tracked row
  depends on whether those ship soon (CLAUDE.md §Bugs — a bug found and FIXED in the same change needs
  no issue).
- **FW-43** — its "closes without changing scope" line no longer holds. Under S1/S2 both branches
  converge on first-usable, so `bws_select_first_value()` is the home for both, with a validity
  predicate rather than only an emptiness one.
- **A NEW tracker row for tag descriptions** across all tags, with the `gb-constraints.md:317`
  placement correction folded in. **The number is allocated when the row is written, not here** — an
  earlier draft of this file called it FW-84, which is taken (`src:site` slot for the two `datetime_`
  try_ tags, shipped 1.17.1). **FW-85 is now taken too** — per-item link wrapping, allocated
  2026-08-20 out of §S26. Check `docs/future-work.md` for the next free number rather than trusting
  any number written here; this bullet has been wrong twice.
- **`CONTEXT.md`** — I7's body/document bullet states no disposition where the attribute bullet does
  ("Plural target collapses to first"). The gap is why FW-43's question was answerable at all.
- **`tag-reference.md`** §List mode — S1/S2 as the stated limit semantics.
- **ADR 0005** — its reasoning is implicated: limits ride steps, and now what a step limit MEANS has
  changed.

## Tests implicated

`limit-clamp-test.php`, `limit-default-test-matrix.md`, `traversal-pipeline-test.php`,
`fold-chain-compile-test.php`, `slot-options-build-test.php`, `control-order-test.php`,
`editor-filter-chain-test.js`, `slot-fold-repeater-test.js`, `fold-test-matrix.md`, and
`fold-migration-test.php` (S36 — the before-touch/after-touch equality pin; S37 — the two-fan stamp
shape, pinned WITH a POINTER to its axis rather than a restatement of it),
`try-slot-arms-test.php` + `try-join-seam-test.php` (S42 — the cross-entity `break` becoming
per-input, and the bound no longer floating), `as-size-fold-test.php`-style entry-vs-transform pinning for the migration change. Matrix rows must
also be generated as VISIBLE GB blocks (`docs/testbed.md`).

---

# 2026-08-21 — the determinism reversal

Grill session 2026-08-21, opened by the user on a defect found AFTER slice A was built on
`feat/first-usable` (nothing released). Everything below supersedes what its §SETTLED flips say it
supersedes; sections above this line are the record of the earlier state and are not edited.

## §S47 — usable is a property of SOURCES; the populated arm is removed

**The defect.** Making "field is populated" part of the selection/counting predicate makes SOURCE
SELECTION depend on WHICH FIELD the tag asks for. The user's most common pattern — `limit(1)` on a
refs step, several adjacent tags pulling different fields through the same source path — becomes
unstable: `{{title}}` pins post A while `{{image}}` skips to post B because A's image is empty. Same
path, different entities, and ACF's incomplete limit enforcement (the 1.17.0 advisory's own subject)
makes the extra entry that triggers it routine. A crude `limit(1)` was the author's working tool for
"the first and only the first referenced post", and the populated arm took that tool away: it became
impossible to SET which source a tag reads.

**The rule.** `usable` is a property of a SOURCE, not of a read: **resolvable × exists × visible**
(§S50 owns the vocabulary). Field population is not part of the predicate ANYWHERE — not at the
terminal step, not in the collector, not in the editor's reasoning about a chain. A limit counts
usable sources; a source failing the gate never consumes budget; an empty READ of a usable source
consumes its slot and outputs nothing. Selection is therefore field-independent and deterministic:
adjacent tags on the same path read the same entity.

**Collapsing tags** read the FIRST usable source and output its read, even when that read is empty.
S24 stands — they still ignore every limit at every position (the tags can only return from one
entity; what changed is WHICH entity, and the ignore keeps the seam below meaningful) — and S44's
polarity FLIPS: the post branch that "took the first SOURCE" was the correct one, and the term
branch's first-non-empty search is the defect. The term route thereby LOSES shipped behaviour
(an unlimited `{{image}}` across three categories no longer finds the third's image) — that is the
subtractive change §S49's Upgrade Notice discloses. Term order is WP's own (`get_the_terms`
pass-through, alphabetical by default, author/plugin term ordering respected) and is deliberately
not overridden.

**The populated-search survives as a DORMANT SEAM, deferred as a possible tag-level opt-in.** The
expensive part of the opt-in is the control + wire token + label/help + placement — hours of design
deliberately NOT spent now. The seam: `bws_collect_usable()` gains an optional usability-predicate
parameter (null = no skipping, the default everywhere); the populated predicate ships as a named
pure function pinned by `collect-usable-test.php` and called by NOTHING. The exists/visible gate
does NOT live in this parameter — it mounts in the engine (§S48). When the opt-in is designed, a
tag-level boolean read at registration picks the predicate into the callback closure, the same way
`takes_first_usable` already rides. An era-stamping migration (flat-era tags demonstrably searched)
is the recorded route for honouring the Migration Tool's promise IF the option ships. FW row
required; term chains flagged there as the constituency the option matters most for (their order is
alphabetical accident, not author intent).

**`try_`**: within a slot, base behaviour exactly — the slot reads its first usable source; an
empty read means the ATTEMPT loses and the chain falls to the next slot, EVEN when a later source
in that slot's fan had the value. Across slots, first-populated attempt wins, unchanged — that is
the product. Both halves confirmed deliberately (2026-08-21), the fall-through with eyes open.

**Editor surfaces.** The advisory (S13) stands with revised copy — direction "This source can match
more than one item. This tag shows the first one." (final wording at the release prose review). The
field-note consequence DROP stands (S11's mechanism unchanged): under the new rule the dropped
sentence's second half ("all entries will be results") is still false on a collapsing tag, and the
advisory carries the true fact on the right axis. The **Limit results** label/help now states the
wrong quantity in the OTHER direction (it counts sources read, not results shown): static reframe
ships with the semantics; dynamic per-kind labels ("Limit Posts Read" / "Limit Terms Read" /
"Limit Repeater Rows Read") are a SECOND pass inside 1.18.0 once wording settles.

Supersedes: S1/S2's terminal arm, S17, S44's polarity. S32's extraction survives but the selector's
job narrows to (a) the N-bounded source read and (b) the dormant predicate seam.

## §S48 — one gate, every step: exists/visible mounts in the ENGINE; slice C is RETIRED

**Uniformity (user, 2026-08-21): every step takes the same usable criteria — intermediates
included.** The exists arm (and the visible arm when it ships — same mount, no new seam) applies at
EVERY hop: injected predicate on `bws_run_traversal` per §S20-corrected (whose scoping already
cleared this — the engine-predicate rejection at §S25 covered only the READ-COUNT predicate), O8's
`(bool $ok, array $source)` AND-composed contract, applied after reader emission and BEFORE that
hop's limit slice. So every step's limit — intermediate and terminal — counts resolvable × exists
(× visible) sources, per input, in the engine. I19 becomes uniformly true; the stale-ID residual an
earlier draft of this reversal accepted is deleted rather than accepted.

**Named consequence, decided not incidental: an invisible entity cannot be a STEPPING STONE.** A
chain hopping through a viewer-unreadable post is cut at that hop even when the hop's own targets
are public — data reachable only through an entity you may not read stays unreachable. Viewer-
relative per S19; O9's cache guidance is restated beside it.

**Coverage fact (explored 2026-08-21):** every production path runs through `bws_run_traversal` —
base, `try_` (both its call sites), email/phone via `bws_resolve_field_values`, `{{table}}`'s row
and `use:title` traversals, `{{join}}` via the text arm — so a DEFAULT-ON predicate there needs no
threading. The one bypass (`table-tags.php` `use:key` calling `bws_pipeline_default_reader`
directly) consumes `meta_row` sources, which carry no entity id; S21 gives them nothing, so nothing
is missed. Recorded here so nobody re-derives the audit.

**Slice C is RETIRED — dissolved by redefinition, not deferred.** Its problem statement was
predicated on populated-counting:

- "Terminal limit counts non-empty reads, applied by the collector after reading" — dead; counting
  is field-independent, so the terminal step limit is an ORDINARY resolution bound, engine-applied
  per input like every other. S25's collector application and provenance-grouping machinery are
  never built; S18 is satisfied by the engine for free.
- List-mode slice-before-read — was C's defect; now CORRECT BY DEFINITION. `{{text}} limit(1)`
  reading an empty first source and rendering empty is the designed deterministic behaviour.
  F15.7 flips from disclosure row to positive pin.
- `try_`'s cross-entity bound (S42's "waits for C") — dissolves; `$slot_max` is the slot's
  tag-level analogue, tag-level is GLOBAL (S43 stands), current behaviour is correct.

The "list-mode tags are not fixed by this release" caveat dies with it; nothing is pending for
list-mode tags at all once B rides 1.18.0 (§S49).

Supersedes: S2 (one criterion per position), S18 (satisfied differently), S25, S42's C-half, S28's
C row. S41 (a limit applies to its own step), S43 (tag-level global holdover), S30, S36, S37 all
stand.

## §S49 — release regrouping: A-rework + B ship together as 1.18.0; one Upgrade Notice

S45's premise died twice: A is no longer notice-free (the term-route subtraction, §S47), and C no
longer exists. Its own consolidation logic therefore lands the other way: **slice B folds into
1.18.0.** The gate ships with the visible arm LIVE (posts: status + viewer capability; terms/users:
existence only, per S21/S35; site vacuous), "usable" reaches its final meaning ONCE, matrix rows
are written once, and ONE Upgrade Notice (300 chars) carries both subtractive consequences: the
term-route search removal and unpublished content no longer resolving. The S22-loosened position is
unchanged — the hook itself still does not ship; O8's per-source-vs-per-list firing stays deferred
with it.

**Version:** 1.18.0 stands (user, 2026-08-21). 1.17.1 was never released or deployed anywhere: its
CHANGELOG section FOLDS into 1.18.0 at release (entries merged into category order, header
deleted), and the version-stamp sweep (`Stable tag` currently claiming 1.17.1, plugin header, any
`@since 1.17.1`) moves to 1.18.0 — a RELEASE-time step on the blocker list, not done in the
rework. Both existing "Not in this release" CHANGELOG caveats die; the term-route change and the
visibility gate are **Changed** entries riding the notice, the email/phone branch fix and the
slot-limit binding stay **Fixed**.

**Release-time blocker list (2026-08-21, as it stands after the integration pass):** fold the
CHANGELOG 1.17.1 section into 1.18.0 (entries merged into category order, header deleted); fold the
`readme.txt` Upgrade Notice's `= 1.17.1 =` section the same way — a notice section for a version
nobody can have installed is a version-keyed message with no reader, and the 1.18.0 section is
already drafted at 263/300 chars; version-stamp sweep (`Stable tag`, plugin header, any
`@since 1.17.1`) — REMIND, never bump; user prose review of the new labels, help, advisory copy and
the notice; FW-87 row to the Closed/Retired ledger.

Supersedes: S45, S46 (model work no longer splits — ADR, CONTEXT vocabulary, and the
`tag-reference.md` reconcile all land in 1.18.0), S26's "all three ship as FIXES" (one is now
Changed-with-notice), S28's sequencing.

## §S50 — vocabulary: FOUR levels; existence is its own level; ADR 0007 revised in place

**resolvable → exists → visible → non-empty read.** "Resolved" keeps its recorded mechanical
meaning — L1 emitted a bound, says nothing about what the bound names (ADR 0002 untouched; the
declined sharpening stays declined). **exists** is the new viewer-INDEPENDENT level — the bound
names a live entity (post: `get_post` non-null — a trashed post EXISTS and fails visible; term:
valid `get_term`; site: vacuously true). **visible** narrows to the viewer-RELATIVE arm alone —
the viewer may read it (S19/S21). Non-empty read keeps no noun, as before. This split is what lets
each word be enforced atomically at the engine mount, with no "partially enforced visible" state.

`usable` KEEPS ITS NAME and redefines: resolvable × exists × visible — a property of sources. This
now ALIGNS with the shipped 1.17.0 Upgrade Notice's resolution-level "unusable"; the two-senses
avoid-note at `tag-reference.md` §List mode dissolves into one sense. "Usable" stays model
vocabulary, never user-facing; the author-facing word remains "results"-family, reframed per §S47.

**ADR 0007 is REVISED IN PLACE and RENAMED** (never published; user 2026-08-21): axis becomes "a
limit counts usable sources", file renamed to match, every citation repointed IN THE SAME EDIT
(`git grep` the old slug). S39's three-level row flips here; S40's homes stand with the renamed
ADR. CONTEXT.md I19 rewords to the source-predicate rule; the I19-vs-:264 on-purpose disagreement
(S46) ends because everything reconciles in one release.

## §S51 — test surface of the reversal

`collect-usable-test.php` re-cased (no-skip default, dormant predicate pinned, n-bounded source
read); `traversal-pipeline-test.php` gains injected-gate cases (harness stays pure — it supplies
its own predicate); `fold-chain-compile-test.php`, `control-order-test.php`,
`editor-filter-chain-test.js`, `slot-fold-repeater-test.js`, `try-slot-arms-test.php`,
`try-join-seam-test.php` re-run/adjust. Matrix: §F15 rewritten to the deterministic rule (F15.4's
headline case INVERTS — refs image now empty unless the first ref has it; F15.7 becomes a positive
pin), §F16 survives, NEW rows for exists + visible (draft/private/trashed fixtures → blueprint
bump, reseed, VISIBLE GB blocks per docs/testbed.md — mandatory). Row labels state expected output.


## §S52 — the integration pass, measured (2026-08-21)

Blueprint **v13**: `matrix-gate` page + `gate-draft` / `gate-private` / `gate-public` staff singles
(differing in ONE property), `gate_staff` naming them in that order, `via_draft` naming the draft
alone with a published `reports_to` behind it, and `stale_ref` as PLAIN meta holding an id created
and force-deleted at seed time. ACF's return format is `id` on purpose — the `object` format
resolves through a query that drops what the viewer cannot read, so an object-format fixture would
pass with the engine gate deleted; and ACF's formatter drops a dead id outright, which is why the
existence shape cannot be an ACF field at all.

MEASURED, all as stated: §F17 six rows × both viewer arms; §F15 nine rows (the four term-route ones
now empty, the subtraction the notice carries); §F16 six rows; the ten-row byte-identity spot-run,
which agrees with its pre-reversal measurement because every one of those rows reads a POPULATED
first source. `verify.php` renders both arms off one ambient post — a hand run measures whichever
viewer the operator happened to be, and WP-CLI's is anonymous unless `--user` says otherwise.

Two findings the pass produced, both now recorded at their sites: a fixture row LABEL may not
contain `{{` (it is a GB text block, so a literal tag in one is parsed — F15.7's label resolved to
nothing and GB took the label block down with it), and three sites stated a term walk that the
reversal deleted ("support carries no blurb, so the walk skips to sales"). The rendered value did
not move — WP returns terms by name, so Sales leads and is the term carrying a blurb — but the
reason moved from a search to an ORDER, and Support's absent blurb pins that instead now.

## §S53 — per-step limit labels (the second pass, same release)

A step's limit is labelled for what the step PRODUCES: `refs` → *Limit Posts Read*, `terms` →
*Limit Terms Read*, `entries` → *Limit Repeater Rows Read*; the generic *Limit items read* stays as
the fallback for a slug shipped without one, which no shipped slug is. The noun is AUTHORED on the
step record beside the row label, not derived from the record's `produces` kind — `meta_row` is the
engine's word for a repeater row, and deriving would put a naming decision in a map keyed on
internals. The control picks between the step's label and the generic one exactly as it picks
between the two help forms; it never composes. Wording is the user's direction verbatim and still
owes the prose review, along with the help text, the advisory and the Upgrade Notice.
