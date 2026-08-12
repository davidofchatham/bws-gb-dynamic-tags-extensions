# Registered Chain Roots + Modifier→Base Migration Matrix (FW-69/70)

**Standing manual regression suite** for a registered source offered as a chain ROOT
(`{{text src:fixture|…}}`) and for the rewrite that turns a modifier family into base tags with that
root (`{{fixture_text key:role}}` → `{{text src:fixture|key:role}}`). Covers what the pure harnesses
structurally cannot: a real source class resolving real seeded content, the container arms a rooted
slot renders through, the Source control an author actually picks the root from, and the converter
run end to end.

> **Re-run trigger:** any change to the chain-root offering (`is_selectable_root()`,
> `SourceRegistry::get_selectable_roots()`, `bws_registered_root_rows()`, the
> `bws_dynamic_tags_chain_roots` filter route or `Sources\CallbackRoot`), to the modifier→base
> transform (`bws_migrate_modifier_root_chain()` and its helpers, the entry generator
> `bws_register_modifier_root_migrations()`, `TagConverter::resolve_full_chain()`), or to the
> blueprint pieces backing them (`fixture-source.php`, `schema.php`'s root + modifier registrations).
> **Pure harnesses are the cheap gate — run them FIRST**, they own the algorithms:
> `slot-options-build-test.php` (which rows each enum contains), `traversal-pipeline-test.php`
> (resolution, incl. the mutation-pinned offering-is-not-resolving case),
> `modifier-base-migration-test.php` (every mapping row + the generator),
> `preview-label-test.php` (a rooted tag names its source by label), `fold-migration-test.php` (the
> cascade this rides). Rows here assert only what needs real WP state or the editor.

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

Term-held `email` values: **sales@example.test** (Sales), **warehouse@example.test** (Warehouse).
Site option `organization_email`: **info@example.test**.

`related_staff` FANS to two targets on purpose, so every row that hops it states a limit and a row
that lost its bound prints two values rather than looking unchanged.

**Both roots resolve from seeded content, never from request state.** That is why they exist rather
than the real external source being used here: that one reads request context, so a row through it
could not state its own expected value.

**No row reads `use:title` on a `fixture_` tag.** A modifier template's text core reads `key` and
ignores `use` entirely, so `use:title` renders empty on `fixture_`, `term_` and `view_` alike
(pre-existing, [#88](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/88)).
Rows read field keys so an equivalence pair cannot pass by comparing two empties.

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

## §FR3 — The `fixture_` modifier corpus (pre-conversion)

The six shapes the transform maps, as stored wire. **Read against a freshly seeded site**: running
the converter (§FR6) rewrites these in place, which is the point of them.

| Row | Tag | Expected | Property |
|---|---|---|---|
| FR3.1 | `{{fixture_text key:role}}` | `Fixture Root Role` | No source stated — the modifier's own entity |
| FR3.2 | `{{fixture_text src:current\|key:role}}` | `Fixture Root Role` | `current` on a modifier named ITS entity, so identical to FR3.1 |
| FR3.3 | `{{fixture_text src:ref\|ref:related_staff\|key:role\|limit:1}}` | `Fixture Ref Role` | Relationship sidecar |
| FR3.4 | `{{fixture_text srcTermIn:department\|key:email}}` | `sales@example.test` | Taxonomy sidecar — the ROOT's term |
| FR3.5 | `{{fixture_text src:ref\|ref:related_staff\|srcTermIn:department\|key:email}}` | `warehouse@example.test` | Both sidecars: hop, then drop into terms |
| FR3.6 | `{{fixture_text src:site\|ref:related_staff\|srcTermIn:department\|use:key\|key:organization_email}}` | **empty** | `site` returns BEFORE either sidecar is read (the [#37](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/37) guard), so this shape has never rendered. Hand-wire only: `site` is filtered out of every rooting-modifier Source dropdown, so it cannot be re-authored through the control |

FR3.6's visible block uses the split-label row helper (`bws_fixture_gb_empty_row`), since GB hides a
text block whose tag resolves to nothing and would take a single-block row's own label down with it.

## §FR4 — Each shape beside the base wire it must become

The migration's whole promise: **same render out**, one row excepted. Numbered to MATCH its FR3
partner, so a divergent pair is read off one digit. There is deliberately no FR4.2 — `src:current` and
a stated-nothing source map to the same wire, which is FR4.1.

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
| FR4.6 | `{{text src:site\|use:key\|key:organization_email}}` | `info@example.test` | FR3.6 — **the one row whose rendered output the migration CHANGES.** The inert sidecars are dropped deliberately (rule + rationale: `deprecated-tags-options.md` §Modifier prefix → base tag, last mapping row) |

**Limits are stated EXPLICITLY on both sides of every pair.** Since 1.17.0 an unset limit is resolved
by the source SPELLING — flat wire bounds at 1, chain wire does not — so a bare-versus-bare
comparison compares two different quantities and diverges by design.

## §FR5 — Editor eyeball (no render-tag equivalent)

The enum is the other half of the offering assertion and only the editor shows it. Open
`/matrix-fixture-roots/` in the block editor and inspect the rows named.

| Row | Where | Expected |
|---|---|---|
| FR5.1 | FR1.1's tag → Source control | Two extra rows after the built-ins: **Fixture Root (class)** and **Fixture Root (filter)**, alongside Current and Site. The labels name their ROUTE, so a row present through the wrong one is visible. **No `ref` row is expected** — a relationship is a STEP, not a root, and is added with `+ Add step` |
| FR5.2 | FR2.1's tag → attempt A's source dropdown | The same two rows, in the same order — one appender feeds both surfaces, so "offered here, absent there" cannot happen |
| FR5.3 | FR2.4's tag → field A's and field B's source dropdowns | Same two rows in a `{{join}}` field |
| FR5.4 | Any `{{fixture_text}}` row (FR3.x) → its own Source control | **Neither root appears.** Registered roots never reach `bws_base_source_option()`, so a modifier family is not offered a second root inside its own dropdown |
| FR5.5 | Any base tag's Source control | No row for the four retired traversal-substitute sources, and none for the internal `post` / `term` keys. A registry that keeps its dead must not leak it into an authoring surface |
| FR5.6 | FR1.1's tag, preview text | Names the source in author terms (**Fixture Root (class)**, the registered source label), never the `src:fixture` token |

## §FR6 — The converter, end to end

Not a row: a script, because it **mutates** the corpus. It converts `/matrix-fixture-roots/` exactly
as the admin Migrate button does, asserts report/run agreement and byte-identical render, and a
reseed puts the pre-conversion `fixture_*` wire back — which is what makes it repeatable rather than
one-shot.

```bash
bin/wp.sh testbed eval-file <mounted-repo>/tools/fixtures/core-structures/verify-migration.php \
  --url=https://testbed.test/matrix-fixture-roots/
bin/seed.sh testbed core-structures        # restore the corpus
```

After a run, re-reading §FR3 shows base tags: FR3.1's block now holds FR4.1's wire. That is the pass
condition, not a fault. The limit spelling differs from §FR4's hand-written wire as described there
(`src:fixture;refs,related_staff,limit(1)`); the render is what must match. **Any §FR3 row read against an unreseeded site is reading post-conversion
wire** — check the seed before filing a failure.
