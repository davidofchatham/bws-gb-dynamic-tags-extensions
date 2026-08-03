# `limit` default + UNLIMITED test matrix

Named for the PROPERTY under test, not a tag family (precedent:
[`fw52-order-test-matrix.md`](fw52-order-test-matrix.md)) — `limit` spans five tag families
and four code paths, and the regression this matrix guards is cross-cutting.

**What changed (1.17.0).** `limit` is now interpreted in ONE place,
[`bws_clamp_limit()`](../../includes/helpers/field-helpers.php), and `0` means **UNLIMITED**:

| Input | Before 1.17.0 | 1.17.0 |
|---|---|---|
| unset / `''` | 1 | **1 (unchanged — this is what the L1 rows pin)** |
| non-numeric (`abc`) | 1 | 1 (explicit `is_numeric()` gate) |
| `0` | 1 (silently clamped) | **UNLIMITED** |
| `-1`, `-3` | 1 (silently clamped) | **UNLIMITED** (parse tolerant, emit `0`) |
| `1`, `5`, `999` | same | same (no ceiling) |

**Why these rows exist.** Opening up `0` moves the meaning of a value that already saves and
survives on the wire, and the tag families reach the rule by four different paths. The
regression it invites is quiet: **the top-level link gate is COUNT-BASED** (a single result is
link-wrapped, a joined multi-value composite is not), so an accidental 1→many flip does not
merely lengthen output — **it drops the anchor while the text still reads plausibly**.
⇒ **Every L1 row asserts TWO things: exactly one value AND the link present.**

**Four code paths, one rule.** A row is worth having per PATH, not per tag:

| Path | Site | Rows |
|---|---|---|
| the seam — `bws_resolve_field_values()` | `field-helpers.php` | text, title, email, phone |
| the shared list fold — `bws_collect_value_list()` | `field-helpers.php` | datetime_single / _range |
| try_ slot dispatch (own implementation) | `class-tag-template-registry.php` | L1.7, L3.4 |
| `bws_try_join_items()` (defensive re-clamp) | `base-shared.php` | covered via the try_ rows |

`{{content}}` is **NOT list-capable** (registers no `limit`) — out of scope BY DESIGN. Do not
add rows for it.

- **Pure algorithm:** `php tools/test/limit-clamp-test.php` (clamp rule + the caller
  slice/early-break contracts) and `php tools/test/try-join-seam-test.php` (the join seam).
  Those pin the rule; this matrix confirms the LIVE reads they cannot reach.
- **Visible blocks:** every row below is generated as a browsable/editable GB block on
  `matrix-post-meta` (blocks.php `matrix_post_meta` builder, sections `Limit L1/L2/L3`).

**Fixture state assumed** (`core-structures` blueprint — no blueprint change needed):
`matrix-post-meta` carries department terms **Support, Sales** (both valid) and
`related_staff` = **Jane Partner, Tom Associate** (jane FIRST — the unset-limit rows pin her).

**How to check:** reseed (`bin/seed.sh testbed core-structures`), then
`curl -sk "https://testbed.test/matrix-post-meta/?nocache=$RANDOM"` — the cache bust is
mandatory or brand-new rows read as missing. Single rows can also be run through
`bin/wp.sh testbed bws render-tag '{{…}}' --url=https://testbed.test/matrix-post-meta/`.

## L1 — unset `limit` MUST stay 1 (the regression floor)

Each row asserts **one value AND link present**. A row that renders two values has flipped the
default; a row that renders one value with no `<a>` has broken the count-based gate.

