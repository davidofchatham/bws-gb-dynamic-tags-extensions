# Archive: FW-3's first piece, datetime term-ambient parity (SHIPPED 1.15.0)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site (`bws_datetime_coerce_read_target()` in `includes/helpers/datetime-helpers.php`).

**Provenance.** Lifted 2026-09-23 from FW-3's live plan, where it had sat as the "Shipped record" section since the piece shipped in 1.15.0 (built on `feat/fw3-datetime-seam`, folded into 1.15.0 by user decision). FW-3 itself stays open, so the rest of the plan stays private; `docs/spec-lifecycle.md` §A plan commits when it is finished, not when it ships lifts a shipped phase out on its own. The section is copied as it stood. "The legacy scalar callers listed above" refers to the plan's §Live constraints list, which stayed behind. The stage-by-stage build notes had already been condensed away before the lift, so this paragraph is all that survives of them; `tools/test/datetime-test-matrix.md` §D7 holds the rows that flipped. The piece shipped second, the list-collection payload, has its own record in `docs/design-history/traversal-convergence-fw49.md`.

---

## Shipped record — term-ambient parity (1.15.0)

The contradiction FW-3 was opened against: `bws_parse_combined_date_time()` overloaded its first arg three ways (int post id, `'option'` sentinel, `"{taxonomy}_{term_id}"` string), and the cheap route to term-ambient parity would have been to push a fourth `"{tax}_{id}"` through it. Instead the cores were rethreaded to take the resolved source the factory already emits (ADR 0002 — a payload shape, not a formal value object), branching on kind rather than sniffing the string shape of `$post_id`; `bws_datetime_coerce_read_target()` absorbs the legacy scalar callers listed above. The behavior delta: a bare `{{datetime_single key:…}}` on a taxonomy archive reads the term's date field, where it rendered honest-empty before (`docs/design-history/fixture-testbed.md` §FW-3/4/5/7/8 zones records the pre-written acceptance assertion).
