# Changelog

## [1.16.0] — unreleased

### Added

- **`{{text}}` now reads the author on an author archive,** joining `{{title}}` and `{{content}}` (1.15.0). With Use Entity Title it shows the display name; with a field key it reads the author's user field. `{{join}}` slots pick this up too, so a join renders on author archives. `try_` slots do not yet.

### Changed

- **`{{join}}`'s fallback option is now named `fallback`,** matching every other tag. It shipped in 1.15.0 as `fallback_text`, the legacy name every other tag had already moved away from. Because 1.15.0 is one release old and the option is new, this is a plain rename with no migration: a `{{join}}` tag that already has Fallback Text set needs it re-entered. Nothing else about the option changes.

### Removed

- Removed seven option-builder functions that no longer fed any tag (`bws_get_content_options`, `bws_get_custom_text_options`, and the four `bws_get_date*_options` builders for the retired date/datetime templates), along with an unused helper method on the source base class. These were internal leftovers from templates retired in earlier releases. `bws_post_term_extraction_options()` is kept, since external plugins can use it, and its fallback option is renamed to `fallback` to match the documented contract.

### Fixed

- **`{{text}}` no longer repeats the fallback text for each empty item in a list.** Reading a list (several taxonomy terms, or several related posts) where only some entries had a value inserted the fallback in place of each empty one, so a three-term list with one value rendered `Sales, N/A, N/A`. The fallback is a stand-in for the whole tag, not for one item, so it now renders only when nothing at all resolves, and the list shows just the values that exist. A single empty result also no longer picks up a link that belonged to a real value. Behavior for a tag reading one value is unchanged.
- **Stray characters no longer appear at the start of plugin output.** One plugin file carried an invisible byte-order mark, which PHP treats as content and prints on every page load. The visible effect was on WP-CLI: the first line of output from any `wp` command was prefixed with the stray bytes, which broke scripts that read a command's value directly. The same bytes were sent before any redirect or JSON response, so this also removes a latent cause of "headers already sent" warnings.

## [1.15.1] — 2026-07-20

### Fixed

- **WP-CLI commands no longer fail on an installed copy of the plugin.** Running any `wp` command with 1.15.0 active stopped WordPress before it finished loading, so unrelated commands (`wp search-replace`, for example) exited without doing their work. The plugin was loading a development-only file that is not part of the released download. Released builds now skip it, and the `wp bws render-tag` development command still loads when the file is present.

## [1.15.0] — 2026-07-20

### Highlights

- **New `{{join}}` tag combines several field values into one line,** with a separator or a format string, and drops the punctuation around empty values so a sparse record still reads cleanly. (Added)
- **Datetime tags now read the term on a taxonomy archive,** closing the gap left open in 1.14.0. (Added)
- **`{{title}}` and `{{content}}` now read the author on an author archive,** showing the display name and biographical info instead of an arbitrary listed post. (Added)

### Added