| Row | Tag | Expected | Path |
|---|---|---|---|
| L1.1 | `{{text srcTermIn:department\|use:title\|linkTo:permalink}}` | ONE dept name (`Sales` — first term), wrapped in `<a>` | seam |
| L1.2 | `{{text src:ref\|ref:related_staff\|use:title\|linkTo:permalink}}` | `Jane Partner` only, wrapped in `<a>` | seam |
| L1.3 | `{{title src:ref\|ref:related_staff\|linkTo:permalink}}` | `Jane Partner` only, wrapped in `<a>` | seam |
| L1.4 | `{{title srcTermIn:department\|linkTo:permalink}}` | ONE dept name, wrapped in `<a>` | seam |
| L1.5 | `{{datetime_single src:ref\|ref:related_staff\|key:event_datetime\|linkTo:permalink}}` | ONE date (jane's), wrapped in `<a>` | list fold |
| L1.6 | `{{datetime_range srcTermIn:department\|startKey:event_date}}` | ONE date, no `; ` separator present | list fold |
| L1.7 | `{{try_text srcTermIn:department\|use:title}}` | ONE dept name, no `, ` separator | try_ dispatch |
| L1.8 | `{{email src:ref\|ref:related_staff\|key:contact_email}}` | ONE `mailto:` anchor (jane@example.test) | seam |
| L1.9 | `{{phone src:ref\|ref:related_staff\|key:main_line}}` | ONE `tel:` anchor (jane's line) | seam |
| L1.10 | `{{join srcTermIn:department\|use:title\|2-key:role}}` | slot 1 = ONE dept name, joined to the role | seam (per-slot) |

**email / phone read differently and that is correct:** both wrap EVERY value in its own
`mailto:`/`tel:` anchor, so link presence there is not count-gated. L1.8/L1.9 are still count
rows — one anchor, not two.

## L2 — explicit values still behave

| Row | Tag | Expected |
|---|---|---|
| L2.1 | `{{text src:ref\|ref:related_staff\|use:title\|limit:2\|linkTo:permalink}}` | BOTH names, `, `-joined, **NO** `<a>` (multi-value composite is unwrappable) |
| L2.2 | `{{text src:ref\|ref:related_staff\|use:title\|limit:1\|linkTo:permalink}}` | `Jane Partner`, wrapped — explicit 1 === unset 1 |
| L2.3 | `{{text srcTermIn:department\|use:title\|limit:99}}` | both dept names — no ceiling, limit > count is not an error |

## L3 — the semantics change

| Row | Tag | Expected | What it proves |
|---|---|---|---|
| L3.1 | `{{text src:ref\|ref:related_staff\|use:title\|limit:0}}` | BOTH names (`Jane Partner, Tom Associate`) | `0` = unlimited. **Rendered ONE value before 1.17.0** — the deliberate break |
| L3.2 | `{{text src:ref\|ref:related_staff\|use:title\|limit:-1}}` | BOTH names | `-1` parses as unlimited (GB Posts-Per-Page convention), tolerated not emitted |
| L3.3 | `{{text src:ref\|ref:related_staff\|use:title\|limit:abc\|linkTo:permalink}}` | `Jane Partner` only, wrapped in `<a>` | the `is_numeric()` guard — a typo resolves to the DEFAULT, never to "no limit". `(int)'abc' === 0`, so without the guard this row fans out |
| L3.4 | `{{try_text srcTermIn:department\|use:title\|limit:0}}` | both dept names, `, `-joined | the try_ dispatch honors unlimited AND does not break out of the term hop after the first item (the `$slot_max &&` guard) |
| L3.5 | `{{datetime_single srcTermIn:department\|key:event_date\|limit:0}}` | both dept event dates | the list fold slices with `?: null` |
| L3.6 | `{{text srcTermIn:department\|use:title\|limit:0\|linkTo:permalink}}` | both names, **NO** `<a>` | unlimited feeds the same count gate — it drops the anchor legitimately, because the output really is multi-value |

## Editor check (no front-end surface)

Open any `limit`-bearing block on `/matrix-post-meta/` in the GB editor:

- the **Result Limit** help text reads *"Maximum number of results to return. Default: 1. Enter 0
  for no limit."*;
- the number input accepts `0` and `-1` — **there is deliberately no `min`** (a control that
  fights a hand-typed value works against
  [ADR 0004](../../docs/adr/0004-serialized-tag-string-human-readable.md), and the parse is already
  tolerant);
- `0` survives a save/reopen round trip (GB serializes every value except strict `false`).
