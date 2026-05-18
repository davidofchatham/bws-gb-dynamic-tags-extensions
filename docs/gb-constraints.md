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
  - `media` → built-in media library selector in editor. **Not used on any image tag in this plugin** (v1.6.0+): `type:'media'` blocks the source selector. Image tags use `'cross-source'` (or `'term'` for term-modifier image) with a custom `bws-media-picker` control for fallback selection.
  - `post` → standard post context
  - `term` → term context
  - `author` → author context

## GB Built-in Tags (for conflict checking)
post_title, post_excerpt, post_permalink, post_date, featured_image, post_meta, author_meta, comments_count, comments_url, author_archives_url, author_avatar_url, term_list, media, archive_title, archive_description, option, site_title, site_tagline, site_logo_url, site_url, current_year, term_meta, user_meta, loop_index, loop_item

## Supports Array Options
link, source, meta, date, image-size, taxonomy, comments, properties, instant-pagination

**`image-size` supports key:** activates GB's native image size control. GB destructures the reserved `size` key from `extraTagParams` before custom controls receive it, so a custom image-size control cannot read its own stored `size:` value reliably — use GB's native `image-size` support instead. Image tags in this plugin (`image`, `term_image`, `view_image`, `try_image`) declare `image-size` in `supports`; the native control handles parse/serialize and strips the `'full'` default automatically.

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

GB editor serializes named default values into the stored tag string even when the user never changed them. A PHP option definition like `'default' => 'none'` results in `{{tag key:none}}` on save, even for untouched options — creating unwieldy tags. Empty-string defaults (`''`) are dropped from the serialized tag.

GB's `parse_options()` only reads keys literally present in the tag string. Options absent from the string are absent from `$options` in the callback.

**Boolean serialization:**
- `true` serializes as a bare key only (e.g. `showCurrentYear`, NOT `showCurrentYear:true`).
- `false` = option dropped entirely — never appears in the tag string.

See [`tag-matrix.md` §Default serialization strategy](tag-matrix.md#default-serialization-strategy) for how this plugin works with the constraint (canonical-token first values, registration-boundary strip, intentional `as` opt-out).
