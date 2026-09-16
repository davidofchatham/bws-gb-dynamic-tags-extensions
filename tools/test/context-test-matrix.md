# Context-detection test matrix (C-rows)

Integration rows for **context-aware base-tag resolution** —
`.scratch/plans/context-aware-base-tags.md` (#19). Bare `{{title}}` /
`{{content}}` per WP context, rendered through the testbed
(`bin/wp.sh testbed bws render-tag '{{...}}' --url=...` — see `docs/testbed.md`). Fixture state: `core-structures` blueprint; the date-archive
rows additionally assume `sample-event` is categoryless + portal-visible
(enforced by `seed.php` — the portal-system anonymous query filter otherwise
empties the date archive to a 404).

**Staging pattern = FW-3 D7 (expected-fail → flip on ship).** Term kind SHIPPED 1.14.0, author kind 1.15.0, and the five QUERY-CONTEXT kinds (date / PTA / search / 404 / latest-home) SHIPPED 1.19.0 — every row below is flipped to its dispatch value and re-measured (2026-08-29, render-tag + front end). The pre-1.19.0 leak baselines each row used to pin are kept in the "Leaked (pre-1.19.0)" column as the regression direction: a row showing its leak value again means the factory's query-context branch stopped firing.

**`?s=` does not survive `render-tag --url`** (measured 2026-08-29: `--url=/?s=searchpin` resolved latest-home, not search — the query string is dropped). C4/C14 are front-end rows only; every other row agrees across both instruments.

**VISIBLE SINCE 2026-08-29, and the exception is retired.** All C-rows need a non-singular main query (archive / search / 404 / home), where page content cannot exist — so these rows could not be `blocks.php` rows and were `render-tag`-only. The surface this note called eventual is now built: a GP block element (`elements` in the blueprint manifest, content in `bws_fixture_element_content_context_header()`) hooked at `generate_after_header` and scoped by GP's display conditions to blog + archive + search + 404. It carries C-T1 (`{{title}}`) and C-C1 (`{{content}}`), and the query-context page snapshots pin what they render. Scoped deliberately AWAY from `general:singular`, so the element never appears on the ten singular snapshot pages.

**Prefer the front end over `render-tag` for these rows.** The two disagree, and it is not academic: on `/`, `render-tag` reported `BWSUT Target Post` while a real request rendered `Home Lead Post`. What `render-tag` cannot reach is stated once, by the instrument that covers the gap — [`tools/test/page-snapshots.php`](page-snapshots.php)'s own header — and that is the path a leak surfaces through.

Baselines captured 2026-07-18, **re-measured on the front end 2026-08-29** when the element made these rows visible; four had moved. `$post`-leak rows reconfirm probe finding #1 (`tools/debug/bws-ctx-probe-matrix.md`): the first main-query row leaks into `$post` on every results-bearing non-singular context.

**A leaked value is not a stable expectation, and same-second ties made it worse than unstable.** This is a shared testbed, so which post leaks depends on what else is seeded — and because most fixtures here are seeded same-second, the date/relevance ties broke DIFFERENTLY per query plan: verify.php caught the host and the container disagreeing about the same page. Three moves pinned the leaders (2026-08-29): `posts_per_page = 1` (blueprint wp_options — no listing renders a tie anywhere), `sample-event` re-dated to lead July, and the search term changed to a token exactly one post matches. `/`, `/2026/07/` and `/?s=searchpin` now leak posts this blueprint owns; `/staff/` still leads with another plugin's post (`Grace Published`), so C2's literal string stays dated. When a kind ships, the expectation becomes a value the context itself determines and stops depending on the corpus at all.

## C-rows — bare `{{title}}`

| # | Context | URL | Expect (shipped dispatch) | Leaked (pre-1.19.0) | Status |
|---|---|---|---|---|---|
| C1 | Date archive (month) | `/2026/07/` | `July 2026` (core's month-archive format `F Y`; a day archive takes `get_the_date()`, a year archive `Y`) | `Sample Event` — first-row `$post` leak | **PASS (1.19.0)** |
| C2 | Post type archive | `/staff/` | PTA label `Staff` (unprefixed `post_type_archive_title`) | `Grace Published` | **PASS (1.19.0)** |
| C3 | Author archive | `/author/fixture-author/` | `Fixture Author` (display name) | — | **PASS (1.15.0)** |
| C4 | Search (results) | `/?s=searchpin` | `Search Results for &#8220;searchpin&#8221;` (core msgid; entities render curly on the page, `wptexturize` does not double-transform — eyeballed on the front end 2026-08-29). **Front-end row only** — `render-tag` drops `?s=` (header note). URL history: changed from `?s=matrix` 2026-08-29 so the term matches exactly one post this blueprint owns | `Home Lead Post` — first-hit leak (was the sharpest silent-wrong case) | **PASS (1.19.0)** |
| C5 | 404 (override arm) | `/no-such-page-xyz/` | `Fixture 404 Title (filter)` — the site's own `generate_404_title` callback wins (the blueprint registers one for exactly this row; §C5 below has the arm pairing) | empty (benign) | **PASS (1.19.0)** |
| C6 | Latest-posts home | `/` (testbed: `show_on_front:posts`, nothing assigned) | `BWS Testbed` (site name, `get_bloginfo('name')`) | `Home Lead Post` — first-row leak | **PASS (1.19.0)** |
| C7 | Term archive (control) | `/department/sales/` | `Sales` (term name) | — | **PASS (1.14.0)** |

## C-rows — bare `{{content}}`

| # | Context | URL | Expect (shipped dispatch) | Leaked (pre-1.19.0) | Status |
|---|---|---|---|---|---|
| C11 | Date archive | `/2026/07/` | empty | empty (coincidentally) | **PASS (1.19.0)** |
| C12 | Post type archive | `/staff/` | the staff type's description, `<p>`-wrapped (core's own `wpautop` on the `get_the_post_type_description` filter): `The staff directory. This description is the post type archive content analog on the staff archive.` — the blueprint gives the CPT a description for exactly this row, else the read is indistinguishable from no read | a leading post's **full rendered GB page content** — worst leak in the set | **PASS (1.19.0)** |
| C13 | Author archive | `/author/fixture-author/` | author bio (`description` user meta) | — | **PASS (1.15.0)** |
| C14 | Search | `/?s=searchpin` | empty. **Front-end row only** (header note) | leaked first hit's body | **PASS (1.19.0)** |
| C15 | Latest-posts home | `/` | empty — the one deliberate break: anyone who built a featured-post home on this leak loses it (named in the CHANGELOG) | the leaked first post's **whole rendered body** | **PASS (1.19.0)** |
| C16 | 404 (default arm) | `/no-such-page-xyz/` | GP's own default through the borrow: `It looks like nothing was found at this location. Maybe try searching?` (no fixture callback on `generate_404_text` — §C5 below). Without GP: empty | empty | **PASS (1.19.0)** |
| C17 | Term archive (control) | `/department/sales/` | Sales term description | — | **PASS (1.15.0 fixture)** |

## C-rows — content/datetime WITH a configured `fallback` (regression fix, 1.19.1)

`bws_base_ambient_analog()`'s `query_context` case claimed `content`/`datetime_single`/`datetime_range` unconditionally on all five query-context kinds, and its reader has no analog for most (tag, kind) pairs — so a configured `fallback` never got a chance to run, and the tag rendered bare empty instead. Fixed by falling through to the post route (content) / widening the outer fallback gate (datetime) so each tag's own self-contained fallback mechanism fires. `{{image}}`'s twin of this fix is C-I1 below — render-tag-only, since its fallback needs a seeded Media Library id these two don't. Author archive is not a regression for content OR datetime: content's `user`-kind claim already answers with the real bio (C13, unaffected); datetime is not claimed by the `user` kind at all (only title/content/text are, per `bws_base_ambient_analog()`'s PHPDoc carve-out), so it was already reaching the post route's self-contained fallback before this fix.

