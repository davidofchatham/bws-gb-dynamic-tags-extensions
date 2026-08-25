# core-structures blueprint

First fixture blueprint (fixture-testbed FW-42). Seeds the state the two existing
manual matrices assume:

- [`tools/test/phone-test-matrix.md`](../../test/phone-test-matrix.md)
- [`tools/test/field-selector-test-matrix.md`](../../test/field-selector-test-matrix.md)
- [`tools/test/text-test-matrix.md`](../../test/text-test-matrix.md) (added 1.14.1 — read-seam rows; uses `staff-tom-associate` + `bws_zero_probe`)
- [`tools/test/join-test-matrix.md`](../../test/join-test-matrix.md) (added 1.15.0 — {{join}} assembly rows; `name_*` person parts dense on `tom-associate` / sparse on `jane-partner`, `role` + `height_*` on `matrix-post-meta`; manifest v2)
- [`tools/test/context-test-matrix.md`](../../test/context-test-matrix.md) (added 1.15.0 — context-aware base tags #19; author-archive C3/C13 via `fixture-author` user, date-archive rows via categoryless portal-visible `sample-event`, `department-sales` description for C17; manifest v4)
- [`tools/test/fw52-order-test-matrix.md`](../../test/fw52-order-test-matrix.md) (added for FW-52 — EDITOR-EYEBALL serialization-order rows on `matrix-post-meta`; a seeded `fixture-photo` attachment + `feature_image` image field back the `{{image}}` reads; manifest v5, additive)
- [`tools/test/registered-roots-test-matrix.md`](../../test/registered-roots-test-matrix.md) (added 1.17.0, #87 — the FR rows below as a runnable matrix: §FR1/§FR2 render-tag, §FR5 editor-eyeball for the enum, §FR6 the converter run)
- the external-source contract rows on `matrix-fixture-roots` (added 1.17.0, #85 — two
  registered chain roots plus the `fixture_*` modifier corpus the migration rehearses
  against; see §External-source contract below; manifest v8)
- [`tools/test/fold-test-matrix.md`](../../test/fold-test-matrix.md) §F15/§F17 (added 1.18.0,
  ADR 0007 — the determinism rows on the existing matrix pages at v12, then the source-gate
  corpus on its own `matrix-gate` page at v13/v14; see §Source-gate corpus below)
- [`tools/test/loop-test-matrix.md`](../../test/loop-test-matrix.md) (added 1.19.0 — the query
  loop corpus on its own `matrix-loops` page: a term loop and a user loop, plus the unstaffed
  `department-workshop` term; see §Query-loop corpus below; manifest v16)

Holds the SHARED schema (CPTs, taxonomies, field groups) for the plugin family;
later blueprints (e.g. portal-system) compose on top and must not redefine keys
listed in `manifest.php` `defines` — reuse via composition instead.

## External consumers (check before bumping `version`)

Composing blueprints pin the manifest `version` and enforce the pin at seed
time (their seed.php errors on mismatch). When bumping, verify these still
seed + verify clean, or coordinate a pin bump with them:

| Consumer | Blueprint | Pins |
|---|---|---|
| bws-portal-system | `tools/fixtures/view-structures/` | v4+ |
| meta-conductor | `tools/fixtures/mc-rules/` | v4+ |
| bws-generate-layout-conditions | `tools/fixtures/layout-states/` | v4+ |

`layout-states` asserts against **`department:sales` specifically**: it needs a
populated non-singular archive for a featured-image detection test, and an
empty term archive 404s (the test would then pass without running). If that
term ever loses its posts, that blueprint's verify reports it.

Orchestrated seeding (order + pins + verify in one command):
wp-litespeed env `bin/seed-all.sh <site>`.

## Files

| File | Role |
|---|---|
| `manifest.php` | Data contract — what the seeded site contains, keyed by stable fixture slugs. Consumers pin `version`. |
| `schema.php` | Code — CPT/taxonomy registration, ACF groups, options page, registered meta. Loaded at runtime by the mu-plugin stub `seed.php` installs. |
| `seed.php` | Idempotent applier — reads the manifest, upserts by fixture slug. `wp eval-file`-able. |
| `blocks.php` | GB block markup generator (4 shapes) — builds the matrix pages' content from tag strings. |
| `fixture-source.php` | The class-route chain-root source (#85). Required lazily from `schema.php`'s registration callback — it extends a plugin class, so a top-level declaration would fatal a site with the plugin off. |
| `verify.php` | Post-seed smoke test — renders through the real seam against `/matrix-post-meta/`, plus the source-gate rows off `matrix-gate` under FOUR viewers (v14: anonymous, administrator, the draft's owner, a different author). Not a matrix replacement. |
| `verify-migration.php` | The modifier→base migration end to end (#86) — report, run, byte-identical render. **Converts the corpus in place; reseed after.** |
| `verify-datetime-migration.php` | The pre-1.6 datetime migration end to end (#90) — report, run, and whether the injected flags move the RENDERED axes. Self-cleaning: it converts its own throwaway draft, so no reseed. |
| `verify-pattern-cache.php` | The GB Pro pattern-cache reconcile end to end (#99) — the defect reproduced, the repair, idempotence, duplicate-row convergence, and the escaping round trip through real meta storage. Self-cleaning: its destructive work happens on throwaway `wp_block` posts, so no reseed. |
| `lib-admin-context.php` | `bws_fixture_assume_administrator()` — sets an administrator when the CLI has no current user. Required by `seed.php` and the three verify scripts that drive converter code. Carries the measurement and the rationale, including why `tools/harvest-replay/replay-tags.php` is deliberately excluded. |

## Seeding

Prereqs: a dedicated test site with GenerateBlocks (Pro), ACF Pro and GB Query Enhancements
active (licensed baseline saved via the env's snapshot tool). All four are declared in
`env-versions.php`, and `verify.php` FAILS by name when one is missing or deactivated
([`docs/testbed.md`](../../../docs/testbed.md) says what the query extension's presence changes and
why it is on this list at all). From the wp-litespeed env:

> **Seeding runs as an ADMINISTRATOR, and that is load-bearing (#99).** `seed.php` calls
> `bws_fixture_assume_administrator()` before anything else, because WP-CLI runs with no
> current user and capability-gated listeners then do not fire. Concretely: GB Pro's pattern
> cache bails on `! current_user_can( 'edit_post' )`, so a capability-less seed creates the
> `wp_block` fixture with **no cache entry at all** and `verify-pattern-cache.php` fails in a
> way that reads like a code regression. Perturbation was measured before adopting this —
> identical row counts, byte-identical `wp_postmeta` MD5, `verify.php` clean. You do not need
> to pass `--user`; the helper finds one. Passing one explicitly is respected.

```bash
bin/wp.sh <site> eval-file <mounted-repo-path>/tools/fixtures/core-structures/seed.php
```

Then smoke-test:

```bash
bin/wp.sh <site> eval-file <mounted-repo-path>/tools/fixtures/core-structures/verify.php \
  --url=https://<site-domain>/matrix-post-meta/
```

The migration end-to-end run is separate because it MUTATES the corpus — it converts
`/matrix-fixture-roots/` exactly as the admin Migrate button does, then a reseed puts the
pre-conversion `fixture_*` wire back, which is what makes it repeatable rather than
one-shot:

```bash
bin/wp.sh <site> eval-file <mounted-repo-path>/tools/fixtures/core-structures/verify-migration.php \
  --url=https://<site-domain>/matrix-fixture-roots/
bin/seed.sh <site> core-structures        # restore the corpus
```

The datetime end-to-end run needs no reseed — it builds and deletes its own draft rather
than converting corpus wire, because the property it asserts is about the CONVERTER and the
renderer, not about the corpus:

```bash
bin/wp.sh <site> eval-file <mounted-repo-path>/tools/fixtures/core-structures/verify-datetime-migration.php \
  --url=https://<site-domain>/matrix-post-meta/
```

It cannot assert byte-identical before/after the way `verify-migration.php` does: the
pre-1.6 datetime renderers were deleted in the 1.6 consolidation, so the legacy tag has
nothing left to render with. That half of the property lives in
`tools/test/datetime-migration-test.php`, which mirrors the deleted read.

### Pattern cache (#99)

```bash
bin/wp.sh <site> eval-file <mounted-repo-path>/tools/fixtures/core-structures/verify-pattern-cache.php
```

No `--url` is needed: nothing here renders a tag, so there is no ambient context to be wrong
about. The reconcile is deliberately context-free, which is the property that made it
preferable to rebuilding the cache entry.

It needs GenerateBlocks **Pro** active and refuses to run without it, rather than reporting
vacuous passes. Its destructive work happens on throwaway `wp_block` posts it creates and
deletes, so the seeded `bws-fixture-legacy-wire` pattern keeps its pre-migration wire and no
reseed is needed. That seeded pattern is browsable in the block editor's pattern inserter and
editable at **Appearance → Patterns**: inserting it before a reconcile is the defect demo,
since it seeds pre-migration wire into fresh content.

> **Cache-busting from `docker exec sh -c` needs a LITERAL token.** The env's usual
> `?nocache=$RANDOM` expands in bash; the container's `sh` leaves it EMPTY, so the URL is
> constant and LiteSpeed serves the pre-conversion page. That reads as "the migration
> changed nothing" when it changed everything. Use `?nocache=<something-unique>`.

Safe to re-run — upserts by slug; page content is regenerated every run.
Seeding also merges a plugin-settings baseline (phone: global CC `1`, strip OFF —
the phone matrix's default state) into `bws_dynamic_tags_settings`.

> **Reseed is additive — it never DELETES a key removed from the manifest.** If a
> fixture edit *drops* a field (e.g. the join dense↔sparse swap that moved the full
> `name_*` set from `jane-partner` to `tom-associate`), the removed keys stay on the
> old post as orphaned meta until cleared by hand (`wp post meta delete <id> <key>`,
> plus the ACF `_<key>` companion) or a fresh reseed on an empty DB. Verify with
> `wp post meta list <id>` after any field-removing change.
`seed.php` also installs `mu-plugins/bws-fixture-core-structures.php`, a loader stub
whose include path is computed at seed time (nothing machine-specific committed),
so the schema survives snapshot restores.

## Seeded surface (summary — manifest.php is authoritative)

- CPT `staff`, taxonomy `department` (post/page/staff).
- Matrix pages split **by source-state** (tag families accrete rows INTO each):
  `matrix-post-meta` (explicit reads: full field value set + src:site + src:ref),
  `matrix-terms-valid`, `matrix-terms-mixed` (one junk term), `matrix-terms-junk`
  (all junk → fallback); post `sample-event` (discovery edge cases); staff
  `jane-partner` (src:ref target).
- External-source corpus (#85): staff `fixture-root` (the `fixture` root's target) +
  staff `fixture-ref` (its relationship target, the only staff single with a department
  term) + page `matrix-fixture-roots` (the FR rows). See §External-source contract.
- Block pattern (`wp_block`) `bws-fixture-legacy-wire` (#99) — carries a pre-1.6 tag name so
  the converter always rewrites it, plus literal backslashes in both the block-comment JSON
  and a rendered code block so the meta layer's recursive unslash has something to damage.
  Browsable with no `blocks.php` row by construction.
- Query-loop corpus (v16): page `matrix-loops` (a term loop and a user loop) + the
  `department-workshop` term, assigned to no post. See §Query-loop corpus.
- Source-gate corpus (v13/v14): page `matrix-gate` + staff `gate-draft` (draft, owned by
  `fixture-author`) / `gate-private` (private) / `gate-public` / `gate-trashed` (trash), plus the
  `fixture-other-author` user. See §Source-gate corpus.
- Options page **Site Settings** with `organization_*` fields.
- Fixture user `fixture-author` (display name + bio) authoring `sample-event`
  → the author-archive context fixture (`/author/fixture-author/`, C3/C13).
- `sample-event` doubles as the date-archive fixture: kept categoryless +
  portal-visible so `/2026/07/` has results under the portal-system anonymous
  query filter (else 404). `department-sales` carries a description (C17).
- join person-name surface: `name_*` parts (Staff Contact group) — dense on
  `tom-associate`, sparse (first+last) on `jane-partner`; `role` + `height_*`
  (incl. blank + zero probes) + a slot-1 `name_first` on `matrix-post-meta`.
  Both staff singles carry a `staff_join` content builder (the name J-rows as
  visible/editable GB blocks — full set on each, tom dense vs jane sparse);
  the post-arm J-rows (height/role/absorb) render in a Join section group on
  `matrix-post-meta`.
- Collision repeaters (Team / Product Features), two flex fields (Page Builder),
  registered-meta set (`bws_global_note`, `bws_page_only`, `subtitle`, `bws_cat_note`),
  `Break </script><b>x</b>` label probe, empty **Scratch** ACF group in the DB.
- Media: one seeded image attachment `fixture-photo` (deterministic solid-color PNG,
  idempotent by `_bws_fixture_slug` meta) + a `feature_image` ACF image field
  (return_format `id`) on `matrix-post-meta` — backs the FW-52 `{{image}}` editor rows.
- Ref-hop return formats, all THREE shapes ACF can hand back (manifest v6):
  `related_staff` (relationship, `id`), `related_staff_obj` (relationship,
  `object` → `WP_Post[]`) and `lead_staff_obj` (post_object, `object` → ONE
  `WP_Post`, the only shape that reaches the reader's non-array wrap). All carry
  the SAME targets, so the hop's output is an equivalence assertion with no new
  expected values (fold matrix RF1/RF2).

## External-source contract (#85, manifest v8)

The in-repo corpus for the registered-chain-root seam (spec
[#80](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/80)), so the
contract is exercised with **no second plugin installed** and the modifier migration has
something reseedable to run against more than once.

| Piece | Where | What it is |
|---|---|---|
| `fixture` root | `fixture-source.php` + `schema.php` | The **class route**: a source registered on `bws_dynamic_tags_register_sources`, opted in with `is_selectable_root()`. Resolves the seeded `staff/fixture-root` post **by slug**. |
| `fixture_alt` root | `schema.php` | The **filter route**: a spec on `bws_dynamic_tags_chain_roots`, no source class. Resolves the seeded `sample-event` post by slug. |
| `fixture_*` tags | `schema.php` (init:21) | A **modifier family** rooted at `fixture`, registered exactly as the external one is — prefix and root key are the same string, which is what makes the seeded tags a faithful rehearsal of the rewrite. |
| Corpus rows | `blocks.php` → `/matrix-fixture-roots/` | FR1/FR2 the roots on a base tag and in a folded slot, FR3 the six migration shapes as `fixture_*` tags, FR4 each shape beside the base wire it must become. |

**Both roots resolve from seeded content, never from request state.** That is the whole
reason they exist rather than the real external source being used here: that one reads
request context, so a row through it cannot state its own expected value. These answer the
same entity on every request, so every row has one.

**Three entities, three distinct values, on purpose.** The corpus page carries its own
`role`/`main_line` and the **Support** term; the root target `fixture-root` carries
different ones and **Sales** alone; its relationship target `fixture-ref` carries a third
set and **Warehouse**. A rooted row that quietly fell through to the ambient entity
therefore prints the wrong words rather than the right ones. (`fixture-ref` exists because
the "relationship **and** taxonomy" shape needs a hop target that carries a term, and
giving one to an existing staff single would have moved state other matrices read.)

Two rows behave in ways that look like faults and are not:

- **FR3.6 renders empty.** The modifier callback returns on `src:site` *before* reading
  either sidecar, so the shape has never rendered; its migrated form (FR4.6, sidecars
  dropped) does. This is the one mapping row that changes rendered output, and `verify.php`
  asserts the divergence so it cannot later be read as a regression.
- **No row reads `use:title`.** A modifier template's text core reads `key` and ignores
  `use` entirely, so `use:title` renders empty on `fixture_`, `term_` and `view_` alike.
  Pre-existing (issue #88); the rows read field keys so an equivalence pair
  cannot pass by comparing two empties.

Running the Tag Converter over the site rewrites the FR3 rows into base tags — that is
what they are for. A reseed puts them back.

## Source-gate corpus (ADR 0007, manifest v13 + v14)

The fixture for the gate's two viewer-independent-and-not levels — EXISTS and VISIBLE
(fold matrix [§F17](../../test/fold-test-matrix.md)). One page, `matrix-gate`, three staff
singles differing in exactly one property, and three reference shapes:

| Piece | What it is |
|---|---|
| `gate_staff` | ACF relationship (return `id`) naming `Dana Draft` (draft), `Paul Private` (private), `Grace Published` — IN THAT ORDER. Anonymous readers get Grace, an administrator gets Dana, from identical wire |
| `stale_ref` | PLAIN post meta holding a genuinely deleted id ahead of Grace. Not ACF: ACF's own relationship formatter drops a dead id before the gate could see it, so an ACF-backed fixture would pass with the engine gate deleted |
| `via_draft` | Names the draft alone; the draft's `reports_to` is Grace. A chain through it is cut at the hop for a visitor and resolves for an administrator, which is what keeps the empty honest |
| `trash_ref` (v14) | PLAIN meta naming a TRASHED staff single (`Trish Trashed`) ahead of Grace. The one status where EXISTS passes and VISIBLE fails for EVERY viewer: WP maps `read_post` on trash toward `edit_post`, so a capability-only gate shows an administrator content no visitor can reach, and WP's own front end 404s a trashed permalink for both |
| `feature_image` (v14) | The seeded attachment, hopped onto AS A SOURCE. An attachment stores the internal `inherit`, so a gate testing the raw status column drops every attachment for logged-out visitors while showing it to logged-in ones. No other row can see this: plain `{{image}}` reads never enter the traversal pipeline |
| `post_author` on the draft (v14) | The draft is owned by `fixture-author`, and `fixture-other-author` (a second author-role user) is seeded beside it. Both arms are logged in and neither can read every draft, so OWNERSHIP is the only difference — the pair is what separates viewer-relative from logged-in-relative. An editor cannot serve as the negative: `edit_others_posts` reads the draft |

**THE ONLY FIXTURE HERE THAT READS DIFFERENTLY PER VIEWER**, and both readings are the
assertion — a row that agreed across the two would mean the viewer-relative arm had been
deleted. WP-CLI runs with no current user unless `--user` is passed, so a bare `render-tag`
and a front-end curl are the anonymous arm while the block editor shows the administrator's.
`verify.php` renders both arms off one ambient post rather than leaving it to whichever
viewer a hand run happens to be.

The deleted id is created and force-deleted AT SEED TIME (`{DELETED_POST_ID}`): a hardcoded
high number passes vacuously until the site's auto-increment reaches it, then fails. FORCE is
the point of that deletion and is not interchangeable with trashing — a trashed post still
EXISTS, which is the neighbouring row's fixture rather than this one's.

**`trash` is in seed.php's explicit status lookup list** for the same reason `draft` and
`private` are: `WP_Query`'s `'any'` subtracts every `exclude_from_search` status, and a fixture
the lookup cannot see is re-created on each reseed, with the duplicate taking the slug.

**Row labels must not contain `{{`.** A fixture label is a GB text block like any other, so a
literal tag spelled inside one is parsed and rendered — F15.7's label named the text tag,
resolved to nothing, and GB took the label block down with it, which reads as missing fixture.

## Query-loop corpus (manifest v16)

The fixture for tags rendered inside a query loop whose items are NOT posts — one page,
`matrix-loops`, carrying a term loop and a user loop (loop matrix
[§QL](../../test/loop-test-matrix.md)). They are one mechanism with two item shapes, which is
why they share a page.

**It is the one page in the blueprint whose loops need a THIRD-PARTY plugin to run at all.**
GB itself has no term or user query type; the co-resident query extension supplies both, which
is why `env-versions.php` declares it required and `verify.php` fails rather than skips when it
is absent. Without it every group here renders nothing and the page reads as broken.

| Piece | What it is |
|---|---|
| The `category` loop (QL1.1–QL1.3) | Restricted to the DEFAULT term, where the id collision is guaranteed by WordPress itself: Uncategorized is term 1 and the first post is post 1 on every install, so a bare tag of ours prints a real title from the wrong entity. Restricted by SLUG, not by id — the id is what makes the collision, but writing it into the query would pin the fixture to the number instead of to the install rule |
| The `department` loop (QL1.4) | The same leak with nothing to land on: the blueprint's own term ids (6/7/8 on the reference site) are carried by no post, so the read comes back EMPTY. Kept because the two shapes look unalike to a reader and are one defect. QL1.4b is its non-vacuity control |
| The user loop (QL2) | The two author-role users, ordered by display name — by ROLE rather than by id, since their ids are whatever the seed order produced. QL2.2 is a row with no tag: there is no explicit user source token, so QL1's middle read cannot be written, and a group with one read missing would otherwise look like a group with a broken row |
| `department-workshop` (QL3) | A department term assigned to no post, so `{{term_count}}` on it is a bare `'0'` — the fourth tag NOT OURS measured reaching the falsy-replacement guard, and the one that needed a term query loop before it could be pinned. Reachable ONLY with `hide_empty` off, which no other fixture sets, so it is invisible to every existing department row (each of those walks the terms assigned to a POST) |

**THE BARE-TAG ROWS ARE SEEDED WRONG ON PURPOSE**, and the page snapshot holds those leaked
values. That is what makes the fix's diff proof rather than assertion: when item-shape
recognition ships, exactly those cells move and nothing else does. The labels say so on the
page, and they are rewritten to the correct values by the change that fixes them.

These loops carry the query extension's own `queryType` strings, which nothing under `includes/`
does. Where that line sits, and why, is stated at `bws_fixture_gb_query_loop_blocks()`.

## Known gaps

- **§V17 tier-3b (degenerate term context)** — not seedable via a normal term
  archive; needs a deliberately broken one. Deferred, tracked in the
  fixture-testbed plan. Hardest fixture in the set.
- Complex styled/structural review surfaces are NOT generated — hand-build in the
  editor once, then snapshot (block-generation pin in the fixture-testbed plan).
