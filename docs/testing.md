# Testing

Referenced from [`CLAUDE.md`](../CLAUDE.md) §Development, which keeps the one-paragraph summary and
links here for the full harness catalog. For operating the seeded fixture testbed itself
(entrypoints, the two staleness layers, seeding, the visible-row mandate, running page snapshots),
see [`testbed.md`](testbed.md) — that split is deliberate: this file owns *what test exists*, that one
owns *how to run it against a live site*.

## Two test layers

Run the pure harness always; route integration through the testbed.

1. **Pure harnesses** under `tools/test/` — no framework, no autoload; each runs via
   `php tools/test/<name>.php`, exiting non-zero on failure. Older ones copy the pure functions they
   exercise inline (house pattern); newer ones **require the real file** when it is pure, because a
   test-local copy of the rule is the exact drift the extraction removed (`limit-clamp-test.php`,
   `slot-options-build-test.php`, `slot-fold-test.php`, `fold-migration-test.php`,
   `related-post-src-migration-test.php`, `pattern-cache-test.php`, `gb-output-boundary-test.php`,
   `gb-trust-boundary-test.php`, `replay-verdict-test.php`); one reads a sibling script's SOURCE
   rather than calling it, because the script under test executes a replay on load
   (`replay-source-identity-test.php`); three require the real file AND then scan every `.php` in the
   repo, because half of what each holds is a census rather than a property of any one file —
   `gb-output-boundary-test.php` (call sites; it requires two real files), `block-context-keys-test.php`
   (the block-context key vocabulary, censused because a misspelled key is a legal read with a
   plausible answer, never an error) and `gb-trust-boundary-test.php` (the sites allowed to ask
   GenerateBlocks about the current user; it is the only census that scans `tools/` too, so the pins
   that ask GB directly are written down as exemptions rather than invisible). Run the one whose
   domain you touched — see `CLAUDE.md` §Update triggers for the key→harness map, or `ls tools/test/`
   for the full set. No CI runs these; run them locally before commit.

   **Three are not pure, for three different reasons.** `control-order-test.php` runs against no
   world at all but is the only harness that sees all three registration constructors at once.
   `page-snapshots.php` needs a SERVED fixture site, which nothing else under `tools/test/` does.
   `replay-vacuity-test.php` SHELLS OUT to a sibling script (`diff-replays.php`) and reads its exit
   status, because the property it holds — that no attestation can fail open — is a property of the
   whole run rather than of any function; the same treatment is unsafe for `replay-tags.php`, which
   executes a replay on load, and `replay-source-identity-test.php` reads that file's SOURCE for
   exactly that reason. Each file's own header carries its rationale; `page-snapshot-normalize-test.php`
   covers the second one's pure half (normalization, diffing, deriving the page set) with no site at
   all, and `replay-verdict-test.php` covers the third's.

   **Some run under `node`, not `php`** — `slot-fold-repeater-test.js`, `editor-filter-chain-test.js`,
   `field-combo-control-test.js`, `editor-preview-context-test.js` (pure JS, reach editor-only logic
   no PHP harness can), and three PHP harnesses that shell out to `node` for a twin-language check
   (`slot-fold-twin-test.php`, `serialization-order-test.php`, `fold-migration-test.php`) — a missing
   `node` FAILS these rather than skipping, since a silent pass would hide exactly the drift each
   exists to catch. Each file's own header has its mechanism.

2. **WordPress integration — the fixture testbed.** The pure harnesses can't reach anything
   WP-dependent (ambient context, ACF/meta reads, GB render, the editor React controls). For that
   there is a seeded WP site on the local **wp-litespeed** OpenLiteSpeed/Docker env, site `testbed`.
   **Prefer routing integration smoke tests through it over hand-built pages or live-site probes.**
   The two entrypoints are `bin/wp.sh testbed bws render-tag` (renders against real ambient context —
   the cheap what-if engine) and `bin/seed.sh testbed core-structures` (reseeds fixture state).

   [`docs/testbed.md`](testbed.md) owns operating it, and reading it is not optional before an
   integration run. Two layers of staleness sit between an edit and what you read (the page cache,
   and a bytecode cache that makes front-end mutation testing silently vacuous), the `bin/*.sh`
   commands live in the ENV repo rather than here, and the **mandatory rule that every new matrix row
   group is also generated as VISIBLE GB blocks** is stated there.