| # | Context | URL | Before (empty, the bug) | After (this session) | Why |
|---|---|---|---|---|---|
| C-C2.1 | Date archive | `/2026/07/` | empty | `No description available` | no content analog on this sub-kind |
| C-C2.2 | Post type archive | `/staff/` | *(not a regression)* | the staff type's description (C12, unchanged) | a real, non-empty analog — the fallback never gets a turn |
| C-C2.3 | Author archive | `/author/fixture-author/` | *(not a regression)* | the author bio (C13, unchanged) | real analog, same reasoning |
| C-C2.4 | Search | `/?s=searchpin` | empty | `No description available` | no analog. **Front-end row only** (header note) |
| C-C2.5 | Latest-posts home | `/` | empty | `No description available` | no analog |
| C-C2.6 | 404 | `/no-such-page-xyz/` | *(not a regression)* | GP's default borrow text (C16, unchanged) | a real analog on this GP testbed |
| C-DT1/C-DT2.1 | Date archive | `/2026/07/` | empty | `TBA` | datetime has no analog on ANY query-context sub-kind |
| C-DT1/C-DT2.2 | Post type archive | `/staff/` | empty | `TBA` | same |
| C-DT1/C-DT2.3 | Author archive | `/author/fixture-author/` | `TBA` (already correct) | `TBA` | not claimed by the `user` kind at all — always reached the post route |
| C-DT1/C-DT2.4 | Search | `/?s=searchpin` | empty | `TBA` | no analog. **Front-end row only** |
| C-DT1/C-DT2.5 | Latest-posts home | `/` | empty | `TBA` | no analog |
| C-DT1/C-DT2.6 | 404 | `/no-such-page-xyz/` | empty | `TBA` | no analog (datetime has no 404 borrow, unlike content) |
| C-I1 | Date archive | `/2026/07/` | empty | the fallback IMAGE renders | **`render-tag` only, exception stated per the visible-rows rule** — `fallback` is a Media Library id assigned at seed time, so no static string in `blocks.php` can name it, same reasoning as F11b.3. Pass the seeded `fixture-photo` attachment's id (`wp post list --post_type=attachment`); repeat against `/staff/`, `/?s=searchpin`, `/`, `/no-such-page-xyz/` — image has no analog on any of the five, so all five were broken and all five are fixed the same way |

