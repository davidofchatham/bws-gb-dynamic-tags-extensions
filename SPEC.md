# SPEC — No active spec. See CHANGELOG, docs/tag-reference.md, Issues.

Traversal Pipeline Phase 1 shipped (1.14.0). Post-ship homes:
- Cross-cutting invariants (factory precedence, term-ambient, ACF-compatible ref) → `CONTEXT.md` §I9.
- Single-class invariants → PHPDoc on the enforcing methods (`bws_resolve_base_source`, `bws_capture_ambient_signals`, `bws_run_traversal`, `bws_read_resolved_source`, `bws_pipeline_default_reader`, `bws_base_term_analog_read`, `bws_term_custom_image_core`, the text/title callbacks, the wrapper).
- Schemas → `docs/tag-reference.md`. Rationale + probe → `.claude/plans/archive/traversal-pipeline.md`.
- Bugs B1–B8 closed in-release, each captured by the §V it drove (now PHPDoc/CONTEXT). See CHANGELOG 1.14.0.
- Deferred follow-ups (try_ fork collapse, src:site in more try_ tags, datetime seam, Phase 2 context kinds) → `docs/future-work.md`.
