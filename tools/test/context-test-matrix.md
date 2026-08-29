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

## Author-kind detail

Author kind shipped 1.15.0 = `{{title}}`/`{{content}}` ONLY (the plan's
author-archive dispatch rows). text/permalink/image/datetime author analogs are
future work (FW-47) — deliberately unhandled, render empty not wrong. The PTA
query-context kind this section used to point at as "next" shipped 1.19.0
(C2/C12 above).

**Query-context detail (1.19.0):** title/content/text (`use:title`) carry
analogs; permalink/image/datetime and the other `try_` families render EMPTY on
the five contexts, never a leaked entity — verified 2026-08-29
(`{{try_datetime_single 1-key:event_date}}` on `/staff/` → empty, the
six-template fallthrough). A `try_text` slot and the equivalent `{{text}}`
agree (I6): `{{try_text 1-key:nosuchfield|2-use:title}}` on `/staff/` →
`Staff`, the key-first/canonical-title-second composition.

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

The 404 borrow's precedence is: site's own callback → GP's own default msgid → core's msgid (title) / empty (content), gated on `GENERATE_VERSION`. A borrow with nothing registered on the filter is indistinguishable from no borrow at all, so the blueprint proves each arm on a DIFFERENT filter (landed 1.19.0, in the same change as the borrow — the arm pairing the pre-ship version of this section demanded):

- **Override arm** — `schema.php` registers `bws_fixture_core_structures_404_title` on `generate_404_title` returning `Fixture 404 Title (filter)`; C5 asserts it.
- **Default arm** — `generate_404_text` deliberately has NO fixture callback, so C16 asserts GP's own default seed coming through the borrow.

The no-GP fallbacks (core's `Page not found` / empty) are unreachable on this GP testbed and stay pinned by the pure harness row in `traversal-pipeline-test.php` (the 404 seam row runs without `GENERATE_VERSION`).
