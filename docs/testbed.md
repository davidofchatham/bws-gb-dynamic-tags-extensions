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

See `tools/fixtures/core-structures/README.md`. Full design: `.scratch/plans/fixture-testbed.md`.

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

NB the visible rows are a bug surface `render-tag` cannot reach, and text formatting is the
smaller half of it. A front-end request runs the WHOLE render path: WP content filters
(`wptexturize` — straight quotes → curly; use prime marks `′`/`″` for units) and, before them,
the `generateblocks_dynamic_tag_replacement` filter chain, where any co-resident plugin can
rewrite what our callback returned. `--porcelain` skips both, and so do the replay scripts. The
page snapshots below are what pin that path; [`update-triggers.md`](update-triggers.md#page-snapshot-instrument-or-baseline-change)
owns what a clean run of them does and does not prove.

## Page snapshots — the committed rendered-output baseline

`tools/test/page-snapshots.php` curls every fixture page, normalizes away per-render churn, and
diffs the result against a baseline committed under `tools/test/snapshots/`. It is the only
instrument here that sees the full render path (above), so it is what a change that could move
rendered output is measured against.

```
php tools/test/page-snapshots.php              # compare against the baseline (exit 1 on diff)
php tools/test/page-snapshots.php --capture    # write/refresh the baseline
php tools/test/page-snapshots.php --base-url=https://other.test
```

**Run it from the host, from the repo root** — the repo is bind-mounted read-only into the
container, so a capture under `wp eval-file` can write nothing. Comparison only reads, so
`verify.php` runs it in-container as part of every verification — you do not have to remember to.

**The page set comes from the blueprint manifest**, not from a list kept in the script: every
`posts` entry carrying a `content_builder` is snapshotted, so a new fixture page enters the set
by existing. Nothing to register.

**Cache-busting is built in** (the §page cache rule above applies to this instrument too, and it
generates its own bust value per request — do not wrap it in one). A reseed does not invalidate
the baseline either: `post_modified` and its siblings are normalized out, so `bin/seed.sh` and
`--capture` are independent operations.

**The environment the baseline was captured under is recorded** in
`tools/fixtures/core-structures/env-versions.php` — GenerateBlocks, GB Pro, GB Query Enhancements
and ACF Pro, with our own version deliberately excluded (that file's header has why). `verify.php`
prints the comparison FIRST and a drift line is a WARNING, never a failure: it exists to tell you
a diff below is attributable to a dependency rather than to your change.

**Re-capture and re-record in the SAME commit** — `env-versions.php`'s header owns why. A partial
capture (any page unreachable) is not committable either; the script says so and exits non-zero
rather than leaving eight fresh files beside one stale one.

`php tools/test/page-snapshot-normalize-test.php` covers the pure half (normalization, diffing,
deriving the page set) with no site and no network.