## C-PROD rows — a product OUTSIDE any loop (FW-100, 1.20.0)

**Why these exist at all, since they pass and always did.** FW-100's fix was about a product INSIDE a loop; the ambient single-product surface was expected to need no code, because nothing in the read path was believed to treat one post type differently from another (what the source gate actually decides on is `bws_source_gate()`'s own PHPDoc, and is not restated here). That is an expectation derived from the code's SHAPE, which this repo does not accept as evidence for a claim about behavior — so the belief was replaced with a measurement, and these are it. They are also the rows that would catch a future post-type narrowing added for some other reason.

**Where they live.** On the product's OWN page (`/product/adjustable-desk-riser/`), inside its description, which WooCommerce renders through `the_content`. A single product carries them and the other two do not: the ambient route does not vary per product, and two more would be two more snapshot files pinning the same answer. `{{content}}` is deliberately absent from the set — it would read the very field these blocks live in.

**These rows also settle what a product custom field IS.** C-PROD.3 reads plain postmeta by key off a product, which is the ordinary field route with nothing product-shaped in it. What that does NOT cover is the field PICKER, which does not offer protected or unregistered postmeta — a boundary tracked at FW-13, not a defect in this row.

| # | Tag (on `/product/adjustable-desk-riser/`) | Expected | Status |
|---|---|---|---|
| C-PROD.1 | `{{title}}` | `Adjustable Desk Riser` — the ambient single route, no loop item present | **PASS (measured 2026-09-14)** |
| C-PROD.2 | `{{permalink}}` | `https://testbed.test/product/adjustable-desk-riser/` | **PASS (measured 2026-09-14)** |
| C-PROD.3 | `{{text key:product_note}}` | `Ships flat-packed in two cartons.` — a product custom field is ordinary postmeta, read by the ordinary route | **PASS (measured 2026-09-14)** |

**Two ambient surfaces are NOT measured, and the reason is not that they were forgotten.** The shop archive and the product-category archive ride the existing post-type-archive and term-archive rows above (C2/C12, C7/C17), which already cover those contexts for a custom post type, and no post-type gate exists for a product to trip. The residual risk there is WooCommerce's archive TEMPLATING — whether a block renders on a Woo-templated archive at all is a theme question, not a question about our resolution — and genuinely measuring it is a fixture-theme task with its own item rather than a row hidden in this table.

