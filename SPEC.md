# SPEC — No active spec. See CHANGELOG, docs/tag-reference.md, Issues.

Smart Field Selector shipped in 1.13.0 (PR #42). Post-ship homes:
- **Cross-cutting invariant:** `CONTEXT.md` §I8 (field discovery projects L1's resolved-source KIND to editor time).
- **Load-bearing §V → PHPDoc:** `assets/js/field-combo-control.js` + `includes/rest/field-discovery.php`.
- **Schema:** `docs/tag-reference.md` §Custom control types + the flipped option rows.
- **Design + v2/v3/follow-ups (FU-1/FU-2/FU-3):** `.claude/plans/field-selector.md`.
- **Regression guards:** `tools/test/field-discovery-test.php` (pure logic) + `tools/test/field-selector-test-matrix.md` (manual).

Shipped-fix bugs (B1-B8) are captured by the invariants they produced (now in code PHPDoc + CONTEXT.md §I8) and the test guards above; no open issues outstanding.
