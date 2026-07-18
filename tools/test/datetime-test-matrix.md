# `{{datetime_single}}` / `{{datetime_range}}` Regression Matrix

**Standing manual regression suite** for the two datetime base tags — not a one-shot plan checklist. Baseline captured 2026-07-18 on the fixture testbed (Stage 0b of the datetime pass, #48), BEFORE the FW-2 normalizer / #25 / #30 changes; behavior-preserving stages must keep every non-flagged row byte-identical.

> **Re-run trigger:** after any change to the datetime helpers (`bws_format_*`, `bws_build_*_format`, `bws_parse_combined_date_time`, `bws_resolve_time_only_format`), the option-key normalizer / mappers, the datetime core functions or base callbacks, the datetime option builders, or the datetime preview branch.
>
> **Two layers:**
> - **Algorithm (pure, automated):** `php tools/test/datetime-format-test.php` — 53 cases over normalization / format resolution / time-range (incl. the #25 custom-format group) / single / range / assembly. Run first; must be green before manual rows.
> - **Integration (manual, WP):** the D-rows below — ACF return-format dispatch, sources, traversal, list mode, link-wrap, preview. Testbed only, never the live/cached site.

**How to run:** on the fixture testbed, seed the `core-structures` blueprint (see [`tools/fixtures/core-structures/README.md`](../fixtures/core-structures/README.md)). The page `/matrix-post-meta/` renders D0–D3, D4.6–D4.9, and D5; term-hop rows (D4.1–D4.5) live on `/matrix-terms-valid|mixed|junk/`. Every row is also runnable standalone:

```
bin/wp.sh testbed bws render-tag '{{datetime_single …}}' --url=https://testbed.test/matrix-post-meta/ --porcelain
```

Batch capture: `bin/wp.sh testbed eval-file <repo>/tools/debug/datetime-baseline-capture.php --url=…` prints every row for a page.

Fixture keys are the blueprint's (`manifest.php` authoritative): `event_datetime` `2030-08-12 09:00:00` / `event_end_datetime` `…17:00:00` (cross-meridiem pair, return `F j, Y g:i A`), `event_time` `09:00` / `event_end_time` `11:30` (same-meridiem pair, return `g:i a`), `event_start_date` `20300801` / `event_end_date` `20300809` (return `F j, Y`), `event_midnight` (00:00), `event_thisyear` (current year Apr 10), `event_date_dmy` (return `d/m/Y`), plain meta `plain_meta_date` `2030-06-15`, term `event_date` (support Oct 5 / sales Nov 12 / warehouse EMPTY), option `organization_founded` (return `Ymd`) + `org_party_datetime`, staff pair `event_datetime`/`event_end_datetime` (jane May 1–3, tom Jun 1–5; jane is the first `related_staff` target).

**ACF junk-date trap (fixture note):** a junk *string* stored in an ACF date field is formatted by ACF itself to TODAY's date before the tag sees it — a junk value is therefore untestable as a skip case at the tag layer. The warehouse "junk" datetime fixture is EMPTY instead (the real-world skippable state).

---

## D0 — single basics (`/matrix-post-meta/`)

| # | Tag | Baseline output |
|---|---|---|
| D0.1 | `{{datetime_single key:event_start_date}}` | `August 1, 2030` (date-only field, auto = ACF return format) |
| D0.2 | `{{datetime_single key:event_datetime}}` | `August 12, 2030 9:00 AM` (combined field) |
| D0.3 | `{{datetime_single key:event_datetime|format:Y-m-d}}` | `2030-08-12 9:00 am` (custom date format; field's time gap-fills in ACF `g:i a`) |
| D0.4 | `{{datetime_single key:event_thisyear}}` | `April 10` (current year omitted by default) |
| D0.5 | `{{datetime_single key:event_thisyear|showCurrentYear}}` | `April 10, <current year>` |
| D0.6 | `{{datetime_single key:event_midnight}}` | `August 12, 2030` (midnight hidden by default) |
| D0.7 | `{{datetime_single key:event_midnight|showMidnight}}` | `August 12, 2030 12:00 AM` |
| D0.8 | `{{datetime_single key:event_start_date|timeKey:event_time}}` | `August 1, 2030, 9:00 am` (separate time field, default `, ` separator, lowercase per field format) |
| D0.9 | `{{datetime_single key:event_start_date|timeKey:event_time|timeSep: @ }}` | `August 1, 2030 @ 9:00 am` |
| D0.10 | `{{datetime_single key:plain_meta_date}}` | `June 15, 2030` (plain post meta, non-ACF read; WP `date_format` default) |
| D0.11 | `{{datetime_single key:event_date_dmy}}` | `15/08/2030` (non-default ACF `return_format` drives both parse and display) |

## D1 — `as:` narrowing (`/matrix-post-meta/`)

| # | Tag | Baseline output |
|---|---|---|
| D1.1 | `{{datetime_single key:event_datetime|as:date}}` | `August 12, 2030` |
| D1.2 | `{{datetime_single key:event_datetime|as:time}}` | `9:00 AM` |
| D1.3 | `{{datetime_single key:event_time|as:time}}` | `9:00 AM` (time-only field; hardcoded `g:i A` render path) |
| D1.4 | `{{datetime_range startKey:event_datetime|as:time}}` | `9:00 am` (single-ended; v1.7.4 resolver chain → ACF `time_format`… here WP default is shadowed by the field's `g:i a`) |
| D1.5 | `{{datetime_range startKey:event_datetime|as:time|format:H:i}}` | `09:00` (single-ended custom format honored) |

## D2 — range basics (`/matrix-post-meta/`)

| # | Tag | Baseline output |
|---|---|---|
| D2.1 | `{{datetime_range startKey:event_start_date|endKey:event_end_date}}` | `August 1–9, 2030` (same-month collapse) |
| D2.2 | `… |rangeSep: to ` | `August 1 to 9, 2030` |
| D2.3 | `{{datetime_range startKey:event_datetime|endKey:event_end_datetime}}` | `August 12, 2030, 9:00 AM–5:00 PM` (same-day, time range) |
| D2.4 | `{{datetime_range startKey:event_midnight|endKey:event_end_datetime}}` | `August 12, 2030, 5:00 PM` (midnight start suppressed, smart default) |
| D2.5 | `{{datetime_range startKey:event_datetime|endKey:event_end_datetime|as:time}}` | `9:00 am–5:00 pm` (cross-meridiem, both sides full; testbed WP `time_format` is `g:i a` — post-#25 the two-ended range rides the same resolver chain as single-ended D1.4. Pre-#25 baseline: `9:00 AM–5:00 PM` hardcoded) |
| D2.6 | `{{datetime_range startKey:event_time|endKey:event_end_time|as:time}}` | `9:00–11:30 am` (same-meridiem consolidation; lowercase per the fields' ACF `g:i a`, matching D1.4. Pre-#25 baseline: `9:00–11:30 AM` hardcoded) |

## D3 — #25 two-ended `as:time` custom format — ✅ shipped (Stage 2, testbed-verified)

Custom `format:` on a two-ended time range. Baseline (pre-#25) showed the custom format thrown away (D3.1/D3.2 rendered `9:00 AM–5:00 PM`); rows assert the post-#25 behavior.

| # | Tag | Expected (post-#25) | Pre-#25 baseline |
|---|---|---|---|
| D3.1 | `…startKey:event_datetime|endKey:event_end_datetime|as:time|format:H:i` | `09:00–17:00` (24-hour, both sides full, no consolidation) | `9:00 AM–5:00 PM` |
| D3.2 | `… |as:time|format:g:i` | `9:00–5:00` (meridiem-less 12-hour, both sides full) | `9:00 AM–5:00 PM` |
| D3.3 | `…startKey:event_time|endKey:event_end_time|as:time|format:g:i A` | `9:00–11:30 AM` (12-hour custom format still consolidates) | `9:00–11:30 AM` |
| D3.4 | `…startKey:event_midnight|endKey:event_end_datetime|as:time|format:H:i` | `17:00` (midnight suppression independent of format) | `5:00 PM` |

## D4 — #30 list mode (`limit` / `sep`) — ✅ shipped (Stage 3, testbed-verified incl. front-end after cache purge)

Mirrors base text/title list mode: slice to `limit` (default 1), join with `sep` (default `, `), skip empty per-item results, per-item fallback suppressed (fires only on all-empty), link-wrap only on exactly one result. Baseline (pre-#30) showed first-non-empty only. D4.1–D4.5 run on the term pages; page column notes which.

| # | Page | Tag | Expected (post-#30) | Pre-#30 baseline |
|---|---|---|---|---|
| D4.1 | terms-valid | `{{datetime_single srcTermIn:department|key:event_date|limit:5}}` | `November 12, 2030, October 5, 2030` (sales, support — term name order) | `November 12, 2030` |
| D4.2 | terms-valid | `… |key:event_date}}` (no limit) | `November 12, 2030` (limit defaults to 1). NB text/title-parity change: the default-limit slice consults the FIRST term only — an empty first term now yields the fallback instead of the old scan-to-first-non-empty result | same |
| D4.3 | terms-valid | `… |limit:5|sep: / ` | `November 12, 2030 / October 5, 2030` | `November 12, 2030` |
| D4.4 | terms-junk | `… |limit:5|fallback:Dates TBA` | `Dates TBA` (all terms empty → fallback once) | `Dates TBA` |
| D4.4b | terms-mixed | `… |limit:5|fallback:Dates TBA` | `November 12, 2030, October 5, 2030` (empty warehouse SKIPPED, fallback does NOT fire) | `November 12, 2030` |
| D4.5 | terms-valid | `{{datetime_range srcTermIn:department|startKey:event_date|limit:5|sep:; }}` | `November 12, 2030; October 5, 2030` (`sep` joins whole ranges; `rangeSep` stays intra-range) | `November 12, 2030` |
| D4.6 | post-meta | `{{datetime_single src:ref|ref:related_staff|key:event_datetime|limit:5}}` | `May 1, 2030 10:00 AM, June 1, 2030 11:00 AM` (jane, tom) | `May 1, 2030 10:00 AM` |
| D4.7 | post-meta | `{{datetime_range src:ref|ref:related_staff|startKey:event_datetime|endKey:event_end_datetime|limit:3|sep:; }}` | `May 1 10:00 AM–May 3, 2030 3:00 PM; June 1 11:00 AM–June 5, 2030 12:00 PM` | first range only |
| D4.8 | post-meta | `…|limit:5|linkTo:permalink` | multi-result → **unwrapped** list | single result, wrapped |
| D4.9 | post-meta | `…|linkTo:permalink` (limit 1) | `<a href="…/staff/jane-partner/">May 1, 2030 10:00 AM</a>` (single result stays wrapped) | same |

## D5 — sources + fallback (`/matrix-post-meta/`)

| # | Tag | Baseline output |
|---|---|---|
| D5.1 | `{{datetime_single src:site|key:organization_founded}}` | `20200115` (auto = the field's `Ymd` return format, verbatim — set `format:` for display) |
| D5.2 | `{{datetime_single src:site|key:organization_founded|format:F j, Y}}` | `January 15, 2020` |
| D5.3 | `{{datetime_single src:site|key:org_party_datetime}}` | `September 20, 2030 6:00 PM` (DT-1 `'option'` value read) |
| D5.4 | `{{datetime_single src:ref|ref:related_staff|key:event_datetime}}` | `May 1, 2030 10:00 AM` (single ref read, jane first) |
| D5.5 | `{{datetime_single key:event_datetime}}` + `--loop-item=<jane id>` | `May 1, 2030 10:00 AM` (loop row wins over page context). **render-tag-only** — synthetic loop row, no fixture query loop on the page (stated exception to the visible-rows rule) |
| D5.6 | `{{datetime_single key:missing_dt_field|fallback:Date TBA}}` | `Date TBA` |

`src:site` range rows: see [`src-site-test-matrix.md`](src-site-test-matrix.md) §R5 (R5.3) — linked, not duplicated.

## D6 — editor preview (manual, testbed wp-admin)

Datetime previews are **excluded from the pure preview harness** (live-clock `wp_date`, documented exclusion in `preview-label-test.php`) — these editor rows are their only net. Schema: [`docs/editor-tag-previews.md`](../../docs/editor-tag-previews.md) datetime rows (authoritative; link, don't duplicate).

| # | Check |
|---|---|
| D6.1 | Open `/matrix-post-meta/` in the editor: an unresolved datetime block shows `[Date-Time like "…"]` (live current time in the active format), range blocks `[Date-Time Range like "…"]`, `as:` variants swap the prefix (Date / Time / Date Range / Time Range). |
| D6.2 | `limit` / `sep` controls appear ONLY when `srcTermIn` is set or `src` = Related Post Field (post-#30; conditional-options JS). |

## D7 — FW-3(a) term-ambient parity — ✅ shipped (testbed-verified 2026-07-18)

Bare datetime tags on a taxonomy archive read the ambient term's date field, matching the base
text/title I1 analog behavior. Pre-FW-3 baseline: post-only resolution → honest-empty (fallback
fires). All rows verified post-rethread; every other matrix row byte-identical to the pre-FW-3
capture.

**render-tag-only** (stated exception to the visible-rows rule): rows need a term ARCHIVE as
ambient context, which can't host page blocks — same exception as text T4. Run:

```
bin/wp.sh testbed bws render-tag '{{datetime_single key:event_date}}' --url=https://testbed.test/department/support/ --porcelain
```

Fixture: term `event_date` — support `October 5, 2030` / warehouse EMPTY (blueprint v3, already
seeded — no new fixture state).

| # | URL context | Tag | Expected (post-FW-3) | Pre-FW-3 baseline |
|---|---|---|---|---|
| D7.1 | support | `{{datetime_single key:event_date}}` | `October 5, 2030` (ambient term read, field return format) | empty |
| D7.2 | support | `{{datetime_single key:event_date|format:Y-m-d}}` | `2030-10-05` | empty |
| D7.3 | support | `{{datetime_single key:event_date|fallback:Date TBA}}` | `October 5, 2030` (value wins, fallback idle) | `Date TBA` |
| D7.4 | support | `{{datetime_single key:event_date|as:date}}` | `October 5, 2030` | empty |
| D7.5 | support | `{{datetime_range startKey:event_date}}` | `October 5, 2030` (single-ended range off the term) | empty |
| D7.6 | warehouse | `{{datetime_single key:event_date|fallback:Date TBA}}` | `Date TBA` (empty term field stays honest-empty → fallback) | `Date TBA` |
| D7.7 | support | `{{datetime_single key:event_date|as:time}}` | empty (date-only term field has no time portion; midnight suppressed by smart default — same as the post-source rule) | empty |