**One piece of fixture state is load-bearing for this group and invisible from it:** WooCommerce's coming-soon mode is off in the blueprint's `wp_options`. It defaults ON for a store whose setup wizard never ran, and it serves every store URL as a launch placeholder to logged-out visitors — which `/matrix-products/` cannot see, because that is an ordinary page. A product loop can therefore pass while the product's own page shows no product at all, which is exactly what happened here before the option was written down.

## C-TERM / CT rows — the ambient-term guard (1.20.0)

An argless `{{term_*}}` tag resolved through `TaxonomyTerm::resolve_id()`, which handed back `get_queried_object_id()` without checking what kind of thing WP had queried. Post, term and user ids share one number space, so wherever the queried object's id collided with a real term the tag rendered that term's data as though it were the answer. 1.20.0 gated that arm on the queried object's type; 1.21.0 removed the arm along with the family that was its only caller, so what these rows measure is no longer a guard but the resolution that outlived it — `TaxonomyTerm::resolve_id()`'s PHPDoc states the rule. **The `term_*` spelling below is retired; rewriting these rows against `{{text src:term,N}}` vs ambient is the remaining FW-129 step for this section.**

**The rows come in pairs on purpose.** The guard's correct behaviour is "empty" on six of seven contexts, and a set of all-empty rows cannot distinguish a working guard from a tag that stopped resolving anywhere. Every ambient row therefore has a pinned-term twin that must keep rendering.

| # | Context | URL | Before 1.20.0 | Expect | Surface |
|---|---|---|---|---|---|
| C-TERM1.1 | Term archive (the positive arm) | `/department/sales/` | `Sales` | `Sales` — unchanged | C-element, `ctx-term` baseline |
| C-TERM1.2 | Author archive | `/author/fixture-author/` | `All Users` — the `portal_visibility` term carrying user 2's id | empty | C-element, `ctx-author` |
| C-TERM1.3 | Post type archive | `/staff/` | empty (nothing queried an id) | empty | C-element, `ctx-pta-staff` |
| C-TERM1.4 | Date archive | `/2026/07/` | empty | empty | C-element, `ctx-date-202607` |
| C-TERM1.5 | Search | `/?s=searchpin` | empty | empty | C-element, `ctx-search` |
| C-TERM1.6 | 404 | `/no-such-page-xyz/` | empty | empty | C-element, `ctx-404` |
| C-TERM1.7 | Latest-posts home | `/` | empty | empty | C-element, `ctx-home-latest` |
| C-TERM2 | all seven above | — | `Support` | `Support` — a tag naming its own term is not an ambient read and the guard must not touch it | C-element, every context baseline |
| CT-A | Singular page | `/matrix-post-meta/` | `Priority` — the `mc_flag` term carrying this page's own id | empty | page content |
| CT-B | Singular page | `/matrix-post-meta/` | `Support` | `Support` — non-vacuity for CT-A | page content |
| CT-C | Singular page | `/matrix-post-meta/` | empty | `(987) 333-4444` — `tax` reaches the first-term-of-this-post tier at last | page content |
| QL1.5 | Term query loop | `/matrix-loops/` | the loop's term name | the loop's term name — unchanged | page content, inside QL1's loop |

**QL1.5 is the row that would have caught the mistake this fix made on its first cut.** The guard's first version refused every arm of GB's `get_id()`, including the `generateblocks_dynamic_tag_id` filter a query loop uses to hand down its row's term. Nothing on any fixture page saw it, because no `term_*` tag stood inside a loop; the page snapshots were green. It reads the same entity QL1.1 does by the other route — QL1.1 is a base tag through `bws_resolve_base_source()`, QL1.5 is the `term_` family through `TaxonomyTerm::resolve_id()` — and only one of those routes has a guard on it.

**CT-A does not depend on the collision it names.** Empty is the right answer on a singular page whether or not some term carries this page's id; the `mc_flag:Priority` coincidence is what made the OLD behaviour visibly wrong, and it is recorded as history, not relied on. Do not pin the page id to "strengthen" the row.

**`/department/sales/` joins the context pages** (`ctx-term`) so C-TERM1.1 has a captured baseline. It asserts `body_class` `tax-department` rather than the generic `archive`: the guard's entire subject is which KIND of archive a page is, so the row must fail if the page degrades into a different one.

