# `src:site` Manual Test Matrix

Manual editor / front-end test pass for the `src:site` unified site source (v1.9.0, Stage A) and the Model B `use`-dispatch (§B5/§B6). Run on a WP test instance with **GenerateBlocks (Pro)** + **ACF**, per the runtime-debug workflow (instrument + pull to a TEST instance, never probe the live/cached site).

**How to run:** paste each tag into a GenerateBlocks block, view the rendered front end. `[SUB ...]` = substitute a real key on your instance.

**No-ACF keys** (always in the GB-parity allowlist seed): `blogname`, `blogdescription`, `siteurl`, `home`, `time_format`, `user_count`.
**ACF options-page fields** auto-allow on registration (e.g. `organization_founded`, `organization_name`, `organization_social.facebook` on the reference instance).

> Reusable: re-run after any change to `bws_site_resolve_value`, the allowlist, the link resolver, or the `use`/`key` editor controls.

---

## R0 — §B6 strip-default regression (the all-tokens tag)

| # | Tag | Expected |
|---|---|---|
| R0.1 | `{{text src:site\|key:blogname}}` | site name (blogname) |
| R0.2 | `{{text src:site\|key:blogname\|linkTo:key\|linkKey:home}}` | blogname text, linked to home URL |
| R0.3 | `{{image as:url\|src:site\|use:featured}}` | site logo URL |
| R0.4 | `{{image as:url\|src:site}}` | **empty** (key-mode, no key — NOT the logo) |
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

| # | Tag | Expected |
|---|---|---|
| R4.1 | `{{image as:url\|use:featured}}` (post) | post featured image URL |
| R4.2 | `{{text\|use:key\|key:[SUB post meta]}}` (post) | post meta value |
| R4.3 | `{{text\|use:title}}` (post) | post title |
| R4.4 | `{{content\|use:content}}` (post) | post content |
| R4.5 | `{{term_text\|use:title}}` (term) | term name |
| R4.6 | `{{try_text\|key:[SUB post meta]\|2-use:title}}` | slot 1 meta, else slot 2 title (carry-forward intact) |
| R4.7 | `{{text src:site\|use:key\|key:[SUB ACF group subfield, e.g. organization_social.facebook]\|linkTo:key\|linkKey:[same key]}}` | value AND link both resolve (§B4 — dot-path + ACF filter via shared reader) |

## R5 — datetime site

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

## Fail triage

- **Empty where a value is expected:** is the key in the GB-parity seed, or a registered ACF options-page field? (`tools/debug/bws-site-datetime-probe.php` logs allowlist parity for a given key.)
- **Wrong post on a link** (e.g. `get_permalink(1)`): V-link `entity_type==='site'` guard not reached before the post/term read.
- **Value resolves but link is empty on an ACF group subfield:** §B4 — both reads must route through `bws_site_read_option` (one reader).
- **Stale label in editor:** JS cache — hard-reload the editor.
- **`use` value selected but ignored** (e.g. excerpt/featured no effect): §B5 — resolver dispatching on key-presence instead of `use`.
- **`{{…|key:X}}` ignored when no explicit `use`:** §B6 — empty `use` not canonicalized to the stripped first-enum value.
- **`{{content src:site}}` returns the tagline (not empty):** §B7 — content has no site analog; the content default must resolve empty. (Tagline has no tag path — GB native `{{site_tagline}}` or `key:blogdescription`.)
