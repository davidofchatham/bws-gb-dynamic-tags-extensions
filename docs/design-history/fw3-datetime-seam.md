# Archive: FW-3's second piece, datetime reads through the per-source reader (SHIPPED, unreleased)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site (the per-source return-format reader beside `bws_read_resolved_source_value()` in `includes/helpers/field-helpers.php`).

**Provenance.** Lifted 2026-11-23 from the feature spec the piece was built from, after PR #169 merged. The spec's `issues/01` and `issues/02` tickets were archived privately, not lifted. Decisions D5 and D11 name FW-194, which is still open.

---

**Status 2026-11-23: SHIPPED, unreleased** (PR #169, merged 2026-11-23, built as two tickets: the per-source reader prefactor with zero output movement, then the row date's return format). The open decision ("field-object formats through the seam") was settled in a grilling session (user, 2026-11-23); D1–D12 below. FW-191 is not a blocker (D3).

| Piece | State | Record |
|---|---|---|
| Term-ambient parity + resolved-source rethread | Shipped 1.15.0 | `docs/design-history/fw3-term-ambient-parity.md` + `tools/test/datetime-test-matrix.md` §D7 |
| List-collection payload | Shipped 1.16.0 as part of FW-49 | `docs/design-history/traversal-convergence-fw49.md` |
| Per-source return-format reader + value read onto the per-source reader | **Shipped, unreleased** (PR #169), D1–D12 | this file |

## The problem, restated against the current tree

A field's ACF return format does two jobs in datetime: it is the PARSE format for the stored string (`bws_get_acf_return_format()` and the second `get_field_object()` inside `bws_parse_acf_date_value()`, both in `datetime-helpers.php`), and it is the default DISPLAY format when the tag sets none (`bws_build_single_format()` / `bws_build_range_format()` read `$result['formats']`).

A repeater row from a `rows` chain step reaches neither: the step reads the repeater with formatted `get_field()` (`traversal-pipeline.php`, the `rows` case of `bws_run_step()`), so a row's sub-field value arrives in its return format, and the core's object-id derivation cannot address a sub-field. The parse falls to the common-format walk, which tries `m/d/Y` before `d/m/Y`: fixture row `review_dmy` = `04/03/2030` (meaning 4 March) prints `April 3, 2030`. `fold-test-matrix.md` §F9.5m4 shows it.

`bws_resolve_field_values()` cannot carry this: it reads one `key` per source and returns `string[]` (frozen), while datetime reads one to four fields per source (date/time × start/end) and pairs them per source (end time inherits start date). Datetime already shares L1 (`bws_base_read_source()`) and L3 (`bws_collect_value_list()`); only the per-field L2 read is its own.

## Decisions (2026-11-23)

- **D1 — Rows are in scope.** FW-3 is done only when a chain-row date parses with its configured format. Closing at "datetime keeps its own read, rows stay format-agnostic" was rejected. FW-74's waiting consumer is served.
- **D2 — "The seam" for datetime is the per-source L2 reader, not `bws_resolve_field_values()`.** That function and its frozen `string[]` return are untouched; no formats out-param.
- **D3 — FW-3 does not move fallback emission.** The cores keep printing the singular fallback; the resolver tail, `link_id` reporting and the link-wrap of a fallback are untouched. FW-191 moves from FW-3's `Blocked by` to `Interacts with` on both items. Taking FW-191 first was considered and declined: no shared code, and FW-191 still needs measuring before it can be decided.
- **D4 — The value read moves onto `bws_read_resolved_source_value()` for term, id-carrying post, site and row.** Measured basis for site (2026-11-23, testbed): `bws_site_read_option()` and the datetime `'option'` branch of `bws_read_field()` return the same formatted value for ACF options-page fields. The measured fact behind that lives in the private shared reference library; shipped code and this record state only the consequence. Term and id-carrying post reads are the same function the reader calls.
- **D5 — ONE stated exception: an entity-less post source keeps `bws_read_field()`'s loop inference.** It is the issue #22 path (a GB Pro query loop over repeater rows falls through the resolver's post tail with no id, and `bws_read_field()` re-infers the loop row); the reader's post arm answers `''` for id 0. The value-read closure's comment names the exception and FW-194, which removes it. Not datetime's: every family's base read reaches that same inference.
- **D6 — Delete the datetime `'option'` branch in `bws_read_field()`.** Its only caller was datetime's site read (its own comment says so); D4 orphans it. Grep for other `'option'` callers before deleting. Trips the GB trust-model trigger row.
- **D7 — New sibling reader beside `bws_read_resolved_source_value()` in `field-helpers.php`:** `(source, key) → ?string` return format, kind-dispatched:
  - post with id → `id`
  - term → `{taxonomy}_{id}`
  - site → `'option'`
  - meta_row WITH provenance → the parent's ACF object id (post `id`, term `{taxonomy}_{id}` — check the term parent carries its taxonomy, site `'option'`, user `user_{id}`), then `get_field_object( repeater, parent )['sub_fields']` matched on the sub-field `name`
  - post with no id → today's `bws_resolve_acf_object_id()` path, unchanged (still fails on loop rows; FW-194)
  - anything else, a nested row (`parent_kind` meta_row), no ACF → `null`
  No top-level user arm: datetime does not serve user.
- **D8 — Return the `return_format` string only.** `bws_parse_acf_date_value()` takes the format as an argument instead of a field key + object id, so each field costs one `get_field_object()`, not two. `bws_get_acf_return_format()` and the core's `$acf_object_id` derivation are deleted (check for callers outside the core first).
- **D9 — A row's return format is also its default display format,** the same rule as a page-level field. Visible change: fixture `contract_start`/`contract_end` (return `Y-m-d`) make §F9.5m, m5, n, n2 print ISO dates; §F9.5m4 prints `04/03/2030, 22/11/2030`. CHANGELOG `Changed`. If the ISO output reads badly on the fixture page, change the fixture's return format, not the rule.
- **D10 — Nested repeater rows stay format-agnostic,** stated at the new reader. A nested row records `parent_kind: meta_row`, `parent_id: 0` and nothing about its grandparent; walking up needs the source shape widened (Resolved-source SHAPE change trigger). No consumer reads a nested date today.
- **D11 — Query-loop repeater rows (the D5 path) are out of scope:** FW-194 tracks giving that row a real source the reader serves (value and format), blocked on measuring what GB Pro's loop context exposes. When FW-194 lands, D5's exception and the closure collapse to the reader alone.
- **D12 — Branch + PR,** not direct to `main`: visible output change, CHANGELOG entry, code + fixture + baseline + docs.

**Settles the FW-113 branch:** `bws_base_datetime_resolve_value()`'s ambient-term branch (`'term' === $ambient['link_type']`) stays — the core takes a term source, which is D2's shape. Its comment changes from "gated on FW-3" to the accepted end state.

## Build checklist

- [x] Reader (D7/D8) in `field-helpers.php` + pure harness section in `tools/test/read-resolved-source-test.php`, one case per arm against a stubbed `get_field_object()`, incl. the `null` arms
- [x] `bws_parse_acf_date_value()` takes the format; `bws_parse_combined_date_time()` asks the reader once per field; delete `bws_get_acf_return_format()` + `$acf_object_id`
- [x] Value-read closure → `bws_read_resolved_source_value()` except the D5 exception; comment names FW-194
- [x] Delete the `'option'` branch in `bws_read_field()` (D6)
- [x] Ambient-term branch comment (FW-113 settle)
- [x] Fixture: rewrite §F9.5m/m4/m5/n/n2 labels in `blocks.php`, the `review_dmy` comment in `schema.php`, manifest comment, `fold-test-matrix.md` rows; reseed; re-capture page-snapshot baseline in the same commit
- [x] `tag-reference.md` (datetime auto format on a row), CHANGELOG `[Unreleased]` `Changed`
- [x] Tracker: FW-3 to Closed ledger; FW-74's "ships format-agnostic until FW-3" prose; FW-194 Interacts

## Verification

- Pure: `datetime-format-test.php`, `datetime-migration-test.php`, `read-resolved-source-test.php`, `gb-trust-boundary-test.php` (D6), `traversal-pipeline-test.php`, `loop-item-classify-test.php`.
- Testbed: `datetime-test-matrix.md` D-rows (D5 site rows must not move), `fold-test-matrix.md` §F9.5, `verify.php`'s GB-trust-model section (D6), `php tools/test/page-snapshots.php` — only the D9 rows may move; anything else moving is a defect.

## Live constraints

- **FW-35** (all-day boolean, `$include_time` in the range core) must survive untouched.
- **One parse site.** `bws_normalize_datetime_options()` is the only public-key parse point; nothing here adds a second.
