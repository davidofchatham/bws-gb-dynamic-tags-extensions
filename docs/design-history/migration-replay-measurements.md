# Archive: the migration replay, measured on a real clone (2026-08-31 and 2026-09-15)

> **DESIGN HISTORY: A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was measured and built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md` §Spec lifecycle owns that rule). Cite it for PROVENANCE: what a rule was hardened against, what a run actually produced. Never cite it as a statement of how the instrument works now. For current state: `tools/harvest-replay/README.md`, `docs/update-triggers.md`, or the header of the script concerned.
>
> **`Site H` is a pseudonym** for the real client clone these runs were measured against, substituted before publication. The measurements are unchanged; only the name is.

**What this record is for.** `tools/harvest-replay/README.md` states the rules a migration replay rides on and keeps one measured number beside each rule where the number is why the rule exists. The runs that produced those numbers, and the rest of what they showed, are here. **None of it can be re-run.** The pre-1.17.0 snapshot both runs started from was a sibling project's snapshot of the same clone, used opportunistically, and its name now holds a later save. A future run starts from a fresh pull of the live site and does not reproduce these numbers. The harvest trees the runs wrote are kept in the ENV repo.

## Run 1: where the deferring constant has to go, 2026-08-31

**Question.** The GB Pro pattern-cache repair also fires from the plugin's own upgrade trigger, which discards the cleared strings rather than recording them. Does it matter WHEN the `BWS_DYNAMIC_TAGS_DEFER_UPGRADE_RECONCILE` constant is defined: before the run, or before the snapshot restore?

**Setup.** The Site H clone, restored from its one genuine pre-1.17.0 snapshot (stamped 1.16.0). Corpus: 345 census rows over 16 pattern trees. Ordering was the only variable.

**Result.** 7 stale strings recorded with the constant in place before the restore, 5 with the trigger allowed to fire one request earlier. The restore alone spends the population: `bin/snapshot.sh --restore` ends by flushing caches through wp-cli, and that boot fires the trigger. A bare `bin/wp.sh <site> option get` does too, and so does anyone loading a page on the clone.

**What the two lost strings were.** A pre-1.6 `post_acf_date_time_range` pair on a single post. Staleness that predated the run, which is what the artifact exists to name and what the run's own migration can never re-create.

**The fixture-testbed measurement it followed.** Same ordering, run on the fixture testbed the same day: 0 strings recordable after the trigger ran, 1 with it deferred (GH #117).

## Run 2: the full four-step run, 2026-09-15

**Setup.** The same clone and snapshot. Build A was the released 1.19.0, the converter ran on 1.20.0, and C was 1.20.0. Corpus: 345 census rows over 105 URLs, with `--map=mapping.jsonl --removed=removed-wire.jsonl`.

**Result.**

| Bucket | Count |
|---|---|
| Distinct strings cleared by the repair | 6 |
| One-sided pairs, every one reported as a repair rather than a failure | 210 |
| Identical | 13,805 |
| Volatile, excluded | 120 |
| CHANGED | 40 |
| A-side renders paired on the OLD wire | 315 |

A separate measured run, which the README carried undated and which is not otherwise written up, showed the other side of this: 212 one-sided pairs beside 13,445 identical, every one a hard failure. That is why `--removed=` is not optional.

**Reading the residue.** The 40 CHANGED were all `{{image …|fallback:…}}` on archive, search, 404, and date contexts. That is the `{{image}}` fallback fix released in 1.19.1: a BUILD-axis change, visible because the clone's live copy sat two patch releases behind dev, not a migration finding. The 315 old-wire renders are exactly the 3 census strings `run-converter.php` reported as unreachable (options and meta only), times the 105 URLs. A migration replay moves both axes by construction, so a CHANGED bucket is attributed to one of them before it is read as a regression.

**What it closed.** FW-78 (the differ could not tell a repaired row from a vanished one). Both halves of the differ change had landed on 2026-08-28; this run is the live reproduction the ticket asked for.
