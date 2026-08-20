# A separator char is accepted only where the grammar already allocates it

**Status:** accepted (2026-07-31 WIRE SPEC approval, amended 2026-08-01 by the approved-twins rule;
both from the FW-56/57 encoding grill, `docs/design-history/src-chain-encoding.md` §WIRE SPEC).

The folded slot-value grammar parses **leniently and emits canonically**: an author may hand-type a
functionally-equivalent separator or the other bracket pair, and the next control commit rewrites it
to the canonical spelling. That leniency is what makes ADR 0004's hand-editable wire survivable in
practice — a hand-edit that picked the wrong twin still round-trips instead of erroring.

**A char may enter an accept class ONLY if the schema already allocates it a role somewhere.** A char
with no sanctioned role, or one that was considered and rejected, stays out — even where accepting it
would parse unambiguously.

## Why, and it is not hypothetical

Accepting an unallocated char silently **claims** it. Stored wire starts containing that char with a
meaning, and the day the grammar wants it for a new axis, it cannot be taken: the change would
redefine a char already live in saved tags, in wire no migration is guaranteed to reach.

That is exactly what nearly happened to `/`. It was rejected as the canonical hop char, then kept in
the lenient hop class anyway — where it would have accumulated in saved wire meaning "hop". The very
next design on the table, per-step `limit`, wanted `/` for its own spelling. The lenient class had
quietly pre-empted a char the grammar had never allocated.

## What this does not reopen

Leniency itself stands, and every twin whose char **is** allocated stays accepted — `,` at slot top
level, either bracket pair at either depth, both `0` and `-1` for unlimited. The rule cuts only
unallocated chars. `+` and `/` are the current reserved pair, and inside a value they are ordinary
content, never grammar.

## Where this is enforced, and what the enforcement does not cover

`bws_fold_grammar_validate()` in `includes/helpers/slot-fold.php` machine-checks the separator classes
on every run of the grammar harness; **its own PHPDoc states what it decides and how far it
generalizes.** `assets/js/slot-fold-grammar.js` is its twin, checked against it by
`slot-fold-twin-test.php` rather than assumed to agree.

**What it cannot cover is THIS ADR's axis.** It compares accept classes against `BWS_FOLD_RESERVED`;
it cannot know that a char absent from both the reserved list and every allocated role has no business
being accepted. **Adding a char to an accept class therefore means first deciding whether the grammar
allocates it** — that judgement is this ADR's, and no test makes it for you.

## Considered and rejected

- **`/` as the canonical hop char.** Rejected on legibility against `;`, which reads as a separator
  rather than a path. Retaining it leniently after rejecting it canonically is the specific mistake
  this ADR exists to prevent.
- **`=` for the bracket-kv token.** Rejected: `name(value)` nests without escaping and survives GB's
  own `:`/`|` option grammar, where `name=value` invites a second splitter at the same level.
- **Strict parsing, no accept classes at all.** Rejected as a direct cost to ADR 0004 — it makes every
  hand-edit that guesses the wrong twin an error rather than a normalization.
