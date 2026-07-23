# `src:site` Regression Matrix

**Standing manual regression suite** for the `src:site` unified site source and the Model B `use`-dispatch — not a one-shot plan checklist. Originated with v1.9.0 Stage A (§B5/§B6); kept as the re-run pass for any change to the site source. Run on a WP test instance with **GenerateBlocks (Pro)** + **ACF**, per the runtime-debug workflow (instrument + pull to a TEST instance, never probe the live/cached site).

> **Re-run trigger:** after any change to `bws_site_resolve_value`, the allowlist, the link resolver, or the `use`/`key` editor controls. Rows are anchored to invariants (§B4–B7/C10), not plan tasks, so they stay valid past the SPEC's post-ship truncation — the fail-triage below explains each failure mode independently.
>
> **Volatile section:** **R3 (label strings)** tracks the *current* label decisions (T19–T21 + the "Key"-suffix pass). Labels are the churniest surface — re-verify / rewrite R3's expected-label column after any label or UX pass. Everything else (R0–R2, R4–R5) tests behavior and is durable.

**How to run:** paste each tag into a GenerateBlocks block, view the rendered front end. `[SUB ...]` = substitute a real key on your instance.

**No-ACF keys** (always in the GB-parity allowlist seed): `blogname`, `blogdescription`, `siteurl`, `home`, `time_format`, `user_count`.
**ACF options-page fields** auto-allow on registration (e.g. `organization_founded`, `organization_name`, `organization_social.facebook` on the reference instance).

---

## R0 — §B6 strip-default regression (the all-tokens tag)

