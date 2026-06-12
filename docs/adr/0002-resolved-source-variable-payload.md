# L1 resolves to a variable-payload resolved source, not a typed `{kind, id}` entity

**Status:** accepted (grill 2026-06-12; pre-build, hardening `.claude/plans/try-email-phone-and-slot-derivation.md`).

The shared source-resolution pipeline's L1 layer resolves a tag's source options to a **resolved source** — a bound *where* a read happens — whose payload **varies by kind** (and by read mechanism within a kind), rather than a uniform `{kind, id: int}` shape. post/term resolved sources carry an integer id; `site` carries the `wp_options` namespace (or the ACF `'option'` object-id for datetime option-fields); future kinds (#19 date-archive/search/404/home, a possible external Site-Views option-set source) carry their own payload. See CONTEXT.md §Language (resolved source) + §L1/L2/L3.

## Considered options

- **`{kind, id}` (typed entity, rejected).** Model L1 output as an entity with a kind tag and an integer id. **Rejected** because several real and near-term kinds have no integer id: `src:site` (a namespace, not a thing), and #19's date-archive (a date span), search (a query string), 404/home (option-driven static). Forcing them into `{kind, id}` requires a sentinel/null id and special-casing at every consumer — re-creating the exact `if ( 'site' === $src )` special-casing scattered across the codebase today (email-tags.php, phone-tags.php, base-tags.php) that the unification was meant to remove.
- **Variable-payload resolved source (accepted).** Each kind carries what its read needs. `site` is not "an entity missing an id" — it is a different read mechanism. This is the "Frame B" outcome of the grill.

## Consequences

- L2b (fetch value) dispatches on resolved-source kind/payload — post/term → meta, site → option or ACF-`'option'`. This dispatch is the model's single home for the per-kind read mechanism; consumers (text/email/phone/datetime/join/try_) stop special-casing source.
- #19 (context-aware resolution) is **not a new mechanism** — it grows L1 with more resolved-source kinds whose source is implicit (recovered from WP context). The contract does not change to absorb it. This is what makes "ship the L1 seam now with post/term/site kinds, grow #19's kinds incrementally" a safe build order.
- The `target cardinality` model (resolved source is a list, plural for `ref`/`srcTermIn`) is orthogonal to and compatible with variable payload.
