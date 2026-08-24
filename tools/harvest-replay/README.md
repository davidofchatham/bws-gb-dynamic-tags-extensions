# harvest-replay

The replay/diff/convert half of a two-clone regression instrument. It answers "did this build
break wire that EXISTS" — not a coverage question, and not a claim that some capability was ever
exercised in the wild. Nothing in a live site can author a tag string nothing could produce.

**This directory owns only the parts committed to this repo.** Harvest — walking a site's content
and building the corpus these tools consume — lives in the separate `wp-litespeed` ENV repo
(`bin/harvest-tags.sh` + `fixtures/harvest/harvest-tags.php`), plugin-agnostic and reused by other
plugins in the family. The env repo also owns the clone lifecycle these tools run inside
(`bin/pull.sh` / `bin/snapshot.sh` / `bin/dev-plugin.sh`, `sites.conf`) and the driver scripts that
loop the files below over a URL set. None of that is duplicated here; this README covers the three
files this repo ships and how they compose with the harvest half, not how to run a harvest.

## Files

| File | Role |
|---|---|
| `replay-tags.php` | Renders one harvested corpus against ONE real URL via `wp eval-file`, through genuine ambient context (`wp()`, not a bare `--url`). Carries the opcache and build-identity tripwires and the per-render volatility check. Own header has the full mechanism. |
| `diff-replays.php` | Plain PHP, no WordPress — compares two replay artifacts. Asserts URL-set, census and build identity, then buckets every difference (`attested` / `synthetic` / `volatile` / `unclassified`) so a reviewer can triage rather than reason from a flat diff. Exits non-zero on any unexplained change. Own header has the full assertion list. |
| `run-converter.php` | Runs the tag converter over a whole clone exactly as the admin Migrate button does, and emits the old→new tag mapping (`mapping.jsonl`) that lets `diff-replays.php --map` compare wire across a migration boundary, where the tag strings themselves changed. Own header has the full mechanism. |

Each file's docblock is the authority on its own mechanism — this page is the connective layer
between them and the harvest half, not a re-export of that detail.

## The replays, and their baselines

**Each replay is named for what VARIES between its two renders**; everything else is held fixed.
That is the whole legend — a run report says what moved without one. The trio runs two of them
today, and conflating their baselines makes one invisible:

- **The migration replay — the WIRE changed**, and it is real wire nobody designed for. Baseline
  is pre-1.17.0 → 1.17.0: harvest + replay on the old build (`A`), upgrade and run
  `run-converter.php` (wire changes — that's the point), harvest + replay again (`C`). The
  assertion is `A-render ≡ C-render` via `diff-replays.php --map=mapping.jsonl`; the wire diff
  (`A-wire` vs `B-wire`) is reviewed, not gated. A re-run after the migrator itself has moved is
  **the second migration replay**, and is the same experiment against a later converter.
- **The build replay — OUR BUILD changed**, same wire both sides. Baseline is one clone, same
  declared plugin version, before/after a resolver change — no converter run, no `--map`, no DB
  restore. The gate expects the diff to come back **empty**; `diff-replays.php`'s build-identity
  check exists specifically because this replay's failure mode is silent — an unswapped build
  also diffs empty, which is this replay's own pass condition, so the diff independently confirms
  the swap happened (recorded commit + a stat-only digest of the source tree) before trusting an
  empty result. **Its diff also cannot see the tag-replacement filter layer, and that is a
  property of the whole instrument rather than of this replay.** `replay-tags.php` calls
  `replace_tags()` directly, so `generateblocks_dynamic_tag_replacement` never fires: a
  co-resident plugin rewriting our output after we return it moves every real page and moves
  nothing here. An empty diff is therefore a statement about our resolver over real wire, not
  about what a visitor sees. `tools/test/page-snapshots.php` is the only instrument that covers
  that half, and it covers the fixture site only — [`docs/update-triggers.md`](../../docs/update-triggers.md#page-snapshot-instrument-or-baseline-change)
  owns the rule.
- **The dependency replay — A DEPENDENCY'S VERSION changed**, with our build and the wire both
  held fixed. **Reserved, not built.** Named here because the name is the hard part: without it
  the case has nowhere to land, and a dependency-driven diff gets filed under one of the two
  above, where it reads as our regression. Tracked as FW-96.

> **Renamed 2026-08-24.** These were `Experiment M`, `Experiment R` and `Experiment E`, with `M2`
> for the second migration run. The letter scheme already needed a numeric suffix to stay
> unambiguous and never said what was held fixed. **Run records under `docs/design-history/` keep
> the old codes and are deliberately not rewritten** — they record runs that happened under those
> names, so a reader crossing into them should expect the mismatch.

## The seam is two artifacts, not one

The corpus a replay consumes is **not** a single manifest. Most tags live in Elements, and an
Element has no URL of its own, so there is no single `{tag, url}` row to hold. Harvest (env repo)
produces two artifacts instead: `census.jsonl` — every tag occurrence plus its container,
exhaustive and cheap — and `urls.jsonl` / `urls.tsv` — a stratified, deterministically-sampled URL
inventory. `replay-tags.php` is the join: it renders the corpus against a URL, producing their
product. That deliberately over-covers (a tag renders against contexts it never actually appears
on); narrowing is a filter over replay output, not a different instrument. Where a container is
itself ordinary public content, its URL is exact (the post's own permalink) rather than parsed from
a display rule — those pairs are `attested`, everything else `synthetic`, and `diff-replays.php`
buckets by that so review effort binds hardest where there's proven front-end exposure.

## Sampling is stratified, not flat (harvest-side, for context)

Because sampling happens on the URL axis rather than the tag axis, the harvest side stratifies by
**(tag × context-kind)** rather than by tag string alone: ambient context is a property of the URL,
and the same tag string takes a different resolution arm on a singular post, a term archive, or an
author archive. A shape key built from the tag string alone would collapse cases this instrument
exists to keep distinct. This is harvest's design (env repo), noted here because it's necessary
context for reading what a replay/diff result does and doesn't cover — a clean diff says nothing
about a context-kind stratum the sample never drew.

## Design history

The original design writeup — including the dated build/debug history and the specific incidents
that shaped the tripwires above — lives in `docs/design-history/multi-step-slot-sources.md`
§Verification. That plan is an archived, frozen record of how this instrument came to be, not a
live doc; this README plus each file's own header are the current, authoritative description of
how it works and how to use it.
