# tags-core blueprint

First fixture blueprint (fixture-testbed FW-42). Seeds the state the two existing
manual matrices assume:

- [`tools/test/phone-test-matrix.md`](../../test/phone-test-matrix.md)
- [`tools/test/field-selector-test-matrix.md`](../../test/field-selector-test-matrix.md)

Holds the SHARED schema (CPTs, taxonomies, field groups) for the plugin family;
later blueprints (e.g. portal-system) compose on top and must not redefine keys
listed in `manifest.php` `defines` — reuse via composition instead.

## Files

| File | Role |
|---|---|
| `manifest.php` | Data contract — what the seeded site contains, keyed by stable fixture slugs. Consumers pin `version`. |
| `schema.php` | Code — CPT/taxonomy registration, ACF groups, options page, registered meta. Loaded at runtime by the mu-plugin stub `seed.php` installs. |
| `seed.php` | Idempotent applier — reads the manifest, upserts by fixture slug. `wp eval-file`-able. |
| `blocks.php` | GB block markup generator (4 shapes) — builds the matrix pages' content from tag strings. |
| `verify.php` | Post-seed smoke test — renders through the real seam against `/phone-matrix/`. Not a matrix replacement. |

## Seeding

Prereqs: a dedicated test site with GenerateBlocks (Pro) + ACF Pro active
(licensed baseline saved via the env's snapshot tool). From the wp-litespeed env:

```bash
bin/wp.sh <site> eval-file <mounted-repo-path>/tools/fixtures/tags-core/seed.php
```

Then smoke-test:

```bash
bin/wp.sh <site> eval-file <mounted-repo-path>/tools/fixtures/tags-core/verify.php \
  --url=https://<site-domain>/phone-matrix/
```

Safe to re-run — upserts by slug; page content is regenerated every run.
Seeding also merges a plugin-settings baseline (phone: global CC `1`, strip OFF —
the phone matrix's default state) into `bws_dynamic_tags_settings`.
`seed.php` also installs `mu-plugins/bws-fixture-tags-core.php`, a loader stub
whose include path is computed at seed time (nothing machine-specific committed),
so the schema survives snapshot restores.

## Seeded surface (summary — manifest.php is authoritative)

- CPT `staff`, taxonomy `department` (post/page/staff).
- Pages `phone-matrix` (full phone value set + valid-terms hop), `phone-mixed-terms`
  (one junk term), `phone-junk-terms` (all junk → fallback); post `sample-event`
  (discovery edge cases); staff `jane-partner` (src:ref target).
- Options page **Site Settings** with `organization_*` fields.
- Collision repeaters (Team / Product Features), two flex fields (Page Builder),
  registered-meta set (`bws_global_note`, `bws_page_only`, `subtitle`, `bws_cat_note`),
  `Break </script><b>x</b>` label probe, empty **Scratch** ACF group in the DB.

## Known gaps

- **§V17 tier-3b (degenerate term context)** — not seedable via a normal term
  archive; needs a deliberately broken one. Deferred, tracked in the
  fixture-testbed plan. Hardest fixture in the set.
- Complex styled/structural review surfaces are NOT generated — hand-build in the
  editor once, then snapshot (block-generation pin in the fixture-testbed plan).
