# Serialized tag strings are human-readable and author-hand-editable

**Status:** accepted (2026-07-28; surfaced during the FW-56 src-chain encoding grill, `.claude/plans/src-chain-encoding.md`).

The serialized tag string (`{{tag src:… | key:… | …}}`) is a **source of truth the author reads and
may hand-edit directly**, not a control-private encoding. Every option-value grammar we design must
therefore stay legible to a hand-editor and round-trip a hand-edit back into control state — controls
are a convenience layer OVER the wire, never its sole decoder. This has been misread more than once
(most recently the FW-56 DECISION 2 draft claimed the chain value could be "opaque on the wire"
because a custom control owned it), so it is recorded here as a standing constraint rather than
re-derived per feature.

## Considered options

- **Wire is human-readable and hand-editable (accepted).** The author sees and may edit the raw
  `src:…`/option string. Costs paid deliberately: every value grammar must read clearly to a human
  segmenting steps/args by eye, and the parser must accept an author's hand-edit (not only re-parse
  its own control output). Benefits: author autonomy + debuggability; a tag is fully decodable without
  its editor control existing (no lock-in to a control to understand a stored tag); GB's own native
  tag strings already set this expectation.
- **Wire is a compact control-private blob, control owns (de)serialization (rejected).** Tempting for
  a multi-step chain: shorter wire, no hand-edit round-trip parser, no readability constraint on
  separators. Rejected because it makes the control mandatory to decode a stored tag, breaks the
  author's ability to read/fix a tag by hand, and diverges from how every other tag string in the
  plugin already reads.

**Note — the safe-char restriction is NOT a cost of this decision.** A value grammar is confined to
`gb-constraints.md` serialization-safe chars regardless of readability: it is a GB-interop hard
constraint (GB's own `find_matches()` / pair-split / KV-split parsers must round-trip the string).
That restriction binds even the rejected opaque-blob option. It is listed under Consequences below as
a standing constraint, not as a price paid for readability.

## Consequences

- Separator / grammar choices for any option value (the FW-56 chain, FW-24 tag-in-slot, future
  options) are constrained by hand-editor legibility, not only by parseability. The FW-56 parens-vs-
  flat-comma and step-separator debates live downstream of THIS decision — the plan owns the specific
  grammar, this ADR owns why readability weighs on it at all.
- Any control that builds an option value must ALSO parse an author's hand-edited value back into its
  state on load — the wire, not the control's last-known state, is authoritative on reopen and after
  a manual edit. (FW-56 DECISION 2 is corrected to say exactly this.)
- A value grammar may not depend on a char outside the `gb-constraints.md` safe set — a GB-interop
  constraint that holds independent of this ADR. What THIS decision ADDS on top: within that safe set,
  the grammar must also stay legible to a hand-editor and must not require a mechanism
  (nesting-balance, escaping) an ordinary hand-editor is likely to break silently.
- **Corollary (2026-08-13) — internal hand-offs use the wire too, not a parsed structure.** Because
  the wire is the canonical language rather than a serialization of some truer internal form, a
  component that resolves a source and hands it to another passes CHAIN WIRE, not a parsed chain in a
  side key. A side key is a second way to state a source, invisible to every reader that consults
  `src` for another purpose. Considered as its own ADR and rejected as a corollary of this one — it
  is not an independent trade-off. See `CONTEXT.md` I16.
