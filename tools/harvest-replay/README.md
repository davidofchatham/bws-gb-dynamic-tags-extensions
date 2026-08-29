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
| `diff-replays.php` | Plain PHP, no WordPress — compares two replay artifacts. Asserts URL-set, census and build identity, then buckets every difference (`attested` / `synthetic` / `volatile` / `unclassified`) so a reviewer can triage rather than reason from a flat diff. **Exit status separates a result from an instrument failure** — `1` means renders moved, `3` means it cannot answer, and a caller that collapses them reports the second as the first. Own header has the full assertion list. |
| `replay-verdict.php` | The two verdict rules `diff-replays.php` cannot hold inline. Pure and side-effect-free on load, which `diff-replays.php` is not — it is a script, so a harness cannot call its decisions. Both rules LOOSEN a gate, and a loosened gate fails silently, so they were extracted to be assertable. `tools/test/replay-verdict-test.php` exercises them. |
| `run-converter.php` | Runs the tag converter over a whole clone exactly as the admin Migrate button does, and emits the old→new tag mapping (`mapping.jsonl`) that lets `diff-replays.php --map` compare wire across a migration boundary, where the tag strings themselves changed. Also writes `removed-wire.jsonl` — which strings the GB Pro pattern-cache repair CLEARED, a separate artifact because a removal is not a rename. Own header has the full mechanism. |

Each file's docblock is the authority on its own mechanism — this page is the connective layer
between them and the harvest half, not a re-export of that detail.

## The replays, and their baselines

**Each replay is named for what VARIES between its two renders**; everything else is held fixed.
That is the whole legend — a run report says what moved without one. The trio runs two of them
today, and conflating their baselines makes one invisible:

- **The migration replay — the WIRE changed**, and it is real wire nobody designed for. Baseline
  is pre-1.17.0 → 1.17.0: harvest + replay on the old build (`A`), upgrade and run
  `run-converter.php` (wire changes — that's the point), harvest + replay again (`C`). The
  assertion is `A-render ≡ C-render` via `diff-replays.php --map=mapping.jsonl
  --removed=removed-wire.jsonl`; the wire diff (`A-wire` vs `B-wire`) is reviewed, not gated.
  **`--removed=` is not optional in practice.** The GB Pro pattern-cache repair fires from the
  upgrade trigger before the run and REMOVES stale shadow wire, so the C-side census legitimately
  holds fewer rows and every removed string lands as a pair present on only one side — 212 of them
  beside 13,445 identical on one measured run, every one a hard failure. The artifact
  `run-converter.php` writes says which strings the repair cleared, so those report as repairs and
  every other one-sided pair stays the failure it was. It is evidence the run produced, not an
  exception list: an artifact naming nothing forgives nothing. A re-run after the migrator itself has moved is
  **the second migration replay**, and is the same experiment against a later converter.
- **The build replay — OUR BUILD changed**, same wire both sides. Baseline is one clone, same
  declared plugin version, before/after a resolver change — no converter run, no `--map`, no DB
  restore. The gate expects the diff to come back **empty**; `diff-replays.php`'s build-identity
  check exists specifically because this replay's failure mode is silent — an unswapped build
  also diffs empty, which is this replay's own pass condition, so the diff independently confirms
  the swap happened (recorded commit + a stat-only digest of the source tree) before trusting an
  empty result. **What its diff cannot see is the render AROUND the tag**, and that is a property
  of the whole instrument rather than of this replay. `replay-tags.php` calls `replace_tags()`
  directly — which does reach `generateblocks_dynamic_tag_replacement`, so a filter on that hook
  is NOT the blind spot — but `$block` is empty, no query loop exists, and `the_content` filters
  never run. An empty diff is therefore a statement about our resolver over real wire, not about
  what a visitor sees. `tools/test/page-snapshots.php` covers that half, over the fixture site
  only — [`docs/update-triggers.md`](../../docs/update-triggers.md#page-snapshot-instrument-or-baseline-change)
  owns the rule.
- **The dependency replay — A DEPENDENCY'S VERSION changed**, with our build and the wire both
  held fixed. **Built ENV-SIDE 2026-08-24** (`bin/dep-replay.sh`); this repo's half is not.
  Because the swap happens outside both repos, the replay artifact carries an `env` row naming
  every installed plugin and its version, and the env repo asserts the two sides disagree about
  it before any diff is read — the build replay's commit check, one axis over, on a plugin
  neither repo owns. **`diff-replays.php --dependency-replay` is this repo's half, and it is now
  built:** under that flag identical build identity is REQUIRED rather than fatal, and the `env`
  row — skipped by the loader until now — becomes the varying axis, which must be present on both
  sides and must differ. It deliberately does NOT say WHICH dependency moved, or that nothing else
  moved with it; `attest-deps.php` owns both, where the staging that produced them lives, and a
  second copy of that rule here would be a drift pair. **The coverage limit is unchanged and is
  not this flag's to close:** only GenerateBlocks has been exercised live (2.4.1 → 2.4.0, 9962
  renders per arm, CHANGED 0). GB Pro, GB Query Enhancements and ACF Pro are supported by
  construction and never run, and the licensed add-a-version path is unexercised. FW-96 stays open
  on that, not on the differ.

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