| # | Tag | Expected |
|---|---|---|
| R0.1 | `{{text src:site\|key:blogname}}` | site name (blogname) |
| R0.2 | `{{text src:site\|key:blogname\|linkTo:key\|linkKey:home}}` | blogname text, linked to home URL |
| R0.3 | `{{image as:url\|src:site\|use:featured}}` | site logo URL |
| R0.4 | `{{image as:url\|src:site}}` | **empty** (key-mode, no key — NOT the logo). *Editor-preview note: shows bare "Image", no `[⚠ No meta key set]`. Not a bug — `as:url`/non-alt-caption image modes suppress the bracket label (`preview-helpers.php:533` returns `''`) because a bracket string would corrupt an `<img>` attribute. The warning branch (`:549`) is therefore unreached. Surfacing an editor-only warning needs render-context awareness the preview helper lacks (it can't tell which GB element it feeds) → deferred to control/image-option work.* |
| R0.5 | `{{text src:site}}` | **empty** (no key) |

## R1 — `use`-dispatch (content / text / image / title / permalink)

| # | Tag | Expected |
|---|---|---|
| R1.1 | `{{content src:site}}` | **empty** (no site content analog — §B7) |
| R1.2 | `{{content src:site\|use:content}}` | **empty** (same as R1.1) |
| R1.3 | `{{content src:site\|use:excerpt}}` | **empty** (no site excerpt) |
| R1.4 | `{{content src:site\|use:key\|key:[SUB block/WYSIWYG option]}}` | option value, rich-rendered (blocks/shortcodes execute) |
| R1.5 | `{{text src:site\|use:title}}` | site name |
| R1.6 | `{{text src:site\|use:key\|key:blogdescription}}` | tagline text (key path — NO `use:tagline` value exists, §B7/C10) |
| R1.7 | `{{title src:site}}` | site name |
| R1.8 | `{{permalink src:site}}` | home URL |
| R1.9 | `{{image as:id\|src:site\|use:key\|key:[SUB attachment-id option]}}` | attachment ID from option |

## R2 — editor controls (open the block, watch field visibility)

| # | Action | Expected |
|---|---|---|
| R2.1 | Any site tag, set src → Site | `ref` + `srcTermIn` (+ taxonomy) HIDDEN |
| R2.2 | content, src:site, use = Content | key field HIDDEN |
| R2.3 | content, src:site, use = Meta/Option Field | key field SHOWN |
| R2.4 | image, src:site, use = Featured Image/Site Logo | key field HIDDEN |
| R2.5 | image, src:site, use = Meta/Option Field | key field SHOWN |
| R2.6 | text/title, src:site | `limit` / `sep` HIDDEN (no multi-result step) |
| R2.7 | Save R1.1–R1.9, reopen | values persist, no GB strip/mangle |

## R3 — label rendering (editor)

| # | Control | Expected label |
|---|---|---|
| R3.1 | text/image/content `use:key` value | "Meta/Option Field" |
| R3.2 | text/image/content key field | "Meta/Option Field" |
| R3.3 | image analog `use` value | "Featured Image/Site Logo" |
| R3.4 | content analog `use` value | "Post Content/Term Description" |
| R3.4b | text `use` enum (site) | only "Meta/Option Field" + "Title/Name" — **no "Site Tagline"** (§B7/C10) |
| R3.5 | linkTo dropdown value | "URL Meta/Option Field" |
| R3.6 | linkKey field | "URL Meta/Option Field" |
| R3.7 | `try_text`/`try_content`/`try_image` slot 2 | "2: Source", "2: Meta/Option Field", "2: Text Field", "2: Get from taxonomy term?" |
| R3.8 | tag titles | "Title/Name", "Date/Time", "Date/Time Range" (tight slash) |
| R3.9 | datetime field labels | "Date/Time Field", "Start Date/Time Field", "End Date/Time Field" |

## R4 — no-regression (post / term / try_)

Place on a single post, then a term archive.

| # | Context | Tag | Expected |
|---|---|---|---|
| R4.1 | post | `{{image as:url\|use:featured}}` | post featured image URL |
| R4.2 | post | `{{text use:key\|key:[SUB post meta]}}` | post meta value |
| R4.3 | post | `{{text use:title}}` | post title |
| R4.4 | post | `{{content use:content}}` | post content |
| R4.5 | term | `{{term_text use:title}}` | term name |
| R4.6 | post | `{{try_text key:[SUB post meta]\|2-use:title}}` | slot 1 meta, else slot 2 title (carry-forward intact). NB `2-use:` is REQUIRED — a slot 2+ given only `2-key:` renders empty (looks like broken fallthrough, isn't; `2-src:same` does not rescue it) |
| R4.7 | post | `{{text src:site\|use:key\|key:[SUB ACF group subfield, e.g. organization_social.facebook]\|linkTo:key\|linkKey:[same key]}}` | value AND link both resolve (§B4 — dot-path + ACF filter via shared reader) |

## R5 — datetime site

> **Standing datetime coverage now lives in [`datetime-test-matrix.md`](datetime-test-matrix.md)** (D5 runs the `src:site` single/format rows against seeded fixture keys — `organization_founded`, `org_party_datetime`). The rows below stay as the site-source view (R5.3 range-site and R5.4 site-link have no D-row twin); run them with your own substituted keys.

| # | Tag | Expected |
|---|---|---|
| R5.1 | `{{datetime_single src:site\|key:[SUB ACF date option]}}` | formatted date, ACF return_format honored |
| R5.2 | `{{datetime_single src:site\|key:[SUB]\|format_type:custom\|custom_format:Y-m-d}}` | custom format overrides ACF return format |
| R5.3 | `{{datetime_range src:site\|key:[SUB start]\|end:[SUB end]}}` | start–end range |
| R5.4 | `{{datetime_single src:site\|key:[SUB]\|linkTo:key\|linkKey:home}}` | date linked to home URL |

---

## Keys to substitute

| Slot | Needs |
|---|---|
| R1.4 | a block/WYSIWYG option (e.g. ACF Extended block-editor field on an options page) |
| R1.9 | a wp_options / ACF-options value holding an attachment ID |
| R4.2, R4.6 | a post meta key |
| R4.7 | an ACF options-page **group subfield** (dot-path, e.g. `organization_social.facebook`) |
| R5.x | ACF options-page **date** fields (e.g. `organization_founded`) |

Plain WP options (`blogname`, `blogdescription`, `home`) need no ACF.

## R6 — `{{email}}` base tag (1.9.0)

Needs an email-valued field: an ACF options-page email field for `src:site` (e.g. `org_email`), and a post/term meta email field for the cross-source rows. `[SUB ...]` = substitute a real key.

| # | Tag | Expected |
|---|---|---|
| R6.1 | `{{email src:site\|key:[SUB org_email]}}` | `<a href="mailto:VALUE">VALUE</a>` (obfuscated when global toggle on) |
| R6.2 | `{{email src:site\|key:[SUB org_email]\|noLink}}` | plain address, no anchor |
| R6.3 | `{{email src:site\|key:[SUB org_email]\|subject:Hello there}}` | anchor with `?subject=Hello%20there` in href |
| R6.4 | `{{email src:site\|key:[SUB org_email]\|subject:Quote\: 20\% off}}` | href subject = `Quote%3A%2020%25%20off` (escaped editor-side, rawurlencoded at render) |
| R6.5 | `{{email key:[SUB contact_email]}}` (no src) | post/term meta email, wrapped |
| R6.6 | `{{email src:site\|key:[SUB org_email]\|fallback:dept@example.com}}` with the primary field EMPTY | `dept@example.com`, wrapped (fallback fires) |
| R6.7 | `{{email src:site\|key:[SUB a non-email text option]}}` | **empty** (invalid → no fallback set → empty) |
| R6.8 | `{{email src:site}}` (no key) | **empty**; editor preview `[⚠ No field key set]` |
| R6.9 | Global **Email → Obfuscate** OFF, re-run R6.1 | clean `mailto:VALUE` href + plain-text display (no antispambot entities) |
| R6.10 | Try to insert `{{email}}` on a Button / Image / link (`<a>`) element | tag **hidden** in the GB dynamic-tag selector (visibility gate) |

## R7 — `src:site` slots on the remaining `try_` tags (FW-4, 1.15.0)

The five post-core try_ templates (`try_text`/`try_title`/`try_content`/`try_image`/`try_permalink`)
dispatch their site slots through the `try_site_fn` descriptor leg (thin closures over
`bws_site_resolve_value`); `try_email`/`try_phone` keep their seam route ($cf(0,…) fallback,
byte-identical). Single-result site output on link-wrap templates wraps with the site sentinel
(`('site', 1)` → home URL) for I6/C9 slot-transparency parity with base `{{title src:site}}`.

Visible rows: `matrix-post-meta` page, section "Site R7 - try_ site slots" (R7.1–R7.7, R7.10).
Exceptions (stated per §Development): R7.8 needs a `[SUB]` WYSIWYG option, R7.9's positive case
needs a site logo, R7.12 a `[SUB]` email option — none seeded by `core-structures`; R7.11 is
editor-only (open any R7 block, check the slot src dropdowns).

| # | Tag | Expected |
|---|---|---|
| R7.1 | `{{try_title src:site}}` | site name |
| R7.2 | `{{try_permalink src:site}}` | home URL |
| R7.3 | `{{try_text src:site\|use:title}}` | site name (text site analog via slot) |
| R7.4 | `{{try_text src:site\|use:key\|key:blogname}}` | site name (option key read) |
| R7.5 | `{{try_text key:[SUB empty/nonexistent post meta]\|2-src:site\|2-use:key\|2-key:blogname}}` | slot 1 empty → slot 2 site value (chain falls through to site) |
| R7.6 | `{{try_title src:site\|linkTo:permalink}}` | site name linked to home URL (site sentinel wrap) |
| R7.7 | `{{try_content src:site}}` | **empty** (no site content analog — §B7 rides through the slot) |
| R7.8 | `{{try_content src:site\|use:key\|key:[SUB block/WYSIWYG option]}}` | option value, rich-rendered |
| R7.9 | `{{try_image as:url\|src:site\|use:featured}}` | site logo URL (empty if no logo set — set one to verify positive) |
| R7.10 | `{{try_image as:url\|src:site}}` | **empty** (key-mode default, no key — NOT the logo; R0.4 parity) |
| R7.11 | Editor: `try_title`/`try_permalink`/`try_text`/`try_content`/`try_image` slot src dropdowns | "Site" offered in every slot (1 and 2+) |
| R7.12 | `{{try_email src:site\|key:[SUB org_email]}}` re-run (R6.1 form via try_) | unchanged — mailto self-wrap, no site-sentinel wrap (fallback leg byte-identical) |

## Fail triage

- **Empty where a value is expected:** is the key in the GB-parity seed, or a registered ACF options-page field? (`tools/debug/bws-site-datetime-probe.php` logs allowlist parity for a given key.)
- **Wrong post on a link** (e.g. `get_permalink(1)`): V-link `entity_type==='site'` guard not reached before the post/term read.
- **Value resolves but link is empty on an ACF group subfield:** §B4 — both reads must route through `bws_site_read_option` (one reader).
- **Stale label in editor:** JS cache — hard-reload the editor.
- **`use` value selected but ignored** (e.g. excerpt/featured no effect): §B5 — resolver dispatching on key-presence instead of `use`.
- **`{{…|key:X}}` ignored when no explicit `use`:** §B6 — empty `use` not canonicalized to the stripped first-enum value.
- **`{{content src:site}}` returns the tagline (not empty):** §B7 — content has no site analog; the content default must resolve empty. (Tagline has no tag path — GB native `{{site_tagline}}` or `key:blogdescription`.)
- **`{{email}}` display shows `&#x…;` entities as literal text:** antispambot output was double-escaped (`esc_html` on top of `antispambot`) — VE4: emit antispambot output raw.
- **`{{email …|subject:}}` href subject corrupted / truncated:** VE2 — subject must be `rawurlencode`d once at render, NOT unescaped in PHP (GB's `parse_options` already unescaped `\:`/`\|`).
- **`{{email}}` wraps a non-email value / outputs `mailto:garbage`:** VE4 — `is_email()` must validate the RAW value before any wrap; invalid → fallback → empty.
- **R7 site slot empty where base `src:site` works:** the template's `try_site_fn` closure missing/mis-keyed — registry falls back to `$cf(0,…)` which is site-blind for the five post-core templates.
- **R7 link-wrap missing on single-result site slot (link template):** the wrap is gated `$sf && $slnk && count===1` in the registry site arm — check `supports_link_wrap` on the descriptor and that the site leg (not the fallback) dispatched.
