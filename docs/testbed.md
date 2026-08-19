# The fixture testbed — operating it

How to run WordPress integration checks for this plugin. The pure harnesses under `tools/test/`
can't reach anything WP-dependent (ambient context, ACF/meta reads, GB render, the editor React
controls); this is what covers that half. `CLAUDE.md` §Development owns the two-layer rule and
points here; this page owns the operation.

**Prefer routing integration smoke tests through the testbed over hand-built pages or live-site
probes.** The manual `tools/test/*-test-matrix.md` files (integration rows exercised by hand or via
`render-tag`) are noted per trigger in [`update-triggers.md`](update-triggers.md) — run them against
the testbed, never the live/cached site.

## Where the commands live

**All `bin/*.sh` commands below live in the ENV repo, not this one.** Run them from the **WSL Ubuntu
shell** in `~/wp-litespeed` — Windows Git Bash cannot reach it. Name the env, not its location.
There is no `bin/` in this plugin repo.

**Location-independent alternative (prefer when scripting):** Docker Desktop shares one daemon
across Windows and WSL, so addressing the container BY NAME works from either shell unchanged:

```
docker exec wp-litespeed-litespeed-1 sh -c 'cd /var/www/vhosts/testbed/html && wp <cmd> --allow-root'
```

## Two entrypoints

### Render a tag with real ambient context

```
bin/wp.sh testbed bws render-tag '{{...}}' --url=https://testbed.test/<context>/
```

`--loop-item=<id>` for a synthetic query-loop row, `--porcelain` for output-only. Runs the real main
query so `is_tax()` / queried-object / current-post are genuine. This is the cheap what-if /
discovery-row engine — use it before building a page to answer "what does this tag do on context X".

### (Re)seed fixture state

```
bin/seed.sh testbed core-structures
```

Idempotent. The `core-structures` **blueprint** (`tools/fixtures/core-structures/`) seeds the state
the `*-test-matrix.md` files assume — matrix pages are split by source-state (`matrix-post-meta`,
`matrix-terms-valid|mixed|junk`); tag families accrete rows into them.

**Group rows by tag in [`tag-reference.md`](tag-reference.md) Catalog order** (the §Base tag GB types
table sequence: text→content→title→permalink→image→datetime_single→datetime_range→email→phone→join→
table→call), NOT arrival order. Applies to `*-test-matrix.md` sections AND the visible `blocks.php`
row groups — forward-going: new rows slot into Catalog position; existing files reorder
opportunistically when touched.

**A new tag's Catalog placement is a DECISION — ASK for it if not already specified, never default
to append-at-end.** E.g. `{{table}}` (not yet shipped, no row in §Base tag GB types today) is decided
to sit after `{{join}}` when it ships, not after the `call` outlier.

See `tools/fixtures/core-structures/README.md`. Full design: `.claude/plans/fixture-testbed.md`.

## Two layers of staleness sit between an edit and what you read

### The page cache

**Front-end pages are LiteSpeed-cached — always cache-bust when eyeballing after a reseed:**

```
curl -sk "https://testbed.test/matrix-post-meta/?nocache=$RANDOM"
```

**`$RANDOM` IS A BASH-ISM AND THE CONTAINER'S SHELL IS `dash`** — inside `docker exec … sh -c '…'`
it expands to EMPTY, so every "bust" hits one URL and you read the same cached page all session.
Generate the value in the OUTER shell (`N=$(date +%s%N)`) and interpolate it in.

A plain curl can return the pre-reseed page, so new fixture rows read as MISSING when they seeded
fine. `bin/wp.sh testbed litespeed-purge all` does NOT work from the wpcli container — use the query
string instead.

### The bytecode cache — quieter, and it invalidates whole experiments

The container runs `opcache.revalidate_freq = 120`, so a front-end request within two minutes of a
source edit runs STALE BYTECODE while the disk bytes are already correct — no cache-bust and no file
check can see it.

That makes front-end MUTATION testing silently vacuous: two mutations that blank a whole fixture
section both read as "no change". Restart the container between arms (`docker restart
wp-litespeed-litespeed-1`, ~6s to serve again) or wait the window out.

WP-CLI is exempt (`opcache.enable_cli = Off`), so `render-tag` sweeps need none of this.

## MANDATORY when adding matrix rows — also make them VISIBLE

Every new `*-test-matrix.md` row group MUST additionally be generated as browsable/editable GB blocks
on the testbed pages, via the blueprint's `blocks.php` (`bws_fixture_gb_section` + `_row` under the
right `content_builder`; add a NEW builder + dispatcher entry + `content_builder` on the fixture when
the rows resolve on a post type with no page content yet — e.g. join's `staff_join` on the staff
singles).

The user browses these on the actual site — front end to eyeball, editor to interact with controls /
check reveal rows. Do NOT leave rows as `render-tag`-only. Reseed + curl the front end (with the
`?nocache=` bust above — a cached page hides brand-new rows) to confirm before commit.

Exceptions (render-tag/harness-only): a bare tag needing a term ARCHIVE as ambient context (text T4),
or synthetic per-field blanking with no fixture (join J23/J24) — state the exception in the matrix.

NB the front-end page runs WP content filters (`wptexturize` — straight quotes → curly; use prime
marks `′`/`″` for units) that `--porcelain` skips, so the visible rows are also a bug surface
render-tag can't reach.