- **New `{{join}}` tag: combine several field values into one line.** Configure up to 10 slots, each reading its own text value (a meta/ACF field or the entity's title/name, from the current post, a related post, a taxonomy term, or a site option), and the tag assembles every non-empty value into a single string. Empty slots are skipped cleanly: no orphan commas, no doubled separators.
  - **Separator mode (default):** joins all non-empty values with a separator string (default `", "`; spaces are kept, so `sep: / ` gives ` / `).
  - **Template mode:** write a format string with `%1`…`%10` positional tokens (`%1 (%2)`), and punctuation attached to an empty token is removed with it. An empty bracketed part drops its brackets, an empty middle part sheds exactly one adjacent comma, a missing unit value drops its dangling mark (`%1′%2″` renders `5′11″`, or `5′` when inches is blank). Example: one format string renders `Dr. Tom M. Smith Jr., PhD, USN (Ret.)` for a fully filled person and `Jane Johnson` for a sparse one.
  - **Unit groups:** wrap a token and its unit text in tildes (`~%5 lbs.~`) and they disappear together when the field is empty, adjacent separator included; when the field has a value the tildes vanish and the unit renders. Handles unit words the punctuation rules can't reach (`%5 lbs.` alone would keep a stray `lbs.`). `~~` gives a literal tilde.
  - For unit strings, prefer the prime marks `′`/`″` over straight quotes. WordPress converts a straight `'`/`"` to a curly quote in normal page content but not when the block renders through a GP Element or hooked layout, so straight marks render inconsistently depending on where the block lives; the prime marks look the same everywhere.
  - **Each slot is a full text read.** Slots support related-post hops (`src:ref`), taxonomy term lists with a per-slot result limit, site options, and "Same as Previous Source" to weave several fields off one related entity. A stored `0` counts as a real value (`5′0″` renders, not `5′`).
  - **Fallback text** renders when every slot is empty; with no fallback the block output is empty so GenerateBlocks can hide it.
  - **In the editor** an unresolved `{{join}}` shows a configuration preview instead of a blank block, like the other tags. Separator mode lists the target fields (`[Join 'name_first', 'name_last']`); template mode shows the format string with each token replaced by its field name (`[Join "'name_first' ('name_last')"]`), so structure and bindings read as one string.
  - Output is plain text with no per-slot links, so composed values never produce nested or broken anchors.
  - Note: template tokens are `%1`…`%10` (not `{1}`), because GenerateBlocks cannot parse a `}` inside a tag. `%%` gives a literal percent sign before a digit.
- **Datetime tags now read the term on a taxonomy archive.** A bare `{{datetime_single}}` / `{{datetime_range}}` on a category, tag, or custom-taxonomy archive reads its date field from the term itself, the same current-entity rule text and title follow there since 1.14.0. Previously the datetime tags were post-only on a term archive and rendered empty or the fallback (the honest gap noted in the 1.14.0 notes as planned work). A term with no value in the field still renders empty or the fallback, and explicit sources (`src:site`, `src:ref`, `srcTermIn`) keep their meaning; only the bare-tag-on-term-archive case changes.
- **Datetime tags can now list several dates.** `{{datetime_single}}` and `{{datetime_range}}` gain the same `limit` / `sep` list mode base text and title already have: reading from a taxonomy term list (`srcTermIn`) or related posts (`src:ref`) with a raised limit renders every date, joined by the separator, instead of only the first. Empty dates are skipped; the fallback text fires once, only when nothing renders; a single result still gets its link, a multi-result list stays unlinked. On the range tag the result separator joins whole ranges while the existing range separator stays between each start and end. The two controls appear in the editor only for term-list and related-post sources. One subtle alignment with text/title: at the default limit of 1, the tag now reads the first term or related post only, where it previously scanned ahead to the first non-empty date; an empty first entry now yields the fallback.
- **`{{title}}` and `{{content}}` now read the author on an author archive.** A bare `{{title}}` shows the author's display name and `{{content}}` shows their biographical info (the user description). Previously the base tags were post-only there and showed an arbitrary listed post or nothing. This follows the same current-entity rule the term and title tags already use on a taxonomy archive: a bare tag reads its context. Explicit sources keep their meaning (`src:site`, `src:ref`, a query-loop row all still win), and `linkTo:permalink` links the author's archive URL. An author with no biographical info renders empty or the fallback. Other tags (`{{permalink}}`, `{{image}}`, the datetime tags) are not yet author-aware; only `{{title}}` and `{{content}}` are.

- **Every `try_` tag now accepts a site source per slot.** `try_text`, `try_title`, `try_content`, `try_image`, and `try_permalink` slots can be set to the Site source, so a fallback chain can end in a site-wide value: a post field first, then a site option or the site name. Each site slot resolves exactly as the base tag does with the site source. `try_title` gives the site name, `try_permalink` the site URL, `try_text` a site option or the site name, `try_image` a site option image or the site logo, `try_content` a rich-rendered site option (its content/excerpt modes stay empty, since a site has no body text). A single-result site value on a linking tag wraps to the site URL when a link is set. `try_email` and `try_phone` already supported this and are unchanged.

### Changed

- **Internal: datetime option keys are parsed in one place.** The public datetime option keys are now normalized by a single function shared by the render callbacks, the try_/term_ template closures, and the editor preview, replacing two mappers plus two hand-copied preview translations. No tag or option change; output is byte-identical (locked by a new pure-PHP formatter harness and a standing testbed matrix).

- **Internal: split the base-tag foundation into its own file.** The shared source, traversal, and dispatch helpers that every tag family builds on moved from `base-tags.php` into a new `base-shared.php`; `base-tags.php` now holds only the base tag renderers. No behavior change, no tag or option change.
- **Internal: the `{{text}}` tag's value resolution extracted from its render callback.** The full text read path (source resolution, term/site arms, list modes) now lives in its own function so future tags can reuse the exact same read; the render callback keeps only link-wrapping and the editor preview fallback. No behavior change.

### Removed

- **Internal: dropped a dead GenerateBlocks filter left over from the old image tags.** A media ID override hooked to `generateblocks_dynamic_tag_id` dated from the era when a tag name fixed its source; it was unreachable (eight of its nine tag names stopped registering in 1.14.0, and the ninth, `image`, resolves its source through the traversal pipeline instead). No behavior change.

### Fixed

- **A custom time format now applies to a two-ended time range.** `{{datetime_range as:time|format:...}}` previously honored the custom format only when a single time was present; with both a start and end time it fell back to a hardcoded 12-hour `g:i A`. The two-ended case now resolves its format through the same chain as the single-ended case (custom format, then the ACF field's time format, then the WordPress setting). AM/PM consolidation (`9:00–11:30 AM`) still applies for 12-hour formats; 24-hour and meridiem-less formats render both sides in full; midnight suppression keeps working regardless of format. (#25)

## [1.14.0] — 2026-07-08

### Highlights

- **Base tags now read the term on a taxonomy archive.** A bare `{{title}}` / `{{content}}` / `{{permalink}}` on a category, tag, or custom-taxonomy archive reads the term itself instead of an arbitrary post. (Added)
- **Deprecated tags no longer register with GenerateBlocks.** The old tag names are gone from the picker; the Migration Tool still finds and updates existing content that uses them. (Removed)
- **`{{text}}` / `{{title}}` with `src:ref` now list every related post,** not just the first, when you raise the result limit. (Fixed)

### Added — base tags resolve the term on a taxonomy term archive

- **On a category, tag, or custom-taxonomy term archive, a bare base tag now reads the term itself.** `{{title}}` shows the term name, `{{content}}` the term description, `{{permalink}}` the term URL. Previously a base tag on a term archive read from an arbitrary post (whatever the main query listed first), so it showed the wrong thing. Now a bare tag follows its context: the term on a term archive, the row post inside a query loop, the current post on a singular page.
  - **Term reference/relational fields are also supported.** A `src:ref` tag hops from the resolved term, reading its relationship field off the term instead of off an arbitrary post.
  - **Per-tag term reads:** `{{title}}` → term name, `{{content}}` → term description, `{{permalink}}` → term URL, `{{text key:…}}` → a term meta/ACF field. `{{image}}` stays empty on a term (a taxonomy term has no intrinsic image); set a key to read a term image field.
  - **`try_` chains match.** A `try_text` / `try_title` / `try_content` / `try_permalink` slot on a term archive resolves the term just like the standalone tag, so a fallback chain behaves the same whether it runs on a post or a term archive.
  - **This covers taxonomy term archives only.** Other archive types (the blog/posts index, search results, date, author, and post-type archives) are not yet context-aware: a bare base tag there still reads the first listed post, unchanged from before. Expanding proper resolution to those contexts is planned for a later release.

### Added — pre-upgrade warning when deprecated tags are about to stop rendering

- **The Updates screen now warns before an upgrade removes the old tag names.** When the available update is the one that stops registering the deprecated tags, its entry on Dashboard > Updates adds a caution line pointing you to the Migration Tool, so you can scan and fix affected content before updating rather than finding raw tag strings in your content afterward. The notice only appears on the upgrade that actually removes them and goes away once you are past it.

### Changed — bare tags on a term archive no longer read a stray post

- **Tags that have no term reading resolve to empty on a term archive instead of showing an arbitrary post's data.** The same context fix that lets `{{title}}`/`{{content}}` read the term also stops the tags that *can't* read a term from silently reading whatever post the archive listed first. On a term archive: `{{datetime_single}}` / `{{datetime_range}}` (which read post/site date fields, not term fields) and `{{call}}` (which needs a post) now return empty or their fallback rather than the first-listed post's value; a bare `srcTermIn` tag (which hops a *post* to its terms) likewise resolves empty, since a term archive has no post to hop from. This is the honest result where before the output came from an unrelated post. Reading term date fields is planned for a later release.

### Changed — deprecated-tag settings split into Deprecated and Removed, with scan-aware hiding

- **The settings page now sorts deprecated tags into two boxes: Deprecated Tags and Removed Tags.** A tag is "removed" once it no longer renders (its GenerateBlocks registration is gone), and "deprecated" while it still registers. All of this plugin's old N×M tags are Removed; context-modifier aliases registered by companion plugins (for example the portal tags) stay Deprecated while the current tag they point at is still live. The Keep/Suppress/Disable control stays on the Deprecated box for tags that still register.
- **Options split the same way.** Deprecated Options lists the option-key corrections still applied to current tags; a Removed Options box is reserved for the future point when an old option key is dropped from the reading code entirely. It is empty today.
- **The whole group moved above Diagnostics**, next to the Migration Tool it works with.
- **Boxes now hide entries that were not found in your content.** After a plugin upgrade or a Migration Tool scan, only the deprecated or removed tags and options actually present in your posts are listed, so the page reflects what you have rather than every name the plugin knows. Migrate a post and its old tag drops off the list on the next scan. The Migration Tool itself is always shown.
- **A "Show all" toggle in Diagnostics lists every registered entry regardless of scan results**, for auditing or if a scan looks wrong. It stays on until you turn it off.

### Changed — internal: traversal pipeline replaces the source-class matrix

- **Base and context-modifier tags (`term_*`, `view_*`) now resolve through one data-driven pipeline instead of a per-combination source class.** A single source factory works out the starting point (term, post, loop row, or site) and generic traversal steps handle the `src:ref` relationship hop and the `srcTermIn` term hop. Resolution is unchanged for existing content; this retires the N×M source-class growth and is what lets base tags become context-aware. The related-post source classes stay registered for the deprecated tag names that still use them.
- **`traversal_source_key` is now accepted-but-ignored in `register_modifier()`.** External plugins registering a context modifier no longer need a custom traversal source class — the `src:ref` hop is generic. Existing registrations pass the key unchanged and keep working; it may be dropped from new ones. See [plugin-integration.md](docs/plugin-integration.md). Verified against bws-portal-system: no portal changes required.

### Removed — deprecated tags no longer register with GenerateBlocks

- **All deprecated tag names (old N×M source×template tags, plus the eight pre-1.6.0 renames) are gone from the GB tag picker and no longer render.** They were already flagged deprecated in the editor and documented as due for removal; this completes that removal instead of leaving them registered indefinitely.
- **The admin Migration Tool (Settings → Tag Extensions) still finds and fixes them.** Scan and Migrate keep working exactly as before, so existing content with an old tag string gets a clean, correct upgrade path to its current equivalent — only the live rendering of the deprecated name itself is gone.

### Fixed — `{{text}}` / `{{title}}` with `src:ref` now list every related post

- **A related-field source with a raised limit now returns all matching posts, not just the first.** `{{title src:ref|ref:related_vendors|limit:5}}` lists up to five titles joined by the separator; before, it silently returned only the first related post even though the **Result Limit** and **Result Separator** controls were offered. The controls now do what they advertise. Default limit is 1, so a tag without an explicit limit is unchanged.

### Fixed — `datetime_single` / `datetime_range` no longer serialize the default source

- **Switching a datetime tag's Source away from and back to "Current" no longer leaves `src:current` written into the saved tag string.** Every other base tag strips its default select value at registration so the default token never gets serialized; `datetime_single` and `datetime_range` built their options through a dedicated function that skipped this step, so round-tripping the Source dropdown left a stray `src:current` (harmless functionally, but inconsistent with every other tag's wire format). No user-facing behavior change — output is identical either way.

## [1.13.0] — 2026-07-06

### Added — smart field selector (replaces blind key typing)

- **Every meta/option field key input is now a searchable field picker instead of a blank text box.** The `key`, `ref`, `linkKey` (link URL field), and all six datetime key inputs (plus their `try_` per-slot versions) list the registered fields on your site — ACF fields, their sub-fields, options-page fields, taxonomy-term fields, and core registered meta — so you pick a field instead of remembering its key. It works in **any editor context, including WP Patterns, GP Elements, and templates**, where GB's own selector shows nothing because it can only read the post you happen to be editing.
  - **Two filters narrow the list.** *Filter fields by location* drills through a path — `Post fields › Client Details › Coverage Options (repeater)` — so you can jump to exactly the group or repeater you mean; container fields are flagged `(repeater)` / `(group)`. *Filter fields by type* narrows to a field type (Date, Email, Relationship, …) or to fields usable inside a loop. The location filter auto-presets from the tag's own source (a `srcTermIn` tag opens on term fields, `src:site` on site fields) but never assumes the current post is the target — you can always override.
  - **The control label follows what you pick.** Narrow the location to a group and the label reads "Client Details Field"; narrow to a source and it reads "Post / Term / Site Meta Field". Datetime and relationship keys keep their specific labels.
  - **Type any key you like.** Unregistered keys (a plugin's raw meta, a key you know by heart) still work — start typing and choose *Use custom key: "…"* to commit it. A clear (✕) button empties the field. There is no separate "Add" step to forget.
  - **Same-named fields are handled honestly.** A field key that appears in more than one field group collapses to one entry that shows under every location it belongs to; two genuinely different fields that share a key but have different labels (a person's "Name" vs a repeater row's "Feature Name") stay as separate, distinguishable entries.
  - **Only fields the tag can actually read are offered** — the list is filtered through the same security gate the tag resolver enforces, so it never lists a key that would refuse to resolve.
  - The field list is assembled once per editor load and inlined into the page, so opening a tag never waits on a network request.

### Fixed

- **A custom key that is a substring of a listed field label can now be committed.** Typing a raw key like `city` no longer suppresses the *Use custom key: "city"* option just because a field labelled "City ('venue_city')" is in the list; the escape hatch now triggers on an exact key match, not a substring-of-label match.
- **A key that differs only in letter case from a listed field is now committable.** Meta keys are case-sensitive, so typing `event_date` when an `Event_Date` field exists now offers *Use custom key: "event_date"* instead of silently steering you to the differently-cased field.
- **A malformed field envelope no longer breaks the editor.** If the inlined field list fails to JSON-encode (malformed UTF-8 in an ACF label, or a very deeply nested repeater), the page falls back to an empty object and fetches the list over REST instead of emitting an invalid inline script. Field labels are also escaped so a label containing markup cannot break the editor page.
- **Meta keys registered for a specific post type or taxonomy now appear in the picker.** Previously only globally-registered meta was listed; a key registered for one post type (or taxonomy) is now offered too, matching what the tag can actually read.
- **A site-wide registered meta key is no longer hidden by a same-named custom field.** A global registered key stays in the list even when one post type also defines a field of the same name, so you keep the key on the post types where only the registered one applies.

## [1.12.0] — 2026-06-29

### Added — `{{call}}` function-passthrough tag (for developers)

- **New `{{call}}` tag runs a site-defined PHP function and outputs what it returns.** Some display values are too conditional for base tags to assemble (a function that branches on a term name, formats a score, looks up an indicator). `{{call}}` hands that work to a PHP function you write, binds the loop-correct post for it, and prints the returned string. This is a **developer tool: it ships empty and produces nothing until you allowlist a function** — every other tag works out of the box; this one does not, by design.
  - **Allowlist in code, not the database.** Register a function via the `bws_fn_passthrough_functions` filter or the `bws_register_call_function( 'my_fn' )` helper. The trust boundary is file/code access only; `{{call}}` grants editors no capability a developer didn't already hold in PHP. A security gate refuses anything that isn't a real, non-built-in function (so `system`, `unlink`, and friends can never be called).
  - **Post-context only.** The source menu offers **Current** and **In Reference/Relational Field** — both resolve to a post the function receives as its first argument. This fixes the Query Loop case where ambient `get_the_ID()` is wrong or empty (e.g. a relationship-field loop). Site and taxonomy-term sources are intentionally not offered: a `$post_id` function can't consume them.
  - **Optional single argument.** An **Argument** field passes one value (e.g. a format like `short` or `Y-m-d`); left empty, the function's own default applies.
  - **Output is verbatim and unescaped** — the function owns its own escaping (real functions return trusted display HTML). If the function is missing, unavailable, errors, or returns nothing, the tag outputs its **Fallback** instead; a thrown error is always logged server-side and never leaks to the page.
  - **Read-only allowlist mirror** under the BWS Dynamic Tags settings shows which functions `{{call}}` will accept and their status. The editor's function dropdown is populated from the same allowlist.
  - Known limit: flat ACF repeater rows (no underlying post) are not yet supported; the related-post and current-post loop cases are.

## [1.11.0] — 2026-06-26

### Added — `try_email` / `try_phone` / `term_email` / `term_phone`

- **`{{email}}` and `{{phone}}` now have full `try_` and `term_` variants**, generated from the shared modifier machinery — so a contact field gets the same first-available fallback chains and term-context resolution every other base tag already had.
  - **`try_email` / `try_phone`** build a fallback chain of up to 5 slots; the first slot that produces a value wins. Each slot resolves **exactly as the standalone tag would** — `try_email` returns a finished `mailto:` link per slot, `try_phone` a `tel:` link (with the same default-on link wrap, obfuscation, `tel:` rebuild, and validation as the base tag). This covers "personal email → team email → site-wide address" without stacking blocks and visibility conditions.
  - **A site-field slot is supported on `try_email` / `try_phone`** — site is the canonical contact fallback (personal address, then the site-wide one). A slot set to `src:site` reads the wp_options / ACF-options value, not current-post meta. (The other `try_*` tags still don't offer a site slot; that work is deferred per tag family.)
  - **`term_email` / `term_phone`** read a contact field off a taxonomy term (the term itself, or a related post via `src:ref`). They do **not** offer a `src:site` source — a rooting modifier surfaces term-distinct data, and a site read is entity-blind (it would just duplicate `{{email src:site}}`). For a site-wide contact read use the base tag or a `try_email`/`try_phone` site slot. The `src` dropdown omits `site` on every `term_*` tag; a hand-typed `src:site` resolves empty at the frontend and shows an invalid-combo warning in the editor preview (`⚠ Site source not valid on Term tag — use the base tag`). ([#37](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/37).)
  - Both `try_` tags carry the same `visibility` gate as the base tags (hidden on `a`/`button`/`img`/`picture`) plus a runtime media-block backstop — a media block's empty `tagName` slips the native gate, so the tag now returns nothing inside a media block rather than corrupting the `<img src>` with a link.

### Added — `try_text` / `try_title` list mode (`limit` / `sep`)

- **`try_text` and `try_title` now join a multi-result slot** instead of silently returning only the first result. When a slot reads across a term hop (`srcTermIn`) or related posts (`src:ref`), the new **Result Limit** and **Result Separator** options join the results — matching how the standalone `{{text}}` tag already behaved. Previously a `try_text` slot truncated a list its base tag would have joined; that parity gap is closed. Default limit is 1 (single result), so existing tags are unchanged unless you raise the limit.

### Changed — internal: shared source resolver + derived slot options

- **Unified the email/phone source-resolution code.** `{{email}}` and `{{phone}}` previously carried byte-identical copies of the field-read pipeline; both now share one resolver (`bws_resolve_field_values`). No behavior change — the same field reads, validation, and list mode — but the contact tags (base, `try_`, `term_`) now read through one path, which is what lets `try_`/`term_` reach full parity. (Issue #32.)
- **`try_` slot source/traversal options are now derived from the base builders** instead of hand-maintained inline copies, removing silent drift between a base tag's source options and its `try_` slots' (e.g. a missing `not:site` guard). Editor-surface only — no change to how tags resolve. (Issue #26.)
- **Editor preview now shows a `from Site` context for `src:site` base tags** (e.g. `{{text src:site\|key:blogdescription}}` → `['blogdescription' from Site]`), matching the existing `from Term` / `from Ref 'X'` segments. Previously a site source rendered no context clause. Preview-only.

## [1.10.1] — 2026-06-12

### Changed — vendored update checker moved to `libs/`

- Relocated the bundled Plugin Update Checker from `vendor/plugin-update-checker/` to [`libs/plugin-update-checker/`](libs/plugin-update-checker/). The library is hand-vendored, not Composer-managed, so `vendor/` was a misleading home; `libs/` reads honestly and matches the convention used across other BWS plugins (where a Composer-populated `vendor/` must be `.distignore`'d, forcing the checker out to `libs/`). Internal change only — the `require_once` path and one doc reference were updated; no behavior change.

## [1.10.0] — 2026-06-09

### Added — `{{phone}}` base tag

- New first-class `{{phone}}` base tag (cross-source like `{{email}}`/`{{text}}`, in `includes/tags/phone-tags.php`) that outputs a stored phone number, by default wrapped in a `tel:` link. The number is read from a meta/option field via the standard field-read path, so it works in every source: `src:site` → wp_options / ACF-options; `src:current`/unset → post/term meta; `src:ref` / `srcTermIn` → traversed-entity meta (list mode). Key-required in every source (no `use` enum).
- **`tel:` href is rebuilt from the stored value, preserving the author's separators.** Unlike `{{email}}` (whose href is the address verbatim), the `tel:` href is normalized into a canonical dial value. Hyphens appear in the href **only where the author wrote a separator** — `(987) 654-3210` → `tel:+1-987-654-3210`, but bare `9876543210` → `tel:+19876543210` (no fabricated grouping). The display text stays the stored value verbatim; only the href is reformatted. (Display-side formatting is a planned follow-up.)
- **Country code resolves 2-tier:** an in-field international prefix (`+…` or `00…`) wins; otherwise the global **Settings → Tag Extensions → Phone → "Default country code"** (digits only, empty default) is prepended. With no country code and no in-field prefix, a national `tel:` link (no `+`) is emitted — fine for single-country sites. A leading national trunk `0` is stripped when a country code is applied (e.g. UK `07911…` → `+44-7911…`). The country-code setting field shows worked-example placeholder text and links a country-code reference.
- **Separated leading country code is auto-deduplicated.** When a `+`-less number carries the global country code as its own author-separated first group (`1-800-555-1212`, `1 (800) 555-1212`, `1.800.555.1212` with country code `1`), the number already contains the code, so it is treated as international and the global code is **not** prepended again — `→ +1-800-555-1212`, never a doubled `+1-1-800…`. The author's separator is the disambiguating signal; this never fires on a flat bare-digit string.
- **Optional unseparated leading-country-code strip** (global **Phone → "Strip unseparated leading digit(s) matching the default country code"**, default OFF) covers the harder, *separator-less* case the auto-dedupe cannot — a code run together with the national number and no `+` (e.g. `18005551212` with country code `1`). With no separator there is no way to tell a real code prefix from a national number that begins with the same digits, so this is opt-in and warned. Matches the configured global country code only.
- **Default-ON `tel:` wrap toggled by `noLink`** (inverted bare key — absent = wrap, present = plain text), and `visibility`-gated off `a`/`button`/`img`/`picture`, mirroring `{{email}}`. Cross-source list mode (`limit`/`sep`) wraps each valid number individually. A `fallback` phone number fires only when no valid number resolves. Unparseable numbers (assembled digit count outside 7–15) are skipped this release.
- **Media-block safety backstop (also applied to `{{email}}`).** The native `visibility` gate cannot hide these tags on the GB **media block** — that block's `tagName` defaults to `""` and never serializes (its enum holds only `img`), so the value-compare leaves the tag offered, and GB then injects the output into the `<img src>`, corrupting it. Both callbacks now detect a `generateblocks/media` host block and emit nothing, so the default-on link wrap can never break an image. Editor-picker UX (hiding the tag in the selector there) is tracked in [#35](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/35); the GB constraint is documented in [`docs/gb-constraints.md`](docs/gb-constraints.md).

## [1.9.2] — 2026-06-08

### Fixed — cross-year `datetime_range` drops a year

- A `datetime_range` spanning two different years with "Omit Current Year" on (the default, `showCurrentYear` off) stripped the year from whichever endpoint fell in the current year, producing lopsided output like `August 12, 2025–June 1` instead of `August 12, 2025 – June 1, 2026`. `bws_format_date_range` now suppresses current-year omission for the whole range when the two endpoints fall in different years — the year is meaningful on both sides. Single-date and same-day ranges are unaffected (no second endpoint / shared year). Help text for the year toggle (`showCurrentYear` and legacy `omit_current_year` on range tags) notes the override.

## [1.9.1] — 2026-06-08

### Added — self-update from GitHub Releases

- Bundled the Plugin Update Checker library (5.7, vendored at `vendor/plugin-update-checker/`) and wired it to this repo's GitHub Releases. Installed copies now surface update notices and one-click updates from the WordPress Plugins screen, pulling the `.zip` asset attached to each tagged release (`enableReleaseAssets()` — dev files stay out of the shipped package via `.distignore`). Runs independently of the GenerateBlocks dependency check so fixes reach sites even when GB Pro is inactive.

## [1.9.0] — 2026-06-08

### Added — `src:site` unified site-wide source (Stage A)

- New `src:site` source value on the `text`, `title`, `permalink`, `image`, `content`, `datetime_single`, and `datetime_range` base tags — one source + one mental model for site-wide data, replacing the need to remember GB Pro's separate `{{site_title}}` / `{{site_tagline}}` / `{{site_logo_url}}` / `{{site_url}}` / `{{option}}` tags. `use` is the analog-vs-option lever (**uniform with every other source**), not key-presence; there is **no `use:option` value** (`src:site` selects the wp_options namespace the way `src:current` selects post meta). An empty wire `use` resolves to the tag's stripped first-enum value:
  - `text`: stripped default = key-mode — `{{text src:site|key:X}}` reads a wp_options value; `use:title` → site name; bare/no-key → empty. (No `use:tagline` — it fails the qualifying test both ways: no unique value over GB's native `{{site_tagline}}`, and no strong cross-source analog. Reach it there or via `key:blogdescription`. See `docs/tag-reference.md` §Qualifying test.)
  - `title`: site name (`get_bloginfo('name')`); no `use`/`key`.
  - `permalink`: site home URL (`home_url()`); no `use`/`key` (the site's own URL, never an option read; `site_url()` is not exposed this release).
  - `image`: stripped default = key-mode → `use:key`/`{{image src:site|key:X}}` reads an attachment-ID wp_options value; **the site logo is the explicit `use:featured` value** (customizer custom-logo, full `as:`/`size:`) — bare `{{image src:site}}` is key-mode and resolves empty without a key, *not* the logo. (`featured` stays explicit/serialized so the empty wire is an unambiguous key-mode signal; a stripped-default logo would be indistinguishable from a stale key. Reliable token authority is deferred to the custom-control work — see SPEC §B6.)
  - `content`: **no site content analog** — site has no long-form body datum (the site "description" is the *Tagline*, a short string), so the `content` default and `use:excerpt` both resolve empty. `content` is only meaningful under `src:site` with `use:key` → a wp_options read through the `bws_render_block_content` entry shipped in 1.8.0 (`do_blocks` + sanitize + recursion guard, keyed `'option:'.$key`), so block-markup options (e.g. an ACF Extended block-editor field on an options page) execute rather than printing raw markup.

    The analogs parallel post→{title, content, permalink, featured} and term→{name, description, URL, —}, except: the site image analog (logo) is reached by explicit `use:featured` rather than the bare tag; and the site has **no** content-body analog (`{{content src:site}}` → empty). The site Tagline (`blogdescription`) is a short string with no tag path here — use GB native `{{site_tagline}}` or `{{text src:site|key:blogdescription}}`. See `docs/tag-reference.md` §Source-analog resolution.
  - `datetime_single` / `datetime_range`: read ACF options-page date fields via `get_field($key,'option')` (the `key`/`end` controls), recovering the field's ACF return format through the normal format chain. **Primary driver:** ACF options-page date fields.
- Link wrapping for site sources (`text`, `title`, `datetime_*`): under `src:site`, `linkTo:permalink` resolves to `home_url()` — the site permalink-analog, matching field-unserialized `{{permalink src:site}}` (no separate `linkTo:site` value; permalink already IS the site's canonical URL). `linkTo:key` reads an option-stored URL (allowlist-gated).

### Added — `{{email}}` base tag

- New `email` base tag — outputs a stored email address, by default wrapped in a `mailto:` link, cross-source like `text` (highest value under `src:site` for an org contact email in a wp_options / ACF-options field). Key-required in every source (no analog, no `use` enum). Reuses the 1.8.0 field-read path, so it benefits from `src:site` without touching site code. Specific behavior:
  - **`mailto:` wrap is default-ON**, toggled off by the inverted bare key `noLink` (`{{email …|noLink}}` → plain text). Built as a minimal anchor — no `linkTo` / target / class (WP emits no class on mailto links either).
  - **`subject`** — optional `mailto:?subject=` line via the `bws-format-input` control; survives GB's tag-string round-trip (escaped editor-side, unescaped by GB server-side) and is `rawurlencode`d into the query at render.
  - **Obfuscation** — addresses run through `antispambot()` on both display and href, controlled by a new global **Settings → Tag Extensions → Email** toggle (default on; disable for a clean `mailto:` href).
  - **Validation + fallback** — `is_email()`-validated; the `fallback` option is a *fallback email address* (validated, wrapped like a real address). List mode (`srcTermIn` / `src:ref`) wraps each valid address individually and joins by `sep`; fallback fires only when no valid address resolves.
  - **`visibility` gate** — hidden in the tag selector on `a` / `button` / `img` / `picture` elements (first native `visibility` use in the plugin, mirroring GB core's `term_list`). See [`docs/tag-reference.md`](docs/tag-reference.md) §Email tag. Follow-ups: `img`/`picture` gate for text/title/datetime ([#31](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/31)), `try_email` parity ([#32](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/32)).

### Changed — editor labels

- The `use:key` value and the field-key control now read **"Meta/Option Field"** across `text`, `image`, and `content` (was "Meta/Custom Field" / "Custom Content Field (WYSIWYG/Blocks)" / "Field Key") — the same control now reads post/term meta *or* a wp_options / ACF-options value under `src:site`, so the label tracks that widened scope. The WYSIWYG/Blocks rendering note moved to the field-key help text. Analog `use` values name every source: image "Featured Image/Site Logo", content "Post Content/Term Description". Link-target control + value "URL Meta Field" → "URL Meta/Option Field". (Nomenclature: *field* is generic; *meta*/*option* are the subtype pair.)
- `try_` multi-slot labels front-load the slot ordinal as an `N: ` prefix (e.g. `2: Meta/Option Field`, `2: Source`) for legibility — was a trailing ` N` suffix / `Source N:` prefix mix.
- Spaced slashes normalized to tight slashes in user-facing labels (`Title / Name` → `Title/Name`, `Date / Time` → `Date/Time`, etc.).
- Field-key controls now carry **"Key"** in the label to distinguish them from the selector options they sit beside: field-key control "Meta/Option Field Key" (was "Meta/Option Field"), relationship-field control "Relationship Field Key", link-URL control "URL Meta/Option Field Key", and the datetime field keys "Date/Time Field Key" / "Start Date/Time Field Key" / "End Date/Time Field Key" (+ "… Time Field Key (optional)" variants). The `src` / `use` / `linkTo` *selector* option labels are unchanged (e.g. `use:key` stays "Meta/Option Field", `linkTo:key` stays "URL Meta/Option Field") — only the key-entry fields gained "Key". `try_` per-slot equivalents follow (`N: Meta/Option Field Key`, `N: Relationship Field Key`).

### Security — site option reads gated by a GB-Pro-parity allowlist

- All site option reads (site option key-mode, site `linkTo:key`, and the datetime `get_field(…,'option')` read) pass through the `generateblocks_dynamic_tags_allowed_options` filter, seeded to **GB Pro parity**: the six WP defaults (`siteurl`, `blogname`, `blogdescription`, `home`, `time_format`, `user_count`) plus every registered ACF options-page field (registration is the opt-in — ACF option fields read with no manual filter). `GenerateBlocks_Meta_Handler::get_option()` enforces a blocklist only (not this allowlist), so the gate is the resolver's responsibility — see [`docs/adr/0001-site-option-read-allowlist.md`](docs/adr/0001-site-option-read-allowlist.md). Mirrors GB Pro's `{{option}}` behavior so `src:site` is not gratuitously stricter than the tag it replaces.

### Fixed

- `limit` / `sep` (list-mode controls on base `text` and `title`) were shown unconditionally, including for scalar sources that can only ever return one value. They now carry `show_if_any => { srcTermIn: not_empty, src: ref }` — visible only when the final traversal step can yield multiple results (terms via `srcTermIn`, or related posts at `src:ref`). Pre-existing over-exposure; surfaced and broadened by `src:site`, which hides both `ref` and `srcTermIn` and so now also hides `limit`/`sep`. See `docs/tag-reference.md` §List mode.
- `bws_parse_combined_date_time` passed the numeric-coerced id to the field **value** read, so a non-numeric ACF object-id sentinel (`'option'`) was lost before reaching `bws_read_field`. The value read now threads the `'option'` sentinel independently of the format-lookup object-id. (Prerequisite for site-datetime; no effect on existing post/term/loop callers.)
- Site `linkTo:key` (`{{… src:site|linkTo:key|linkKey:…}}`) read the option through a raw `get_option()` instead of the reader the value path uses, so it lacked dot-path traversal and the ACF `get_field` filter — ACF options-group subfields (e.g. `organization_social.facebook`) resolved as a value but failed when used as a link target. Both site wp_options reads (key-mode value + `linkTo:key`) now share one canonical reader (`bws_site_read_option`), so the value and the link always agree.

### Notes

- Stage A only: no `Site` source class and no registry registration (site is a dropdown value + early-gate resolver). `try_` slot support is staged separately ([#26](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/26)). Per-value link-target gating ([#27](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/27)) and `src:site` → reference-field resolution ([#28](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/28)) tracked as follow-ups.

## [1.8.0] — 2026-06-01

### Refactor — Post content rendering pipeline extracted to `ContentProcessor` class (C4, issue #3)

- `includes/classes/content/class-content-processor.php` (new): post-content render pipeline (recursion guard, memory check, `do_blocks` dispatch, inline-CSS extraction + footer queue, fallback path) now lives as `\BWS\DynamicTags\Content\ContentProcessor`. Procedural API in `includes/helpers/content-helpers.php` preserved as thin wrappers — no caller-visible signature changes.
- New generic entry: `bws_render_block_content( $raw, $cache_key, $args )` / `ContentProcessor::render()`. Recursion stack now keys on a caller-supplied string (`'post:'.$post_id` for post-content, `'option:'.$key` reserved for v1.9.0 `src:site` wp_options rendering) rather than an integer post ID. Required to support rendering block markup that doesn't live in a `wp_posts` row.
- `$GLOBALS['bws_content_processing_stack']` removed. Stack state is now sole property of `ContentProcessor` (static array). `bws_content_debug_end()` reads depth via `ContentProcessor::stack_depth()`.

### Added — Content pipeline filters

- `bws_content_memory_threshold` (float, default `0.80`): memory-usage fraction below which the primary render path runs. Filter to `0.0` to force the fallback path; raise toward `1.0` to allow primary rendering closer to OOM. Replaces the previous hardcoded 80% threshold.
- `bws_content_max_recursion_depth` (int, default `3`): maximum content stack depth before further pushes are blocked. Replaces the previous hardcoded depth-3 cap.

### Refactor — `includes/helpers/content-helpers.php` split into single-concern files

- `includes/helpers/field-helpers.php` (new): post-meta/ACF reads (`bws_read_field`, `bws_read_term_field`, `bws_meta_handler_read`, `bws_get_loop_row_context`, `bws_resolve_acf_object_id`, `bws_extract_post_id`, `bws_get_related_posts_data`, `bws_is_valid_meta_key`). Moved verbatim.
- `includes/helpers/preview-helpers.php` (new): editor preview-label builders (`bws_build_preview_label`, `bws_build_try_preview_label`, `bws_try_preview_template_label`, `bws_try_preview_field_part`, `bws_try_preview_source_part`, `bws_try_preview_datetime_part`, `bws_wrap_preview_label_with_link`). Moved verbatim.
- `includes/helpers/link-helpers.php` (new): `linkTo` / `linkKey` resolution + `<a>` wrapping (`bws_resolve_link_url`, `bws_wrap_with_link`, `bws_get_link_options`, `bws_map_gb_link_option`). Moved verbatim.
- `includes/helpers/registration-helpers.php` (new): GB-registration / wire-format helpers (`bws_strip_default_select_values`). Moved verbatim from `content-helpers.php`.
- `includes/helpers/content-helpers.php` slimmed to ContentProcessor wrappers + `bws_sanitize_rich_content` + relationship-field-options + `bws_is_query_loop_setup_phase` + `bws_safe_content_output`.

### Docs

- `docs/post-content-processing-reference.md`: rewritten primary/fallback pipeline + recursion sections to reflect `ContentProcessor`. New filters documented; public API table now distinguishes class-level methods from procedural wrappers and notes `bws_render_block_content` as the generic entry. Cache key contract documented (`'post:'.$id`, `'option:'.$key`, collisions defeat the guard).
- `docs/plugin-integration.md`: helper section split into Field / Preview / Link / Content / Registration subsections matching the new file layout. Added Filters table.

## [1.7.4] — 2026-05-29

### Fixed — `datetime_range` consolidation dropped data with day-name or time tokens

- `includes/helpers/datetime-helpers.php` `bws_format_multi_day_range()`: same-month consolidation collapses the end side to a bare day number (`August 1–9`) via `extract_day()`, which returns the first match of `[dDjlNS]`. That collapse is only valid for pure-date formats:
  - **Day-name tokens** (`l`, `D`, `N`) differ across days — a format like `l, F j, Y` matched `l` first, so the end side rendered as just `Sunday` and the rest was lost (`Saturday, August 1–Sunday` instead of `Saturday, August 1 – Sunday, August 9`).
  - **Time tokens** caused a lopsided collapse — with the default `as`-less datetime format the start kept its time while the end was reduced to a bare day (`April 1, 2026 3:27 PM–30`).
  Both cases now block the day-collapse (`$blocks_day_collapse`) and fall through to the same-year branch, which keeps the end side's full format.

### Fixed — `remove_year()` left the year in datetime formats

- `includes/helpers/datetime-helpers.php`: `remove_year()` only matched a year token adjacent to a comma or at the end of the string, so a datetime format like `F j, Y g:i A` (year mid-string, followed by a space and the time) kept its year in consolidated range output. Rewritten to drop the year token wherever it sits — before a space-and-time, leading with a `-`/`/` separator (`Y-m-d`), etc. — then normalise leftover punctuation.

### Changed — `as` selects components, custom `format` only styles them

- `includes/helpers/datetime-helpers.php` `bws_build_range_format()`: range formatting was asymmetric — a date-only custom format had time auto-appended (when the field carried time), but a time-only custom format did **not** get the date prepended, so `format:g:i A` silently dropped the date while `format:l, F j, Y` kept the time. Range formatting now reconciles symmetrically with the single-date path: `as` decides **which** components render; the custom format only supplies their **style**. Missing components are completed and excluded components stripped on both sides. Gap-fill style for a missing component comes from the ACF field format first, then the WordPress `date_format` / `time_format` options (no hardcoded constants).
- `includes/tags/datetime-tags.php` + new `bws_resolve_time_only_format()`: single-ended `as:time` ranges now honor a custom time format (reduced to its time tokens) → ACF time format → WP `time_format`, instead of hardcoding `g:i A`. Two-ended `as:time` ranges still hardcode `g:i A` for AM/PM consolidation — tracked as issue #25.

### Fixed — `format` / `fallback` option round-trip corruption on tag reopen

- `assets/js/format-input-control.js` (new): GB's JS `parseTag()` splits each `key:value` pair on the first unescaped colon but does **not** unescape the value, while GB's serializer writes `${key}:${value}` raw with no escaping. Format strings containing a colon in the time portion (e.g. `l, F j, Y, g:i A`) round-tripped as `l, F j, Y, g` on reopen — everything after the time colon was discarded. Both `datetime_single` and `datetime_range` `format` options now use a custom control type `bws-format-input` that escapes `:` → `\:` and `|` → `\|` on save and unescapes for display. PHP `parse_options()` already unescapes both sequences (`class-register-dynamic-tag.php:60`), so render-side behavior is unchanged.
- `assets/js/format-input-control.js`, `assets/js/image-tag-controls.js`: clearing a custom-control value left a bare `format:` / `fallback:` in the tag string, because GB's serializer only skips a key when its value is `false` (not `''`). Both controls now delete the key from state on empty (matching GB's native `handleChange`), so the option is dropped entirely.

### Docs

- `docs/gb-constraints.md`: corrected the tag-string escape section — GB's PHP `parse_options()` **does** honor `\:` and `\|` escapes (it was previously documented as having no colon escape); the real limitation is the JS-side `parseTag()` asymmetry, now documented along with the custom-control escape/unescape workaround.
- `docs/tag-reference.md`: added `bws-format-input` to the custom control table; updated the `bws-media-picker` row to reflect ID storage.

## [1.7.3] — 2026-05-28

### Fixed — `bws-media-picker` fallback corrupting tag string on reopen

- `assets/js/image-tag-controls.js`: media picker stored the selected attachment's `source_url` in the option key. URLs contain `:` (scheme) and `/` characters that collide with GB's tag-string parser — `parse_options()` splits on `:` with no escape sequence, so `fallback:https://host/path.jpg` round-tripped as `fallback:https` only, dropping the actual URL on modal reopen. Picker now stores the attachment ID (`att.id`) and re-fetches the preview URL via `wp.data` `core` store `getMedia(id)` for display. `bws_handle_media_fallback()` already accepted IDs (legacy code path), so render-side behavior is unchanged for existing URL-based tags; only the tag-string round-trip is fixed.

### Docs

- `docs/gb-constraints.md`: new §Tag-string-unsafe values documenting the colon/pipe parser limitation, workarounds (store ID, protocol-relative, encode), and pointer to the `bws-media-picker` ID-storage decision.

## [1.7.2] — 2026-05-25

### Fixed — `datetime_single` / `datetime_range` in ACF repeater query loops (issue #22)

- `bws_datetime_single_core()` and `bws_datetime_range_core()`: hard `! $post_id` bail returned fallback before the field-read layer could resolve loop-row data. Tags inside GB Pro repeater loops (`TYPE_OPTION` site-options repeaters and `TYPE_POST_META` flat repeater rows) saw no output. Bail now relaxes when the block instance is in a loop-row context (`generateblocks/loopItem` set), matching the v1.7.0 pattern used by `bws_post_custom_text_core()`. Mode 2b reads via `bws_read_field()` then resolve the date/time values from `$loop_item[$key]`.
- `bws_parse_combined_date_time()`: ACF field-config lookups (`bws_get_acf_return_format()`, `bws_parse_acf_date_value()`) received `$post_id = false` in Mode 2b, so `get_field_object()` couldn't return the configured return_format. Custom return formats fell through to the generic-format parser and could mis-parse non-default storage. Resolves the ACF object_id once at the top of the function via the new `bws_resolve_acf_object_id()` helper and threads it through both lookups.
- `bws_parse_combined_date_time()` time-only inheritance: when ACF return_format lookup failed (flat repeater subfields not findable via the resolved object_id), `$date_is_time_only` was false even for time-only stored values like `"14:30:00"`. The inheritance branch was skipped, and `DateTime::createFromFormat( 'H:i:s', ... )` produced a DateTime at today's date + parsed time instead of the start-field's date. Now falls back to raw-value pattern inspection via new `bws_value_looks_time_only()` helper when format metadata is unavailable, restoring the documented behavior ("Time-only values inherit date from start").
- `bws_datetime_range_core()` with `as:time` on time-only fields: when both `startKey` and `endKey` resolved to time-only ACF stored values (no date component), the time-only-range branch was unreachable — it required `$start_result['date']` to be populated, but start-side time-only values land under `$start_result['time_only']` (no `$inherit_date` available for start context). The tag fell through to the diagnostic partial-parts branch, rendering `Start time: 4:00 PM; End time: 7:30 PM` instead of `4:00 pm–7:30 pm`. Time-only branch now accepts either `date` or `time_only` from parse results, and runs before partial-parts.

### Added

- `bws_resolve_acf_object_id( $instance, $post_id )` in `content-helpers.php`: single source of truth for resolving the ACF object_id used by `get_field_object()` / `get_field()` when the caller has no resolved row entity. Returns the explicit `$post_id` when set, `'option'` for GB Pro `TYPE_OPTION` rows, the outer page's `postId` (from context) for `TYPE_POST_META` rows, or `0` otherwise. Reusable by other ACF-aware helpers (image-helpers, relationship validation).
- `bws_value_looks_time_only( $value )` in `datetime-helpers.php`: format-agnostic detection for time-only ACF stored values (`"14:30:00"`, `"2:30 PM"`, etc.). Used as fallback in `bws_parse_combined_date_time()` when ACF return_format lookup fails on repeater subfields.

## [1.7.1] — 2026-05-21

### Fixed
- `deprecated-tags.php`: `related_post_content` migration entry had `new_tag => 'title'`; corrected to `'content'`. Callback was already correct; only the migration hint was wrong.
- `includes/hooks.php` (new): GB's `required` check uses `! $replacement` (falsy, not empty-string), silently killing blocks for two legitimate cases: `as:alt` with no alt text (empty string), and text fields returning `'0'` (e.g. jersey number zero). Filter on `generateblocks_dynamic_tag_replacement` — the only hook between callback return and the required check — returns `' '` for empty alt and `'0 '` for bare zero. Both render correctly in HTML; trailing space collapses in text content, space is semantically equivalent to empty alt.
- `bws_read_field()`: in GB query loops, Mode 2a (`row_post_id`) and Mode 2b (`loop_item`) branches read from the row entity even when caller passed an explicit `$post_id`. Broke any meta-field tag (`try_text` `use:key`, `try_content` `use:key`, `try_image` `use:key`, base `text`, `content`, `image` custom-field paths) whose `src:ref` slot resolved a post outside the loop row — the resolved id was ignored and the loop row was read instead, yielding empty results when the row entity lacked the field. Explicit `$post_id` (from upstream resolution like `bws_resolve_post_by_source`) now always wins; loop branches only fire when no explicit id was passed.

## [1.7.0] — 2026-05-20

### Added — Link wrapping for text/title/datetime tags
- `linkTo` / `linkKey` / `newTab` options on `text`, `title`, `datetime_single`, `datetime_range` (base tags, `term_` modifier tags, and `try_` variants). Excluded: `content`, `permalink`, `image`.
- `linkTo` values: `permalink` (entity permalink) or `key` (URL from `linkKey` meta field). Unset = no link.
- `newTab` presence-flag: adds `target="_blank" rel="noopener noreferrer"` when set.
- Link options appear after fallback text in each template's option list.
- Link wrap applied after fallback resolves; empty `linkKey` or unresolvable URL skips wrapping without affecting tag output.
- `try_` tags: single `linkTo`/`linkKey` applies to the winning slot's entity (post or term). No per-slot link key.
- `term_` modifier tags: entity type routed automatically (term for base-source dispatch; post for `src:ref` dispatch; term for `srcTermIn` hop).
- New helpers in `content-helpers.php`: `bws_resolve_link_url()`, `bws_wrap_with_link()`, `bws_get_link_options()`, `bws_map_gb_link_option()`.
- Editor preview labels for link-eligible templates now annotate the configured link destination (e.g. `[Title (link: permalink)]`) and wrap the bracket string in `<a href="#">` so the link treatment is visible in the block editor even when the tag can't resolve a real value.

### Changed — Docs
- `docs/tag-matrix.md` renamed to `docs/tag-reference.md`; title updated to "BWS Dynamic Tags — Tag & Option Reference". All cross-links updated.
- `linkTo` meta-field destination token renamed `'meta'` → `'key'` for consistency with the plugin-wide `key` convention. Saved tags using `linkTo:meta` will not be present in the wild (v1.7.0 not yet released).

### Fixed — Migration: link option remapping for deprecated tags
- `related_post_content` `transform_callback` now maps old `link_to`/`link_field`/`new_window` options → `linkTo`/`linkKey`/`newTab`. Previously these were silently dropped. Content/excerpt migration targets still drop link options (content tag excluded from link wrap).
- Six deprecated tags that had GB-native `link` support (`related_post_title`, `related_post_custom_text`, `post_term_title`, `post_term_custom_text`, `term_related_post_title`, `term_related_post_custom_text`) now remap `link:post` → `linkTo:permalink`, `link:post_meta,<key>` → `linkTo:key|linkKey:<key>`, `link:term` → `linkTo:permalink`. Other GB link destinations (`author_archive`, `author_meta`, `author_email`, `comments`) dropped (no equivalent). Handled via `gb_link_remap` flag added to `MigrationRegistry::run_transform()`.

### Fixed — Migration: `related_post_content` transform and preview label
- `related_post_content` was a multi-field tag in the original (pre-N×M) codebase whose `target_field` option selected what to extract (`post_title`, `post_content`, `post_excerpt`, `custom`). The migration entry incorrectly mapped all instances to `{{content}}` regardless of `target_field`. Now branches correctly: `post_title`/absent → `{{title src:ref|ref:…}}`; `post_content` → `{{content src:ref|ref:…}}`; `post_excerpt` → `{{content src:ref|ref:…|use:excerpt}}`; `custom` → `{{text src:ref|ref:…|key:{custom_field}}}`. Both `key` and `rel` accepted as the relationship field (old tag used `key`).
- `MigrationRegistry::transform_tag()`: added `transform_callback` support — when a registry entry includes a `transform_callback` callable, it is invoked instead of `run_transform()`, enabling branching transforms that can't be expressed as rename maps.
- `bws_build_deprecation_preview_label()`: strip GB-injected `tag_name` key from `$options` before reconstructing the tag string for migration preview. GB's `parse_options()` always prepends `tag_name` to every callback's options array; without this strip, every deprecated tag preview included a spurious `tag_name:…` option in the suggested replacement.

## [1.6.2] — 2026-05-19

### Added
- Plugin action links: "Settings" link now appears in the Plugins list, pointing to the Tag Extensions settings page.

### Fixed — Editor preview: resolve-then-label (#21)
- Base tag callbacks (`text`, `content`, `title`, `image`), modifier callbacks, try callbacks, and datetime callbacks now attempt resolution before falling back to a structured label; tags that can resolve in the editor (e.g. `{{title src:current}}` while editing a post) show live values instead of labels
- Removed `REST_REQUEST` short-circuits from `bws_post_title_core`, `bws_post_excerpt_core`, `bws_post_custom_text_core`, `bws_post_content_core` — those guards prevented resolution even when GB had already provided a valid post ID
- `bws_resolve_post_by_source`: Mode 2b flat-row bail now skipped when GB has injected an explicit `id:` option (editor REST context), allowing `src:current` tags inside query loops to resolve via the injected post ID
- `bws_read_field`: Mode 2b array read now skipped when a valid `$post_id` was passed in, allowing custom-field and datetime tags inside query loops to read post meta via the resolved ID rather than attempting a flat-row array lookup

### Fixed — Datetime range editor preview
- Default range-end offset for `datetime_range` / `datetime_single` (unset `as`) corrected from +1 day to +1 hour, matching `as:time` behavior
- Preview separator default changed from ` – ` (spaced) to `–` (bare en-dash), matching frontend output
- Range preview now routes through `bws_format_date_range()` instead of naïve string concatenation, so same-day smart AM/PM consolidation (e.g. `10:02–11:02 AM`) and year-omission apply correctly in the editor
- `showCurrentYear` / `showMidnight` options now respected in preview (previously both ignored; `smart_time` defaulted to `false`, causing midnight suppression to never apply)

## [1.6.1] — 2026-05-18

### Fixed — Migration pipeline
- `MigrationRegistry::serialize_tag_string()`: PHP `true` values now serialize as bare keys (e.g. `showMidnight`, not `showMidnight:true`) matching GB's boolean serialization convention
- `apply_datetime_transforms()`: `smart_time` and `omit_current_year` no longer auto-injected based on absence of old key (old defaults serialized as bare keys, so absence is ambiguous). Only explicit `:false` override maps definitively to new boolean: `smart_time:false` → `showMidnight` bare; `omit_current_year:false` → `showCurrentYear` bare
- `apply_option_migration()` now loops until stable (cap 16) so overlapping/cascading option-migration entries all apply in one converter call
- `MigrationRegistry`: added `match_any_options` entry field (OR semantics) alongside existing `match_options` (AND semantics); `find_option_migration()` and scanner in `class-tag-converter.php` honor it; `group_option_entries_by_transform()` includes it in signature + group data
- Added `type:'option'` `MigrationRegistry` entries for live `datetime_single` and `datetime_range` tags carrying pre-v1.6 option keys (`date_time_field`, `time_field`, `start_field`, `start_time_field`, `end_field`, `end_time_field`, `separator`, `date_time_separator`, `fallback_text`, `format_type`, `custom_format`, `date_only`, `time_only`, `smart_time`, `omit_current_year`) — covers partially-migrated tags where tag name was renamed but option keys were not
- Added `type:'option'` entries for remaining live base-tag legacy keys: `fallback_text` → `fallback` (text, content, title, permalink, image); `via`/`from` → `src` (all 7 base tags); `type` → `use` + `custom_field` value → `key` (content); `return_type`/`fallback_url`/`field_key` → `as`/`fallback`/`key` (image, term_image, try_image); legacy slot keys `src_N`/`rel_N`/`key_N` → v1.6 slot syntax (all try_ tags)

## [1.6.0] — 2026-05-18

### Architecture (v1.5.0 → v1.6.0)
- Pattern B completed: related-variant mechanism replaced by standalone source classes (`RelatedPost`, `TermRelatedPost`)
- N×M per-source tag matrix replaced by base (source-agnostic) tags + context-modifier registry. Single `image`, `text`, `content`, `title`, `permalink`, `datetime_single`, `datetime_range` tags with `src` option (rename pipeline `via`→`source`→`src`; intermediate `source` rejected as GB-reserved key — see Fixed). Old per-source tags become deprecated wrappers via the migration registry below.
- Source dispatch simplified to two values: `''` (current entity) and `'ref'` (relationship field hop)
- Option ordering standardized per three-group structure: global formatting → per-slot → global fallback

### Added — Migration / registry infrastructure
- `MigrationRegistry` (`includes/classes/class-migration-registry.php`): unified transform registry supporting `type:'tag'` (deprecated tag name) and `type:'option'` (live base tag option-key) entries; shared 7-step `run_transform()` pipeline; public `parse_tag_string()`, `format_tag_string()`, `transform_tag()`, `apply_option_migration()`, `get_deprecated_tag_names()`, `get_option_migrations_by_tag()`
- `DeprecatedTagRegistry`: externally-registered deprecated tag wrappers with `transform_options()` migration pipeline (`source_inject`, `option_renames`, `value_renames`, `fixed_options`, `datetime_transforms`). Refactored as thin 4-method facade over `MigrationRegistry` (see Changed).
- `DeprecatedTagRegistry::has_migration_path( string $old_tag ): bool` for converter and admin UI use
- `combine_options` `MigrationRegistry` primitive: maps `[when_present, value_from] → new_key`; both old keys always dropped; new key emitted only when presence-flag and value both present. Runs as Step 2 of the transform pipeline (before `option_renames`). Used to migrate hand-written `srcTerm` + `tax:<slug>` strings via the Migration Tool; reusable for future combined-option migrations.
- `MigrationRegistry` option entries for all 7 base tags matching `tax` presence: combine `srcTerm` + `tax:<slug>` → `srcTermIn:<slug>` so the admin Migration Tool detects and converts legacy term-hop strings.
- Deprecated term-extraction tag entries (15 across `post_term_*`, `related_post_term_*`, `term_related_post_term_*` families): `$srcterm_fixed` injection removed; `tax` → `srcTermIn` rename merged into `option_renames` so migrator output matches the new key.
- `bws_register_option_migrations()` in `deprecated-tags.php`: registers `type:'option'` `MigrationRegistry` entries for all base tags carrying a `rel` option key — renames `rel` → `ref` and prepends `src:ref` (fixes broken converter output from the `via`→`src` rename cycle)
- `TagTemplateRegistry::register_modifier()` and `generate_base_try_tags()`: term_ and try_ modifier tags generated from modifier template descriptors

### Added — Editor preview labels
- `bws_build_preview_label( $options, $template )` in `content-helpers.php`: structured editor preview labels for unresolvable base and modifier tags (e.g. `['body_text' from Ref 'rel_post']`, `[Date like “April 24, 2026”]`, `[⚠ No taxonomy set]`)
- `bws_build_try_preview_label( $options, $base_template )` in `content-helpers.php`: structured editor preview labels for try_ tags. Walks slots 1-5, applies carry-forward, emits `[Try Text: 'a', 'b', Title]`-style summaries with per-slot source segments when source differs from slot 1. Per-slot warnings (`[⚠ Try: slot 2 no key, slot 3 no ref]`); empty-config warning (`[⚠ Try: no slots configured]`); image excluded for `as:url`/`as:id` modes. Helpers `bws_try_preview_prefix`, `bws_try_preview_field_part`, `bws_try_preview_source_part` for shape pieces. Try callback short-circuits on `$inst->context['bwsEditorPreview']` to call this builder.
- `assets/js/editor-preview-context.js`: injects `bwsEditorPreview: true` into GB's dynamic tag preview context; activates structured preview labels in block editor
- In-editor deprecated tag preview warnings: all deprecated callbacks check `$instance->context['bwsEditorPreview']` and return `[⚠ {{old_tag}} deprecated — use {{new_tag_with_actual_options}}]`; `bws_build_deprecation_preview_label()` helper calls `MigrationRegistry::transform_tag()` to show actual replacement
- `bws_build_preview_label()` reads `srcTermIn` (with `tax` legacy fallback) when deriving term-hop missing-taxonomy warning so the new key no longer triggers a false "No taxonomy set" preview.

### Added — Custom editor controls
- `bws-media-picker` (`wp.media()`) custom editor control for image-tag fallback (`assets/js/image-tag-controls.js`). Initial release also shipped a `bws-img-size` ComboboxControl; superseded mid-cycle by GB's native `image-size` support — see Changed.
- `srcTermIn` term-hop option on base tags (`text`, `content`, `title`, `permalink`, `image`, `datetime_single`, `datetime_range`): single persisted key encodes "term hop enabled + taxonomy slug" — empty/absent = disabled, slug = enabled. Replaces the prior `srcTerm` (boolean) + `tax` (slug) pair. `bws-term-hop` custom control (`assets/js/term-hop-control.js`) renders sibling CheckboxControl + ComboboxControl (taxonomies sourced via `wp.data` `core`, public-only); checkbox is React-local state, only the slug round-trips through `extraTagParams`. Resolves GB-reserved-key conflict where `tax` was extracted and silently dropped on modal reopen for cross-source base tags. Term-modifier (`term_*`) tags continue to use GB's native `tax` selector. Legacy `srcTerm` boolean stripped from state on mount so existing tags re-serialize cleanly.
- `srcTermIn` term-hop control on modifier tags: `register_modifier()` now reuses `bws_base_traversal_options()` so all modifier prefixes (`term_*`, `view_*`, etc.) get the term-hop control. Term-context base sources (e.g. `term_*` from `TaxonomyTerm`) gate visibility to `src:ref` only — at `src:current` the entity already IS a term, so inner-term-hop is meaningless. Post/unknown-context base sources (e.g. `view_*` from `PortalSource`) show the control unconditionally. Modifier callback dispatches term-hop via `bws_get_srcterm_terms( $post_id, $tax )` loop calling `term_fn` per term, returning the first non-empty result (mirrors `bws_base_image_callback`).
- `show_if` conditions `in:` and `not_in:` added to `editor-conditional-options.js`

### Added — Admin Migration Tool
- Admin Migration Tool (`includes/classes/admin/class-tag-converter.php`): `scan()` queries all non-revision posts via multi-LIKE SQL then PHP-level regex+parse verification; `migrate_post()` calls `wp_save_post_revision()` for pre-migration snapshot, applies full deprecated tag and option-key transforms, writes via `$wpdb->update()` + `clean_post_cache()` to avoid hook side-effects and duplicate revisions
- `assets/js/admin-tag-scanner.js`: Scan button → paginated AJAX scan; results table with post title, type, issues list (deprecated tags + option migrations), per-row Migrate button; Select All / Bulk Migrate Selected with progress bar; per-row status shows tag and option fix counts; ⚠ note when post type has no revision support
- Suppress mode for deprecated tags: callback returns `''` immediately when `SettingsPage::is_deprecated_tag_suppressed()` is true, preventing unprocessed tag strings on the frontend
- Modifier toggle controls in admin settings page (term_, try_ enable/disable)

### Added — Field-extraction helpers
- `bws_read_field( $key, $instance, $post_id, $single_only = true )` and `bws_read_term_field( $key, $term_id, $single_only = true )` in `content-helpers.php`: unified field-extraction helpers routing through `GenerateBlocks_Meta_Handler::get_meta()`. ACF reads now happen via GB Pro's `generateblocks_get_meta_pre_value` filter — no inline `get_field()` calls in helpers. Loop-row context detection cached on `$instance->context['bws/loopItemPostId']` (Mode 2a: row resolves to post → read post meta; Mode 2b: flat repeater row → read `$loop_item[$key]` directly). `DISALLOWED_KEYS` security guard mirrors GB native posture; protected meta allowed on frontend (matches `Meta_Handler::get_meta()` behavior, supports plugins like Pie Calendar that store data in `_`-prefixed keys).
- `bws_get_loop_row_context( $instance ): array` in `content-helpers.php`: single source of truth for GB Pro loop-row detection. Returns `['loop_item' => mixed, 'row_post_id' => int|false, 'in_loop' => bool]`. Caches `bws/loopItemPostId` on `$instance->context` so per-block detection runs once. Consolidates 5 prior inlined detection blocks (see Changed).

### Added — Plugin metadata
- Plugin header `Requires Plugins: generateblocks-pro` declares GB Pro as a hard dependency. WP 6.5+ enforces this in `/wp-admin/plugins.php` (cross-references both directions, prevents deactivation while dependent active). Runtime check for `class_exists( 'GenerateBlocks_Meta_Handler' )` enforces GB 2.0+ minimum (since core `Requires Plugins` syntax does not support version constraints).
- Plugin header `Requires at least` bumped from 6.0 to 6.5 (matches `Requires Plugins` minimum).

### Changed — Option key renames
- `via`/`from` option renamed to `src`; `from` (field selector) renamed to `use` across all base tags and modifier callbacks
- Datetime option keys renamed to camelCase names: `time_sep` → `timeSep`, `range_sep` → `rangeSep`, `show_current_year` → `showCurrentYear`, `show_midnight` → `showMidnight`, `key2` → `timeKey` (single), `key`/`key2`/`end`/`end2` → `startKey`/`startTimeKey`/`endKey`/`endTimeKey` (range); mapper functions and migration rename targets updated accordingly
- `taxonomy` option key renamed to `tax` in post-context term-extraction templates (`bws_post_term_extraction_options`, `bws_post_term_image_options`, `PostTermRelatedPost::get_source_options()`); readers accept both `tax` and `taxonomy` for backward compatibility
- Canonical-token refactor for `src` and `use` options across base + modifier tags: source files now declare semantic tokens (`current`, `key`, `content`) as first option values; `bws_strip_default_select_values()` (in `content-helpers.php`) flips first option's value to `''` at registration boundary so wire format stays clean (GB drops empty values). Read sites apply `?? '<canonical>'` defaults: `src` → `'current'`, text/image `use` → `'key'`, content `use` → `'content'`. Content `use` reordered per matrix (content, key, excerpt). Required for try_ slot 2+ "Same as Previous" semantic to disambiguate "inherit" from "explicitly default". Wire format unchanged — existing saved tags continue working.

### Changed — Try-tag overhaul
- Try-tag use-mode dispatch wrappers added (`bws_try_text_post_dispatch`, `bws_try_text_term_dispatch`, `bws_try_content_post_dispatch`, `bws_try_content_term_dispatch`, `bws_try_image_post_dispatch` in `base-tags.php`); template `try_core_fn` / `try_term_fn` now point to these so each slot routes by its resolved `use` value (e.g. slot use=`title` → `bws_post_title_core`, slot use=`featured` → `bws_featured_image_core`). Previous direct pointers to the custom-field core functions ignored `use`, causing all non-key modes to fail.
- Try tag generator (`generate_base_try_tags()`) overhauled: per-slot `use` selector added for `try_text` and `try_content` (in addition to `try_image`); slot 2+ src + use dropdowns prepend "Same as Previous" inherit row (`same` value, stripped to `''` per `_strip_default` semantics); slot ≥2 raw `''` = inherit prior carry-forward, explicit `current`/`key`/etc. tokens flow through as explicit overrides. Slot N labels: `Source N`, `Relationship Field N`, `Field Key N`, `[Text/Image/Content] Field N` (suffix); `Source N: Get from taxonomy term?`, `Source N: Taxonomy` (prefix for `srcTermIn` term-hop control). `srcTerm` (boolean) + `tax` (slug) per-slot pair replaced by `srcTermIn` combined `bws-term-hop` control (matches base tag pattern post-v1.6.0). Slot ≥2 key field hidden when use is `same` (inherits both `use` and `key` from prior slot); shown only when user explicitly picks a key-needing `use` value (override mode).
- `text` + `content` modifier templates: `use` option added (text: `key`, `title`; content: `content`, `key`, `excerpt`); `try_per_slot_use` + `try_use_no_key_values` flags set so try_ slot 2+ slots can independently choose field type.
- `try_image`: per-slot `use` added (`try_per_slot_use`); `psk` key-check skips `use:featured` slots via `try_use_no_key_values`

### Changed — Label / source-option unification
- Base + modifier tag labels updated to matrix-prescribed forms: `src` → `Source`, `ref` → `Relationship Field`, `use` → `Text Field`/`Content Field`/`Image Field` (was verbose `Get text from:`/etc.).
- Source-option labels unified across base, modifier, and try_ tags: `src:current` → "Current", `src:ref` → "In Reference/Relational Field". `register_modifier()` reuses `bws_base_source_option()` and `bws_base_traversal_options()` directly so labels stay synchronized with base tags. Drops the prior modifier-specific labels ("Current (no traversal)", dynamic source-label for ref) and try_ slot labels ("Current Post", "Related Post (ref field)").
- Image `as:title` option label changed from "Title" to "Image Title" to disambiguate from text/content `use:title` ("Title/Name") in the same UI surface.
- `term_*` modifier tag titles now suffixed "(term-based)" (e.g. "Image (term-based)") matching the `view_*` "(View-based)" pattern; `register_modifier()` `modifier_label` parameter set on the term modifier registration.
- `SecondRelatedPost` label: "Post → 2nd Rel. Post"; `PostTermRelatedPost` label: "Post → Term → Rel. Post"

### Changed — Editor preview labels
- `bws_build_preview_label()` shape redesigned: literal user-supplied identifiers (meta keys, ref names) now wrapped in straight single quotes (`'X'`); display values (fallback strings, formatted datetimes) keep curly double quotes (`“X”`); fallback append moved from `· fallback: …` to `(fallback: …)`. Field-part shape: `text` uses bare key (`['body_text']`), `content` uses key + type-noun (`['body_text' Content]`), `image` uses key + type-noun + as-suffix (`['hero' Image Alt Text]`). Ref segment renders as `Ref 'rel_post'` (was `Ref (rel_post)`). Marker conventions documented in `docs/tag-reference.md` §Editor preview label schema.
- Base tag callbacks (`text`, `content`, `title`, `image`, `datetime_single`, `datetime_range`) and `term_` modifier callbacks: return `bws_build_preview_label()` in editor preview context instead of static REST placeholders (`[Custom Field]`, `[Title]`, etc.)

### Changed — Registry refactor
- `DeprecatedTagRegistry` refactored as thin 4-method facade over `MigrationRegistry`; external callers (e.g. `bws-portal-system`) unchanged; `transform_options()` delegates to `MigrationRegistry::transform_tag()`
- `required_options` field on `MigrationRegistry` entries: array of post-rename option keys whose presence is required for the migrated tag to reproduce the deprecated tag's default behavior. Display-only metadata for the admin migration preview — does not affect transform pipeline. Rendered by `SettingsPage::format_migration_target()` as `<key>:…` placeholder segments alongside `src:<inject>` and `fixed_options`. Populated with `srcTermIn` on all 15 term-extraction deprecated tag entries (`post_term_*`, `related_post_term_*`, `term_related_post_term_*` families) so the term-hop key shows in migration previews where it's required for the same output as the deprecated tag.
- Eight pre-NxM hand-written deprecated wrappers (`current_post_featured_image`, `current_post_meta_image`, `related_post_meta_image`, `related_post_url`, `post_acf_date_time_single`, `post_acf_date_time_range`, `term_name`, `term_field_image`) flipped from their original GB tag types (`'media'`/`'post'`/`'related'`/`'term'`) to `'deprecated'`, matching the type used by NxM `MigrationRegistry`-driven wrappers. Aligns editor grouping for all deprecated entries.

### Changed — Admin UI redesign
- Admin deprecated tags settings redesigned: per-tag enable/disable replaced by two group-level radio sets — **Has migration path** and **No migration path** — each with three modes: Keep / Suppress / Disable; tag membership stored per-tag, toggled by group; collapsible `<details>` reference lists show tags in each group
- Migration Tool moved to a separate section outside the settings `<form>`; replaces per-tag List Posts / Convert buttons with a unified post-level scan and migrate workflow
- Admin settings page reorganized: Migration Tool moved into the main settings form between Deprecated Tags and Diagnostics so the deprecated-tags reference, deprecated-options reference, and Migration Tool now sit adjacent (issue #4). New "Deprecated Options" section lists `type:'option'` migrations grouped by transform signature so each unique rename appears once with an "Applies to:" tag list rather than repeating per match_tag (issue #3).
- Deprecated tag list rendering: per-row migration target now reconstructed via new `SettingsPage::format_migration_target()` helper (Approach A) — shows `{{<new_tag>[ src:<inject>][|<fixed_options>][|…]}}` with the ellipsis serialized as a final pipe segment inside the braces to indicate user options carry over via `option_renames` / `value_renames` / `combine_options` / `datetime_transforms`. Old tag wrapped as `{{<old_tag>}}` for symmetry (issue #2).
- Deprecated option rows render structured rename description (`<old_keys>` → `<new_keys>` *(reason)*) plus "Applies to:" tag list. Old/new keys derived from `option_renames` + `combine_options`; reason extracted from the trailing parenthetical of the entry's `label`. Tag preview line dropped for option rows (not informative when grouped). New `SettingsPage::group_option_entries_by_transform()` collapses duplicates by signature (`option_renames` + `value_renames` + `combine_options` + `source_inject` + `fixed_options` + `match_options`).

### Changed — Image consolidation
- `image` base tag type changed from `'media'` to `'cross-source'`; `supports:['image-size']` removed in favor of explicit PHP options
- `term_image` modifier: `use:featured` gated behind `src:ref` (term entities have no featured image)
- Image template option definitions consolidated to single source of truth: `register_modifier()` no longer rebuilds `as`/`use`/`key`/`fallback` for image tags — modifier tags now consume the same template descriptor `options` array as `try_image`. Drift between `image`, `term_image`, `view_image`, `try_image` field labels eliminated. `key` option added to image template descriptor `options` (was previously only declared in modifier rebuild).
- Image tags now use GenerateBlocks' native `image-size` support instead of custom `bws-img-size` ComboboxControl. The custom control couldn't recognize stored `size:` values because GB's `DynamicTagSelect` destructures the reserved `size` key from `extraTagParams` before custom controls receive it. Native control handles `size:` parsing/serialization correctly and strips the `'full'` default automatically. Affects base `image`, `term_image`, modifier image tags (e.g. `view_image`), and `try_image`. Per-tag `$tag_supports` now built from a copy of `$base_supports` to avoid mutation across template iterations (prevented `image-size` support leaking to non-image tags like `view_datetime_*`).
- Modifier callback (`make_modifier_callback`) now dispatches image template by `use` option on post-context paths: `use:featured` → `bws_featured_image_core`, otherwise → `post_fn` (`bws_custom_image_core`). Previously the post-context path always called `bws_custom_image_core` regardless of `use`, so `view_image use:featured` (and any post-context modifier image with `use:featured`) returned empty.
- `bws_get_meta_image_data()` (image-helpers.php) now performs a two-pass meta read: pass 1 with `single_only=true` (returns scalar for ACF URL/ID return formats), pass 2 with `single_only=false` only when pass 1 yields nothing (returns array for ACF Image Array return format). Works around a `GenerateBlocks_Meta_Handler::get_value()` behavior where `single_only=false` returns the fallback (`''`) for plain scalars when an upstream filter (e.g. ACF `generateblocks_get_meta_pre_value`) populates the value, causing URL/ID-format ACF image fields to return empty. Provider-agnostic — any meta provider hooking the GB filter benefits.

### Changed — Field-extraction consolidation
- All 6 inline `get_field()/get_post_meta()/get_term_meta()` field-extraction call sites consolidated through `bws_read_field()` / `bws_read_term_field()`: `bws_get_meta_image_data()` (image-helpers), `bws_get_term_field_image_data()` (taxonomy-helpers, `$taxonomy` param dropped), `bws_post_custom_text_core()` and `bws_post_content_core()` custom_field branch (content-tags), `bws_term_custom_text_core()` (taxonomy-tags), and `bws_get_acf_field_value()` (datetime-helpers) — the latter retained as a thin shim that routes ACF term object_id syntax (`"{taxonomy}_{term_id}"`) to `bws_read_term_field()` and post IDs to `bws_read_field()`.
- `bws_parse_combined_date_time()`, `bws_get_acf_field_value()`, `bws_get_meta_image_data()`: `$instance` parameter threaded through so loop-row context detection works for datetime + image tags.
- `bws_post_custom_text_core()`, `bws_post_content_core()` (custom_field branch), `bws_get_meta_image_data()`: short-circuit on `! $post_id` relaxed when block instance is in a loop-row context (`generateblocks/loopItem` set), allowing field reads against the row entity.
- `bws_resolve_post_by_source()`: now Mode 2 aware. Mode 2a (loop row resolves to post): `src:''` returns row post ID, `src:ref` reads `ref` meta from row post. Mode 2b (flat repeater row): `src:''` returns `false` so callback can fall through to row data; `src:ref` reads `$loop_item[$ref]` directly. ACF Relationship/post_object subfields returning a list (no `ID` key) auto-unwrap to the first entry.
- `try_*` slot dispatch in `TagTemplateRegistry::generate_base_try_tags()`: Mode 2b (flat repeater row) skip-on-`! $post_id` was too aggressive — `bws_resolve_post_by_source()` correctly returns `false` for `src:''` in Mode 2b, but the slot's core function can still resolve via `$loop_item[$key]`. Now allows fallthrough when `$in_loop_row && '' === $last_src && ! empty( $last_key )`, so `try_text`, `try_content`, etc. can read flat-repeater row keys directly across slots.
- `bws_extract_post_id()`: handles list-of-posts return formats (Relationship/post_object subfield with no max_size limit). When passed an array without an `'ID'` key, takes the first entry and recurses. Lets `bws_resolve_post_by_source()` Mode 2 paths drop their inline list-unwrap workaround.
- `TermRelatedPost::resolve_id()` (`class-term-related-post.php`): inline `get_field( $rel, 'term_'.$term_id )` replaced with `bws_read_term_field( $rel, $term_id, false )`. Routes through `Meta_Handler` for ACF integration via filter; consistent with rest of field-extraction pipeline. Falls back to raw `get_field()` if helpers unavailable.
- `bws_get_loop_row_context( $instance )` extracted as single source of truth — replaces 5 inlined detection blocks across `bws_read_field()`, `bws_resolve_post_by_source()`, `bws_get_meta_image_data()`, `bws_post_content_core()`, `bws_post_custom_text_core()`, and `bws_custom_image_core()`.

### Changed — show_if extension / source cleanup
- `show_if` / `show_if_any` support added to `editor-conditional-options.js` (OR conditions)
- `SourceInterface` and `AbstractSource` cleanup: removed related-variant methods post Pattern B
- `get_traversal_options()` removed from `SourceInterface`, `AbstractSource`, and all source classes; `register_modifier()` now hardcodes standardized `ref` traversal sub-option (Q8 resolution)
- `date-helpers.php` renamed to `datetime-helpers.php`; `date-tags.php` deleted (content merged into `datetime-tags.php` in v1.6.0)

### Removed
- `bws_get_acf_field_value()` from `datetime-helpers.php`: thin shim retained through Phase 2 of the field-extraction consolidation. Replaced by inline `bws_read_field()` / `bws_read_term_field()` calls in `bws_parse_combined_date_time()` with ACF term object_id (`"{taxonomy}_{term_id}"`) detection inlined.
- `generate_all_tags()` and `generate_try_tags()` from `TagTemplateRegistry` — N×M loop eliminated; deprecated wrappers now active for all old per-source tag names
- `register_template()`, `get_templates()`, `make_direct_callback()`, `make_entities_callback()`, `compute_tag_default()` from `TagTemplateRegistry` (N×M support methods)
- N×M template registration functions from tag files: `bws_register_post_content_tag_templates()`, `bws_register_image_tag_templates()`, `bws_register_date_tag_templates()`, `bws_register_datetime_tag_templates()`, `bws_register_taxonomy_term_extraction_templates()`
- `$templates` static property from `TagTemplateRegistry`
- `bws_extract_text_field()`, `bws_extract_url_field()`, `bws_get_link_url()` from `content-helpers.php` (dead code — no callers in active files)
- `TagConverter::list()` and `TagConverter::convert()` — replaced by unified `scan()` + `migrate_post()` + paginated batch AJAX
- Per-tag List Posts / Convert buttons in admin deprecated section — replaced by Migration Tool
- "Enable benchmark admin page" diagnostics toggle, `is_benchmark_page_enabled()` accessor, sanitizer entry, and activation-seed key — dead UI; benchmark page never wired up. Stale `benchmark_page` key in saved options is harmless and ignored.

### Added — Activation defaults
- `register_activation_hook` (`bws_dynamic_tags_activate()`) seeds default settings on fresh activation when no option row exists. Deprecated tag groups (`mode_with_path`, `mode_without_path`) default to `'disable'` so legacy N×M tags are removed from GB out of the box on new installs. Existing installs (option row present) are left untouched.

### Changed — Admin UI polish
- Deprecated Options reference list collapses by default (matches Deprecated Tags list); `<details open>` → `<details>` in `SettingsPage::render_page()`.

### Documentation
- `docs/gb-constraints.md` (promoted from memory): GB editor/runtime constraints catalog (tag prefix rule, custom tag types, supports keys, reserved option keys, custom controls registered) moved from local memory into the tracked project docs. Bidirectionally cross-linked with `docs/deprecated-tags-options.md` so future renames driven by GB constraints have a documented justification path.
- `docs/deprecated-tags-options.md`: new **Superseded** status added to the option rename tracker legend. `via`/`from` → `source` rename marked **Superseded** (GB-reserved key); replacement row `via`/`from` → `src` added as **Implemented**. `via:tax` → `srcTerm` boolean marked **Superseded** (cross-source base tags drop reserved `tax` on modal reopen); replacement row `srcTerm` + `tax` pair → `srcTermIn` slug added as **Implemented**. Cross-link to `gb-constraints.md` added near the top.
- `docs/deprecated-tags-options.md` (new): migration reference containing all deprecated N×M tag name tables, template key renaming tracker, and option name renaming tracker; moved from `docs/tag-reference.md`
- `docs/tag-reference.md`: removed N×M matrix tables and rename trackers; replaced with forward references to `docs/deprecated-tags-options.md`; default-enabled logic section updated for v1.6.0 modifier group + deprecated wrapper toggles
- `docs/plugin-integration.md`: new §2 (Registering a Context Modifier with `register_modifier()` example and parameter reference); new §8 (Renaming a Modifier Prefix — converter-based migration pattern); §5 helper table corrected; §6 admin settings rewritten for v1.6.0; §7 deprecated wrapper parameter table updated (removed `source_key`/`is_related`, added all new fields)
- `CLAUDE.md`: simplified to dependency + development summary; defers to `README.md` and `docs/tag-reference.md`
- `README.md`: expanded from one-liner to proper overview with requirements and architecture pointer
- `docs/post-content-processing-reference.md`: rewritten against current implementation. Removed stale three-tier processing-mode documentation (Basic/Limited/Full), Query Monitor auto-downgrade, `processing_level` tag option, shortcode-toggle, and self-reference recursion check — none survive in plugin-era code. Documented current pipeline: single `bws_process_post_content()` entry, automatic `bws_process_post_content_fallback()` on low-memory, `bws_extract_and_queue_inline_styles()` + `bws_queue_inline_css()` / `bws_output_queued_inline_css()` deferral of cross-post GB-inlined `<style>` elements to `wp_footer`, `bws_safe_content_output()` strip of destructive GB options (`trunc`/`case`/`wpautop`/`link`). Standalone-era version log preserved at the bottom under a "Pre-Plugin-Integration History" header.
- Docs ownership split between `gb-constraints.md` and `tag-reference.md` clarified: `gb-constraints.md` now contains only GB-imposed behaviors (default serialization, boolean shape, `parse_options()` semantics, reserved keys, tag prefix rule, supports keys). Our plugin's response to those constraints (registration-boundary default-strip mechanism `bws_strip_default_select_values()`, canonical-token first values, `image`/`term_image`/`try_image` `as:url` always-serialized opt-out) consolidated into a new `tag-reference.md` §Default serialization strategy section. Removed duplicate `as` exception paragraph from §Base tag GB types and §Option render order — both now defer to the strategy section. Custom editor control registry (`bws-media-picker`, `bws-term-hop`) moved from `gb-constraints.md` into new `tag-reference.md` §Custom editor controls registered section. `gb-constraints.md` `image-size` reserved-supports advice flipped from "use a prefixed name" to "use GB's native control" (matches v1.6.0 retirement of `bws-img-size`). `gb-constraints.md` `media` type entry updated from "planned for removal" to past-tense statement of v1.6.0 behavior.
- `docs/tag-reference.md` simplification: Notation table (✅, —, GB, ★, ☐) and GB built-in collision-check paragraph moved to `docs/deprecated-tags-options.md` where the symbols are actually used; outdated "approved names" caveat removed (option names are implemented in v1.6.0); duplicate "Potential future traversals" section dropped (statuses already in §`src` option values table); plugin-specific external-modifier subsection removed and external-prefix rows in §Modifier prefixes and §Source classes neutralized to generic external-plugin descriptors; "(planned architecture)" qualifier dropped from §Base tag GB types heading.
- `docs/plugin-integration.md`: example identifiers neutralized — all example prefixes and class/function names in §2, §7, §8 walkthroughs renamed so the doc reads as generic guidance rather than referencing any specific third-party plugin.
- `README.md`: overview table added (one row per base tag — `text`, `image`, `content`, `datetime_single`, `datetime_range`, `title`, `permalink`) describing each tag's user-facing purpose. Footnote flags term-context behavior for tags marked with `*` as not yet tested without `term_` prefix. Note added about custom field names being supplied manually (no dropdown selector yet). `content` tag description revised to describe block-CSS-for-embedded-post-content consolidation into the page footer rather than fallback-pipeline specifics.
- `CLAUDE.md`: documentation ownership policy added — content-type-to-doc ownership table, update triggers per change type, cross-link rules. Single source of truth: each content type has one owner doc; other docs link rather than duplicate. `docs/tag-reference.md` opening paragraph + §Updating this document forward-reference the policy. `MEMORY.md` trimmed to one-line pointers per the cross-link rule (removed inlined option-key lists, GB-type assignments, architecture-shift narrative — all derivable from the docs they point at).

### Fixed — `source` → `src` GB-destructure rename (cross-cutting)
- `bws_base_source_option()`: option key renamed `source` → `src`; labels corrected to "Current" and "In Reference/Relational Field". GB's `DynamicTagSelect` unconditionally destructures `'source'` from parsed tag params before spreading into `extraTagParams`, so any PHP option named `source` is silently eaten — the editor control never receives the value and the option is dropped on save. `src` avoids the conflict. PHP callbacks read `src ?? source` for backward compatibility. C7 `type:'option'` migration entries registered for all 7 base tags to rename `source` → `src` in saved content. `source_inject` in `MigrationRegistry` updated to emit `src` key.
- `bws_base_traversal_options()`: `show_if` key updated `source` → `src` to match renamed option
- `TagTemplateRegistry::register_modifier()`: option key `source` renamed to `src` (and `show_if` references updated). The earlier `source`→`src` rename in v1.6.0 was applied to base tags but missed `register_modifier()`, so all generated modifier tags (e.g. `term_*`, `views_*`) had their source dropdown silently eaten by GB's `DynamicTagSelect` destructure — users could not pick the "ref" traversal option in any modifier tag.
- `generate_base_try_tags()`: slot 1 option keys were `source`/`use`/`1-ref`/`1-srcTerm`/`1-tax`/`1-key`; same GB destructure bug caused `source` to be eaten, and `1-` prefix on remaining slot-1 keys diverged from spec. All slot-1 keys now un-prefixed: `src`, `ref`, `srcTerm`, `tax`, `use`, `key`. Slots 2–5 unchanged (`N-src`, `N-ref`, etc.). `$src_opts` merges in callback updated to pass `src` key. Slot trigger `prev_any` refs corrected for when `$prev = 1`.
- `TagTemplateRegistry::make_modifier_callback()`: unset-`src` branch hardcoded `term_fn` dispatch, which assumed the modifier prefix entity was always a term. Broke post-context modifiers (e.g. `views_*` from `bws-portal-system`): bare `{{views_content}}` resolved a post ID via `PortalSource` then called `bws_term_description_core` with that post ID. Now dispatches by base source's `get_context_type()` — `term` → `term_fn`, `post` → `post_fn`. `term_*` modifier behavior unchanged; `views_*` modifier tags now render correctly.

### Fixed — Loop-row resolution
- `bws_get_loop_row_context()`: `row_post_id` resolution was gated on `generateblocks/queryType === 'post_meta'`, so standard `WP_Query` post loops left `row_post_id = false` while `in_loop = true`. `bws_resolve_post_by_source()` for `src:'current'` then hit its Mode 2b guard and returned `false`, breaking any base tag inside a regular query loop (e.g. `{{text key:foo|srcTermIn:bar}}` rendered empty). Now extracts a row post id whenever `loop_item` is non-array (`WP_Post` / numeric — covers standard query loops and post-meta relationship loops GB Pro materializes into `WP_Post` instances), or under `post_meta` queryType when the array carries an explicit `ID` key. Flat repeater rows (Mode 2b) still fall through correctly because `bws_extract_post_id()`'s list-of-posts fallback no longer runs on array `loop_item`s without `ID`.
- Loop-row context detection only matched `is_array( $loopItem )` rows, but GB Pro's post_meta loop hands rows as `WP_Post` objects (ACF Relationship field with return_format=object) or numeric IDs (return_format=id). All Mode 2 detection sites now accept `array | WP_Post | numeric` so `{{title}}`, `{{text key:...}}`, `{{datetime_*}}` tags inside relationship loops correctly resolve to row entities instead of falling back to the outer post.

### Fixed — Preview-label safety
- `bws_build_preview_label()`: replaced straight double quotes (`"..."`) around `$fallback` value and datetime `$formatted` value with curly quotes (`“...”`, U+201C/U+201D). Straight quotes broke `<img alt="...">` attribute when `image as:alt`/`as:caption` rendered preview labels containing user-controlled fallback strings; curly quotes are attribute-safe. Affects three call sites (warning branch, datetime branch, final-assembly branch). Doc examples in `tag-reference.md` updated to match.

### Fixed — Try-tag option ordering
- `try_image`, `try_datetime_single`, `try_datetime_range`: Group 1 formatting options (`as`, `size`, `format`, `timeSep`, `rangeSep`, `showCurrentYear`, `showMidnight`) were appended after per-slot options instead of preceding them; corrected via `leading_options` on modifier template descriptors
- `datetime_single`, `datetime_range` base tags: source block appeared before formatting options; reordered to formatting → source → field keys → fallback per spec

### Fixed — Image fallback
- `image`, `term_image`, `try_image` tags: `fallback` option (set by `bws-media-picker`) was ignored at runtime; core functions read `id` (legacy GB media-type key) instead of `fallback`; now read `fallback ?? id` with backward compat for pre-v1.6.0 saved tags
- `bws_term_custom_image_core`: read `fallback_url` instead of `fallback`; now reads `fallback ?? fallback_url`
- `bws_handle_media_fallback`: only accepted numeric attachment IDs; now also resolves attachment URL via `bws_get_attachment_id_from_url()` to support `bws-media-picker` output (stores URL, not ID)
- `bws_register_option_migrations()`: added `type:'option'` entries for `image`, `term_image`, `try_image` to rename `id → fallback` on tags saved in v1.5.x when those tags still used `type:'media'`
- `$fi_renames` / `$ci_renames` in `bws_register_v1_deprecated_tag_wrappers()`: `id → fallback` rename now included so deprecated-tag converter migrations carry the rename through to the target tag
- `ImageSizeControl` (`image-tag-controls.js`): `generateBlocksInfo.imageSizes` array items not normalized to `{ value, label }` objects; `ComboboxControl` crashed with `Cannot read properties of undefined (reading 'replace')` when items were strings or lacked a string `label` property

### Fixed — Migration entry corrections
- `DeprecatedTagRegistry::has_migration_path()` returned `true` for all entries; now checks `new_tag` non-empty
- Converter output for related-source tags: `rel` option key was not renamed to `ref` and `src:ref` was not prepended; caused tags like `{{text rel:field|key:val}}` instead of `{{text src:ref|ref:field|key:val}}`; fixed via `MigrationRegistry` `type:'option'` entries registered by `bws_register_option_migrations()`
- 22 deprecated tag registrations missing `new_tag` (and migration config) caused admin scanner to show them as having no auto-convert path despite approved migration specs: `post_term_description/custom_text/custom_image`, `related_post_term_description/custom_text/custom_image`, `term_related_post_term_description/custom_text/custom_image`, `term_custom_text/image/date_single/date_range/datetime_single/datetime_range`, `try_custom_text/featured_image/custom_image/date_single/date_range/datetime_single/datetime_range`; all now carry `new_tag`, `source_inject`, `option_renames`, `value_renames`, `fixed_options`, and `datetime_transforms` as appropriate
- `MigrationRegistry::run_transform()`: empty-string `new_key` in `option_renames` now drops the option (unsets without creating new key); enables `src_1 => ''` pattern used by `try_*` slot migrations to suppress the slot-1 source (which defaults to `post`)
- `bws_register_v1_deprecated_tag_wrappers()`: six `term_custom_*` migration entries had wrong `new_tag` and a spurious `source_inject:'term'` — `src:term` is not a valid src value; term modifier tags (`term_text`, `term_image`, `term_datetime_single`, etc.) are a separate GB tag family that do not accept a `src` option. Corrected: `term_custom_text` → `term_text`, `term_custom_image` → `term_image`, `term_custom_date_single/range` → `term_datetime_single/range`, `term_custom_datetime_single/range` → `term_datetime_single/range`; `source_inject` removed from all six.
- `bws_register_early_deprecated_tag_migrations()`: `term_name` migration entry had `new_tag:'title'` + `source_inject:'term'` (invalid); corrected to `new_tag:'term_title'` with no source inject. `term_field_image` had `new_tag:'image'` + `source_inject:'term'` (invalid); corrected to `new_tag:'term_image'` with no source inject.
- All 16 `term_related_post_*` deprecated entries (`deprecated-tags.php`): `new_tag` flipped from base post tags (`title`, `text`, `image`, etc.) to term-modifier equivalents (`term_title`, `term_text`, `term_image`, etc.). Convention: any tag starting with `term_` starts on a term; the modifier tag's `src:ref` traversal handles the term→post hop. Term-extraction subset (`term_related_post_term_*`) carries the second hop via existing `tax → srcTermIn` rename in `option_renames` (issue #1).
- Eight pre-v1.6 hand-written deprecated callbacks (`current_post_featured_image`, `current_post_meta_image`, `related_post_meta_image`, `related_post_url`, `post_acf_date_time_single`, `post_acf_date_time_range`, `term_name`, `term_field_image`) used hardcoded override strings in `bws_build_deprecation_preview_label()` instead of computing the replacement from actual options; override arg removed from all eight — preview labels now show the real migrated tag string. `bws_register_early_deprecated_tag_migrations()` added to register `MigrationRegistry` entries for all eight, enabling the admin converter and live preview labels.

### Fixed — Misc
- `DeprecatedTagRegistry` loop: undefined `$sk` variable
- Datetime converter: boolean injections use `'true'` string, not `'1'`
- Scanner falsely counted post revisions as separate posts; `scan()` now excludes `post_type = 'revision'` and `post_status IN ('auto-draft','trash')` at SQL level
- Datetime tags failed for non-ACF meta fields (e.g. Pie Calendar's `_piecal_start_date` / `_piecal_end_date`): inline `get_field()` only path returned null for non-ACF keys, and even with `get_post_meta()` fallback, GB Pro's filter never fired. Field-extraction consolidation via `bws_read_field()` routes through `GenerateBlocks_Meta_Handler` — both ACF and raw post-meta keys now resolve correctly.
- `bws_content_debug()` and `bws_content_debug_start()` (content-helpers.php) now gated solely by the admin "Enable benchmark logging" setting; previously also activated by `WP_DEBUG`, which bypassed the user-facing toggle. Per-request post content benchmark output (`[BWS Content] post_id=… time=… mem_delta=…`) now respects the setting in all environments.

### Deprecated (N×M → base-tag wrappers, Commit C1)
- 75 N×M source × template generated tag names deprecated with `DeprecatedTagRegistry` entries covering all post-context, term-context, and term-extraction combinations
- Three callback factories added (`bws_make_deprecated_post_callback`, `bws_make_deprecated_term_callback`, `bws_make_deprecated_term_extraction_callback`) for runtime resolution via `SourceRegistry`
- All migration-capable entries include `source_inject`, `option_renames`, `value_renames`, `fixed_options`, and `datetime_transforms` for converter use
- Pre-C2 dup-check in `bws_register_deprecated_tags()`: skips deprecated entries whose `old_tag` is still live in GB's registry (N×M active); wrappers activate automatically once C2 removes `generate_all_tags()`

---

## [1.5.0]

- Pattern B: RelatedPost and TermRelatedPost promoted to standalone source classes; removes related-variant mechanism (~240 lines)
- New: TermRelatedPost source (Term → Rel. Post) — term context, post resolution, enabled by default
- Add `needs_relationship_field()` and `get_ui_group()` to SourceInterface/AbstractSource
- Remove `has_related_variant()` and 5 related-variant methods from SourceInterface/AbstractSource (breaking change for external sources)
- SecondRelatedPost and PostTermRelatedPost: source toggle now enabled by default
- All traversal sources now exclude `link` support
- try_ tags: traversal moved into source `resolve_id()`; `$last_rel` carry-forward preserved
- Fix: inject relationship field option on traversal-source direct tags (RelatedPost, TermRelatedPost)
- Fix: disabled sources no longer appear as options in try_ slot source dropdowns

## [1.4.2]

- Fix datetime fallback: `bws_handle_date_time_fallback()` returns empty string when `fallback_text` is unset; previously returned hardcoded strings unconditionally

## [1.4.1]

- Remove GB source picker from `related_post_*` and `second_related_post_*` tags (traversal always from current post)
- Add `tag_default_enabled()` to SourceInterface/AbstractSource
- Fix `is_source_enabled()` to respect `source_default_enabled()` instead of hardcoding true
- Flip `second_related_post_` tags to enabled-by-default when source is on
- Add `post_term_related_post_` source: 3-hop traversal (current post → taxonomy term → term's related post)

## [1.4.0]

- Extend `content` template with Content Type option (post content/description or ACF/meta field)
- Add `try_content` tag with per-slot type selection
- Suppress `term_content` direct tag

## [1.3.3]

- Add conditional field visibility: `show_if` (AND) and `show_if_any` (OR) on PHP option definitions, evaluated by `assets/js/editor-conditional-options.js`
- Redesign try_* tags: 5 slots (was 3), source-first field order, progressive slot disclosure

## [1.3.2]

- Refactor: extract 5 named callback factory methods from TagTemplateRegistry
- Refactor: decouple `SettingsPage::is_tag_enabled()` from `_registered_tags` during tag generation
- Refactor: standardize `resolve_id()` on CurrentPost and RelatedPost sources

## [1.3.1]

- Fix: custom_text fallback not triggering when ACF returns empty string for blank registered field

## [1.3.0]

- Add fallback text option to custom_text template (post, term, and try_ variants)
- Add `get_excluded_supports()` to SourceInterface/AbstractSource

## [1.2.0]

- Refactored to source × template architecture
- Added external plugin API for registering additional tag sources
- Added deprecated tag registry for backwards compatibility

## [1.0.0]

- Initial release