## §C-CONV — a `term_*` tag beside the base tag it converts to (FW-39)

The C-TERM rows above measure ONE tag against its own past. These measure a tag against its REPLACEMENT: the migration rewrites a `term_*` tag into a base tag, and what has to be shown is not that either side is right but which way every difference between them runs. A single row cannot show a direction, so every row here is half of a pair and is useless read alone.

**The two arms have different answers, and the rows are grouped by which.** On the ARGLESS arm every difference runs empty→value, never value→anything-else. On the ENTITY-NAMING arm (C-CONV10..14, ticket 08) there is no difference at all — a tag that named its own term still names it — with ONE labelled exception: a named term that has been deleted, which runs value→empty because the old family falls through to the ambient term where the new wire refuses.

The ARGLESS rewrite is not output-neutral, which is why this section exists at all: a `term_*` tag addresses a term and nothing else, a base tag addresses whatever the page is about (the capability difference recorded in [`docs/design-history/term-family-kind-lock.md`](../../docs/design-history/term-family-kind-lock.md)), and off a term page the first renders nothing where the second renders the page. Whether a migration may do that is FW-39's decision, recorded with the ship; this table is the measurement it rests on. The entity-naming rewrite is a different question — an `id` was never an ambient read, so that difference does not reach it — and C-CONV10/11 are what says the answer is "no change at all".

Measured 2026-09-10 via `bws render-tag --porcelain` on all seven contexts; the entity-naming rows (C-CONV10..14) 2026-09-11 the same way. Search is the one context `render-tag` cannot reach (header note) and is front-end only.

**The EDITOR half of C-CONV13/14, measured the same day** via `bws render-tag --preview --porcelain`, because a row that renders nothing has to be distinguishable from a row that is broken and only the editor does that: `{{text src:term,999999|use:title}}` previews as `[Title from term 999999 (missing)]`, while the live `{{text src:term,<support>|use:title}}` previews as its resolved value (`Support`) and claims no bracket at all. `preview-label-test.php` owns the namer's own rules; this records that the wire the MIGRATION emits is wire that namer reads.

