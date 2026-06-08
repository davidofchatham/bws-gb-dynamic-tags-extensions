# `{{phone}}` Regression Matrix

**Standing manual regression suite** for the `{{phone}}` base tag (v1.10.0) — not a one-shot plan checklist. Rows are anchored to invariants (VP1–VP-vis), so they stay valid past the SPEC's post-ship truncation.

> **Re-run trigger:** after any change to `bws_phone_normalize_tel` (or its trunk-strip / length-gate / strip-CC sub-helpers), `bws_phone_callback`, `bws_phone_render_one`, the two `phone.*` settings, or the phone preview branch.
>
> **Two layers:**
> - **Algorithm (pure, automated):** `php tools/test/phone-normalize-test.php` — 19 cases over VP-hyphen / VP3 / VP-strip / VP4 / VP-href-safe. Run first; must be green before manual rows.
> - **Integration (manual, WP):** the R-rows below — sources, list mode, `noLink` render, fallback, preview, settings. Run on a WP test instance with **GenerateBlocks (Pro)** + **ACF**, per the runtime-debug workflow (TEST instance, never the live/cached site).

**How to run:** paste each tag into a GenerateBlocks block, view the rendered front end. `[SUB …]` = substitute a real key on your instance. Settings rows assume **Settings → Tag Extensions → Phone**.

---

## R0 — href rebuild (model C, VP-hyphen) — global CC `1`, strip OFF

| # | Field value | Tag | Expected href / output |
|---|---|---|---|
| R0.1 | `(987) 654-3210` | `{{phone key:[SUB]}}` | `<a href="tel:+1-987-654-3210">(987) 654-3210</a>` (author groups reused) |
| R0.2 | `987.654.3210` | `{{phone key:[SUB]}}` | href `tel:+1-987-654-3210` (dots → hyphens) |
| R0.3 | `(987)654-3210` | `{{phone key:[SUB]}}` | href `tel:+1-987-654-3210` (parens break group, no space) |
| R0.4 | `9876543210` | `{{phone key:[SUB]}}` | href `tel:+19876543210` (**bare digits → no internal hyphens**) |
| R0.5 | `987 654 3210` | `{{phone key:[SUB]}}` | href `tel:+1-987-654-3210` (spaces → hyphens) |

## R1 — country code 2-tier + trunk-0 (VP3)

| # | Field | Global CC | Tag | Expected |
|---|---|---|---|---|
| R1.1 | `+1 987 654 3210` | `44` | `{{phone key:[SUB]}}` | href `tel:+1-987-654-3210` (**in-field `+` wins**, global ignored) |
| R1.2 | `0011 22 3333` | (empty) | `{{phone key:[SUB]}}` | href `tel:+11-22-3333` (`00`→`+`) |
| R1.3 | `07911 123456` | `44` | `{{phone key:[SUB]}}` | href `tel:+44-7911-123456` (**trunk 0 stripped** on CC apply) |
| R1.4 | `07911 123456` | (empty) | `{{phone key:[SUB]}}` | href `tel:07911-123456` (**national, trunk 0 KEPT**, no `+`) |
| R1.5 | `9876543210` | (empty) | `{{phone key:[SUB]}}` | href `tel:9876543210` (national, no `+`) |

## R2 — strip-leading-CC (VP-strip) — global CC `1`

| # | Field | Strip setting | Expected |
|---|---|---|---|
| R2.1 | `1-800-555-1212` | **ON** | href `tel:+1-800-555-1212` (leading CC stripped, no double) |
| R2.2 | `1-800-555-1212` | OFF | href `tel:+1-1-800-555-1212` (double prefix — the hazard the flag guards) |
| R2.3 | `8005551212` (no leading 1) | ON | href `tel:+18005551212` (nothing to strip; unchanged) |
| R2.4 | `1-800-555-1212` | ON, but global CC **empty** | href `tel:1-800-555-1212` (no-op — strip needs a CC to match; national) |

## R3 — `noLink` + list mode + fallback (VP1, VP4)

| # | Setup | Tag | Expected |
|---|---|---|---|
| R3.1 | field `07911 123456` | `{{phone key:[SUB]\|noLink}}` | `07911 123456` (**plain text, stored verbatim**, no anchor) |
| R3.2 | term-hop, ≥2 terms with phone fields | `{{phone srcTermIn:[SUB tax]\|key:[SUB]}}` | each valid number its own `<a tel:>`, joined by `, ` |
| R3.3 | term-hop, one term's value is junk (`abc`) | as R3.2 | junk term **skipped**; valid ones joined (strict, no plain passthrough) |
| R3.4 | term-hop, ALL values junk, `fallback:555-123-4567` | as R3.2 + `\|fallback:…` | fallback number wrapped (fires only on all-empty) |
| R3.5 | field empty, no fallback | `{{phone key:[SUB]}}` | empty output on front end |
| R3.6 | field `12345` (too short) | `{{phone key:[SUB]}}` | empty (length gate <7) — VP4 |

## R4 — extension sever + sources (VP4, cross-source)

| # | Field | Tag | Expected |
|---|---|---|---|
| R4.1 | `555-867-5309 x99` | `{{phone key:[SUB]}}` | href `tel:+1-555-867-5309` (**`x99` severed**, dials main); display shows the raw incl. `x99` |
| R4.2 | option `org_phone` on options page | `{{phone src:site\|key:org_phone}}` | wp_options/ACF-options number wrapped |
| R4.3 | post meta on current post | `{{phone src:current\|key:[SUB]}}` | post-meta number wrapped |
| R4.4 | related-post field via `src:ref` | `{{phone src:ref\|ref:[SUB]\|key:[SUB]}}` | traversed-entity number(s) wrapped, list mode |

## R5 — preview + visibility (VP-vis, preview)

| # | Check | Expected |
|---|---|---|
| R5.1 | Editor preview, `key` empty | `[⚠ No field key set]` |
| R5.2 | Editor preview, `key:phone` set | `[Phone: 'phone']` (label-only, no sample href) |
| R5.3 | Tag picker on an `<a>` / `<button>` / `<img>` / `<picture>` element | `{{phone}}` **not offered** (visibility gate) |

## R6 — security (VP-href-safe)

| # | Field | Expected |
|---|---|---|
| R6.1 | `+1-987"><script>654-3210` | href is `tel:+1-987-654-3210` (digits+hyphens only; `"><script>` discarded as a separator); **no script in source**; display shows the raw string HTML-escaped as text |

---

## Fail triage

- **R0 wrong hyphens** → `bws_phone_normalize_tel` separator-map capture (step 3) or the boundary marker-shift (steps 5–7). Bare-digit case adding a hyphen = the `$has_structure` guard on the `+CC-` boundary (step 7).
- **R1 wrong CC / trunk** → international detection (step 1) or `bws_phone_strip_trunk_zero` (gated on CC-applied; national-fallback must keep the 0).
- **R2 strip misfire** → the VP-strip gate (ON + CC matches global + ≥7 remain). Matches GLOBAL CC only.
- **R3 list/fallback** → callback loop (skip-invalid) + fallback-on-empty ordering.
- **R4 ext / source** → junk-sever (step 2) or the `bws_phone_resolve_numbers` source paths.
- **R5 preview/vis** → `preview-helpers.php` phone branch / the `visibility` registration array.
- **R6 leak** → VP-href-safe is structural; a leak means a non-digit survived into the value (regex assert in the pure harness should have caught it).
