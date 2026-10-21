# `{{table}}` test matrix (TB / TK / TP / TE)

**Standing manual regression suite** for `{{table}}`: the shape of the table it assembles (TB), the resolved kind each row can be (TK), what it draws (TP) and what the editor previews (TE). Covers what the pure harnesses structurally cannot: a real repeater, real terms, a real user loop and a real site, read through the real render path.

> **Re-run trigger:** any `{{table}}` row below that a change could move. The update-trigger rows in [`CLAUDE.md`](../../CLAUDE.md#update-triggers) say which: assembly and a11y (`bws_table_assemble`), row production and columns (`bws_table_callback`, `bws_table_columns`, `bws_table_read_cell`), the text-key value grammar, and the presentation controls.
>
> **Pure harnesses are the cheap gate, run them FIRST**, they own the algorithms: `table-assemble-test.php`, `table-rows-test.php`, `table-text-align-test.php`, `table-style-test.php`, `table-style-twin-test.php` (needs `node`), `table-style-control-test.js`, `table-style-preview-test.js`. Rows here assert only what needs real WP state or the editor.

**How to run:** every row is a visible block on `/matrix-table/` (blueprint **v31**, `bin/seed.sh testbed core-structures`), except TB0 (on `/matrix-post-meta/`) and the rows that say RENDER-TAG ONLY. Open the page to eyeball the front end, or open it in the editor for the rows whose label names an editor message. For a one-liner, from the wp-litespeed env:

```bash
bin/wp.sh testbed bws render-tag '{{TAG}}' --url=https://testbed.test/matrix-table/ --porcelain
```

Location-independent equivalent (works from Windows or WSL, since Docker Desktop shares one daemon):

```bash
docker exec wp-litespeed-litespeed-1 sh -c 'cd /var/www/vhosts/testbed/html && \
  wp bws render-tag "{{TAG}}" --url=https://testbed.test/matrix-table/ --porcelain --allow-root'
```

`--porcelain` prints the table HTML and none of the page's inline CSS: a styled table shows only its `bws-dte-table-<hash>` class there, and the rules are in the front-end page's footer `<style id="bws-dte-table-<hash>">`. The TP expectations below quote those rules, read off the page. A cached front end hides brand-new rows; cache-bust with a literal token (`?nocache=run1`), never `$RANDOM`.

Operating the testbed, and why every row must also be a visible block: [`docs/testbed.md`](../../docs/testbed.md).

## Fixture state these rows assume

| Entity | Reached by | Carries |
|---|---|---|
| page `matrix-post-meta` (`<MID>` below) | `src:post,<MID>` | the `team_members` repeater: **Alice Adams** / `Engineering` / `Founding partner` / `lead_ref` Jane Partner, **Bob Brown** / `Operations` / `Support lead` / no `lead_ref`; `related_staff` → **Jane Partner, Tom Associate** (that order); department terms **Sales, Support** |
| staff `jane-partner`, `tom-associate` | the `refs` tail | `main_line` `(555) 200-3000` and `(555) 200-4000` |
| terms `sales`, `support` | the `terms` tail | `phone` `(987) 333-4444` and `(987) 111-2222` |
| users `fixture-author`, `fixture-other-author` | a `WP_User_Query` loop over role `author` | display names **Fixture Author** and **Other Author**; only the first has a biography (`description`) |
| page `matrix-table` | the ambient context | title **Matrix: Table**; no repeater of its own |

The rows are written against `{{table}}`'s wire: a source chain, then `A:`/`B:`/… column slots. Output below is the table's cell text per row, header cells first.

## TB: shape

Headers, caption, chain cardinality and column edge cases, pinned to the `matrix-post-meta` page by id. TB0 is the one ambient control and lives on `matrix-post-meta` itself, whose page owns the repeater.

| # | Tag | Expected | Status |
|---|---|---|---|
| TB0 | `{{table src:rows,team_members\|A:header(Name);key(name)\|B:header(Role);key(role)}}` on `/matrix-post-meta/` | no root named, so the `rows` step runs off the PAGE: Name / Role, Alice Adams / Engineering, Bob Brown / Operations | **PASS (measured 2026-10-20)** |
| TB1 | `{{table src:post,<MID>;rows,team_members\|A:header(Name);key(name)\|B:header(Role);key(role)\|C:header(Note);key(description)}}` | 2 body rows: Alice Adams / Engineering / Founding partner, Bob Brown / Operations / Support lead; plain `<table>`, no class, no wrapper | **PASS (measured 2026-10-20)** |
| TB2 | `…\|A:key(name)\|B:key(role)` | no headers: no `<thead>`, two body rows | **PASS (measured 2026-10-20)** |
| TB3 | `…\|A:header(Name);key(name)\|B:header(Lead);src(inherit;refs,lead_ref);use(title)` | the Lead cell is Jane Partner on row 1 and EMPTY on row 2 (no `lead_ref`): an empty cell, not a crash | **PASS (measured 2026-10-20)** |
| TB4 | `{{table …\|caption:Our Team\|A:header(Name);key(name)\|B:header(Role);key(role)}}` | `<div role="region" tabindex="0" aria-labelledby="bws-table-caption-N">` around the table, whose `<caption id="bws-table-caption-N">` reads Our Team. No caption, no wrapper (TB1) | **PASS (measured 2026-10-20)** |
| TB5 | `…\|A:header(Name);key(name)\|B:header(Role);key(role)\|C:header(Spacer)` | a header-only column: Name / Role / Spacer, Spacer cells EMPTY on both rows, no warning | **PASS (measured 2026-10-20)** |
| TB6 | `…\|A:key(name)\|B:header(Role);key(role)\|C:key(description)` | one header among three: a `<thead>` of blank, Role, blank; three cells per body row | **PASS (measured 2026-10-20)** |
| TB7 | `{{table A:header(Page);use(title)\|B:header(Fixed);fixed(Row)}}` | no source named: the current context is the ONE row: Matrix: Table / Row | **PASS (measured 2026-10-20)** |
| TB8 | `{{table src:post,<MID>;rows,no_such_repeater\|…}}` | a chain that resolves to zero rows renders NOTHING on the front end (no table, no empty body). The editor draws the real table with an empty body (TE2) | **PASS (measured 2026-10-20)** |
| TB9 | `{{table src:post,<MID>;refs,related_staff,limit(1)\|A:header(Staff);use(title)}}` | the chain's own `limit(1)` is the row cap: ONE row, Jane Partner | **PASS (measured 2026-10-20)** |
| TB10 | `{{table src:post,<MID>;refs,related_staff\|A:header(Staff);use(title)}}` | no limit: BOTH rows, Jane Partner, Tom Associate. The table registers no row count of its own | **PASS (measured 2026-10-20)** |
| TB11 | `…\|A:header(Name);key(name)\|B:header(Office);src(inherit;refs,lead_ref)\|C:header(Role);src(same);key(role)` | a column with a header and a hop but no read renders EMPTY cells and never feeds the carry: Office is empty on both rows, and column C `src(same)` carries the ROW, so Role reads Engineering / Operations (not an office) | **PASS (measured 2026-10-20)** |
| TB12 | `…\|A:header( );key(name)\|B:key(role)` | a whitespace-only header is no header: NO `<thead>`, two body columns | **PASS (measured 2026-10-20)** |
| TB13 | `…\|A:header(Name);key(name)\|B:header(Lead);src(inherit;refs)` | a header with an unfinished step is still a column: Name / Lead headers, Lead cells EMPTY on both rows | **PASS (measured 2026-10-20)** |
| TB14 | `…\|A:header(Lead);src(inherit;refs,lead_ref);use(title)\|B:header(Line);src(inherit;refs,lead_ref);key(main_line)` | a copied chain (what Copy Previous Column's Source writes): row 1 Jane Partner / `(555) 200-3000`, row 2 both empty | **PASS (measured 2026-10-20)** |
| TB15 | `…\|A:header(Lead);src(inherit;refs,lead_ref);use(title)\|B:header(Line);src(same);key(main_line)` | a hand-written `src(same)` on column B renders exactly like TB14 | **PASS (measured 2026-10-20)** |
| TB16 | `…\|A:header(Name);key(name)\|B:header(Lead);src(inherit;refs,lead_ref);fixed(Has lead)` | a fixed-text column that hops off the row shows its text only where the hop resolves: Has lead on row 1 (Alice), EMPTY on row 2 (Bob) | **PASS (measured 2026-10-20)** |
| TB17 | `{{table …\|layout:fixed\|A:header(Name);key(name)\|B:header(Lead);src(inherit;refs,lead_ref);fixed(Has lead)}}` | the same cells as TB16, in a table carrying a scope class (`table-layout:fixed;width:100%`) | **PASS (measured 2026-10-20)** |
| TB18 | `{{table src:post,<MID>;refs,related_staff\|A:header(Staff);use(title)\|B:header(Site);src(site);use(title)\|C:header(Page);src(current);use(title)}}` | a bare column roots at its ROW and the explicit roots leave it: per staff row its own title in Staff, BWS Testbed in Site, Matrix: Table (the ambient page) in Page | **PASS (measured 2026-10-20)** |

## TK: the resolved kind of the row

**The row axis is the resolved kind**: one table per kind, the kinds being the ones `bws_read_resolved_source_value()`'s PHPDoc lists, each crossed with the three reads a text column makes: an analog (`use(title)`), a keyed read (`key(…)`) and a fixed read (`fixed(…)`). A missing branch reads as a missing row. Every table has the columns Title / key / Fixed.

| Seam branch | Row | Reached by |
|---|---|---|
| `post` | TK1 | `refs,related_staff` off the pinned page |
| `term` | TK2 | `terms,department` off the pinned page |
| `site` | TK3 | the `site` root |
| `meta_row` | TK4, TK4b | `rows,team_members` off the pinned page; a nested repeater off `matrix-repeaters` |
| `user` | TK5 | the user loop (a user is reached ambiently only) |

| # | Tag | Expected | Status |
|---|---|---|---|
| TK1 | `{{table src:post,<MID>;refs,related_staff\|A:header(Title);use(title)\|B:header(Main line);key(main_line)\|C:header(Fixed);fixed(Staff)}}` | 2 rows: Jane Partner / `(555) 200-3000` / Staff, Tom Associate / `(555) 200-4000` / Staff | **PASS (measured 2026-10-20)** |
| TK2 | `{{table src:post,<MID>;terms,department\|A:header(Title);use(title)\|B:header(Phone);key(phone)\|C:header(Dept)…}}` | 2 rows: Sales / `(987) 333-4444` / Dept, Support / `(987) 111-2222` / Dept | **PASS (measured 2026-10-20)** |
| TK3 | `{{table src:site\|A:header(Title);use(title)\|B:header(Option);key(blogname)\|C:header(Fixed);fixed(Site)}}` | ONE row: BWS Testbed / BWS Testbed / Site | **PASS (measured 2026-10-20)** |
| TK4 | `{{table src:post,<MID>;rows,team_members\|A:header(Title);use(title)\|B:header(Name);key(name)\|C:header(Fixed);fixed(Team)}}` | 2 rows: Title EMPTY on both (a repeater row has no analog, and the cell never falls through to the page title Matrix: Table), Name Alice Adams and Bob Brown, Fixed Team on both. **The fixed read applies to every kind, `meta_row` included; the analog is the one that refuses** | **PASS (measured 2026-10-20)** |
| TK4b | `{{table src:post,<RID>;rows,duty_roster;rows,shifts\|A:header(Day);key(shift_day)\|B:header(Hours);key(shift_hours)\|C:header(Title);use(title)}}` (`<RID>` = `matrix-repeaters`) | a NESTED repeater, the row-set whose parent is a row: 4 rows in document order across both parents, Monday 8am to 4pm, Thursday 10am to 6pm, Tuesday 7am to 3pm, Friday 12pm to 8pm; Title EMPTY on every row | **PASS (measured 2026-10-20)** |
| TK5 | `{{table A:header(Title);use(title)\|B:header(Bio);key(description)\|C:header(Fixed);fixed(Author)}}` inside a `WP_User_Query` loop over role `author` | per author a ONE-row table: Fixture Author / its biography / Author, then Other Author / EMPTY / Author. A bare table in a loop takes the loop item as its row | **PASS (measured 2026-10-20)** |
| TK6 | `{{table A:header(Title);use(title)\|B:header(Bio);key(description)\|C:header(Fixed);fixed(Ctx)}}` at `--url=https://testbed.test/?s=fixture` | **RENDER-TAG ONLY, stated exception** (a search page has no page content to carry a block). ONE row, the `query_context` kind: `Search Results for “fixture”` / empty / Ctx | **PASS (measured 2026-10-20)** |
| TK7 | the TK6 tag at `--url=https://testbed.test/no-such-page/` | **RENDER-TAG ONLY, stated exception.** ONE row, `query_context`: `Fixture 404 Title (filter)` / empty / Ctx | **PASS (measured 2026-10-20)** |
| TK8 | the TK6 tag at `--url=https://testbed.test/author/fixture-author/` | **RENDER-TAG ONLY, stated exception** (an author ARCHIVE is ambient context). ONE row, the ambient `user` kind: Fixture Author / its biography / Ctx | **PASS (measured 2026-10-20)** |
| TK9 | the TK6 tag at `--url=https://testbed.test/department/sales/` | **RENDER-TAG ONLY, stated exception** (a term ARCHIVE). ONE row, the ambient `term` kind: Sales / empty / Ctx | **PASS (measured 2026-10-20)** |

**`use(permalink)` is not a column read.** A text column's `use` values are `key`, `title` and `fixed` (`bws_get_text_field_options()`), so a hand-written `use(permalink)`, `use(content)`, `use(text)` or `use(excerpt)` renders an EMPTY cell on every kind (measured 2026-10-20 over post, term, site and repeater rows, and over a user row on `/author/fixture-author/`, where `use(title)` beside it still reads Fixture Author), never the surrounding page. A user row therefore has no `permalink` read to refuse, and there is no row for one.

## TP: presentation

Every TP row is a visible block on `/matrix-table/`; the rendered output is pinned by the page-snapshot baseline, and the rules quoted for TP24-TP48 were also read off the page footer. A styled table gets ONE scope class and ONE footer `<style>` per distinct rule set. **A row whose label says "the editor shows" is read in the editor** (`docs/editor-controls.md` owns the controls); the front end only shows what the key draws.

### Layout, width, text keys, stripes, column styling

| # | Wire (after `{{table <src>\|`) | Expected rules | Status |
|---|---|---|---|
| TP0 | no presentation keys | plain `<table>`: no scope class, no `<style>` | **PINNED (snapshot)** |
| TP1 | `layout:fixed` | `table-layout:fixed;width:100%` | **PINNED (snapshot)** |
| TP2 | `layout:fixed\|width:70%` and `A:…width(50%)` | `table-layout:fixed;width:70%` and `:is(th,td):nth-child(1){width:50%}` | **PINNED (snapshot)** |
| TP3 | `color:var(--contrast-2)` | `:is(th,td){color:var(--contrast-2)}` | **PINNED (snapshot)** |
| TP4 | `color:#475569;header,var(--accent)` | `:is(th,td){color:#475569}` and `thead th{color:var(--accent)}` | **PINNED (snapshot)** |
| TP5 | `fontWeight:normal;header,bold\|fontStyle:italic\|textAlign:left;header,center\|textTransform:header,uppercase` | `:is(th,td){font-weight:normal;font-style:italic;text-align:left}` and `thead th{font-weight:bold;text-align:center;text-transform:uppercase}` | **PINNED (snapshot)** |
| TP6 | `fontSize:0.875rem` | `:is(th,td){font-size:0.875rem}` | **PINNED (snapshot)** |
| TP7 | `stripe:rows` | `tbody tr:nth-child(odd)`: body row 1 (Alice) shaded at the default 5% of the text color | **PINNED (snapshot)** |
| TP8 | `stripe:rows,odd,#1e73be,25%` | `thead tr, tbody tr:nth-child(even)`: the header and body row 2 (Bob) shaded blue 25% | **PINNED (snapshot)** |
| TP9 | `stripe:cols` | `:is(th,td):nth-child(even)`: the Role column, header cell included | **PINNED (snapshot)** |
| TP10 | `stripe:rows,#c00,10%;cols,#c00,10%` | both rules, the overlap darker | **PINNED (snapshot)** |
| TP11 | `stripe:rows,#1e73be,20%;cols,#c00,10%` | rows blue 20%, columns red 10% | **PINNED (snapshot)** |
| TP12 | `stripe:rows,rgba(0,0,0,.12)` | `linear-gradient(rgba(0,0,0,.12),rgba(0,0,0,.12))`: the color's own alpha, no `color-mix` | **PINNED (snapshot)** |
| TP13 | `stripe:rows,var(--accent),30%` | `color-mix(in srgb,var(--accent) 30%,transparent)` | **PINNED (snapshot)** |
| TP14 | `A:…width(10rem);textAlign(right)\|B:…textAlign(center);color(#c00)\|C:…fontWeight(header,bold)` | `:nth-child(1){width:10rem;text-align:right}`, `:nth-child(2){text-align:center}`, `tbody td:nth-child(2){color:#c00}`, `thead th:nth-child(3){font-weight:bold}` | **PINNED (snapshot)** |

### Borders and corner radius

TP18, TP19, TP22 and TP23 are read in the EDITOR (their labels say which message). Rules for the rest are in their labels and pinned by the baseline.

| # | Wire | Expected | Status |
|---|---|---|---|
| TP15 | `border:2px,#888` | all four lines 2px grey: the outer edge, under the header, between rows, between columns | **PINNED (snapshot)** |
| TP16 | `border:1px,#888;header,3px;rows,dotted` | 1px grey lines, 3px under the header, dotted between rows | **PINNED (snapshot)** |
| TP17 | `border:none;rows,2px` | no lines except a 2px line between rows | **PINNED (snapshot)** |
| TP18 | `border:rows` | same as TP0, theme borders untouched; editor: Between rows ticked, Style Default, Width Theme default | **PINNED (snapshot) + editor** |
| TP19 | `borderRadius:outer,12px` | rounded corners over the theme's own borders; editor: Outer ticked, Style Solid, Radius 12px, no message | **PINNED (snapshot) + editor** |
| TP20 | `border:outer,2px,#888\|borderRadius:outer,12px` | a 2px grey rounded border, inner lines left to the theme | **PINNED (snapshot)** |
| TP21 | `borderRadius:outer,12px` with no headers | rounded corners; the top line still drawn above the first row | **PINNED (snapshot)** |
| TP22 | `borderRadius:8px` | no rounding; editor: an error that a bare 8px is reserved for cell corners, with a Fix button | **PINNED (snapshot) + editor** |
| TP23 | `border:row,2px` | `row` is not a line, so 2px is the base and all four lines draw 2px; editor: an error and a Fix button | **PINNED (snapshot) + editor** |

### The cascade, layout and stripe cases

Rules measured off the rendered page footer.

| # | Wire | Expected rules | Status |
|---|---|---|---|
| TP24 | `textAlign:center;header,left\|fontWeight:bold;header,normal\|color:#475569;header,#c00` | `:is(th,td){color:#475569;font-weight:bold;text-align:center}` and `thead th{color:#c00;font-weight:normal;text-align:left}`: the cascade, one header override on each key | **PASS (measured 2026-10-20)** |
| TP25 | `A:…textAlign(right)\|B:…color(#c00)\|C:…fontWeight(bold)`, no Header segments | `:is(th,td):nth-child(1){text-align:right}` (header cell INCLUDED), `tbody td:nth-child(2){color:#c00}` and `tbody td:nth-child(3){font-weight:bold}` (body only, their headers untouched): alignment is the one property whose header follows | **PASS (measured 2026-10-20)** |
| TP26 | `fontWeight:header,bold` | `thead th{font-weight:bold}` and nothing for the body | **PASS (measured 2026-10-20)** |
| TP27 | `textAlign:center;header,inherit\|color:#c00;header,revert\|fontSize:larger` | `:is(th,td){color:#c00;font-size:larger;text-align:center}` and `thead th{color:revert;text-align:inherit}`: the CSS globals pass through on a header, and a size keyword is accepted | **PASS (measured 2026-10-20)** |
| TP28 | `textAlign:left;header,center;header,right` | `:is(th,td){text-align:left}` and `thead th{text-align:center}`: the repeated header segment is dropped, the first stands | **PASS (measured 2026-10-20)** |
| TP29 | `textAlign:center;footer,right` | `:is(th,td){text-align:center}` only: the unknown head is dropped | **PASS (measured 2026-10-20)** |
| TP30 | `color:#c00;header,nope` | `:is(th,td){color:#c00}` only: the bad header value is dropped, the body stays | **PASS (measured 2026-10-20)** |
| TP31 | `layout:auto` | `table-layout:auto` and no width | **PASS (measured 2026-10-20)** |
| TP32 | `layout:fixed\|width:auto` | `table-layout:fixed;width:auto`: as written, not full width (TP1 is the full-width case) | **PASS (measured 2026-10-20)** |
| TP33 | `width:40rem` | `width:40rem` and no `table-layout` | **PASS (measured 2026-10-20)** |
| TP34 | `layout:fixed\|width:var(--table-w, 40rem)` | `table-layout:fixed;width:var(--table-w,40rem)`; the variable is unset here, so it reads 40rem | **PASS (measured 2026-10-20)** |
| TP35 | `layout:fixed\|A:…width(12rem)` | `table-layout:fixed;width:100%` and `:nth-child(1){width:12rem}`: Name fixed, the other two share the rest | **PASS (measured 2026-10-20)** |
| TP36 | `layout:auto\|A:…width(12rem)` | `table-layout:auto` and `:nth-child(1){width:12rem}`: a hint the content can overrule | **PASS (measured 2026-10-20)** |
| TP37 | `stripe:rows,currentColor,10%` | `tbody tr:nth-child(odd)` shaded `color-mix(in srgb,currentColor 10%,transparent)` | **PASS (measured 2026-10-20)** |
| TP38 | `stripe:rows,#1e73be,100%` | `color-mix(in srgb,#1e73be 100%,transparent)`: a solid fill | **PASS (measured 2026-10-20)** |
| TP39 | `stripe:rows` with no headers | `tbody tr:nth-child(even)`: counting from the first body row, Alice row 1 plain and Bob row 2 shaded, the reverse of TP7 | **PASS (measured 2026-10-20)** |
| TP40, TP41 | two tables, both `stripe:rows,#c00,10%` | the SAME scope class on both, and exactly ONE `<style id="bws-dte-table-…">` for it in the footer | **PASS (measured 2026-10-20)** |
| TP42 | `stripe:rows,#1e73be,10%` | a different scope class and its own `<style>` | **PASS (measured 2026-10-20)** |
| TP43 | `stripe:rows;rows,odd` | `tbody tr:nth-child(odd)` only: the repeated target is dropped, the first stands | **PASS (measured 2026-10-20)** |
| TP44 | `stripe:rows,#1e73be,#c00` | `color-mix(in srgb,#1e73be 5%,transparent)`: the second color is dropped, the default 5% applies | **PASS (measured 2026-10-20)** |
| TP45 | `stripe:rows,odd,even` | `thead tr, tbody tr:nth-child(even)`: the `odd` sequence stands and `even` is dropped | **PASS (measured 2026-10-20)** |
| TP46 | `stripe:banana;cols` | `:is(th,td):nth-child(even)` only: the unknown target is dropped, the column stripe draws | **PASS (measured 2026-10-20)** |
| TP47 | `stripe:rows,rgba(0,0,0` | NO scope class and no `<style>`: an unbalanced bracket drops the whole key | **PASS (measured 2026-10-20)** |
| TP48 | `layout:fixed\|stripe:rows` | both flags on one scope class: `table-layout:fixed;width:100%` and `tbody tr:nth-child(odd)` shaded at the default 5% | **PASS (measured 2026-10-20)** |

## TE: the editor preview

Only TE1 renders on the front end; TE2-TE5 are read in the EDITOR. Their expectations are in their labels on `/matrix-table/`; this file does not restate them.

## Not covered, and why

- **The unresolved-source front-end row.** An unresolvable root (`src:post,999999;…`) renders nothing on the front end like TB8; the editor skeleton is TE3-TE5.
- **The skeleton's column-source wording has no automated pin at its call site.** `preview-label-test.php` pins what a column's source part reads as (a step-first `src(rows,team)` previews as its row, not Current), but nothing runs `bws_table_skeleton()`, so the root the table passes is read only in the testbed editor (TE2-TE5).
- **`when` on a table** (FW-27), **`orientation`, the `scope="col"`/`scope="row"` pair and the responsive-overflow JS** are post-v1 (spec §Not in scope).