| # | Arm | Before (`term_*`) | After (base) | Term archive `/department/sales/` | The other six |
|---|---|---|---|---|---|
| C-CONV1 | bare title read | `{{term_text use:title}}` | `{{text use:title}}` | `Sales` / `Sales` — EQUAL | empty / this context's own heading — **empty→value** |
| C-CONV2/3 | bare collapsing template | `{{term_content}}` | `{{content}}` | the Sales term description, both sides | empty / the PTA description, author bio or 404 borrow — **empty→value**; on date and latest-home both are empty |
| — | chain-only collapsing template | `{{term_permalink}}` | `{{permalink}}` | the term archive URL, both sides | empty / the singular page's own URL — **empty→value**; empty on both everywhere else. `render-tag` rows only, no fixture pair (its value is a URL that moves with every reseed) |
| C-CONV4/5 | CHAINED, argless | `{{term_text src:ref\|ref:dept_lead\|use:title}}` | `{{text src:refs,dept_lead,limit(1)\|use:title}}` | `Tom Associate`, both sides | empty on both sides, every context — **IDENTICAL in both directions** |
| — | chained, two steps | `{{term_text src:ref\|ref:dept_lead\|srcTermIn:portal_visibility\|use:title}}` | `{{text src:refs,dept_lead;terms,portal_visibility,limit(1)\|use:title}}` | `All Users`, both sides | `render-tag` row only; the visible pair is C-CONV4/5, whose one step is the shape a stored tag actually has |
| C-CONV6/7 | SKIPPED: `tax`, no `id` | `{{term_text tax:department\|key:phone}}` | *(not converted)* | `(987) 333-4444` / empty — **value→empty**, which is why it is skipped | empty on both sides on all six; `(987) 333-4444` on both on the singular `/matrix-post-meta/`, which is where the two agree and is not a context page |
| C-CONV8/9 | converts: INERT `srcTermIn` | `{{term_text srcTermIn:department\|key:phone}}` | `{{text key:phone}}` | `(987) 333-4444` on both sides — **EQUAL**, the key is dropped with the source axis it belonged to | empty on both sides on all six. On the singular `/matrix-post-meta/` both are empty too, which is the row's second half: the step the converter used to fold in renders `(987) 333-4444, (987) 111-2222` there, a value the stored tag has never produced on any context |
| — | SKIPPED: bare `src:term` | `{{term_text src:term\|use:title}}` | *(not converted)* | `Sales` / empty — **value→empty**. The base tag REFUSES an argless declaring root (D8), the `term_*` family falls through to its own ambient read | empty on both |
| — | converts: `src:site` | `{{term_text src:site\|use:title}}` | `{{text src:site\|use:title}}` | empty / `BWS Testbed` — **empty→value**, so it converts through the shared mapping like every other family's | same, every context |
| C-CONV10/11 | NAMES A TERM (FW-39 ticket 08) | `{{term_text id:<support>\|use:title}}` | `{{text src:term,<support>\|use:title}}` | `Support`, both sides — **EQUAL** | `Support` on both sides on all six. The selected term is deliberately NOT the archive's own term: a row naming Sales would pass whether the selection resolved or not |
| C-CONV12 | …the same term id carrying `tax` | `{{term_text id:<support>\|tax:department\|use:title}}` | C-CONV11, `tax` GONE | `Support` — **EQUAL** | `Support` everywhere. A term id is globally unique, so the key adds nothing to an entity-naming read and is dropped; with no `id` beside it the same key is C-CONV6, skipped whole |
| C-CONV13/14 | DEAD reference | `{{term_text id:999999\|use:title}}` | `{{text src:term,999999\|use:title}}` | `Sales` / empty — **value→empty** | empty on both sides on all six. The ONE entity-naming pair that is not output-neutral: the old family falls through to the ambient term and shows whichever term the page is about, and the converted tag renders nothing. Decided, not overlooked — a broken reference reads as broken (`(missing)` in the editor) instead of silently borrowing the page's term |
| QL1.5/QL1.6 | TERM QUERY LOOP | `{{term_text use:title}}` | `{{text use:title}}` | — | the loop row's term name, both sides, `/matrix-loops/` — see below |

