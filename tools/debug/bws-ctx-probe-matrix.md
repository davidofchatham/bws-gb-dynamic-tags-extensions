# Context probe — visit matrix (fail-fast order)

Companion to `bws-ctx-probe.php`. Feeds the base-entity factory design
(`.claude/plans/traversal-pipeline.md` §Base Resolution) and the #19 context
taxonomy (`.claude/plans/context-aware-base-tags.md` §Context Taxonomy).

**Ordering principle: highest architectural risk first.** Each row lists the
FAIL condition — the observation that invalidates a design assumption. An early
fail reshapes the factory before any time is spent on low-signal rows, so stop
and reassess at the first FAIL rather than completing the sweep. Rows P6+ are
payload-shape discovery, not go/no-go.

Probe placements referenced below (set `note:` accordingly):

- **`element-header`** — `{{bws_ctx_probe note:element-header}}` in a site-wide
  GP Element header/hero (the plan's target use case: one Element, all contexts).
- **`in-content`** — `{{bws_ctx_probe note:in-content}}` in a page/post body
  (a plain GB headline block).
- **`loop-item`** — `{{bws_ctx_probe note:loop-item}}` inside a GB Query Loop
  item template.
- **`element-loop-item`** — same as loop-item but with the whole Query Loop
  living inside the GP Element (Element-rendered loop, not in-content loop).

Every row: also note whether the visible `[bws_ctx_probe <note>]` marker
rendered — marker absent + no log line = placement never rendered (caching or
Element display rules), not a signal result.

---

## P0 — smoke: singular post

Validates the probe itself, not architecture. One singular post, placements
`element-header` + `in-content`.

| Record | Expect |
|---|---|
| `conditionals.singular` | true |
| `queried_object` | `["WP_Post", <id>, "post", ...]`, id == `get_the_id` |
| `loop_ctx.in_loop` | false |

**FAIL =** no log line / marker missing → probe or deploy broken (mu-plugin not
loading, GB class timing, debug.log off). Fix before proceeding — nothing below
is trustworthy until P0 passes.

---

## P1 — GB Query Loop on a term archive (precedence + ambient stability)

**Highest-risk row.** Category archive URL; placements `element-loop-item`,
`loop-item` (loop in archive Element content), plus `element-header` on the
same page for the outside-loop baseline.

| Record | Question |
|---|---|
| `instance_ctx` has `generateblocks/loopItem` (loop placements) | Does GB context propagate to tags inside Element-rendered loops? |
| `loop_ctx.in_loop` / `row_post_id` | Does existing loop detection fire there? |
| `queried_object` inside loop items | Still `WP_Term`, or clobbered per-row? |
| `get_the_id` / `post_global` inside loop items | Row post (GB swaps globals) or unchanged? |
| `is_main_query` inside loop items | Secondary-query state at tag render time |

**FAIL (a) =** `loopItem` context missing in `element-loop-item` → loop-row
detection impossible in Elements → factory precedence rule (loop_ctx wins over
ambient) has a blind spot; need another loop signal before designing dispatch.
**FAIL (b) =** `queried_object` changes to the row post inside loops → ambient
detection is NOT stable under loops → factory cannot read queried-object lazily;
must capture ambient once pre-loop. Either fail reshapes §Base Resolution.

**Feeds:** factory precedence order (loop_ctx > ambient), taxonomy rows
"Loop row" + "Term archive".

---

## P2 — GP Element hook-position sweep (queried object survival)

The plan's whole payoff is one site-wide Element. Same `element-header` probe,
Element hooked at three positions: an early hook (e.g. `generate_after_header`),
a hero/page-hero position, and `generate_before_content`. Visit one singular
post AND one category archive per position (6 log lines).

| Record | Question |
|---|---|
| `queried_object` / `queried_id` | Available + correct at every hook position? |
| `post_global`, `get_the_id`, `in_the_loop` | Which post-identity signals exist pre-loop? |
| `hook_stack` | Exact render position per placement (documents when GB tags actually resolve) |

**FAIL =** `queried_object` null or wrong at any front-end hook position →
factory's primary signal is hook-dependent; needs a fallback chain
(queried object → `$post` → main-query flags) and the plan's "detect once at
entry" needs a defined entry point. Partial fail (only `$post`/`in_the_loop`
vary) is expected and fine — that's why queried object is the candidate primary.

