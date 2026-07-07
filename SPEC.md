# SPEC — Deprecated/Removed settings split + scan-hide (1.14.0, in-flight)

Branch `deprecated-tag-removal`. Lands before merge to main (FW-1 done, this = FW-36 follow-on). §G/§C live here — no separate architecture doc for this feature.

## §G — goal

Split settings page into 4 boxes (Deprecated Tags / Removed Tags / Deprecated Options / Removed Options) on a live/inert axis, reposition above Diagnostics, add scan-driven hide-when-unused with a permanent "show all" escape hatch.

## §C — constraints

1. Deprecated Tags box stays wired but renders empty today — ALL 108 tag-type entries are inert post-FW-1 (V1/V7 of prior spec, now PHPDoc). No entry qualifies as live yet; box is a placeholder for a future round of tags that keep GB registration.
2. Deprecated Options (11 entries) UNCHANGED in behavior — always-live option-key corrections on current tags, never GB-registered, no K/S/D concept ever applied here. Cite V1.
3. Liveness is a HAND-SET fact per entry, never derived/inferred. Tags: presence of a `callback` key (existing mechanism). Options: new explicit flag, entry is "removed" only when the runtime's legacy-key fallback code is itself deleted (cite V2).
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
- I.registry = `includes/classes/class-migration-registry.php` — `register()` gains the option-side liveness flag; `get_by_type()`/new liveness-filter accessor.
- I.converter = `includes/classes/admin/class-tag-converter.php` — `scan()` (existing) becomes the allowlist-rebuild source; hook activation/upgrade to call it or an equivalent lighter pass.
- I.activation = plugin activation/upgrade hook (file TBD at build time — wherever `register_activation_hook`/version-bump check currently lives).
- I.diagnostics = `class-settings-page.php` Diagnostics box render block (~line 801) — add "show all" checkbox.

## §V — invariants

- V1. **Deprecated Options entries never gain a removed/inert state from GB-registration logic** — they never had GB registration to lose (V3 of prior spec: option-type entries carry no `callback`/`title`/GB fields at all). Cite I.registry.
- V2. **An option-type entry is "removed" only via an explicit hand-set flag**, set when the runtime's legacy-key fallback (e.g. `$options['old_key'] ?? $options['new_key']`, the B2/`key`↔`rel` shape) is deleted from the reading code — never inferred from scan results or registry cross-reference. Cite I.registry.
- V3. **Tag-type liveness stays the existing test**: entry has a `callback` key → Deprecated Tags box; no `callback` key → Removed Tags box. All 108 current entries lack `callback` (prior spec V3/V7) → all land in Removed Tags today.
- V4. **K/S/D radios (`mode_with_path`/`mode_without_path`) stay independently settable** — no schema change, no value migration needed for existing installs' saved settings.
- V5. **Migration Tool box has no hide condition** — always renders regardless of every other box's item count.
- V6. **Empty-cascade is structural, not per-row**: a bucket (K/S/D subgroup, or a Removed tag-list/option-list) with zero entries after the hide-filter hides its entire heading+description+control block, not just an empty disclosure.
- V7. **Allowlist is a positive list** (entries with ≥1 real content match), not a denylist of hidden ids — default-empty hides everything until a scan populates it. Rebuilds on activation, upgrade, "Scan All Content", and after any migration completes.
- V8. **"Show all" toggle bypasses the allowlist filter entirely** when on — renders every registered entry (tags + options, both buckets) same as pre-FW-36 behavior. Toggle state itself is NOT reset by any scan.

## §T — tasks

| id | st | task | cites |
|----|----|------|-------|
| T1 | x | Add hand-set liveness flag to option-type `register()` args (e.g. `legacy_fallback_removed`); default false | V2,I.registry |
| T2 | x | Add liveness-filter accessor(s) to MigrationRegistry (tag: `callback` presence; option: new flag) — used by settings render | V2,V3,I.registry |
| T3 | x | Split settings page's tag rendering into Deprecated Tags box (liveness=true, K/S/D radios, empty today) + Removed Tags box (liveness=false, list-only, all 108 today; keeps with-path/without-path as 2 informational sub-lists, no control) | V3,V6,I.settings |
| T4 | x | Split Deprecated Options box into Deprecated Options (flag=false, unchanged behavior) + Removed Options (flag=true, list-only, empty today) | V1,V2,V6,I.settings |
| T5 | x | Reposition the 4-box group + Migration Tool as one unit above Diagnostics | C-repositioning,I.settings |
| T6 | x | Build allowlist storage (rebuild source = `TagConverter::scan()` result set of matched names) | V7,I.converter |
| T7 | . | Hook allowlist rebuild: activation, upgrade, "Scan All Content" button, post-migration | V7,I.activation,I.converter |
| T8 | . | Apply allowlist filter to all 4 boxes' entry lists at render time | V6,V7,I.settings |
| T9 | . | Add "show all" checkbox to Diagnostics box; wires bypass in render | V8,I.diagnostics |
| T10 | . | Verify: fresh install / first scan shows expected buckets (Deprecated Tags+Removed Options empty, Removed Tags=108, Deprecated Options=11) | V3,V1 |
| T11 | . | Verify: migrate a post, confirm its tag/option drops off allowlist on next scan | V7,V9 |
| T12 | . | Verify: "show all" on shows all entries in all 4 boxes regardless of allowlist state | V8 |

## §B — bugs

| id | date | cause | fix |
|----|------|-------|-----|
