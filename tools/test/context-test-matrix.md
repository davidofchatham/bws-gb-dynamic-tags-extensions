# Context-detection test matrix (C-rows)

Integration rows for **context-aware base-tag resolution** —
`.scratch/plans/context-aware-base-tags.md` (#19). Bare `{{title}}` /
`{{content}}` per WP context, rendered through the testbed
(`bin/wp.sh testbed bws render-tag '{{...}}' --url=...` — see `docs/testbed.md`). Fixture state: `core-structures` blueprint; the date-archive
rows additionally assume `sample-event` is categoryless + portal-visible
(enforced by `seed.php` — the portal-system anonymous query filter otherwise
empties the date archive to a 404).

**Staging pattern = FW-3 D7 (expected-fail → flip on ship).** Term kind SHIPPED
1.14.0 (C7 = regression control). Every other row records today's WRONG output
as the pinned baseline; when a context kind ships, flip its row's Expect to the
plan's dispatch table value and re-run.

**VISIBLE SINCE 2026-08-29, and the exception is retired.** All C-rows need a non-singular main query (archive / search / 404 / home), where page content cannot exist — so these rows could not be `blocks.php` rows and were `render-tag`-only. The surface this note called eventual is now built: a GP block element (`elements` in the blueprint manifest, content in `bws_fixture_element_content_context_header()`) hooked at `generate_after_header` and scoped by GP's display conditions to blog + archive + search + 404. It carries C-T1 (`{{title}}`) and C-C1 (`{{content}}`), and the query-context page snapshots pin what they render. Scoped deliberately AWAY from `general:singular`, so the element never appears on the ten singular snapshot pages.

**Prefer the front end over `render-tag` for these rows.** The two disagree, and it is not academic: on `/`, `render-tag` reported `BWSUT Target Post` while a real request rendered `Home Lead Post`. `render-tag` runs no `the_content`, no real block render and no `wptexturize`, which is exactly the path a leak surfaces through.

Baselines captured 2026-07-18, **re-measured on the front end 2026-08-29** when the element made these rows visible; four had moved. `$post`-leak rows reconfirm probe finding #1 (`tools/debug/bws-ctx-probe-matrix.md`): the first main-query row leaks into `$post` on every results-bearing non-singular context.

**A leaked value is not a stable expectation, and three of these are other plugins' fixtures.** This is a shared testbed, so which post leaks depends on what else is seeded — `/staff/` and `/2026/07/` both currently leak posts this blueprint does not own. That is why the rows record the leak's SHAPE as the assertion and the literal string only as of a date; when a kind ships, the expectation becomes a value the context itself determines and stops depending on the corpus at all. `/` is the exception by construction: `post-home-lead` exists so that row leaks a post we own.

## C-rows — bare `{{title}}`

| # | Context | URL | Current output (pinned baseline) | Ships as (plan dispatch) | Status |
|---|---|---|---|---|---|
| C1 | Date archive (month) | `/2026/07/` | `VPost: Open (all-users)` — first-row `$post` leak (2026-08-29; another plugin's fixture) | formatted date span | EXPECTED-FAIL |
| C2 | Post type archive | `/staff/` | `Grace Published` — first-row leak (2026-08-29; another plugin's fixture) | PTA label `Staff` | EXPECTED-FAIL |
| C3 | Author archive | `/author/fixture-author/` | `Fixture Author` | display name | **PASS (1.15.0)** |
| C4 | Search (results) | `/?s=matrix` | `Matrix: Query Loops` — first-hit leak (2026-08-29; sharpest silent-wrong case) | "Results for: matrix" (format option) | EXPECTED-FAIL |
| C5 | 404 | `/no-such-page-xyz/` | empty (benign — `$post` null on zero results) | static fallback option | EXPECTED-FAIL |
| C6 | Latest-posts home | `/` (testbed: `show_on_front:posts`, nothing assigned) | `Home Lead Post` — first-row leak; ours by construction (`post-home-lead`), so this row does not move when another plugin reseeds | site name / title-source option | EXPECTED-FAIL |
| C7 | Term archive (control) | `/department/sales/` | `Sales` | term name | **PASS (1.14.0)** |

## C-rows — bare `{{content}}`

| # | Context | URL | Current output (pinned baseline) | Ships as | Status |
|---|---|---|---|---|---|
| C11 | Date archive | `/2026/07/` | empty | empty / fallback option | (already target) |
| C12 | Post type archive | `/staff/` | **Tom Associate's full rendered GB page content** — worst leak in the set | empty / fallback option | EXPECTED-FAIL |
| C13 | Author archive | `/author/fixture-author/` | author bio (`description` user meta) | author bio | **PASS (1.15.0)** |
| C14 | Search | `/?s=matrix` | empty | empty / fallback option | (already target) |
| C15 | Latest-posts home | `/` | empty — **measured, and not what was predicted**; see below | empty | (already target) |
| C16 | 404 | `/no-such-page-xyz/` | empty | GP `generate_404_text` where present, else empty | (already target today) |
| C17 | Term archive (control) | `/department/sales/` | Sales term description | term description | **PASS (1.15.0 fixture)** |

## Author-kind detail (C2/C12 remain expected-fail)

Author kind shipped 1.15.0 = `{{title}}`/`{{content}}` ONLY (the plan's
author-archive dispatch rows). text/permalink/image/datetime author analogs are
future work — deliberately unhandled, render empty not wrong. PTA (C2/C12) is a
separate query-context kind, still expected-fail; its `{{content}}` leak (C12,
full page markup) is the worst in the set and the strongest argument for
shipping the PTA guard next.

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


## The `{{content}}` leak does not reproduce on ANY query context — measured 2026-08-29

**This is the finding of the pass, and it is not what the rows above predicted.** With the element rendering a bare `{{content}}` on a real front-end request, the output is EMPTY on the post-type archive, the date archive, search, 404 and the latest-posts home alike. `render-tag` agrees on every one of them.

That contradicts **C12**, which has recorded since 2026-07-18 that PTA `{{content}}` renders "Tom Associate's full rendered GB page content — worst leak in the set", and calls it "the strongest argument for shipping the PTA guard next". **C12's baseline no longer reproduces on either surface.** It is left standing rather than rewritten: a pinned baseline that stopped reproducing is evidence about when something changed, and deciding which way it moves is not this file's call.

The `{{title}}` half is unaffected and every title row still leaks exactly as recorded.

**What it means for scope, if it holds:** the `{{content}}` half of the query-context work is not a leak to fix but an analog to add — the post type's own description on a PTA, and nothing anywhere else, because empty is already the target on the other four. The likely cause is `e55602e` ("deterministic source selection — usable is a property of sources", ADR 0007, 1.18.0), which changed what a source has to satisfy before it is read at all. Confirm the mechanism before relying on this; the measurement is solid, the attribution is a lead.

**C15 is therefore vacuous as an assertion** — current and target are both empty — and is recorded for coverage rather than as a pin. The prediction it was written to hold (that a featured-post home page would break) did not survive contact: nothing leaks there to break.

`post-home-lead` still earns its place, on the OTHER row. C6's title leak was another plugin's post and moved whenever anything else was reseeded; it is now a post this blueprint owns.

## C5 — the 404 title override is not split yet

The shipped 404 title borrows the site's own `generate_404_title` before falling back to core's `Page not found`. Proving the override path needs a callback registered on the fixture site, and proving the DEFAULT path needs it absent — two rows, not one.

Deliberately **not** added ahead of the borrow. A fixture callback with no consumer is inert and unverifiable, which is the same unproven-guard shape this corpus keeps getting bitten by. It lands in the change that builds the borrow, where both arms can be measured the day they are written.
