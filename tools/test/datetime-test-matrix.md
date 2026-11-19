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
| D1.3 | `{{datetime_single key:event_time|as:time}}` | `9:00 am` (time-only field, in its own return format `g:i a`) |
| D1.4 | `{{datetime_range startKey:event_datetime|as:time}}` | `9:00 AM` (single-ended, the field's own `F j, Y g:i A` tokens, not the WP `time_format`) |
| D1.5 | `{{datetime_range startKey:event_datetime|as:time|format:H:i}}` | `09:00` (single-ended custom format honored) |

## D2 — range basics (`/matrix-post-meta/`)

| # | Tag | Baseline output |
|---|---|---|
| D2.1 | `{{datetime_range startKey:event_start_date|endKey:event_end_date}}` | `August 1–9, 2030` (same-month collapse) |
| D2.2 | `… |rangeSep: to ` | `August 1 to 9, 2030` |
| D2.3 | `{{datetime_range startKey:event_datetime|endKey:event_end_datetime}}` | `August 12, 2030, 9:00 AM–5:00 PM` (same-day, time range) |
| D2.4 | `{{datetime_range startKey:event_midnight|endKey:event_end_datetime}}` | `August 12, 2030, 5:00 PM` (midnight start suppressed, smart default) |
| D2.5 | `{{datetime_range startKey:event_datetime|endKey:event_end_datetime|as:time}}` | `9:00 AM–5:00 PM` (cross-meridiem, both sides full, in the start field's own tokens) |
| D2.6 | `{{datetime_range startKey:event_time|endKey:event_end_time|as:time}}` | `9:00–11:30 am` (same-meridiem consolidation; lowercase per the fields' own `g:i a`, matching D1.3) |

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

The pure preview harness pins datetime labels with the live-clock sample masked (`preview-label-test.php`), so the sample value itself and the editor are these rows' to check. Schema: [`docs/editor-tag-previews.md`](../../docs/editor-tag-previews.md) datetime rows (authoritative; link, don't duplicate).

| # | Check |
|---|---|
| D6.1 | Open `/matrix-post-meta/` in the editor: an unresolved datetime block shows `[Date-Time like "…"]` (live current time in the active format), range blocks `[Date-Time Range like "…"]`, `as:` variants swap the prefix (Date / Time / Date Range / Time Range). |
| D6.2 | `limit` / `sep` controls appear ONLY when `srcTermIn` is set or `src` = Related Post Field (post-#30; conditional-options JS). |
| D6.3 | A datetime tag whose source is a pinned post or term (`src:post,<id>`, `src:term,<id>`), a repeater row (`src:rows,<field>`) or a related post (`src:ref`), with a `key` that reads empty and `fallback:Date TBA`: the preview shows the description with the fallback noted, e.g. `[Date-Time like "…" from Post: … (fallback: “Date TBA”)]`, matching `{{text}}`, not the bare `Date TBA`. A tag with no `src` shows the bare fallback. A resolved value shows the value. Measured with `render-tag --preview` on 2026-11-17 (all four sources); the editor itself has not been opened for this row. |
| D6.4 | `{{datetime}}` warnings (FW-81, Unreleased), each on a tag that reads empty (a missing field key, on `/matrix-post-meta/`): `{{datetime use:key;now}}` shows `[⚠ No field key set for read 1]`; `{{datetime use:key;key;now\|key:nonexistent_field}}` shows `[⚠ 2 field reads but only 1 field key set]`; `{{datetime key:nonexistent_a+b+c}}` shows `[⚠ Only a date and a time can be joined with +, so extra parts are ignored]`; `{{datetime_single key:nonexistent_a+b+c}}` shows no warning. A tag that still resolves (`{{datetime use:now;key\|key:nonexistent_a;nonexistent_b}}`, whose surplus key is ignored and whose start prints) shows its value and no warning. Measured with `render-tag --preview` on 2026-11-18 (all rows above), and the extra-operand label in the editor on 2026-11-19. `try_datetime` warns per slot: rows D6.4a to D6.4k. |
| D6.4a | `{{try_datetime A:key(nonexistent_a)}}` (FW-193, Unreleased): a single read previews `[Try Date-Time like “…”]`. Measured with `render-tag --preview` on `/matrix-post-meta/` 2026-11-19, as written (the sample is the live clock). A real render prints nothing. |
| D6.4b | `{{try_datetime A:key(nonexistent_a;nonexistent_b)}}`: two entries preview as a range, `[Try Date-Time Range like “…”]`. Measured with `render-tag --preview` on `/matrix-post-meta/` 2026-11-19, as written. A real render prints nothing. |
| D6.4c | `{{try_datetime A:key(nonexistent_a)\|B:key(nonexistent_a;nonexistent_b)}}`: slot A decides, so `[Try Date-Time like “…”]` although slot B reads a range. Measured with `render-tag --preview` on `/matrix-post-meta/` 2026-11-19, as written. A real render prints nothing. |
| D6.4d | `{{try_datetime A:key(nonexistent_a;nonexistent_b)\|B:key(nonexistent_a)}}`: `[Try Date-Time Range like “…”]` although slot B reads a single. Measured with `render-tag --preview` on `/matrix-post-meta/` 2026-11-19, as written. A real render prints nothing. |
| D6.4e | `{{try_datetime A:key(nonexistent_a;nonexistent_b)\|B:src(parent)}}`: slot B states no read and reuses A's, so the range shape with the source list, `[Try Date-Time Range like “…” from Current, Current Parent]`. Measured with `render-tag --preview` on `/matrix-post-meta/` 2026-11-19, as written, with the wording `from Current, Current Parent` exactly. `/matrix-post-meta/` has no parent, so `src(parent)` leaves the tag empty on a real render (measured, prints nothing). |
| D6.4f | `{{try_datetime A:src(parent)\|B:key(nonexistent_a;nonexistent_b)}}`: slot A states no read and has none to reuse, so the read-less single shape, `[Try Date-Time like “…” from Current Parent, Current]`, whatever slot B reads. Measured with `render-tag --preview` on `/matrix-post-meta/` 2026-11-19, as written, with the wording `from Current Parent, Current` exactly. A real render prints nothing (`src(parent)` has no parent here). |
| D6.4g | `{{try_datetime A:key(nonexistent_a+b+c)}}`: `[⚠ Try: A: Only a date and a time can be joined with +, so extra parts are ignored]`. Measured with `render-tag --preview` on `/matrix-post-meta/` 2026-11-19, as written. A real render prints nothing. |
| D6.4h | `{{try_datetime A:key(nonexistent_a)\|B:use(key;key;now);key(nonexistent_a)}}`: the warning names slot B, `[⚠ Try: B: 2 field reads but only 1 field key set]`. Measured with `render-tag --preview` on `/matrix-post-meta/` 2026-11-19, as written. A real render prints nothing. |
| D6.4i | `{{try_datetime A:use(key;now)\|B:use(key;now)}}`: both slots state a verb list with no field key, so each warns and the two join with `; `, `[⚠ Try: A: No field key set for read 1; B: No field key set for read 1]`. Measured with `render-tag --preview` on `/matrix-post-meta/` 2026-11-19, as written. A real render prints nothing: a `use(key;now)` with no field key reads empty (measured, not inferred). |
| D6.4j | `{{try_datetime A:key(nonexistent_a+b+c)\|B:src(parent)}}`: slot B reuses the read slot A states, so only A is named, `[⚠ Try: A: Only a date and a time can be joined with +, so extra parts are ignored]`. Measured with `render-tag --preview` on `/matrix-post-meta/` 2026-11-19, as written. A real render prints nothing. |
| D6.4k | `{{try_datetime A:use(key;now)\|B:use(published)}}`: slot A would warn, but slot B resolves, so the tag shows the post's published date and no warning. Measured with `render-tag --preview` on `/matrix-post-meta/` 2026-11-19: the preview prints `March 3, 2025` and no warning, and a real render prints the same, so slot A reads empty and slot B resolves (measured, not inferred). |
| D6.5 | The read control's notices (FW-81, Unreleased), in the tag's settings whatever the tag renders: `{{datetime use:now;key\|key:event_datetime;event_end_datetime}}` shows a warning naming `event_end_datetime` as removed on the next change, and changing a read then inserting writes `use:published;key\|key:event_datetime`; `{{datetime key:event_date+event_time+event_end_time}}` names `event_end_time` as ignored; `{{datetime key:event_datetime;event_end_datetime}}` shows none. Neither notice has a dismiss button, and each is announced in WordPress's polite live region. The Start/End headings match the "Date/Time Read" label's type. Measured in the testbed editor on 2026-11-19 (a new page, trashed after). Visible rows to open in the editor: section D6.5 on `/matrix-datetime/` (D6.5.1 a key entry no read takes, D6.5.2 a third `+` field, D6.5.3 a key entry past the most reads, which the control shows as two reads, D6.5.4 a clean range with no notice). |

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

Fixture: `event_allday_end` = `2030-08-12 23:59` and `event_allday_end_multi` = `2030-08-14 23:59`, both paired with the existing `event_midnight` = `2030-08-12 00:00` as the start (blueprint v19 — NEW fixture state, reseed required). The meridiem case in the `as:time` expectations is the start field's own: `g:i A` on the datetime fields, `g:i a` on the time-only ones (#164; the WP `time_format` is `g:i a` and is only the last fallback).

| # | Tag | Expected (post-fix) | Pre-fix |
|---|---|---|---|
| D8.1 | `{{datetime_range startKey:event_midnight|endKey:event_allday_end}}` | `August 12, 2030` | `August 12, 2030, 11:59 PM` |
| D8.2 | `{{datetime_range startKey:event_midnight|endKey:event_allday_end_multi}}` | `August 12–August 14, 2030` (day-collapse still blocked by the format's time token, as D2.3/R11) | `August 12–August 14, 2030 11:59 PM` |
| D8.3 | `… |endKey:event_allday_end|as:time` | empty (no time part on either end) | `11:59 pm` |
| D8.4 | `… |endKey:event_allday_end|as:time|showMidnight` | `12:00 AM–11:59 PM` (the flag opts both ends back in) | `12:00 am–11:59 pm` |
| D8.5 | `{{datetime_range startKey:event_time|endKey:event_end_time|as:time|showMidnight}}` | `9:00–11:30 am` (**consolidates despite showMidnight** — the #125 fix) | `9:00 am–11:30 am` |
| D8.6 | `…startKey:event_datetime|endKey:event_end_datetime|as:time|showMidnight` | `9:00 AM–5:00 PM` (cross-meridiem, both sides full either way) | `9:00 am–5:00 pm` |
| D8.7 | `…startKey:event_time|endKey:event_end_time|as:time|format:H:i|showMidnight` | `09:00–11:30` (24-hour: the format gate still blocks consolidation) | `09:00–11:30` (unchanged) |

D8.4/D8.6/D8.7 are pinned-unchanged rows, and they carry the load here: they are what separates "consolidation was ungated" from "the format gate or the sentinel was widened too far".

Also worth knowing when reading D8.1: the same-instant case (`start === end`) now collapses to one value instead of rendering `X–X`. No row covers it, because the one production caller (`bws_format_same_day_range()`) only calls the time formatter when the two ends differ — it is reachable only through a direct call to the documented helper, and `datetime-format-test.php` §T23–T25 pins it there.

## D9 — the merged `{{datetime}}` tag (FW-81, Unreleased)

One tag, no stated mode: how many `;`-separated entries `key:` holds decides it. One entry is a single value, two are a range, and a `+` joins at most two operands inside one entry (a date or datetime field, then an optional time field). `{{datetime}}` renders through `bws_base_datetime_resolve_value()`, the one seam the two old tags and their two `try_` templates also read through, each with its family fixed, so an old tag keeps its family whatever its options say (D9.54 to D9.57) and still renders byte-identically.

**Measured 2026-11-17:** 66 old-tag renders across `/matrix-post-meta/`, the three term pages and the two department archives, taken on the PREVIOUS build, then every one rendered again as the merged tag fed its equivalent wire (`startKey`+`startTimeKey` become `a+b`, the range keys become two `;` entries, a single-ended range keeps its trailing `;`). All 66 old renders were unchanged and all 66 merged renders were byte-identical to the previous build's output, 64 of them non-empty. The two empty ones are the matrix's own expected-empty rows (D8.3, D7.7).

**A one-ended range and a single value now print a time display identically (#164).** Measured 2026-11-17 on the previous build against the same fields: `as:time` on `event_datetime` printed `9:00 AM` as a single and `9:00 am` as a one-ended range; `event_time` with no `as` printed `9:00 AM` against `Start time: 9:00 AM`; `as:time` on `event_midnight` printed nothing against `12:00 am`; `as:time` on a date-only field printed nothing against the parser's noon buffer, `12:00 pm`. The single core and the range core now share one function, `bws_format_time_display()`, and D10 pins every case through both old tags and the merged tag. The consequences: a side shows a time only when its value has one; midnight follows `showMidnight`; a start with no date is a time display with or without `as`; a missing start reads nothing; no label text is ever printed; and the style of the time (the case of the meridiem, 12-hour or 24-hour) comes from the field before it comes from the WP `time_format`, in the order `bws_resolve_time_only_format()` states. D9.15 and D9.16 keep the trailing-`;` spelling of the one-ended range so the equivalence stays measurable.

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
| D9.14 | `{{datetime src:post,<meta id>|key:event_time|as:time}}` | D1.3 | `9:00 am` |
| D9.15 | `{{datetime src:post,<meta id>|key:event_datetime;|as:time}}` | D1.4 | `9:00 AM` |
| D9.16 | `{{datetime src:post,<meta id>|key:event_datetime;|as:time|format:H:i}}` | D1.5 | `09:00` |
| D9.17 | `{{datetime src:post,<meta id>|key:event_start_date;event_end_date}}` | D2.1 | `August 1–9, 2030` |
| D9.18 | `{{datetime src:post,<meta id>|key:event_start_date;event_end_date|rangeSep: to }}` | D2.2 | `August 1 to 9, 2030` |
| D9.19 | `{{datetime src:post,<meta id>|key:event_datetime;event_end_datetime}}` | D2.3 | `August 12, 2030, 9:00 AM–5:00 PM` |
| D9.20 | `{{datetime src:post,<meta id>|key:event_midnight;event_end_datetime}}` | D2.4 | `August 12, 2030, 5:00 PM` |
| D9.21 | `{{datetime src:post,<meta id>|key:event_datetime;event_end_datetime|as:time}}` | D2.5 | `9:00 AM–5:00 PM` |
| D9.22 | `{{datetime src:post,<meta id>|key:event_time;event_end_time|as:time}}` | D2.6 | `9:00–11:30 am` |
| D9.23 | `{{datetime src:post,<meta id>|key:event_datetime;event_end_datetime|as:time|format:H:i}}` | D3.1 | `09:00–17:00` |
| D9.24 | `{{datetime src:post,<meta id>|key:event_datetime;event_end_datetime|as:time|format:g:i}}` | D3.2 | `9:00–5:00` |
| D9.25 | `{{datetime src:post,<meta id>|key:event_time;event_end_time|as:time|format:g:i A}}` | D3.3 | `9:00–11:30 AM` |
| D9.26 | `{{datetime src:post,<meta id>|key:event_midnight;event_end_datetime|as:time|format:H:i}}` | D3.4 | `17:00` |
| D9.27 | `{{datetime src:post,<meta id>|key:event_midnight;event_allday_end}}` | D8.1 | `August 12, 2030` |
| D9.28 | `{{datetime src:post,<meta id>|key:event_midnight;event_allday_end_multi}}` | D8.2 | `August 12–August 14, 2030` |
| D9.29 | `{{datetime src:post,<meta id>|key:event_midnight;event_allday_end|as:time}}` | D8.3 | empty |
| D9.30 | `{{datetime src:post,<meta id>|key:event_midnight;event_allday_end|as:time|showMidnight}}` | D8.4 | `12:00 AM–11:59 PM` |
| D9.31 | `{{datetime src:post,<meta id>|key:event_time;event_end_time|as:time|showMidnight}}` | D8.5 | `9:00–11:30 am` |
| D9.32 | `{{datetime src:post,<meta id>|key:event_datetime;event_end_datetime|as:time|showMidnight}}` | D8.6 | `9:00 AM–5:00 PM` |
| D9.33 | `{{datetime src:post,<meta id>|key:event_time;event_end_time|as:time|format:H:i|showMidnight}}` | D8.7 | `09:00–11:30` |
| D9.34 | `{{datetime src:post,<meta id>|key:event_start_date+event_time;event_end_date+event_end_time}}` | new combination | `August 1, 9:00 am–August 9, 2030, 11:30 am` |
| D9.35 | `{{datetime src:post,<meta id>|key:event_start_date+event_time;+event_end_time}}` | new combination | `August 1, 2030, 9:00–11:30 am` |
| D9.36 | `{{datetime src:post,<meta id>|key:event_datetime;+event_end_time}}` | new combination | `August 12, 2030, 9:00–11:30 AM` |
| D9.37 | `{{datetime src:post,<meta id>|key:event_start_date+event_time;}}` | new combination | `August 1, 2030, 9:00 am` |
| D9.38 | `{{datetime src:post,<meta id>|key:event_start_date;missing_field|fallback:FB}}` | new combination | `August 1, 2030` |
| D9.52 | `{{datetime src:post,<meta id>|key:event_start_date+event_time;event_end_time}}` | new combination | `August 1, 2030, 9:00–11:30 am` |
| D9.53 | `{{datetime src:post,<meta id>|key:event_datetime;event_end_time}}` | new combination | `August 12, 2030, 9:00–11:30 AM` |
| D9.54 | `{{datetime_range src:post,<meta id>|key:event_datetime;event_end_datetime|as:time}}` | new combination | `9:00 AM–5:00 PM` (the range core reads both entries; the single core would read only the first and print `9:00 AM`. Since #164 the two cores print a one-ended read the same, so the second entry is what tells them apart) |
| D9.55 | `{{datetime_single src:post,<meta id>|key:event_start_date;event_end_date}}` | new combination | `August 1, 2030` (a single tag reads only the first entry) |
| D9.56 | `{{try_datetime_range src:post,<meta id>|key:event_datetime;event_end_datetime|as:time}}` | D9.54 | `9:00 AM–5:00 PM` |
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
| D9.T9 | `{{datetime key:event_start_date+event_time+event_end_time}}` | — | `August 1, 2030, 9:00 am` (a third operand is ignored; its warning is D6.4) |
| D9.T10 | `{{datetime}}` (no `key`) | — | empty, and `fallback:No field` prints `No field` (the read-less tag shows its fallback) |

## D10 — one time display (FW-81, Unreleased, #164)

One function, `bws_format_time_display()`, renders the time half of every read: a single, a one-ended range and both ends of a two-ended one. Each case below is a visible block on `/matrix-datetime/` (blueprint v46) in every spelling that applies, labelled `D10.<n>a` to `d` in the order the "Spellings" column lists them, and every spelling of a case must print the same bytes, so a case that splits by tag is the defect. The old tags render through their own callbacks and the merged tag through the merged seam, which makes the equality a measurement. The fixture's WP `time_format` is `g:i a`, and `event_datetime` returns `F j, Y g:i A`, so a `9:00 AM` is the field's own tokens and not the WP option.

| # | Case | Spellings | Expected | Before #164 |
|---|---|---|---|---|
| D10.1 | `as:time` on a datetime field: the field's own time tokens | `datetime_single key:event_datetime`, `datetime_range startKey:event_datetime`, `datetime key:event_datetime`, `datetime key:event_datetime;` (each with `\|as:time`) | `9:00 AM` | single `9:00 AM`, range `9:00 am` |
| D10.2 | `as:time` on a time-only field, in its own return format | the same four, on `event_time` | `9:00 am` | single `9:00 AM` (hardcoded), range `9:00 am` |
| D10.3 | a time-only field with no `as`: the bare time, no label | the same four, on `event_time`, no `as` | `9:00 am` | single `9:00 AM`, range `Start time: 9:00 AM` |
| D10.4 | `as:time` on a midnight value: hidden by default | the same four, on `event_midnight` | empty | single empty, range `12:00 am` |
| D10.5 | `as:time` on a midnight value with `showMidnight` | the same four, plus `\|showMidnight` | `12:00 AM` | single `12:00 AM`, range `12:00 am` |
| D10.6 | `as:time` on a date-only field: no time to show, so the parser's noon buffer never prints | the same four, on `event_start_date` | empty | single empty, range `12:00 pm` |
| D10.7 | the same date-only read with `fallback:FB` | the same four | `FB` | single `FB`, range `12:00 pm` |
| D10.8 | a custom `format` is honored on a time-only field | the same four, `event_time\|format:H:i` | `09:00` | single `9:00 AM` (ignored), range `Start time: 9:00 AM` |
| D10.9 | a two-ended `as:time` range, in the start field's own tokens | `datetime_range startKey+endKey`, `datetime key:a;b` | `9:00 AM–5:00 PM` | `9:00 am–5:00 pm` |
| D10.10 | two time-only fields with no `as`: the time range, never labels | the same two, `event_time` and `event_end_time` | `9:00–11:30 am` | `Start time: 9:00 AM; End time: 11:30 AM` |
| D10.11 | a time-only start with a date-time end: both times, the end's date dropped | the same two, `event_time` and `event_end_datetime` | `9:00 am–5:00 pm` | `Start time: 9:00 AM; End date/time: August 12, 2030 5:00 PM` |
| D10.12 | a missing start reads nothing, even when the end has a value | the same two, `missing_a` and `event_end_time` | empty | `End time: 11:30 AM` |
| D10.13 | the same missing start with `fallback:FB` | the same two | `FB` | `End time: 11:30 AM` (the fallback never fired) |
| D10.14 | a start given only as a time key is a time-only start | `datetime_single timeKey`, `datetime_range startTimeKey`, `datetime key:+event_time`, `datetime key:+event_time;` | `9:00 am` | empty (the fallback) |
| D10.15 | a time-key-only start with a date-time end | `datetime_range startTimeKey+endKey`, `datetime key:+a;b` | `9:00 am–5:00 pm` | `End date/time: August 12, 2030 5:00 PM` |
| D10.16 | `as:date` on a time-only field: no date to show | the same four, `event_time\|as:date` | empty | single `9:00 AM`, range `Start time: 9:00 AM` |

D10.4, D10.12 and D10.16 are expected-empty rows, so each renders as a label with nothing after it (`bws_fixture_gb_empty_row`). The two-ended cases that were already right stay where they are: D2.6 (same-meridiem consolidation), D3.1 to D3.4 (custom format, both ends), D8.1 to D8.7 (the all-day span). The pure half is `datetime-format-test.php` §F7 to §F11 (the format chain) and §U (the display); the first change to either rule that is not mirrored there fails by name.

## D11 — verbs and the `use` list (FW-81, Unreleased)

`use` is absent unless a verb (`now`, `published`, `modified`) is mixed in, and then it is the full ordered list, `key` included: each `key` in it takes the next `;` entry of `key:`, and a verb takes none, so `use:key;now` and `use:now;key` are different ranges over the same fields. A `use` made only of `key` reads as no `use` at all. A verb read is a moment with no field behind it. `now` carries a time. `published` and `modified` are a post's own dates and print as dates, and `published` and `modified` read only from a post source (a term, the site and a repeater row print the fallback). A verb takes the time display of the field beside it, which is why D11.8 prints no time of day and D11.11 prints both. The pure half is `datetime-format-test.php` §V and `use-stripped-default-test.php` §5 (the list collapse, held to the editor twin by `editor-filter-chain-test.js`).

**The fixture pins `matrix-post-meta`'s dates** (blueprint v47: `post_date` 2025-03-03 09:15, `post_modified` 2025-04-04 18:30, the seed writing the second last because every reseed's `wp_update_post` moves it to now), in a past year on purpose, since this year's date prints without its year. D11.12 to D11.14 carry `now`, so each states the current year, which is the one part that cannot differ between two loads and the one part that needs a re-capture each January, as D0.5 and D9.5 do.

**Measured 2026-11-17** on the testbed, one wpcli container per context: every row below through `render-tag` and then through the front end, all matching. `published` before the pin printed the page's insert date and `modified` printed the reseed day, which is what made the pin necessary.

Every row is a visible block on `/matrix-datetime/` and pinned to `/matrix-post-meta/` by id where it reads a post.

| # | Tag | Expected |
|---|---|---|
| D11.1 | `{{datetime src:post,<meta id>\|use:published}}` | `March 3, 2025` |
| D11.2 | `{{datetime src:post,<meta id>\|use:modified}}` | `April 4, 2025` |
| D11.3 | `{{datetime src:post,<meta id>\|use:published\|format:Y-m-d}}` | `2025-03-03` |
| D11.4 | `{{datetime src:post,<meta id>\|use:published\|as:time\|fallback:FB}}` | `FB` (a post date carries no time) |
| D11.5 | `{{datetime src:term,<sales id>\|use:published\|fallback:Date TBA}}` | `Date TBA` |
| D11.6 | `{{datetime src:site\|use:modified\|fallback:Date TBA}}` | `Date TBA` |
| D11.7 | `{{datetime src:term,<sales id>\|use:modified}}` | empty |
| D11.8 | `{{datetime src:post,<meta id>\|use:published;key\|key:event_start_date}}` | `March 3, 2025–August 1, 2030` |
| D11.9 | `{{datetime src:post,<meta id>\|use:key;modified\|key:event_start_date}}` | `August 1, 2030–April 4, 2025` (the order spelled is the order printed) |
| D11.10 | `{{datetime src:post,<meta id>\|use:published;modified}}` | `March 3–April 4, 2025` |
| D11.11 | `{{datetime src:post,<meta id>\|use:published;key\|key:event_datetime}}` | `March 3, 2025 9:15 AM–August 12, 2030 9:00 AM` |
| D11.12 | `{{datetime use:now\|as:date\|format:Y\|showCurrentYear}}` | the current year |
| D11.13 | `{{datetime src:site\|use:key;now\|key:organization_founded\|format:Y}}` | `2020–` and the current year |
| D11.14 | `{{datetime src:site\|use:now;key\|key:organization_founded\|format:Y}}` | the current year and `–2020` |
| D11.15 | `{{datetime src:post,<meta id>\|use:key;key\|key:event_start_date;event_end_date}}` | `August 1–9, 2030` (= D9.17) |
| D11.16 | `{{datetime src:post,<meta id>\|use:key\|key:event_start_date;event_end_date}}` | `August 1–9, 2030` (`use:key` is no `use`, so two entries are a range) |
| D11.17 | `{{datetime src:post,<meta id>\|use:key;published\|fallback:FB}}` | `FB` (a `key` with no entry is an empty read; an empty start reads nothing) |
| D11.18 | `{{datetime src:post,<meta id>\|use:published;key\|key:event_start_date;event_end_date}}` | `March 3, 2025–August 1, 2030` (a surplus entry is ignored) |
| D11.19 | `{{datetime src:post,<meta id>\|use:published\|key:event_start_date}}` | `March 3, 2025` (a `key` beside a verb-only list is ignored) |
| D11.20 | `{{datetime src:post,<meta id>\|use:nope;key\|key:event_start_date}}` | `August 1, 2030` (a name that is neither is dropped) |
| D11.21 | `{{datetime_single src:post,<meta id>\|use:published\|key:event_start_date}}` | `August 1, 2030` (the old tags have no `use`) |

**Render-tag only** (a bare tag on a context with no post, which a visible block cannot host):

| # | Tag | Context | Expected |
|---|---|---|---|
| D11.T1 | `{{datetime use:now\|as:date\|format:Y\|showCurrentYear}}` | `/no-such-page-xyz/`, `/?bwsfx_scope=core` (the blog home), `/department/support/` | the current year (a tag whose every read is `now` reads no source, so no context can empty it) |
| D11.T2 | the same, with `\|linkTo:permalink` | the same | the current year, no link (there is nothing to link to) |
| D11.T3 | `{{datetime use:published\|fallback:FB}}` | the same | `FB` |
| D11.T4 | `{{datetime use:key;now\|key:event_date\|as:date\|format:Y\|showCurrentYear}}` | `/department/support/` | `2030–` and the current year (the ambient term's field, until now) |
| D11.T5 | the same | `/no-such-page-xyz/`, the blog home | empty (no post or term for the field to read; `now` alone cannot make it a range) |
| D11.T6 | `{{datetime use:published}}` with `--loop-item=<meta id>` | `/matrix-datetime/` | `March 3, 2025` (a loop item is a post for a post verb) |

## D12 — `try_datetime` (FW-81, FW-193, Unreleased)

`try_datetime` replaces `try_datetime_single` and `try_datetime_range`. Each attempt carries its own complete read, written in the attempt's value with the spelling `{{datetime}}`'s `key` and `use` use: `key(event_start_date;event_end_date)`, `use(key;published)`, `use(published)`. The attempt then reads exactly as `{{datetime}}` does with those reads, so a single date, a range, a `+` join and a verb all work in an attempt, and the first attempt that prints something wins. An attempt with no read (or `use(same)`) reuses the previous resolving attempt's whole read; an attempt that states any read part states its own complete read and borrows nothing. The first attempt cannot carry over, so with no read it prints nothing. Formatting, link wrap and fallback stay tag-level. `key` and `use` are not options of `try_datetime`: one written at tag level is ignored. The old two forms keep rendering.

**Measured:** D12.3, D12.5 and D12.12 (the old forms) 2026-11-17 on the testbed, `render-tag` and then the front end, and again on the build before `try_datetime`, byte for byte the same. The per-slot rows (D12.1, D12.2, D12.4, D12.6 to D12.9, D12.11, D12.13 to D12.24) are **measured 2026-11-19 on the per-slot build** (84c80a1, testbed reseeded to manifest v50): all 24 rows, the old forms D12.3, D12.5 and D12.12 and the unchanged D12.10 included, print exactly their "Expected" cell through `render-tag` (one boot, `--url=https://testbed.test/matrix-datetime/`), and the visible blocks on `/matrix-datetime/` print the same values on a cache-busted front-end fetch (D12.11's link points at `/matrix-post-meta/`). D12.22 to D12.24 measure the tag-level `key` as ignored with an attempt (D12.22 prints `August 1, 2030`, the attempt's own `key(event_start_date)`, not the tag-level `event_end_date`'s `August 9, 2030`; D12.23 prints nothing, not the tag-level `event_datetime`'s date) and without one (D12.24 prints nothing).

`refs,missing_rel` is an attempt that reads nothing on this page; the two pinned posts are the attempts that do, with different dates (the second is the staff post `jane-partner`, whose `event_datetime` is May 1, 2030), so a walk that stops at the wrong attempt prints the wrong date.

| # | Tag | Expected |
|---|---|---|
| D12.1 | `{{try_datetime A:src(post,<meta id>);key(event_datetime)\|B:src(post,<jane id>)}}` | `August 12, 2030 9:00 AM` (the first attempt reads, so it wins) |
| D12.2 | `{{try_datetime A:src(refs,missing_rel);key(event_datetime)\|B:src(post,<jane id>)}}` | `May 1, 2030 10:00 AM` (the first reads nothing, the second reuses its read and wins) |
| D12.3 | D12.2 as `try_datetime_single` with a tag-level `key:event_datetime` | the same |
| D12.4 | `{{try_datetime A:src(refs,missing_rel);key(event_start_date;event_end_date)\|B:src(post,<meta id>)}}` | `August 1–9, 2030` |
| D12.5 | D12.4 as `try_datetime_range` with `startKey` and `endKey` | the same |
| D12.6 | `{{try_datetime A:src(refs,missing_rel);key(event_start_date+event_time)\|B:src(post,<meta id>)}}` | `August 1, 2030, 9:00 am` (= D9.8) |
| D12.7 | `{{try_datetime A:src(refs,missing_rel);use(published)\|B:src(post,<meta id>)}}` | `March 3, 2025` |
| D12.8 | `{{try_datetime A:src(refs,missing_rel);use(key;published);key(event_start_date)\|B:src(post,<meta id>)}}` | `August 1, 2030–March 3, 2025` |
| D12.9 | `{{try_datetime A:src(refs,missing_rel);key(event_datetime)\|B:src(site)\|fallback:Date TBA}}` | `Date TBA` (printed once, after every attempt read nothing) |
| D12.10 | `{{try_datetime}}` | empty (no read, no fallback) |
| D12.11 | `{{try_datetime A:src(post,<meta id>);key(event_datetime)\|linkTo:permalink}}` | `August 12, 2030 9:00 AM` linked to the `matrix-post-meta` page (the link follows the winning attempt) |
| D12.12 | D12.1 as `try_datetime_single` with a tag-level `key:event_datetime` | `August 12, 2030 9:00 AM`, the same (the old form's first-attempt row, measured on the build before `try_datetime` too) |
| D12.13 | `{{try_datetime A:src(refs,missing_rel);key(event_start_date;event_end_date)\|B:src(post,<meta id>);key(event_datetime)}}` | `August 12, 2030 9:00 AM` (a range that reads nothing falls to the single behind it: each attempt has its own mode) |
| D12.14 | `{{try_datetime A:src(post,<meta id>);key(event_start_date;event_end_date)\|B:src(post,<meta id>);key(event_datetime)}}` | `August 1–9, 2030` (the range wins over the single behind it) |
| D12.15 | `{{try_datetime A:src(refs,missing_rel);key(event_datetime)\|B:src(post,<meta id>);use(published)}}` | `March 3, 2025` (a verb as the last attempt) |
| D12.16 | `{{try_datetime A:src(refs,missing_rel);key(event_datetime)\|B:src(post,<jane id>);use(same)}}` | `May 1, 2030 10:00 AM` (= D12.2 with the carry-over spelled out) |
| D12.17 | `{{try_datetime A:src(post,<meta id>);key(no_such_field)\|B:src(post,<jane id>);key(event_datetime)}}` | `May 1, 2030 10:00 AM` (each attempt reads its own field) |
| D12.18 | `{{try_datetime A:src(post,<meta id>)\|B:src(post,<jane id>);key(event_datetime)}}` | `May 1, 2030 10:00 AM` (the first attempt has no read and prints nothing) |
| D12.19 | `{{try_datetime A:src(post,<meta id>)}}` | empty (a read-less first attempt, nothing behind it, no fallback) |
| D12.20 | `{{try_datetime A:src(post,<meta id>);use(key;now)\|B:src(post,<jane id>);key(event_datetime)}}` | `May 1, 2030 10:00 AM` (a use list that needs a field and has none reads nothing; the second attempt states a read and borrows nothing from the first) |
| D12.21 | `{{try_datetime A:src(post,<meta id>);use(key;now)\|fallback:FB}}` | `FB` (the same use list alone is an empty read, so the fallback prints once) |
| D12.22 | `{{try_datetime key:event_end_date\|A:src(post,<meta id>);key(event_start_date)}}` | `August 1, 2030` (the tag-level `key` is ignored: the attempt's own `key(event_start_date)` reads) |
| D12.23 | `{{try_datetime key:event_datetime\|A:src(post,<meta id>)}}` | empty (the tag-level `key` is ignored, and the attempt states no read) |
| D12.24 | `{{try_datetime key:event_datetime}}` | empty (a tag-level `key` alone is not recovered into the first attempt) |

Every row is a visible block on `/matrix-datetime/`.

## D13 — the Migration Tool on the old tags (FW-81, Unreleased)

The tool renames `datetime_single`, `datetime_range`, `try_datetime_single` and `try_datetime_range` to `datetime` / `try_datetime` and rewrites their six datetime options to the one `key` (the rule is `bws_datetime_legacy_reads_rewrite()`, shared with the editor's mount step and run through `tools/test/datetime-mount-corpus.json`). On a `try_` tag the one `key` is written into slot A as `key(…)`, the form `try_datetime` reads (`bws_datetime_legacy_key_to_slot_a()`, its twin run over the same corpus as `expectTry`): a one-slot old tag becomes a one-slot new tag, a range with a time join lands as one `key(s+st;e)` list, and slots 2+ state no read and so reuse it. The editor's mount step does not do this, because the old hidden `try_` tags still read the tag-level `key`. Every rewritten tag must print what its old form printed. One shape is left whole: a range that states an end and no start, which prints its fallback today and would gain a read under `key`. The scan lists it as skipped (reason `range_without_start`), and the run does not touch it.

The converted half has no rows of its own, because every old-form row on `/matrix-datetime/` (D0 to D12) is converted by the run and compared: `tools/fixtures/core-structures/verify-migration.php` §4b renders each old tag before the run and its rewrite after (36 rewritten, 42 checked, all byte-identical when measured 2026-11-18), and `php tools/test/page-snapshots.php` after a full run shows `/matrix-datetime/` unchanged. Run it, then reseed (it mutates the corpus).

| # | Tag | Expected |
|---|---|---|
| D13.1 | `{{datetime_range src:post,<meta id>\|endKey:event_end_date\|fallback:FB}}` | `FB` (an end and no start; the run leaves it, the scan lists it as skipped) |
| D13.2 | `{{datetime_range src:post,<meta id>\|endKey:event_end_date}}` | empty (the same range, no fallback) |
| D13.3 | `{{datetime_range src:post,<meta id>\|endTimeKey:event_end_time\|fallback:FB}}` | `FB` (an end time and no start) |
| D13.4 | `{{try_datetime_range A:src(post,<meta id>)\|endKey:event_end_date\|fallback:FB}}` | `FB` (= D13.1 through the old `try_datetime_range`) |

Left whole and not reported (the new form would read each differently): a single holding `;` or `+` in a field key, a single that sets a range option or `use`, and a range that states no read at all. The pure side of this is `report-run-agreement-census-test.php` §C3.

Every row is a visible block on `/matrix-datetime/`.
