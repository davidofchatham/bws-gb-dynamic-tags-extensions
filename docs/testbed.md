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

`--loop-item=<post_id>` for a synthetic query-loop item, `--porcelain` for output-only. Runs the
real main query so `is_tax()` / queried-object / current-post are genuine. This is the cheap
what-if / discovery-row engine — use it before building a page to answer "what does this tag do on
context X".

### (Re)seed fixture state

```
bin/seed.sh testbed core-structures
```

Idempotent. The `core-structures` **blueprint** (`tools/fixtures/core-structures/`) seeds the state
the `*-test-matrix.md` files assume — matrix pages are split by source-state (`matrix-post-meta`,
`matrix-terms-valid|mixed|junk`); tag families accrete rows into them. A page that splits on
something else says so in its own builder docblock — `matrix-loops`, whose axis is the loop
context a row renders inside.

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

The container runs `opcache.revalidate_freq = 120`, so a front-end request within two minutes of a source edit runs STALE BYTECODE while the disk bytes are already correct — no cache-bust and no file check can see it.

That makes front-end MUTATION testing silently vacuous: two mutations that blank a whole fixture section both read as "no change". Recycle the lsphp workers between arms — `docker compose exec -T litespeed bash -c 'killall lsphp 2>/dev/null; true'` — instead of restarting the whole container or waiting the window out. Near-instant, and it's the fix this env's own docs already validate for the identical symptom (env repo `README.md:688-696`).

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

NB the visible rows are a bug surface `render-tag` cannot reach, and `wptexturize` is only part
of it. A front-end request renders each tag inside a real block, on a real query, through
`the_content` — `$block` is empty under `render-tag`, term and user loops cannot be faked at any
flag combination, and `--porcelain` skips the content filters entirely. (Straight quotes do turn
curly there, so keep using prime marks `′`/`″` for units.) The page snapshots below are what pin
that path; [`update-triggers.md`](update-triggers.md#page-snapshot-instrument-or-baseline-change)
owns what a clean run of them does and does not prove.

## Page snapshots — the committed rendered-output baseline

`tools/test/page-snapshots.php` curls every fixture page, normalizes away per-render churn, and
diffs the result against a baseline committed under `tools/test/snapshots/`. It is the only
instrument here that renders a tag the way a visitor gets it (above), so it is what a change that
could move rendered output is measured against.

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
and ACF Pro, with our own version deliberately excluded (that file's header has why, and owns which
entries must be present). `verify.php` prints the comparison FIRST. A version drift line is a
**WARNING**: it exists to tell you a diff below is attributable to a dependency rather than to your
change. A dependency the record requires and the site cannot use **FAILS the run, naming it**,
instead of skipping, so deactivating one is enough to stop a verification.

**Every plugin that was ACTIVE at capture is recorded too**, in the same file's `active` list — not only the four required above. Re-record it from `wp plugin list --status=active --field=file` whenever you re-capture. A plugin activated or deactivated since capture prints as a **WARNING** in both directions and never fails the run: the fixture site is shared, so an unexpected co-resident plugin is something to attribute a diff to, not something to forbid. A warning with no page diff under it needs nothing done — say so in the commit rather than re-capturing to make it quiet.

**A plugin toggle no longer moves the baseline.** The document head is not captured (bar `<title>`, the two description metas and any `bws-*` `<style>`), which is where co-resident stylesheet links live; `docs/update-triggers.md` owns what that costs. The practical consequence for operating this: you can activate and deactivate unrelated plugins on the fixture site without invalidating the committed baseline, and the warning line above is how you find out it happened.

**All four must be ACTIVE on the fixture site, GB Query Enhancements included.** It supplies no
fixture row's content; it is a co-resident extension that filters our tag rendering itself, from
three PHP hooks that run on every tag render —
[`coresident/gb-query-enhancements.md`](coresident/gb-query-enhancements.md#it-filters-tag-rendering-in-php-on-every-render)
enumerates them and owns that half. Every baseline below was therefore captured with them in the
chain, and the defect that made that visible is GitHub
[#123](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/123).

What makes the declaration load-bearing is that **nothing else in the tree notices if it goes**:
measured 2026-08-24, with it deactivated all nine snapshot pages still matched the baseline and the
dependency check was the only failure. Today's agreement is a coincidence nobody measured forward, and it is exactly the
state in which a silent variable is easiest to acquire.

**Re-capture and re-record in the SAME commit** — `env-versions.php`'s header owns why. A capture
with any page unreachable **writes nothing at all**: every fetch completes before the first write,
so the baseline on disk is left untouched and the run exits non-zero. The one case that escapes
that guard is a write failing partway through, which does leave a mixed set on disk — the run says
so explicitly and asks you to re-run rather than commit.

`php tools/test/page-snapshot-normalize-test.php` covers the pure half (normalization, diffing,
deriving the page set) with no site and no network.
