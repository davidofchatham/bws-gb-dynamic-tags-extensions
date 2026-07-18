# Context-detection test matrix (C-rows)

Integration rows for **context-aware base-tag resolution** —
`.claude/plans/context-aware-base-tags.md` (#19). Bare `{{title}}` /
`{{content}}` per WP context, rendered through the testbed
(`bin/wp.sh testbed bws render-tag '{{...}}' --url=...` — see CLAUDE.md
§Development). Fixture state: `core-structures` blueprint; the date-archive
rows additionally assume `sample-event` is categoryless + portal-visible
(enforced by `seed.php` — the portal-system anonymous query filter otherwise
empties the date archive to a 404).

**Staging pattern = FW-3 D7 (expected-fail → flip on ship).** Term kind SHIPPED
1.14.0 (C7 = regression control). Every other row records today's WRONG output
as the pinned baseline; when a context kind ships, flip its row's Expect to the
plan's dispatch table value and re-run.

**Visible-rows exception (CLAUDE.md §Development):** all C-rows need a
non-singular main query as ambient context (archive / search / 404 / home) —
page content cannot exist there. Same exception class as text T4. render-tag
only; no `blocks.php` builder. (A site-wide GP Element header on the testbed
theme is the eventual visible surface — deferred until a kind actually ships.)

Baselines captured 2026-07-18. `$post`-leak rows reconfirm probe finding #1
(`tools/debug/bws-ctx-probe-matrix.md`): first main-query row leaks into
`$post` on every results-bearing non-singular context.

## C-rows — bare `{{title}}`

| # | Context | URL | Current output (pinned baseline) | Ships as (plan dispatch) | Status |
|---|---|---|---|---|---|
| C1 | Date archive (month) | `/2026/07/` | `Sample Event` — first-row `$post` leak | formatted date span | EXPECTED-FAIL |
| C2 | Post type archive | `/staff/` | `Tom Associate` — first-row leak | PTA label `Staff` | EXPECTED-FAIL |
| C3 | Author archive | `/author/admin/` | empty (zero-result archive → no leak) | display name `admin` | EXPECTED-FAIL |
| C4 | Search (results) | `/?s=matrix` | `Sample Event` — first-hit leak (sharpest silent-wrong case) | "Results for: matrix" (format option) | EXPECTED-FAIL |
| C5 | 404 | `/no-such-page-xyz/` | empty (benign — `$post` null on zero results) | static fallback option | EXPECTED-FAIL |
| C6 | Latest-posts home | `/` (testbed: `show_on_front:posts`, nothing assigned) | `Sample Event` — first-row leak | site name / title-source option | EXPECTED-FAIL |
| C7 | Term archive (control) | `/department/sales/` | `Sales` | term name | **PASS (1.14.0)** |

## C-rows — bare `{{content}}`

| # | Context | URL | Current output (pinned baseline) | Ships as | Status |
|---|---|---|---|---|---|
| C11 | Date archive | `/2026/07/` | empty | empty / fallback option | (already target) |
| C12 | Post type archive | `/staff/` | **Tom Associate's full rendered GB page content** — worst leak in the set | empty / fallback option | EXPECTED-FAIL |
| C13 | Author archive | `/author/admin/` | empty | author bio (`description` user meta) | EXPECTED-FAIL |
| C14 | Search | `/?s=matrix` | empty | empty / fallback option | (already target) |
| C17 | Term archive (control) | `/department/sales/` | empty (term has no description — fixture gap, see below) | term description | **PASS-vacuous** |

## Fixture gaps / notes

- **C3/C13 payload thin:** single user `admin`, no bio, and its one post
  (`hello-world`) is portal-filtered out (categorized). A dedicated fixture
  author (display name + `description` meta + an authored visible post) makes
  the author rows non-vacuous — add to the blueprint when the author kind is
  scheduled.
- **C17 vacuous:** no `department` term carries a description. Seed one
  (e.g. on `department-sales`) to make the shipped term-kind content row
  assert something.
- **Posts-page state (P4a)** untestable in parallel with C6 — mutually
  exclusive site options. Toggle around the run if needed:
  `wp option set show_on_front page` + `page_for_posts <id>`, restore after.
- Payload shapes per context: captured 2026-07-18 via
  `tools/debug/ctx-capture.php` — results distilled into
  `context-aware-base-tags.md` §Detection signals; raw runs in
  `tools/debug/bws-ctx-probe-matrix.md` (P6/P7).
