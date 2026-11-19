# Archive: the deprecation-mode radio — FW-104 (RETIRED, cut 2026-11-19)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

Detail home for FW-104 in `docs/future-work.md`. Moved from GH #110 (closed 2026-10-12, not a user-reachable defect). Status: kept, not started; reviewed when the next deprecation window opens.

## Decision (2026-10-12)

The Keep / Suppress / Disable modes are **kept for future deprecation windows**. They are the same class of affordance as FW-153's editor warning: meant to be reused the next time a tag family goes through a deprecation window, the likely first being the two datetime tags if FW-81 collapses them. Deleting the accessors is off the table; wiring the radio for `try_` alone is not the goal either. Whether they are useful as built needs review at that point.

## The dead half (verified 2026-10-12)

The settings page radio stores a value and reads it back into its own form. Nothing else consults it.

- `SettingsPage::is_deprecated_tag_registered()` and `is_deprecated_tag_suppressed()` (`includes/classes/admin/class-settings-page.php`, `@since 1.6.0`) have zero callers.
- `get_deprecated_mode()` is called only by those two.
- The 1.0.0 `is_deprecated_tag_enabled()` wrapper (`@deprecated 1.6.0`, over `is_deprecated_tag_registered()`) is uncalled too.

Repro, no fixture: set the radio to Disable, reload the editor, and the deprecated tags still register and render.

## How it is built

- **Per GROUP, not per tag.** `get_deprecated_mode()` picks `mode_with_path` or `mode_without_path` by `MigrationRegistry::has_migration_path( $tag_name )`. Every tag with a migration path shares one setting, and registering a migration entry silently enrolls its tags in that pool. Nothing at the `bws_register_modifier_root_migrations()` call site says so.
- **The seed.** `bws_dynamic_tags_activate()` writes `disable` to both groups on fresh installs (since 1.6.1), deliberately, so legacy N×M tags were removed from GB out of the box; existing installs were left untouched. The settings read falls back to `keep`, so only seeded sites carry `disable`, which is most of them.

## The trap (unreachable since 1.21.0)

Per-group mode + seeded `disable` meant wiring the radio would take every externally-registered family in the pool dark on upgrade, with no conversion run and no warning. Since 1.17.0 (#86) `bws_register_modifier_root_migrations()` let an external plugin enroll a live family; the one known integrating plugin did with its nine tags, removed 2026-09-08. The trap was a property of the registration API, not of that integrator.

FW-129 withdrew `register_modifier()` in 1.21.0 and deleted it after, so no external plugin can enroll a live family any more. `term_`'s seed entry went with its family, leaving `try_` as the only member of either pool. Re-widens if a future release re-opens a minting route, which nothing plans.

## Directions filed against the trap (2026-09)

1. Do not let the group mode reach externally-registered entries; the group radio governs only this plugin's own retired tags (an external family already retires via `prefix_removed => true`).
2. Per-family or per-entry mode instead of the two-group split.
3. Never auto-disable on upgrade: a stored `disable` applies only to entries present when it was saved.
4. Remove the dead accessors. **Rejected 2026-10-12** (see Decision).

With the trap gone, only 2 may still matter, and only if a window needs it.

## Open

- Does the radio as built (per-GROUP mode, the seeded `disable`, the `keep` read fallback) fit the next deprecation window, or does it need reshaping first?
- Whichever lands, `docs/plugin-integration.md` §9 must state what registering an entry enrolls tags in; today it reads as purely additive.