**Feeds:** factory's signal choice + the "detects ambient context once" entry
contract (traversal-pipeline §Design principle).

---

## P3 — 404 + search (no-entity contexts)

404: any garbage URL. Search: `?s=test` (also `?s=` empty-query variant).
Placement `element-header`.

| Record | Question |
|---|---|
| `conditionals.404` / `conditionals.search` | True at tag render time? |
| `queried_object` | Expect null — confirm |
| `post_global`, `get_the_id` | Null/false, or stale garbage needing a guard? |
| `query_vars.s` | Search payload present (incl. empty-query case)? |

**FAIL =** conditionals false at render time, or `post_global` carries a stale
post the current dispatch would happily read → today's "silently misresolve"
becomes "confidently wrong"; query-context kinds need an explicit guard before
entity kinds in the factory, not after.

**Feeds:** query-context resolved-source kinds (search/404 payload shape),
ADR 0002 no-field-read path (L2 skipped).

---

## P4 — static posts page + latest-posts home (page-entity-wins rule)

The resolved Open Question (page assigned → page entity wins) presumes a signal
distinguishes the assigned page from the posts in its loop.

- **P4a:** Reading Settings → posts page assigned. Visit it. `element-header`.
- **P4b:** posts page unassigned (latest-posts home). Visit `/`. `element-header`.
- **P4c (variant):** P4a URL page 2 (`/page/2/`) — pagination stability.

| Record | Question |
|---|---|
| P4a `queried_object` | The assigned page `WP_Post` (rule implementable via qo)? |
| P4a `post_global` / `get_the_id` | First loop post, NOT the page (expected divergence — proves `$post` is the wrong signal here)? |
| P4b `queried_object` | Null (only-then-consult-title-option case detectable)? |
| `conditionals.home` / `front` | Combination per case |
| `page_for_posts` / `page_on_front` | Logged for cross-check |

