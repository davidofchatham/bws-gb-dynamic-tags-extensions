# SPEC — Deprecated/Removed settings split + scan-hide (1.14.0, in-flight)

Branch `deprecated-tag-removal`. Lands before merge to main (FW-1 done, this = FW-36 follow-on). §G/§C live here — no separate architecture doc for this feature.

## §G — goal

Split settings page into 4 boxes (Deprecated Tags / Removed Tags / Deprecated Options / Removed Options) on a live/inert axis, reposition above Diagnostics, add scan-driven hide-when-unused with a permanent "show all" escape hatch.

## §C — constraints

1. **CORRECTED by B1.** Original premise (all 108 tag-type entries inert → Deprecated Tags box empty today) was wrong: external plugins (bws-portal-system) register 18 context-modifier alias entries carrying a `callback` key. Corrected buckets today (per V9 classifier): **Deprecated Tags = the 18 external aliases** (callback present, no `prefix_removed`); **Removed Tags = our 108 N×M entries** (callback stripped by FW-1, no `prefix_removed`). FW-1's "108 → Removed" holds — the callback-presence default keeps them there; the 18 externals were the only thing the original premise missed. `prefix_removed` is the new override an external sets to move a retired alias generation from Deprecated to Removed.
2. Deprecated Options (11 entries) UNCHANGED in behavior — always-live option-key corrections on current tags, never GB-registered, no K/S/D concept ever applied here. Cite V1.
3. **CORRECTED by B1 / superseded by V9.** Original: tag liveness = `callback`-presence. That proxy broke when FW-1 deleted the GB dispatch loop — `callback` presence no longer implies the tag renders. Corrected: tag liveness is a hand-set `prefix_removed` flag (default absent = live/Deprecated; true = Removed), never the callback test, never derived from GB runtime state. Options keep their own hand-set flag (`legacy_fallback_removed`, V2). Both axes stay HAND-SET, never inferred.
4. K/S/D (`mode_with_path`/`mode_without_path`) stays TWO independent radios — no collapse into one control (rejected in grill: collapsing forces a settings-schema migration for zero benefit, user confirmed "not a major concern").
5. Migration Tool box always visible regardless of any bucket's emptiness (user-confirmed, cite V5).
6. Removed boxes (tags + options) are informational only — zero interactive controls (checkboxes/radios), list + counts + link to Migration Tool only.
7. Empty-hides-everything cascades: an empty with-path/without-path K/S/D subgroup hides its whole block (heading+description+radio), not just its disclosure link. Same rule for Removed's tag-list/option-list sub-sections. If ALL of Deprecated Tags + Removed Tags + Deprecated Options + Removed Options are empty, the whole 4-box group disappears (Migration Tool still shows, C5).
8. Hide-on-scan allowlist rebuilds on: plugin activation, plugin upgrade, AND the existing "Scan All Content" button (all three, not just activation/upgrade — user confirmed the button already re-triggers `TagConverter::scan()`, cheap to hook the same pass).
9. Allowlist must also update after a migration runs (a migrated post's old tag/option no longer matches — correct for it to drop off next scan, not a bug).
10. "Show all" toggle lives in the Diagnostics box, bypasses the hide filter, lists everything unconditionally (today's default behavior). Stays indefinitely — no fixed removal date, user decides later.
11. Option hide-condition mirrors `MigrationRegistry::find_option_migration()`'s real match semantics — tag name AND declared option key(s) (`match_options`/`match_any_options`) both present in scanned content, not a name-only check.

## §I — surfaces

- I.settings = `includes/classes/admin/class-settings-page.php` — `render_page()` (box markup/order), `sanitize_settings()` (unchanged schema, K/S/D stays 2 keys), new allowlist read/render helpers.
- I.registry = `includes/classes/class-migration-registry.php` — `register()` gains BOTH liveness flags: option-side `legacy_fallback_removed` (V2) and tag-side `prefix_removed` (V9); `get_by_type()`/`get_by_type_and_liveness()` liveness-filter accessor; `is_entry_live()` reads flag-only per type (V9), NOT callback presence.
- I.integration = `docs/plugin-integration.md` — external-plugin guide; documents the `prefix_removed` flag + the authoritative-status/alias model (V10) so an integrating plugin knows how to declare a retired alias generation.
- I.handoff = portal-system change-request doc (separate deliverable) — tells bws-portal-system how to set `prefix_removed` on its retired generation(s); no code change to portal-system on this branch.
- I.converter = `includes/classes/admin/class-tag-converter.php` — `scan()` (existing) becomes the allowlist-rebuild source; hook activation/upgrade to call it or an equivalent lighter pass.
- I.activation = plugin activation/upgrade hook (file TBD at build time — wherever `register_activation_hook`/version-bump check currently lives).
- I.diagnostics = `class-settings-page.php` Diagnostics box render block (~line 801) — add "show all" checkbox.

## §V — invariants

- V1. **Deprecated Options entries never gain a removed/inert state from GB-registration logic** — they never had GB registration to lose (V3 of prior spec: option-type entries carry no `callback`/`title`/GB fields at all). Cite I.registry.
- V2. **An option-type entry is "removed" only via an explicit hand-set flag**, set when the runtime's legacy-key fallback (e.g. `$options['old_key'] ?? $options['new_key']`, the B2/`key`↔`rel` shape) is deleted from the reading code — never inferred from scan results or registry cross-reference. Cite I.registry.
- V3. **SUPERSEDED by V9** (was: tag-type liveness = `callback`-presence). Retained for numbering; do not implement. See B1 for why the callback proxy is wrong.
- V4. **K/S/D radios (`mode_with_path`/`mode_without_path`) stay independently settable** — no schema change, no value migration needed for existing installs' saved settings.
- V5. **Migration Tool box has no hide condition** — always renders regardless of every other box's item count.
- V6. **Empty-cascade is structural, not per-row**: a bucket (K/S/D subgroup, or a Removed tag-list/option-list) with zero entries after the hide-filter hides its entire heading+description+control block, not just an empty disclosure.
- V7. **Allowlist is a positive list** (entries with ≥1 real content match), not a denylist of hidden ids — default-empty hides everything until a scan populates it. Rebuilds on activation, upgrade, "Scan All Content", and after any migration completes.
- V8. **"Show all" toggle bypasses the allowlist filter entirely** when on — renders every registered entry (tags + options, both buckets) same as pre-FW-36 behavior. Toggle state itself is NOT reset by any scan.
- V9. **Tag-type liveness = `prefix_removed` override on top of the existing callback default** (replaces V3; Path X). `is_entry_live()` for a tag entry returns `false` (Removed) iff `prefix_removed === true`; ELSE it falls back to the existing default test (callback presence). Why not flag-only: the two tag populations have OPPOSITE natural defaults — our own 108 N×M entries are Removed (FW-1 stripped their `callback`; no `prefix_removed`) and the 18 external aliases are Deprecated (carry `callback`; no `prefix_removed`). A single flag with one global default splits them wrong (a flag-only rule with default=live wrongly pulls all 108 back to Deprecated, undoing FW-1). Callback-presence is TODAY the de-facto "internal-removed vs external-still-registered" marker — reused, not enshrined. `prefix_removed` adds the missing retirement axis so an external can push a specific alias generation to Removed. Never derived from GB runtime (`get_tags()`) — load-order timing + "toggle changes groups" problem. Cite I.registry, I.settings. **Superseded on ship of V11 (Path Y).**
- V11. **FUTURE (Path Y, tracked FW-38, NOT this release):** replace the callback-as-proxy with two explicit hand-set fields recorded at `register()` time — `registered_by` (source: internal vs external plugin id) and `lifecycle` (`unset=active` | `deprecated` | `removed`). Then box placement reads `lifecycle` only, callback is irrelevant to classification, and internal-removed entries carry an explicit `removed` marker instead of relying on absent-callback. Feeds the portal-system coordination (external sets its own `registered_by`+`lifecycle`). Path X (V9) is the interim; V11 makes the two-population default principled. Cite I.registry, I.integration, I.handoff.
- V10. **External context-modifier aliases take their authoritative status from THIS plugin, and declare their own prefix retirement.** An external plugin's alias (e.g. portal-system `portal_title → view_title`) is a modifier over a tag THIS plugin owns; its target's live/removed status is authoritative here. The alias additionally owns a hand-set `prefix_removed` flag it sets when it retires that prefix generation — the two axes: target-removed OR prefix-removed ⇒ alias in Removed. Today all external targets (`view_*`) are live and no external prefix is flagged removed, so all 18 aliases sit in Deprecated. Rebuilding these aliases inside the external plugin is rejected — they are modifiers of our tags, not standalone tags the external plugin owns (would only revisit if base tags become a drop-in module). Cite I.integration, I.handoff.

## §T — tasks

| id | st | task | cites |
|----|----|------|-------|
| T1 | x | Add hand-set liveness flag to option-type `register()` args (e.g. `legacy_fallback_removed`); default false | V2,I.registry |
| T2 | x | Add liveness-filter accessor(s) to MigrationRegistry (tag: `callback` presence; option: new flag) — used by settings render | V2,V3,I.registry |
| T3 | x | Split settings page's tag rendering into Deprecated Tags box (liveness=true, K/S/D radios, empty today) + Removed Tags box (liveness=false, list-only, all 108 today; keeps with-path/without-path as 2 informational sub-lists, no control) | V3,V6,I.settings |
| T13 | x | Path X: `is_entry_live()` tag branch returns `false` iff `prefix_removed===true`, ELSE existing callback default. Preserves 108→Removed + 18→Deprecated. Add `prefix_removed` to `register()` @param docs (tag-type) + document callback-as-interim-marker. | B1,V9,I.registry |
| T14 | . | Document `prefix_removed` + authoritative-status/alias model in `plugin-integration.md` (V10); write portal-system handoff doc (set `prefix_removed` on retired generation). | V10,I.integration,I.handoff |
| T15 | . | Verify (live-WP): 18 external aliases in Deprecated Tags, 108 in Removed Tags. NOTE: the `prefix_removed`→moves-to-Removed leg is NOT testable today (no live entry sets the flag until the portal-system handoff lands); defer that leg to when portal-system applies T14's handoff. | V9,V10 |
| T16 | x | Add `docs/future-work.md` FW-38 row: Path Y (explicit `registered_by` + `lifecycle` fields replace callback proxy). NOT this release. | V11 |
| T4 | x | Split Deprecated Options box into Deprecated Options (flag=false, unchanged behavior) + Removed Options (flag=true, list-only, empty today) | V1,V2,V6,I.settings |
| T5 | x | Reposition the 4-box group + Migration Tool as one unit above Diagnostics | C-repositioning,I.settings |
| T6 | x | Build allowlist storage (rebuild source = `TagConverter::scan()` result set of matched names) | V7,I.converter |
| T7 | x | Hook allowlist rebuild: activation, upgrade, "Scan All Content" button, post-migration | V7,I.activation,I.converter |
| T8 | x | Apply allowlist filter to all 4 boxes' entry lists at render time | V6,V7,I.settings |
| T9 | x | Add "show all" checkbox to Diagnostics box; wires bypass in render | V8,I.diagnostics |
| T10 | . | Verify: fresh install / first scan shows expected buckets. **CORRECTED per B1/V9**: Removed Options empty; Deprecated Options=11; Deprecated Tags = 18 external aliases (callback, no prefix_removed); Removed Tags = our 108 N×M entries (no callback). | V9,V1 |
| T11 | . | Verify: migrate a post, confirm its tag/option drops off allowlist on next scan | V7,V9 |
| T12 | . | Verify: "show all" on shows all entries in all 4 boxes regardless of allowlist state | V8 |

## §B — bugs

| id | date | cause | fix |
|----|------|-------|-----|
| B1 | 2026-07-08 | Tag-liveness used `callback`-presence as a proxy for "GB-registered/renders" (§V3). FW-1 deleted the GB dispatch loop, so a present `callback` no longer implies rendering. External aliases (portal-system, 18 entries) still carry `callback` → misclassified as live/Deprecated in a box offering a no-op K/S/D control, while they don't actually render. SPEC §C1/§V3's "all 108 inert → box empty" premise was also wrong (didn't account for external callback-bearing entries). | Replace callback test with hand-set `prefix_removed` flag (V9); add authoritative-status/alias model (V10). Reported by user observation of the live settings page. |
