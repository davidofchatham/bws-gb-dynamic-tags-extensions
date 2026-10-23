# BWS GenerateBlocks Dynamic Tags Extensions

A [GenerateBlocks Pro](https://generatepress.com/blocks/) extension with advanced dynamic tags for both standard post/term fields and custom field data.

## Our tags

| Tag | Returns | Specific limitations | [Multi-attempt variant](#try_-tags-return-the-first-successful-sourcefield-attempt) |
|---|---|---|---|
| `text` | Simple meta/option text fields or post title/term name. | | `try_text` |
| `title` | Post titles, term names, user display names, archive labels, date spans, search and 404 headings, or the site name (for a blog homepage). | | `try_title` |
| `permalink` | Post/term permalink or site URL. | | `try_permalink` |
| [`content`](#content-output-long-form-content) | Post content, term description, user bio, post type description, or GeneratePress's 404 text, via a processing pipeline that handles block-rendered content safely, including consolidating block CSS for embedded post content into the page footer. | Since there's no site-wide body/content field, an option field name must be supplied to use this tag with the *Site* source. | `try_content` |
| `image` | An image from a meta field or the post featured image or site logo field, with return options like GB's (alt text, etc.) and a Media Library fallback image selector. | Since terms have no native image fields, a field name must be supplied to retrieve images from a term source. | `try_image` |
| `datetime_single` | Combined datetime fields, or separate date and time fields, formatted as a single date and time. By default, also hides midnight times and the current year. Multi-result sources can render a delimited date list. | | `try_datetime_single` |
| `datetime_range` | Like `datetime_single`, but formats a range from separate start and end fields. In a range list, the result separator joins whole ranges while the range separator stays between each start and end. | | `try_datetime_range` |
| `email` | An email address as a `mailto` link (by default) or plain text. Validates emails (by format) and returns empty if invalid. | | `try_email` |
| `phone` | A phone number as a `tel` link (by default) or plain text. Normalizes numbers and allows global country code configuration. | | `try_phone` |
| [`join`](#join-combine-multiple-text-fields) | Up to 10 text fields combined into one string, by separator or format template. | Plain text only; no link options. | — |
| [`table`](#table-build-a-dynamic-table) | A complete HTML table: one row per repeater row, related post or term, up to 10 columns. | | — |
| [`call`](#call-return-custom-function-output) | The output of a registered PHP function, passed the post ID. | Post sources only. | — |

## What's different (other than the tag names)?

### Use the same tags almost anywhere

Rather than being prefixed and locked to particular contexts, our tags work across post templates, query loop items (post, term, user, or repeater row), taxonomy term archives, and author archives. What each tag returns from each kind of source:

| Tag | Post | Term | User | Repeater row | Page with no post, term or user | Site | Alternative to (GP/GB) |
|---|---|---|---|---|---|---|---|
| `title` | Post title | Term name | Display name | — | That page's heading: the date, archive label, search or 404 heading, or site name | Site name | `post_title`, `archive_title`, `site_title` |
| `title` with a *Terms in Taxonomy* source path step | The post's term names, each linked when *Link To* is set | — | — | — | — | — | `term_list` |
| `content` | Post content, or the excerpt when *Post Excerpt* is selected | Term description | User bio | — | Post type description, or GeneratePress's 404 text; otherwise empty | — | GeneratePress Post Content block, `post_excerpt`, `archive_description` |
| `text`, `content`, `image` with a field key | Post field | Term field | User field | Row field | — | Option field | `post_meta`, `term_meta`, `loop_item`, `option` |
| `email`, `phone` with a field key | Post field | Term field | User field | Row field | — | Option field | Without linking and validation/normalization: `post_meta`, `term_meta`, `loop_item`, `option` |
| `image` with featured image | Featured image | — | — | — | — | Site logo | `featured_image`, `site_logo_url` |
| `datetime_single`, `datetime_range` | Post field | Term field | — | Row field | — | Option field | Without custom formatting: `post_meta`, `term_meta`, `loop_item`, `option` |
| `permalink` | Post URL | Term URL | — | — | — | Site URL | `post_permalink`, `site_url` |

**Post** is a single post or page, a post query loop item, a post you pick, or a *Posts in Reference/Relational Field* step. **Term** is a term archive, a term query loop item, a term you pick, or a *Terms in Taxonomy* step. **User** is the user an author archive or a user query loop item is about, never the logged-in visitor. **Repeater row** is a repeater query loop item or a *Rows in Repeater Field* step. The last page column is a date or post type archive, search results, a 404, or a blog homepage with no page assigned. **Site** is the *Site* source. The GP/GB alternatives each cover only some of a row's cells; `archive_title` and `archive_description` work only on the archive page itself.

`try_` variants, `join` slots and `table` columns follow the same rows, except that `try_datetime_` slots take no *Site* source. Measurement details are in [the tag reference](docs/tag-reference.md#coverage-by-ambient-context).

### Build source paths to access related data

A source path is where a tag starts, plus any steps it takes from there.

#### Starting points

| Start from | What it is | Notes |
|---|---|---|
| Current Context (default) | The current post, term, user, repeater row, or archive, search or 404 page | What each tag returns in each context is in the table above |
| Post | One post you pick | Searchable picker of every post you can see in the admin, grouped by post type |
| Term | One term you pick | Same picker, grouped by taxonomy |
| Site | Site-wide data: site name, URL, logo, option fields | Option fields must be on the allowed-options list |
| Added by another plugin or your theme | Whatever that source points to | See [Extending](#extending) |

#### Steps

| Step | Can follow | Gives you |
|---|---|---|
| Posts in Reference/Relational Field | A post, term, user, repeater row, or site options | The posts stored in that field |
| Terms in Taxonomy | A post | That post's terms in the taxonomy |
| Rows in Repeater Field | A post, term, user, repeater row, or site options | The field's rows |
| **[UNRELEASED]** Parent Post/Term | A post or term | Its parent post, or its parent term in the same taxonomy. A top-level post or term has none, so the tag shows its fallback |

Steps chain in any order the "Can follow" column allows, for example a relationship field's posts, then each post's categories. `title` and `permalink` can't read a repeater row, since a row has no title or address. `call` takes post sources only.

#### Limiting results

Each step that can return several results carries its own optional limit, meaning at most that many *from each* incoming result. For example, limiting a taxonomy step to 1 gives you one term from the current post or each previous step's posts, not one term overall. Pick an ACF Relationship or Post Object field for a source step and you get a short note about its current configuration, including bidirectionality and entry limits, to help you decide whether and how to configure that step's limit. Leave it blank for all results.

### Unlocked field selector

GB's field selector is post-type-based, so when you're building GP Elements or WP Patterns, you usually can't see the fields that are actually available for what you're working on. Using our tags, every meta/option field key input shows all registered fields (including ACF fields and sub-fields, options-page fields, term fields, and post meta fields), and can be filtered by context, field group, and field type, or searched by label, name, and type.

When a tag's source is one specific term or post, the list goes the other way and narrows to that taxonomy's or post type's own fields, plus any field whose group isn't tied to one location. Select a different one and it re-narrows on the spot. Sources that don't name a specific entity still show everything, which is the only honest answer while the entity isn't known until the page renders, and you can always type a key in by hand either way. A step through a relationship or post object field narrows the next list to post fields from the post types that field allows, so a field limited to Staff offers Staff fields rather than every post type's. A field that allows any post type, or one we don't know about, keeps every post type.

### Static content from a dynamic tag?

Yes! Why?

- Type in an email address or phone number and get a validated `mailto:` or normalized `tel:` link, with no field to read from. You could even save a site-wide address or phone number as a WP Pattern if you don't want to use a site option field.
- Add a fixed text string along with field values in a `join` tag.
- Use the same text for every row of a particular `table` tag column.

Available in `text`/`try_text`, `email`/`try_email`, `phone`/`try_phone`, `join` and `table` via the *Fixed* option (*Fixed Text*, *Fixed Email* or *Fixed Phone Number*). It shows once for each source the tag reaches, so a list of posts repeats it and a limit of 1 gives a single result, and it still needs a source to render against: if that source finds nothing, the tag shows its fallback as usual, and a `join` slot or `try_` attempt drops out.

### Special handling

#### Zero values

When "Required to render" is checked for a tag (the default), real values of `0` are treated as an empty result, and the whole block is suppressed. When this extension is enabled, zeroes are padded with a space, returning '0 '. GB doesn't treat *that* as an empty result, so the block renders and you see **0**.

*The zero padding isn't limited to our own tags:* It applies to GenerateBlocks and GenerateBlocks Pro tags, as well as any other extension's tags. For example, GB Pro's `loop_index` returns a bare `0` on the first row of a zero-based loop; without this extension, it's suppressed unless you uncheck "Required to render." However, even with this installed, GB's `post_meta` returns empty for a field holding `0`, so neither unchecking "Required" nor our zero padding has any effect; use our `text` tag instead.

Note that if you're injecting a value which may be `0` into a URL or attribute, or using a value directly followed by punctuation, the added space could cause problems. For the latter case, try our `join` tag.

#### Empty alt text

If you use GB's `{{featured_image key:alt|…}}` for alt text, an image that exists but doesn't have stored alt text will result in the *entire* `img` tag being suppressed, unless "Required to render" is unchecked. For our `{{image as:alt|…}}` dynamic tag, we inject a space so it doesn't fail GB's empty-result check even if you forget to uncheck the "Required" toggle. (It should still parse as empty alt text for screen readers.)

## Tag details

### `content`: Output long-form content

One portable tag for long-form content in any context. GeneratePress's Post Content block is the only native way to output full post content, and GenerateBlocks' `archive_description` covers term, post type and author descriptions but is empty outside an archive. `content` returns whichever fits where it renders: post content, term description, user bio, post type description, or GeneratePress's "not found" text on a 404.

- **Post content renders safely inside a tag.** Blocks render as they would on the post itself, their CSS is gathered into the page footer, and a post that ends up showing itself is cut off instead of looping.
- **Excerpt** returns the post excerpt instead, and **a field key** returns any long-form field, from a related post or a repeater row as much as from the page itself.
- With a *Site* source, a field key is required, since a site has no body content of its own.

Output is one result, never a list, and has no link options because content may already contain links.

### `join`: Combine multiple text fields

Instead of stringing together multiple tags in separate `span` elements, use one tag to combine up to 10 text fields in either simple separator or advanced template mode:

- **Separator mode** joins every non-empty value with a separator string (default `", "`), skipping empties so a missing middle value never leaves a doubled separator.
- **Template mode** places values by position in a format string, using tokens `%A`-`%J` to represent the configured slots (the same letters the slot panels are labeled with). The older `%1`-`%10` tokens still work. Punctuation attached to an empty value drops with it: an empty bracketed part sheds its brackets, an empty middle part its comma, a missing unit value its mark. One format string can return both *Dr. Tom M. Smith Jr., PhD, USN (Ret.)* and *Jane Johnson*.

Output is plain text; no link options are currently available.

**Note:** For feet and inches, use the prime marks `′` and `″` rather than straight quotes (`%A′%B″` renders **5′11″**, or **5′** with no inches value). WordPress converts a straight `'`/`"` to a curly quote in normal page content, but not when the block renders through a GP Element or a hooked layout, so straight marks render inconsistently depending on where the block lives. The prime marks look the same everywhere.

### `table`: Build a dynamic table

GenerateBlocks' query loop can build a list but not a table. The `table` tag returns a complete HTML table built from fields pulled from repeater rows, related posts, taxonomy terms, etc.

- **Rows** come from the tag's source. If *Source* is *Current Context*, the table will have one row for the current post, term or user. Point it at a repeater field and you get one row per repeater row; at a relationship field, one row per related post; at a taxonomy, one row per term. The source's limit caps the rows.
- **Columns** each read one field from their row, the same way a `join` slot does: a field by name, a title, or text you type. You can also add a source path step to get the title of a post stored in a relationship field within the repeater row, for example.
- **Headers** are set per column. A table with no column headers renders without a header row. Picking a field offers its field label as the header, one click to apply.
- **Caption** is optional and labels the table for screen readers. A captioned table also scrolls sideways on its own when it is wider than the page, instead of overflowing it.
- **Styling** covers table layout and width, column widths, text color, font size, weight and style, alignment and letter case (for the whole table, the header row, or one column), row or column stripes, and borders with rounded corners. A live preview in the tag's settings shows the result as you go. The default stripe color and opacity are set on the plugin's settings page.

Styles are written to one small stylesheet per distinct table design, never as inline styles, so they pass WordPress's content filtering and identical tables share one stylesheet.

### `try_` tags: Return the first successful source/field attempt

`try_*` tags (e.g. `try_text`, `try_image`, `try_content`, `try_datetime_single`, `try_email`, `try_phone`) allow using the first available (populated) field from an editor-selected list of up to five sources/fields. Each slot resolves exactly as the standalone tag would (`try_email` outputs a finished `mailto:` link per slot, `try_phone` a `tel:` link), so a contact chain like "personal email → team email → site-wide address" works without multiple blocks and complicated visibility conditions.

Other than `try_datetime_`, the `try_` tags accept a site source per slot, so a chain can end in a site-wide fallback value.

### `call`: Return custom function output

The `call` tag hands off a post ID to a PHP function and returns its output. I've grouped it with GB's Post tags since it's strictly post-based, unlike the other tags. However, it still allows using a post related to the current context via a reference/relational field, and it can also pass correct post IDs when used within a Post Meta Query Loop on a reference/relational field.

Custom functions must take a post ID as their first argument (or you may get unexpected results), sanitize their own output (HTML is allowed in the return string), and be registered via `bws_register_call_function()`:

```php
add_action( 'init', function () {
    bws_register_call_function( 'my_result' );
} );

function my_result( $post_id, $arg = '' ) {
    return '<span>' . esc_html( get_the_title( $post_id ) ) . '</span>';
}
```

Properly registered functions will appear in the tag's **Function** dropdown for easy access. The optional **Argument** field allows passing a second parameter in addition to the post ID.

A security gate blocks adding PHP built-ins (`system`, `unlink`, `eval`, and the like) or anything that isn't a real function. All registered functions are shown, along with their security-gate status, on the admin settings page. Manually inserting an unregistered or blocked function will cause the tag to return its fallback text or return empty.

## Extending

Another plugin can add its own starting point to our source path options, and that starting point can take an argument of its own, so it can name a specific entity rather than only something available from the context. See [`docs/plugin-integration.md` §1](docs/plugin-integration.md#1-offering-your-source-as-a-chain-root).

**[UNRELEASED]** A plugin that decides who may see a post, such as a membership or audience plugin, can hide that post from our tags too, and its own tags can apply the same visibility check ours do. It can only hide content, never reveal what the built-in check refuses. See [`docs/plugin-integration.md` §3](docs/plugin-integration.md#narrowing-who-may-see-an-entity).

## Requirements

- WordPress 6.5+
- GenerateBlocks Pro
- ACF or any plugin hooking `generateblocks_get_meta_pre_value` for meta/option-field reads (optional)

## Documentation

- [`CHANGELOG.md`](CHANGELOG.md) — version history
- [`docs/tag-reference.md`](docs/tag-reference.md) — current tag architecture, options, render order
- [`docs/editor-tag-previews.md`](docs/editor-tag-previews.md) — editor-time tag configuration preview text
- [`CONTEXT.md`](CONTEXT.md) — cross-cutting design invariants (source-analog model, `use`-dispatch, qualifying gate)
- [`docs/deprecated-tags-options.md`](docs/deprecated-tags-options.md) — N×M historical reference + migration tracker
- [`docs/plugin-integration.md`](docs/plugin-integration.md) — external plugin API (chain roots, source classes, migration entries)
- [`docs/gb-constraints.md`](docs/gb-constraints.md) — GB editor/runtime constraint catalog

## Acknowledgements

Of course, this is completely dependent on the work of [Tom Usborne and the GeneratePress/GenerateBlocks team](https://generatepress.com/about/). But I'm also quite indebted to [Taylor Drayson](https://taylordrayson.com), whose SnippetClub tutorial [How to Create Custom Dynamic Tags in GenerateBlocks 2.0: A Complete Guide](https://snippetclub.com/how-to-create-custom-dynamic-tags-in-generateblocks-2-0-a-complete-guide/) started me down this path. Based on that, I began using Claude to generate my own tag code snippets, and it's grown from there through many, many versions into this plugin!

### Libraries

- In-WordPress update notices and one-click updates are powered by [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) by [Yahnis Elsts](https://github.com/YahnisElsts) (MIT-licensed), bundled at [`libs/plugin-update-checker/`](libs/plugin-update-checker/).