**FAIL =** P4a queried object is NOT the assigned page → page-entity-wins needs
the `get_option('page_for_posts')` fallback (GB's own `is_home` branch does
this — [class-register.php:203-210](../../../../../Resources/GeneratePress%20and%20GenerateBlocks/generateblocks-pro-2.6.0-beta.2/generateblocks-pro/includes/extend/dynamic-tags/class-register.php#L203)),
and the taxonomy row's "singular path, no special handling" claim is wrong.

**Feeds:** taxonomy rows "static posts page" + "latest-posts home"; the
title-source option's gating condition.

---

## P5 — editor / REST preview snapshot

Preview parity is deferred, but the signal shape is cheap to capture now and
scopes that future work. Open the editor on: a normal page, and (if term
archives are edited via a GP Element) the archive Element. Probe already in
content from earlier rows; look for `is_rest: true` lines.

| Record | Question |
|---|---|
| `is_rest`, `is_admin` | Which pipeline previews run through |
| `conditionals.*` | All false / garbage as assumed? |
| `queried_object` | Anything at all? |
| `instance_ctx` | What GB passes in preview (postId? loop context?) |

**No FAIL condition** — pure shape discovery. But if conditionals turn out
RELIABLE in preview, the out-of-scope call in context-aware-base-tags.md
§Out of Scope loosens (preview parity cheaper than assumed).

**Feeds:** preview-parity deferral scoping.

---

## P6 — date archive + post-type archive (payload shapes)

Date: `/2026/`, `/2026/07/`. PTA: any CPT with `has_archive`.
Placement `element-header`.

| Record | Question |
|---|---|
| Date: `queried_object` | Expect null; payload = `query_vars.year/monthnum/day` |
| PTA: `queried_object` | `WP_Post_Type` + labels (title analog source) |
| `conditionals.date` / `pta` | Confirm |

**Feeds:** date + PTA resolved-source payload definitions (query-context kinds).

---

## P7 — author archive

`/author/<nicename>/`. Placement `element-header` + `loop-item` (author archives
commonly render a loop — spot-check P1's conclusion holds for a `WP_User`
queried object).

| Record | Question |
|---|---|
| `queried_object` | `["WP_User", <id>, <display_name>]` |
| `queried_id` | User id (entity-kind payload for bio/user-field reads) |

**Feeds:** user resolved-source kind (entity kind — id payload, field-reads via
ACF `'user_' . $id`).

---

## P8 — plain term archives (control)

Category, tag, one custom taxonomy. Placement `element-header`. GB's own
`get_archive_title` already proves conditionals here — this row is the control
confirming our probe agrees, plus captures `term_id` + `taxonomy` payload.

**Feeds:** term resolved-source payload (already shipped kind — confirmation
only).

---

## Result capture

Paste log lines under each row as they're gathered (this file is the worksheet).
When the sweep is done: distill the per-context truth table + precedence
decisions into the two plan files (§Base Resolution / §Context Taxonomy), then
delete the probe file and this matrix per the debug-workflow rule.

### Runs so far (2026-07-06)

**P0 — static front page, `element-header` — PASS.** singular+front true,
`queried_object` == `$post` == `get_the_id` == `instance_ctx.postId` (39740).
Probe healthy; singular path byte-clean.

**P1 — `benefit-tier/core/` term archive + GB Query Loop — PASS (both fail
conditions cleared). Highest-risk row; §Base Resolution spine validated.**

- FAIL(b) cleared — **ambient stable under loops.** `queried_object` stays
  `WP_Term 34 "Member"` across all 10 loop rows (in-content + element-loop-item).
  Never clobbered by the row post → factory may read queried-object lazily; no
  pre-loop capture needed.
- FAIL(a) cleared — **loop context reaches Element-rendered loops.** Rows carry
  `generateblocks/loopItem` + `loopIndex`; `loop_ctx.in_loop:true`,
  `row_post_id` tracks each row (48418, 48415, 48411…).
- **Precedence rule confirmed = `loop_ctx > ambient`, nothing more.** Outside
  loop → `loop_ctx.in_loop:false` → ambient (`WP_Term 34`) = right archive-header
  answer. Inside loop → `row_post_id` set → row post = right loop-item answer.

**P2 — Element hook-position sweep — PASS (fully closed).** `queried_object`
correct + available at the earliest possible GP Element hook
(`generate_before_header`), confirmed for BOTH singular/front (`WP_Post 39740`)
AND term archive (`WP_Term 34`). Also clean at `generate_header` +
`generate_before_main_content`. Primary signal is hook-stable across the entire
Element hook range; no hook exists earlier where a BWS tag renders. Stale `$post`
(48418) present at every hook on the term archive — reconfirms finding #1 holds
from the earliest hook.

**P3 — 404 + search — PASS (surfaced the safety requirement). Both at
`before_header` + `header`.**

- **404** (garbage URL): `404:true`, `queried_object:null`, **`post_global:null`,
  `get_the_id:false`, `instance_ctx:[]`** — no stale-post leak (zero-result main
  query → `$post` null). 404 dispatch to `$post` → empty, not wrong. Benign.
- **Search-with-results** (`s:dental`): `search:true`, `queried_object:null`,
  **`post_global:[47955,"benefit"]`, `get_the_id:47955`, `instance_ctx.postId`
  same** — main query's FIRST hit leaks into `$post`, identical to the archive
  leak. `query_vars.s:"dental"`, `post_type:"any"`.
- **Consequence:** on search, null queried-object + populated `$post` co-occur.
  A factory that falls to `$post` renders "some dental post" title — plausible,
  silent, wrong. This makes query-context precedence a SAFETY requirement, not a
  nicety (404 alone made it look optional; search proves it isn't).
- **Empty-query `/?s=`** (captured): `search:true`, `queried_object:null`,
  `query_vars.s:""` — BUT **`post_global:[51604,"page"]` STILL leaks** (WP treats
  blank search as match-all → populated main query → first row `$post`). So empty
  search is NOT a null-`$post` case like 404; it leaks like results-search.
  Reinforces the rule: guard on `queried_object === null` + conditional, NEVER on
  whether `$post` is set.

### Findings locked (carry into plans)

1. **`$post` leaks the main-query's first row on EVERY results-bearing
   non-singular context — never use it as an ambient fallback.** Two repros:
   archive `benefit-tier/core/` → `queried_object` = term 34 "Member" but
   `post_global`/`get_the_id` = 48418 (first benefit post); search `s:dental` →
   `queried_object` = null but `post_global` = 47955 (first hit). Zero-result
   contexts (404) don't leak (`$post` null), so the hazard is specifically
   *non-singular + has results*. `$post` trustworthy ONLY inside a confirmed
   loop row (`loop_ctx.in_loop`).

   → **§V-candidate (the factory precedence guard):**
   `loop_ctx → queried_object → query-context-by-conditional → ($post only inside
   a confirmed loop)`. **`$post` is NEVER an ambient fallback.** `queried_object`
   distinguishes the two leak contexts: archive → non-null entity (use it);
   search/404 → null → route to query-context kind, do NOT drop to `$post`.
   Search is the sharpest repro: null queried-object + populated `$post`
   co-occur, so a `$post` fallback fails silent-confident.

2. **`instance_ctx.postId` is NOT a dependable identity cross-check.** On
   archives it mirrors stale `$post` (48418), is ABSENT in one pre-loop wrapper
   state, and only tracks the row inside the loop. Factory should ignore it and
   use `loop_ctx.row_post_id` (derived correctly) + `queried_object`. Kills the
   "cheap cross-check" idea.

3. **Pre-loop wrapper renders in ≥2 distinct context states** (one with
   `postId`+`query`, one with `queryData` and no `postId`). Both `in_loop:false`
   → both correctly fall through to ambient. No action; just don't assume
   pre-loop context shape is singular.

**P4a — static posts page assigned ("News", page 51690) — PASS. Page-entity-wins
implementable via `queried_object` alone; no `page_for_posts` fallback needed.**

- `conditionals`: `home:true`, `front:false`, `singular:false` (posts-page
  signature). `page_for_posts:51690`, `page_on_front:39740` logged.
- **`queried_object` = `["WP_Post",51690,"page","News"]`** = the assigned posts
  page, NOT the first loop post. `queried_id` == `page_for_posts` == 51690. So
  "page entity wins" is a direct `queried_object` read — GB reads
  `get_option('page_for_posts')` defensively in its `is_home` branch; our factory
  doesn't have to.
- **Best finding-#1 repro yet:** `queried_object`=51690 (News page) vs
  `post_global`/`get_the_id`/`instance_ctx.postId`=51686 (first `post` row).
  `$post` diverges from the ambient answer AND (pre-loop) from the row. Outside
  loop → `queried_object` → "News". Inside loop (L93+, `row_post_id` tracks
  51686/51683/51689…) → row post. `loop_ctx > queried_object`, `$post` never the
  signal.

**P4b — latest-posts home, no page assigned — PASS. Closes the home/front
matrix; the title-source option gate is confirmed implementable.**

- `conditionals`: **`home:true` + `front:true`** (the latest-posts signature —
  P4a posts-page was `home:true`+`front:false`; front flips true here).
- **`queried_object:null`, `queried_id:0`** — no page entity exists. `page_for_posts:0`,
  `page_on_front:0` (vs P4a 51690/39740). `$post`=51686 (first post) leaks, but
  `in_loop:false` → factory falls through.

**Home/front matrix fully resolved — three cases, separable by conditionals +
queried_object:**

| Case | home | front | queried_object | `{{title}}` → |
|---|---|---|---|---|
| Static front page (P0) | F | T | the page | page title (singular path) |
| Static posts page (P4a) | T | F | assigned page | page title |
| Latest-posts home (P4b) | T | T | **null** | **title-source option** (`site_name`\|custom) |

The P4b null is the ONLY no-canonical-entity case and is unambiguously detected
by `home && front && queried_object === null`. Title-source option gating
condition confirmed.

**DESIGN QUESTIONS: all closed.** Every decision-bearing row (P0-P4) is green.
Factory precedence spine + finding-#1 guard + home/front matrix + title-source
gate all evidence-backed. Remaining rows are pure payload capture (no design
risk): P4c (`/page/2/` pagination), P6 (date/PTA payloads), P7 (author WP_User),
P8 (tax control), P5 (preview shape / deferral scoping).
