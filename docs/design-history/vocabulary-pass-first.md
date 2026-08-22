# Archive: the plain-English vocabulary pass, first cut (SHIPPED 2026-08-22)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `CONTEXT.md` §Language (every term this pass settled), `docs/tag-reference.md`, or the PHPDoc at
> the enforcing site.

**Lifted 2026-08-22** under `CLAUDE.md` §Spec lifecycle, "a phase ships, plan continues": the first
cut shipped, five items stayed open. The live plan keeps those and stays private
(`.scratch/plans/vocabulary-pass.md`); their visible surface is **FW-93 / FW-94 / FW-95** in
`docs/future-work.md`. Nothing here is a to-do.

---

## Why the pass happened

The `entries` → `rows` question opened into a wider one. The user is learning rather than an
experienced programmer and is the primary reader of this vocabulary, so a name that costs a lookup
every time is a real tax on their own decisions — not a style preference. Two failure modes got
named:

- **situational shorthand accidentally enshrined** — `mode 2a` / `mode 2b`, coined in a 2026-05 plan
  whose Mode 1 stopped existing, then carried for three months into code comments, matrix headings
  and a visible fixture section title;
- **unfamiliar terms of art** — "static analysis", where "static" reads as the opposite of "dynamic"
  to anyone whose reference point is static-vs-dynamic website backends.

## The rules it decided

Binding forward, and the reason each survived the pass rather than being applied once:

1. **Plain English over terms of art.** Pick the word a careful non-specialist reads correctly on
   first encounter. Keep a term of art only where it is load-bearing AND defined at its owning site.
2. **American spellings** for every new name or rename (`-ize`, `-or`). No sweep of existing mixed
   prose.
3. **Never enshrine shorthand coined for one situation.** If it is worth reusing, give it a real name.
4. **Surface the trade** when a plainer word costs an established idiom; the user decides.

Rules 2 and 3 became standing preferences at the time, outside this plan's life.

## What it renamed

| Was | Became | Enforcing site as built |
|---|---|---|
| chain kind `base` | `render_time` | `bws_fold_chain_resolution()` in `slot-fold-compile.php` |
| "static" (the ANALYSIS sense only) | **parse-time**; `BWS_FOLD_STATIC_ROOT_KINDS` → `BWS_FOLD_PARSE_TIME_ROOT_KINDS` | same |
| `''` chain kind | **unrecognized** in prose; the VALUE was deliberately left unchanged | same |
| step slug `entries` | `rows` | `BWS_FOLD_STEP_TYPES` / `_KINDS`, `BWS_TRAVERSAL_STEP_INPUT_KINDS` |
| `mode 2a` / `mode 2b` | the query loop's **post** / the query loop's **repeater row** | `CONTEXT.md` §Language, **Query-loop item** |
| (undefined, in use everywhere) | **ambient** — given a definition rather than a rename | `CONTEXT.md` §Language |

Two usage rules landed with the glossary entry: **"loop" alone always means GB's query loop** (our
own iteration over a fan is never called one), and **"item" is the umbrella** — once the loop is the
subject, name the shape alone rather than repeating the umbrella word.

"Static" was retired only in the analysis sense. `presetKind`'s "static map" in `CONTEXT.md` is the
fixed-table sense and was left alone on purpose; only one of the two senses moved.

## The `entries` → `rows` reversal, and why the history is the point

This is the part most at risk of being re-derived backwards, which is why it is recorded at length.

**A 2026-08-19 lean had decided the opposite** — keep `entries` for the step, on the reasoning that
a slug is the harder thing to move, because wire is hand-editable by decision (ADR 0004) and a
rename is therefore a migration. **That premise was false.** `entries` was on NO step offer: both
authoring surfaces pass `['refs','terms']`, with a comment at each site saying why. No editor ever
wrote the token; only a hand edit could. Nothing in the wild carried it and nothing migrated. The
competing sense — the 1.17.0 field-configuration note's `single-entry` / `multiple-entry` /
"can add entries", for what a relationship or post-object field holds — was genuinely shipped
user-facing copy. The cheap side and the dear side were the reverse of what the lean assumed.

**The plan text that agreed with the outcome was STALE, not authoritative.** `.scratch/plans/table-tag.md`
had said "`rows` = new fold step" since it was written, but during prototyping (2026-07-24) the user
decided to rename it to `entries`, and that decision **was implemented**. The reason was sound at the
time: src options were not folded yet, so a step needed a top-level option token (`rows:field`), and
burning `rows` as an option name would have cost `{{table}}` a word it might need. The plan simply
never got updated.

The 2026-08-22 reversal is a NEW decision on changed facts: with the src path folded, a step slug no
longer claims a top-level option name, so `rows` is free and `entries` can go back to the
relationship-field sense. The stale plan text is correct again by coincidence.

**This is what amended `CLAUDE.md` §Documentation ownership on the same day.** Read off the artifacts
alone, the situation looked exactly like code drifting from an authoritative doc, and the old
"the owner doc wins" rule would have produced the right edit for entirely the wrong reason. It was
the other scenario — a deliberate decision the doc never caught up with — and the rule would have
been wrong in principle at any point between the prototype and that week. Two opposite histories
leave identical evidence in the tree, so drift is now surfaced for a human decision rather than
resolved on a default, with the doc as the presumption and not the licence.

## The no-CHANGELOG decision

The step-slug rename shipped with **no CHANGELOG entry and no version bump**, deliberately.

The reason that holds is that **nothing shipped consumes a repeater row.** The only tag that turns
rows into output is `{{table}}`, whose registration is gated behind
`apply_filters( 'bws_dynamic_tags_register_table_tag', false )` — off on every install but the
fixture testbed. Everywhere else the step resolves to a `meta_row` no arm assembles (FW-74). Stored
`entries` wire produced no output before the rename and produces none after it, so the net delta from
the last shipped release is zero, which is the CHANGELOG's own test.

**A weaker reason was written first and does not hold:** that the slug was on no step offer, so only
a hand edit could have written it. ADR 0004 makes hand-edited wire a first-class authoring route, so
that argument argues against itself. Both are recorded in `docs/deprecated-tags-options.md` with the
weak one marked, because the weak one is the one a future reader would reach for.

## What the two-axis review caught

The pass was reviewed against `main` on 2026-08-22, Standards and Spec axes in parallel, after the
work was believed finished. Twelve findings survived. The two worth carrying forward as lessons:

- **A rename sweep cannot lean on a green suite.** `try-slot-arms-test.php` §A4.6 had its LABEL
  renamed to `render_time` and its ARGUMENT left as `'base'`, so the branch's headline rename was
  pinned by nothing — the assertion had quietly become a duplicate of the unknown-string fallback
  beneath it. Same shape as three `'kind' => 'base'` literals found mid-branch. Both halves of a
  kind→fallback pair pass whether or not the kind is spelled right.
- **A degraded fixture is invisible.** `slot-fold-corpus.json` kept `"slug": "entries"`, and the twin
  test kept passing because the grammar treats slugs opaquely — the row had stopped exercising a real
  step and started exercising unknown vocabulary, with no failure to show for it.

The review also found the enforcing site naming two poles where the new glossary insists on three
(`bws_fold_chain_resolution()` still said "Static analysis cannot say which" and called `''`
"honestly unknown"), which is the failure mode of renaming in the glossary alone.

---

**Shipped on branch `refactor/vocabulary-pass`, 11 commits.** Suite green throughout the fixes
(24 PHP + 2 JS harnesses); testbed reseeded and verified 58/58, front end curled after a container
restart to clear the bytecode cache.
