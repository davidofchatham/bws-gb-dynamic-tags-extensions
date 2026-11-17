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
| D4.8 | post-meta | `…|limit:5|linkTo:permalink` | each date in its own `<a>` to the staff post it was read from — the datetime arms inherit per-item wrapping from the shared list fold, not from a datetime edit (FW-85) | single result, wrapped |
| D4.8b | post-meta | `{{datetime_range src:ref|ref:related_staff|startKey:event_datetime|endKey:event_end_datetime|limit:3|sep:; |linkTo:permalink}}` | each whole RANGE in its own `<a>` to the staff post it was read from, `; ` outside both anchors — the range arm inherits per-item wrapping from the same shared fold as `datetime_single`, which is what makes D4.8 a fold result rather than a single-arm edit | first range only, wrapped |
| D4.9 | post-meta | `…|linkTo:permalink` (limit 1) | `<a href="…/staff/jane-partner/">May 1, 2030 10:00 AM</a>` (single result stays wrapped) | same |

## D5 — sources + fallback (`/matrix-post-meta/`)

| # | Tag | Baseline output |
|---|---|---|
| D5.1 | `{{datetime_single src:site|key:organization_founded}}` | `20200115` (auto = the field's `Ymd` return format, verbatim — set `format:` for display) |
| D5.2 | `{{datetime_single src:site|key:organization_founded|format:F j, Y}}` | `January 15, 2020` |
| D5.3 | `{{datetime_single src:site|key:org_party_datetime}}` | `September 20, 2030 6:00 PM` (DT-1 `'option'` value read) |
| D5.4 | `{{datetime_single src:ref|ref:related_staff|key:event_datetime}}` | `May 1, 2030 10:00 AM` (single ref read, jane first) |
| D5.5 | `{{datetime_single key:event_datetime}}` + `--loop-item=<jane id>` | `May 1, 2030 10:00 AM` (the loop item wins over page context). **render-tag-only** — synthetic loop item, no fixture query loop on the page (stated exception to the visible-rows rule) |
| D5.6 | `{{datetime_single key:missing_dt_field|fallback:Date TBA}}` | `Date TBA` |
| D5.7 | `{{datetime_single src:site|key:org_party_datetime}}` on `/department/support/` | `September 20, 2030 6:00 PM` (the option, not the queried term). **render-tag-only** — needs a term archive as ambient context (stated exception to the visible-rows rule) |
| D5.8 | the same tag inside a post query loop | `September 20, 2030 6:00 PM` on every item (the option, not the loop post). Visible on `/matrix-loops/` as QL6.1; `--loop-item=<id>` reproduces it under render-tag |
| D5.9 | the same tag inside a repeater (post-meta) query loop | `September 20, 2030 6:00 PM` on every row (the option, not the row). Visible on `/matrix-repeaters/` as QL6.2 |

`src:site` range rows: see [`src-site-test-matrix.md`](src-site-test-matrix.md) §R5 (R5.3) — linked, not duplicated.

## D6 — editor preview (manual, testbed wp-admin)

Datetime previews are **excluded from the pure preview harness** (live-clock `wp_date`, documented exclusion in `preview-label-test.php`) — these editor rows are their only net. Schema: [`docs/editor-tag-previews.md`](../../docs/editor-tag-previews.md) datetime rows (authoritative; link, don't duplicate).

| # | Check |
|---|---|
| D6.1 | Open `/matrix-post-meta/` in the editor: an unresolved datetime block shows `[Date-Time like "…"]` (live current time in the active format), range blocks `[Date-Time Range like "…"]`, `as:` variants swap the prefix (Date / Time / Date Range / Time Range). |
| D6.2 | `limit` / `sep` controls appear ONLY when `srcTermIn` is set or `src` = Related Post Field (post-#30; conditional-options JS). |
| D6.3 | A datetime tag whose source is a pinned post or term (`src:post,<id>`, `src:term,<id>`), a repeater row (`src:rows,<field>`) or a related post (`src:ref`), with a `key` that reads empty and `fallback:Date TBA`: the preview shows the description with the fallback noted, e.g. `[Date-Time like "…" from Post: … (fallback: “Date TBA”)]`, matching `{{text}}`, not the bare `Date TBA`. A tag with no `src` shows the bare fallback. A resolved value shows the value. Measured with `render-tag --preview` on 2026-11-17 (all four sources); the editor itself has not been opened for this row. |

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

## D8 — all-day range + ungated consolidation (`/matrix-post-meta/`) — #125 / #126

Two independent defects, both in the time-range formatters, both fixed 1.19.1.

**#125** — `showMidnight` gated AM/PM consolidation as well as midnight suppression, so turning it on silently expanded `9:00–11:30 AM` to `9:00 AM–11:30 AM`. The approved row in `deprecated-tags-options.md` had called for consolidation to be always-on since 1.6.0; the rename and converter shipped then, the ungating did not. Consolidation is now independent of the flag and gated only by the format being 12-hour (that gate is deliberate — #25 / FW-12).

**#126** — `23:59`/`23:59:59` on the END of a range is now read as "no end time known", mirroring `00:00` on the start side, so the all-day encoding `00:00`–`23:59` renders as bare dates. The asymmetry is deliberate: `23:59` as a START is a real time. Both spans were affected — same-day through `bws_format_time_range()` and multi-day through `bws_format_multi_day_range()`, which strips each end independently but recognised `00:00` only. #126's own body claimed the multi-day half was already correct; it was not.

Fixture: `event_allday_end` = `2030-08-12 23:59` and `event_allday_end_multi` = `2030-08-14 23:59`, both paired with the existing `event_midnight` = `2030-08-12 00:00` as the start (blueprint v19 — NEW fixture state, reseed required). Lowercase meridiem in the `as:time` expectations follows the testbed's WP `time_format` of `g:i a`, same as D2.5/D2.6.

| # | Tag | Expected (post-fix) | Pre-fix |
|---|---|---|---|
| D8.1 | `{{datetime_range startKey:event_midnight|endKey:event_allday_end}}` | `August 12, 2030` | `August 12, 2030, 11:59 PM` |
| D8.2 | `{{datetime_range startKey:event_midnight|endKey:event_allday_end_multi}}` | `August 12–August 14, 2030` (day-collapse still blocked by the format's time token, as D2.3/R11) | `August 12–August 14, 2030 11:59 PM` |
| D8.3 | `… |endKey:event_allday_end|as:time` | empty (no time part on either end) | `11:59 pm` |
| D8.4 | `… |endKey:event_allday_end|as:time|showMidnight` | `12:00 am–11:59 pm` (the flag opts both ends back in) | `12:00 am–11:59 pm` (unchanged) |
| D8.5 | `{{datetime_range startKey:event_time|endKey:event_end_time|as:time|showMidnight}}` | `9:00–11:30 am` (**consolidates despite showMidnight** — the #125 fix) | `9:00 am–11:30 am` |
| D8.6 | `…startKey:event_datetime|endKey:event_end_datetime|as:time|showMidnight` | `9:00 am–5:00 pm` (cross-meridiem, both sides full either way) | `9:00 am–5:00 pm` (unchanged) |
| D8.7 | `…startKey:event_time|endKey:event_end_time|as:time|format:H:i|showMidnight` | `09:00–11:30` (24-hour: the format gate still blocks consolidation) | `09:00–11:30` (unchanged) |

D8.4/D8.6/D8.7 are pinned-unchanged rows, and they carry the load here: they are what separates "consolidation was ungated" from "the format gate or the sentinel was widened too far".

Also worth knowing when reading D8.1: the same-instant case (`start === end`) now collapses to one value instead of rendering `X–X`. No row covers it, because the one production caller (`bws_format_same_day_range()`) only calls the time formatter when the two ends differ — it is reachable only through a direct call to the documented helper, and `datetime-format-test.php` §T23–T25 pins it there.

## D9 — the merged `{{datetime}}` tag (FW-81, Unreleased)

One tag, no stated mode: how many `;`-separated entries `key:` holds decides it. One entry is a single value, two are a range, and a `+` joins at most two operands inside one entry (a date or datetime field, then an optional time field). `{{datetime}}` renders through `bws_base_datetime_resolve_value()`, the one seam the two old tags and their two `try_` templates also read through, each with its family fixed, so an old tag keeps its family whatever its options say (D9.54 to D9.57) and still renders byte-identically.

**Measured 2026-11-17 (datetime-tag-collapse/02):** 66 old-tag renders across `/matrix-post-meta/`, the three term pages and the two department archives, taken on the PREVIOUS build, then every one rendered again as the merged tag fed its equivalent wire (`startKey`+`startTimeKey` become `a+b`, the range keys become two `;` entries, a single-ended range keeps its trailing `;`). All 66 old renders were unchanged and all 66 merged renders were byte-identical to the previous build's output, 64 of them non-empty. The two empty ones are the matrix's own expected-empty rows (D8.3, D7.7).

**A one-ended range and a single value differ for a time-only display, and that is a defect to unify, not a spelling rule.** The single and range cores resolve the time format separately. Measured 2026-11-17 on the testbed against the same field: `as:time` on `event_datetime` prints `9:00 AM` as a single and `9:00 am` as a one-ended range; `as:time` on `event_time` the same; `event_time` with no `as` prints `9:00 AM` against `Start time: 9:00 AM`; `as:time` on `event_midnight` prints nothing against `12:00 am`. Every other pair tried (dates, `as:date`, custom formats, a separate time field, the current-year flag) was identical. D9.15 and D9.16 below pin the range core's current output through a trailing `;` so the equivalence stays measurable; they do not endorse it. Tracked as `datetime-tag-collapse/02c`.

Every row below is a visible block on `/matrix-datetime/` (blueprint v43, D9.54 to D9.57 in v44, D9.58 to D9.67 in v45), pinned to `/matrix-post-meta/`'s fields by id where it reads one, and its label states the expected output. "Same as" names the old row whose output it must match byte for byte.

| # | Tag | Same as | Expected |
|---|---|---|---|
| D9.1 | `{{datetime src:post,<meta id>|key:event_start_date}}` | D0.1 | `August 1, 2030` |
| D9.2 | `{{datetime src:post,<meta id>|key:event_datetime}}` | D0.2 | `August 12, 2030 9:00 AM` |
| D9.3 | `{{datetime src:post,<meta id>|key:event_datetime|format:Y-m-d}}` | D0.3 | `2030-08-12 9:00 am` |
| D9.4 | `{{datetime src:post,<meta id>|key:event_thisyear}}` | D0.4 | `April 10` |
| D9.5 | `{{datetime src:post,<meta id>|key:event_thisyear|showCurrentYear}}` | D0.5 | `April 10, ` and the current year |
| D9.6 | `{{datetime src:post,<meta id>|key:event_midnight}}` | D0.6 | `August 12, 2030` |
| D9.7 | `{{datetime src:post,<meta id>|key:event_midnight|showMidnight}}` | D0.7 | `August 12, 2030 12:00 AM` |
| D9.8 | `{{datetime src:post,<meta id>|key:event_start_date+event_time}}` | D0.8 | `August 1, 2030, 9:00 am` |
| D9.9 | `{{datetime src:post,<meta id>|key:event_start_date+event_time|timeSep: @ }}` | D0.9 | `August 1, 2030 @ 9:00 am` |
| D9.10 | `{{datetime src:post,<meta id>|key:plain_meta_date}}` | D0.10 | `June 15, 2030` |
| D9.11 | `{{datetime src:post,<meta id>|key:event_date_dmy}}` | D0.11 | `15/08/2030` |
| D9.12 | `{{datetime src:post,<meta id>|key:event_datetime|as:date}}` | D1.1 | `August 12, 2030` |
| D9.13 | `{{datetime src:post,<meta id>|key:event_datetime|as:time}}` | D1.2 | `9:00 AM` |
| D9.14 | `{{datetime src:post,<meta id>|key:event_time|as:time}}` | D1.3 | `9:00 AM` |
| D9.15 | `{{datetime src:post,<meta id>|key:event_datetime;|as:time}}` | D1.4 | `9:00 am` |
| D9.16 | `{{datetime src:post,<meta id>|key:event_datetime;|as:time|format:H:i}}` | D1.5 | `09:00` |
| D9.17 | `{{datetime src:post,<meta id>|key:event_start_date;event_end_date}}` | D2.1 | `August 1–9, 2030` |
| D9.18 | `{{datetime src:post,<meta id>|key:event_start_date;event_end_date|rangeSep: to }}` | D2.2 | `August 1 to 9, 2030` |
| D9.19 | `{{datetime src:post,<meta id>|key:event_datetime;event_end_datetime}}` | D2.3 | `August 12, 2030, 9:00 AM–5:00 PM` |
| D9.20 | `{{datetime src:post,<meta id>|key:event_midnight;event_end_datetime}}` | D2.4 | `August 12, 2030, 5:00 PM` |
| D9.21 | `{{datetime src:post,<meta id>|key:event_datetime;event_end_datetime|as:time}}` | D2.5 | `9:00 am–5:00 pm` |
| D9.22 | `{{datetime src:post,<meta id>|key:event_time;event_end_time|as:time}}` | D2.6 | `9:00–11:30 am` |
| D9.23 | `{{datetime src:post,<meta id>|key:event_datetime;event_end_datetime|as:time|format:H:i}}` | D3.1 | `09:00–17:00` |
| D9.24 | `{{datetime src:post,<meta id>|key:event_datetime;event_end_datetime|as:time|format:g:i}}` | D3.2 | `9:00–5:00` |
| D9.25 | `{{datetime src:post,<meta id>|key:event_time;event_end_time|as:time|format:g:i A}}` | D3.3 | `9:00–11:30 AM` |
| D9.26 | `{{datetime src:post,<meta id>|key:event_midnight;event_end_datetime|as:time|format:H:i}}` | D3.4 | `17:00` |
| D9.27 | `{{datetime src:post,<meta id>|key:event_midnight;event_allday_end}}` | D8.1 | `August 12, 2030` |
| D9.28 | `{{datetime src:post,<meta id>|key:event_midnight;event_allday_end_multi}}` | D8.2 | `August 12–August 14, 2030` |
| D9.29 | `{{datetime src:post,<meta id>|key:event_midnight;event_allday_end|as:time}}` | D8.3 | empty |
| D9.30 | `{{datetime src:post,<meta id>|key:event_midnight;event_allday_end|as:time|showMidnight}}` | D8.4 | `12:00 am–11:59 pm` |
| D9.31 | `{{datetime src:post,<meta id>|key:event_time;event_end_time|as:time|showMidnight}}` | D8.5 | `9:00–11:30 am` |
| D9.32 | `{{datetime src:post,<meta id>|key:event_datetime;event_end_datetime|as:time|showMidnight}}` | D8.6 | `9:00 am–5:00 pm` |
| D9.33 | `{{datetime src:post,<meta id>|key:event_time;event_end_time|as:time|format:H:i|showMidnight}}` | D8.7 | `09:00–11:30` |
| D9.34 | `{{datetime src:post,<meta id>|key:event_start_date+event_time;event_end_date+event_end_time}}` | new combination | `August 1, 9:00 am–August 9, 2030, 11:30 am` |
| D9.35 | `{{datetime src:post,<meta id>|key:event_start_date+event_time;+event_end_time}}` | new combination | `August 1, 2030, 9:00–11:30 am` |
| D9.36 | `{{datetime src:post,<meta id>|key:event_datetime;+event_end_time}}` | new combination | `August 12, 2030, 9:00–11:30 AM` |
| D9.37 | `{{datetime src:post,<meta id>|key:event_start_date+event_time;}}` | new combination | `August 1, 2030, 9:00 am` |
| D9.38 | `{{datetime src:post,<meta id>|key:event_start_date;missing_field|fallback:FB}}` | new combination | `August 1, 2030` |
| D9.52 | `{{datetime src:post,<meta id>|key:event_start_date+event_time;event_end_time}}` | new combination | `August 1, 2030, 9:00–11:30 am` |
| D9.53 | `{{datetime src:post,<meta id>|key:event_datetime;event_end_time}}` | new combination | `August 12, 2030, 9:00–11:30 AM` |
| D9.54 | `{{datetime_range src:post,<meta id>|key:event_datetime|as:time}}` | new combination | `9:00 am` (the range core prints `am` lowercase; the single core prints `9:00 AM`, so this row tells the two cores apart) |
| D9.55 | `{{datetime_single src:post,<meta id>|key:event_start_date;event_end_date}}` | new combination | `August 1, 2030` (a single tag reads only the first entry) |
| D9.56 | `{{try_datetime_range src:post,<meta id>|key:event_datetime|as:time}}` | D9.54 | `9:00 am` |
| D9.57 | `{{try_datetime_single src:post,<meta id>|key:event_start_date;event_end_date}}` | D9.55 | `August 1, 2030` |
| D9.39 | `{{datetime src:post,<meta id>;refs,related_staff,limit[5]|key:event_datetime}}` | D4.6 | `May 1, 2030 10:00 AM, June 1, 2030 11:00 AM` |
| D9.40 | `{{datetime src:post,<meta id>;refs,related_staff,limit[3]|key:event_datetime;event_end_datetime|sep:; }}` | D4.7 | `May 1 10:00 AM–May 3, 2030 3:00 PM; June 1 11:00 AM–June 5, 2030 12:00 PM` |
| D9.41 | `{{datetime src:post,<meta id>;refs,related_staff,limit[5]|key:event_datetime|linkTo:permalink}}` | D4.8 | `<a href="https://testbed.test/staff/jane-partner/">May 1, 2030 10:00 AM</a>, <a href="https://testbed.test/staff/tom-associate/">June 1, 2030 11:00 AM</a>` |
| D9.42 | `{{datetime src:post,<meta id>;refs,related_staff,limit[3]|key:event_datetime;event_end_datetime|sep:; |linkTo:permalink}}` | D4.8b | `<a href="https://testbed.test/staff/jane-partner/">May 1 10:00 AM–May 3, 2030 3:00 PM</a>; <a href="https://testbed.test/staff/tom-associate/">June 1 11:00 AM–June 5, 2030 12:00 PM</a>` |
| D9.43 | `{{datetime src:post,<meta id>;refs,related_staff,limit[1]|key:event_datetime|linkTo:permalink}}` | D4.9 | `<a href="https://testbed.test/staff/jane-partner/">May 1, 2030 10:00 AM</a>` |
| D9.44 | `{{datetime src:site|key:organization_founded}}` | D5.1 | `20200115` |
| D9.45 | `{{datetime src:site|key:organization_founded|format:F j, Y}}` | D5.2 | `January 15, 2020` |
| D9.46 | `{{datetime src:site|key:org_party_datetime}}` | D5.3 | `September 20, 2030 6:00 PM` |
| D9.47 | `{{datetime src:post,<meta id>;refs,related_staff,limit[1]|key:event_datetime}}` | D5.4 | `May 1, 2030 10:00 AM` |
| D9.48 | `{{datetime key:missing_dt_field|fallback:Date TBA}}` | D5.6 | `Date TBA` |
| D9.49 | `{{datetime fallback:No field}}` | new combination | `No field` |
| D9.50 | `{{datetime src:site|key:org_party_datetime;}}` | R5.3 | `September 20, 2030 6:00 PM` |

**A pinned post or term prints the stated fallback on an empty read**, unwrapped even with `linkTo`, as `{{text}}` does for a pinned source. A repeater-row source does too.

| # | Tag | Same as | Expected |
|---|---|---|---|
| D9.58 | `{{datetime src:post,<meta id>|key:missing_dt_field|fallback:Date TBA}}` | D9.48 | `Date TBA` |
| D9.59 | `{{datetime_single src:post,<meta id>|key:missing_dt_field|fallback:Date TBA}}` | D9.58 | `Date TBA` |
| D9.60 | `{{datetime_range src:post,<meta id>|startKey:missing_a|endKey:missing_b|fallback:FB}}` | D9.58 | `FB` |
| D9.61 | `{{datetime src:post,<meta id>|key:event_start_date|fallback:Date TBA}}` | control | `August 1, 2030` (the value wins, fallback idle) |
| D9.62 | `{{datetime src:term,<sales id>|key:missing_dt_field|fallback:Date TBA}}` | D9.58 | `Date TBA` |
| D9.63 | `{{datetime_single src:term,<warehouse id>|key:event_date|fallback:Date TBA}}` | D7.6 | `Date TBA` (a real key, an empty term field) |
| D9.64 | `{{datetime_range src:term,<sales id>|startKey:missing_a|endKey:missing_b|fallback:FB}}` | D9.60 | `FB` |
| D9.65 | `{{datetime src:term,<sales id>|key:event_date|fallback:Date TBA}}` | control | `November 12, 2030` (the value wins, fallback idle) |
| D9.66 | `{{datetime src:post,<meta id>|key:missing_dt_field|fallback:Date TBA|linkTo:permalink}}` | new combination | `Date TBA` with no link around it |
| D9.67 | `{{datetime src:post,<meta id>|key:missing_dt_field}}` | new combination | empty (no fallback stated) |

**Render-tag only** (a visible block cannot host them): the ambient and loop-item rows, and one empty-read row.

| # | Tag | Same as | Expected |
|---|---|---|---|
| D9.T1 | `{{datetime srcTermIn:department|key:event_date|limit:5}}` on `/matrix-terms-valid/` | D4.1 | `November 12, 2030, October 5, 2030` |
| D9.T2 | `{{datetime srcTermIn:department|key:event_date;|limit:5|sep:; }}` on `/matrix-terms-valid/` | D4.5 | `November 12, 2030; October 5, 2030` |
| D9.T3 | `{{datetime key:event_date}}` on `/department/support/` | D7.1 | `October 5, 2030` (ambient term read) |
| D9.T4 | `{{datetime key:event_date;}}` on `/department/support/` | D7.5 | `October 5, 2030` |
| D9.T5 | `{{datetime key:event_date|fallback:Date TBA}}` on `/department/warehouse/` | D7.6 | `Date TBA` |
| D9.T6 | `{{datetime key:event_datetime}}` with `--loop-item=<jane id>` | D5.5 | `May 1, 2030 10:00 AM` |
| D9.T7 | `{{datetime key:event_datetime;event_end_datetime}}` with `--loop-item=<jane id>` | D5.5 | `May 1 10:00 AM–May 3, 2030 3:00 PM` (the loop item wins over page context) |
| D9.T8 | `{{datetime key:;event_end_date|fallback:FB}}` on `/matrix-post-meta/` | D26 | `FB` (an end with no start prints the fallback, as the old range does) |
| D9.T9 | `{{datetime key:event_start_date+event_time+event_end_time}}` | — | `August 1, 2030, 9:00 am` (a third operand is ignored; its warning is ticket 05) |
| D9.T10 | `{{datetime}}` (no `key`) | — | empty, and `fallback:No field` prints `No field` (the read-less tag shows its fallback) |
