# Registered Chain Roots + Modifier→Base Migration Matrix (FW-69/70)

**Standing manual regression suite** for a registered source offered as a chain ROOT
(`{{text src:fixture|…}}`) and for the rewrite that turns a modifier family into base tags with that
root (`{{fixture_text key:role}}` → `{{text src:fixture|key:role}}`). Covers what the pure harnesses
structurally cannot: a real source class resolving real seeded content, the container arms a rooted
slot renders through, the Source control an author actually picks the root from, and the converter
run end to end.

> **Re-run trigger:** any change to the chain-root offering (`is_selectable_root()`, `SourceRegistry::get_selectable_roots()`, `bws_registered_root_rows()`, the `bws_dynamic_tags_chain_roots` filter route or `Sources\CallbackRoot`), to the modifier→base transform (`bws_migrate_modifier_root_chain()` and its helpers, the entry generator `bws_register_modifier_root_migrations()`, `TagConverter::resolve_full_chain()`), or to the blueprint pieces backing them (`fixture-source.php`, `schema.php`'s root registrations and its `fixture_*` migration-entry registrar).
> **Pure harnesses are the cheap gate — run them FIRST**, they own the algorithms: `slot-options-build-test.php` (which rows each enum contains), `traversal-pipeline-test.php` (resolution, incl. the mutation-pinned offering-is-not-resolving case), `modifier-base-migration-test.php` (every mapping row + the generator), `preview-label-test.php` (a rooted tag names its source by label), `fold-migration-test.php` (the cascade this rides). Rows here assert only what needs real WP state or the editor.

**How to run:** rows are `render-tag` one-liners against the seeded testbed (state:
`core-structures` blueprint **v8** — `bin/seed.sh testbed core-structures`). From the wp-litespeed
env:

```bash
bin/wp.sh testbed bws render-tag '{{TAG}}' --url=https://testbed.test/matrix-fixture-roots/ --porcelain
```

Location-independent equivalent (works from Windows or WSL, since Docker Desktop shares one daemon):

```bash
docker exec wp-litespeed-litespeed-1 sh -c 'cd /var/www/vhosts/testbed/html && \
  wp bws render-tag "{{TAG}}" --url=https://testbed.test/matrix-fixture-roots/ --porcelain --allow-root'
```

**Also browsable + editable.** Every §FR1-§FR4 row is generated as a visible GB block on
`/matrix-fixture-roots/` (`blocks.php` → `bws_fixture_page_content_matrix_fixture_roots`, the
`matrix_fixture_roots` content builder). Open the page on the front end to eyeball output, or open it
in the editor for §FR5 — the Source control's enum, the folded slot's source dropdown and the preview
text are reachable ONLY there. §FR6 is a script rather than a block, for the reason stated there.

**Owner docs** (rules are stated there, not here): the root enum's membership in
[`docs/tag-reference.md` §Root enum membership](../../docs/tag-reference.md#root-enum-membership-1170-83),
the rewrite's mapping rows in
[`docs/deprecated-tags-options.md` §Modifier prefix → base tag](../../docs/deprecated-tags-options.md#modifier-prefix--base-tag-with-a-registered-root-1170),
and the integrator-facing API in
[`docs/plugin-integration.md` §1a + §9](../../docs/plugin-integration.md#1a-offering-your-source-as-a-chain-root).

**Cache-bust with a LITERAL token when curling.** `?nocache=$RANDOM` does not expand in the
container's `sh`, so the URL stays constant and LiteSpeed serves the cached page — which reads as
"the rows are missing" or "the migration changed nothing":

```bash
docker exec wp-litespeed-litespeed-1 sh -c 'curl -sk "https://testbed.test/matrix-fixture-roots/?nocache=run17"'
```

## Fixture state these rows assume

Three entities carrying three distinct value sets, on purpose: a rooted row that quietly fell through
to the ambient entity prints the wrong words rather than the right ones.

| Entity | Reached by | Carries |
|---|---|---|
| page `matrix-fixture-roots` | the ambient context (no root) | `role` **Ambient Page Role**, its own `main_line`, term **Support** |
| staff `fixture-root` | `src:fixture` — the CLASS route, resolved by slug | `role` **Fixture Root Role**, `related_staff` → **`fixture-ref` then `tom-associate`** (two targets, `fixture-ref` first), term **Sales** alone |
| staff `fixture-ref` | `src:fixture;refs,related_staff` | `role` **Fixture Ref Role**, title **Fixture Ref Target**, term **Warehouse** |
| post `sample-event` | `src:fixture_alt` — the FILTER route, resolved by slug | `venue_city` **Chatham** |
| staff `fixture-root`, again | `src:fixture_scoped` — the FILTER route, SCOPE-BOUND (blueprint v11) | the same values as the class route reaches, **and only on `/matrix-fixture-roots/`**. Off that page it resolves nothing, which is §FR2b's whole subject |

Term-held `email` values: **sales@example.test** (Sales), **warehouse@example.test** (Warehouse).
Site option `organization_email`: **info@example.test**.

`related_staff` FANS to two targets on purpose, so every row that hops it states a limit and a row
that lost its bound prints two values rather than looking unchanged.

**Both roots resolve from seeded content, never from request state.** That is why they exist rather
than the real external source being used here: that one reads request context, so a row through it
could not state its own expected value.

**§FR1-§FR4 read field keys, not `use:title`, on purpose.** Through 1.19.1 a modifier template's text core read `key` and ignored `use` entirely, so `use:title` rendered empty on `fixture_`, `term_` and `view_` alike (pre-existing, [#88](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/88)) — an equivalence pair built on it would have passed by comparing two empties. Fixed in the same release, and §FR7 was the dedicated row that fix earned; §FR7 is retired now that this fixture mints no family, and `term_`'s twin rows in `text-test-matrix.md` §T10 / `content-test-matrix.md` §CT7 are what still hold the fix.

---

## §FR1 — Registered roots on a base tag

The offering half: a root chosen on an ordinary tag, then the whole base-tag surface applied to it.

| Row | Tag | Expected | Property |
|---|---|---|---|
| FR1.1 | `{{text src:fixture\|key:role}}` | `Fixture Root Role` | Class-route root resolves through the factory's registry delegation |
| FR1.2 | `{{text key:role}}` | `Ambient Page Role` | The ambient contrast — same key, no root. FR1.1 passing while this printed the same value would prove nothing |
| FR1.3 | `{{text src:fixture_alt\|key:venue_city}}` | `Chatham` | Filter-route root (no source class) resolves identically |
| FR1.4 | `{{text src:fixture;refs,related_staff\|use:title\|limit:1}}` | `Fixture Ref Target` | A relationship step continues from a registered root |
| FR1.5 | `{{text src:fixture;terms,department\|use:title\|limit:1}}` | `Sales` | A taxonomy step drops into the ROOT's term, not this page's Support |
| FR1.6 | `{{text src:fixture;refs,related_staff;terms,department\|use:title\|limit:1}}` | `Warehouse` | Two steps off a registered root — the hop TARGET's term |

## §FR2 — Registered roots inside a folded slot

Ungated by decision: a root offered on a base tag and silently absent from an attempt or a join field
is the failure users report as a bug.

| Row | Tag | Expected | Property |
|---|---|---|---|
| FR2.1 | `{{try_text A:src(fixture);use(key);key(role)}}` | `Fixture Root Role` | A first-available attempt roots at the class route |
| FR2.2 | `{{try_text A:src(fixture_alt);use(key);key(venue_city)}}` | `Chatham` | …and at the filter route |
| FR2.3 | `{{try_text A:src(fixture);use(key);key(no_such_field)\|B:use(key);key(role)}}` | `Ambient Page Role` | A rooted attempt that resolves EMPTY is fallen past, not fatal — the root does not capture the tag |
| FR2.4 | `{{join mode:template\|A:src(fixture);use(key);key(role)\|B:src(fixture_alt);use(key);key(venue_city)\|format:%A of %B}}` | `Fixture Root Role of Chatham` | One assembled string mixes both roots, in different fields |

## §FR2b — The SCOPE-BOUND root: offering is not resolving, and resolving is per-page

`fixture_scoped` (blueprint v11) is the third fixture root and the only one that ever answers
nothing. It resolves **on this page** and refuses everywhere else, which is the #76 category-2 shape:
a registered source, correctly written, off its scope. The two other roots always resolve, so before
v11 a rendered refusal could only be reached by an UNREGISTERED token, which is a different decline
of the factory's and proves nothing about this one.

**This section is the NON-VACUITY half of a pair.** Its negative lives in
[`fold-test-matrix.md`](fold-test-matrix.md) §F11a.4, on `/matrix-post-meta/`, where the identical
tag must render nothing. Run them together or neither means anything: a source that could never
resolve anywhere would satisfy the negative while asserting nothing at all.

| Row | Tag | Expected | Property |
|---|---|---|---|
| FR2b.1 | `{{text src:fixture_scoped\|use:key\|key:role}}` | `Fixture Root Role` | The scoped root resolves on the page it is scoped to |
| FR2b.2 | `{{text key:role}}` | `Ambient Page Role` | The ambient contrast, same key — a root that fell through would print this |
| FR2b.3 | FR2b.1's tag, on `/matrix-post-meta/` | EMPTY | Stated here as the pointer; the row itself is fold §F11a.4, beside its own ambient contrast |

## §FR3 — The retired-prefix corpus (pre-conversion)

The six shapes the transform maps, as stored wire. **Read against a freshly seeded site**: running the converter (§FR6) rewrites these in place, which is the point of them.

**Every row renders LITERALLY.** The fixture stands down from `register_modifier()` ahead of FW-129 withdrawing it, so nothing mints a `fixture_*` tag and GB hands the unknown tag back untouched. (The constructor is still live and still mints `term_`; only this prefix is gone.) That is not a fault in the fixture — it is the state a retired prefix leaves on a published page, and the reason the converter exists. The value each row is *worth* after conversion is its §FR4 partner, which renders live on the same page.

| Row | Tag | Expected | Property |
|---|---|---|---|
| FR3.1 | `{{fixture_text key:role}}` | the tag, literally | No source stated — migrates to FR4.1. The visible row's LABEL carries no tag braces, here or on any FR3 row: §FR6's converter rewrites every `{{fixture_…}}` string in the post body, so a label quoting its own tag is rewritten out from under itself |
| FR3.2 | `{{fixture_text src:current\|key:role}}` | the tag, literally | `current` on a prefix that named ITS entity, so it migrates to FR4.1 too |
| FR3.3 | `{{fixture_text src:ref\|ref:related_staff\|key:role\|limit:1}}` | the tag, literally | Relationship sidecar — migrates to FR4.3 |
| FR3.4 | `{{fixture_text srcTermIn:department\|key:email}}` | the tag, literally | Taxonomy sidecar — migrates to FR4.4 |
| FR3.5 | `{{fixture_text src:ref\|ref:related_staff\|srcTermIn:department\|key:email}}` | the tag, literally | Both sidecars — migrates to FR4.5 |
| FR3.6 | `{{fixture_text src:site\|ref:related_staff\|srcTermIn:department\|use:key\|key:organization_email}}` | the tag, literally | Hand-wire only: `site` was filtered out of every rooting-modifier Source dropdown ([#37](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/37)), so this shape could never be authored through a control. Migrates to FR4.6, sidecars dropped |

FR3.6's visible block is an ordinary single-block row like its five siblings. It used the split-label helper (`bws_fixture_gb_empty_row`) while the family was minted and the row resolved to nothing, because GB hides a text block whose tag renders empty and would have taken the row's own label with it. Unregistered wire renders its own braces rather than nothing, so the block is no longer hidden and the helper is not needed.

## §FR4 — Each shape beside the base wire it must become

What the migration produces, and the only side of the pair that renders now the `fixture_` family is no longer minted. Numbered to MATCH its FR3 partner, so a divergent pair is read off one digit. There is deliberately no FR4.2 — `src:current` and a stated-nothing source map to the same wire, which is FR4.1.

**This is no longer a byte-identity pair.** The old `fixture_*` side rendered, so the two columns could be compared directly; it renders literally now, so what FR4 pins is that the converter's target renders the value the row names. `verify.php` asserts the same four values under the same wire, and `verify-migration.php` asserts the converter produces exactly these strings — between them the equivalence is still measured, just not by reading two rows side by side.

These are the hand-written wire each shape MUST become, not a transcript of what the converter emits.
It writes the same read a different way where a limit is involved: FR4.3's tag-level `limit:1` is
written by the converter INSIDE the step it bounds (`refs,related_staff,limit(1)`), because the
base-tag chain entry absorbs a tag-level limit in the same pass. Same quantity, stated where the
source is stated — see §FR6.

| Row | Tag | Expected | Pairs with |
|---|---|---|---|
| FR4.1 | `{{text src:fixture\|key:role}}` | `Fixture Root Role` | FR3.1 **and** FR3.2 |
| FR4.3 | `{{text src:fixture;refs,related_staff\|key:role\|limit:1}}` | `Fixture Ref Role` | FR3.3 |
| FR4.4 | `{{text src:fixture;terms,department\|key:email\|limit:1}}` | `sales@example.test` | FR3.4 |
| FR4.5 | `{{text src:fixture;refs,related_staff;terms,department\|key:email\|limit:1}}` | `warehouse@example.test` | FR3.5 |
| FR4.6 | `{{text src:site\|use:key\|key:organization_email}}` | `info@example.test` | FR3.6 — the inert sidecars are dropped deliberately (rule + rationale: `deprecated-tags-options.md` §Modifier prefix → base tag, last mapping row) |

**Limits are stated EXPLICITLY on every row.** Since 1.17.0 an unset limit is resolved by the source SPELLING — flat wire bounds at 1, chain wire does not — so wire written without one is not the same quantity as the flat shape it replaces.

## §FR5 — Editor eyeball (no render-tag equivalent)

The enum is the other half of the offering assertion and only the editor shows it. Open
`/matrix-fixture-roots/` in the block editor and inspect the rows named.

| Row | Where | Expected |
|---|---|---|
| FR5.1 | FR1.1's tag → Source control | After the built-ins (Current, Site), in registration order: **Post**, **Term** (both DECLARING rows, 1.20.0 FW-39 — see below), then **Fixture Root (class)** and **Fixture Root (filter)**. The labels name their ROUTE (for the fixture pair), so a row present through the wrong one is visible. **No `ref` row is expected** — a relationship is a STEP, not a root, and is added with `+ Add step` |
| FR5.2 | FR2.1's tag → attempt A's source dropdown | The same rows, in the same order — one appender feeds both surfaces, so "offered here, absent there" cannot happen |
| FR5.3 | FR2.4's tag → field A's and field B's source dropdowns | Same rows in a `{{join}}` field |
| FR5.4 | Any `{{fixture_text}}` row (FR3.x) → its own Source control | **No tag panel at all.** Nothing mints the family, so the block holds unrecognized wire and GB offers nothing to configure. This row used to assert that a modifier family was not offered a second root inside its own dropdown; the shape it guarded against cannot exist once no constructor mints a family |
| FR5.5 | Any base tag's Source control | No row for the four retired traversal-substitute sources — a registry that keeps its dead must not leak it into an authoring surface. **Post and Term DO appear** (1.20.0, FW-39): each is now a declaring root, not an internal-only key; selecting either mounts the entity-picker control (D11/D15) rather than a plain enum row. The dedicated entity-selection eyeball (picker browsing, filtering, the resolved-label caption, the "nothing selected" warning) lives on `/matrix-pinned-roots/` — see `fold-test-matrix.md` §F20 |
| FR5.6 | FR1.1's tag, preview text | Names the source in author terms (**Fixture Root (class)**, the registered source label), never the `src:fixture` token |

## §FR6 — The converter, end to end

Not a row: a script, because it **mutates** the corpus. It converts `/matrix-fixture-roots/` exactly as the admin Migrate button does, asserts report/run agreement and that what it wrote renders, and a reseed puts the pre-conversion `fixture_*` wire back — which is what makes it repeatable rather than one-shot.

```bash
bin/wp.sh testbed eval-file <mounted-repo>/tools/fixtures/core-structures/verify-migration.php \
  --url=https://testbed.test/matrix-fixture-roots/
bin/seed.sh testbed core-structures        # restore the corpus
```

After a run, re-reading §FR3 shows base tags: FR3.1's block now holds FR4.1's wire. That is the pass
condition, not a fault. The limit spelling differs from §FR4's hand-written wire as described there
(`src:fixture;refs,related_staff,limit(1)`); the render is what must match. **Any §FR3 row read against an unreseeded site is reading post-conversion
wire** — check the seed before filing a failure.

## §FR7 — RETIRED (was: modifier `use` dispatch, [#88](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/88))

Two rows measured `register_modifier()`'s `use` dispatch on a minted `fixture_*` tag. This fixture no longer mints one, so there is no dispatch left on this prefix to observe and the blocks are gone from `blocks.php`. The constructor itself is still live for `term_` until FW-129 withdraws it. `term_`'s twin rows survive until the `term_` family does — `text-test-matrix.md` §T10 and `content-test-matrix.md` §CT7, both render-tag-only against a term archive.
