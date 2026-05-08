# GenerateBlocks Dynamic Tag Constraints

Catalogs GB editor/runtime constraints discovered while building this plugin.
Several discoveries here invalidated or revised approved option renames —
see [`deprecated-tags-options.md`](deprecated-tags-options.md) for the active
rename tracker. The "Already-renamed keys to avoid GB conflicts" section below
cross-references the constraints to the rename decisions they forced.

## Tag Name Prefix Rule
GB does not allow one dynamic tag to start with the same string as another existing tag.

**Example conflicts:**
- `post_meta` + `post_meta_related` → CONFLICT (second starts with first)
- `related_post_meta` + `related_post_meta_image` → CONFLICT
- `current_post_acf_date` + `current_post_acf_date_range` → CONFLICT

**Solution pattern:** Use suffixes that differentiate immediately:
- `current_post_acf_date_single` + `current_post_acf_date_range` → OK (neither is prefix of other)
- `related_post_meta_field` + `related_post_meta_image` → OK

## Custom Tag Types
- Single-word names confirmed working; **hyphenated names also confirmed usable** in the latest GB version (e.g. "select-a-source") — 2026-04-10
- Can define custom types beyond GB's built-in set
- Type determines available features:
  - `media` → built-in media library selector in editor — **planned for removal on all image tags** (see `.claude/plans/custom-image-controls.md`). `type:'media'` blocks source selector; custom controls replace it.
  - `post` → standard post context
  - `term` → term context
  - `author` → author context

## GB Built-in Tags (for conflict checking)
post_title, post_excerpt, post_permalink, post_date, featured_image, post_meta, author_meta, comments_count, comments_url, author_archives_url, author_avatar_url, term_list, media, archive_title, archive_description, option, site_title, site_tagline, site_logo_url, site_url, current_year, term_meta, user_meta, loop_index, loop_item

## Supports Array Options
link, source, meta, date, image-size, taxonomy, comments, properties, instant-pagination

**Reserved supports key conflict:** `image-size` is a built-in GB supports key that activates GB's native image size control. Do NOT use `image-size` as a custom type name or supports value — use `bws-img-size` or similar prefixed name to avoid activating GB's native rendering.

## Reserved Option Keys (extracted from extraTagParams)

GB destructures these keys out of `extraParams` in `DynamicTagSelect.jsx:385-395` BEFORE spreading into `extraTagParams`. Custom controls never receive their values; they round-trip only when GB's own re-emit logic fires.

**Reserved:** `id`, `source`, `key`, `link`, `required`, `tax`, `size`, `dateFormat`

Re-emit conditions:
- `tax` → only re-serialized when `'term' === dynamicTagType || tagSupportsTaxonomy` (line 553). Cross-source base tags (e.g. `text`, `image` with `gb_type:'cross-source'`) meet neither → `tax` is silently dropped on modal reopen.
- `tagSupportsTaxonomy` requires `'taxonomy'` in the tag's `supports` array.
- `source` → tag-type-specific re-emit; safer to use a non-reserved name (`src`).

**Workaround pattern — "two controls, one key":** present multiple UI controls (checkbox + selector) but persist a single non-reserved key whose presence/value encodes both signals. See `srcTermIn` (slug = enabled + slug, empty = disabled) implemented via `bws-term-hop` custom control type.

**Already-renamed keys to avoid GB conflicts:**
- `source` → `src` (registered as option migration)
- `tax` → `srcTermIn` on cross-source base tags (replaces `srcTerm` + `tax` pair)

## Option Default Serialization

GB editor serializes named default values into the stored tag string even when the user never changed them. A PHP option definition like `'default' => 'none'` results in `{{tag key:none}}` on save, even for untouched options — creating unwieldy tags.

**Rule:** All optional options must use empty string `''` or omit the `default` key entirely. Callbacks read `$options['key'] ?? ''` and treat empty as "not set". Unset/blank options are not serialized.

**Boolean serialization:**
- `true` serializes as a bare key only (e.g. `showCurrentYear`, NOT `showCurrentYear:true`).
- `false` = option dropped entirely — never appears in the tag string.
- Design boolean options as presence-flags: unset = false/default, present = true/non-default.

Confirmed via GB source: `parse_options()` only reads keys literally present in the tag string. Options absent from the string are absent from `$options` in the callback.

**Documented exception:** `image`/`term_image` `as:url` is always serialized — see [`tag-matrix.md` §Base tag GB types](tag-matrix.md#base-tag-gb-types-planned-architecture) "`as` serialization exception".

## Custom Control Types Registered

Via `generateblocks.editor.tagSpecificControls` filter:
- `bws-img-size` (ComboboxControl from `generateBlocksInfo.imageSizes`) — `assets/js/image-tag-controls.js`
- `bws-media-picker` (`wp.media()` modal, persists URL) — `assets/js/image-tag-controls.js`
- `bws-term-hop` (CheckboxControl + ComboboxControl over public taxonomies via `wp.data` `core`) — `assets/js/term-hop-control.js`. Reads `pickLabel` / `pickHelp` from PHP option config in addition to `label` / `help`.