**The `src:site` row is the one D33 predicted would be unconvertible, and the measurement refuted it.** A modifier tag returns EMPTY under `src:site` by an explicit guard (`register_modifier()`'s callback, #37) — for every family, not just this one — so the arm is empty→value, inside the exemption, and skipping it here would be a rule that applies to one family for no reason the code states. The shared mapping has made exactly this rewrite since 1.17.0 (`modifier-base-migration-test.php` §V1.6).

**QL1.6 is the convert-side twin of QL1.5, and it is a fixture row because it cannot be anything else.** A term query loop hands its row's term down through GB's `generateblocks_dynamic_tag_id` filter, and `render-tag` cannot fake a term loop at any flag combination — so the two routes to one term (QL1.5 through `TaxonomyTerm::resolve_id()`, QL1.6 through `bws_resolve_base_source()`'s loop-item classification) are comparable on the page and nowhere else. That is the same gap QL1.5 itself was added to fill, one guard over.

**Vacuity, and how it was ruled out.** An earlier sweep compared `{{term_text use:name}}` on both sides; `name` is not a registered `use` value (`bws_get_text_field_options()` offers `key` and `title`, whose LABEL reads "Title/Name"), so both sides rendered empty on every context and the table proved nothing. Every row above renders a value on at least one side.

## Author-kind detail

Author kind shipped 1.15.0 = `{{title}}`/`{{content}}` ONLY (the plan's author-archive dispatch rows). text/permalink/image/datetime author analogs are future work (FW-47) — deliberately unhandled, render empty not wrong. The PTA query-context kind this section used to point at as "next" shipped 1.19.0 (C2/C12 above).

**Query-context detail (1.19.0):** title/content/text (`use:title`) carry analogs; permalink/image/datetime and the other `try_` families render EMPTY on the five contexts, never a leaked entity — verified 2026-08-29 (`{{try_datetime_single 1-key:event_date}}` on `/staff/` → empty, the six-template fallthrough). A `try_text` slot and the equivalent `{{text}}` agree (I6): `{{try_text 1-key:nosuchfield|2-use:title}}` on `/staff/` → `Staff`, the key-first/canonical-title-second composition.

Precedence verified on the author archive: `linkTo:permalink` wraps the author
URL (`get_author_posts_url`); `src:site` still wins (author does not hijack);
the `--loop-item` post wins over author ambient. Same guard spine as the term kind.

## Fixture gaps / notes

- **Posts-page state (P4a)** untestable in parallel with C6 — mutually
  exclusive site options. Toggle around the run if needed:
  `wp option set show_on_front page` + `page_for_posts <id>`, restore after.
- Payload shapes per context: captured 2026-07-18 via
  `tools/debug/ctx-capture.php` — results distilled into
  `context-aware-base-tags.md` §Detection signals; raw runs in
  `tools/debug/bws-ctx-probe-matrix.md` (P6/P7).


## The `{{content}}` leak — declared dead 2026-08-29 and corrected the same day

**First measurement said the leak was gone; it was masked, twice over, and the correction is the useful record here.** With the element rendering a bare `{{content}}` on real requests, output was empty on all five query contexts, `render-tag` agreed, and the finding was written up as "not a leak to fix but an analog to add", with 1.18.0's source gate (`e55602e`, ADR 0007) as the suspected cause. A portal-system A/B (deactivate, measure, reactivate — title as the sensitivity control) exonerated that plugin, and then the actual mechanism fell out:

1. **Every post leading any archive carried a 0-byte body.** `Grace Published`, `VPost: Open (all-users)`, `BWSUT Target Post` — other plugins' fixtures, all empty, all sorting ahead of the July corpus. C12's July baseline leaked `Tom Associate` (6,861 bytes) because *he* led `/staff/` then. The leak reads whatever `$post` carries; when that post has no body, the leak renders nothing and looks fixed.
2. **The one content-bearing post this blueprint added was masking itself.** `post-home-lead`'s body text named the content tag in braces; GB parsed it as a real dynamic tag, the self-reference resolved empty, and GB hid the whole block — so the post built to be non-empty rendered an empty body everywhere, its own singular page included.

With the fixture sentence reworded, the decisive test ran: latest-home `{{title}}` → `Home Lead Post`, latest-home `{{content}}` → **its full rendered body**, on `render-tag` and on the real page alike. **The leak is alive, `e55602e` is exonerated, and no code change ever occurred** — C12's mechanism was right all along; only the leaked post moved.

**C12 itself is still not directly reproducible today** — `/staff/` currently leads with a 0-byte post — but the mechanism it records is proven by C15, which pins the same leak on a post this blueprint owns. That is the durable lesson: **a leak row must lead with a post the blueprint controls, or its baseline records the sort order of other people's fixtures.**

Two standing cautions this episode adds:

- **A "leak fixed" reading needs a content-bearing leader before it is believed.** Empty output from a leak site is compatible with "fixed", "masked by an empty leader", and "masked by a self-hiding fixture" — and this pass hit all three readings in one afternoon.
- **No literal tag syntax in fixture body text.** GB parses it wherever it renders, and a self-referencing tag hides the block that carries it.

## C5 — the 404 borrow's two arms, split across the two filters

The 404 borrow's precedence is: site's own callback → GP's own default msgid → core's msgid (title) / empty (content), gated on `GENERATE_VERSION`. A borrow with nothing registered on the filter is indistinguishable from no borrow at all, so the blueprint proves each arm on a DIFFERENT filter (landed 1.19.0, in the same change as the borrow — the arm pairing the pre-ship version of this section demanded). NB the "exception is retired" note at the top of this file is about the C-rows' own visibility (the C-element); T9/F19's render-tag-only rows in the text/fold matrices still ride T4/T8's archive-context exception, which stands.

- **Override arm** — `schema.php` registers `bws_fixture_core_structures_404_title` on `generate_404_title` returning `Fixture 404 Title (filter)`; C5 asserts it.
- **Default arm** — `generate_404_text` deliberately has NO fixture callback, so C16 asserts GP's own default seed coming through the borrow.

The no-GP fallbacks (core's `Page not found` / empty) are unreachable on this GP testbed and stay pinned by the pure harness row in `traversal-pipeline-test.php` (the 404 seam row runs without `GENERATE_VERSION`).
