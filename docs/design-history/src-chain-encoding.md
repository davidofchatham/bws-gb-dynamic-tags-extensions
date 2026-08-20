# Multi-step src-selection encoding + authoring model — FW-56

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

Tracker: `docs/future-work.md` FW-56. This is the detail
home the tracker points at. Promoted from implicit to load-bearing by the `{{table}}`
src-chain-cardinality reframe (2026-07-26) — table's authoring surface hard-depends on it
(`row:FW-56` on FW-53). Related spines: FW-32 (ref-hop parity — its `/`-vs-`+` step-separator
note), FW-24 (tag-in-slot — its positional-vs-pairs / bracket-grouping note), FW-20 (combined
controls), FW-39 (ID source), FW-13 (field discovery).

**Former sub-plan — [`per-step-limit.md`](docs/design-history/per-step-limit.md), ARCHIVED 2026-08-19 (B6).** Used to
own per-step `limit` SEMANTICS and defaults, the compile-time legacy-cap rule, base-tag chain authoring
vs migration, and **FW-63** (arm dispatch); it was split out because the 2026-08-04 reopen produced ~12
decisions plus four corrections to rows this file had marked settled. That content is now BUILT and has
migrated to its owners: `docs/adr/0005-limits-are-stated-where-the-source-is-stated.md` (era-marker rule,
D1's control retirement, no-deprecation-path), `docs/tag-reference.md` §List mode (per-input/product
schema, the migration mapping table, the residual two-fanning-step hole), and PHPDoc on
`bws_run_traversal()` / `bws_limit_default()` / `bws_fold_chain_apply_legacy_limit()`. Check THOSE first
on any `limit` question — the archived file is decision history, not a live reference. This file keeps
the wire FORM only (bracket-kv, depth alternation, `0` = unlimited).

## SETTLED — CHECK HERE BEFORE RECORDING ANY CLAIM

> **Why this exists (2026-08-03).** Three claims recorded during Pass 6 were wrong about decisions
> this file had ALREADY CLOSED, twice needing the user to point at the section. The failure mode is
> not length — it is SUPERSESSION IN PLACE: live decisions and withdrawn drafts sit interleaved and
> both read as authoritative unless the banner is caught. This index is pointers only; the sections
> remain the content. **If a row says CLOSED, do not re-derive it from code — go read it.**
>
> **The recurring defect it guards against: reading ONE container's resolver and generalizing.**
> "Verified against the shipped resolver" is not a check unless it names WHICH container and looks
> at the other. `try_` (selecting) and `join`/`table` (combining) differ on the READ axis; the spike
> only ever ran one container, so single-container over-generalization is baked into spike code too
> (`proto-fold-tag.php:233-234`, `compaction-probe.js:118-121`).

> Line numbers below are a convenience and DRIFT on every edit — the section TITLE is the real
> anchor. If a number misses, grep the title.

| Decision | Section (line) | Container-sensitive? | State |
|---|---|---|---|
| WIRE SPEC (separators, brackets, grammar) | §WIRE SPEC (1791) | no | ✅ APPROVED 2026-07-31 |
| Hop sep `;`; `+` REJECTED (typographic) | §Hop separator (1846) | no | ✅ closed |
| `+` and `/` RESERVED — unspent, assert inert | §Item 0 (3360) | no | ✅ 2026-08-01 |
| DECISION 1 — step ordinal in the VALUE | §DECISION 1 (567) | no | ✅ locked |
| DECISION 2 — control builds value incrementally | §DECISION 2 (588) | no | ✅ locked |
| DECISION 3 — intra-step slug vocabulary | §DECISION 3 (459) | no | ✅ locked |
| **ROOT IMPLIED — `current` serializes only where absence means something else** | §ROOT IMPLIED + the migration's real SCOPE | **YES** (slot 2+ is the exception) | ✅ 2026-07-31 (user) — a migrated chain does not lead with its ambient root; level-aware strip rule, because a slot has three states where a base tag has two. Two arguments for the explicit form examined and withdrawn |
| **Chain-migration SCOPE = tags with a serialized `src` OR `srcTermIn`** | §ROOT IMPLIED + the migration's real SCOPE | no | ✅ 2026-07-31 (user) — base tags included, so do NOT sequence it around the slot fold (one walk, not two), and the partial-migration hazard is TAG-level. Not bounded by the strip rule: migrating `srcTermIn` gives an ambient-only tag a `src` it never had |
| Per-step `limit` = bracket-kv `limit[5]` | §DECIDED FORM (2303) | no | ✅ decided (positional SUPERSEDED at 228) |
| `limit` char by depth (`(N)` vs `[N]`) | §Depth: alternation HOLDS (2325) | no | ✅ alternation KEPT — do not reopen on aesthetics |
| **`src`-axis absence (Matrix A)** | §Matrix A (2643) | **NO** | ✅ closed, no open cells |
| **read-axis absence (Matrix B)** | §Matrix B (2657) | **YES** | ✅ closed — but see DEFECT below |
| `use(same)` in combining | §use(same) in combining (2150) | YES | ✅ LEGAL, never default, NOT BUILT |
| **Seed shape differs by container** | §Three distinctions (2139) | **YES** | ✅ selecting `src(same);use(same)`; combining `src(same)` + UNSET read |
| Absence rule is TWO rules split by LIFECYCLE | §Three distinctions (2098) | — | ✅ emit-side only; legacy parse keeps inherit |
| `same` always serialized (S1) | §`same` ALWAYS serialized (896) | no | ✅ locked |
| Materialization-on-removal = wire semantics | §Materialization-on-removal (2512) | no | ✅ locked |
| Slot ceiling — per-container, not one number | §Slot ceiling (2480) | YES | ✅ reframed |
| Container-conditional TYPE token | §Container-conditional TYPE (831) | YES | ✅ locked |
| Read-token exclusivity (`use`+`key` both present) | §asymmetric rule (2767) | no | ✅ PARSE tolerant / EMIT exclusive |
| `use(key)` with NO key = keyed read, field PENDING | §`use(key)` in the fold (AMENDED 2026-08-04) | no | ✅ EMITTED (was "never emit") — the control re-parses what it writes, so the pending kind needs a wire spelling |
| **The READ is a SIBLING token, NOT a chain terminal** | §The read folds into the chain as a TERMINAL step | no | ⚠ SUPERSEDED-IN-PLACE 2026-07-31 — the read is `use(x)`/`key(x)` beside `src(...)`, never `/`-joined onto the chain. Row added 2026-08-13 after this exact question was re-derived from code in a grilling pass |
| **SLOTS-ONLY — base tags NEVER fold the read** | §The read folds into the chain as a TERMINAL step, "SLOTS-ONLY (locked 2026-07-28)" | **YES** | ✅ 2026-07-28 (user) — base tags keep `src:` + separate `use:`/`key:`; the zero-hop base fold is all cost, no payoff (`src:site/key(X)` reads WORSE than `key:X`). Do NOT reopen off `CONTEXT.md` I16, whose identity claim is SOURCE-only |
| Table #8 (all columns 1-prefixed) OBSOLETED | §FINDING 1 (2899) | — | ✅ superseded by the fold |
| Table is GREENFIELD — adopts fold rules directly | §FINDING 3 (2947) | table | ✅ closed |
| Column existence vs cell value = 2 predicates | §Column existence (2957) | table | ✅ closed |
| Multislot tags always fold slot 1 under `A:` (was `1:`) | §Item 5 (3598) | no | ✅ 2026-08-04 (user) — the SLOT-1 half never moved (every slot folds, slot 1 included). The KEY SPELLING was reopened 2026-08-04 and closed the same day on **CAPITALS**, because an all-digit key is what forced the leading serialization position. See §RESOLVED — CAPITALS |
| **SLOT KEY SPELLING is CAPITALS (`A:`…`J:`), not digits** | §RESOLVED 2026-08-04 — CAPITALS | no | ✅ 2026-08-04 (user) — decided on ORDERING, with the prefix-legibility loss conceded: digits permanently forfeit `format`-first on join/`try_` and tag-level-first on `{{table}}`, and no sort can move an array-index key. Tokens move with the keys; digit token read kept permanently for paste-compat. Single owner `bws_slot_ordinal()` + JS twin for keys, labels, tokens |
| Fold is ONE-WAY; modes never mix | §Item 5 (3618) | no | ✅ 2026-08-02 |
| FW-24 — (a)-(c) satisfied by the spike | §FW-24 is mostly SATISFIED (2197) | — | ✅ status only, NO build |
| **Read twin takes `$allow_same` (container param)** | §Anti-drift obligation (4018) | **YES** | ✅ 2026-08-03 — dissolves join's fork; join rejoins this release |
| Option leaves are GROUP-PURE (one KEY_MAP group) | §Leaf-granularity discipline (4080) | no | ✅ 2026-08-03 — composers may straddle, leaves may not |
| Item 7 label defects = SYMPTOMS of item 6 | §Label defects (4116) | no | ✅ 2026-08-03 — no decision content; derivation discharges both |
| Value-dependent noun is CONTROL-side, not registration | §`{{table}}`'s noun (4189) | table | ✅ 2026-08-03 — GB registers statically; `dynamicLabel` precedent |
| `{{table}}` noun stays owned by `table-tag.md` #8 | §`{{table}}`'s noun (4189) | table | ✅ 2026-08-03 — build order: fold FIRST, then table |
| Single-source via LEAF builders, not "pick a copy" | §THE PATTERN IS ALREADY SHIPPED (4051) | no | ✅ 2026-08-03 — datetime precedent (1.6.0) |
| Multi-option terminal tags | §Multi-option terminal (976) | YES | ⏸ v1 DEFER |
| FIXED-revealed-slots control model | §Control model (1046) | no | ✅ v1 |
| **`fold_from_legacy` IS container-aware (Option A)** | §RESOLVED (3754) | **YES** | ✅ 2026-08-03 — closes the Matrix B inconsistency; combining absent-read → unset/skip |
| **No legacy shape needs a MIGRATION flag** | §The FLAG surface (3791) | **YES** | ✅ 2026-08-03 — FW-51 flag is try_-only; would have flagged nearly every join tag |
| Fold makes FW-51's broken shape UNEXPRESSIBLE | §The FLAG surface (3791) | try_ | ✅ 2026-08-03 — claim on the FW-51 tracker row |
| Reserved/grammar chars INERT in text-entry values | §CONSTRAINT (3831) | no | ✅ 2026-08-03 — binds forward: any text option folded into a slot must be bracketed |
| **UNLIMITED = `0`; PARSE `0`+`-1`, EMIT `0`** | §UNLIMITED encoding (3897) | no | ✅ 2026-08-03 — WP blocks `-1`, GB uses it; tolerance serves both |
| **Legacy `limit:0`/`-1` → UNLIMITED, NOT 1** | §⚠ CORRECTED (3915) | no | ✅ 2026-08-03 — freezing a CLAMP ≠ preserving a semantic; earlier draft withdrawn |
| `is_numeric()` guard — non-numeric ⇒ UNSET, never 0 | §⚠ NEW DEFECT (3932) | no | ✅ 2026-08-03 — new hazard: `(int)'abc' === 0` would fan unlimited |
| ~~**`{{table}}` terminal step defaults UNLIMITED**~~ | §terminal step (3939) | **table** | ⚠ **SUPERSEDED 2026-08-05** by [`per-step-limit.md`](docs/design-history/per-step-limit.md) §The spelling is the era marker — table authors CHAIN wire, so its rows step is uncapped by the per-hop default and the caller stops materializing `limit => 0`. The `0` = unlimited seam rule (row 69) is untouched |
| ONE clamp helper lands BEFORE the semantics change | §Blast radius (3860) | no | ✅ 2026-08-03 — 3 sites today; escape hatch = explicit `limit[0]` per step |
| **Migrator shape — SPLIT REGISTRATION LOOPS, no runtime branch** | §Remaining migrator-shape TBDs (3640) | **YES** | ✅ 2026-08-03 — `match_tag` is an exact compare, so container-ness is known at registration |
| Multislot tag list DERIVED; staleness caught by fixtures | §Remaining migrator-shape TBDs (3640) | no | ✅ 2026-08-03 — `get_modifier_templates()` + join/table; base list hand-written but matrix-covered |
| **BUILD ORDER — 6 steps, each citing its constraint** | §BUILD ORDER (4255) | — | ✅ 2026-08-03 — was ABSENT (Pass 6 is a checklist, not a sequence) |
| Legacy `limit` with NO fanning step stays SLOT-LEVEL | §Step 5 build decisions | no | ✅ 2026-08-03 (build) — per-step has nothing to attach to. ⚠ **RATIONALE WRONG (2026-08-05):** "that case caps a multi-value READ" — there is no multi-value read (`bws_read_resolved_source(): string`, one value per source). The BEHAVIOUR stands (with no fanning step the source list has one element, so the cap is a no-op); do not reason from the stated reason. See [`per-step-limit.md`](docs/design-history/per-step-limit.md) §The read is 1:1 |
| FW-51 key-only selecting slot ≥2 maps to NOTHING (dropped) | §Step 5 build decisions | **YES** | ✅ 2026-08-03 (build) — shipped skips it BEFORE carry-forward, so drop is the output-preserving branch. **LEGACY wire only:** a FOLDED `2:key(b)` resolves, because the fold can state the override the flat wire could not (P14.7) |
| `limit` `-1` normalizes to `0` in the STRUCT, not only at emit | §Step 5 build decisions | no | ✅ 2026-08-03 (build) — one representation downstream |
| **Render seam = era-per-SLOT + ONE carry accumulator in the flattener** | §Flip decisions (5d) | read axis | ✅ 2026-08-03 (build) — mixed-era resolves both directions; preview walk COLLAPSED into the same seam (its copy had drifted) |
| Chain the flat seam can't express ⇒ SKIP the slot, never a prefix | §Flip decisions (5d) | no | ✅ 2026-08-03 (build) — a truncated prefix reads a different source than the wire states |
| ARGLESS `refs` step keeps the CARRIED ref | §Flip decisions (5d) | no | ✅ 2026-08-03 (build) — shipped `$last_ref` survives src overrides; found by the equivalence property |
| Empty chain at slot ≥2 = RESET to ambient, not inherit | §Flip decisions (5d) | no | ✅ 2026-08-03 (build) — legacy absence materializes `src(same)`, so absence is unambiguous |
| **Folded keys are KNOWN to the FW-52 canonical order** | §Flip decisions (5d) | no | ✅ 2026-08-03 (build), **RANK RESTORED 2026-08-04 (5i)** — a folded key DECODES to `[N,'']` (`bws_slot_ordinal_num()`) and ranks as its slot's SOURCE: the `''` KEY_MAP entry is back (`array( 'source', -1 )`), so the order is `format` → tag-level (slot 0) source → slots `A`,`B`,… ascending → `link` → `fallback`. The 5f supersession ("they lead the whole string") was a property of the DIGIT spelling and fell away with it. PHP + JS port in one edit, port twin-tested under `node` |
| Registration derives; only hop labels + the combining unset row are authored | §Flip decisions (5d) | **YES** | ✅ 2026-08-03 (build) — no shipped builder has a step-shaped hop noun; `hops` is a capability list |
| **A selecting container is THREE read shapes**, read off the derived config | §Flip decisions — try_ (5d) | **YES** | ✅ 2026-08-03 (build) — `try_per_slot_use`/`_key` pair: enum+picker / picker alone / no read; each resolves absence differently |
| Slot 1 of a SELECTING container is never absent | §Flip decisions — try_ (5d) | **YES** | ✅ 2026-08-03 (build) — every axis unset IS the default attempt (bare `{{try_title}}`); combining needs no exception |
| `use(same)` materialized only where a read axis exists | §Flip decisions — try_ (5d) | **YES** | ✅ 2026-08-03 (build) — cosmetic in a selecting container, so never written onto a read-less tag |
| Legacy `use:key` + EMPTY key at slot ≥2 ⇒ inherit | §Flip decisions — try_ (5d) | selecting only | ✅ 2026-08-03 (build) — the borrowed-key shape, FW-51 from the other side; found by the equivalence property |
| All-inherit slot resolves as a DUPLICATE, not skipped | §Flip decisions — try_ (5d) | selecting only | ✅ 2026-08-03 (build) — output-identical; it is also the repeater's seed shape, so the seam must resolve it |
| try_ `limit`/`sep` are UNCONDITIONAL under the fold | §Flip decisions — try_ (5d) | no | ✅ 2026-08-03 (build) — a list axis inside a slot value has no honest `show_if` predicate |
| NEVER `array_merge()` an option set holding folded slot keys | §Flip decisions — try_ (5d) | no | ✅ 2026-08-03 (build) — int keys get renumbered (`1..5` → `0..4`); append by key. ⚠ **RETIRED 2026-08-04 (5i)** — capitals are ordinary string keys, so PHP no longer stores them as ints and `array_merge` cannot renumber them. Append-by-key stays as a habit; `slot-options-build-test.php` keeps the assertion INVERTED so the hazard cannot return unnoticed |
| Compaction probe is CONTAINER-parameterized (closes the last 5c OPEN row) | §Flip decisions — try_ (5d) | **YES** | ✅ 2026-08-03 (build) — four configs now: combining, selecting, key-only, chain-only; per-shape SEED asserted |
| **Only the MULTISLOT registration loop ships; base depth-0 has no reader** | §Flip decisions — migrator (5e) | **YES** | ✅ 2026-08-03 (build) — base tags build their chain from the flat keys, so a depth-0 `src:refs,…` renders nothing; the two-loop SHAPE is unchanged, it just has one instance |
| `{{table}}` needs NO migration entry, ever | §Flip decisions — migrator (5e) | **table** | ✅ 2026-08-03 (build) — ships folded, so no stored table tag carried flat slot keys (asserted, M3.7) |
| **tag-level/slot-level split is PER CONTAINER, excluded at EVERY position** | §Flip decisions — migrator (5e) | **YES** | ✅ 2026-08-03 (build) — `limit` is a join SLOT axis and a try_ TAG-level cap; chain-only `use`/`key` are tag-level, and a dead `N-key` must stay dead. Owner `TagTemplateRegistry::try_slot_axes()`, consumed by BOTH registration and migrator |
| An already-folded slot WINS over its legacy siblings (dropped, not merged) | §Flip decisions — migrator (5e) | no | ✅ 2026-08-03 (build) — same rule as the render dual-read; merging would invent a config neither side wrote |
| Migrator output is CANONICALLY ORDERED | §Flip decisions — migrator (5e) | no | ✅ 2026-08-03 (build) — else every migrated tag shows a spurious diff on first open; tag-level source keys lead the slots |
| **A no-op entry must NOT halt the option-migration cascade** | §Flip decisions — migrator (5e) | no | ✅ 2026-08-03 (build) — ENGINE fix; as+size (matches `as`, survives its transform) was stranding the fold entry live. Cap 16 → 32 |
| `parse_tag_string()` PRESERVES the last value's trailing space | §Flip decisions — migrator (5e) | no | ✅ 2026-08-03 (build) — GB's `parse_options()` does not trim; an rtrim rewrote authored `sep:, ` to `,` on every migrated tag |
| The EQUIVALENCE property is blind to the strip | §Flip decisions — migrator (5e) | no | ✅ 2026-08-03 (build) — folded value wins in the dual-read, so leftover legacy keys still resolve identically; only `fold-migration-test.php` sees it (mutation-confirmed) |
| **wire→steps COMPILE is its OWN sub-step (5h), before `{{table}}`** | §⚠ THE MISSING MIDDLE | **YES** | ✅ 2026-08-04 (user) — the gap was a STATED requirement with no assigned step; table consumes a built compiler rather than designing one, and the base depth-0 migration half 5e held lands with it |
| **FOLDED slot keys LEAD the serialized string — forced, not chosen** | §Flip decisions — mount (5f) | no | ✅ 2026-08-04 (build) — an all-digit key is a JS array-index property, enumerated before every string key; GB serializes with `Object.entries`, so the editor CANNOT emit otherwise. PHP's canonical order adopted it, else converter and mount write one tag two ways. SUPERSEDES the 5d row above (folded keys ranked *inside* the source group). ⚠ **WITHDRAWN 2026-08-04:** "forced" was forced GIVEN an all-digit key — a consequence of the SPELLING, not of folding. The spelling is now CAPITALS, so the key is no longer an array index and this row FALLS AWAY: rank returns to `bws_serialization_order_sort()` + its port, and `format` / tag-level options lead as §Option order intends. Kept because the MECHANISM is still true and still the reason capitals won. See §RESOLVED — CAPITALS |
| **`format` TOKENS follow the slot KEY spelling, digits kept as a FALLBACK** | §Candidate elimination + token decision | join / table (the containers with template tokens) | ✅ 2026-08-04 (user) — `%N` was only ever the digit ordinal spelled for a `}`-free wire, so it moves with the keys: ONE alphabet, which dissolves the two-alphabet objection to capitals. The fallback is load-bearing — `bws_join_wire_format()` maps both alphabets onto the same internal `{N}`, so the move needs NO converter/mount entry and no dual-read era. Costs: the `%%` escape widens from "before a digit" to "before a digit or a SLOT LETTER" (no fallback trick, and the fallback is what forces it), and panel labels must move too — hence ONE `bws_slot_ordinal()` owning digit→letter for keys, labels, tokens and previews. Conditional on the spelling landing on capitals |
| **The per-slot legacy AXIS SURFACE ships to the editor (`legacyAxes`)** | §Flip decisions — mount (5f) | **YES** | ✅ 2026-08-04 (build) — single owner `bws_fold_slot_legacy_axes`; a hand-kept list in the control deleted a try_ template's TAG-level `limit` and folded a `try_datetime_*`'s TAG-level `key` into slot 1 |
| The mount migrator knows NO tag names | §Flip decisions — mount (5f) | no | ✅ 2026-08-04 (build) — the converter matches by tag because MigrationRegistry does; the editor reads container config off the option definition instead |
| Both invisible controls use FUNCTION UPDATERS | §Flip decisions — mount (5f) | no | ✅ 2026-08-04 (build) — two whole-object writes now land on one tag in one React batch; composing off `prev` loses neither, and returning `prev` is the loop guard |
| **Explicit legacy `src:current` maps to a `current` STEP, not to nothing** | §Flip decisions — mount (5f) | **YES** | ✅ 2026-08-04 (build) — a chain-only container's fallback attempt can be `{N}-src:current` ENTIRE; mapping it to nothing emitted `''`, so the slot key was never written and the attempt VANISHED. Verified three ways with `render-tag` |
| The 5d "inexpressible chain SKIPS the slot" rule is a SYMPTOM | §⚠ THE MISSING MIDDLE | no | ⚠ **HALF-WITHDRAWN at build 2026-08-04** — the missing compiler was real and is built (5h), but it was not the whole cause. P13.5's cases resolve at DEPTH 0 only; the SLOT skip STANDS, because container ARMS gate on flat tokens. See §Flip decisions — compile (5h) |
| **The ROOT is not a step — factory consumes it, engine consumes the hops** | §Flip decisions — compile (5h) | no | ✅ 2026-08-04 (build) — decidable from the slug alone via DECISION 3's singular/plural disjointness; `bws_fold_chain_root` + `bws_fold_chain_to_steps` |
| An ARGLESS fanning step is DROPPED, not compiled field-less | §Flip decisions — compile (5h) | no | ✅ 2026-08-04 (build) — legacy `src:ref` with no `ref` read the AMBIENT entity; a field-less step would short-circuit to empty and change a stored wire's output |
| An UNKNOWN hop slug compiles to an unknown engine TYPE | §Flip decisions — compile (5h) | no | ✅ 2026-08-04 (build) — engine answers empty ⇒ chain short-circuits; dropping it would read a different source than the wire states |
| ~~**Per-hop `limit` caps the STEP'S WHOLE OUTPUT**~~; terminal `limit` stays the caller's | §Flip decisions — compile (5h) | no | ⚠ **SUPERSEDED 2026-08-05** — the per-hop cap is **PER-INPUT** (`limit(1)` on a terms hop = one term per post). Its only recorded justification was legacy equivalence, and no stored tag reaches the engine with a hop cap at all, so nothing bound it. The "emitted only when it CAPS" half STANDS. [`per-step-limit.md`](docs/design-history/per-step-limit.md) §Per-input, not whole-output |
| The wrapper takes the LEADING RUN of `ref` steps and STOPS | §Flip decisions — compile (5h) | no | ✅ 2026-08-04 (build) — §V13/B2 preserved; filtering past a term/rows hop would run later ref steps against the wrong entity |
| A legacy `srcTermIn` beside chain wire APPENDS; an existing `terms` hop WINS | §Flip decisions — compile (5h) | no | ✅ 2026-08-04 (build) — separate option KEY describing a hop; dropping loses a configured hop, appending twice double-hops |
| **Base depth-0 MIGRATION is REGISTERED, landing this release** | §Flip decisions — compile (5h) | **YES** | ✅ 2026-08-05 (user) — 5e's held half lands. Gated on **FW-63** completing FIRST: migrating flat→chain puts every stored base tag through chain arms at once, so a broken arm goes from affecting hand-converted tags to affecting the whole corpus on upgrade. The compiler's legacy branch still reads unmigrated flat wire at its old cap meaning — that is the SAFETY NET for wire no migration path reaches (ACF meta), not a substitute for migrating. ⚠ An intermediate draft of the sub-plan recorded this as permanently unregistered; **withdrawn**. [`per-step-limit.md`](docs/design-history/per-step-limit.md) §Two surfaces, one rule |
| **The seam REPORTS its skip reason; `'chain'` is flagged, `'read'` stays silent** | §Flip decisions — graduation (5g) | **YES** | ✅ 2026-08-04 (build) — an unconfigured slot is a normal in-progress state; an inexpressible chain is wire that will never render. Reported BY THE OWNER (out-param), never re-derived in the preview |
| **Rendered output is NOT evidence for a skip** | §Flip decisions — graduation (5g) | no | ✅ 2026-08-04 (build) — a skip and an empty read both print nothing; the matrix carries a NEGATIVE CONTROL, and a first-draft row claiming ref+term as the skip case was vacuous (the flat triple holds one hop of EACH axis) |
| **Legacy↔folded pairs CROSS on the source axis** | §Flip decisions — graduation (5g) | no | ✅ 2026-08-04 (build) — folded `N:key(x)` twins legacy `N-src:current\|N-key:x`; folded `N:src(same);key(x)` twins bare `N-key:x`. Consequence of the 5d absence rule, never written down; the first matrix draft mis-paired them |
| **Ref-hop OBJECT return formats now have FIXTURES (blueprint v6)** | §Flip decisions — graduation (5g) | no | ✅ 2026-08-04 (user + build) — the `WP_Post` arms were asserted only against a shim's GUESS at ACF's shape; same class as 5h's inverted `sanitize_key`. Equivalence rows + a written non-vacuity check |
| Expected-EMPTY fixture rows need a SPLIT label block | §Flip decisions — graduation (5g) | no | ✅ 2026-08-04 (build) — GB hides the whole block, label included, so a one-block row reads as MISSING FIXTURE |
| A `{{…}}` inside a fixture row LABEL is LIVE WIRE | §Flip decisions — graduation (5g) | no | ✅ 2026-08-04 (build) — GB rendered a `{{table}}` mentioned in prose and the empty output hid the label |
| Fold rows sit BETWEEN join and table (no Catalog slot of their own) | §Flip decisions — graduation (5g) | no | ✅ 2026-08-04 (build) — a wire-form axis is not a tag family; the multislot containers are its neighbours |
| **`{{join}}`'s 1.16.0 `fallback` rename never reached its matrix or fixture row** | §Flip decisions — graduation (5g) | no | ✅ 2026-08-04 (build) — five rows + the visible J3 row rendered EMPTY while claiming `—`. Fixed; the PREVIEW still tolerates the dead key (FW-50's lane) |
| **Slot keys re-spelled digits→CAPITALS in ONE atomic flip** | §BUILD ORDER — 5i | no | ✅ 2026-08-04 (build) — modes do not mix (a tag is folded iff a CAPITAL key exists), so registration, resolver, both migrators, both order parsers and both previews had to flip together: folded keys registered against a flat resolver render nothing. **No digit→letter migration entry**, deliberately — folded digit wire never shipped, and a lenient digit KEY read would re-import the two-spellings drift the single owner removes. The testbed's stored digit wire is covered by a RESEED, not a converter |
| **Both order parsers DECODE the key; nothing compares slot keys as strings** | §BUILD ORDER — 5i | no | ✅ 2026-08-04 (build) — `AA` is slot 27: after `Z` numerically, BEFORE it lexically. So `serialization-order.php` now depends on `slot-fold.php` (call-time), and the JS enqueue INVERTED — `serialization-order-normalizer.js` depends on `slot-fold-grammar.js`, because the normalizer decodes on every `setState` where the grammar only needs `bwsReorderKeys` at emit time. An absent decoder is silently wrong (every slot ranks as slot 0); a `function_exists` guard was rejected for that reason |
| **The `%%` escape migration is gated on wire ERA, never on CONTENT** | §BUILD ORDER — 5i | join (the container with template tokens) | ✅ 2026-08-04 (build) — `%A` is literal-or-token by ERA and undecidable by inspection, so a content test ("does the format also hold a digit token?") destroys an INTENDED token: it breaks working output instead of fixing broken output. A folded slot key cannot predate the letters, so its PRESENCE decides. That gate forces `bws_migrate_join_format_escape` to register BEFORE the fold entry — the fold ADDS folded keys, so after it every tag looks post-letters and the entry never fires. Scanner path only; no mount twin (one rendering change in rare literal text, not lost configuration). Caught by the M8 harness block escaping its own tokens |
| **The DIGIT read is retained FOREVER for TOKENS and NOT AT ALL for KEYS** | §SETTLED — format tokens follow the key spelling | no | ✅ 2026-08-04 (build) — both token alphabets collapse to one internal `{N}` at a SINGLE translation point (`bws_join_wire_format`), which is what makes the token move migration-free and keeps hand-pasted wire renderable (ADR 0004). Keys get no such read: that spelling never shipped, so two spellings would buy nothing and cost every consumer a branch |
| Panel labels carry the ORDINAL, not the number | §BUILD ORDER — 5i | no | ✅ 2026-08-04 (build) — `Slot B` / `Field B` (`%d` → `%s`), so the panel heading, the wire key and the `%B` token are ONE alphabet. Narrower than the step row claimed: the `%d:` prefixes on `bws_build_slot_traversal_options()` and `{{table}}`'s `Column %d` label LEGACY flat keys, which stay DIGITS — nothing pins their order. Table's labels revisit at step 6 |
| **VOCABULARY PASS — 9 decisions (V1–V9): `hop`, `terminal`, `target cardinality` RETIRED; `fanning` / `flat`-vs-`legacy` / engine-slug alignment ADOPTED** | §VOCABULARY | **YES** | ✅ 2026-08-05 (user, `/domain-modeling`) — four terms named the mechanism from the wrong side. Binds PROSE now; identifier renames are build tasks. **V9 flips DECISION 3's vocabulary half** (the disjointness half stands) |

**OPEN — genuinely undecided, do not treat as settled:**

> **NOTHING HERE BLOCKS THE BUILD (assessed 2026-08-03).** The last blocking DECISION (migrator
> target-form shape) closed today. Every row below is a build TASK that attaches to a step in
> §BUILD ORDER, or scope deferred out of this release — none gates the remaining steps.

> **RECHECKED 2026-08-12, ahead of the merge.** TWO rows are live: `sep` + the five datetime keys
> under the fold (deferred by decision, stays deferred), and **Preview-tool cleanup, REOPENED the
> same day (user) for one more pass once the release work is done** — scheduled after the merge, not
> before. Neither gates the merge. The sub-plan [`per-step-limit.md`](docs/design-history/per-step-limit.md) §OPEN was rechecked in the
> same pass and holds one live row (*where `term_`'s limit goes*, riding FW-43) plus two that moved
> to tracker rows (FW-64 composite control, FW-67 `bws-term-hop`). ⚠ **That recheck's closing clause said the
> sub-plan "still WINS on every `limit` question" — TRUE WHEN WRITTEN, FALSE NOW.** B6 migrated
> its substance to the owners on 2026-08-19 and archived it; read `docs/adr/0005`,
> `docs/tag-reference.md` §List mode and the PHPDoc on `bws_run_traversal()` /
> `bws_limit_default()` / `bws_fold_chain_apply_legacy_limit()` instead, as the header says.
> The clause is struck rather than deleted because the recheck is a DATED record and its
> present tense is exactly what would mislead a reader who reaches this index before the header.

> **CLOSED OUT 2026-08-20 — every row below is homed on the tracker, and this file archives.** The
> two live rows were the last reason to keep it open, and neither needed it: `sep` under the fold is
> **FW-44** (`{N}-sep`) and **FW-61** (per-step `sep` on a fanning chain), the five datetime keys are
> **FW-81** (which absorbed FW-68), and preview-tool cleanup is **FW-79**. FW-56 itself has been in
> Closed / Retired since 1.17.0, and GH #55 — the authoring surface FW-53 waited on — is closed. What
> remains here is the wire FORM and the deliberation that produced it, which is a record. Read the
> tracker rows for what is still to do; read this for how the grammar came to be what it is.

| Open item | Section (line) | Note |
|---|---|---|
| ~~`sep` + 5 datetime keys under the fold~~ | §Item 5 (3562) | ✅ **TRACKED 2026-08-20** — `sep` → FW-44 + FW-61, datetime keys → FW-81. Was explicitly DEFERRED, not overlooked; deferral now has a home that outlives this file |
| ~~**Preview-tool cleanup — ANOTHER PASS**~~ | §Item 2 (3403) | ✅ **TRACKED 2026-08-20 as FW-79**, which re-bases the tool on shipped chain wire and subsumes this pass. ⚠ **REOPENED 2026-08-12 (user): one more pass over `tools/preview/tag-string-preview.html` once the release work is done.** Scheduled AFTER the merge, not before — the tool is a design-exploration surface, nothing renders through it, and re-syncing it mid-branch is what left it stale twice already. What the next pass inherits, beyond re-reading it against the SHIPPED grammar: it is one of the two deliberate carriers of the retired word "cap" (the other is the shipped CHANGELOG, which is append-only and stays), so the D7 rename question lands here on its own terms. History of the earlier passes, kept because both left something stale: ✅ DONE in 5g, **FINISHED in 5i** — the pending-revision banner is now a SHIPPED banner naming the owner files (`slot-fold.php` + its JS twin), with what shipped differently from the approved bullets: no `N-` siblings in the shipped containers, and `+`/`/` RESERVED rather than merely rejected. **5g left two things stale and they were the review's finding:** the banner still stated the DIGIT pin as fact and the key picker still read "digits (shipped) / capitals (candidate)" under a ✅ SHIPPED heading — the exact supersession-in-place this index exists to catch, in the one deliverable outside the index's reach. Now capitals-default with digits kept as a look-back. Also 5g's stated ASCII prune was NOT done: `»` and `·` survived in FIVE preset lists (not the two named), and are now cut from all of them — ADR 0004 makes the wire hand-editable, so an untypeable separator is a dead candidate, and a picker that cannot produce an illegal option cannot lead the next review astray. A `·` sitting in a scenario's `sep` VALUE stays: that is output content, not grammar. The page STAYS as the exploration surface (side-by-side candidate comparison outlives the decision) |
| ~~`src:site` has no try_ preview vocabulary~~ | §Flip decisions — try_ (5d) | ✅ **FILED 2026-08-11 as [#79](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/79)** — the source part renders empty for `src(site)` in `bws_try_preview_source_part` (pre-existing since the FW-4 site arm; surfaced while writing 5d preview cases). Not undecided, just unfiled: the base-tag preview already ships the author-facing noun (`Site`), so this is a missing arm in one of three builders rather than a wording call. Bugs live in Issues, not in a plan's OPEN table |
| ~~SLOT KEY SPELLING — digits vs capitals~~ | §REOPENED — slot KEY spelling | — | ✅ **CLOSED 2026-08-04 — CAPITALS.** Moved to the settled table above; row kept as the audit trail of what was open. Build follows |
| ~~**Base-tag ARMS gate on flat `src`/`srcTermIn` tokens**~~ | §Flip decisions — compile (5h) | ✅ **CLOSED AS OPEN 2026-08-05** — filed as **FW-63** and scoped INTO this release (user: *"together"*), because base-tag chain AUTHORING has no other encoding for multi-step relationships. Sized: ≈19 render-path sites across five files, and they conflate two axes the compiler already separates (root vs terminal kind), so the fix is two chain queries — one of which, `bws_fold_src_root_token()`, already ships. Detail home [`per-step-limit.md`](docs/design-history/per-step-limit.md) §Arm dispatch, sized |
| ~~**`srcTermIn` does NOT carry forward through `src(same)`**~~ | §Render seam (the carry table, 2359) | ✅ **REOPENED AND REVERSED 2026-08-11** — [#74](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/74). It carries now, uniformly in both containers. The old rule was preserved from the flat era as shipped behaviour rather than chosen, and the CAUSE was a **UI artifact**: `srcTermIn` was a separate control beside the source, and inheriting a standalone control's state across slots caused problems of its own. With `terms` as a step INSIDE `src` that constraint is gone — `src(same)` names the same source, and a term step is part of what the source IS, unlike `limit` (a *parameter of* a source, hence its container split). Two ambient-fallback defects in the same walk were fixed with it: an argless step now says nothing instead of reading the ambient entity, and `src(same)` with nothing ever carried skips. Invariant home [`CONTEXT.md` I15]; §P16/§P17 pin it, §P14.5 inverted with the cause recorded |

## VOCABULARY — the 2026-08-05 terminology pass

Run as a `/domain-modeling` session at the user's request, before any FW-63 code moves. Recorded here
because the terms span FW-56 / FW-57 / FW-63 and CONTEXT.md, so no single sub-plan owns them. **These
bind PROSE immediately** (the concepts are shipped); the identifier renames are build tasks listed at
the end.

**The finding, stated once.** Four of the words under review named the mechanism *from the wrong side* —
`hop` named transport where the property is fan-out, `terminal step kind` named the step where the
property belongs to the chain, `target cardinality` named the target where the property belongs to the
resolved source, and `legacy axes` named lifecycle where membership is structural. §Language's `_Avoid_`
lists are the existing defence against exactly this, and none of the four carried one.

### Decisions

| # | Decision | Replaces |
|---|---|---|
| V1 | **`hop` RETIRED.** A routing metaphor is 1:1 and *hides cardinality* — the one property the whole `limit` design turns on. `fanning` already carried the meaning next to it (`BWS_FOLD_FANNING_SLUGS`) | `hop`, `ref-hop` (as a general term) |
| V2 | **`step` KEPT**, and **a root is not a step** — I14 already says so, which is what keeps `step`'s movement sense honest. Chain = a root plus N steps | — |
| V3 | **chain = the DESCRIBED structure; pipeline = its EXECUTION.** The compiler is the translation point (`bws_fold_chain_to_steps()`). The container disambiguates the element, so *"chain step"* / *"pipeline step"* need no third noun | the layer split Q14 wanted to put on the element noun |
| V4 | **`try_` has no collective noun.** *"Resolves its attempts in order; the first non-empty one surfaces."* `attempt` is already shipped author-facing vocabulary (`+ Add attempt` / `Attempt A`). **`fallback` is unavailable** — it is a live option key | *"try_ chain"* (the nested-`chain` collision), *"fallback"* as a mechanism name |
| V5 | **`terminal` RETIRED outright.** Nothing is left for it to name | see the four replacements below |
| V6 | **`target cardinality` RETIRED, head noun and all.** `singular`/`plural` are the words the definition already uses; the abstract term added a lookup, not a meaning | — |
| V7 | **`fanning` = MAY resolve to more than one (static, from the wire).** The runtime count is a length, not an adjective. **A plural slug asserts CAPACITY, not outcome** (user) — a relationship field capped at 1, a single-term taxonomy and a one-row repeater are all routinely length-1 | `plural` as a property of a *source* |
| V8 | **`flat` / `folded` = STRUCTURE. `legacy` = LIFECYCLE** (read but no longer written). `unfolded`, `non-folded`, `pre-fold` retire as synonyms of `flat` | three synonyms |
| V9 | **Engine step types ALIGN to the wire slugs** — `refs` / `terms` / `entries`. `BWS_FOLD_STEP_TYPES` STAYS as the seam | `ref` / `srcTermIn` / `rows`; flips DECISION 3's vocabulary half |

### V5 — what `terminal` was standing in for

Each replacement is a term §Language **already owns**, which is why the retirement is free:

| was | is | home |
|---|---|---|
| *"the chain's terminal step kind"* | **resolved-source kind** | CONTEXT.md:252 |
| *"…and does it fan"* | **fanning** (V7) | — |
| *"terminal cap"* | the **tag-level `limit`**, capping `ResolvedSource[]` | CONTEXT.md:260 |
| *"terminal step"* (positional) | **the last step** | — |
| *"multi-option terminal tags"* (FW-57 row) | **atomic tag** | CONTEXT.md:210 |

**The defect that forced it:** *"terminal step kind"* is undefined for a chain with no steps, and
`src:site` is exactly that — a root, an output kind, no last step. **A chain is L1**
(CONTEXT.md:267), so its output IS the resolved source; asking for a "terminal kind" was asking for the
resolved-source kind by a longer name. One distinction survives and it is a TIME, not a kind: FW-56's I8
floor needs the kind computable statically, so a chain **declares** what the engine later **produces** —
say *"the chain's declared resolved-source kind"* only where that matters.

### V9 — why align, and what stays decoupled

DECISION 3's stated reason was that the engine type *"predates"* the slug. That is history, not design,
and it left three defects: `ref` vs `refs` differ by one letter for one concept at two layers (the exact
slip the user reports making); `srcTermIn` is an **option key** — a serialization token — leaked inward
as a category name; `rows` vs `entries` diverge for no reason, which makes the map look meaningful when
two thirds of it is accident.

Cheap now because the blast radius shrank: both hand-written assemblers retired into the compiler in 5h,
so `BWS_FOLD_STEP_TYPES` is the ONLY producer of engine types.

**Untouched: DECISION 3's disjointness half** — roots singular, steps plural, decidable from the slug
alone. That is load-bearing and V7 sharpens rather than weakens it: the plural spelling is a **category
marker**, never a count claim.

### Rename tasks (build, not yet done)

| from | to | why |
|---|---|---|
| `BWS_FOLD_LEGACY_AXES` | `BWS_FOLD_FLAT_AXES` | membership is "the flat spelling" — its own PHPDoc says *"exactly the keys `bws_fold_from_legacy()` reads"* |
| `bws_fold_slot_legacy_axes()` | `bws_fold_slot_flat_axes()` | same set, minus tag-level |
| `legacyAxes` (fold config → JS) | `flatAxes` | must match its PHP source or the derive stops being obvious |
| `bws_fold_from_legacy()` | `bws_fold_from_flat()` | it maps flat → folded; the direction is structural |
| `BWS_FOLD_HOP_CLASS` (`;`) | `BWS_FOLD_STEP_CLASS` | it splits the chain into steps |
| `BWS_FOLD_STEP_CLASS` (`,`) | `BWS_FOLD_PART_CLASS` | it splits one step into parts — misnamed today regardless |
| `$link` (compiler loop) | `$step` | third word for one thing |
| engine types `ref`/`srcTermIn`/`rows` | `refs`/`terms`/`entries` | V9 — ✅ BUILT 2026-08-07 (#69). `BWS_FOLD_STEP_TYPES` NARROWED to `slug => arg-key` in the same commit (grill 2026-08-07): with the types aligned its `type` column was an identity, and "the seam stays" means the ownership point, not the array shape |

**Decisive reason the axis renames are not deferrable:** base-tag src migration lands THIS release and
needs the same list for flat SOURCE axes — three members overlap by name (`src`, `ref`, `srcTermIn`). Left
alone, one release ships two words for one role with overlapping membership, which is the conflation the
pass exists to remove.

**Stays `legacy`, because lifecycle IS the definition:** `legacy_fallback_removed` / `prefix_removed` on
MigrationRegistry entries, `docs/deprecated-tags-options.md`, CONTEXT.md:137's alias-status rules.

### Prose sites carrying a retired word

CONTEXT.md I14 (*"fanning hops are plural"* — both errors in one clause) and I14:199; CONTEXT.md:72
(*"A `try_` chain"*); CONTEXT.md:260 (the `target cardinality` heading); FW-56 / FW-57 / FW-63 tracker
rows; `fold-test-matrix.md` F14.3 (*"a fallback attempt"* — doubly wrong); this plan's own §Flip
decisions rows; `docs/design-history/per-step-limit.md` §Vocabulary and §Arm dispatch, plus its filename (`per-step-limit.md`
→ `per-step-limit.md`) and CLAUDE.md's temporary trigger row pointing at it.

---

## ⚠ THE MISSING MIDDLE — wire→steps compile (found 2026-08-04, user)

§Problem frames the gap as *"the engine already CHAINS internally … but NO authoring surface
exposes an arbitrary ordered chain"*, which reads as if only the UI is missing. **It is not.** The
piece that does not exist is neither the engine nor the authoring surface but the layer between them:
**nothing COMPILES a chain into traversal steps.** Two flat assemblers do that job today and both cap
at one ref hop plus one term hop:

| Assembler | Home | Reads | Emits |
|---|---|---|---|
| `bws_field_values_assemble_steps()` | `field-helpers.php:357` | flat `src`/`ref`/`srcTermIn` | ≤ `[ref]` + ≤ `[srcTermIn]` |
| `bws_wrapper_ref_steps()` | `base-shared.php:709` | flat `src`/`ref` | ≤ `[ref]` |

**The fold does not feed them a chain — it flattens BACK to the flat keys.**
`bws_fold_slot_flat_options()` reduces a slot to `src`/`ref`/`srcTermIn` and returns null when the
chain holds a second `refs` hop, a second `terms` hop, or an `entries` step. The 5d decision
"inexpressible chains SKIP the slot" is therefore a SYMPTOM of this gap, not an independent design
choice — the wire can already state chains the renderer cannot run.

**What it blocks (all four already tracked elsewhere, none of them tracked HERE as work):**

1. **Base depth-0 migration** — 5e's deferred half. This is how the gap surfaced.
2. **Multi-hop folded slots** — legal wire, hand-editable, renders nothing (P13.5 pins the skip).
3. **`{{table}}`'s ROW chain at the DECIDED encoding** (`src:refs,related_staff`). The prototype
   sidesteps it: `bws_wrapper_ref_steps()` + a hardcoded `rows` step off tag-level `key`.
   `table-tag.md` calls this its ⚠ blocker and notes per-column `{N}-src` shares the same dependency.
4. **FW-32 ref-hop parity** (pick primary source THEN hop, fan-out preserved) and **FW-56's authoring
   surface**, which is the consumer the whole plan is named for.

**The requirement was STATED and the work was never ASSIGNED.** §Two serialization consumers says the
render floor needs the whole ordered chain faithfully; §BUILD ORDER gives it no step. That is the
exact supersession-adjacent failure the §SETTLED practice exists to catch — a stated requirement with
no owner reads as covered.

**Shape of the work (not yet a decision):** one `bws_fold_chain_to_steps( array $chain ): array`
mapping wire slugs to engine step types (`refs`→`ref`, `terms`→`srcTermIn`, `entries`→`rows`) with
per-step `limit`, plus depth-0 chain PARSING on `src` so a base/table tag-level chain reaches it, plus
retiring the two flat assemblers behind it (they become the zero/one-hop case, not a parallel path).
The engine side already exists and is harnessed (`traversal-pipeline-test.php`), and the per-step
`limit` semantics are settled. **`entries` is the one that is more than plumbing:** promoting the
repeater step to an author-facing chain step is `{{table}}`'s row model, so this compiler and table's
row chain are the same question asked from two ends.

**✅ SEQUENCED (user, 2026-08-04): its OWN sub-step 5h, before `{{table}}`** — so table consumes a
built compiler instead of designing one, and 5e's held base depth-0 migration entry lands with it. It
is independently testable before table exists: P13.5's inexpressible-chain SKIP cases become RESOLVE
cases, and the engine side is already harnessed. Runs after 5f and BEFORE the 5g tail.

## Problem

The engine already CHAINS source steps internally: `bws_run_traversal($sources, $steps)` folds
an ordered `$steps` list (`ref` / `srcTermIn` / `rows`), each step fanning out one→many
(traversal-pipeline.php). But NO authoring surface exposes an arbitrary ordered chain — the
author only ever picks ONE step at a time via separate `show_if`-revealed options
(`src` / `ref` / `srcTermIn`, single hop). To let an author build a real chain (pick primary
THEN hop THEN hop, or promote the repeater `rows` step to an author-facing token per the table
reframe), the chain needs a **wire encoding** (flat, `}`-free) AND an **editor control model**.

## Constraints from the editor UX (STATED, 2026-07-26)

The slot-level field pickers ( `{N}-key` in `{{table}}`; the same shape anywhere a downstream
option reads fields OFF the chain's result) impose a HARD floor on the serialization — the UX
need constrains the wire, not the other way round. Three constraints:

1. **Terminal output entity-type must be STATICALLY computable from the wire** — no live probe,
   no running the chain. The slot picker scopes to the row entity's field-set; the row entity is
   the chain's TERMINAL step output. Scope must derive at editor config time, on remount, from
   serialized state ALONE (the I8 rule): the editor has no bound post in Patterns/Elements, can't
   call `get_field_object` (ACF-only, empty there), can't infer from ambient. So the terminal
   step's output kind + type must be readable off the wire without execution.

2. **Each fan-out step must carry, on the wire, the STABLE datum that its output type is derivable
   FROM — the FIELD REFERENCE, not the resolved type.** Subtlety (user, 2026-07-27): the resolved
   type is often NOT a stable scalar and MUST NOT be serialized (staleness — see the serialization
   rule below). What the wire carries is the author's actual choice (the field/step ref); the
   output type is COMPUTED from it at discovery time, recomputed each editor load, always current.
   Per step (wire slug per DECISION 3; engine `type` in parens):
   - `entries` (engine `rows`, repeater) → serialize the repeater field key; scope = its sub-field set
     (the `repeater_key` discovery stamp, BUILT). Field key is stable → fine on the wire.
   - `refs` (engine `ref`, reference field) → serialize the FIELD ref; scope = the UNION of the field's
     currently configured allowed post types, read LIVE from discovery. **NOT a single post-type, NOT
     serialized** (corrected 2026-07-27 — see §The `ref` post-type scope below).
   - `terms` (engine `srcTermIn`) → serialize the taxonomy (already an author-chosen arg, stable).
     Post-input-only; that constraint is enforced by the engine silent-empty guard + editor picker
     graying, NOT the slug name (DECISION 3 — family consistency over input-encoding).
   - entity-START (`post`/`term`/`user`, FW-39) → serialize `<kind>,<ID>`; kind on the wire is FORCED
     by constraint 1 (no live ID resolution in the editor). DECISION 3.
   - `query` (engine `src:query`) → serialize the query filter (FW-54); target PT is the author's
     filter, stable-ish.
   Output KIND (`post`/`term`/`meta_row`) IS derivable statically from the step slug alone (`refs`→post,
   `terms`→term, `entries`→meta_row) — that part is safe on the wire. Only the output TYPE (which
   post-type / which taxonomy's terms) needs the live discovery lookup for `refs`.

3. **Bias toward INCREMENTAL step-commit in the editor** — each step serializes its type-token the
   moment it's added, so pickers rescope reactively mid-build (a slot picker may need to scope
   before the author has finished the chain). Favors an append-a-step control where each step
   commits immediately over a "type the whole chain string" model (opaque until parsed). Precedent:
   the shipped `scope:'row'` picker reads `state.key` reactively as it changes
   (field-combo-control.js).

4. **NEVER serialize a RESOLVED type that can drift from its source of truth** (user, 2026-07-27).
   A field's configured post-type set can CHANGE after the tag is authored (the author edits the ACF
   field, adds/removes an allowed PT). A resolved PT baked into the wire goes STALE — the tag would
   scope to a post-type the field no longer targets. So: serialize the STABLE ref (field key,
   taxonomy, step kind); DERIVE the type live at discovery. This is why constraint 2 carries the ref,
   not the type. Corollary for the worked example below: the earlier draft baked the resolved PT
   (`...,post,staff`) into the token — WRONG; corrected.

## The `ref` post-type scope — ACF facts + the derivation (2026-07-27)

An ACF relationship / post_object field's `post_type` config is an **ARRAY** (0, 1, or many allowed
PTs; empty = "all post types"). So the scope for a `ref` step's `{N}-key` picker is a SET, not a
value — there is no 1-to-1 "return post type." Resolution:

- **Derive scope = UNION of the field's currently-configured allowed PTs, read LIVE from discovery.**
  Field discovery already fetches every field group's full config client-side
  (field-discovery.php:463-466) — a relationship field's `post_type[]` is ALREADY in the payload. The
  `{N}-key` picker reads the sibling ref-field's record → its `post_type[]` → unions → scopes. **No
  live WP call** (works in Patterns/Elements, satisfies constraint 1), **no serialized PT** (satisfies
  constraint 4 — recomputed each load, always current). Empty config → full post pool (generalizes the
  table-grill Q4 fallthrough; free-text always open).
- This is the clean path and it dissolves the earlier "`returns_post_type` singular stamp" idea — the
  datum isn't a scalar to stamp, it's the field's live `post_type[]` already in the payload.

**Author narrowing / override (a VISIBLE filter, LEAN ephemeral — user, 2026-07-27):** when a field
allows many PTs (broad union) or is unrestricted (all PTs), the author may want to narrow — or
OVERRIDE, because the author may know something not discoverable (e.g. a runtime-registered PT the
field config doesn't list). Provide a visible PT filter for that. **Lean: EPHEMERAL** — a view-only
narrowing/override aid, NOT serialized; the picker's actual scope on remount stays the field's live
union, the filter just trims the display for the session. Zero staleness by construction (constraint
4), at the cost of re-narrowing each edit.

- **OPEN — author-intent serialization (flagged, undecided):** the override case ("author knows a PT
  discovery can't see") is the one that tempts serialization — an ephemeral override is lost on
  reopen, so a deliberately-added out-of-config PT can't persist. If that intent should survive
  remount, it must serialize as SEPARATE AUTHOR INTENT, re-validated against the field's live PTs each
  load (invalid → drop/flag, not silently trusted) — NOT a cached config value. Tension: persistence
  vs the staleness rule. Decide in the FW-56 grill; do NOT default either way. (Same shape as any
  "author asserts beyond what discovery knows" case — may generalize past `ref`.)

## Two serialization consumers — different floors (decomposition)

The chain wire serves TWO readers with different needs:

- **Render + round-trip:** needs the WHOLE ordered chain faithfully — every step, every arg, in
  order — to execute (`bws_run_traversal`) and to reconstruct the editor state on reopen.
- **Editor slot-picker scope:** needs ONLY the TERMINAL step's output entity-type. Intermediate
  steps' output types are irrelevant to the slot picker — only the last hop determines the row.

**The render floor is a SUPERSET of the picker floor.** So if FW-56 serializes the chain faithfully
enough to render, the picker's need is met AUTOMATICALLY — PROVIDED constraint 2 holds (each step's
type-determining args stay statically readable, not collapsed into an opaque blob). That proviso is
the real bite: **no lossy/opaque step encoding.** The picker doesn't add a second serialization
target; it forbids a lossy one.

## Worked example — what a multi-step src chain must serialize

Scenario: a `{{table}}` on a **Department** post. The author wants ONE ROW PER staff member in a
related-staff relationship, and each row's cells read that staff member's fields — INCLUDING a
second hop to each staff member's linked "office" post for an office column.

Chain (conceptual): `current (Department) → ref:related_staff (fan-out → staff posts = ROWS)`.
Then a COLUMN does its own downstream hop: `staff-row → ref:office → office.address`.

**All names + separators below are PLACEHOLDER** (the encoding is the open FW-56 decision; this
shows WHAT must be captured, not the final spelling). **Note the resolved post-type is NOT in the
wire** — it is derived live from the field ref (constraint 4 + §The `ref` post-type scope). Two
candidate encodings for the ROW chain:

### The DECIDED encoding (value-side, one key per level — see §DECIDED above)

Wire slugs per DECISION 3 (`refs`/`terms`/`entries`; the engine `type` spelling ALIGNED to them 2026-08-07 — V9, #69).

```
{{table src:refs,related_staff | 1-label:Name | 1-use:title
                               | 2-label:Office | 2-src:refs,office | 2-key:address}}
```

The row chain is the `src` VALUE: `refs,related_staff`. A single step here; a two-hop row chain would
be `src:refs,related_staff/refs,manager` (`/` = ordered hop). What serializes inside the value:
- `refs`           — step SLUG (fan-out reference-field hop). Output KIND (`post`) is derivable from
                     this alone: `refs`→post always. ← constraint 1 (statically readable, no lookup)
- `related_staff`  — the field ref: what render hops AND what the picker reads to derive scope.

The picker scopes `1-key`/`1-use` by reading `related_staff`'s record from the discovery payload →
its `post_type[]` → union → STAFF-family fields. The resolved post-type is NEVER serialized (would
go stale if the field's config changes — constraint 4); it's recomputed each load, always current.
`refs,related_staff` is all render needs too. One key, no baked type.

The per-column downstream hop `2-src:refs,office` is the SAME shape, one level down: the column's own
mini-chain (its own `src` value under the `2-` column prefix). `2-key`'s picker reads `office`'s live
`post_type[]` → OFFICE-family scope. The column-level value nests trivially because DECISION 1 keeps
one chain key per level — the column prefix is just prepended, no second ordinal.

### What each step serializes (the actual requirement)

Under the DECIDED value-encoding, every fan-out step (a `/`-segment of the chain value) carries
exactly these — and NOT the resolved type:

| Datum | Serialized? | Consumer | Why |
|---|---|---|---|
| step SLUG (`refs`/`terms`/`entries`/`post`/`term`/`user`) | YES | render + picker | which pipeline case / base kind; output-KIND is derivable from it (DECISION 3) |
| step ARG (field key / taxonomy / entity ID) | YES (stable) | render + picker | render hops/roots it; picker derives scope from it |
| chain ORDER | YES | render + round-trip | fold sequence; reconstruct editor state |
| terminal output KIND (`post`/`term`/`meta_row`) | NO — derived from step KIND | picker | cell-reader branch + discovery kind; no lookup needed |
| terminal output TYPE (post-type set / taxonomy) | **NO — derived LIVE from the field ref** | picker | scope the field set; serializing it risks staleness (constraint 4) |

The picker's whole diet = (step KIND → output kind) + (field ref → live type lookup). Neither needs
the resolved type on the wire. A faithful encoding of KIND + field-ref + order satisfies render AND
picker with zero staleness.

## Per-step `limit` — a fan-out-step ARG (user, 2026-07-27)

> 🔄 **REOPENED 2026-08-04, RESOLVED IN A SUB-PLAN — read [`per-step-limit.md`](docs/design-history/per-step-limit.md) FIRST.**
> The wire FORM below is untouched (bracket-kv, depth alternation, `0` = unlimited). What changed is
> everything about MEANING and DEFAULTS: the cap is **per-input** not whole-output, the per-step default
> is **uncapped** not 1, the legacy cap is materialized at **compile** time so the flat `src` spelling
> is the era marker, and the tag-contextual positional-default rule below is superseded outright.
> The sub-plan also files **FW-63** (arm dispatch) and scopes base-tag chain authoring into this
> release. Treat the SEMANTIC prose in this section and the two below as historical.
>
> - **`limit(N)` — a named bracket-kv token** in the step's comma list, alongside any other per-step
>   option. Bracket char follows depth alternation like every other bracket: `limit(3)` at depth 0
>   (the colon form `src:refs,x`), `limit[3]` inside a slot's `src(…)` wrapper.
> - **Emitted FIRST**, directly after `slug,arg`. Cosmetic — kv is order-free — but fixed so output
>   is stable.
> - **Step body stays FLAT**: `slug,arg,limit(N)`, no step bracket.
>
> Rejected along the way, both kept as look-backs in the preview tool: the POSITIONAL form
> (`refs,rel_post,5` — shreds to `entries,,5` when the arg is blank) and the SLASH form
> (`refs,rel_post/2` — §below).
>
> Parked with it: **`limit` 0 = unlimited**, provisional on a look at the GB editor interface. Not
> blocking — no picker offers the value. Build carry-ins if it holds: the resolved default policy
> must treat 0 as no-cap rather than forwarding it to a query arg that reads 0 as none, and a
> numeric 0 is falsy so `?:` / `||` guards discard it silently.

`limit` is a **per-step option**, not a tag-level one. Every fan-out step (`ref` / `srcTermIn` /
`rows` / `src:query`) can over-produce; the cap belongs on the STEP that fans out, alongside that
step's field-ref arg on the chain wire. Today `src:ref` hardcodes limit-1 (collapse-to-first,
FW-32); this generalizes it to an author-set per-step cap.

**Default is NOT one global value — it's f(step-position, consuming-tag, output-mode):**

- **Intermediate vs terminal step.** An intermediate over-fan-out MULTIPLIES downstream work (each
  intermediate result spawns its own sub-chain / column reads); a terminal over-fan-out is just the
  visible row/item count. Different pressure → likely a tighter default on intermediate steps, looser
  (or none) on the terminal.
- **Consuming tag type.** `{{table}}` / `{{dl}}` WANT the terminal step multi-result (that's the row
  set) → terminal default high/unbounded. Scalar-output tags (`{{text}}`/`{{title}}`/… in string
  mode) want small-or-1. So the terminal-step default is supplied by the consuming tag, not fixed by
  the chain.
- **Output mode (see below).** `string` mode wants few; `ul`/`ol` mode wants the full set;
  `limit:1` collapses EITHER mode to a scalar.

So the resolved default = the consuming tag's per-position policy, overridable by an explicit
per-step `{step}-limit` on the wire. Wire datum: one more stable per-step arg (an integer — no
staleness concern, unlike the resolved-type case). Interacts FW-32 (which owns the
collapse-to-first→preserve-fan-out change `limit` presupposes).

**`limit` 0 = UNLIMITED — PROVISIONAL, pending a look at the GB editor interface (user, 2026-08-01;
not urgent, no picker offers the value yet).** Follow GB's editor-facing convention. Surfaced
rendering the value in the preview tool. The two readings pull opposite ways: **uncapped** (pass the
whole fan-out through, overriding a tighter intermediate default) versus **none** (a cap of zero,
which empties the chain so every downstream step and the read resolve to nothing). GB already uses 0
for unlimited in editor-facing number inputs, and our controls sit inside GB's editor — an author
carries one convention across both, so matching it costs nothing and diverging would be a trap.

**Verification owed before this locks:** the user is checking the GB editor interface directly. My
search supports the direction but does not establish it — see the correction below. Do NOT treat
this as settled in any downstream doc until that check lands.

Two things to carry to the build: the
**resolved** default policy above must treat 0 as "no cap" rather than passing it to a query arg
that reads 0 as none, and a **numeric 0 is falsy**, so any `$limit ?: $default` / `h.limit || ''`
guard silently discards exactly this value. The tool now tests `!= null`; the same guard shape
appears wherever an optional integer option is read.

⚠ **My "no precedent to inherit" was wrong** and the error is the recurring one: I reached for WP's
`-1` convention instead of checking the HOST plugin whose editor our controls live in. Note the
distinction that makes both true — GB's PHP query layer does use `-1`/`10` for `posts_per_page`
(`class-query-utils.php:262`, `class-query-loop.php:293`), so the 0-is-unlimited rule is an
editor-INPUT convention, not a documented constant; the string "unlimited" appears in no bundled GB
build (2.2.1 / 2.3.0 / 2.3.0-beta.2 / pro 2.6-beta.2 / pro 2.7-beta.1, searched 2026-08-01). Worth
re-confirming against the live editor at build.

Fixtures carry the value at three positions (intermediate, mid-chain-of-3 between two real caps, and
beside a kv token) plus two inside a slot, so the shape stays reviewable.


### SLASH candidate for `limit` — REJECTED 2026-08-01 (user). Bracket-kv `limit(N)` stands.

> **The decision and its reason, before the working record below.** "With `limit` on its own, I was
> prepared to reopen and switch to the slash. With `sep` in the picture, it doesn't work."
>
> **A step that can carry more than one option must spell them ONE way.** The slash was affordable
> while `limit` was the only per-step option — a single dedicated char against a single token. The
> moment a second option shares the step, the step string mixes two idioms:
>
>     refs,rel_post/2,sep(; )        one option by dedicated char, the rest by kv
>     refs,rel_post,limit(2),sep(; ) uniform — every option named the same way
>
> And the asymmetry is PERMANENT, not transitional: every future per-step option arrives as kv
> (nothing else scales — a dedicated char per option is not a grammar), so the slash does not become
> one member of a set, it stays the sole exception forever. That is the cost the three-item ledger
> below never priced, because each entry weighed the CHAR and none weighed the step's internal
> consistency. Per-step `sep` did not create this — it revealed it. `sep` is still speculative;
> the argument holds for whatever the second per-step option turns out to be.
>
> **Testing it was worth it** (user) — the candidate is now rejected on a reason, not on inertia, and
> the ledger below is the audit trail. Toggle + fixtures KEPT as a look-back; OFF is the decision.
> What survives independently: `limit` emits FIRST in the step (cosmetic under kv, which is
> order-free), the numeric-0 falsy guard, and the free-form escape on step `sep`.

**Working record of the candidate (kept for the audit trail — the decision is above).**

Walk back the bracket-kv `limit[N]` and spend a dedicated char on it: `/N` appended to the whole
step. Live in the preview tool behind a toggle (`S.limitStyle`, `bracket-kv` default | `slash`).

**Token ORDER — `limit` FIRST, directly after `slug,arg` (user, flipping the same day's first cut).**

    chosen    refs,rel_post/2,sep(; )      `/2` reads as "this hop, capped at 2"
    first cut refs,rel_post,sep(; )/2      `/2` floats off the end, past an unrelated value

Under the slash spelling the limit is not a member of the comma list at all — it binds to the step's
ARG, so it belongs against the thing it caps. **This retires the "limit last is forced" cost**
recorded in the first cut: that constraint came from the trailing POSITION, not from the slash char.
Bracket-kv is order-free (every token is named), so the order is cosmetic there — matched only so a
mode diff isolates the CHAR and nothing else.

**Per-step `sep` was modelled to make the comparison honest.** With ONE kv token per step the two
spellings look equally clean and nothing distinguishes them. `sep` is the likeliest second kv token
(today one TAG-level `sep` serves a whole fan-out; splitting it per-step is SPECULATIVE, not a
decided option — see `serializeStep`), so the tool now carries an optional per-step `sep` purely as
the second-token case. Probable format `sep(; )` — an ordinary bracket-kv token, i.e. exactly the
shape `limit` had before this candidate, which is the point: it obeys the same depth-alternation
rule, so at depth 0 it prints `sep(; )` and inside a slot's `src(…)` wrapper it prints `sep[; ]`.

**A step sep is FREE-FORM and must escape like one** — found on the first render of a ` | ` sep
fixture (2026-08-01), which emitted a raw `|` straight into the option list. The bracket is inert to
GB: it delimits for OUR parser only, so `\:`/`\|` escaping is the whole protection, exactly as for
`label`/`format`/`fallback`. Sep-value fixtures now cover a bare glyph with no padding, a value
carrying the STEP separator, one carrying the OPT/HOP separator, one carrying a GB-reserved char,
and a 3-hop chain with a different sep on every step — the case the shipped single tag-level `sep`
cannot express at all, which is the argument FOR per-step sep rather than a hazard.

⏸ **DEFERRED 2026-08-01 (user) — tracked as FW-61.** Modelled and rendered, not adopted. Its job
here is done: it existed to give a step a SECOND kv token, which is what let the slash-`limit`
candidate be judged and rejected. Settled by the same render: the wire shape and the escape
behaviour. NOT settled, and what FW-61 carries: whether the authoring surface is worth it, and how
it interacts with the tag-level `sep` it would not replace. Toggle stays (`S.perStepSep`, off).

⚠ **Own toggle, INDEPENDENT of the limit spelling (user, 2026-08-01).** The two were introduced in
one pass and that made them look coupled — they are not. Per-step `sep` is a change to the shipped
single-sep model that would arrive whichever limit form wins, so `S.perStepSep` is its own switch and
all four combinations are reachable. Only the sep-ON views are decisive for the limit question; the
sep-OFF views are what the wire looks like if per-step sep never lands.

| | bracket-kv (decided) | slash (candidate) |
|---|---|---|
| base tag, 2 hops, limits only | `src:refs,rel_post,limit(2);terms,category,limit(5)` | `src:refs,rel_post/2;terms,category/5` |
| base tag, + per-step sep | `src:refs,rel_post,limit(2),sep(; );terms,category,limit(5),sep(, )` | `src:refs,rel_post/2,sep(; );terms,category/5,sep(, )` |
| inside a slot (L2 brackets) | `2:src(refs,rel_post,limit[2],sep[; ];terms,category,limit[3],sep[, ]);key(other)` | `2:src(refs,rel_post/2,sep[; ];terms,category/3,sep[, ]);key(other)` |

**What the render shows.** The saving is real and grows with token count (`,limit[2]` → `/2`). With
the limit bound to the arg the step reads as one unit whose cap is attached at the point it applies,
and the remaining comma list is uniformly kv — arguably a cleaner shape than the bracket form, where
`limit` is a named token pretending to be peer to `sep` when it is really a modifier on the arg.

**⚠ The residual objection is WITHDRAWN (user, 2026-08-01): "sep will often have `,;:` — all
reserved."** `/` in a `sep` value is not a new class of problem; it is one more member of a set the
grammar already handles by design. A `sep` value is a joiner, so it routinely holds exactly the
chars the grammar reserves, and this was ALREADY true before the slash candidate existed:

    sep(, and )     STEP separator, inert inside the bracket
    sep(; )         OPT/HOP separator, inert inside the bracket
    sep(\: )        kv char — escapes, GB-level protection
    sep( \| )       GB option separator — escapes
    sep( / )        limit char under the candidate — inert, same as , and ;

The bracket is the general mechanism for a free-form value holding a reserved char, and `sep` is
precisely the option where that capability was already being exercised. So the candidate adds a
member to an existing set rather than creating a hazard. All five render and check clean.

I built the "frequency" case for this objection one turn earlier and it does not survive: I treated
`/`-in-`sep` as a new exposure without checking that `,` and `;` were already sitting in the same
position, doing the same thing, accepted. The reserved-char sep fixtures that disprove it were in
the file at the time — **the case for a cost has to be tested against what the grammar already
accepts, not just against the change.** (The check written for the objection was cut in the same
pass, for the separate reason that it fired on correct authoring; two earlier cuts of that rule were
wrong too — depth-only, which condemned every correct `src(…/3)`, then free-form-scoped.)

⚠ **The checker's first cut got this wrong and the fix is the reusable lesson.** It flagged the limit
char at any bracket DEPTH > 0 and reported six correct strings as faults — every `src(…/3)` in a slot.
The question is never "is the char inside a bracket", it is **whose bracket** — free-form
(`sep`/`label`/`format`/`fallback`) is a hazard, structural is the feature. The rule now walks the key
name owning each open bracket and tests against `RESPELL_FREEFORM`.

**Ledger — all three costs are now SPENT.**

| # | objection | status |
|---|---|---|
| a | `/` must leave the lenient hop class | **spent** — approved-twins-only removes `/` regardless; the candidate never had to pay for it |
| b | `limit` is forced last in the step | **spent** — a property of the trailing position, retired by binding the limit to the arg |
| c | glyph overlap with `sep` values | **spent** — `sep` already holds `,` `;` `:` `\|`; the bracket handles all of them, `/` is one more |

⚠ **This ledger reached the wrong conclusion, and the shape of the error is worth keeping.** With all
three entries spent I framed the decision as a bare preference between economy and inertia — and the
actual deciding cost was not in the table at all. Every entry weighed the CHAR (is `/` free, does it
force an order, does it collide with content); none weighed what having a dedicated char DOES to the
step string once the step holds more than one option. **An exhausted objection list is not an
exhausted analysis** — the ledger was built one objection at a time, so it could only ever
re-examine the axes already raised. The deciding axis (token-idiom uniformity, §above) was raised by
the user, from the same `sep` evidence that had just been used to clear entry (c).

**Full-set render, all four toggle combinations: 169 strings each, 0 problems.**


## `ul`/`ol` output-mode × `limit` (cross-ref §0-A of the handoff)

The multi-result output-mode option (`string`|`ul`|`ol`, `sep` nests under `:string`, key ≠ `as:` —
datetime_ collision) is the OTHER half of "what to do with a fan-out terminal step." **`limit` bounds
the result-set; output-mode shapes it.** Orthogonal, co-located on the same terminal fan-out step;
`limit:1` collapses either mode to a scalar. Blocked on FW-32 (fan-out preservation) same as `limit`.

**Output-mode is OWNED by `structured-output-tags-handoff.md` §0-A** (which tags qualify, key name,
`:string`/`sep` nesting — §0-Q1/Q2, survey phase). This section owns only the `limit` × output-mode
interaction; do not duplicate the output-mode design here.

## DECIDED — encoding + commit model (grill, 2026-07-27)

Two load-bearing decisions locked. Rest of the grill (control internals, `limit` defaults) still open.

### The hard constraint that drove it: SLOT-COMPATIBILITY

The chain is NOT top-level-only. A per-column mini-chain (`2-src:ref,office → …`, the worked
example's `2-src`) is a WHOLE chain nested under the `{N}-` column prefix. So the encoding has TWO
variable-length ordinals stacked: **column, then step.** Any encoding must nest a full chain under
`{N}-` cleanly.

**Verified fact that settled it (serialization-order.php:134):** the FW-52 slot parser is
`^(\d+)-(.+)$` — SINGLE leading ordinal only. Everything after the first `-` is the bare name. So a
second ordinal in the key (`2-s1-kind` or `2-step1`) parses as slot=2, bare=`s1-kind`/`step1` = an
UNKNOWN KEY_MAP entry → falls to end-of-group. **No encoding gets the step-ordinal ordered for
free** — the earlier "FW-52 handles D's keys natively" argument was WRONG at slot level (it only
handles the SLOT ordinal, never a nested step ordinal). That killed encoding D's load-bearing reason.

### DECISION 3 — intra-step token vocabulary (grilled + locked, 2026-07-27)

Each step in the value is `<slug>,<arg>`. Two disjoint slug families, uniform grammar, no collisions.
Names locked via `/grill-with-docs`; the governing PRINCIPLE (below) generalizes to future slugs.

| Family | Slug | Arg | Engine `type` | Input kinds | Card. | Reads |
|---|---|---|---|---|---|---|
| **hop** | `refs` | field key | `ref` | post/term/user/meta_row | N | `refs,related_staff` |
| **hop** | `terms` | taxonomy | `srcTermIn` | **post ONLY** | N | `terms,category` |
| **hop** | `entries` | field key | `rows` | post/term/user/meta_row/site | N | `entries,staff_members` |
| **start** (FW-39) | `post` | ID | (base source) | — | 1 | `post,9999` |
| **start** (FW-39) | `term` | ID | (base source) | — | 1 | `term,34` |
| **start** (FW-39) | `user` | ID | (base source) | — | 1 | `user,7` |

Future (not yet built, principle-checked): fixed relationships `parent`/`next`/`prev`/`author` (card.
1, singular), `children` (N, plural); `same*` criterion family (`sameAuthor`/`sameTerms`/`sameDate`/
`samePostType` — see principle escape clause); roots `query` (FW-54), `current`/`same`.

**THE GOVERNING PRINCIPLE (locked):**
> A step slug names its **hop category** (the relationship/source producing the next *field context* —
> a traversable resolved source you read fields off). Its **number signals fan-out cardinality**
> (plural ⟺ fans to N, singular ⟺ yields one) — **UNLESS the slug's FORM already implies cardinality**,
> in which case the noun's number is free to name the criterion/kind instead. Cardinality is
> form-implied for: the `same*` family (`same` prefix ⟹ "all siblings sharing X" ⟹ always N, so the
> noun names the MATCH CRITERION: `sameAuthor` = one-author-criterion, N-post output) and ROOTS
> (`query`, `<kind>,<id>` entity-starts — they PRODUCE a base source, they don't fan from an input, so
> they name themselves by kind/mechanism; `query` staying singular is correct).

Derivation (the grill path, for future reference):
- **The three fanning hops are a SIBLING FAMILY** — `refs`→post, `terms`→term, `entries`→meta_row —
  each yields a *field context* (a source you can read fields off). They differ only in WHICH
  relationship produced them. So they're named consistently: each names its relationship-category,
  plural (all fan).
- **`ref` → `refs`.** `ref`/`reference` is the GENERIC "a value referring to another entity's ID" —
  field-type-agnostic (spans ACF Relationship, Post Object, and non-ACF plain-meta-holding-an-id,
  CONTEXT.md:127). NOT the ACF "Relationship" mechanism name (that's `rel`, rejected as
  type-specific — doesn't cover Post Object). Plural per the principle: the relationship is
  one-to-many; a max-1 ACF field yielding a 1-list is `refs`-with-one-target, like `children` with an
  only child (populated cardinality ≠ relationship cardinality). **Migration:** the shipped two-option
  form `src:ref | ref:<field>` → the one-value chain `src:refs,<field>`. No coexistence — old form
  migrates; states are unmigrated / migrated / new. Needs a `deprecated-tags-options.md` row at build.
> **✅ RESOLVED 2026-08-11 — the banner below is kept for the reasoning, not for its status.** Both
> shipped renames are BUILT (`bws_migrate_base_src_chain` for base tags, both halves of the fold
> migrator for slots; the flat read is retained forever in `bws_fold_chain_from_options()`), and both
> now carry `deprecated-tags-options.md` rows in the Option name renaming tracker, marked
> *Implemented (v1.17.0)*. The rows record the two things this plan settled and the code cannot say:
> that these are SHAPE changes rather than renames, and that the flat SPELLING is closed while the
> flat READ has no deprecation path.
>
> ~~**⚠ MIGRATION HAS NO TRACKED HOME — flagged 2026-07-31 (user).** The DECISION 3 renames are
> DECIDED here and their `deprecated-tags-options.md` rows are twice called "needed at build" — but
> no row exists (verified: zero matches) and nothing outside this gitignored plan tracks them.~~
>
> **They do NOT all need migration — split by shipped-ness (user correction, 2026-07-31):**
>
> | Rename | Shipped? | What it needs |
> |---|---|---|
> | `srcTermIn` → `terms,<tax>` | ✅ shipped, author-visible | **Real migration** + deprecation row + two-era shim |
> | `src:ref\|ref:<f>` → `refs,<f>` | ✅ shipped, author-visible | **Real migration** + deprecation row |
> | `rows` → `entries` | ❌ **UNSHIPPED** | **No migration.** Implementation in `{{table}}` + fixture update only |
>
> `rows` is `{{table}}`-prototype vocabulary on the unreleased `1.17.0` branch; its only production
> occurrences are the ENGINE-INTERNAL step `$type` (`traversal-pipeline.php:136`, `:548`), never an
> author-facing wire key. No stored tag can contain it, so there is nothing to migrate and no
> deprecation row is warranted — writing one would document a rename no author ever saw. Rename it
> at the source, update the fixtures, done. **`srcTermIn` is the load-bearing one: 19 PRODUCTION files** under `includes/` +
> `assets/` reference it (49 repo-wide), including a shipped custom control (`bws-term-hop`,
> `assets/js/term-hop-control.js`), the serialization-order KEY_MAP + its JS port, the traversal
> engine, and the settings page. This is NOT spike-local like the other residuals — it is a
> shipped, author-visible option key with its own migration history already
> (`srcTerm`+`tax` → `srcTermIn`, `deprecated-tags-options.md:127-128`), so this would be its
> SECOND rename and the round-trip shim must handle both eras.
>
> Note the shape change is not a pure rename: `srcTermIn:<tax>` is a standalone OPTION KEY, while
> `terms,<tax>` is a STEP inside a `src` chain value. So the migration is key→chain-step
> (the same class as `src:ref|ref:<f>` → `refs,<f>`), not key→key, and it can only land with the
> chain encoding itself. **Belongs on the FW-56 tracker row, not left here.** Sequencing note: the
> shipped `bws-term-hop` control's whole reason for existing was dodging GB's reserved `tax` key —
> re-verify that constraint still binds when the taxonomy becomes a chain-step arg rather than an
> option key.

- **`srcTermIn` → `terms`** (NOT `postTerms`, NOT `termIn`). Family consistency wins over input-honesty:
  `refs`/`entries` don't encode their input; `terms` shouldn't either. The post-ONLY constraint
  (engine `bws_run_step` `srcTermIn`: `'post' !== $kind → array()`) is enforced by the engine's
  silent-empty guard + the editor picker graying `terms` when the current source isn't a post — NOT by
  slug spelling. Bonus: generalization-safe if the term-hop ever accepts non-post input. The legacy
  `src` prefix of the option key was noise inside `src:` — dropped.
- **`rows` → `entries`** (NOT `rows`, NOT `repeater`). Two strikes on `rows`: (1) **presentation-leak** —
  "rows" is L3-table-orientation vocabulary at the L1 source layer; the same chain feeds `{{text}}`/
  `{{join}}`/`{{dl}}` (no rows) and a table may even render entries as COLUMNS depending on
  orientation. A source slug must not presume a downstream consumer's shape. (2) **family-break** —
  `refs`/`terms` name the relationship-category; `rows` named a shape. `entries` = ACF's own UI word
  for repeater rows, presentation-neutral, plural (fans ✓), names the repeater's contents-as-
  relationship. `repeater`/`reps` rejected: singular-for-a-fanning-step (the exact inconsistency this
  grill fixed) AND ACF-mechanism-specific (the `rel` flaw). Cost: wire `entries` ≠ engine
  `rows`/`meta_row` — the already-accepted wire≠engine split (like `terms`≠`srcTermIn`). Watch:
  "entries" collides with form-submission vocab (GravityForms/CF7) — different context, no such source
  exists/planned; if one lands it's a `src:`-level source, not this hop.
- **Entity-START (FW-39) = `<kind>,<ID>`** — `post,9999`/`term,34`/`user,7`. FORCED by constraint 1:
  the editor has no bound entity to resolve a bare ID against, so KIND must be on the wire to scope the
  picker statically. `id,9999`/bare `9999` rejected (kind not statically derivable). Matches the engine
  `{kind,id}` shape 1:1. Roots: only the FIRST chain step may be an entity-start. **Parser-recovery
  affordance → routed to FW-39** (build-time, NOT today): on `term,<arg>`, if `<arg>` is non-numeric it
  can't be a term ID → recover as a `terms` hop (`<arg>` = taxonomy). Unambiguous because term-IDs are
  numeric and taxonomy slugs non-numeric (disjoint value-spaces) — guards the unique `term`/`terms`
  dropped-`s` mis-edit hazard (only pair where the singular is ALSO a valid other slug). Confirm at
  build no taxonomy can have a purely-numeric slug.
- **No collision:** start slugs {`post`,`term`,`user`} ∩ hop slugs {`refs`,`terms`,`entries`} = ∅.

Worked forms:
```
src:refs,related_staff/terms,category    row chain: staff via reference field → their category terms
src:post,9999/refs,office                 start from post 9999 → hop to its office reference field
2-src:refs,office                         per-column mini-chain (column prefix + same grammar)
```

### ✅ ROOT IMPLIED + the migration's real SCOPE (user, 2026-07-31)

Both halves lived only on the FW-56 tracker row until 2026-08-04, when the row was cut back to
pointer shape. They are recorded here because the tracker indexes decisions, it does not hold them.

**SCOPE: the chain encoding touches EVERY TAG whose source is serialized — base tags included, not
only slots.** This is FW-56 reach, NOT FW-57's: the fold is explicitly slots-only (base tags keep
`src:` + `use:`/`key:`), so the migration gate is *`src` becoming a chain wherever `src` appears*, and
slots inherit it because slots carry chains too — not the reverse. Two consequences:

1. **Do NOT sequence this as before/after the slot fold.** The migrator is already walking `src` on
   every affected tag, so `srcTermIn` is one case inside a rewrite that has to happen anyway; landing
   it separately walks the same tags twice.
2. **The partial-migration hazard is TAG-level, not slot-level** — "some tags' `src` converted, some
   not" across a site, with `srcTermIn`'s two prior eras underneath. The mixed-era accumulator
   threading the fold solved (I13, era-per-slot) is the slot-level instance of a tag-level problem.

**Scope is NOT bounded by the strip rule.** `src:current` never serializing bounds tags whose SOURCE
is ambient — but `srcTermIn` is a standalone option key describing a hop applied TO the ambient root,
so a tag can carry `srcTermIn:category` with NO `src` at all. Migrating it into a chain step
necessarily GIVES an ambient-only tag a `src` token it never had:
`{{text srcTermIn:category|key:foo}}` → `{{text src:terms,category|key:foo}}`. So the affected set is
**tags with a serialized `src` OR `srcTermIn`**, and the second group is precisely the ambient tags an
earlier draft called untouched. A chain must state where it starts before it can state the hop, which
makes the implicit root explicit.

**ROOT IMPLIED: a migrated chain does NOT lead with its ambient root.** `srcTermIn:category` →
`src:terms,category`, never `src:current;terms,category`. One rule governs both levels — **`current`
rides the wire only where it is doing work**, i.e. where absence would mean something else:

- **slot 2+**, where absent = inherit the `same` carry-forward, so an explicit `current` is the RESET
  that re-roots the slot at ambient (see §Flip decisions (5d), "Empty chain at slot ≥2 = RESET");
- any future level with the same three-state shape.

Everywhere else `current` is the stripped default and stays off the wire — identical to the shipped
strip strategy, now stated as a LEVEL-AWARE rule rather than a flat one. That asymmetry is the reason
the strip rule cannot be applied uniformly: a slot has three states (absent = malformed per Matrix A,
`same` = inherit, `current` = reset) where a base tag has two.

Verdict reached by VISUAL REVIEW, per the standing methodology note (both prior schema changes shipped
syntax that did not survive one): `current` on effectively every chain was too noisy to justify, and
no counter-argument cleared that bar. **Two arguments for the explicit form were examined and did not
survive:**

1. *"FW-32 will force the root back anyway"* — **withdrawn, it was wrong.** FW-32's
   pick-primary-then-hop is consumed by the **entity-start roots** (`post,9999`/`term,34`/`user,7`),
   not by a `current` token, so a chain rooting anywhere other than ambient already states a DIFFERENT
   root. `current` is only ever the value you would omit — redundant by construction rather than
   merely verbose — and no second migration is created.
2. *"static computability (constraint 1) needs the root stated"* — weak: `current` does not resolve
   the ambient entity either, so the editor is equally blind with or without it.

**Residual cost, accepted:** the parser must INFER root-vs-hop for step 0 from the slug. Cheap and
already safe by DECISION 3's own principle — hop slugs are PLURAL (`refs`/`terms`/`entries`),
entity-start slugs SINGULAR (`post`/`term`/`user`), so the vocabularies are disjoint by design. **Build
guard:** assert that disjointness explicitly, because the grammar validator checks separator classes
and NOT slug-namespace collisions, so a future slug could silently straddle the two families.
(Shipped 1.17.0 as `bws_fold_chain_root()`'s root-vs-hop split — CONTEXT.md I14.)

**Sequencing check carried to build:** `bws-term-hop` exists specifically to dodge GB's reserved `tax`
key — re-verify that constraint still binds once the taxonomy is a chain-step ARG rather than an
option key.

### DECISION 1 — step ordinal lives in the VALUE (one key per level)

Each src level = ONE option key whose VALUE encodes the ordered steps:

```
TAG-GLOBAL:   src:refs,related_staff/refs,office
PER-COLUMN:   2-src:refs,office/refs,manager
```

- **Wire grammar (PLACEHOLDER separators, still open below):** value = `/`-joined steps; each step =
  `,`-joined `kind,arg[,limit]`. `/` = ordered hop (FW-32's note; wire-safe, reads as path). `,` =
  intra-step (join precedent). Both `gb-constraints.md`-safe; no `}`, no `[`/`]` needed.
- **Why:** the slot prefix stays SINGLE-ordinal → the FW-52 parser + KEY_MAP are UNTOUCHED. The step
  structure is deliberately OPAQUE to serialization-order because it lives inside one key's value, not
  across many keys. One key per level nests trivially under `{N}-` (just prepend the column prefix).
- **Rejected:** D (second key-prefix `2-s1-kind`) — stacked ordinals need a KEY_MAP + parser
  extension AND bloat the wire ×2 axes. A (numbered stem `2-step1`) — same unknown-key fallback as D,
  plus positional-CSV intra-token order management. Both lose once slot-nesting is the lens.
- **Cost accepted:** a value sub-parser (split `/` then `,`) — but it's small, pure, harness-able
  (`bws_run_traversal` already consumes `[{type,field,limit?}]`; the parser just builds that array).

### DECISION 2 — editor control builds the value incrementally; control is editor source-of-truth

Constraint 3 (pickers rescope mid-build) is met by having the custom chain control **ALWAYS hold
parsed step-state and rewrite the whole value on every step commit**; the slot picker reads the
CONTROL's live parsed state, NOT a fresh parse of the raw wire each keystroke. This is a
picker-plumbing convenience, NOT a claim that the wire is opaque.

**The raw wire is NOT opaque to humans (user, 2026-07-28 — now `docs/adr/0004`).** The `src:...` value
is ALWAYS visible to the author and MAY be hand-edited at their discretion. So human-readability of the
raw value is a REAL wire requirement (weighs on the separator/grammar choice — see §Parens question),
and the parser MUST round-trip an author's hand-edit back into control state, not only consume its own
output. The control is the editor source of truth for LIVE picker scope; the wire is the source of
truth on load/reopen and after any hand-edit. This is the cross-cutting principle recorded in
**ADR 0004** (human-readable/hand-editable wire); it binds every option grammar, not just the chain.

- Author adds a hop → control appends to its state array → serializes the full value
  (`src:refs,related_staff` → `src:refs,related_staff/refs,office`). Picker scopes off the control's
  terminal step immediately (precedent: `field-combo-control.js` reads `state.key` reactively). On
  load / hand-edit, the control re-parses the wire into that same state.
- **Rejected the sidecar** (a `2-tail:refs,office` cache of the terminal step for the picker): it's a
  DERIVED value on the wire → brushes constraint 4 (never serialize a derived/cacheable datum; desync
  risk, needs revalidation). The control-holds-parsed-state model needs no wire cache.

### What these two settle (sub-decisions now moot) — and WHY

**Root cause of all three:** they were live questions ONLY because the earlier framings put chain
structure at the KEY level (one key per step). Moving the structure INTO THE VALUE (DECISION 1)
dissolves all three at once — that is the actual reason value-side won, not an incidental perk. Each
question's precondition disappears:

- **`/`-vs-`+` separator (FW-32) — MOOT.** The debate had two *meanings* in play: does a hop separator
  read as ordered-traversal (`/`) or set-union (`+`)? Only two answers because two meanings competed.
  In-value, the separator is positional between ordered segments and its meaning is FIXED (the fold is
  inherently sequential; fan-out is what each step DOES, not an operator the author signals). One
  meaning survives → the question collapses to "pick a wire-safe char," no semantics to settle.
- **Positional-vs-pairs (FW-24) — MOOT *for the chain*, still live for FW-24.** FW-24's fear is
  serialization-order fragility: positional CSV breaks when args are MANY + OPTIONAL + REORDERABLE, so
  you'd want self-describing pairs. That precondition is ABSENT here — a chain step has 2-3 args in
  engine-FIXED order (`kind,arg,limit?`), no author-visible reordering, no sparse middle. Positional
  is safe *specifically because the fragility precondition doesn't hold at this scale.* It stays live
  for FW-24's richer whole-tag-in-slot payload (many args) — decoupled, that's FW-24's call.
- **Serialization-order for variable chains (interacts FW-52) — MOOT.** The scary one: FW-52's KEY_MAP
  assumes a fixed key set, so a variable-length chain = variable key count = keys the normalizer never
  saw. In-value, the chain is ONE key (`src`) whose count NEVER varies regardless of depth — depth is
  absorbed into a value the sorter doesn't inspect. FW-52 ranks `src` exactly as today. The thing that
  would've varied (key count) no longer varies.
- **Double-ordinal KEY_MAP extension — NOT needed.** Avoided by keeping one key per level (the
  slot-compat driver above).

The common thread: **each was a downstream consequence of key-level chain structure; the value-side
move removes the cause, so all three effects vanish together.**

### Validator — the tag-string-preview tool (`tools/preview/tag-string-preview.html`)

Before building, the planned serialization gets validated in the hand-run HTML preview tool (moved
out of `.claude/` to `tools/preview/` 2026-07-27 — it's a reusable dev validator, git-tracked +
`.distignore`d like the rest of `tools/`). It already carries a `srcRel` hop-chain EXPERIMENT
(`buildRelParts`, the `csv` vs `repeater` radios) that prototyped exactly this question — the
DECIDED value-side encoding maps onto its `csv` mode (one option, positional). Extend that experiment
to the final grammar (`src:kind,arg[,limit]/kind,arg…` + the `{N}-` column nesting) and eyeball the
serialized strings across base + try_ slots + column mini-chains before any PHP/JS lands. The tool's
per-slot prefix + key-name preset machinery already exercises the slot-compat nesting the encoding
hinges on.

### Still open after these two

- Intra-step `limit` position in the CSV (`kind,arg,limit` vs `kind,arg` + a separate mechanism) —
  ties to the `limit`-default policy above.
- The control's own internals: fixed-revealed-slots (show_if chain, cap N, ships on existing tech,
  shares FW-45 cap) vs a true append-a-step `bws-chain` custom control (unbounded, new control work,
  `docs/editor-controls.md` owner). v1 = fixed-slots leaned; append-a-step graduates (mirrors join
  10-cap → FW-45). NOT locked — needs its own pass.
- `use`/`key`/`label` per-column options already sit BESIDE the chain value under the same `{N}-`
  prefix (`2-src:…/…` + `2-use:title` + `2-key:address`) — confirm no collision between the chain
  value's internal separators and those sibling keys (there isn't: siblings are separate `|` options;
  the value's `/`,`,` live entirely inside the one `src` value). Note for the parser spec.

## Open — remaining after the 2026-07-27 grill

The grill RAN (2026-07-27); DECISIONS 1/2/3 + the `ref`-scope derivation are LOCKED above. Most of
the old open list is now resolved or moot — recorded here so it isn't re-litigated:

- **Wire encoding (pairs/positional/bracket) — RESOLVED.** DECISION 1 = positional, value-side, one
  key per level; DECISION 3 = intra-step slug vocabulary. FW-24's bracket-grouping stays FW-24's call
  (richer whole-tag-in-slot payload; decoupled, L349-354).
- **Chain-step separator meaning (`/` vs `+`) — MOOT** (L344-348): in-value the separator is
  positional between ordered segments, meaning fixed. Only the literal CHAR is open (below).
- **Serialization-order for variable chain — MOOT** (L355-359): one `src` key, count never varies
  with depth; FW-52 KEY_MAP untouched.
- **Discovery scope derivation — RESOLVED** (§The `ref` post-type scope): live `post_type[]` union
  from the payload, no stamp, no serialized type. Consolidating the derivation into one scope-hint
  carrier is an implementation call, not a wire datum.
- **Per-column `{N}-src` nesting — RESOLVED** (DECISION 1, L152-154): a column IS a mini src chain;
  one key per level nests under the `{N}-` prefix trivially, no second ordinal.

**Genuinely still open (carry into build / a follow-up pass):**

> ⚠ **THREE OF THESE FOUR SHIPPED — struck 2026-08-20, not deleted.** This list predates the build and
> was never rechecked against it, so it sat under a "genuinely still open" heading describing decisions
> the wire has since made. That is supersession in place, in the one part of this file the §SETTLED
> index does not cover — the index guards the rows it lists, not prose sections that carry their own
> open list. Only item 3 is live.

1. ~~**Literal separator chars.**~~ ✅ **SHIPPED** — `;` between steps, `,` intra-step
   (`BWS_FOLD_STEP_SEP` in `slot-fold.php`; `refs,office;terms,department` in `tag-reference.md`).
   `/` and `+` are RESERVED rather than merely rejected. The escaping hazard the item names was
   answered by the free-form escape discipline, not by char choice.
2. ~~**Control internals: fixed-revealed-slots vs true append-a-step `bws-chain`.**~~ ✅ **SHIPPED as
   APPEND-A-STEP**, i.e. the option this item leaned AGAINST for v1 — `+ Add step` in
   `assets/js/slot-fold-control.js`, gated on the offerable steps rather than on the registered list.
   The `show_if` reveal chain it proposed keeping is what the repeater replaced.
3. **Author PT narrowing/override serialization** (L94-100) — **the one still live.** Echoed at §The
   `ref` post-type scope. See below.
4. ~~**Intra-step `limit` position.**~~ ✅ **SHIPPED as BRACKET-KV INSIDE THE STEP** — `limit(3)`, not
   the positional CSV `kind,arg,limit` and not a separate `{step}-limit` key. The default policy it
   was to be decided with is now `docs/adr/0005`.

**The live one, restated:**

3. **Author PT narrowing/override serialization** (L94-100): ephemeral view-filter LEANED; whether an
   author-asserted out-of-config PT should SURVIVE remount (serialize as separate author-intent,
   re-validated live each load) is undecided. Tension: persistence vs the staleness rule (constraint
   4). Do NOT default either way. May generalize past `ref`.

## Slot-payload fold — read-step + per-slot intent radio (CONCEPT, 2026-07-28)

**Status: CONCEPT, settled shape, spelling PINNED-OPEN.** Explored 2026-07-28. Extends the DECIDED
src-chain encoding to fold the FIELD READ (`use`/`key`) into a slot's chain value, and proposes a new
per-slot control model to kill the current slot-UX horror. Distinct from FW-56 proper (the chain
wire) and FW-24 (whole-heterogeneous-tag-in-slot) — this is the tractable middle: a slot value =
`chain + terminal read`. **Tracked as FW-57** (`docs/future-work.md`; assigned since this section was
written — the "numbering deferred" note it carried is closed). Control-layer detail eventually homes
in `docs/editor-controls.md` /
`.claude/plans/combined-option-controls.md`; the WIRE half homes here.

### The read folds into the chain as a TERMINAL step (slots-only)

> **⚠ SUPERSEDED in part (2026-07-31 assessment):** the read is now a SIBLING bracket-kv token
> (`src(chain);use(x)`), NOT a `/`-joined chain terminal — see §Sandbox convergence + §Follow-up
> passes Pass 1/2. The slots-only scope + coverage rules below still hold.

A **slot** (try_ candidate / join value / table column) is uniformly a *single-reader* chain:
`source-chain → read`. The read (`use`/`key`) is the chain's **terminal step** — it yields a VALUE
instead of another traversable *field context*. So it folds into the slot value the same way hops do,
using the DECISION 3 grammar.

- **SLOTS-ONLY (locked 2026-07-28).** Base tags (`{{text}}`/`{{title}}`/…) keep `src:` + separate
  `use:`/`key:` — the zero-hop base fold is all cost, no payoff (`src:site/key(X)` reads WORSE than
  `key:X`; user confirmed). The read-step family exists ONLY inside a `{N}:` slot value.
- **Coverage:** try_ / join slots (unbounded chain root — any entity-start) AND table columns
  (ROOTED — the chain starts at the shared row entity, the `entries` terminal; a column may only hop
  FROM there, no entity-start; matches table-plan #7 `bare|ref` restriction + #8 column-src-implicit).
  Same grammar, different root constraint.

### The read-step is a BARE SLUG — no `use` carrier (user, 2026-07-28)

> **⚠ SUPERSEDED in practice (2026-07-31 assessment):** spike canon emits `use(title)`/`use(same)`
> for analog + inherit reads (control.js:141-143, all PF fixtures); only `key(x)` stays bare and
> `default` drops. Ratify-or-revert is §Follow-up passes Pass 2 item 1. The `text`-is-a-non-type
> lock below is untouched.

`use` was a redundant KV carrier. As a chain step the analog NAMES ITSELF, exactly like hops
(`refs`/`terms`) do. The terminal read splits into the same nullary/unary shapes as the rest of the
grammar:

- **analog read = bare slug**, nullary: `title` / `excerpt` / `featured` / `default`. Names the datum.
- **key read = unary**: `key(field)` — the one read that needs an arg. *(Spelling `key(field)` vs
  `key,field` is PINNED to the chain-grammar `,`-vs-paren hold — see Pinned-open below.)*
- **absent read-step ≡ implicit analog ≡ `default`** — a chain with no terminal read falls to the
  tag's best intrinsic analog for the terminal source (tag-reference §Design principle L56). Most
  slots write NO read-step. So `refs(staff)` alone = the staff analog; `/default` is just its explicit
  spelling.

This is MORE consistent with DECISION 3, not a special case: every step is `<slug>` or `<slug>(arg)`
— hops/starts are unary, reads are mostly nullary + `key` unary. Collision-safe: analog slugs share
the flat step namespace with hop/start slugs but a read is TERMINAL-ONLY, so position disambiguates.

**Nullary scope = analog-bearing (tag × terminal-source) pairs only (grill-accepted 2026-07-28).**
A bare nullary read-step (`/title`, `/excerpt`, `/default`) is valid IFF the (tag × terminal-source)
pair has an intrinsic analog. Keyed-only tags on keyed-only sources (`text`/`datetime_*` everywhere;
`email`/`phone` on post/term) REQUIRE a `key(...)` field arg — bare `/email` on a post terminal is a
parse/author error. This inherits the base-tag reality verbatim (tag-reference:60-67 analog table:
`text`/`datetime_*` "key required in all contexts") — and it IS the anti-drift discipline: the slot
read-step derives its analog-or-not property from the base tag, so `/text` nullary is invalid
automatically. Makes the illegal form UNREPRESENTABLE (the container-conditional-type principle).

Two FORWARD caveats (accepted, refine the rule — do NOT break the accept):
- **Nullary validity is `f(tag × terminal-SOURCE)`, not `f(tag)` alone.** `email` is analog-less on
  post/term but `src:author/email` (user terminal) HAS one — `user_email` is the user's email analog.
  So the source-dependent analog table (tag-reference:60) extends to email/phone on the USER row: a
  chain landing on a user makes `/email` / `/phone` nullary-valid there. `src:author/email` is the
  sensible target (user, 2026-07-28). The tag is analog-less on MOST sources, not globally.
- **Admin-configured global-default analog (parked future, generalizes FW-34 / GH #29).** Admin picks
  e.g. "the term image field" → it becomes the `default`/`featured` analog for a term source, filling
  the tag-reference:65 *(none — terms have no native image)* gap — and is CROSS-TAG (one config can
  serve image + others). Consequence: the analog table gains ADMIN-CONFIGURED rows → "does (tag ×
  source) have an analog?" becomes partly RUNTIME, not purely static-code. Watch against FW-56
  constraint 1 (terminal type statically computable, no live probe): the admin config must be readable
  at EDITOR LOAD (like the discovery payload), not per-render, to keep the nullary-slot picker's scope
  static. Likely fine (config is load-time-readable) but it is a NEW derivation input — confirm at
  build. See memory `project_default_field_keys.md`.

**Interacts with the `default` rename** (analogs → one consistent `default` value — under separate
consideration, tag-reference). This fold REINFORCES it (absent ≡ `default`) but does not settle it.

> **⚠ ASSESSED 2026-07-31 — NOT a build carry-in, and UNTRACKED.** Two findings:
>
> **1. It is unshipped and unwritten.** `use:default` has ZERO production occurrences as an analog
> value — today every tag names its own (`title`/`content`/`permalink`/…). The rename is a PROPOSAL
> to unify them, not existing state, so there is nothing to migrate and no deprecation row is due
> until it is decided.
>
> **2. The coupling is already discharged by the anti-drift obligation**, not by anything the fold
> must do. §The ONE real obligation (L901-908) requires the slot read control derive its analog
> enum from the base tag's read definition (a `bws_build_slot_read_options()` twin), explicitly so
> that *"a base change (new analog, the `default` rename) propagates to slots automatically"*.
> Honor that and the rename costs the fold NOTHING whenever it lands — before or after. Violate it
> (hand-author the slot read enum) and the rename becomes a two-place edit that can silently
> diverge. **So the real dependency runs the other way: the `default` rename depends on the fold
> honoring anti-drift, not the fold depending on the rename.**
>
> **TRACKER HOME FILED 2026-08-18 — `docs/future-work.md` FW-80**, pointing at
> [`handoff-1-source-analog-and-contextual-controls.md`](handoff-1-source-analog-and-contextual-controls.md)
> §STILL-OPEN decisions 2 + 3, which had held the question all along. Still not definite (user).
>
> Drop it from the FW-57 build carry-in list — it is neither a defect nor a blocker. What it DID
> need was a tracker home: it existed only in this gitignored plan and as the bare phrase
> "`default`-rename coupling" inside the FW-57 row, with no `docs/future-work.md` row of its own and
> no `tag-reference.md` section under that name despite being cited as living there. **Either file
> it as its own FW row or fold it into the tag-naming track** (it is the same track as the parked
> `{{text}}`→`{{field}}` footnote at L568 — both are "analog/tag vocabulary consistency", and they
> should be decided together rather than piecemeal).

**`text` is a NON-TYPE — no `text` token, ever (grill-locked 2026-07-28).** The tag-slug names a
TRANSFORM: `email` validates+wraps, `phone` normalizes, `datetime` formats, `image` renders. **`text`
applies NOTHING** — raw field value out; it IS `{{field}}` (identity read). So the tag-slug is present
IFF there's a real transform to name, and its ABSENCE means identity read:

| Read | Transform | Wire (agnostic container) |
|---|---|---|
| plain field read (identity, = `{{text}}`/`{{field}}`) | none | `key(x)` — **NO type token** |
| email / phone / datetime / image read | the transform | `email;key(x)` / … |
| analog read | the analog | `use(title)` / `use(excerpt)` / *(`default` drops)* |
| inherit | — | `use(same)` |

*(Wire column corrected 2026-07-31, Pass-5 audit — it predated two supersessions: the type is a
STANDALONE `;`-separated token, not a `,`-prefix on the read, and analogs ride the ratified `use(...)`
carrier rather than a bare slug. **The decision this table illustrates is untouched.**)*

Encoding an explicit `text,` token would encode the ABSENCE of a transform — **over-encoding a no-op,
rejected** by the no-bare-default discipline (memory `feedback_no_bare_default_value`). This is NOT
S1-inconsistent: S1 forbids eliding a MEANINGFUL token (`same` = the inherit directive); `text` carries
NO meaning to elide (null transform), so omitting it isn't an implicit default — there's nothing there.
Holds in BOTH containers: agnostic `key(x)` = plain read / `email;key(x)` = email read; try_text `key(x)`
with no `text;` because text isn't a nameable transform. **No orphaned analog trigger** (user worry,
resolved): `{{text}}` has no analog by definition (tag-reference:66 "key required in all contexts"), so
there's no nullary `text` form needed either. text's two specials CONVERGE — no analog (⇒ no bare slug)
AND no transform (⇒ no tag-slug) ⇒ text's ONLY representation is `key(x)`, nothing left over. Footnote:
this makes base `{{text}}` arguably misnamed (`{{field}}`) — parked `default`-rename / tag-naming track,
not this decision.

### Container-conditional TYPE token (formerly "Option R"), redundancy-free (locked 2026-07-28; mechanism corrected 2026-07-31)

> **⚠ MECHANISM CORRECTED 2026-07-31 (Pass 5 audit).** The DECISION below is unchanged and live.
> What changed is HOW the type rides the wire. This section was locked 07-28 while the read was a
> chain TERMINAL step, and it described the type as *"the read-step's leading token"* (`email,key(x)`).
> **That mechanism died with the terminal-step model** (§L477 ⚠, recorded Pass 1) and was never
> propagated here — the type is now a **STANDALONE slot-level token**, sibling to `src(...)` and the
> read, not a prefix on anything: `[type];src(chain);use(x)|key(x)` (`proto-fold-tag.php:31`, parsed
> as its own field at `:133`). **Renamed from "Option R"** in the same pass: the letter came from a
> rejected-alternatives lettering (vs Option U below) for a read-step variant that no longer exists,
> so the letter now implies a shape the design does not have. Old cross-references to "Option R" mean
> this section.

Does a slot NAME its PROCESSING TAG (the format/type: `email`/`phone`/`title`/…)?
**Only when the container is FORMAT-AGNOSTIC** — the type token is present IFF the container imposes
no per-slot format:

| Container | Per-slot format | Type token present? | Slot shape |
|---|---|---|---|
| `try_<tag>` (format-FIXED — `try_email`, `try_phone`, …) | the tag IS the format | **NO** | `src(chain);use(x)`\|`key(x)` |
| `{{join}}`, `{{table}}` column (AGNOSTIC combiner) | none — combiner is format-blind | **YES** | `<type>;src(chain);use(x)`\|`key(x)` |

This delivers **FW-24's heterogeneous-tag-in-slot for free on the agnostic containers** (each join
slot / table column names its own processing tag — `1:title | 2:phone;key(mobile)` = different tags
per slot, NO recursive braces) while keeping `try_<tag>` slots minimal (no redundant type).

**The standalone form is strictly better than the superseded read-step-prefix form**, which is why
the correction is not a regression: the type governs the WHOLE slot (it selects the handler that
processes the datum), so attaching it to the read step scoped it to the wrong thing — a slot's type
is not a property of its read any more than of its source. As a sibling it also composes with the
multi-option terminal tags deferred below (a datetime slot's `format` becomes another sibling, not
another read-step arg), and it keeps the read token identical across both container classes, so a
parser reads the read the same way regardless of container.

**Rejected Option U (always-emit-type, even in `try_email`):** its ONLY advantage is a future
migration to a FULLY-AGNOSTIC `{{try}}` (per-slot type like join) with zero slot rewrite. But that
migration is REJECTED — output types don't mix (phone/email/url/url-vs-alt can't cross; user, twice),
so a unified `{{try}}` stays **type-FIXED** (`{{try type:email}}`) or expands only WITHIN an
output-SHAPE family (text-shaped: field/title/excerpt/image-as-alt — `try_text` "needs MORE options,
not fewer", user 2026-07-28). Under a type-fixed collapse the container STILL supplies the type →
the container-conditional type migrates rewrite-free too → U buys nothing. Worse, U makes the illegal cross REPRESENTABLE
(a `try_email` slot could name `phone`) → validation surface + author confusion; R makes it
UNREPRESENTABLE by construction (no type slot in a fixed container). **The wire shape TELLS the
container class:** type-present ⟺ agnostic.

**Output-SHAPE cut (the crossing rule, for when a container DOES allow cross — text-try / the
`{{text}}` `use:title` edge):** crossing is safe ⟺ SAME OUTPUT SHAPE. Text-shaped family
{`text`, `title`, `excerpt`, `image as:alt|caption`} all emit a plain renderable string → cross
freely. Typed {`email`, `phone`, `image as:url|id`, `permalink`} emit validated/structured values →
NEVER cross. **`content` is text-ish but functionally TABLE-like** (block-formatted output via
`bws_render_block_content`, not a bare string — user 2026-07-28) → does NOT sit cleanly in the
text-shaped crossing family; flag before any shape-family rule absorbs it. This reframes the
`{{text}}` `use:title` "edge" as the principled text-shaped cross affordance (tag-reference:97
feedstock note): a text-try slot draws from a per-source text-shaped CAPABILITY SET, not the base
`text` enum — the #26 decouple the note wants; the slot names its read (`title`/`excerpt`/`key(x)`)
directly, no values hung on `text` to pipe into `try_text`.

**Forward note:** if a fully-agnostic `{{try}}` ever DOES become real (not currently planned), a
one-time mechanical slot-rewrite injects the type into existing `try_<tag>` slot wires (`key(x)` →
`<tag>,key(x)`), a `deprecated-tags-options.md`-style migration pass. Revisit R only then.

**Try unification itself is PARKED** (separate track) — this rule only needs the format-FIXEDNESS
property of today's containers (try_<tag> fixed, join/table agnostic), which holds regardless of
whether/how try unifies.

### `same` ALWAYS serialized — no "absent" wire state (S1, grill-locked 2026-07-28)

> **⚠ PHRASING CONTRADICTED (2026-07-31 assessment):** "always fully specified" is wrong —
> `default` analog DROPS on emit (absent-read = default IS a wire state; frontrunner table col 1).
> The CORE survives: absence never means INHERIT (repeater re-affirms; empty chain slot ≥2 is
> malformed/flagged). Reword as inherit-axis rule — Pass 1; formal rule — Pass 2 item 2.

The collapsed slot encoding DISSOLVES the old implicit-in-slot collision (tag-reference:58 — "slot
wire-absence means inherit, not analog"). That collision was an artifact of the OLD wire, where a
slot's read lived in a separate `{N}-use`/`{N}-key` option that could be ABSENT, and absence was the
only carrier for "same as previous." In the collapsed wire, inherit is the **explicit `same` token**
on BOTH axes (Path A model) and the DECISION 2 control rewrites the whole value on every commit — so
writing `same` explicitly is free. Consequence: **a slot value is ALWAYS fully specified; "absent
read-step" is not a wire state.** Two read states, no ambiguous third:

| Slot read axis | Means |
|---|---|
| `same` (explicit) | inherit prior slot's read |
| `title` / `key(x)` / `default` / … | specific read (incl. explicit analog `default`) |

`default` is now a plain positive read token, no longer overloaded against absence — the collision
has no wire state to live in. Slot 1 cannot write `same` (no prior — control hides it / validation
error); slot 2+ writes `same` or a positive token.

**Rejected S2** (omit-means-`same`, terser wire): it re-introduces position-dependent absence (slot-1
absence historically = analog, slot-2+ = inherit) — the exact mess the collapse escapes. S1's
verbosity cost is small and reads self-documenting per ADR 0004 (`2:src(refs,office);use(same)` =
"slot 2: from the office ref, same read as before" — spelling corrected 2026-07-31 from the dead
`/`-joined `2:refs(office)/same`; the argument is unaffected and the canonical form is if anything
MORE self-documenting, since every token is named).

**Strip-default posture shift (user, 2026-07-28):** the more options collapse, the LESS we strip — a
reasonable tradeoff. `same` as an always-written token is the OPPOSITE of strip-default (memory
`feedback_no_bare_default_value`), and that is FINE here: `same` is not a stripped *default fallback*,
it is a positive author DIRECTIVE (inherit). Strip-default governs not-serializing a fallback; `same`
isn't a fallback.

### Migration of shipped try_/join to the collapsed encoding (a+b grill-accepted 2026-07-28)

Table is unshipped (greenfield, no migration). try_ + join are shipped → their wires migrate.

**(a) Rides the EXISTING `MigrationRegistry` tag-string-rewrite precedent — no new mechanism.** The
near-identical precedent is `bws_migrate_image_as_size_fold` (deprecated-tags.php:1016): it folds
separate `{N}-as`+`{N}-size` keys → one `{N}-as:mode,size` value, looping slots `''/2/3/4/5` via
`MigrationRegistry::parse_tag_string` → transform → `format_tag_string`, applied as a registered,
converter-run WIRE REWRITE (NOT a read-time shim). Our collapse (`{N}-src`+`{N}-use`+`{N}-key` →
`{N}:src(chain);<read>`) is the same operation, more keys folded; `combine_options` is the declarative
pair-fold primitive (combined-option-controls.md:172). Needs `deprecated-tags-options.md` rows per
family.

**(b) But it is an ADDITIVE, CONTAINER-AWARE, POSITION-AWARE fold — more involved than as/size
relocate:**
- **Synthesizes `same` from old ABSENCE.** as/size only relocated existing values; this INJECTS the
  explicit `same` tokens S1 requires where the old wire relied on absence (old `2-src:same` + no read
  → new `2:src(same);use(same)` — corrected 2026-07-31 from the dead `/`-joined spelling `2:same/same`;
  this is the migration's commonest output and it is also exactly the repeater's "Add slot" seed, so
  migrator and control must emit the identical string).
- **Container-aware type injection (container-conditional type token).** join slots gain the tag-name
  type token as a STANDALONE leading token (`email;key(x)` — corrected 2026-07-31; the earlier
  `email,key(x)` read-step-prefix shape is superseded, see that section's ⚠); try_<tag> slots do NOT
  (container fixes type). Two transforms keyed on container. **Note for the migrator:** since the type
  is a sibling rather than a read prefix, injection is a PREPEND to the slot value and does not touch
  the read token at all — the read migrates identically in both container classes.
- **Position-dependent absence resolution.** Old absence was position-dependent (tag-reference:58:
  slot-1 absent = analog, slot-2+ = inherit). Migration maps slot-1 absent-read → the analog,
  slot-2+ absent-read → `use(same)`. Get the position rule right or migrated tags flip meaning.
  *(Spelling corrected 2026-07-31 from `/default` and `/same`. **The slot-1 case needs care at build:**
  under the ratified carrier the `default` analog DROPS on emit, so "map to an explicit analog" means
  emitting `use(<the tag's actual analog>)` — or, where that analog IS the default, emitting NO read
  token at all. It does not mean writing a literal `use(default)`, which the emitter never produces.)*

**Failure surface + empirical gate (user, 2026-07-28):** the position-dependent absence resolution is
where it can go wrong — ambiguous/broken old slots (the FW-51 "slot 2 with `key` set but `use` absent
= silently empty" shape, memory `feedback_try_slot_use_required`) inherit that ambiguity. **Lean:
FLAG such slots unmigratable for author review** rather than best-guess — BUT do NOT lock flag-vs-
best-guess in the abstract: **assess against live-site CLONES with real uses at build time.** If
clones show every absence resolves cleanly, best-guess is safe; if genuinely ambiguous ones exist,
flag them. Empirical, not a priori. Every old absence pattern (incl. broken ones) needs a DEFINED
mapping or an explicit flag — no silent guess (unlike as/size's clean `if no size, continue` skip).

### Multi-option terminal tags + link — v1 DEFER, container-split (grill 2026-07-28)

The container-conditional type says agnostic-container slots (join, table columns) NAME their processing tag. Fine for
zero-arg tags — but even "easy" tags carry MORE than a field: their own format/return options AND
LINK options. Where does that ride?

**Multi-option terminal tags — S-defer for v1.** v1 agnostic slots support only the low-arg terminal
tags (`title`/`content`/`text`/`email`/`phone`/`permalink`/`default`/`key(x)`). Tags with their own
option payload — `datetime_*` (`format`), `image` (`as`/`size`), nested composers (FW-28) — DEFER to
FW-24's bracket-grammar hard half (positional-vs-pairs, comma-in-format escaping FW-55). Rejected
S-fold (pull FW-24's grammar into v1 — balloons the build) and S-sibling (multi-option tags keep
`{N}-`-prefixed siblings — revives the per-slot sprawl the fold kills, asymmetric). **Cost:** a v1
join/table cannot date-FORMAT or size an image cell; a raw date field still reads unformatted via
`key(x)`. Degraded, not absent. Non-breaking to extend (v1 simple slots are a strict subset of
FW-24's grammar).

**Link — container-split, and it is NOT this fold's to design (user, 2026-07-28):**
- **try_<tag>: link stays TAG-LEVEL** (unchanged — tag-reference:476 "no per-slot `linkKey`; read from
  the winning slot's entity"; line 471 link on text/title/datetime_* only). Same as image `as`/`size`
  being tag-level on try_image — one link config for the winning output. NOT a slot axis, no encoding.
  ✓ solved by staying put.
- **join AND table columns are the SAME agnostic case (user correction) — whatever per-slot link one
  gets, the other gets.** Link there is inherently PER-SLOT/PER-CELL (a table concatenation can't share
  one wrap; a staff-name column links each cell to its row entity — a PRIMARY use case). So link CANNOT
  purely defer on the agnostic containers the way join's format can.
- **BUT the slot fold does NOT invent per-slot link encoding — it CONSUMES FW-20's link-cluster
  combine.** The `linkTo`/`linkKey`/`newTab` cluster "wants its own collapse/combine" (user) — already
  OWNED by FW-20 (combined-option-controls.md:211 "Link cluster unification"; the sketched collapsed
  token is `linkTo:key,FIELD` folding `linkTo`+`linkKey`, :220). The slot value carries FW-20's ONE
  collapsed link token per slot, not a parallel grammar. **Dependency: per-slot link (join+table) is
  BLOCKED ON FW-20's link-cluster combine.** If FW-20's link token exists, it rides the slot value; if
  not, per-slot link is deferred BY DEPENDENCY, not by choice. v1 common case once unblocked = the
  per-slot row-entity link (`linkTo:permalink`, staff-name→staff-page); full arbitrary-`linkKey`
  URL-field per slot rides the same FW-20 token, no extra slot grammar.

Encoded-vs-deferred summary for v1 agnostic slots: **encode** = low-arg terminal tags + (once FW-20
lands) its collapsed link token per slot. **Defer to FW-24** = datetime-format, image-as/size, nested
composers. **Defer to FW-20** = the per-slot link token itself.

### NOT blocked on FW-20 — and the derivation INVERTS for the read token (grill-locked 2026-07-28)

The fold keeps consuming FW-20 (combined-option-controls Phase 2 `use`+`key`, STILL OPEN) — read-step
`key(x)` looks like FW-20's `use:key,field`; per-slot link needs FW-20's link token. **Does the fold
block on FW-20? NO.**

- **Core (source+read) is INDEPENDENT.** The `{N}:` slot value is NEW bracket-kv wire grammar the fold
  INVENTS; its read (`key(x)` / `use(<analog>)` / `use(same)`) is defined BY the fold, not borrowed
  from base-tag option keys. *(Terms corrected 2026-07-31 Pass-5 audit — this line predates two
  supersessions and described the grammar as "positional" and the analog read as a "bare analog".
  Both are dead: the structure lock made every slot token `name(value)`, and the `use(...)` carrier
  was ratified. **The decoupling argument itself is unaffected** — it turns on the read being
  fold-defined rather than borrowed, which is more true under the carrier, not less.)* Base tags stay SLOTS-ONLY excluded (locked) → base
  `use`+`key` combine (FW-20) and the slot read-step are DECOUPLED: base keeps separate options until
  FW-20 whenever; slots use the fold grammar now.
- **Anti-drift still holds — the slot read derives from base CAPABILITY, not base WIRE SHAPE.** The
  derivation source is the base's analog capability set (which analogs exist per source, what `key`
  means) — stable whether base serializes combined or separate. So no FW-20 shape dependency.
- **Derivation INVERTS for the read TOKEN (user, 2026-07-28).** If the slot `key(x)` and FW-20's
  `use:key,field` are the SAME token, the slot fold ships FIRST and becomes the REFERENCE FW-20's
  base-tag combine derives BACK from (or they converge on one shared definition) — the reverse of the
  usual slot-derives-from-base direction, BECAUSE this token is greenfield (base doesn't have it yet).
  One token definition, authored by whichever ships first (the fold), reused by the other. Simpler, no
  block either direction.
- **Link = the ONE soft-deferred axis.** Per-slot link (join+table) rides FW-20's link-cluster token
  when it lands (§Multi-option above); until then deferred by dependency. That is ONE axis deferred,
  not a block on the fold.

**Net: FW-20 is INTERACTS-WITH, not BLOCKED-BY.** Core fold ships independent; the read token is a
shared greenfield definition (fold-first); per-slot link soft-defers to FW-20's link combine.

### Control model = FIXED-revealed-slots for v1 (grill-locked 2026-07-28) — `same`-coupled

> **⚠ SUPERSEDED (2026-07-31 assessment):** the slot REPEATER (§L1096) replaced the progressive
> `show_if_any` reveal chain, and the "why fixed for v1" rationale inverted — the spike BUILT the
> `same`-materialization mutation handling this section deferred to FW-45. Fixed slot COUNT
> (register-to-ceiling) survives; reveal mechanics + deferral rationale do not. See Pass 1;
> ceiling 8-vs-10 is Pass 2 item 5.

The `same` inherit is a POSITIONAL back-reference ("`3:...same` = whatever slot 2 resolved") → it
needs stable, contiguous, ordered slots. That couples the control-model choice to `same`:

- **FIXED-revealed-slots (today's model, base-tags.php:880-903 — extended): v1.** N fixed slots,
  `{N}-` ordinal = permanent position, progressive `show_if_any` reveal (slot N+1 shows when slot N is
  "real"). `same` is CORRECT BY CONSTRUCTION — ordinals never move, so the back-reference is stable.
  Ships on EXISTING tech (the `show_if_any` chain + the stock radio from §Per-slot intent RADIO). Keeps
  the `BWS_JOIN_MAX_SLOTS` cap (10). Today's `same` is source-axis only (base-tags.php:895 "source can
  carry forward; field identity cannot"); the fold ADDS read-axis `same`.
- **Append-a-step `bws-chain` (FW-45's target, new control): DEFERRED.** Unbounded + drag-reorder, one
  composite owning all `{N}-` keys (affordance-3, combined-option-controls.md:260). **`same`-HAZARD:**
  mid-list delete/reorder BREAKS positional `same` (delete slot 2 → slot 3's `same` now points at old
  slot 1) → the composite must renumber AND rewrite `same` targets on every structural mutation — a NEW
  correctness burden ON TOP of renumber-contiguity. v1 shouldn't take the new control AND the
  `same`-rewrite complexity at once.

> **⚠ RATIONALE INVERTED — flagged by Pass 1 (claim 5), NEVER APPLIED; applied 2026-08-01.** Two of
> the three reasons below are DEAD: the `show_if` chain and the stock radio were both CUT (§Slot
> repeater), so "ships on existing show_if + stock radio" and "the radio already fixes the main UX
> pain" argue from machinery that no longer exists. Worse, the repeater spike WENT ON to build the
> very `same`-rewrite mutation handling (materialization + `compaction-probe.js`) whose absence was
> the deferral's premise. **The CONCLUSION still holds and the reorder scoping below is untouched**
> — v1 keeps fixed ordinals, uncapped+reorder wait for FW-45 — but it now rests on the surviving
> reason (don't take a new control AND the rewrite burden at once) plus the probe's own finding that
> reorder is a materialization CASCADE, not the single fixup removal is. Re-derive from those, not
> from the radio.

**Why fixed for v1:** `same` free-by-construction (vs a mutation-maintenance burden in append-a-step);
~~ships on existing show_if + stock radio (no `docs/editor-controls.md`-gated primitive); the radio
already fixes the main UX pain (intent-first reveal replaces the `show_if` wall)~~ so append-a-step's
only remaining win is uncapped count (rare — 10 is a lot); and fixed v1 PROVES the `same` semantics
before FW-45's harder control must maintain it under mutation. **Cost:** v1 keeps the 10-cap + fixed
ordinals; uncapped + reorder wait for FW-45, which then inherits a working `same` to preserve.

**Radio unset-by-default (user, 2026-07-28 — minor UX, one non-cosmetic consequence).** A fresh slot
shows only the radio (no control wall) until the author picks an intent — good default-empty UX. Unset
radio writes NO tokens → slot is EMPTY (collapses like any empty slot, correct). BUT it interacts with
the progressive-reveal trigger: an unset radio on slot N leaves slot N NOT "real" → slot N+1 never
reveals (this is the DESIRED chain gating, self-consistent). **Build consequence (not a decision):**
the reveal-trigger predicate (today `{N}-key`/`{N}-use` not_empty, base-tags.php:887-888) MUST be
rewritten to test the FOLDED `{N}:` value's presence — the old per-axis keys don't exist under the
fold. Migrate the predicate or the reveal chain breaks.

### Path A needs an inherit-READ — spelled as the read-axis twin of `src:same` (locked 2026-07-28)

The three authoring intents per slot 2+ (user's "three-path strategy"):

- **A. new context, SAME field** — change source, reuse the prior slot's read.
- **B. same context, new field** — inherit source (`same`), change read.
- **C. both new.**

Path A (confirmed WANTED) needs "inherit the prior slot's read." Source already has this
(`{N}-src:same` inherits the prior SOURCE — base-shared.php:313-317). The locked answer: give the
READ axis a `same` too — a terminal `same` read-step, **LIVE inheritance** (edit slot 1's read →
slot 2 follows). Rejected the ephemeral-snapshot copy: path A's whole meaning is "SAME field," and a
snapshot silently DIVERGES the moment the author edits slot 1 — betraying the intent exactly where
they lean on it. The cross-slot wire dependency is NOT a new kind of coupling — `src:same` already
ships the source-axis twin; read-`same` is its mirror.

**The triad = the 2×2 of source[same|new] × read[same|new]** (minus the same/same degenerate). The
three paths FALL OUT of two independent `same`-able axes; no dedicated "mode" concept:

| source \ read | same (inherit) | new (set) |
|---|---|---|
| **same** | degenerate (identical slot) | **B** `same / key(y)` |
| **new** | **A** `refs(x) / same` | **C** `refs(x) / key(y)` |

### Per-slot intent RADIO — new control tech, EPHEMERAL (Model 1)

> **⚠ SUPERSEDED (built, trialled, CUT — §Slot repeater L1096):** radio gated axis visibility;
> repeater leaves it no job. The 2×2 cell survives as ADVISORY text only (`inferIntent`). Also
> dead with it: B3's folded `show_if_any` reveal predicate + the L735 reveal-trigger rewrite
> consequence below.

Today's slot UX = a wall of ~5 flat `show_if`-revealed controls per slot (`N: Source` select +
ref picker + srcTermIn + use select + key combo), ×N slots, with no signpost for WHICH axis the
author is changing. The `same` source value is the only nod to path B and it's buried in a dropdown.
User: "the slot UX is horrible."

Proposed fix: a per-slot **radio / segmented 3-way** that names the INTENT (context / field / both =
the 3 useful 2×2 cells) and reveals only that path's sub-controls. No radio is USED in the codebase
yet (everything is `select` / `bws-field-combo` / `bws-as-size` / media-picker), **but a stock React
radio IS available** — verified 2026-07-28 against testbed WP 7.0.2: `wp.components.RadioControl` is
STABLE (no `__experimental` prefix) and rides the same UMD-global surface our controls already read
(as-size-control.js pattern). `wp.components.__experimentalToggleGroupControl(+Option)` also ships
(prettier segmented "signpost" look) but keeps the experimental prefix — use only behind a
`__experimentalToggleGroupControl || RadioControl` fallback guard. So this is NOT "build a radio
primitive" — it's wiring stock `RadioControl` into the `tagSpecificControls` seam; the REAL work is
the reveal-lens (infer the 2×2 cell from wire on load) + the parsed-state chain control, not the
radio widget.

- **The radio value is EPHEMERAL — NOT serialized (Model 1, locked).** It is a reveal-LENS inferred
  from the wire on load: is each axis `same` or set? → which cell → which controls show. The WIRE
  stays `src`-chain + read exactly as the fold grammar defines. Serializing an `{N}-mode` token was
  rejected by the same logic that killed the DECISION 2 sidecar — it is derivable from what src/read
  already hold → desync risk → don't serialize (constraint 4). Same principle as DECISION 2 (control
  is editor source-of-truth for live reveal; wire derives).
- Radio → cell A: chain control shown, read = `same` (hidden/inherit). Cell B: source = `same`
  (hidden), read control shown. Cell C: both shown.

### Fold boundary — what rides IN the slot value vs stays sibling / tag-level

Axis-by-axis (reviewed 2026-07-28):

| Axis | Level | Fold verdict |
|---|---|---|
| `src` chain | slot | → VALUE (path) |
| read (`use(<analog>)` / `key(field)` / `use(same)`) | slot | → VALUE (the path's datum endpoint, carried as a SIBLING token beside `src(…)`) |
| `limit` | STEP (within slot chain) | → VALUE, forced — a multi-hop slot has N independently-cappable fanning steps, so `limit` CANNOT be one slot-level sibling; it must be a step arg (§Per-step `limit`) |
| `as` / `size` (image) | **TAG-LEVEL** | NOT a slot axis. Verified `leading_options` on try_image (base-tags.php:469) — one return-type for the whole tag, applied to the winning slot. User: don't want different sizes per slot. Untouched. |
| `sep` (FW-44 inner list) | slot | SIBLING (post-value — joins the slot's own list AFTER read) |
| `label` (table header) | slot | SIBLING (post-value — off the datum path entirely) |
| `if` (FW-27) | slot | **DEFERRED** (user) — a gate wrapping the resolved slot; if it lands, sibling `{N}-if:`; nothing in the value grammar depends on it |

**Boundary rule:** the `{N}:` value carries the source→datum PATH — src chain, per-step `limit`,
terminal read. Post-value shaping (`label`, inner `sep`) and tag-level rendering
(`as`/`size`/`format`/`mode`/`valueSep`) stay OUT. Clean line: **slot value = which datum; siblings +
tag-level = how it's shaped/rendered.**

### Migration

`{N}-src` / `{N}-use` / `{N}-key` → the folded `{N}:` value, for try_ + join + table columns. No
coexistence (precedent: `ref`→`refs`, DECISION 3). Needs `deprecated-tags-options.md` rows per family.
Bigger migration than `ref`→`refs` (three tag families, per-slot). States: unmigrated / migrated / new.

### Cost (user-flagged)

More custom controls: the new intent radio PLUS the DECISION 2 parsed-state-holding chain control
(`bws-chain`-style) that rewrites the whole `{N}:` value on each commit. Bigger control-layer
investment than today's flat options — the real price of killing the slot-UX horror.

### Anti-drift discipline — slot controls do NOT break base-tag absorption (analysis 2026-07-28)

Concern (user): the plugin absorbs from base tags wherever possible to prevent drift — do
slot-specific controls break that? **No — provided the read-step fold honors ONE obligation.** The
discipline has TWO axes, and the radio touches NEITHER:

- **Axis 1 — option DEFINITIONS derive from base builders.** `bws_build_slot_traversal_options()`
  (base-shared.php:299) re-emits the base `src`/`ref`/`srcTermIn` definitions per-slot (#26, closed).
  Change the base builder → slots inherit.
- **Axis 2 — RESOLUTION absorbs the base read-seam.** Slots read THROUGH `bws_base_text_resolve_value`
  (the ABSORB invariant, join-helpers.php; FW-48 constraint). Change the base read → slots inherit.

The **REVEAL layer is a THIRD axis the discipline NEVER covered — and it was ALREADY slot-specific.**
`bws_slot_qualify_show_if()` (base-shared.php:330-341) re-qualifies base `show_if` for slots
(ordinal-prefixes sibling keys). Reveal was never absorbed; base tags reveal via flat `show_if`, slots
via ordinal-qualified `show_if` — different mechanisms already. So the intent **radio swaps one
slot-only reveal (the qualified `show_if` wall) for a better slot-only reveal.** It sits strictly
ABOVE definitions + resolution; it defines no option and adds no read-seam. **The radio is FREE of the
drift discipline** (reveal was always per-context).

**The ONE real obligation the fold creates — on the read-step control, NOT the radio:** the folded
slot read-step control MUST derive its analog enum + `key` semantics from the base tag's read
definition — a `bws_build_slot_read_options()` TWIN of the traversal builder — NOT a hand-authored
per-slot read control. Then a base change (new analog, the `default` rename) propagates to slots
automatically, same as #26 did for src/ref. Terminal-read RESOLUTION must likewise stay on the base
seam (Axis 2). Honor that and the fold adds ZERO new drift surface; violate it (hand-build the slot
read) and a slot read can silently diverge from base read semantics — that, not the radio, is where
the discipline would break.

### PINNED-OPEN (blocks a full spelled write-up)

1. **Terminal read-step token FORM** — `key(field)` vs `key,field`, and the analog-slug delimiters —
   PINNED to the larger chain-grammar `,`-vs-paren decision (the §Genuinely-still-open #1 separator
   call + FW-24's bracket-grouping). Do NOT lock read-step spelling before the chain grammar. All
   worked forms above are PLACEHOLDER spelling.
   **⚠ SETTLED (2026-07-31 assessment):** paren form won via the bracket-kv STRUCTURE lock
   (§Sandbox convergence); only literal CHARS stay open (Pass 2 item 3).
2. **`default` rename** — analogs → one `default` value — separate consideration; reinforced here, not
   settled.
3. **`if` slot gate (FW-27)** — deferred; sibling if it lands.
4. **FW numbering / tracker home** — new FW row vs §-under-FW-56 vs FW-24-subset — deferred (user
   interrupted the tracking-decision ask). ASK before assigning.
   **⚠ RESOLVED: FW-57 assigned** (`docs/future-work.md`; row reconciled to the spike outcome
   2026-07-31 in Pass 1 — title, cut radio, `use()` carrier, open list).

## Sandbox convergence — the FRONTRUNNER + escape discipline (2026-07-29)

Session eyeballing the `tools/preview/tag-string-preview.html` sandbox against real folded-slot
strings. ~~SEPARATORS are NOT finalized (the picker chars stay tunable)~~ *(⚠ true when written
2026-07-29; separators were APPROVED 2026-07-31 — OPT `;` · HOP `;` · STEP `,` · L1 `()` · L2 `[]`)*,
but the STRUCTURAL decisions below are locked. The sandbox opens on the frontrunner as its default
state.

### FRONTRUNNER (2026-07-29 sandbox state — ⚠ SEPARATORS SUPERSEDED, see canonical below)

> **⚠ THE BLOCK BELOW USES TWO REJECTED FORMS — do NOT copy it (flagged 2026-08-01).** It is kept
> as the historical sandbox record. Both defects post-date it: **(1) hop-sep `+`**, which failed
> visual review (it pairs the text before+after it rather than delimiting a step) and is now `;`;
> **(2) positional `limit`** (`refs,related_staff,5`), cut for the `,,` interior-empty hole and now
> bracket-kv. **This is the doc's most-copied artifact**, so the canonical pair is given directly
> beneath it. Missed by Pass 1 (which audited read-token supersessions, not separators) and by the
> Pass-5 shape-sweep (which grepped the dead `,`-prefix and `/`-joined forms, never `+`).

```
{{try_text 1:key(custom_field)|2:src(terms,category);use(title)|3:src(refs,office+refs,region);key(name)}}
{{table   src:post,9999+refs,related_staff,5|1:label(Name);title|2:label(Phone);phone;key(mobile)}}
```

**CANONICAL equivalents (approved wire spec — use THESE):**

```
{{try_text 1:key(custom_field)|2:src(terms,category);use(title)|3:src(refs,office;refs,region);key(name)}}
{{table   src:post,9999;refs,related_staff,limit(5)|1:label(Name);title|2:label(Phone);phone;key(mobile)}}
```

Note `limit(5)` not `limit[5]` on the table line: that chain rides the **colon** form (`src:…`, no
enclosing bracket ⇒ depth 0), so its limit sits at L1 `()`. Inside a slot's `src(…)` wrapper the
same token would be `limit[5]` — depth-alternation, not two different tokens.

- **Slot schema = bracket-kv** (SOLE — the `unsafe-lit` / `bracket-all` / `bracket-uniform`
  experiments were built, eyeballed, and CUT). Shape: `name(value)` — the slot bracket IS the kv
  delimiter, no `=`. Type LEADS, options in FW-52 canonical order, format-safe by construction.
- **src chain = FLAT, LOCKED.** `slug,arg` bare-comma, NO step brackets: `refs,office`,
  `post,9999`, `refs,related_staff,limit(5)`. *(⚠ Limit notation corrected 2026-08-01 — written as
  positional `[,limit]` / `refs,related_staff,5`, superseded by the bracket-kv `limit` decision;
  the FLATNESS claim this bullet exists to make is untouched, since a named limit token is still a
  bare member of the flat step, not a nested bracket.)* Backs out of the earlier bracketed-chain (`refs(office)`)
  entirely — the nested-bracket complexity (`src(refs(office))`) was the cost that killed it. The
  chain's own `,` never collides with a slot opt-sep because the whole chain sits INSIDE the slot's
  `src(...)` bracket (isolated) at slot level, and at TAG level (`src:…`) the `|` divides options.
- **Depth-alternating brackets = RULE (no toggle).** Bracket CHAR is a function of NESTING DEPTH:
  level 1 = `()`, level 2 = `[]` (opposite). Slot token = L1; a read-target/chain nested inside a
  slot = L2. GUARANTEES no same-char nesting. The frontrunner's flat chain shows only L1 (`()`),
  since flat chains carry no L2 bracket; L2 `[]` surfaces only where a resolution read nests (e.g. a
  future bracketed sub-read). NB depth-alt governs the SLOT-token + READ-target brackets ONLY — it
  does NOT re-bracket chain steps (chain is flat regardless of depth).
- **N: slot value + N- siblings** (slot 1 included); **chain owns `src`**; **type LEADS** — restated
  from the FW-56/57 locks above. *(⚠ "unchanged from locks" is NOT true of the READ, corrected
  2026-08-01: the read became a SIBLING bracket-kv token rather than a chain terminal — Pass 1 listed
  this correction as claim 3 but never applied it here. `type LEADS` is unaffected and correct: the
  type token leads the slot, `[type];src(...);use(x)`.)*
- ~~**Separators PROVISIONAL** (frontrunner values, still tunable via the kept pickers): slot opt-sep
  `;`, chain hop-sep `+`, intra-step-sep `,`, L1 bracket `()`. These are the leaning values, NOT
  locked.~~ **⚠ SUPERSEDED 2026-08-01 — separators are APPROVED, and the hop-sep shown here is the
  REJECTED char.** Canonical: OPT `;` · **HOP `;`** · STEP `,` · L1 `()` · L2 `[]` (§Hop separator,
  approved 2026-07-31). `+` was the frontrunner and FAILED visual review — it pairs the text
  before+after it rather than delimiting a step. **Pass 1 did not catch this section** (it audited
  the read-token supersessions, not the separator one), so it went on presenting a rejected char as
  the leaning value, inside the very §Sandbox-convergence section later passes cite as authority.
  Structure (flat chain, depth-alt, bracket-kv) was already locked here and still is.

### Free-form escape discipline (corrects the earlier quote approach)

The escape-hazard scenarios (`format`, `label`, `fallback` with `:` `|` and inner brackets) settled
what actually protects free-form values — and it is NOT the slot bracket char:

- **Brackets are INERT to GB.** `( ) [ ]` are serialization-safe (`gb-constraints.md`
  §Separator-safe); GB never opens/closes/depth-counts them. So the slot bracket is a SECOND parser
  WE own — make it BALANCE-AWARE and balanced inner brackets (`label(Size (cm))`) survive; only an
  UNBALANCED bracket (`label(Note (TBD`) breaks (sandbox flags it `⚠`, preview-only).
- **What actually needs escaping: `:` and `|`** — GB's real split chars. Free-form values get
  `\:` / `\|` escaped (GB unescapes on read; `bws-format-input` precedent). `format(g\:i A)`,
  `label(Price\: USD)`.
- **`{` `}` remain hard-unsafe** (no escape) but ~never appear in dates/labels.
- **The earlier `bracket-uniform` quote wrap (`"…"`) was a LIABILITY** — `"` is RichText
  entity-encoded (`gb-constraints.md`), so quoting INTRODUCED a hazard while solving a non-problem
  (brackets were never the GB-level danger). Cut with the schema.
- This finding drove **FW-59** (bracket free-form values on BASE tags too — base + slot should share
  one free-form spelling rule). Tracker: `docs/future-work.md` FW-59.

### Kept sandbox toggles (OFF by default — future looks, not the frontrunner)

- **always-serialize-src on slot 1** (`src:current` on join/try_ slot 1) — parked.
- **read-via-`use(...)` parity** — meta read as `use(key,name)` to match `use(same)`/`use(title)`.
  LOVED the parity, but verbose on the common meta case (`use(key,name)` vs bare `key(name)`); OFF
  for now. The collapse rule holds under it (analog==type still drops, no `title;use(title)`).
- **use+key / link combine** — the global combine schema; DECIDED the slot read should INHERIT
  whatever the global combine produces (not a separate slot control) when this graduates.

### Dropped (built, eyeballed, cut)

- Slot schemas `unsafe-lit`, `bracket-all`, `bracket-uniform` (multi-hop `,`-collision + the quote
  liability killed them; bracket-kv survives).
- `independent` bracket mode + its slot-bracket / step-bind / kv-chain / L1-char radios — replaced by
  the depth-alt RULE + flat-chain LOCK.
- Bracketed chain modes (`refs(office)` / `refs[office]` / `refs=office`) — flat won.

### Spike A — parse⇄emit round-trip VALIDATED (2026-07-29)

The sandbox only EMITS; ADR 0004 + DECISION 2 require the parser to consume hand-edits too. Spike
harness `tools/test/slot-fold-roundtrip-spike.php` (pure PHP, house pattern, NOT yet a registered
`*-test.php` — graduates on fold build) proves the frontrunner grammar parse⇄emit round-trips:
**154/154** across identity (canonical corpus), structure fixpoint, hand-edit normalization
(reorder/whitespace → canonical re-emit), full-GB-layer escape survival (`\:`/`\|`, PHP-unescaped
AND JS-still-escaped inputs both accepted), balance-aware brackets (balanced inner survive; opt-sep
inside brackets not split), malformed-input flagging, and backslash adversarial (PHP date-format
literals `\a\t g\:i`, author-typed literal `\:`, trailing-`\` fallback). Runs under BOTH the
frontrunner chars (`;` `+` `,` `()`) and an alternate set (`,` `/` `~` `[]`) — grammar is
char-independent, per still-open #1. Two structural findings:
- **bracket-kv structurally guards GB's pair split**: an emitted slot value always ends with a
  bracket/alpha token, never `\` — so the `1:…\|2:…` phantom-escaped-pipe slot-merge hazard
  (demonstrated real in the harness) is unreachable by construction.
- **Forward wrinkle**: a hand-typed `use(key,name)` (the parked readViaUse parity form) parses as
  analog slug `key,name` under the default grammar — if readViaUse ever graduates, the parser needs
  the composite-`use` branch before it; harmless today (invalid analog → renders empty like any
  unknown analog). **⚠ STALE as written (2026-07-31): parsers since grew the `use` branch**
  (`use(...)` became the analog/inherit carrier — proto-fold-tag.php:171, control.js:117); only the
  composite `use(key,name)` ARG form remains future.

### Spike B — {{proto_fold}} proto tag: fold + intent radio LIVE on testbed (2026-07-29)

Throwaway `{{proto_fold}}` tag (`tools/spike/proto-fold-tag.php` + `proto-fold-control.js`,
loaded via a file_exists-guarded require in the main plugin file — CLI-command pattern, absent
from released builds; REMOVE the require when the spike concludes). Proto-not-real decision:
folding shipped try_text would churn live option defs + reveal chains and poison the existing
matrix rows; greenfield proto also skips migration entirely (not what B tests). Slot options
derive through the REAL base builder seam (`bws_base_source_option()` localized to the control)
so the anti-drift obligation is exercised, not bypassed.

**Render-side PROVEN via testbed render-tag (matrix-post-meta context):**
- B5 numeric `'1'/'2'/'3'` option keys survive GB registration → parse_options → callback.
- B6 PHP parses the exact frontrunner wire: `1:key(staff_name)|2:src(refs,office);use(same)|3:src(same);key(city)`
  dumps Path A (new ctx, inherit read) + Path B (src same, new read) correctly; the bare STANDALONE
  type token (`2:title;key(x)`) recognized. **This line is the evidence that settled the 2026-07-31
  mechanism correction** — the type was proven `;`-separated and standalone here while §Container-
  conditional TYPE still described it as a `,`-prefix on the read step.
- Malformed input flags `⚠` instead of silently mis-parsing.

**Editor-side (B1 composite one-key rewrite / B2 wire-inferred ephemeral lens / B3 folded
`show_if_any:{N: not_empty}` reveal predicate / B4 stock RadioControl in the seam) — built,
awaiting editor eyeball on testbed** (insert `{{proto_fold}}` in a GB block, watch the wire echo
under the controls, reopen modal to test lens remount-inference).

**Finding (live, first smoke): token-level close-then-reopen laxity.** `src(a)+use(b)` arriving
as ONE token (author typo: hop-sep where opt-sep intended) passed the "ends with `)`" check and
parsed as a junk chain. Fix: token parser must verify depth does NOT return to 0 before the final
char. Patched in all three parsers (Spike A harness — now 156/156 with regression case — spike B
PHP + JS). Carry this guard into the real build's parser spec.

### Multi-hop chains need per-step controls in v1 — NOT deferrable (editor trial 2026-07-30)

**Corrects the §Control-model lean above.** That section said "v1 = FIXED-revealed-slots;
append-a-step graduates to FW-45." Right about SLOT count, WRONG if read as also fixing CHAIN
depth. The editor trial found saved multi-step src tokens (`src(refs,office+refs,region)`)
surviving the wire with NO controls to interact with them — and worse, the single-hop control was
**editable-LOSSY**: it read only `chain[0]` and rebuilt the chain as a one-element array, so
touching the source select SILENTLY DROPPED hops 2+. Round-trip was clean (which is why the pure
harness never caught it — the loss is only reachable through an editor edit).

**Rule: because the WIRE supports arbitrary chains, v1 controls MUST edit every step.** A control
that can't reach a step it can serialize is a data-loss bug, not a missing feature. Two axes,
decoupled:
- **Slot count** — fixed N + progressive reveal is fine for v1 (FW-45 graduates it). Unchanged.
- **Chain depth** — must be fully editable from v1. Per-step source select + arg input, per-step
  remove, and an append affordance gated on the last step being COMPLETE (never serialize a
  half-built step). Edits are positional so an edit to step 1 preserves the rest.

Verified in the spike control: 2-hop round-trip identical; edit-step-1 keeps hop 2; remove +
append correct; 3-hop with start-kind + per-step `limit` renders intact. NB the `same`-inherit
chain shows no per-step UI (the intent radio owns that axis), and the append affordance defaults
to a `refs` step.

**Editor-VERIFIED 2026-07-30 — multi-hop axis CLOSED:**
- **PFm1** add/remove of steps in several orderings, stable — the positional-edit model holds
  under real interaction, not just the simulated paths.
- **Remount** — a saved multi-hop tag reopens with its chain re-parsed into per-step controls
  (the B2 lens twin, chain axis).
- **PFm2 `limit` preservation** — editing a step that carries `limit 5` keeps it, though the
  control renders NO limit input. Worth noting as a PATTERN, not just a pass: a value the wire
  holds but no control surfaces is exactly the shape of the truncation bug (silently dropped on
  edit). It survives here only because `writeChainAt` threads `step.limit` through explicitly. Any
  future per-step arg (the §Per-step `limit` work, FW-32's fan-out args) must thread the same way
  or be given a control — invisible-but-preserved is a standing hazard, not a solved problem.

**Method note:** this class of bug (wire-faithful, edit-lossy) is invisible to the pure harness
and to `render-tag` — BOTH only exercise parse→render. It needs the editor. Weigh that when
deciding how much of the real fold build can rely on harness coverage alone.

### Spike-fixture trap: label text and markup-returning tags both invalidate blocks (2026-07-29)

Two DISTINCT causes of Gutenberg's "Block contains unexpected or invalid content", both hit while
eyeballing the proto lab page. Neither is a fold/grammar defect; both are fixture-authoring rules
that the real `{{table}}`/fold matrix rows must honor:

1. **Label text containing markup-looking characters.** The page builder wrote a row label with a
   literal `<code>` into block content unescaped → Gutenberg reparses the saved `<p>`, finds an
   unclosed element, flags the block. Rule: **`esc_html()` the LABEL, never the tag string** (the
   tag must stay literal so GB's dynamic-tag parser still sees `{{...}}`).
2. **A tag whose OUTPUT is markup inside a `<p>` host.** Already-known for `{{table}}` — the
   blueprint's Shape 2b (`bws_fixture_gb_block_host_row`, blocks.php:43-58) exists precisely for
   this: host markup-returning tags in a `generateblocks/text` block with `tagName:div`, NOT a `<p>`
   text row and NOT `generateblocks/element` (which doesn't parse dynamic tags in its body). The
   spike's dump renderer returns `<code>…</code>` and ignored that precedent.

**Consequence for the fold work:** the `plain`-vs-markup A/B probe conflates two variables (output
markup AND non-ASCII). Since cause 2 is a KNOWN, SOLVED host-shape issue, a markup-returning folded
tag is fine — it just needs the div host. Any future fold/table matrix row returning markup uses
Shape 2b.

### Mount-reconcile + render dual-read — legacy recovery WITHOUT the migrator (spiked 2026-07-29)

Complement (NOT replacement) to the converter-run migration (§Migration a+b): the fold control
can recover old `{N}-src`/`{N}-use`/`{N}-key` wires at MOUNT, and render can dual-read them.
Enabled by what Spike B proved — the control sees ALL sibling keys in `context.state`, owns the
whole value (DECISION 2), and the modal-confirm boundary makes mount-recovery display-only until
the author commits (cancel = stored wire untouched; VERIFY the write-on-confirm boundary at build).

- **ONE shared mapping** (`bws_spike_fold_from_legacy` PHP reference + JS port) consumed by all
  three paths — converter migrator / editor mount-reconcile / render dual-read — so the
  position-dependent absence rules (slot-1 absent read → default analog; slot-≥2 → S1 `same`
  synthesis) live exactly once and cannot fork.
- **Touch-migration:** any commit writes the folded `{N}:` value AND delete-omits the slot's
  legacy keys — tags an author edits migrate themselves ahead of the converter run.
- **FW-51 ambiguous shape (slot ≥2 `key` set, `use` absent): FLAG, never guess** — and
  mount-reconcile gives that flag an AUTHOR-FACING surface (inline notice) the converter never
  had. Render emits `⚑ needs author review`.
- **FINDING — dual-read applies to the REVEAL layer too:** an unmigrated tag has no folded value,
  so the folded `show_if_any:{N-1: not_empty}` predicate hides the very control that would
  recover slot ≥2. Transition-era predicate must also accept the previous slot's legacy keys
  (`{prev}-key`/`{prev}-use` rows, dropped when converter migration completes).
- Verified live (render-tag): pure-legacy recovery; FW-51 flag; MIXED wire (legacy slots 1-2 +
  already-folded slot 3) coexisting in one tag.
- Migrator stays: tags never reopened never touch-migrate; stored strings only clean via the
  converter run. Dual-read + reveal-extension are the transition bridge, retired after.

> **⚠ RE-DERIVED under the repeater — Pass 3 DONE 2026-07-31.** Two of the three claims above are
> now void; the section is kept for its verified-live evidence, not its conclusions.
>
> - **Cardinality — NO hole.** `slotCount()`/`readSlot()` (`control.js:222-237`) and the render
>   loop (`proto-fold-tag.php:331-345`) are dual-era: a slot counts if it holds a folded value
>   **or** if `foldFromLegacy()` recovers one. Cardinality is content-derived in EITHER wire era,
>   so legacy tags mount with their true slot count. (The interim Pass-1 `⚠` here claimed
>   unmigrated tags surface ZERO slots — wrong, and deleted.)
> - **Reveal-layer FINDING — MOOT.** No reveal chain exists under the repeater; the
>   transition-era `show_if_any` predicate extension it prescribes has nothing to extend.
> - **Migrator + dual-read bridge — STANDS**, as does everything "verified live".
>
> **The real residual is narrower:** touch-migration is per-slot while legacy inherit is positional
> and cross-slot, so migrating one slot can change what a later still-legacy slot means. That is a
> build blocker, tracked at §Residual P3 items — not a cardinality problem. Full matrix in §P3
> findings.

### Lenient separator classes — accept twins, emit canonical (spiked 2026-07-29)

> **⚠ AMENDED 2026-08-01 — the framing below OVERSTATES, and the classes have narrowed.** User,
> accepting the cost: *"change in leniency acceptable for the readability prize. When used within
> the same bracket level without other brackets to disambiguate suboptions, comma and semicolon now
> have different meanings."*
>
> **The corrected principle — position fixes role ACROSS bracket levels; WITHIN a level, the CHAR
> fixes it.** The original claim ("role fixed by parse POSITION … never by the char") was true only
> while hop and step were spelled with disjoint chars. Once `;` became the canonical hop, hop and
> step share one level inside `src(…)` with no bracket between them, so position cannot separate
> them and the char must. This is not a new safety condition — it is the `hop ∩ step = ∅` rule
> below, which was always the real load-bearing statement; the prose around it was looser than the
> machine check.
>
> **Concretely: `step_class` drops `;`.** Inside a chain `;` means hop and only hop, so a hand-typed
> `refs,office;region` is a TWO-STEP CHAIN, not a forgiven intra-step separator. Verified against
> the validator 2026-08-01 (approved config failed as `hop_class ∩ step_class ≠ ∅` until `step_class`
> narrowed to `{,}`).
>
> **Leniency survives where a level has only ONE structural meaning for the char** — `opt_class`
> keeps `{;,}` at slot top level, since `,` has no competing role there and a bracket separates it
> from any chain below. So this narrows the leniency model rather than abandoning it: **accept twins
> only where the level is unambiguous; where two roles meet at one level, the char IS the
> distinction.**

Separators are chosen for VISUAL distinctiveness; functionally-equivalent chars are safe to
ACCEPT on parse where the level admits only one structural role for that char — role is fixed by
parse POSITION across levels (depth 0 vs inside `src(...)` vs inside a free-form bracket), and by
the CHAR where two roles share a level (see amendment above). Spiked + validated (harness
176/176 — ⚠ on the SUPERSEDED hop `+`; re-run under the approved config is a Pass-6 first item):

- **Classes (as approved):** opt-sep {`;` `,`} at depth 0 · step-sep {`,`} inside the chain
  (`;` REMOVED — it is the hop char) · hop-sep {`;`} (**`/` REMOVED — see the approved-twin rule
  below**) · brackets `()`≡`[]`. Parse accepts the class; **emit stays canonical**
  (normalize-on-commit — same DECISION 2 model as token reorder; ADR 0004-friendly: hand-typed
  `,` re-canonicalizes to `;` on next control commit).

> **✅ APPROVED-TWINS-ONLY RULE (user, 2026-08-01): "my objection is to unapproved twins."** An
> accept class may contain a char ONLY if that char has a sanctioned role somewhere in the schema.
> Two cases, and the distinction is the whole rule:
>
> | | Example | Verdict |
> |---|---|---|
> | **Approved twin** — char is allocated in the grammar, borrowed at a level where it is unambiguous | `,` in `opt_class` (`,` is the approved STEP sep) | **fine** — leniency as designed |
> | **Unapproved twin** — char has no sanctioned role, or was explicitly REJECTED | `/` in `hop_class` (`/` was rejected as the canonical hop char, then left in the accept class) | **cut** |
>
> **Why it matters, concretely:** accepting an unallocated char silently CLAIMS it. Stored wire
> starts containing `/` meaning "hop", so the day we want `/` for another axis we cannot take it —
> we would be redefining a char already live in saved tags. This is not hypothetical: it surfaced
> because the very next idea on the table (§per-step `limit` slash candidate) wants `/`, and the
> lenient hop class had quietly pre-empted it.
>
> **⚠ THE VALIDATOR CANNOT CATCH THIS — build guard needed.** `bws_spike_grammar_validate()` passes
> BOTH `hop_class {;,/}` and `hop_class {;}` (verified 2026-08-01): it checks cross-class disjointness
> and bracket-freedom, never whether an accepted char is allocated. **Add a check** that every member
> of every accept class appears in the schema's approved-char set — the same shape as the
> hop/start slug-namespace disjointness guard already queued for the build.
>
> **Scope note — this does NOT reopen the lenient design.** An earlier framing of this objection
> ("it generalizes to `,` in `opt_class` and the whole accept-twin model") was WRONG and is withdrawn:
> the rule keeps every twin whose char is allocated, and cuts only the unallocated ones. `opt_class`
> is untouched.
- **Per-token delimiter rule (brackets):** the open char following a token name fixes THAT
  token's structural pair; the other pair is INERT text inside it. Affordance: an author picks
  the pair their free-form content avoids — `label[Note (TBD]` parses (lone `(` inert) where
  `label(Note (TBD)` errors.
- **THE safety condition, machine-checked** (`bws_spike_grammar_validate`): classes may overlap
  ACROSS positions (opt + step both take `,;`) but must be DISJOINT within one — hop ∩ step = ∅
  (both live inside the chain); no separator class may contain a bracket char. Any future char
  tuning re-validates automatically.
- **Forward guard:** if a future grammar puts bare `,`-composite values at depth 0 of a slot
  value (none planned — reads are bracketed), the depth-0 `,`≡`;` acceptance must be revisited.

### `foldResolution` flat-mode fix (sandbox)

Fixed a latent bug: `foldResolution` floored `valuePair:'off'` back to `()`, so the flat combine
`use:key,field` was unreachable. Now honors `off` → `head<valueSep>tail`. Parallels the chain
flat-mode fix. Makes the base-tag `use:key,field` combine testable in the sandbox.

### Slot repeater replaces the show_if reveal chain (editor trial 2026-07-30/31)

**Supersedes the intent-radio design above — B2/B4 AND (added 2026-07-31) B3, the folded
`show_if_any` reveal predicate; the L735 reveal-trigger-rewrite consequence; and the
progressive-reveal half of §Control model. The repeater removes the reveal chain outright, so
every design that threaded it is dead, not merely amended** (the legacy dual-read bridge inherits
this — see its ⚠ above). The radio was built, trialled, and CUT —
it gated axis VISIBILITY, which tangled reveal state with slot existence. Under the repeater
both axes always render and cardinality is explicit, leaving the radio no job. The 2×2 cell
survives as ADVISORY text only (`inferIntent` reads the wire, describes the slot, gates nothing).
**Keep the advisory in the real build** — confirmed valuable for `try_` fallback chains, where
"what varies vs the previous slot" is the authoring question. The wire echo below it is
spike-only debug scaffolding, NOT a keeper. *(**This original reading is CORRECT and RESTORED
2026-08-01 by user decision** — "the wire echo per slot should be dropped"; the keeper is the
advisory cue line above it. An interim 07-31 note reversed this on a "later wins" reconciliation
with the §Open note below; that reversal was wrong and is retracted — see §Pass 5 item 5.3.)*

**Cardinality model.** GB registers options statically, so a control can never mint an option
key — register to a ceiling (spike: 8) and let the control decide how many are live. Slots
1..MIN always render; slot N≥3 renders when it HOLDS A VALUE. "Add slot" writes an explicit
seed (`src(same);use(same)`), so cardinality survives remount with no arming state anywhere — a
per-key `useState` could not reveal a sibling, since GB mounts one control per option key.

**Out-of-order removal needs MATERIALIZATION — the finding that generalizes.** `same` is a
POSITIONAL backreference, so compacting a slot away re-points its successor's `same` at a
different neighbour: a silent meaning change. Before renumbering, materialize the successor's
inherited axes against the slot being removed. Only the immediate successor can be affected, so
it is a single fixup, not a cascade; promotion into position 1 (where `same` is illegal) falls
out of the same step. **Hop removal has NO equivalent hazard** — hops carry no backreference —
which is why the step-repeater pattern could not be copied across unmodified. Guarded by
`tools/spike/compaction-probe.js` (18 cases), which exists because compaction runs ONLY in the
control: `render-tag` sees already-compacted wire, and the editor does not surface the bug.

**Absence must never mean "inherit" on slot ≥2.** An empty chain there resolved to ambient
context — a silent RESET that read as an inherit, and the same shape that made the FW-51 legacy
wire unmigratable. Now: renderer FLAGS it, the control cannot emit it (emptying a chain falls
back to explicit `src(same)`), and an unconfigured floor-visible slot MOUNTS as the seed rather
than as empty-chain/default-read. That last one was a live bug — the slot displayed "Default
(intrinsic analog)" while a commit would have written the malformed shape.

Also flagged rather than silently resolved: a `refs` hop with no reference field (nothing to hop
through).

### Field-combo under the fold — the composition seam (2026-07-31)

The shipped `bws-field-combo` mounts per OPTION KEY and commits `upd[key] = value`. Under the
fold there is no `{N}-key` option — the field lives inside the slot value — and two controls
cannot own one key. **Seam that avoids reimplementing discovery:** `FieldComboControl` is a plain
component taking `{optionKey, context}` touching state in only four places, so a parent drives it
through a SYNTHETIC context presenting folded state in the legacy shape it expects. The shipped
control needed exactly one line (`window.bwsFieldComboControl = FieldComboControl`); its logic
was deliberately not modified, on the principle that needing to modify it would have been the
fold breaking it, i.e. a finding rather than a fix.

**The kind preset is FW-56's editor-UX floor, exercised for real.** Legacy preset the Location
filter from a sibling `src` token; under the fold the source is a chain, so the preset derives
from the chain's TERMINAL step. Verified live on a multi-hop chain whose last step differs from
its first (`src(refs,office+site)` → "Site fields" + "Site Option Field"). This is the concrete
instance of "terminal kind statically computable from wire" — no live probe, no run.

**Both field surfaces are the same control.** The hop's reference field is ALSO a `bws-field-combo`
in shipped code (`base-shared.php:146`), unscoped with no kind preset per SPEC V3 — the hop
target's post type is not reliably known until ref-hop parity. v2 type-filters it to
`relationship`+`post_object`.

**Name collision the fold exposes (unresolved, matters for {{table}}).** The shipped control reads
the bare `key` two ways: the TAG-LEVEL repeater name under `scope:'row'`, and its own value
otherwise. Legacy kept them apart by prefix — a slot's key was `{N}-key`, never bare. Under the
fold every slot's field arrives as bare `key`, so both readings land on one name. The spike does
not pass `scope:'row'`, so only one reading is live there. **A real build combining the fold with
`{{table}}`'s row-scoped columns must pass the repeater name as an explicit prop rather than
smuggling it through state.**

### Open after this pass (NOT spiked)

- **Tag-dependent nouns.** "Add slot" must parameterize per tag ("Add field" for `{{join}}`,
  "Add fallback" for `try_`). Mechanical once the noun is a registration parameter.
- ~~**Editor preview scope.** Code style is wanted for the wire echo specifically, NOT the whole
  tag preview — which promotes the echo from spike debug aid to a real affordance and crosses
  into `docs/editor-tag-previews.md` ownership.~~ **STRUCK 2026-08-01 — the per-slot wire echo is
  CUT** (user; §Pass 5 item 5.3). This note's promotion of the echo to "a real affordance" was
  taken as authoritative by Pass 1's "later wins" reconciliation and is retracted; the keeper is
  the advisory cue line, not the echo. The `editor-tag-previews.md` ownership claim was wrong
  independently — that doc is scoped to unresolved-tag placeholder text, not to in-control
  readouts.
- **Step-dependent `src` enums.** Whether a step's selectable values narrow based on the PREVIOUS
  step's output kind. Same terminal-kind computation the field preset now uses, pointed forward
  to constrain the next step instead of back to constrain the read. Feasible with what is built;
  the open question is how strict to be, and it interacts with ref-hop parity being unbuilt.
- **Standard source/field control labels.** May be partly answerable from existing nomenclature
  decisions (`docs/tag-reference.md`; "Meta/Option Field", never "field or option") rather than
  genuinely open.

## Follow-up passes — post-spike consolidation plan (2026-07-31)

The spike proved the serialization viable, then the UI/UX pass (repeater, per-step controls,
field-combo seam) landed further findings that partially supersede earlier locks WITHOUT the doc
recording it. Session assessment (2026-07-31) catalogued the drift. Consolidation is split into
SIX passes — decision-bearing grills separated from mechanical doc-truth and build-prep. Order
matters: Pass 1 is cheap truth-restoration; Pass 2's wire spec feeds 3 and 4; 6 is last before
the real build. **Every superseded section now carries an inline `⚠` marker at its heading**
(placed 2026-07-31) so a mid-doc reader can't trust a dead lock; Pass 1 does the full reword.

### Pass 1 — Doc-truth reconciliation (mechanical, no new decisions) — ✅ DONE 2026-07-31

Mark supersessions in THIS doc + satellites. No grilling — record what the spike already decided
de facto, flag anything genuinely still provisional for Pass 2 instead of silently locking it.

**Executed.** In-doc: 8 inline `⚠` markers at the stale headings; PINNED-OPEN #1 + #4 closed and
the §Slot-payload-fold header's "numbering deferred" note retired (FW-57 is assigned); the
repeater's supersession scope WIDENED past B2/B4 to name B3, the L735 reveal-trigger consequence
and §Control model's progressive-reveal half; the wire-echo tension resolved in favour of the
later note (keeper, shape + doc owner → Pass 5) — **⚠ that resolution was WRONG and is REVERSED
2026-08-01: the echo is CUT, the earlier "not a keeper" note was right, and the keeper is the
advisory cue line (§Pass 5 item 5.3). Recency lost to attribution here; check who decided before
applying "later wins"**; a new ⚠ on the legacy dual-read bridge stating
the repeater-cardinality hole explicitly at the site (Pass 3 owns it). Satellites: `docs/
future-work.md` FW-57 row retitled + reconciled (radio/fixed-reveal cut, `use()` carrier,
default-drop, S1 reword, spike-outcome block, new open list) and FW-45 re-scoped (its "reveal
chain" threading is dead; the repeater largely answers its control blocker); memory
`project_slot_payload_fold.md` updated the same way.

Deliberately NOT done (they are decisions, not reconciliation): the `use(...)` ratify-or-revert,
the separator CHARS, and the absent-read rule stay flagged-provisional for Pass 2. The original
item list below is kept as the audit trail.

**✅ P1 FINALIZED 2026-07-31** (after the wire spec was approved as a whole). All three deferred
decisions came back green and their provisional flags are retired: `use(...)` ratified (P2),
separator chars settled with hop `;` (P2, amended), absent-read resolved by container class (P3 —
not one rule, which is why P1 was right not to lock it). Two mechanical leftovers cleared:
the §Legacy dual-read `⚠` rewritten to void its cardinality + reveal-layer claims and point at
the real (narrower) blocker; the §WIRE SPEC status table updated from PARTIAL to APPROVED.
~~**Nothing in Pass 1 remains open.**~~

> **⚠ THAT CLAIM WAS FALSE — Pass 1 AUDITED 2026-08-01 (user-directed, after Pass 5 turned up two
> Pass-1 errors).** Of the ten claims below, **seven verified as executed** (1, 2, 4, 6, 6b, 7, and
> the satellites), **one was executed but WRONGLY** (9, the wire-echo "later wins" reconciliation —
> reversed, see §Pass 5 item 5.3), and **two were never applied at all**:
>
> - **Claim 3** — §Sandbox convergence's *"restated, unchanged from the FW-56/57 locks above"* was
>   named as wrong for the read and left standing. Applied now.
> - **Claim 5** — §"Why fixed for v1" was named as rationale-inverted and left standing, still
>   arguing from the `show_if` chain and the stock radio, both cut. Applied now.
>
> **And Pass 1 MISSED a supersession outside its frame.** Two lines below claim 3's target, the same
> section still declared *"Separators PROVISIONAL … chain hop-sep `+` … NOT locked"* — the REJECTED
> char, presented as the leaning value, in the section later passes cite as authority. Pass 1 audited
> the read-token supersessions only; the separator decision landed in Pass 2 and nobody swept back.
>
> **Lesson, and it generalizes past this doc: a pass that records itself DONE is not evidence it is
> done.** Both misses are cheap to detect (grep the named target, confirm the marker exists) and
> neither was detected for a week, because the executed-summary was read instead of the sites. Pass 6
> should verify its own claims the same way — and treat any "nothing remains open" as a claim needing
> a check, not a status.

- §"read-step is a BARE SLUG" (L459): superseded in practice — spike canon emits `use(title)` /
  `use(same)` (control.js:141-143, all PF fixtures); only `key(x)` bare; `default` drops. Note
  supersession, point at Pass 2 for the lock-vs-provisional call.
- §S1 (L576): strong phrasing ("always fully specified; absent read-step not a wire state")
  contradicted by the default-drop collapse + frontrunner table col 1. Core survives (absence
  NEVER means inherit — repeater re-affirms L1122). Reword as inherit-axis rule only.
- §"read folds into the chain as a TERMINAL step" (L444): structurally superseded — read is a
  SIBLING bracket-kv token (`src(chain);use(x)`), not a `/`-joined chain terminal. L891's
  "unchanged from locks" claim is wrong for this.
- Repeater supersession scope (L1098): names only B2/B4 — ALSO dead: B3 (folded `show_if_any`
  predicate), the L735 reveal-trigger rewrite consequence, §Control-model's progressive-reveal
  half (L711). Say so.
- §"Why fixed for v1" (L724): rationale inverted — the repeater spike BUILT the `same`-rewrite
  mutation handling (materialization + compaction-probe) that justified deferral. Note it.
- PINNED-OPEN #1: paren form settled by bracket-kv structure lock; only CHARS open. PINNED-OPEN
  #4: FW-57 assigned — close both, fix the L441 "numbering deferred" header.
- Spike A forward wrinkle (L949): stale — parsers have the `use` branch now. Note it.
- Wire-echo tension: L1104 "NOT a keeper" vs L1166 "real affordance" — later wins; record.
- Satellites: `docs/future-work.md` FW-57 row title (still "intent radio"), FW-45 row ("reveal
  chain" threading + "CONTROL is the blocker" framing), memory `project_slot_payload_fold.md`
  (radio ephemeral / fixed-reveal v1).

### Pass 2 — Serialization-strategy grill (what the UI/UX pass changes about the wire) — ✅ WORKED 2026-07-31, APPROVED same day

> **⚠ HEADER WAS STALE until 2026-08-01 — said "AWAITING APPROVAL" after approval landed.** The
> user approved the wire spec as a whole on 2026-07-31 (§WIRE SPEC status table, every constituent
> green). **Pass 2's OUTPUT was recorded correctly; only its own status line was not** — and that
> line did real damage, because it instructed the FRONTRUNNER's "separators PROVISIONAL" marker to
> STAY, which is exactly the stale marker (with the REJECTED hop-sep `+`) found in the Pass-1 audit.
> **A stale status line is not inert — it can hold a downstream marker in place.** Audited
> 2026-08-01: all six items verified as landed (1 `use()` ratified, both parsers require it ·
> 2 absence → container-class, S1 retired · 3 separators approved, hop `;` · 4 `limit` bracket-kv
> per §DECIDED FORM · 5 ceiling per-container, table's 6 flagged underived · 6 materialization
> promoted to spec rule). Substance sound; status line corrected.

**Outcome: §WIRE SPEC below — APPROVED 2026-07-31.** All six items worked
through. ~~Nothing is settled until the user signs off, and the FRONTRUNNER's "separators
PROVISIONAL" marker STAYS until then.~~ *(Struck — approval landed; the marker it protected was
superseded, see above.)* Two items did not go the way this pass description assumed:
item 5 (one ceiling) came back REFRAMED — the shipped code already disagrees with itself, so the
proposal is per-container rather than one number; item 4 surfaced a live emitter DEFECT rather
than a ratification. Original item list kept below as the audit trail.

1. **Read-carrier: `use(...)` vs bare slug.** The UI converged on `use()` (uniform kv token,
   `use(same)` seed symmetry with `src(same)`). Ratify or revert? Interacts: collapse rules
   (`default` drops; analog==type drops on agnostic containers), readViaUse meta-parity toggle
   (still OFF — if `use()` is canonical for analogs, is `use(key,name)` the natural graduate?),
   ADR 0004 readability.
2. **Absent-read semantics, formal.** Replace S1 with the precise rule: absence = default
   analog (positive, derivable); absence NEVER = inherit; slot ≥2 empty CHAIN is malformed
   (flagged, control cannot emit). Ratify the repeater's seed-materialization posture as the
   wire-level rule, not just control behavior.
3. **Separator chars — the final call.** Lenient classes accepted on parse; pick CANONICAL emit
   chars (`;` `+` `,` `()` leaning). Closes still-open #1.
4. ~~**Per-step `limit`: position settled positional (`refs,related_staff,5` in canon) — ratify;
   default policy (per-position × consuming-tag) still to design.**~~ **SUPERSEDED 2026-07-31** —
   ratification FAILED. The positional form was cut for the `,,` interior-empty hole; canon is now
   bracket-kv `refs,related_staff,limit[5]` (§Per-step `limit` — DECIDED FORM). Default policy
   settled separately as tag- and position-contextual over the value space `0/1/N`.
5. **Slot ceiling: spike registers 8, join lock says 10.** One number, stated reason.
6. **Materialization-on-removal as WIRE semantics.** The compaction fixup is control-side today;
   grill whether the rule ("`same` re-points ⇒ materialize before renumber") belongs in the
   parser/migration spec too (converter renumbering legacy slots hits the same hazard).

Output: locked wire-spec section (grammar + canonical chars + read tokens + malformed states),
superseding the FRONTRUNNER's "provisional" markers.

---

## WIRE SPEC — ✅ APPROVED 2026-07-31 (Pass 2 output, as amended by P3/P4)

> **APPROVED AS A WHOLE — user, 2026-07-31.** Every constituent landed green; the umbrella call
> followed once the two held items closed. Status per section:
>
> | Section | Status |
> |---|---|
> | Read carrier `use(...)` | ✅ **APPROVED** — reversal of the 07-28 bare-slug lock stands |
> | `limit` position + DEFECT | ✅ **APPROVED** — position ratified; defect is a build fix |
> | Slot ceiling (per-container reframe) | ✅ **APPROVED** — try_ 5 / join 10 stand, spike's 8 not adopted |
> | Materialization-on-removal → wire semantics | ✅ **APPROVED** ("looks okay") |
> | Separator CHARS | ✅ **APPROVED** — hop `;` (was `+`); OPT `;` · STEP `,` · L1 `()` · L2 `[]`. See §Hop separator + §Char-selection rationale |
> | Absence semantics | ✅ **APPROVED** — resolved by container class in P3, NOT one rule. See §Absence-by-container + Matrices A/B |
>
> Structure (flat chain, depth-alt brackets, bracket-kv) was locked at §Sandbox convergence and is
> not reopened by any of this.
>
> **What approval unblocks:** the satellite edits held pending this call — `table-tag.md`
> decision #8 rewrite + retrofit (ii) deletion; `docs/future-work.md` FW-24 re-scope, FW-56
> separator resolution, FW-57 update. **What it does NOT do:** authorize a build. The residual
> items (§Residual P3 items, §P4 residual, Pass 6) are build-time work and the build is unstarted.

### Grammar

```
slot value  := token ( OPT_SEP token )*
token       := name BR_OPEN value BR_CLOSE          // bracket-kv, no `=`
chain       := step ( HOP_SEP step )*               // inside src(...) only, FLAT
step        := slug [ STEP_SEP arg [ STEP_SEP limit ] ]

OPT_SEP  ';'    HOP_SEP  ';'    STEP_SEP  ','    L1 brackets  '()'    L2  '[]'
```

Worked example (user, 2026-07-31):

```
{{try_text 1:key(custom_field)|2:src(refs,rel_post,2;terms,category,3);key(other_field)}}
                                      └─ hop ─┘              └─ opt ─┘
```

> **✅ DECIDED 2026-07-31 — but NOT the frontrunner set.** Opt-sep `;`, intra-step `,`, brackets
> `()`/`[]` approved as written below. **The hop-sep is `;`, NOT `+`** — see §Hop separator below.
> Closes still-open #1 and FW-32's `/`-vs-`+` note.

Canonical emit chars — approved (with the hop-sep amended to `;`). Rationale:
the lenient-class work (§Lenient separator classes) already made the CHOICE cheap and reversible —
parse accepts the whole class (`;`/`,` opt · `,`/`;` step · `+`/`/` hop · `()`≡`[]`), role is fixed
by parse POSITION not by char, and emit re-canonicalizes on next control commit. So the char pick
is a readability call with no correctness weight, and the frontrunner's set is the one that was
actually eyeballed against real strings in the sandbox. `+`-over-`/` for the hop stands: FW-32's
old "urldecode trap" objection was a URL-layer concern that GB's `parse_options()` never triggers
(it does not urldecode — `gb-constraints.md` §Option values NOT trimmed), and `/` reads as a path
separator, which is exactly the wrong mental model for an ordered traversal of DIFFERENT kinds.
**Closes still-open #1** and settles FW-32's reopened `/`-vs-`+` note (answer: neither — `;`).

#### Hop separator = `;` — `+` REJECTED on typographic grounds (user, 2026-07-31)

The frontrunner's `+` is **cut**. User finding, confirmed on the worked example: *"`+` is not
serving as well as hoped — seems to pair before+after text instead of whole-step."* This is a real
typographic failure, not a preference. `+` is a binary INFIX operator, so the eye binds it to the
tokens on either side rather than reading it as a boundary:

```
src(refs,rel_post,2+terms,category,3)      ← reads as `2+terms`, exactly the wrong grouping
src(refs,rel_post,2;terms,category,3)      ← `;` terminates, so the step boundary lands right
```

`;` is a TERMINATOR, not an operator — it has no binding pull, so it reads as "end of step".

**This makes `;` do double duty** (slot opt-sep AND chain hop-sep), which the frontrunner's
three-glyph tier was avoiding. Accepted, because the two roles are the SAME SENSE at different
levels — "next sibling here" — and the BRACKET already marks the level unambiguously to the eye: a
reader sees `src(` and knows everything to the matching `)` is chain-internal. No depth arithmetic
required. That is more coherent than `+`, which asserted a distinct relation that does not exist.
The parser already disambiguates by depth, and both chars are in the lenient classes
(`OPT_CLASS = [';', ',']`, `STEP_CLASS = [',', ';']`), so this is a canon change, not a parser change.

Cost, stated honestly: the glyph tier collapses from three roles to two (`;` sibling / `,`
intra-step), leaning harder on the bracket for level. Net gain anyway, since `+` was actively
MIS-signalling.

**Rejected candidates and why** (recorded so this is not re-litigated):

| Char | Verdict |
|---|---|
| `+` | Infix pairing — binds visually to adjacent tokens, not the boundary. The finding above. |
| `/` | Reads as a PATH (nested containers); a chain traverses different KINDS. FW-32's original lean; its urldecode objection was never the real issue (GB's `parse_options()` does not urldecode) but the path-reading stands. |
| `>` | **UNSAFE** — RichText entity-encodes `& < > " '` (`gb-constraints.md` §Tag-string-unsafe, table at :394); round-trips as `&gt;`. Same liability class as the already-cut quote-wrap. |
| Unicode (`→` `»` `·`) | **Not typeable.** ADR 0004 makes the wire hand-editable; a char the author cannot type on a normal keyboard breaks that outright. Rules out the whole non-ASCII escape hatch. |
| `.` `-` | Collide with real field keys / slugs (`related_staff`, `post-type`) — would be ambiguous against step ARGS. |
| `~` `!` | Typeable and safe, but read as negation/approximation — semantic noise worse than `;`'s neutrality. |

`;` is the last candidate standing, and not merely by elimination: it is the only one that reads
as a terminator rather than asserting a relation, which is precisely what makes the dual role work.

#### Char-selection rationale, consolidated (user, 2026-07-31)

The reasoning behind the whole char set, recorded because several of these decisions were resting
on weaker or partial arguments elsewhere in this doc. **These are LEGIBILITY-AGAINST-GB's-`|`
arguments** — the tag string is always read in the presence of GB's own option separator, so a
char's fitness is judged in that context, not in isolation.

1. **`[]` next to GB's `|` is hard to read; `()` differentiates better.** This is the PRIMARY
   argument against two things at once: (a) the bracket-OUTSIDE schema variants (where the slot
   value as a whole was wrapped), and (b) `[]` as the primary/L1 bracket char. Vertical strokes
   next to square corners create visual noise — `…|[foo]|…` reads worse than `…|(foo)|…`. Parens'
   curvature distinguishes them from `|` at a glance. **This retroactively strengthens the L1=`()`
   / L2=`[]` assignment**, which until now was justified only as "outer level is the common case";
   the real reason is that L1 is where `|` proximity happens, so L1 gets the char that contrasts
   with `|`.
2. **`N:` stands out better when option tokens are NOT outer-bracketed.** The slot ordinal is the
   author's primary navigation handle in a long tag string. Wrapping every option token in an
   outer bracket buries it in punctuation; leaving tokens bare-with-inner-brackets keeps `1:` /
   `2:` visually prominent. Independent argument for the same conclusion as (1) — and an argument
   the bracket-kv schema satisfies by construction.
3. **`/` and `+` both read as CONNECTORS, not separators.** Generalizes the `+`-infix finding above
   to cover `/` on the same axis: both assert a RELATION between what they join (sum / path),
   whereas a step boundary needs a char that merely ENDS the preceding item. This is the cleaner
   statement of why both were cut — the earlier `/` rejection leaned on the path-reading, which is
   a specific instance of this general point.
4. **`/` inside the step (`terms/category`) was passable but too loud.** Worth recording as a
   TESTED alternative, not a hypothetical: using `/` for the intra-step arg separator worked
   semantically, but its visual weight is disproportionate to its structural role — an intra-step
   arg is the INNERMOST, least-structural boundary in the grammar, so it should be the QUIETEST
   glyph. `/` competes for attention with the higher-level separators instead of receding.
5. **`,` was inherited from GB and works.** Intra-step keeps `,` — familiar from GB's own option
   conventions, visually light (sits on the baseline, low ink), and correctly subordinate to `;`.
   Continuity with GB's existing grammar is a real asset for hand-editing (ADR 0004).

**Principle these share, worth stating once:** separator weight should be INVERSE to nesting
depth — outer boundaries get the visually stronger glyph, inner boundaries the quieter one.
`;` (strong, structural) at slot/step level, `,` (light) intra-step, with brackets carrying the
level distinction. `+` and `/` both violated this by being loud AND relational at a boundary that
needed quiet and terminal.

#### Overturned prior: `;` was once rejected for low contrast (user, 2026-07-31)

**An earlier, UNRECORDED rejection of `;` is hereby reversed.** The prior assumption: `;` would
not contrast enough with `:` and `,` — the three share a comma-shaped lower mark, so a dense
string was expected to blur. **Tested in the sandbox against real strings: wrong. It works well.**

Why the prior was wrong, in hindsight: the three chars are never in COMPETITION at the same level.
`:` binds an option NAME to its value (and the ordinal to its slot) — it always has a name
immediately left of it; `;` always follows a complete VALUE, frequently a closing bracket
(`use(same);key(x)`); `,` sits between bare args inside a step. Position and neighbours
disambiguate them before glyph shape matters. The feared blur assumed they'd appear
interchangeably, which the grammar never produces.

Recorded for two reasons. **(1) The rejection existed only as an unwritten prior** — searched this
doc, `docs/`, `tools/`, and the git log; nothing anywhere states it. An assumption with no record
is one that quietly returns, and this one would now contradict a decided char. **(2) It is a
methodological data point** — see the practice note directly below, which this is the third
instance of.

#### Visual review of serialized forms is a REQUIRED phase, not a nicety (user, 2026-07-31)

**Two consecutive major schema changes have had proposed syntax that did not survive to shipping.**
A pattern with a cost history, not a run of bad luck:

- **1.6.0** (source-agnostic base-tag architecture — the N×M source×template collapse,
  `CHANGELOG.md:401`, `docs/deprecated-tags-options.md`): proposed syntax **derailed a build in
  progress**. The most expensive possible discovery point — after implementation had started.
- **This change (FW-56/57)**: caught before any build work — a materially better outcome. The
  preview phase itself was ROBUST (user, correcting an earlier overstatement here): the
  `tools/preview/tag-string-preview.html` sandbox did its job, and this session was deliberately
  PAUSED for a further preview pass before returning to finalize separators. **The one real gap
  was VARIETY OF FORMS, not rigour or timing.** The spike nonetheless began with `+` as hop-sep,
  and three glyph findings surfaced only once enough DIFFERENT shapes were on screen together:
  `+` binds as an infix rather than reading as a boundary; `/` intra-step was semantically fine
  but visually too loud; and `;` — long rejected on an unwritten low-contrast assumption —
  actually works well.

**The lesson, stated precisely:** the failure mode is not "no preview" or "preview too late" — it
is **insufficient VARIETY of serialized forms under review**. Typographic and legibility
properties are comparative: they appear when multiple real, dense, STRUCTURALLY DIFFERENT strings
are viewed together, and stay invisible when the sample is narrow, however carefully that sample
is examined. A thorough review of too few shapes still misses them. Every finding in the
char-selection rationale above came from LOOKING at a widened sample; none came from reasoning.

**Practice for the next schema change (and for FW-59, which touches base-tag free-form values):**

1. **Cover the FORM SPACE, not just the canonical example — the primary rule.** Deliberately
   enumerate structurally different shapes before reviewing: bare read vs analog vs `same`; zero-,
   one-, and multi-hop chains; with and without `limit`; free-form values carrying `:` / `|` /
   inner brackets; a long multi-slot tag; a table column beside a join slot. Narrow samples are
   what defeated an otherwise-rigorous preview phase this time. Rigour on too few shapes does not
   substitute for breadth.
2. **Review those forms SIDE BY SIDE.** The `[]`-vs-`|` legibility finding and the `+`-infix
   finding are both COMPARATIVE — neither is visible in a single string examined alone, no matter
   how closely.
3. **Include the surrounding context.** These strings are always read next to GB's `|`, inside
   `{{...}}`, in an editor field. A glyph judged in isolation is judged in the wrong environment —
   the entire `[]`-vs-`()` call turns on `|` proximity.
4. **Record rejected candidates WITH reasons** (the table above). Both derailments involved
   re-examining choices whose original rationale was unwritten; an unrecorded rejection returns.
5. **Treat "it parses correctly" as necessary but NOT sufficient.** `/` intra-step worked
   mechanically and was still wrong. Round-trip harness green ≠ grammar shippable.

Carry into Pass 6 as a standing practice, and cite in FW-59 (base-tag free-form bracketing) — that
item proposes changing how EXISTING base-tag values serialize, so it inherits the same failure
mode and must not skip the visual-review gate.

> **✅ RE-RUN 2026-08-01 — IT FAILS, and the fix is a real narrowing of the lenient-parse design.**
> The obligation below was recorded but never discharged. Ran the approved config through the actual
> validator (`tools/test/slot-fold-roundtrip-spike.php`, which is where
> `bws_spike_grammar_validate()` lives — NOT `tools/spike/proto-fold-tag.php`, whose comment cites it):
>
> | Config | Result |
> |---|---|
> | **APPROVED** (`hop_sep ';'`, `hop_class {;,/}`) | ❌ `hop_class ∩ step_class ≠ ∅` |
> | spike default (`hop_sep '+'`) | ✅ valid |
> | APPROVED **+ `step_class` narrowed to `{,}`** | ✅ valid |
>
> **Cause:** the lenient classes were `opt {;,}` / `step {,;}` — `step_class` accepts `;` — so making
> `;` the canonical HOP char puts it in both classes that share the in-chain position. **Consequence
> (a cost the hop-`;` decision did not price): the step axis LOSES its lenient `;` acceptance.**
> Inside a chain, `;` must mean hop and only hop; a hand-typed `refs,office;region` can no longer be
> forgiven as an intra-step separator, because that spelling is now a two-step chain. Parse stays
> lenient elsewhere (`opt` still accepts `{;,}` — that pair is disambiguated by bracket depth, and
> the validator deliberately does NOT check opt ∩ hop for that reason).
>
> **NOT a spike defect** (user, 2026-08-01: *"I finalized separators after the spike"*) — the spike's
> `+` is chronology, and it validates cleanly on its own config. The finding is narrower and worse:
> **the APPROVED grammar has never been machine-checked or round-tripped.** All 176 harness cases and
> all 18 probe cases run on the superseded separator. Both suites are grammar-parameterized, so
> re-running them under the approved config is cheap — do that BEFORE the build treats "round-trip
> proven" as covering the shipped grammar.

**Build obligation:** `;` now appears in BOTH `OPT_CLASS` and `STEP_CLASS` as the canonical emit
char, so re-run the disjointness validator (`bws_spike_grammar_validate`) — it exists to catch
exactly this class of overlap turning ambiguous. Depth-disambiguation should satisfy it; VERIFY,
do not assume. Add a hop-and-opt-in-one-value case to the round-trip harness
(the worked example above is the natural fixture).

### Read carrier — `use(...)` RATIFIED (item 1)

The 2026-07-28 "bare slug, no `use` carrier" lock is **REVERSED**. Decided on three grounds:

1. **Both spike parsers already require it.** `proto-fold-tag.php:171` has a `use` case and NO
   bare-slug branch — a bare analog falls to `default:` and lands in `extra[]`. The emitter
   (`proto-fold-control.js:141-143`) writes `use(...)`. The bare-slug form was never implemented
   on either side, so "revert" would mean building something new, not restoring something.
2. **Uniformity beats brevity here.** Every other thing in a slot value is `name(value)`. A bare
   slug is the ONE token whose meaning comes from position rather than name — the same
   positional-vs-named fragility FW-24 rejected for the chain. `use(same)`/`src(same)` seed
   symmetry is a real readability win (ADR 0004).
3. **The original argument doesn't survive the sibling reframe.** "The analog names itself, like
   hops do" held while the read was a chain TERMINAL step (bare slugs among bare slugs). Once the
   read became a SIBLING bracket-kv token beside `src(...)`, its neighbours are all named — so
   naming it is now the consistent choice, not the redundant one.

Read tokens, complete: `use(<analog>)` · `use(same)` · `key(<field>)`. `key` stays its own token
name (not `use(key,name)`) — it takes an arg of a different KIND (a user-authored field name, not
a vocabulary term), and the discovery control binds to `key` specifically. The `readViaUse`
meta-parity toggle stays OFF and is now moot: it was asking whether analogs should ride `use()`,
which is exactly what this ratifies. No `text` token (unchanged — `{{text}}`=`{{field}}` identity).

### Absent-read semantics — 🔄 REOPENED (item 2)

> **⚠ USER REOPENED 2026-07-31: "absence needs further discussion — there MAY be a difference by
> tag here (try_ has different logic than join/table)."** The single-rule proposal below is
> SUSPENDED pending §Absence-by-container. Reading the shipped resolvers confirms the instinct and
> makes it sharper than a config difference — see below. S1 is NOT yet replaced.

#### Absence-by-container — evidence from the shipped resolvers (2026-07-31)

Both shipped containers give absence a MEANING, and it is **not the same meaning**, and neither
matches the proposal's "absent chain on slot ≥2 = malformed":

| | `try_` (`class-tag-template-registry.php:680-719`) | `join` (`base-tags.php:1025-1057`) |
|---|---|---|
| empty `src` | **inherit** (`'' = same`, carry-forward `$last_src`) | **inherit** (`'' = same`, carry-forward `$last_src`) |
| empty `use`+`key` | slot **SKIPPED** (`$has_new` false → `continue`) | slot **SKIPPED** (`continue`) |
| slot-1 empty `src` | `'current'` (stripped default) | stripped default |
| `same` sentinel | normalized to `''` at slot ≥2, then inherit | `'same'` explicitly checked, then inherit |
| **slot semantics** | **ordered fallback — first non-empty WINS, rest not evaluated** | **assembly — every non-empty slot contributes** |

Two findings, one of which contradicts the proposal outright:

1. **Absence ALREADY means inherit on the shipped wire, in BOTH containers.** The proposal's
   surviving-S1 core ("absence NEVER means inherit") is a *break* with shipped behaviour, not a
   restatement of it. That is defensible — the fold is greenfield and explicit `same` is exactly
   the readability win — but it must be argued as a deliberate reversal with a migration story
   (`bws_spike_fold_from_legacy` already encodes it: slot ≥2 read absent → synthesize `same`).
   It was previously written as though it were the status quo. **This is the real error the user
   caught.**
2. **The container difference is structural, not cosmetic.** `try_` is a SELECTING fold (first
   non-empty wins; a skipped slot is invisible, and an empty slot mid-chain costs nothing because
   evaluation continues to the next candidate). join/table are COMBINING folds (every slot
   contributes; a skipped slot silently drops a value out of an assembled string, or a column out
   of a row). So the COST of a mis-parsed empty slot differs by container: in `try_` it degrades a
   fallback chain; in join/table it corrupts output.

**Open question — SUPERSEDED by the axis split (see §P3 findings, Matrices A + B).** This
paragraph originally asked whether "empty chain on slot ≥2 is malformed" should vary by
container. That framing was wrong on the axis: it is a `src`-axis question, and the `src` axis
turns out NOT to be container-sensitive at all (Matrix A — no open cells). The genuine
container-dependence lives on the READ axis (Matrix B), where the single open cell now sits.
Retained for the audit trail; do not answer as written.

The standing argument for per-container divergence still applies to Matrix B's open cell:
per-container matches shipped semantics, avoids flagging tags that render fine today, and mirrors
the already-approved per-container CEILING decision — the containers are demonstrably not
interchangeable. NB this is the same selecting-vs-combining split the open verb-agnostic RESOLVER
refactor is about (`project_open_refactors.md`) — if read-absence diverges by container, that
refactor's fold-verb distinction becomes load-bearing on the WIRE, not just in the resolver.

#### Three distinctions the absence rule MUST respect (user, 2026-07-31)

Stated before settling anything. All three verified against shipped code; (3) especially, which
the earlier write-up flattened.

**(1) LIFECYCLE — new/resaved tags vs render/migration/mount of SAVED tags.** The approval of
in-slot `use(same)` serialization was granted to make MATERIALIZATION feasible (compaction needs
a literal to rewrite). That approval governs what the control EMITS on a new or resaved tag. It
does **not** license any change to how already-saved `join`/`try_` tags behave on render, on the
migration pass, or at editor mount. Those tags are on the shipped wire, where absence already
means inherit, and they must keep rendering byte-identically. `{{table}}` is unshipped and
therefore has no saved-tag constraint at all — greenfield.

> Consequence for the spec: the absence rule is **two rules, split by lifecycle**, not one.
> "Absence never means inherit" can only ever be an EMIT-side rule for folded values. The
> READ side must continue to honour absence-means-inherit for legacy-shaped input, permanently
> if unmigrated tags persist. These are not in conflict as long as the doc never states the
> emit rule as though it were a universal parse rule — which is exactly the error above.

**(2) PER-TAG SLOT LOGIC** — the selecting-vs-combining table above. Already recorded; stands.

**(3) `src` and `use` DO NOT SHARE AN INHERIT STORY.** The earlier proposal treated the two axes
symmetrically (`src(same)` / `use(same)` as one seed). That symmetry is real only in `try_`:

- **`src:same` is coherent on all three containers.** A slot can sensibly read a DIFFERENT field
  off the SAME source — that is the common case in join ("name and phone, both off this staff
  post").
- **`use:same` is coherent ONLY in `try_`.** In a selecting fold, "same field, different source"
  is the entire point of a fallback chain. In a COMBINING fold it is degenerate: `use:key` twice
  with the same key assembles the identical datum twice, and `use:title` twice reads the same
  title twice. A join/table slot is only properly configured when it targets a DIFFERENT field.

This is not an inference — it is shipped, deliberate, and documented in place. Join's option
builder omits the same-row from `use` while keeping it on `src`, with the reason in the comment
(`base-tags.php:895-897`): *"Slot ≥2 keeps the `same` inherit row: source can sensibly carry
forward in combining; field identity cannot."* The PHPDoc (`base-tags.php:825-828`) states the
same and notes this is *"a concrete reason join owns its build loop rather than reusing the try_
per_slot_use emit, which hardcodes the same-prepend"*. `key` is likewise marked *"per slot, never
inherited"* (`:922`).

> Consequences for the fold, all of which the single-rule proposal got wrong:
> - The repeater's `src(same);use(same)` seed is **wrong for join/table**. It seeds a degenerate
>   duplicate-datum slot. The correct seed is container-dependent: `src(same)` + an UNSET read for
>   combining containers (author must choose a field — that is the whole configuration act),
>   versus `src(same);use(same)` for selecting containers. The spike used one seed because it only
>   ever exercised one container.
> - ~~`use(same)` may not even be a legal token in a combining container's slot value; reject at
>   emit.~~ **WITHDRAWN — see §`use(same)` in combining containers below.** It is LEGAL there.
> - The absence asymmetry I proposed (read-absent = default, chain-absent = malformed) needs
>   re-deriving per axis AND per container, since "read absent" in a combining container means
>   "unconfigured slot", not "default analog".

#### `use(same)` in combining containers: LEGAL, never DEFAULT, not built yet (user, 2026-07-31)

The "reject `use(same)` at emit for join/table" proposal is **withdrawn**. User counterexample:

> Once a per-slot HANDLER is selectable, an author may want `use(same)` with a *different format* —
> e.g. splitting one datetime field into a date slot and a time slot.

The degeneracy argument was never about `use(same)` per se. It was: *same source + same read =
the same datum twice*. That holds only while the READ is the whole output specification. Under
the container-conditional TYPE token (already locked, and available precisely on the
format-AGNOSTIC containers), a slot also carries its own processing tag, so `use(same)` with two
different handlers yields two DIFFERENT outputs from one datum. Combining is satisfied: nothing
duplicates. `{{join}}` over `date-part | time-part` off a single field is the clean case, and it
is a strictly better authoring story than forcing the author to re-select the same field twice.

Note this makes the two container classes differ in the REASON `use(same)` is useful, not in
whether it is:
- **selecting (`try_`)**: same field, different SOURCE — a fallback chain.
- **combining (join/table)**: same field AND source, different HANDLER — a decomposition.

Three-part classification, and the distinctions matter:

1. **LEGAL** in combining containers. The grammar does not restrict `use(same)` by container; a
   parser must accept it wherever it appears. Do NOT special-case it out at emit.
2. **NEVER THE DEFAULT** there. Shipped join deliberately omits the same-row from its `use` enum
   (`base-tags.php:895-897`) and that stays right for the DEFAULT/seed path: with no handler
   selected, `use(same)` in a combining slot IS degenerate, so the combining seed remains
   `src(same)` + unset read. Legality is about what the wire may express; the seed is about what
   the control offers unprompted.
3. **NOT BUILT until the handlers exist.** Explicit user constraint: do not build this ahead of
   per-slot handler selection. Until then it is a grammar allowance with no editor surface — the
   control simply never emits it for combining containers, and a hand-written one round-trips.

> **Bearing on the open absence cell.** This does NOT reopen read-absence. Absent read on a
> combining slot still means UNCONFIGURED (shipped resolver skips — `base-tags.php:1035`);
> `use(same)` is an EXPLICIT token and its legality says nothing about what absence means. The
> two stay cleanly separated, which is itself an argument for the explicit-`same` design: had
> absence meant inherit, this legal-but-non-default case would be unspellable.
>
> **Bearing on the container-conditional TYPE.** Strengthens it. Per-slot TYPE was justified as
> heterogeneous-slots-for-free; this adds a second, independent justification — it is what makes
> `use(same)` meaningful in a combining container at all. Worth citing in that section's rationale.
>
> **Interacts with:** FW-24 (multi-option terminal tags — a datetime slot needs its `format`
> alongside the type); FW-20 (read-token fold). **See §FW-24 status below — the gate is smaller
> than "FW-24 lands".**

#### FW-24 is mostly SATISFIED by the spiked syntax (user, 2026-07-31) — assessment, not a build

User: *"FW-24 is almost satisfied by the spiked syntax. Not looking for a full build right now."*
Correct, and the tracker row understates it. FW-24's open questions were: (a) can a whole base tag
ride a slot on a flat, `}`-free wire; (b) positional-vs-pairs encoding; (c) comma-in-value escaping;
(d) schema coupling. The spike answers (a)-(c) as BUILT-AND-ROUND-TRIPPED, not as a proposal:

- **Type token parses today.** `bws_spike_fold_parse_slot` matches a bare token against a type
  vocabulary — `title`, `content`, `email`, `phone`, `permalink`, `image`, `datetime_single`,
  `datetime_range` (`proto-fold-tag.php:117,133-134`). A slot ALREADY names its processing tag.
  That is FW-24's core ask (heterogeneous tag-per-slot) on the wire, working.
- **Pairs, not positional** — settled by the bracket-kv schema. FW-24's own row already recorded
  the 2026-07-29 sandbox as validating this direction; the spike then implemented it.
- **Escaping settled** — brackets inert to GB, balance-aware sub-parser ours, `\:`/`\|` for the
  live chars, `{`/`}` still barred (which is why nested braces were never the route).
- **Unknown tokens survive.** Anything unrecognized lands in `extra[]` (`:135`) and re-emits, so
  a future option keyword does not break an older parser — the schema-coupling worry (d) is
  softened, though not formally specified.

**What genuinely remains of FW-24** is narrower than the row implies, and it is exactly the
blocker for the date/time-split case above:

> **Multi-option terminal tags.** A slot can name `datetime_single`, but it has nowhere to put
> that tag's OWN options (`format`, image `as`/`size`, link cluster). One more sibling token per
> option is the obvious shape (`datetime_single;src(same);use(same);format[g\:i A]` — note the
> free-form bracket, FW-59) and the grammar already permits it, but the OPTION VOCABULARY per
> type is unspecified, and the editor has no per-slot sub-control for it.

So the date/time split is gated on **per-type option tokens**, not on FW-24 wholesale. That is a
smaller, well-shaped piece of work — and the grammar takes it without change.

**No build implied.** This is a status correction for the tracker (FW-24's row should note the
spike satisfied (a)-(c) and re-scope its remainder to multi-option terminals), pending the wire
spec's approval. Do not start it.

**Where this leaves item 2:** the single formal rule is withdrawn. What replaces it is a small
MATRIX — axis (`src`/read) × container class (selecting/combining) × lifecycle (emit/parse) —
and the next pass should build that matrix explicitly rather than trying to collapse it early.
The collapse is what produced the errors here.

#### The suspended single-rule proposal (unchanged text, for reference)

S1's strong phrasing ("slot value always fully specified; no absent wire state") is **retired**.
The precise rules:

- **Absence of a read token = the `default` analog.** Positive and derivable, not a hole: the
  emitter DROPS `default` (`control.js:143` guards `'default' !== r.slug`), so absent is the
  canonical spelling of default. A slot with only `src(...)` is well-formed.
- **Absence NEVER means inherit.** The surviving core of S1. Inheritance is ALWAYS explicit
  `same` on both axes (`src(same)`, `use(same)`). This is what makes a slot value readable in
  isolation and what the repeater's seed depends on.
- **An empty CHAIN on slot ≥2 is MALFORMED**, not an implicit `same`. The control cannot emit it;
  a parser meeting one flags rather than guesses (same posture as the FW-51 broken-slot flag).
- **The repeater's seed is the WIRE rule, not control behavior.** "Add slot" writing explicit
  `src(same);use(same)` is the serialization contract: any slot the author has materialized
  exists on the wire with both axes stated. Control convenience follows from the rule; it is not
  the source of it. *(⚠ The SEED SHAPE here is wrong for combining containers — `use(same)` is
  degenerate in join/table; see §Three distinctions (3). The principle "a materialized slot is
  explicit on the wire" survives; the literal `use(same)` half does not generalize.)*

Note the asymmetry and keep it deliberate: READ absence = default, CHAIN absence = malformed
(on slot ≥2). Justified because a default analog is a real, resolvable read, whereas there is no
default SOURCE for a slot ≥2 — the only candidate meaning would be inherit, which rule 2 forbids.

### Per-step `limit` (item 4) — ⚠ REOPENED 2026-07-31 → now BRACKET-KV (`limit[5]`)

> **⚠ THE POSITIONAL RATIFICATION BELOW IS SUPERSEDED.** User reopened it ("we didn't push hard on
> `limit`") after the `,,` defect. **Decision: `limit` becomes a bracket-kv step token** —
> `refs,related_staff,limit[5]` — with the slug positional and `arg` positional, but the optional
> `limit` NAMED. Full reasoning + the FW-24 prerequisite it exposed in §Per-step `limit` — DECIDED
> FORM below. Text kept for the audit trail.

Positional at index 2 (`slug,arg,limit` — `refs,related_staff,5`) is **ratified**: both sides
already implement it (`proto-fold-tag.php:167`, `control.js:134-135`) and the alternatives
(a named token inside a flat chain, or a 4th slot-level token) either break the chain's flatness
or detach the limit from the step it bounds.

> **⚠ DEFECT (found in this pass — carry to the build, spike is wrong today).** The emitter writes
> the two args with INDEPENDENT guards: `if (s.arg) seg += ','+s.arg; if (s.limit) seg += ','+s.limit;`
> An argless step carrying a limit therefore emits `entries,5`, which the positional parser reads
> back as **arg=`5`, limit=null** — silent corruption, not a parse error. This is reachable:
> `control.js:729` explicitly writes `arg: null` while PRESERVING `step.limit` when the author
> switches a step's slug away from `refs`. The spike never showed it only because no UI surface
> sets `limit` yet. **Fix at build: emit a placeholder for the empty arg slot** (`entries,,5`) and
> have the parser treat an empty middle segment as null-arg. Add to the round-trip harness as a
> named case; positional encodings need the hole to be spellable.
>
> **✅ FIX EMPIRICALLY VERIFIED 2026-07-31** (`php -r` against the live `preg_split` step regex).
> `entries,,5` → `slug=entries arg='' limit='5'`: the placeholder lands the limit in the correct
> positional slot, so the approach is sound. **But the fix has TWO halves, not one** — the middle
> segment parses back as **`''`, NOT `null`**:
>
> | input | slug | arg | limit |
> |---|---|---|---|
> | `entries,,5` | `entries` | `''` ← *not null* | `'5'` |
> | `entries,5` | `entries` | `'5'` ← *the defect* | `null` |
> | `refs,related_staff,5` | `refs` | `'related_staff'` | `'5'` |
> | `entries,,` | `entries` | `''` | `''` ← *empty, not absent* |
>
> So the parser MUST normalize an empty middle to `null` explicitly, or the defect is merely
> traded: `''`-vs-`null` diverge across the two sides (`proto-fold-tag.php:369`'s incomplete-hop
> check tests both, but the JS emitter's truthy `if (s.arg)` guard does not, so an `''` arg
> re-emits as absent and a round-trip is lossy in a different place). Normalize BOTH args on parse
> (`'' → null` for arg and limit alike — see the `entries,,` row, which yields `limit=''`).
> **Harness cases to add:** all four rows above, asserting emit(parse(x)) === x.

### Per-step `limit` — DECIDED FORM: bracket-kv `limit[5]` (2026-07-31)

**Positional `limit` is too fragile.** The datetime precedent (`combined-option-controls.md:137`
§The `,,` interior-empty problem) resolved the identical hazard by making the interior gap
STRUCTURALLY IMPOSSIBLE — a toggle gates the second slot so the only empty is a clean TRAILING
one. **That dodge does not transfer:** datetime's slots are ordered by dependency (no time without
a date), whereas `limit` has no dependency on `arg` — argless-with-limit is a legitimate state the
control already produces (`control.js:729` nulls `arg`, preserves `limit`). So the interior gap is
reachable here, and `,,` must either be spellable or the encoding must change.

Three reasons it changes:
1. **Interior gap reachable** — the datetime resolution is unavailable.
2. **Fails silently** — `entries,5` is not a parse error; it parses as arg=`5`. Corrupted meaning,
   no flag.
3. **Contradicts a ratified premise** — bracket-kv was chosen over positional CSV precisely so
   token ORDER is never semantic (the same argument that condemned read-token last-wins). Positional
   `limit` re-imports positional fragility into the last place inside a step where it survived.

**Form: `refs,related_staff,limit[5]`** — slug + `arg` stay positional (required, order-carrying),
the OPTIONAL `limit` is named. Common case is unchanged (`refs,related_staff`), the hole becomes
unspellable rather than spelled `,,`, and a hand-edited wire self-describes (ADR 0004).

**Depth: alternation HOLDS; portability comes from parse leniency, not from pinning** (user,
2026-07-31). The chain is a PORTABLE construct — base tags (`src:` at depth 0), slots
(`src(...)` inside L1), and `{{table}}` needs BOTH LEVELS IN ONE TAG. So the same step emits
`limit(5)` at depth 0 and `limit[5]` inside a slot. Two spellings, one construct — and that is
FINE, because:
- **The parser is already depth-agnostic.** `proto-fold-tag.php:126-140` picks whichever pair
  opens FIRST in the token and looks its partner up in `BR_PAIRS` — the pair is discovered from
  the CHAR, never from ambient depth. Either spelling parses at either depth.
- **Emit re-canonicalizes to the writing depth** (same normalize-on-commit model as separators),
  so a pasted `limit[5]` at depth 0 round-trips and simply re-spells on next commit.
- *(An earlier proposal to PIN `limit` to `[]` at all depths — on the theory that a bare-scalar
  leaf cannot nest so needs no alternation — is DROPPED. It protected a strict-parser property
  that does not exist, and would have broken the alternation rule for no gain.)*

**✅ RE-CONFIRMED 2026-07-31 (user: "keep bracket alternation") — pinning is CLOSED.**
The pin was reopened once more when `{{table}}` became the first construct to show BOTH depths
inside a single tag string:

> `{{table src:post,9999;refs,related_staff,limit(5)|…|2:label(Depts);src(terms,department,limit[3]);use(title)}}`
> — `limit(5)` on the row chain (colon form, no wrapper → L1) and `limit[3]` in a column chain
> (inside the slot bracket → L2). Verified as the rule working, not an emitter bug.

Reviewed on that row and alternation was KEPT. So the two-spelling consequence is now an
ACCEPTED, reviewed property of the grammar, not an unexamined side effect — the strongest case
against it (same token, two chars, one tag) has been looked at directly and judged principled.
Do not re-open on aesthetic grounds; a future challenge needs a NEW argument (e.g. a parser or
authoring failure), not a restatement of the visual objection.

**Why this matters beyond `limit`:** alternation is what keeps bracket DEPTH trackable through
nesting, which the bracket-aware splitter and FW-24 (tag-in-slot) both depend on. Pinning any one
token would have made depth locally unreadable at exactly the place FW-24 needs to nest.

⚠ **A chain has TWO wrapper styles, and `limit`'s char differs between them. This is the rule
working, not drift.** Settled 2026-07-31 after two user-caught emitter bugs.

| wrapper style | who emits it | bracket enclosing the chain | `limit` char |
|---|---|---|---|
| **colon** `src:refs,x` | every base-tag emitter; `{{table}}` row + column chains | NONE (depth 0) | `limit(3)` |
| **bracket** `2:src(refs,x)` | slot values only (`serializeSlot`, `chainStr` case) | L1 `(` | `limit[3]` |
| (FW-24) chain one level deeper still | future tag-in-slot | L2 `[` | `limit(3)` |

So the SAME chain legitimately spells `limit(3)` on a base tag and `limit[3]` in a slot. Any review
that sees both and reads "schema varies" should re-check the wrapper before treating it as a bug.

**Rule for the build, stated so it cannot drift:** `limit` sits one level INSIDE whatever encloses
the chain, so its char is `bracketAt(<enclosing level> + 1)`. The emitter that prints the wrapper
passes its own level down; nothing recomputes depth independently.

**Both bugs came from violating exactly that.** (1) The slot caller passed the chain's own nesting
level (2) instead of the wrapper's (1) → `src(…,limit(2))`, same char nested. (2) The fix then made
1 the default, which is right for slots but wrong for the colon form that has no wrapper at all →
`src:refs,x,limit[3]`, skipping L1. Both were caught by USER EYEBALL on rendered permutations; both
survived a harness, because the harness was written from the same wrong premise as the code and
tested an extraction rather than a rendered tag.

**Therefore, required in the round-trip harness (not optional):** assert **no same-char immediate
nesting** across the full emitted tag — a ~5-line bracket walk. It is independent of the emitter's
model of depth, so it catches this entire class mechanically. It is now run over both wrapper styles
in the preview tool and passes; the equivalent assertion must exist on the PHP side at build. Do not
rely on reasoning about depth being correct — assert the invariant on the output.

#### ⚠ PREREQUISITE this exposes: the step splitter must be BRACKET-AWARE (FW-24 constraint)

**User constraint: nested brackets must be safe for FW-24.** The step splitter is a bare
`preg_split( '/[,;]/', $seg )` today. Tested 2026-07-31:

| step segment | naive split | verdict |
|---|---|---|
| `refs,related_staff,limit[5]` | `["refs","related_staff","limit[5]"]` | ✅ — but only because a bare integer cannot contain a separator |
| `refs,related_staff,label[A, B]` | `["refs","related_staff","label[A"," B]"]` | ❌ shredded |
| `refs,x,tag[datetime_single,format[g:i]]` | `["refs","x","tag[datetime_single","format[g:i]]"]` | ❌ shredded — **this is the FW-24 shape** |

So `limit[5]` is safe under the naive splitter by ACCIDENT (bare-int value), not by structure. The
moment a step token carries free-form or nested content — which is exactly FW-24's whole-tag-in-slot
— it splits mid-token. **Build the splitter bracket-aware NOW:** scan tracking bracket depth, split
only at depth 0, mirroring the token-level depth guard that already exists at
`proto-fold-tag.php:146-157` one level up. This is the "balance-aware sub-parser (WE own it)"
FW-24 already names as ours; the only new fact is that it is a PREREQUISITE, not follow-on work —
writing it naively now means FW-24 replaces it and every wire stored in between is ambiguous.

**Consequence worth keeping:** with a depth-aware splitter, bracket ALTERNATION stops being
cosmetic — alternating pairs are what make depth trackable through `tag[...[...]...]` nesting. The
alternation rule and nested-bracket safety are the same mechanism, so "the rule should hold" and
"nested brackets must be safe" reinforce rather than merely coexist.

**What this retires:** the `,,` placeholder fix and its four harness cases (the ⚠ DEFECT block
above) are MOOT for `limit` — there is no positional hole left to spell. Keep the `'' → null`
normalization for `arg`, which is still positional. The read-token last-wins defect is unaffected.

**Default policy: DEFERRED, deliberately.** It is f(step-position × consuming-tag × output-mode)
per FW-56, and the consuming side (`{{table}}`'s row-set, the `ul`/`ol` output-mode row) is not
settled. Wire-level rule that IS locked: **the limit is always OPTIONAL and never inferred onto
the wire** — an absent limit serializes as absent and the default is applied at RESOLVE time, so
changing the default policy later never invalidates a stored tag. That is the only part the
serialization spec needs to commit to now.

#### Default is TAG-CONTEXTUAL and POSITION-CONTEXTUAL (user, 2026-07-31)

> ⚠ **SUPERSEDED 2026-08-05 — [`per-step-limit.md`](docs/design-history/per-step-limit.md) §Uncapped defaults + §The spelling
> is the era marker.** Two defects, both traced: (1) *"the current default limit is 1"* conflates
> `bws_clamp_limit`'s TERMINAL cap with a per-hop cap — the engine has never capped a hop
> (`traversal-pipeline.php:92-93`, absent ⇒ no slice), so "preceding steps default to 1" would
> INTRODUCE a cap rather than preserve one, breaking 5h's byte-exact equivalence. (2) The
> *"LAST src-chain step"* framing is vocabulary from the CUT read-as-terminal-step model; under the
> ratified sibling read no step produces values. **Live rule:** per-step default uncapped, position
> plays no part, and the ⚠ OPEN below (terminal reassignment on append) DISSOLVES rather than being
> answered — neither dynamic nor materialize-on-append is needed.

**Rule stated:** the current default limit is **1**; `{{table}}` needs its **LAST src-chain step**
to default to **0/all**, with **preceding steps still defaulting to 1**. Directly analogous to the
tag-contextual `use` dispatch — same shape: one wire, resolution supplied by the consuming tag.

This is NOT a new policy but the PREDICTED function instantiated — it confirms two of the three
axes named above (**step-position**: terminal vs preceding; **consuming-tag**: table vs scalar).
Output-mode remains the unexercised third. **The deferral paid off exactly as designed:** because
an absent limit never rides the wire, `{{table}}` can adopt a terminal-step default of 0/all
without touching a single stored tag.

**⚠ `0` MUST be a real value, not a falsy hole.** Three states have to survive round-trip:

| state | wire | resolves to |
|---|---|---|
| absent | *(no token)* | the contextual default (1, or 0/all on a table terminal step) |
| explicit all | `limit[0]` | all — AUTHOR-PINNED, independent of the default |
| explicit N | `limit[5]` | 5 |

**Value space = `0` / `1` / `N` (assumed, user 2026-07-31)** — plain integers, `0` = all. Chosen
because it is the `WP_Query` `posts_per_page` idiom a WP author already reads as "all", and it
keeps the token a BARE-SCALAR leaf (which is what makes `limit[N]` safe under the naive splitter
and keeps it free of the free-form/nesting hazard above). A keyword spelling (`limit[all]`) was
considered and dropped: it would trade a stable integer for a vocabulary needing its own parse
rules for no wire benefit.

**Control shape DEFERRED to the `{{table}}` authoring pass** (an all-vs-number radio was raised,
user: "not sure it's worth it" — agreed). Reasoning: a radio makes the wrong distinction visible.
The genuine ambiguity is **absent vs explicit**, not all-vs-number — a bare number input cannot
show that an empty box means 1 on a scalar tag but all on a table terminal step. That is a LABEL
problem (surface the inherited value: "Default: all (last step)"), not a control-type problem, and
the cheap instrument already used twice (the `srcTermIn`/datetime React-state toggle,
re-derived on mount, zero wire cost) covers it if a gate is wanted. A radio would also COLLIDE
with the open terminal-reassignment question below: if a step stops being terminal, a radio parked
on "All" asserts a choice the author never made.

The emitter's truthy guard (`if ( s.limit )`, `control.js:135`) treats `0` as absent and would
DROP it. That is the same falsy-collision bug class already found twice on this axis (`''` vs
`null` for `arg`; positional `,,`), so it is a predictable failure, not a hypothetical. If
`limit[0]` collapses to absent, an author who pinned "all" silently reverts whenever the
contextual default later changes — precisely the staleness the wire-level lock exists to prevent.
**Guard on `null`/`undefined`, never truthiness.** Harness case: `limit[0]` round-trips as `0`.

**~~⚠ OPEN~~ — DISCHARGED 2026-08-06 by D4, and never re-derive it: "last step" is POSITIONAL, so growing a chain re-assigns the terminal.** The hazard below is entirely a consequence of a terminal step defaulting DIFFERENTLY from a non-terminal one. D4 (`docs/design-history/per-step-limit.md` §The grill) made every step default UNCAPPED regardless of position, so there is no longer a default to flip and neither reading (a) nor (b) has anything to decide between. Kept as the audit trail of a hazard that closed by a decision taken elsewhere — which is the failure mode this file's index exists to catch, so it is struck rather than deleted. Appending a
step to `entries → refs` makes `entries` non-terminal, so its default silently flips from all to 1
and the author's row-set collapses without any edit to that step. Two candidate readings, NOT
resolved here:
- **(a) Dynamic** — the default always follows whichever step is currently terminal. Simple, no
  wire change, but a distant edit silently changes an earlier step's meaning.
- **(b) Materialize-on-append** — when a step stops being terminal, the control writes its former
  effective limit explicitly (`limit[0]`) so the meaning is pinned. Costs a wire token but matches
  the materialization rule ALREADY approved for compaction, and for the same underlying reason:
  a positional/contextual meaning must be materialized before the position it depends on changes.

(b) is the consistent choice on current precedent (`same` is a positional backreference that must
materialize before renumbering — identical hazard shape), but it is a `{{table}}` authoring-UX
call and belongs with the row-set work. Flagged, not decided.

### Slot ceiling (item 5) — REFRAMED: per-container, not one number

The pass asked for "one number, stated reason". The shipped code already refuses that: try_ tags
hardcode **5** (`class-tag-template-registry.php:466,605,667`), join defines **10**
(`join-helpers.php:68`, driver documented: "a full personal name needs 7 parts + headroom"), and
the spike picked **8** arbitrarily. Two shipped containers with different, defensible caps is not
drift to be unified — a fallback CHAIN and a name-assembly JOIN have genuinely different natural
lengths, and `{{table}}` columns will have a third.

**Locked:** the ceiling is a **per-container registration parameter**, not a global constant. The
spike's 8 was a spike value and is not adopted; existing caps stand (try_ 5, join 10) so the fold
migration changes no author's available slot count.

**`{{table}}` picks its own at build — and its current 6 is NOT that pick** (user flag,
2026-07-31). `BWS_TABLE_MAX_COLS=6` (`table-tags.php:46`) is prototype code on the unreleased
`feat/table-tag` branch and is an UNDERIVED placeholder: nothing computes it, and `table-tag.md`
#8 + the Build list both mark it "provisional; revisit with the option-name pass". Do not cite it
as a decided per-container ceiling the way try_ 5 and join 10 can be cited — those have shipped
behaviour and (for join) a documented driver. Table's real cap is an open option-name-pass
decision, and unlike the other two it has **no migration constraint pinning it** (table is
greenfield), so it is free to be chosen on authoring grounds alone. The


**floor of 2 always-rendered slots is global** and matches the shipped try_ behaviour
(`registry:478` `if ($n <= 2)`) and the spike's MIN — a one-slot try_/join is a degenerate tag,
so two configurable slots up front is correct everywhere.

This also re-scopes FW-45 more sharply than Pass 1 did: with the ceiling a per-container
parameter, "uncapped N" stops being one decision and becomes "does any container need TRUE
unbounded registration" — which GB's static option registration still forbids, so the honest
answer is a raised parameter, not an uncapped one.

### Materialization-on-removal = WIRE semantics (item 6)

**Promoted from control behavior to spec rule.** `same` is a POSITIONAL backreference, so any
operation that RENUMBERS slots must materialize inherited axes BEFORE renumbering, or the
backreference silently re-points at a different source. The rule:

> Before removing or reordering slot N, every later slot whose `src` or `use` is `same` must have
> that axis rewritten to the literal value it currently resolves to. Only then may ordinals shift.

Belongs in the parser/migration spec, not just the control, because **the converter hits the
identical hazard**: migrating legacy `{N}-src`/`{N}-use` keys synthesizes `same` for absent
slot ≥2 reads (`bws_spike_fold_from_legacy`, position rules) and any renumbering during that pass
re-points those synthesized backreferences. Cases already enumerated in `compaction-probe.js`
(18) — Pass 6 carries them into the real harness. Pass 3 must re-check this against the legacy
bridge, since a half-migrated tag can hold `same` alongside legacy keys.

### What this proposal does NOT address (open regardless of the approval call)

Deliberately still open, with owners: `limit` DEFAULT policy (FW-56, needs the consuming-tag
decisions); `default`-rename coupling; the bare-`key` name collision under `scope:'row'` (Pass 4 —
a NAMING collision inside the control, not a grammar hole); legacy dual-read × repeater
cardinality (Pass 3); FW-59's base-tag free-form bracketing (rides this grammar, separate row).

### Pass 3 — Legacy-transition grill — ✅ WORKED 2026-07-31 (see §P3 findings below)

The mount-reconcile/dual-read bridge (§L1042) was derived against show_if reveal predicates that
no longer exist. Re-derive under repeater cardinality:

- Unmigrated tag holds LEGACY keys, no folded `{N}:` values — under "slot N≥3 renders when it
  HOLDS A VALUE", do legacy slots surface at mount? Presumably via reconcile's synthetic state;
  never spiked. Define the mechanism.
- Reveal-extension finding (L1059, transition predicate accepting legacy keys) — moot or
  transformed? Restate for the repeater.
- FW-51 ambiguous-shape flags, touch-migration delete-omit, converter run, write-on-confirm
  boundary VERIFY — re-confirm each against the repeater model.
- Empirical gate stands: flag-vs-best-guess assessed on live-site clones at build.

---

## P3 FINDINGS — legacy transition under the repeater (2026-07-31)

Went to P3 to illuminate the reopened absence question (user call). It does, decisively: the
LIFECYCLE distinction is P3's actual subject, and the spike code answers most of it. **The Pass-1
"one real hole" was overstated** — the spike DOES handle legacy cardinality; what it leaves open
is narrower and different from what Pass 1 predicted.

### The predicted hole is CLOSED in code (Pass 1's framing was wrong)

Pass 1 asked: "under 'slot N renders iff it HOLDS A VALUE', do legacy slots surface at mount?"
They do. `slotCount()` (`control.js:222-229`) counts a slot as present if it holds a folded value
**OR** if `foldFromLegacy()` recovers one:

```js
if ( state[ String( i ) ] ) { highest = i; }
else if ( foldFromLegacy( i, state ) ) { highest = i; }   // unmigrated wire still counts
```

and `readSlot()` (`:232-237`) is dual-era by construction. Render mirrors it exactly
(`proto-fold-tag.php:331-345`). So cardinality is derived from CONTENT in EITHER era, and an
unmigrated tag mounts with its true slot count. The comment even states the design reason: an
"armed but empty" slot could not survive remount, so content — legacy or folded — is the only
durable cardinality source. **Delete the Pass-1 hole claim; the ⚠ on §Legacy dual-read needs
rewriting, not the code.**

### What the transition actually is: ONE mapping, THREE consumers, and touch-migration

`bws_spike_fold_from_legacy()` (PHP, reference) + its JS port is the single legacy→fold mapping,
deliberately shared by the converter, editor mount-reconcile, and render dual-read so the
position-dependent absence rules exist exactly once. Touch-migration is per-slot and
commit-triggered: any commit on slot N deletes N's legacy sibling keys (`control.js:520`), and a
COMPACTION clears every legacy key across the block (`:290-293`) because it rewrites all slots
densely. So a tag migrates incrementally as it is edited; untouched tags stay legacy forever and
render via dual-read. That is the correct posture and needs no change.

### THE RESIDUAL RISK P3 exposes (new, not previously stated)

Touch-migration is **per-slot**, but the legacy wire's inherit semantics are **positional and
cross-slot**. Migrating slot N in isolation can therefore change what slot N+1 means:

> Legacy slot 3 with absent `src` inherits from slot 2's RESOLVED carry-forward. If the author
> edits slot 2 only, slot 2 migrates to a folded explicit value and its legacy keys are deleted —
> but slot 3 is still legacy and still resolves its absent `src` by carry-forward. Under
> dual-read, slot 3's recovery now reads a slot 2 that no longer has legacy keys to carry
> forward FROM.

`bws_spike_fold_from_legacy` maps each slot independently (`$n`, `$options`) with no visibility of
whether the PRIOR slot has already migrated, and its `same` synthesis for slot ≥2 is purely
positional. Whether a half-migrated tag preserves meaning is therefore **not established** — the
spike verified "MIXED wire (legacy slots 1-2 + already-folded slot 3) coexisting", i.e. legacy
BEFORE folded, which is the safe direction; the hazardous direction is a folded slot BEFORE a
legacy one that inherits across it.

> **⚠ RE-DERIVED against the shipped resolvers (2026-07-31) — the risk is REAL but RELOCATED, and
> milder than stated above.** Two corrections:
>
> **1. Inherit is ACCUMULATOR-based, not predecessor-based.** Both shipped resolvers carry forward
> through `$last_src`/`$last_ref`/`$last_key`/`$last_use` accumulators that update ONLY when the
> current slot supplies a value (`base-tags.php:1042-1047`, `registry:716-719`). A slot inherits
> from **the last slot that STATED a value**, not from slot N-1. The chain is therefore already
> resilient to a silent slot in the middle — which is exactly the shape a migrated-and-deleted
> legacy slot would take. So "slot 3 carries forward FROM slot 2's legacy keys" (the framing above)
> is wrong: it carries forward from an accumulator, and slot 2 only needs to CONTRIBUTE to that
> accumulator, by whichever era's wire.
>
> **2. The unexercised part is CONSUMER-side accumulator threading.** Because the mapping is
> per-slot and accumulator-blind, the caller must thread one accumulator across a mixed-era wire.
> The spike **does not do this and could not have caught it**: `proto_fold`'s render loop
> (`proto-fold-tag.php:329-347`) folds each slot in isolation and renders its chain as a DISPLAY
> string — it never resolves `same` against any predecessor, in either era. So the "MIXED wire
> verified live" evidence proves parse/display coexistence ONLY, not inherit correctness. Neither
> direction was actually tested.
>
> **Restated blocker:** the real migrator's three consumers must share ONE carry-forward
> accumulator that a folded slot and a legacy slot both feed. That is a build requirement to
> SPECIFY and TEST, not an unresolved design question — and it is narrower than "migrate the whole
> tag on first touch", which is now unnecessary: per-slot touch-migration is safe under
> accumulator semantics provided the accumulator is threaded. Forward-materialization also drops
> out as unnecessary for this hazard (it remains required for COMPACTION, where ordinals actually
> renumber). **Test to write first:** folded slot 2 between legacy slots 1 and 3, asserting slot 3
> inherits slot 2's folded source — the case no spike fixture covers.

### How this illuminates the ABSENCE question (the reason we came here)

P3 supplies the missing frame. Absence is **not one rule with exceptions** — and it is not ONE
matrix either. **Split by AXIS (user, 2026-07-31):** `src` and the read (`use`/`key`) have
different shapes, different container-sensitivity, and different open cells. A single table
conflated them and hid which axis each verdict belonged to.

Both matrices index by SLOT ordinal and share the LIFECYCLE columns (now concrete and code-backed).
Neither is about the CHAIN's internal steps — those are a separate axis, stated after.

**Matrix A — `src` axis (the source chain, as a whole, per slot)**

| | PARSE legacy-shaped input | PARSE folded value | EMIT (control) |
|---|---|---|---|
| slot 1, absent | `current` (`fold_from_legacy` → empty chain) | `current` (legal — no predecessor to inherit from) | never emitted for slot 1 |
| slot ≥2, absent | **inherit** — synthesize `same` (S1 synth; shipped meaning preserved) | **MALFORMED** — flagged, not resolved (`proto-fold-tag.php:355-361`) | never — seed always states `src(same)` or a real source |
| `same` present | inherit prior resolved source | inherit prior resolved source | emitted explicitly |

**Container-sensitivity: NONE.** `src(same)` is coherent in all three containers (user's
distinction 3) — a slot reading a different field off the same source is the common case in
combining and the whole point of carry-forward in selecting. The only container-specific `src`
rule is `{{table}}`'s ROOTING restriction (columns hop from the row entity; no entity-start),
which constrains which chains are legal, not what absence means. **No open cells.**

**Matrix B — read axis (`use` / `key`)**

| | PARSE legacy-shaped input | PARSE folded value | EMIT (control) |
|---|---|---|---|
| slot 1, absent | default analog (`read = null`) | default analog | `default` dropped on emit |
| slot ≥2, absent — SELECTING (`try_`) | **inherit** — synthesize `same` | inherit (same field, different source = the fallback chain) | seed emits `use(same)` |
| slot ≥2, absent — COMBINING (join/table) | **inherit** — synthesize `same` (legacy meaning, must be preserved on this path) | ✅ **UNCONFIGURED — skip the slot, NOT inherit** (user-approved 2026-07-31) | seed leaves read unset |
| slot ≥2, `key` set + `use` absent | **FLAG** (FW-51 ambiguous shape) — never guess | LEGAL — `key(x)` alone IS the plain keyed read (no `use` needed; the 07-28 no-`text`-token lock) | emitted as bare `key(x)` |
| **both `use` and `key` present** | n/a (legacy uses them jointly: `use:key` + `key:x`) | ✅ **CLOSED — PARSE tolerant / EMIT exclusive** (see §Read-token exclusivity; the §SETTLED index has carried this as closed, and this cell said OPEN until 2026-08-11 — supersession in place, in the table the index points at) | control never emits both |
| `use(same)` present, combining | n/a (not expressible on legacy wire) | **LEGAL** — decomposition via per-slot handler | never seeded; hand-written round-trips |

**Container-sensitivity: THIS IS WHERE IT LIVES.** Selecting inherits the field across differing
sources; combining requires a distinct field per slot to be properly configured
(`base-tags.php:895-897`: *"source can sensibly carry forward in combining; field identity
cannot"*).

**✅ DECIDED (user, 2026-07-31): combining, slot ≥2, read absent on a FOLDED value =
UNCONFIGURED — skip the slot. Not inherit.** Preserves shipped behaviour rather than changing it
(`base-tags.php:1035` already `continue`s when both `use` and `key` are empty). Matrix B has no
open cells; both matrices are now closed.

Three consequences to hold onto, all deliberate:

1. **Scope is the FOLDED parse path only.** The legacy-parse column is untouched — a legacy
   combining slot with an absent read still synthesizes `same`, because that is what shipped
   tags mean and lifecycle distinction (1) forbids changing them. So `fold_from_legacy`'s slot ≥2
   `same` synthesis stays as written; it is a MIGRATION mapping, not a statement about folded
   semantics. Do not "fix" it to match this decision.
2. **The two containers now diverge on the same wire shape.** An identical folded slot value with
   no read token means *inherit the field* in `try_` and *skip me* in join/table. The grammar is
   uniform; the RESOLVER is container-aware — which is exactly the verb-agnostic
   selecting-vs-combining distinction the open resolver refactor is about
   (`project_open_refactors.md`). That refactor's fold-verb split is now load-bearing on
   interpretation, not just internal structure. Worth citing when it is finally filed.
3. **Skip ≠ empty string.** In a combining fold, skipping a slot must drop it from assembly
   entirely — not contribute an empty value that a separator then wraps (`A, , C`). Shipped join
   gets this right via `continue` before the seam; the fold's resolver must too. Explicit because
   it is the obvious way to get this subtly wrong.

#### Read-token exclusivity — ✅ CLOSED 2026-08-11 (PARSE tolerant / EMIT exclusive); the matrix previously mis-stated it (user, 2026-07-31)

> **This heading read "OPEN" until 2026-08-18.** The §SETTLED index and the matrix cell at
> §Read-token exclusivity both closed it on 2026-08-11 — that cell's own note records it having
> said OPEN until then. The heading then repeated the same supersession-in-place one level up,
> which is what a heading can do and an index row cannot. The body below is the closed rule:
> both token names may APPEAR and both write `$slot['read']`; the control never EMITS both.

The row above originally read *"n/a (fold has no split key/use)"*. **That was wrong.** The fold
does not COMBINE `use`+`key` the way the legacy wire does (`use:key` selecting the mode, `key:x`
supplying the arg), but it absolutely permits both tokens to appear — they are two token NAMES
that both write the same destination:

```php
case 'use': $slot['read'] = ( 'same' === $val ) ? [ 'kind' => 'same' ] : [ 'kind' => 'analog', 'slug' => $val ]; break;
case 'key': $slot['read'] = [ 'kind' => 'key', 'field' => $val ];                                                break;
```
(`proto-fold-tag.php:171-178`)

So `2:src(same);use(title);key(phone)` parses to `key:phone`, and reversing the token order parses
to `analog:title` — **last token wins, silently, with no flag.** Three problems:

1. **Order-dependence contradicts the bracket-kv premise.** The whole point of named kv tokens
   (over positional CSV) was order-independence — FW-52's serialization-order work exists so token
   ORDER is never semantic. Here it is, on the one axis that decides what the slot reads.
2. **It is a REGRESSION against the legacy shape it was meant to dissolve.** Legacy `key` set with
   `use` absent is FLAGGED as the ambiguous FW-51 shape and never guessed. The folded parser is
   more permissive about a MORE ambiguous input.
3. **The round-trip harness would not catch it**, because the control never emits both — the
   defect is only reachable by hand-editing, which ADR 0004 explicitly invites.

**The read axis is single-valued by design** — Matrix B has exactly one read per slot, and `key(x)`
alone is already the plain keyed read (the 07-28 no-`text`-token lock: `{{text}}`=`{{field}}`, so
a bare `key(x)` needs no `use` companion). So both-present is not a meaningful state to support;
it is an input to REJECT.

~~**Proposed:** flag both-present as MALFORMED, never resolve by precedence.~~ **REJECTED — too
strict on the parse side.** It would flag tags the shipped wire legitimately produces. See the
decision below.

#### Bare-`key` collision under `scope:'row'` — it is a SCOPE-DISCOVERY seam, not a naming clash (2026-07-31)

Diagnosed against the shipped control. `field-combo-control.js:529`:

```js
var scopeRepeaterKey = ( 'row' === props.scope ) ? String( state.key || '' ).trim() : '';
```

with the assumption stated in its own comment — *"the TAG-LEVEL `key` option (always the bare
`key`, whatever this control's own prefix)"*. **Two different meanings share the name `key`, and
the fold puts them at two levels of one tag string:**

| level | token | means |
|---|---|---|
| tag | bare `key` | the REPEATER the rows come from — a *source* selector |
| slot | `key(x)` inside `{N}:` | which sub-field this column READS — a *read* selector |

Shipped, these never collide: the repeater is tag-level bare `key`, columns are `{N}-key`. Under
the fold a column's read is ALSO spelled `key(...)`, so a control reaching outward for "the bare
`key`" is reaching into a namespace where `key` now means something else one level in.

**The real defect is the reach, not the spelling.** The control DISCOVERS its own scope by reading
sibling tag state (`state.key`) rather than being TOLD it. Renaming either token would paper over
one instance and leave the mechanism — any future two-level tag re-breaks it. And the coupling is
load-bearing, not incidental: Constraint 3 (§L59) cites this very reactive `state.key` read as the
precedent justifying incremental step-commit, so the chain design depends on the behaviour while
the fold invalidates how it is obtained.

**Fix: pass the scope handle in explicitly** — an explicit `scopeKey` (or `repeaterKey`) prop
supplied by whatever registers the column control, which alone knows the tag's shape. Removes the
outward reach, makes the control's contract self-contained, and keeps the reactivity Constraint 3
wants (the prop changes when the tag-level source changes). This is the "explicit repeater name
prop, not state-smuggling" already named in §P4; the addition here is WHY a rename is insufficient.
**Verify at build:** the degradation path at `:537-541` (unmatched repeater key → fall through to
the full pool rather than stranding the author) must survive the prop switch — it is the behaviour
that makes a wrong/absent scope handle non-fatal.

#### ✅ DECIDED — asymmetric rule: PARSE tolerant with `use` precedence, EMIT exclusive (user, 2026-07-31)

The both-present state is not author error — **the shipped wire produces it deliberately**, as a
concession to the `show_if` system: GB cannot UNSET one option based on another option's value,
so a `key` left over from an earlier `use:key` selection stays on the wire after the author
switches `use` to something else. Precedence is the shipped resolution, and it is uniform across
the base callbacks:

```php
$use = $options['use'] ?? 'key';          // base text (:669), phone (:1604), email (:1617), image (:1346)
$use = $options['use'] ?? 'content';      // {{content}} (:1110) — different default, same dispatch shape
...
} elseif ( 'key' === $use ) { $opts['type'] = 'custom_field'; ... }   // key consulted ONLY in the use:key arm
```

`use` is dispatched on FIRST; `key` is read only inside the `use:key` arm. So `use:<not key>` +
`key:<field>` already means *`use` wins, `key` is inert* — everywhere, today. `{{content}}`
confirms the both-serialized case is real rather than theoretical: its `use` default is `content`
(not `key`), so `use:key|key:<field>` must BOTH be on the wire for a keyed content read to work —
neither token is strippable-as-default there.

**The rule, split by lifecycle (matching distinction (1)):**

| | Rule |
|---|---|
| **PARSE** (any era: legacy keys, folded values, hand-edited) | **`use` WINS when `use` is present and is not `key`.** `key` is then inert — not an error, not a flag. When `use` is absent or is `key`, the `key` token supplies the read. Deterministic, order-INDEPENDENT, and identical to shipped dispatch. |
| **EMIT** (control, going forward) | **Serialize `use` XOR `key`, never both** — `use:`/`use()` or `key:`/`key()`. Contingent on the controls being able to clear the other token; where a control cannot, both-present remains legal and the parse rule covers it. |

**✅ VIOLATION REPRODUCED 2026-07-31** (`php -r`, mirroring the `proto-fold-tag.php:171-178`
token switch). The spike does NOT implement the decided rule — it is order-dependent, so `use`
wins only when it happens to be written last:

| wire | parses to | decided rule says |
|---|---|---|
| `use(title);key(staff_role)` | `{"kind":"key","field":"staff_role"}` | ❌ should be `analog:title` |
| `key(staff_role);use(title)` | `{"kind":"analog","slug":"title"}` | ✅ correct, but only by luck of ordering |

Both wires are the SAME tag under an order-free grammar, and they resolve differently. Confirms
this is a live ambiguity rather than a theoretical one, and pins the defect precisely: the switch
assigns `$slot['read']` unconditionally in both arms, so whichever token is visited last overwrites.

**Fix shape (build):** do not assign the read inside the token loop. Collect `use` and `key` into
separate locals during the scan, then resolve ONCE after it, applying the precedence rule:
`use` present and ≠ `key` → analog/`same`; otherwise `key` supplies it. Order-independent by
construction, and it mirrors the shipped `$use = $options['use'] ?? 'key'` dispatch rather than
re-deriving it. Same change on both sides (PHP switch + the JS parser port). **Harness cases:**
both orderings above, asserting identical output — the round-trip harness cannot catch this
(the control never emits both), so these must be explicit hand-edit parse cases.

This is better than the flag proposal on every count: it preserves shipped meaning exactly (no tag
that renders today starts flagging), it kills the order-dependence defect (precedence is by TOKEN
NAME, not position — so the bracket-kv order-independence premise holds), and it still moves the
wire toward one-read-token-per-slot through the emit side. Refusing to guess was the right posture
for genuinely AMBIGUOUS shapes (FW-51's key-without-use, where the author's intent is unrecoverable);
this shape is not ambiguous — the shipped resolver has always had an answer for it.

**Fix required in the spike parser** (`proto-fold-tag.php:171-178`): it currently lets the LAST
token win, which for `key(x);use(title)` yields `analog:title` but for `use(title);key(x)` yields
`key:x` — the second contradicts this rule. Replace last-wins with name-precedence: `key` writes
`$slot['read']` only if no non-`key` `use` was seen. Add both orderings to the round-trip harness
as named cases (they are hand-edit-only, so nothing else would surface them).

**`use(key)` in the fold:** the fold has no `use:key` mode token — a bare `key(x)` IS the plain
keyed read (07-28 no-`text`-token lock). So on FOLDED values the rule reduces to: `use(<analog>)`
present ⇒ it wins and any `key()` is inert; otherwise `key()` supplies the read. A literal
`use(key)` on a folded value is a legacy-shaped token and maps to the keyed read.

**AMENDED 2026-08-04 — "never emit" was wrong, and the editor is what proved it.** `use(key)`
with no `key()` is not only a legacy shape: it is the ONLY spelling of *keyed read, field not
chosen yet*, which is the state the author is in between picking "Meta/Option Field" and picking
the field. The control rewrites the whole slot value on every commit and derives the read
select's value by RE-PARSING it (DECISION 2), so a shape that parses but never emits round-trips
to nothing — the kind reverted to unset on commit and took the field picker it reveals with it.
Selecting an ANALOG row was unaffected, so the bug read as "Meta/Option Field doesn't stick".
Emit rule now: field present → bare `key(x)` (canonical, unchanged); field empty → `use(key)`.
Render is unaffected — the flat seam already spelled it `use=key, key=''`, exactly as the flat
era did.

Open sub-question, unchanged: does duplicate-token-name rejection generalize (`src(...)` twice,
`label(...)` twice)? Note this decision makes the read axis NOT an instance of that rule — `use`
and `key` are different names with a precedence relation, not a duplicate. True duplicates of the
SAME name are still unresolved and want a deliberate grammar-wide call (affects `extra[]`
passthrough).

**Not in either matrix — the CHAIN-INTERNAL axis.** A slot's `src(...)` holds an ordered STEP
sequence, and its malformed states are per-STEP, indexed by step position, not by slot ordinal:
an incomplete hop (`refs` with no reference field — flagged, `proto-fold-tag.php:365-369`) and a
step whose slug is unknown. These are container-independent (modulo table rooting) and already
handled. Listing them here only so they stop being smuggled into a slot-ordinal table: the
Matrix-A "slot ≥2 absent = MALFORMED" row is about the chain being ABSENT ENTIRELY, which is a
slot-level fact; a chain that EXISTS but is internally broken is this third axis.

Two things fall out, both confirming the user's distinctions:

1. **The lifecycle split is already IMPLEMENTED, not merely advisable.** Absence means inherit on
   the legacy parse path and MALFORMED on the folded parse path — simultaneously, in the same
   function, today. The spike comment states the reasoning explicitly (`proto-fold-tag.php:352-357`):
   unset-means-current *"WAS THE BUG"* because an untouched slot ≥2 silently RESET rather than
   inheriting. So "absence never means inherit" was always an EMIT+folded-parse rule; my Pass-2
   write-up erred only by stating it universally. **The two-rule split is ratified by the code.**
2. **The remaining open cell is exactly the one the user identified** — read-absent on slot ≥2 of
   a FOLDED value — and it is open precisely BECAUSE it is container-dependent. In a selecting
   container, absent read = inherit-the-field is meaningful (`use(same)` is a real configuration).
   In a combining container, `use(same)` is degenerate, so absent read cannot mean inherit; it
   means UNCONFIGURED, and the slot should not render a value at all. Note the shipped combining
   resolver already does this — `base-tags.php:1035` skips the slot when both `use` and `key` are
   empty. **So the combining containers already treat read-absence as "skip", not "inherit" —
   which is the one absence rule that transfers to the fold unchanged.**

### Residual P3 items (carry to build)

- Partial-migration cross-slot inherit (above) — **RE-DERIVED 2026-07-31, no longer a
  design decision.** Inherit is accumulator-based (last slot that stated a value), so per-slot
  touch-migration is SAFE and neither whole-tag-on-touch nor forward-materialization is needed
  here. What remains is a build REQUIREMENT: all three consumers thread ONE carry-forward
  accumulator that folded and legacy slots both feed. Untested in the spike (its render loop has
  no accumulator) — write the folded-slot-2-between-legacy-1-and-3 case first.
- FW-51 flag path: verified present in all three consumers; re-confirm on the real
  `MigrationRegistry` shape.
- Write-on-confirm boundary + converter run: unchanged by the repeater (cardinality is content-
  derived in both eras), but re-verify once the seed shape is container-aware.
- The `⚠` note on §Legacy dual-read (added in Pass 1) overstates the hole — rewrite to point here.

### Pass 4 — Table-composition grill — ✅ WORKED 2026-07-31 (see §P4 findings below)

- **Bare-`key` name collision** (L1154): row-scoped repeater name vs slot field, both bare `key`
  under the fold — explicit repeater-name prop, not state-smuggling. Design the prop seam.
- Rooted column chains (no entity-start, hop-from-row-only) under the repeater control — enum
  restriction per container.
- **Step-dependent `src` enums** (L1169): narrow next-step choices by previous step's output
  kind — how strict, and how it degrades while ref-hop parity (FW-32) is unbuilt.
- Align `table-tag.md` plan with the fold wire (its column options predate the fold).

---

## P4 FINDINGS — `{{table}}` under the fold (2026-07-31)

Grilled the fold against `.claude/plans/table-tag.md`'s locked decisions. The table plan predates
the fold, and **one of its locked decisions is directly contradicted by it** — that is the main
finding. The bare-`key` collision turns out to be a symptom of the same thing, not an independent
problem.

### FINDING 1 — table decision #8 (`ALL columns 1-prefixed`) is OBSOLETED by the fold

Table locked-decision #8 reserves bare option keys EXCLUSIVELY for the tag-level repeater source,
prefixing every column from `1-`. Stated reason: *"the tag has a src BEFORE columns exist, so
column-1 can't inherit bare like join slot-1 does."* That reasoning is entirely about the LEGACY
wire, where a column's config is spread across sibling bare/prefixed option KEYS (`{N}-use`,
`{N}-key`, `{N}-label`) that share a namespace with the tag's own options.

Under the fold there is no shared namespace to collide in. A column is ONE option whose key is the
ordinal (`1:`, `2:`) and whose value is self-contained (`label(Name);title;src(same);use(...)`).
The tag-level repeater source is a different option entirely (`key:` per table #11). The collision
#8 was designed to prevent **cannot occur** — so its remedy (uniform `1-` prefixing, and the
deferred option (ii) "retrofit join/try_ to uniform prefixing") is solving a problem the fold
deletes.

Consequences:
- **#8's prefix scheme should be re-derived, not carried forward**, when table adopts the fold.
  The fold's own scheme (slot 1 included, `{N}:` for all N) is already uniform — which is what (ii)
  wanted — and gets there without touching join/try_ at all.
- **Retrofit option (ii) is moot under the fold.** Delete it rather than leaving it deferred; the
  fold's migration IS the uniformity change.
- `BWS_TABLE_MAX_COLS` (=6) survives as table's per-container ceiling — consistent with the
  approved per-container ceiling decision (try_ 5 / join 10 / table 6), and no longer an outlier
  needing justification.

### FINDING 2 — the bare-`key` collision is a CONSEQUENCE of #8, and the fold both causes and cures it

Recorded in the spike (`proto-fold-control.js:427-437`) and confirmed in shipped code. The
shipped `bws-field-combo` reads the bare `key` two ways:

```js
var scopeRepeaterKey = ( 'row' === props.scope ) ? String( state.key || '' ).trim() : '';
```
(`field-combo-control.js:529`) — under `scope:'row'` the bare `key` is the TAG-LEVEL repeater name
(the scope handle); otherwise it is the control's own value. **Legacy keeps these apart by prefix**
— a column's own field is `{N}-key`, never bare. Under the fold, every slot's field arrives as bare
`key` in the synthetic field-combo context, so both readings land on one name.

The spike sidesteps it only because it never passes `scope:'row'`. A real table build does.

**Resolution (proposed): pass the repeater key as an EXPLICIT PROP, not through state.** The
control should take `scopeKey` (or similar) as a prop supplied by the column control, which knows
the tag-level repeater name from the tag's own option. State-smuggling was always the fragile part
— it works on the legacy wire purely because the prefix convention accidentally keeps the two
names distinct. Note this is a small change to a SHIPPED control (`field-combo-control.js`), so it
carries a compat obligation: the existing `scope:'row'` state read must keep working for
non-folded table columns until they migrate (same dual-era posture as everything else in P3).

### FINDING 3 — table is GREENFIELD, so it should adopt the fold's rules directly

Table is unshipped (P2 lifecycle distinction (1)), so it has **no saved-tag constraint at all**:
- No legacy-parse column in its matrices — only the folded-parse and emit columns apply.
- The combining-container read rule (Matrix B, just decided) applies to columns unchanged: a
  column with an absent read is UNCONFIGURED. **But "unconfigured read" and "no column" are
  different facts in a table** — see §Column existence vs cell value below.
- `use(same)` in a table column is legal-but-never-seeded (the join/table combining rule), and the
  date/time-split case applies to columns as naturally as to join slots.

### Column existence vs cell value — TWO predicates over DIFFERENT tokens (user + grill, 2026-07-31)

Finding 3 originally said "skip-the-column in table means an EMPTY CELL, because the grid is
positional." **That conflated two questions.** User correction: *"if BOTH the slot heading and the
field are empty, skipping can mean omit from assembly; if either is populated, don't skip."*
Separating them:

| Predicate | Reads | Evaluated | Meaning |
|---|---|---|---|
| **Column EXISTS** | `label` token **OR** read token | ONCE per table | column appears in the grid at all |
| **Cell HAS VALUE** | read token, resolved against THIS row | per ROW | `<td>` content vs `<td></td>` |

The key point the original text missed: `label` is **not on the read axis**. Matrix B governs
`use`/`key`; `label(...)` is an independent sibling token in the slot value (table #5, fixed-string
header mode). So a column's existence is decided by a token Matrix B says nothing about. The two
rules therefore never conflict:

- `1:label(Name)` with no read → read UNCONFIGURED (Matrix B) but label present → **column exists**,
  every cell empty. A heading-only column is a legitimate authoring act (spacer / placeholder the
  author will fill), and it is a shape join has no analogue for — join slots have no second axis to
  be populated.
- A configured column whose field resolves empty on a given row → column exists, **that cell** is
  empty. Per-row, and unrelated to configuration.

**So Matrix B's "skip" NEVER means "omit the column" in a table** — it means "this column's cells
carry no value". Omission is governed by the label-OR-read predicate, which sits ABOVE the read
axis entirely. (In join, with no second axis, skip does mean omit from assembly — the containers
diverge here for a structural reason, not an arbitrary one.)

**✅ DECIDED (user, 2026-07-31): omit TRAILING empty columns only.** An all-empty column
(no label, no read) is dropped only when nothing configured follows it. A wholly-empty column
BETWEEN configured ones is retained as an empty column rather than collapsed.

Rationale: trailing empties are the "never configured" case — unused capacity at the end of a
fixed column set, which the author never touched and does not expect to see. An interior empty is
either deliberate (spacing) or a mistake the author should SEE; silently collapsing it would shift
columns 3-4 leftward and break the alignment of everything the author DID configure. Trailing-only
gets the cleanup without the positional surprise.

Notes for the build:
- This is a presentation rule, so it lives tag-side in `bws_table_assemble` (table #4: the fold
  stays dumb, all presentation is L3). It is NOT a wire or parser rule — the wire faithfully holds
  an empty trailing column; assembly declines to render it.
- Header row and body rows must apply the SAME existence predicate, or the header desynchronizes
  from the columns.
- Interaction with the repeater control: trailing-empty omission is a RENDER behaviour, not a
  cardinality one. The editor should still show the empty trailing column (it is how the author
  adds the next one) — exactly the "renders iff it holds a value" / "shows because the control
  offers it" split the repeater already makes.

### FINDING 4 — rooted column chains: the constraint is already table's, not the grammar's

Table #6/#7 restrict a column's mini-traversal to `bare | ref` rooted at the row entity (no
entity-start). Under the fold this is a CONTAINER-SCOPED ENUM RESTRICTION on the chain's first
step, not a grammar change — exactly the shape already established for the container-conditional
TYPE token and the per-container ceiling. The grammar permits `src(post,9999;…)` in a
column; the table's control simply never offers an entity-start root, and a hand-written one is
author error that degrades to empty (consistent with table #2's stated posture on the
repeater-not-on-context trap).

Left OPEN deliberately (needs the FW-32/FW-56 authoring surface, not decidable here):
**step-dependent `src` enums** — narrowing the NEXT step's choices by the previous step's output
kind. Fine as a v1 restriction (`bare | ref` is already a hardcoded narrow enum); the general
mechanism waits on ref-hop parity.

### P4 residual items

- Re-derive table #8's prefix scheme under the fold; delete the deferred retrofit (ii). **Requires
  editing `table-tag.md`** — held until the wire spec is approved.
- `field-combo-control.js` explicit-`scopeKey` prop (shipped-control change + dual-era compat).
- State the empty-cell-vs-omit distinction wherever the skip rule lands (it is a table-side L3
  obligation, so `bws_table_assemble`'s PHPDoc is the likely home on ship).
- Table's own ceiling (6) is provisional per its option-name pass; unaffected by the fold.

### Pass 5 — Control/UX residue (small decisions, can ride any session) — ✅ DONE 2026-07-31

Ran against the SHIPPED label/control surface rather than deciding in the abstract, which
collapsed two of the five items into one and turned a third from "open" into a defect report.
Original item list retained per-item below with its resolution.

**The pass's own finding: four of five items were answerable from shipped code, and the fifth
was a decision the spike had already made without recording it.** None needed a grill. Read the
shipped registration FIRST next time an item is phrased as "may be partly answerable from
existing nomenclature" — that phrasing was correct and the check is cheap.

**5.1 — Tag-dependent nouns. ✅ DECIDED: ONE registration parameter, not two.** The item asked
only about the repeater's "Add slot" button, but the shipped read-select label is ALREADY
tag-dependent by the same axis: `Text Field` (`base-tags.php:95,364`) / `Content Field` (`:142,404`)
/ `Image Field` (`:253,503`). So the tag noun is a property the registration must carry anyway,
and the button label is a second CONSUMER of it, not a second parameter. Shape:

| Container | Slot noun | Add button | Slot heading | Read-select label (shipped, unchanged) |
|---|---|---|---|---|
| `try_text` / `try_content` / `try_image` | **attempt** ✅ (user, 2026-08-01) | "Add attempt" | "Attempt N" | `Text` / `Content` / `Image Field` |
| `{{join}}` | **field** ✅ (user, 2026-07-31) | "Add field" | "Field N" | ordinary label; ENUM widens |
| `{{table}}` | ⏸ **DEFERRED to `table-tag.md` #8** — see 5.1b | — | — | ordinary label; ENUM widens |

Note the noun is f(CONTAINER) while the read label is f(TAG) — they coincide on `try_<tag>` only
because that container fixes its tag. **The parameter to register is the SLOT NOUN**, and the read
label stays derived from the tag as it already is. **This is the anti-drift obligation again**
(§L843): do not hand-author a slot read label per container.

**RULE — button and heading are ONE registered noun, not two strings (user, 2026-08-01).** *"I want
headers and buttons aligned, not necessarily verbatim, e.g. Add attempt → Attempt N (slot heading).
This should apply across multislot tags."* So the registration parameter is the **noun**, and both
surfaces derive from it:

| Surface | Form | try_ | join |
|---|---|---|---|
| repeater button | `+ Add <noun>` | Add attempt | Add field |
| slot heading | `<Noun> N` | Attempt 2 | Field 2 |

Aligned, not verbatim — the button carries the verb, the heading carries the ordinal. **Applies to
every multislot container**, present and future; a new container registers one noun and gets both.
Registering two independent strings is the thing this rule forbids, because that is precisely how
they drift apart.

⚠ **This replaces the spike's generic `Slot N` heading** (`proto-fold-control.js:573`
`props.label || 'Slot ' + key`) — "Slot" is internal vocabulary, and the generic fallback is what
lets a container ship with an unregistered noun. **At build the fallback should be the noun, not a
generic word**; a container with no noun registered is a registration bug, not a case to paper over.

**Interaction with the SHIPPED ordinal idiom — the fold is what makes this possible.** Shipped
multislot labels are `sprintf( '%d: %s' )` — `2: Source`, `2: Text Field`, `2: Meta/Option Field Key`
(`base-shared.php:322`, `base-tags.php:910,926,942`). That prefix numbers the **option**, not the
slot, because a slot IS three or four sibling options and each needs its own ordinal to stay
associated. Under the fold there is ONE control per slot, so the ordinal has exactly one place to
live — the heading — and the per-option prefixes disappear with the options that carried them.
`Attempt 2` is therefore not a restyling of `2: Source`; it is what the ordinal collapses to once
the slot is a single control. Legacy-era slots keep the old prefixes until migrated.

> **⚠ CORRECTED 2026-07-31 (user review of this pass).** The first draft of the table above put
> *"per-slot type token (container-conditional TYPE)"* in the READ-LABEL column for the agnostic
> containers. **That was a category error** — the type token is a WIRE construct (a standalone
> slot-level token, `[type];src(...);use(x)`), never a UI label, and after the mechanism correction
> it is not attached to the read at all. On join/table the read select still needs an ordinary
> label; what the agnostic container changes is the read's **ENUM** (any processing tag, not just
> the container's fixed one), and that enum is owned by the anti-drift derivation, not by this
> table. Root cause: this pass read §Container-conditional TYPE while that section still carried
> the superseded read-step-prefix mechanism, and propagated its framing forward — see that
> section's ⚠ and the audit note in 5.6.

**5.1a — `try_` slot noun: ✅ DECIDED "attempt" (user, 2026-08-01).** Button "Add attempt", heading
"Attempt N", per the button/heading rule above. The draft's "Add fallback" is
**rejected — it collides with a shipped concept.** `fallback` is one of the four canonical option
GROUPS (`source → format → link → fallback`, tag-reference:291), present on nearly every tag, with
its own doc section (tag-reference:493-500), serialization rank (`serialization-order.php:60`) and
per-tag semantics (`{{email}}`'s fallback is a validated address, `{{phone}}`'s a normalized number).
So an "Add fallback" button would sit on a `try_` panel one group away from a literal **Fallback**
control meaning something else. Worse, tag-reference:354 already calls `try_` tags *"entity-agnostic
**fallback chains**"* — the word names the WHOLE TAG, not one rung of it. Candidates: **"Add
attempt"** (nouns one rung, sequence-implying, no collision — current lean), "Add source to try"
(verbose, self-describing, but repeats `source`, itself a group name), "Add another source" (natural
but same soft collision). User steer was "add try, or something more verbose"; `fallback` "has a
different meaning in the Dynamic Tag context". **"attempt" chosen** — it nouns one rung of the
sequence, implies the try-until-one-resolves semantics, and collides with nothing shipped
(`grep` confirms no `attempt` in `includes/`). Note it does NOT contradict tag-reference:354 calling
`try_` tags "fallback chains": that names the CHAIN, and "attempt" names a link in it, which is the
distinction that made "Add fallback" wrong in the first place.

**5.1b — `{{table}}` slot noun: DEFERRED, not decided here.** The draft's "Add column" is
**orientation-dependent** (user) and this pass had no standing to fix the orientation. Today's
prototype is column-oriented throughout (`BWS_TABLE_MAX_COLS`, per-column `{N}-label`/`{N}-align`,
per-column `<th scope="row">` flag), so "column" is not WRONG — but `table-tag.md` #8 explicitly
owns the column option-NAME pass and has not run, so naming the author-facing noun here would
pre-empt it. Route the decision there; it should be made with the option names, not ahead of them.

**5.2 — Advisory `inferIntent` keeper. ✅ DECIDED: keep; home is the CONTROL'S OWN JSDoc HEADER, and
it is not blocked on any doc.** The item assumed `docs/editor-controls.md` (then reserved and uncreated; it exists as of 2026-08-19) had
to exist first. It does not — but the first draft of this pass got the destination wrong too, and
the correction matters because it generalizes.

> **⚠ CORRECTED 2026-08-01.** The draft routed this to *"PHPDoc on the control class"*, citing
> CLAUDE.md:99. **There is no PHP class to put it on.** `inferIntent` is JS
> (`proto-fold-control.js:153`); in the `bws-*` control pattern PHP registers only the option
> `type` string while JS implements the control, so a JS-side behavioural invariant has no PHPDoc
> home. The draft also treated 5.2 and 5.3 asymmetrically — advisory to PHPDoc, wire echo to a
> doc — without justifying why two JS artifacts in the same control route differently.

**Shipped practice answers it: JS control invariants live in the JS file's JSDoc header.** Not a
new convention — `term-hop-control.js:1-19` documents its two-affordances-one-key persistence
semantics and the deliberate "incomplete config = disabled" non-persist rule; `as-size-control.js:1-25`
documents the whole `as`+`size` fold frame, the always-serialized/no-strip decision, and the GB
constraint that forced it. Both are exactly this class of fact — load-bearing, single-artifact,
would silently regress if lost — and neither waited on `editor-controls.md`.

So the advisory's one load-bearing property — **it DESCRIBES and never GATES** (the cut radio's
residue) — goes in the fold control's own header block, beside the code that must not regrow a gate.
That also resolves the 5.2/5.3 asymmetry honestly: **both are JS, and they differ by AUDIENCE, not
by layer.** The advisory is an internal behavioural constraint on the control (a maintainer must not
re-gate on it), so it belongs with the code; the wire echo is an author-facing affordance whose
existence and shape other surfaces must agree with, so it belongs in a doc (5.3).

The four strings themselves (`Varies: source…` / `…field…` / `…source and field` / the all-inherit
seed warning, `proto-fold-control.js:611-616`) are ordinary UI copy, not schema — no owner needed.

**5.3 — Per-slot wire echo. ✅ DECIDED: CUT (user, 2026-08-01).** *"I flagged the cue line stating
what's different from the previous attempt as a keeper. The wire echo per slot should be dropped."*
The echo is spike debug scaffolding and goes with the spike. **The keeper was always the ADVISORY
CUE LINE** (`inferIntent`, item 5.2) — the two sit adjacent in the panel (`proto-fold-control.js:
609-620` advisory, `:906-907` echo) and got conflated.

> **⚠ THIS REVERSES PASS 1's RECONCILIATION — the earlier note was right.** Two notes conflicted:
> L1104 *"wire echo is spike-only debug scaffolding, NOT a keeper"* vs L1166 promoting it to *"a
> real affordance"*. Pass 1 resolved it "later wins" and carried the promotion into Pass 5, where
> this pass then spent its effort on the echo's SHAPE and DOC OWNER — both moot. **"Later wins" is
> a reasonable default for reconciling notes, but it is only a heuristic, and here it lost the
> correct reading.** When two notes conflict about whether something is scaffolding, the question
> is not which is newer but which one the USER flagged; a promotion recorded without an attributed
> decision is a note, not a decision. Recorded as a Pass-6 caution: check attribution before
> applying recency.

**The routing this pass proposed was also wrong on its own terms, which is worth keeping** because
it constrains any FUTURE echo-like surface. `docs/editor-tag-previews.md` is scoped in its own
opening lines to *"the placeholder string GenerateBlocks shows in the editor when a tag can't yet
resolve"*, gated by `$instance->context['bwsEditorPreview']` and built PHP-side by
`bws_build_preview_label()`. A wire echo is none of that: it fires while the tag resolves FINE, it
lives inside a control rather than in place of the tag, and its content is code-style where that
doc's entire apparatus (`[ ]`, `“X”`, ` from `, `→`) is prose markers. The first draft sensed the
mismatch and patched it with "same doc, second section, distinct generator" instead of concluding
the doc was the wrong home. **A shared owner that needs a disclaimer about not sharing the builder
is a signal the ownership is wrong** — the CLAUDE.md rule is single-source-of-truth per CONTENT
TYPE, and these are two content types.

**Build consequence:** drop the echo when the spike graduates (Pass 6 already removes the spike
wholesale). Do not port it. If an author-facing wire readout is ever wanted, it needs its own
owner decision, not this doc.

**5.4 — Standard source/field labels. ✅ DONE — resolved to a DEFECT LIST, not a decision.**
Every label the spike needed already has a shipped spelling, so this was drift, not an open
question. Drafted as three divergences in `proto-fold-control.js`; **one was withdrawn on review
(dead code), so TWO stand**, both fix-at-build:

| Spike | Shipped | Verdict |
|---|---|---|
| ~~`'Meta/Option Field'` on the field-combo (`:878`)~~ | ~~`'Meta/Option Field Key'`~~ | **WITHDRAWN — NOT a defect (2026-08-01).** See below. |
| `'Read'` on the read select (`:845`) | `'Text Field'` / `'Content Field'` / `'Image Field'` | **WRONG** — and it is 5.1's parameter, not a string. `Read` is internal vocabulary leaking to the author. |
| `'Same as previous slot'` — BOTH axes (`:667` src, `:834` read) | `'Same as Previous Source'` (`base-shared.php:316`) / `'Same as Previous Field'` (`class-tag-template-registry.php:521`) | **WRONG** — one generic string where shipped has an established PAIR, one per axis. Straight two-string fix. |

> **⚠ DEFECT 1 WITHDRAWN 2026-08-01 — the string is DEAD CODE, not a wrong label.** The spike passes
> `dynamicLabel: true` alongside it, and `field-combo-control.js:671-682` **never reads `props.label`
> when `dynamicLabel` is set** — the label is computed from the active Location instead
> ("<Group> Field" when narrowed to an ACF group, else the Location's kind, else the sibling-source
> preset). `props.label` is consulted only in the `else` branch. So the spike's `'Meta/Option Field'`
> never renders, and the `:888` fallback — a plain `TextControl` with no dynamic labeling — correctly
> hardcodes `'Meta/Option Field Key'`. **The two branches do not disagree; each is right for its own
> path.** Shipped does the identical thing (`base-tags.php:104` sets both `label` and
> `dynamicLabel: true`), so its literal is equally inert — a PHP-side fallback for when the JS control
> does not mount.
>
> **The error was comparing two string literals without checking whether either reaches the UI.**
> That is the specific failure mode to avoid in a labels audit: a label's spelling is only a defect
> if the label is RENDERED. Worth re-checking the surviving two on the same basis — defect 2's
> `'Read'` is a plain `SelectControl` label (rendered, real) and defect 3's rows are `SelectControl`
> option labels (rendered, real), so both stand.

> **⚠ DEFECT 3 RESTATED 2026-08-01 (user: "there should be existing labels for same as previous
> field; it's not a new option").** Correct — **`'Same as Previous Field'` SHIPS**
> (`class-tag-template-registry.php:521`; documented `tag-reference.md:364,366,453`). The draft
> claimed the read axis "needs its own twin", i.e. a NEW string to be invented. It does not: shipped
> has an established PAIR, `Same as Previous Source` for the source axis and `Same as Previous Field`
> for the read axis, and the spike collapses both into one generic `Same as previous slot`. **Same
> root error as defect 1** — asserting the shipped surface instead of grepping it.
>
> This also **retires a caveat the draft raised**: whether the twin should track 5.1's tag-dependent
> read label ("Same as Previous *Text* Field"?). It should not — the shipped axis word is generic
> (`Source`/`Field`) and independent of the select's own label, so 5.4 and 5.1 do NOT need settling
> together. Straight two-string fix.
>
> **Carry-forward for the combining containers:** shipped join deliberately OMITS the "Same as
> Previous Field" row (`base-tags.php:843`, `tag-reference.md:768` — "in combining, same-`use` is
> redundant or pointless"). That is consistent with §L1783's later finding: `use(same)` is LEGAL in
> a combining container but never the default/seed, and only becomes genuinely useful once per-slot
> handlers exist (same field, different handler = a decomposition). So the read-axis label applies to
> `try_` now and to join only when handlers land — do not add the row to a combining container ahead
> of them.

Matching shipped already: `'Source'`, `'Relationship Field Key'`, `'Meta/Option Field Key'`,
`'Title/Name'`. **`'Default (intrinsic analog)'` (`:314`) is NOT a labels call** — "intrinsic
analog" is internal vocabulary, and per anti-drift the read enum must derive from the base tag's
read definition rather than be hand-authored here, so its spelling is decided by that derivation
(§L843, the `bws_build_slot_read_options()` twin). Left for the build.

**5.6 — Supersession-dependent audit (added mid-pass 2026-07-31, user-directed).** Triggered by the
5.1 category error: this pass trusted §Container-conditional TYPE, which was locked 07-28 and still
described the type as a prefix on the read step — a mechanism the terminal-step supersession had
killed and Pass 1 had recorded 700 lines away at §L477. **Pass 1 deliberately chose flag-don't-rewrite
and that was right; the gap is that a `⚠` on section A does not mark section B, which depends on A
and reads as fully live.** Audited every dependent of the read-token supersessions. Result — 10 sites,
three classes:

| Class | Sites | Action |
|---|---|---|
| **A — stale MECHANISM** | §Container-conditional TYPE (§L595 table + prose), §Migration type-injection (§L695), §FW-20 decoupling premise (§L757) | **rewritten** |
| **B — decision correct, NAME stale** | §L526, §L635, §L735, §L1753, §L1783, §L2605 | **renamed** (see below) |
| **C — already correct** | §Read-token ratification (reasons FROM the supersession) — **re-verified 2026-08-01, genuinely clean** | none |
| **C-misclassified** | §Fold-boundary axis table — **WAS NOT clean; corrected 2026-08-01** | rewritten |

**The audit's own finding — staleness tracks AGE-SINCE-LAST-TOUCH, not topic.** Every section
written or revisited on 07-31 had absorbed the change; every section locked 07-28 and untouched had
not. The proof is §L1081, which records the spike's render-side evidence as *"the bare STANDALONE
type token (`2:title;key(x)`)"* — the correct `;`-separated mechanism was PROVEN AND WRITTEN DOWN in
this same doc on 07-31 while §Container-conditional TYPE went on claiming the `,`-prefix form.
**Use recency as the staleness heuristic in Pass 6**, and chase supersessions to their dependents
rather than trusting a section because it carries no marker.

**Second finding — GREP BY WIRE SHAPE, NOT BY NAME.** The class A/B/C table above was built by
searching "Option R", and it was INCOMPLETE: a re-sweep for the dead SPELLINGS found four more live
sites in sections that never name the concept, they just use its syntax. §`text` is a NON-TYPE
(§L576 table + §L590) carried the `,`-prefix form; §Migration carried the `/`-joined chain-terminal
form in two places, one of them the **position rule for absent reads** — the mapping that decides
whether a migrated tag keeps its meaning. Three greps catch what one name-grep missed:

| Dead spelling | Superseded by | Canonical now |
|---|---|---|
| `<type>,<read>` (`email,key(x)`) | terminal-step death | `<type>;<read>` (`email;key(x)`) |
| `/`-joined terminal (`2:same/same`, `/default`) | bracket-kv structure lock | `2:src(same);use(same)` |
| bare analog slug (`title`) | `use(...)` carrier ratification | `use(title)`, `default` drops |

Worth re-running these three before the build; a wire-shape grep is cheap and it is the only search
that finds a stale example inside a section about something else.

> **⚠ THIRD FINDING (2026-08-01) — the shape-grep was ITSELF run as a name-grep, and the class-C
> bucket was wrong.** Re-auditing the two sites cleared as "already correct":
>
> - §Read-token ratification — **genuinely clean**, it reasons explicitly from the supersession.
> - §Fold-boundary axis table — **NOT clean.** Its read cell listed the enum as
>   `` `default`/`title`/…/`key(field)`/`same` `` — bare analog slugs, the pre-carrier spelling, in
>   the very table that defines what rides in a slot value. Cleared on the judgment that "terminal
>   of path" was semantic rather than structural; that judgment was about the LEVEL column and
>   never looked at the enum beside it.
>
> **Why the sweep missed it:** the third dead spelling was searched as `grep 'bare analog|analog
> slug'` — the PHRASE. A section that simply WRITES bare slugs never says "bare analog". Same error
> the finding above warns about, committed while writing that finding. **A shape-grep must match the
> SHAPE** (here: analog slugs appearing without a `use(` wrapper), not a description of it.
>
> **And a fourth spelling was never on the list at all: hop-sep `+`.** The three dead spellings were
> derived from the read-token supersessions only, so the SEPARATOR supersession contributed none —
> which is how `+` survived in the FRONTRUNNER block, the doc's most-copied artifact, through Pass 1
> and the Pass-5 sweep. **Dead-spelling lists must be derived from EVERY supersession, not the one
> currently in view.** Full list now: `<type>,<read>` · `/`-joined terminal · bare analog slug ·
> **hop-sep `+`** · **positional `limit`**.

**Renamed "Option R" → "container-conditional type token"** (user, option 1). The letter came from a
rejected-alternatives lettering (vs Option U) for a read-step variant that no longer exists, so it
implied a shape the design does not have; the doc already carries one letter-named ghost. The
section head keeps a `(formerly "Option R")` parenthetical so old cross-references resolve. **The
DECISION is untouched by all of this** — type present ⟺ format-agnostic container, `try_<tag>` omits
it — and is confirmed live in both spike parsers.

**5.5 — FW-45 re-scope. ✅ DONE.** Residual after the repeater: (a) whether the ceiling stays finite
or registration itself goes dynamic; (b) applying the repeater to UNFOLDED join slots if the fold
does not land first; **(c) REORDER** — assigned to FW-45 by this plan (L831/L843/L2132) and NOT
discharged by the repeater, which builds add + remove only. The `BWS_JOIN_MAX_SLOTS` constant is a
pure cardinality cap (`join-helpers.php:61-68`, loops `:146`/`:195`), so uncapped-N and slot-ORDER
are independent axes that FW-45 happens to own BOTH of — which is why the row's cap-focused
phrasing does not narrow it to (a).

> **⚠ MY 5.5 VERDICT WAS WRONG — corrected 2026-08-01 (user: "5.5 was a follow-up on the reordering
> concept?").** Yes, it was, and the draft's "reorder is NOT in scope — it was never in the FW-45
> row" is **wrong**. Reorder has a prior home in THIS PLAN, assigned to FW-45, in three places that
> all predate Pass 5:
>
> - **L831-833** — *"Append-a-step `bws-chain` (**FW-45's target**, new control): DEFERRED.
>   Unbounded + **drag-reorder**… mid-list delete/**reorder** BREAKS positional `same`"*, with the
>   renumber-AND-rewrite burden named as the reason v1 does not take it.
> - **L843** — *"v1 keeps the 10-cap + fixed ordinals; **uncapped + reorder wait for FW-45**, which
>   then inherits a working `same` to preserve."* Explicit assignment.
> - **L2132** — the materialization rule is stated for *"removing **or reordering** slot N"*. Both
>   operations, from the start.
>
> So the original Pass-5 item text ("uncapped N + **reorder** only") was faithfully restating an
> existing decision, not inventing one. **The error: I checked the TRACKER ROW, found no "reorder",
> and concluded it was never in scope — but the row INDEXES and this plan is the detail HOME.**
> Auditing the index while ignoring the home inverts the ownership rule (CLAUDE.md: the tracker
> "points, never duplicates"). Third instance in this pass of the same root error — asserting a
> surface instead of grepping it.
>
> **What survives from the draft is the TECHNICAL point, which sharpens the existing decision rather
> than overturning it.** Reorder is not merely "the same hazard as removal": removal touches only the
> immediate successor (a single fixup — `compaction-probe.js`, 18 cases), whereas reorder moves a
> slot PAST others so every `same` between old and new position re-points, and a slot dragged into
> position 1 hits the where-`same`-is-illegal case from the other direction. A materialization
> CASCADE, with no coverage (the probe is a removal harness). That is *why* L831's "v1 shouldn't take
> the new control AND the `same`-rewrite complexity at once" is right, and it is the concrete content
> to carry into FW-45 when it runs.
>
> **Net: reorder STAYS in FW-45** (a) uncapped-N and (b) reorder — the item's original phrasing was
> correct. Note L831's surrounding section is itself partly superseded (it argues from the radio,
> since CUT), but the reorder scoping is independent of that and survives.

### Pass 6 — Build-prep / spike graduation (checklist, run when the real build starts)

> **⚠ REORDERED + PARTLY EXECUTED 2026-08-01 (user-directed walkthrough).** The suite re-run was
> marked "⚠ FIRST" but sat fourth; it is now item 0 because everything else is reviewed against a
> grammar that had never been exercised. Items 0 and the preview-tool comment fixes are DONE; the
> rest stay triage-only until the build starts. Outcomes recorded inline per item.
>
> **Preview-tool cleanup is DEFERRED TO THE END of the build (user, 2026-08-01)** — it must reflect
> the grammar as BUILT, since implementation constraints and phasing can still move things (the
> `step_class` narrowing already fell out that way). See item 2.

#### ✅ Item 0 (was "⚠ FIRST", listed 4th) — re-run both suites under the APPROVED grammar — DONE 2026-08-01

Both suites ran green on the superseded hop `+` FIRST (176/176, 18/18) so the delta was
attributable, then flipped. **Result under the approved grammar: 178/178 and 18/18.**

- **The checklist named 2 files; there are FOUR grammar copies.** The compaction probe owns no
  grammar (it `eval`s the real control), so `proto-fold-control.js` was a third copy and the
  render-side `proto-fold-tag.php` a fourth. All four were on `+`.
- **`step_class` narrowed to `{,}` exactly as predicted** — `bws_spike_grammar_validate` rejected
  the approved config on `hop_class ∩ step_class ≠ ∅` until it landed. The validator earned its
  keep: caught mechanically, not as a mystery parse failure.
- **HOP CLASS IS STRICT — `;` ONLY. `+` AND `/` ARE RESERVED (user, 2026-08-01).** Both were
  initially kept as lenient-accept twins; the user cut them, for a reason that generalizes past
  this grammar: **a lenient class is not free, it SPENDS the char.** Accepting `+` binds it to
  `hop` meaning now, so giving it a different job later would silently change what already-saved
  wires resolve to. `/` reserved on the same ground (it was rejected for reading-as-a-path, which
  is not a reason to spend it). This supersedes the §Lenient separator classes line listing
  `+`≡`/` as hop twins, and the "approved-twins-only removes `/` regardless" ledger entry (a) in
  §SLASH candidate — `/` is now reserved rather than merely unspent.
- **Reserved-ness is now ASSERTED, not just commented** — three checks per reserved char: not in
  `hop_class`; a chain using it is FLAGGED (not silently traversed); and it round-trips inert
  inside an arg. Carry these to the real parser spec.
- **A mutation test caught the first version of that guard being fake.** It skipped when the char
  WAS in `hop_class` (meant to spare the alt grammar) — so re-admitting `+` produced zero failures,
  just a quieter count (176→174). **The same edit that broke the property disarmed its guard.**
  Fixed by declaring the reserved set per-grammar (`$G['reserved']`), never inferring it from
  `hop_class`; re-running the mutation now yields 3 explicit failures. **GENERAL RULE for the
  build: a guard whose enabling condition is derived from the thing it guards will go quiet
  instead of failing.** Same family as Pass 1's "a pass that records itself DONE is not evidence
  it is done" — verify by mutation, not by a green count.
- Fixture fallout: 3 compaction cases and 6 `make-proto-page.php` wires moved to `;`. PFm4 was
  repurposed from a leniency fixture to a RESERVED-char fixture (same wire, now asserting it must
  NOT split into two hops). PFb1/PFb2 keep their `+` — still malformed at depth 0 — with a comment
  saying so, since the reason is now subtler and invites a wrong "fix".

- Remove the file_exists-guarded spike require (kill switch) from the main plugin file.
  **✅ TRIAGED 2026-08-01: clean single-block deletion.** Footprint in shipped code is exactly
  `bws-gb-dynamic-tags-extensions.php:251-261`; the control-JS enqueue lives INSIDE the spike file,
  so removing the block removes everything (verified by grep across `includes/` + the main file).
  **Spike leaves NO residue and needs NO migration (user, 2026-08-01):** `{{proto_fold}}` was never
  author-facing (`tools/` is `.distignore`d, absent in released builds), so no stored tag can
  contain it — same reasoning as `rows`→`entries` in DECISION 3, unshipped means no deprecation row.
  Delete the tag, the control, and the fixture generator together.
- **Clean up `tools/preview/tag-string-preview.html` once the grammar is finalized** (user,
  2026-07-31). **⏳ DEFERRED TO THE END OF THE BUILD (user, 2026-08-01)** — the cleanup must
  reflect the grammar as BUILT, not as specced, because implementation constraints and phasing can
  still move things; the `step_class` narrowing to `{,}` is the precedent (it was not a decision,
  it fell out of the approved config mechanically). Revising now guarantees revising twice, and
  Pass 6's own warning — a fixture displaying a wrong form as canonical is worse than none — cuts
  AGAINST an early pass, not for it. An interim staleness banner is in the sidebar instead.
>
> **⚠ THIS ITEM'S PREMISES WERE LARGELY STALE — audited 2026-08-01.** The described defects had
> mostly been fixed already, and the line refs had drifted:
>
> | Claim below | Actual state 2026-08-01 |
> |---|---|
> | schema stated as `name[value]` (`:316`) | the VISIBLE panel already said `name(value)` + approved chars; `:316` is CSS |
> | carries frontrunner-era `+` defaults | `SLOT_SCHEMAS` already had `hopSep: ';'` — only COMMENTS still said `+` |
> | remove CUT variants (`unsafe-lit`, `bracket-all`, …) | already gone from the code; only a stale comment listed them as live |
>
> So the real defect was narrower and entirely in COMMENTS — three contradicting adjacent live
> code. Those were fixed on the spot (a comment that contradicts the code beside it is a trap
> whenever the cleanup lands); pickers, presets and examples were left untouched.
>
> **⚠ THE "prune the pickers" INSTRUCTION IS REJECTED (user, 2026-08-01).** Removing rejected
> candidates conflates two different things. **Safety is enforceable; rejected-vs-reserved is a
> DECISION, and an exploration tool must keep decided-against options comparable** — FW-59 and any
> future grammar change need to re-examine WHY a char lost, which a pruned picker cannot support.
> Only UNSAFE chars are ever removed. This also re-opens the Unicode instruction below: `»`/`·` are
> excluded by hand-editability (ADR 0004), which is a decision about the wire, NOT a corruption
> risk — a different class from `>` (RichText entity-encodes it) or the un-enumerated React layer.
> Re-examine at the end-of-build pass rather than treating it as settled.

  **✅ ROLE DECIDED: (b) — keep it as the standing VISUAL-REVIEW artifact**, reduced,
  not retired. Rationale: FW-59 (base-tag free-form bracketing) needs exactly this, and the
  practice note above makes form-space coverage a REQUIRED gate for any future grammar change —
  a gate with no artifact behind it is one that gets skipped. Retiring it would mean rebuilding it
  under time pressure the next time.

  **This converts a TUNING SANDBOX into a REVIEW FIXTURE, which inverts where its value lives:**
  from the CONTROLS (pickers for tuning undecided chars) to the EXAMPLE SET (breadth of
  structurally different forms). Cleanup follows from that inversion:
  - **Update canonical defaults FIRST**, before any trimming — the file currently states the
    schema as `name[value]` (`:316`) and carries frontrunner-era `+` defaults, both now WRONG.
    A review fixture that displays a rejected form as canonical is worse than no fixture.
  - **Remove the CUT variants** (`unsafe-lit`, `bracket-all`, `bracket-uniform`, quote-wrap) —
    dead schemas, and their presence implies they are still live options.
  - **Separator pickers: ✅ KEEP — but ASCII-only, Unicode presets REMOVED** (user, 2026-07-31).
    They stay because FW-59 and any future grammar change need to compare candidates, and the
    practice note makes that comparison a required gate. **The preset lists must be pruned to
    typeable ASCII**: `VALUE_SEP_PRESETS` (`:478`) currently offers `»` and `·`, and
    `TYPE_SEP_PRESETS` (`:480`) offers `·` and `»`. Both are DEAD candidates under the
    hand-editability rule (ADR 0004 — a char the author cannot type breaks the wire's
    hand-editable guarantee), so offering them is offering a choice that can never be taken.
    **This is the fixture ENFORCING a constraint rather than merely documenting it** — the same
    reasoning that makes displaying a rejected form as canonical harmful. A picker that cannot
    produce an illegal option cannot lead the next review astray.
    Retain the existing `':' excluded — gb-reserved` comment idiom and add the parallel
    exclusion notes, so the WHY travels with the list.

  - **⚠ Exclusions come from FOUR INDEPENDENT LAYERS — filter on all of them** (user flag,
    2026-07-31: *"must not break block editor/React either"*). Any one filter alone lets a bad
    char through, and the layers do not overlap cleanly:

    | Layer | Excludes | Failure mode |
    |---|---|---|
    | **GB tag grammar** | `\|` `:` `{` `}` | pair/KV split, render matcher (`gb-constraints.md` §Unsafe). `\|`/`:` escapable; `{`/`}` hard-unsafe |
    | **Gutenberg RichText** | `&` `<` `>` `"` `'` | entity-encoded — round-trip as `&amp;`/`&lt;` in the editor field |
    | **Block editor / React / block-attribute round-trip** | ⚠ **NOT FULLY ENUMERATED** | see below |
    | **Hand-editability (ADR 0004)** | all non-ASCII / untypeable | author cannot type it |

    Note `>` is excluded by RichText but is ASCII AND typeable — so an "ASCII-only" filter alone
    would readmit it. That is the concrete proof the layers must be applied jointly, not as a
    single "safe chars" list.

    **The block-editor/React layer is the one with no written enumeration**, and it is a real
    surface: option values pass through block attribute serialization into the post-content
    comment JSON, through React prop/state round-trips, and back out on reopen. Candidates that
    warrant explicit checking before any picker offers them: backslash (JSON escape — doubles or
    mangles), backtick, `$` (regex-replacement semantics in `String.replace`, a classic silent
    corruptor), and anything that survives PHP but not `JSON.parse`/`JSON.stringify` symmetry.
    **Do not treat the absence of a known failure as safety here** — the `:`-JS-limit-2 bug in
    the table above is precisely a JS-layer failure that only appeared on editor REOPEN, i.e.
    invisible on first render. Verify empirically in the editor, not by reading the char list.
    If the check produces findings, they belong in `gb-constraints.md` as a new row/section —
    that doc owns GB-imposed constraints, and a React-layer constraint is the same class of fact.
  - **BUILD OUT the example set to cover the form space** (practice-note rule 1) — this is the
    actual work, not the trimming. The narrow sample is what let `+` through; a review fixture
    whose examples are as narrow as last time would repeat the failure exactly.
- Graduate `tools/test/slot-fold-roundtrip-spike.php` → registered `*-test.php` + CLAUDE.md
  §Update-triggers row.
  **⚠ TWO harnesses graduate, not one (found 2026-08-01).** This item names only the round-trip
  harness, but `tools/spike/compaction-probe.js` (18 cases) is a SECOND harness and the ONLY
  coverage of out-of-order slot removal + `same`-materialization — which the PHP side structurally
  cannot reach, because compaction lives entirely in the control and `bws render-tag` only ever
  sees ALREADY-compacted wire. Left in `tools/spike/` it dies with the spike and that coverage is
  silently lost.
  **✅ DECIDED (user, 2026-08-01): graduate it AS A JS HARNESS** into `tools/test/`, with an
  §Update-triggers row invoked via `node`. This is a real CONVENTION CHANGE — `tools/test/` is
  100% PHP+MD today and CLAUDE.md §Development says "**Pure PHP harnesses**"; that wording must be
  widened when the row lands. Rejected: porting the cases to PHP (the probe's whole value is that
  it loads the REAL control by `eval`, so a port would test a reimplementation, not the shipping
  logic) and leaving it in `tools/spike/` referenced from the row (relies on the row being read).
  NB an adjacent gap already exists — `serialization-order-normalizer.js` has a trigger row whose
  only harness is PHP; the compaction probe would be the first JS-side harness to close one.
- ~~**⚠ FIRST: re-run BOTH suites under the APPROVED grammar.**~~ **✅ DONE 2026-08-01 — promoted
  to item 0 above** (it was marked FIRST but listed fourth). Outcome, the four-copy finding, the
  reserved-char cut and the mutation-test lesson are all recorded there. Harness is now 178 cases
  (was 176); compaction probe unchanged at 18.
- Carry into the real parser spec: depth close-then-reopen guard (L977), lenient-class
  disjointness validator (`bws_spike_grammar_validate` — lives in
  `tools/test/slot-fold-roundtrip-spike.php`, NOT in `tools/spike/`), compaction/materialization
  cases (compaction-probe.js, 18 cases — count VERIFIED by running it, exit 0),
  PFm2 invisible-but-preserved hazard as a standing pattern.
  **Added 2026-08-01 (from item 0):** the RESERVED-CHAR property — `+` and `/` carry no separator
  meaning and must be asserted inert (flagged in a chain, preserved verbatim inside an arg), with
  the reserved set DECLARED per-grammar rather than inferred from `hop_class`; and the
  `step_class = {,}` narrowing, without which the validator rejects the approved config.

  **✅ ITEM 4 REVIEWED 2026-08-01 — all five carry-ins VERIFIED against the repo** (unlike items
  1–3, none had drifted): depth guard `slot-fold-roundtrip-spike.php:235` + its P6 case at `:677`;
  `bws_spike_grammar_validate()` at `:74` with 5 call sites, and the "NOT in `tools/spike/`"
  location note is correct; probe re-run at 18, exit 0; PFm2 hazard as written at §L1357.
  **THREE carry-ins were MISSING — added:**
  - **Grammar-copy count is a SPEC OBLIGATION, not an incident.** The depth guard now exists in
    THREE copies (`slot-fold-roundtrip-spike.php:235`, `proto-fold-control.js:107`,
    `proto-fold-tag.php:150`) and the grammar constants in FOUR — and item 0 found all four sitting
    on `+` at once, because nothing forced them to agree. The spike could absorb that (throwaway,
    one author, one week); the real build cannot, since the wire is author-facing and a divergence
    between the parse copy and the emit copy corrupts saved tags rather than failing a suite. The
    spec must name ONE owner of the grammar and carry a cross-copy assertion for every copy it
    cannot eliminate (render PHP / control JS at minimum — they run in different languages, so
    elimination is not available and agreement must be TESTED).
  - **Mutation-verification applies to the SPEC's own guards.** Item 0's rule (a guard whose
    enabling condition derives from the thing it guards goes quiet instead of failing) is recorded
    there as a build practice, but it is equally a property of the parser spec's assertions: any
    guard the spec mandates must be shown to FAIL under a deliberate break, not merely to be
    present and green.
  - **The coverage BOUNDARY is a carry-in, not just a method note** (already stated at §L1364):
    wire-faithful/edit-lossy bugs — the wire holds a value no control surfaces — are invisible to
    BOTH the pure harness and `render-tag`, since both only exercise parse→render. PFm2 is the
    instance; the boundary is the general fact. It decides how much of the build may lean on
    harness coverage alone, so it belongs where the spec's test obligations are set, not only in a
    trial writeup.
- Migration rows in `deprecated-tags-options.md` per family (`ref`→`refs`; `{N}-src/use/key` →
  folded `{N}:`).

  **✅ ITEM 5 REVIEWED 2026-08-02.** The item named 2 families and framed both as renames; neither
  holds. Findings, decisions and open questions below.

  **⚠ SHIP COUPLING CORRECTED (user, 2026-08-02): `{{table}}` does NOT ship before the fold — it
  ships WITH it.** Unfolded slot syntax on table is most of what forced these changes. So item 5 is
  IN-FLIGHT work on a days-away release, not build-prep for a later one. (An earlier note in this
  pass reasoned the opposite — that table ships on the current wire and the fold's migration
  surface merely grows by one family. Wrong, and the wrong direction: item 5 gates table.)

  **The source group is 7 keys + 5 datetime keys, not 2** ([serialization-order.php:96-108]):
  `src → ref → srcTermIn → limit → sep → use → key`, then `timeKey/startKey/startTimeKey/endKey/
  endTimeKey`. Unnamed by the item: `srcTermIn` (**371 hits / 30 files**, the largest footprint),
  `limit`, `sep`, and the datetime keys.
  - **`limit` folds to PER-STEP** (decided, user 2026-08-02). Forced, not tidier: a slot-level
    `limit` has no defined meaning once a chain fans more than once — which step does it cap?
  - **`sep` + the 5 datetime keys: EXPLICITLY DEFERRED.** Datetime would need a partial (read-half)
    fold nobody has defined. Recorded as a deliberate hole so it is not later mistaken for an
    oversight.

  **Neither `ref` nor `srcTermIn` is a RENAME, and `deprecated-tags-options.md` cannot express
  them.** `ref` is a KEY whose value is a field name; `refs` is a chain STEP SLUG whose ARG is that
  field name (`ref:office` → `src:refs,office` / `src(refs,office)`). The doc's rows are old-key →
  new-key ([:129]); a key that dissolves into a positional arg inside another key's value has no
  such row. Needs a different row shape or its own section.

  **The `rel` shim named in `deprecated-tags-options.md:129` DOES NOT EXIST** (`Shim:
  $options['ref'] ?? $options['rel'] ?? ''`). Every live `$options['rel']` read is a DEPRECATED
  N×M source class reading its OWN live key (`class-related-post.php:55`,
  `class-term-related-post.php:83`). The doc row is stale — nothing to pull.

  **`rel` does not reach the fold migrator at all** (user challenge, 2026-08-02 — corrects a wrong
  hazard first drafted here). `rel`→`ref` is `$rel_renames` (`deprecated-tags.php:200`) merged into
  `option_renames` on **`type:'tag'`** entries keyed by deprecated tag NAMES — a different code
  path (`transform_tag`, matched by name) from the fold's `type:'option'` path
  (`find_option_migration`). And the converter already sequences them in ONE pass
  (`class-tag-converter.php`: Step 3 tag migrations to fixpoint, Step 4 option migrations over the
  ALREADY-rewritten content), so `{{related_post_text rel:x}}` → `{{text src:ref|ref:x}}` → folded
  in a single run. The fold callback needs no knowledge of `rel`.

  **⚠ ENGINE HAZARD (real, survives the above with a different trigger): a no-op entry HALTS the
  cascade.** `find_option_migration` returns the FIRST matching entry; `apply_option_migration`
  returns as soon as a transform produces no change. Multiple `type:'option'` entries per live tag
  is already NORMAL — `text` has ~5 (`deprecated-tags.php:1086/1129/1156/1222/1240`), `image` ~7 —
  and they only all fire because each matched entry currently changes something. **The fold is the
  first entry that will chronically match WITHOUT changing anything**: it triggers on `src`/`key`,
  which nearly every tag has, and must no-op once folded. A folded `{{text}}` therefore hits the
  fold entry, no-ops, and the other five entries for `text` NEVER RUN — with registration order
  silently deciding whether that matters. **Fix is an ENGINE fix, not a fold workaround: skip a
  no-op entry and try the next rather than stopping. Wants a harness case — two entries on one tag,
  first no-ops, second must still fire.**

  **✅ DECIDED: multislot tags ALWAYS fold slot 1 under `1:`** (user, 2026-08-02). Two reasons, the
  first decisive:
  - **`{{table}}` carries tag-level and slot-level options in one string** — the row chain is a
    tag-level `src:` while columns are `N:src(...)`. Bare slot 1 would make `src:` mean both,
    resolvable only by a per-container key ALLOWLIST that must be maintained as options are added.
    Uniform `N:` makes the split SYNTACTIC: numeric-prefixed key = slot, everything else =
    tag-level. **Clarification (user, 2026-08-03): table is UNIQUE in having the same option NAME
    at both levels with different meanings.** A future base-tag move would RELOCATE options
    tag-level → slot-level, not duplicate a name across both. So the rule earns its keep twice over
    for different reasons: level-disambiguation on table, unfolded-legacy-vs-folded-slot-1 on base
    tags.
  - **Compaction stays a pure renumber.** Removing slot 1 promotes slot 2; if position 1 were bare
    and 2+ bracketed, promotion would be a FORM change stacked on the `same`-materialization that
    already happens in the same operation (compaction-probe.js PROMOTION case).

  Two invariants that fall out:
  - **Mode is presence-determined and modes DO NOT MIX.** A tag is folded iff any `N:` key exists;
    in folded mode no bare slot option (`src`/`ref`/`use`/`key`/`srcTermIn`/`limit`) may appear. A
    mixed wire (`{{text src:ref|1:key(a)|2:…}}`) is a hand-edit or a HALF-APPLIED MIGRATION and
    must be FLAGGED, never silently merged — it is exactly the shape a botched migration produces.
  - **Folding is ONE-WAY.** Compacting a multislot tag to one slot does not de-fold; `{{try_text}}`
    is still a multislot tag. Only base-tag → multislot migrates, in-modal, on author action —
    and that migration has **NO SCANNER PATH, correctly**: it is author-initiated, not
    version-driven, so nothing stored needs finding. Stated because "every migration has a scanner
    entry" is otherwise a reasonable assumption.

  **Remaining migrator-shape TBDs — ✅ ALL FOUR CLOSED 2026-08-03.** One was a real decision, one
  became answerable from the code, two were already settled elsewhere:

  - **~~Two target forms, one transform~~ → ✅ FALSE BINARY, the code decides it.** Base →
    `src:refs,office` (depth 0, `limit(2)`); slot → `N:src(refs,office)` (L1, `limit[2]`) — per
    §L2271-2311 the alternation rule working. **`find_option_migration` compares `match_tag` with
    `!==` (`class-migration-registry.php:259`) — an EXACT string, so every registration already names
    ONE tag.** Container-ness is therefore known at REGISTRATION time and never at runtime, which
    dissolves "one branching callback vs two registrations": **split the registration LOOPS, no
    branch.**
    ```php
    foreach ( $base_tags as $tag )      { register( $tag, 'bws_migrate_src_chain_base' ); }   // depth 0
    foreach ( $multislot_tags as $tag ) { register( $tag, 'bws_migrate_src_chain_slots' ); }  // L1
    ```
    **⚠ AT BUILD (5e): only the MULTISLOT loop was registered** — no base tag reads a chain off the
    wire yet, so the base callback would write unrenderable wire. The shape below stands; see
    §Flip decisions — migrator (5e).
    Shared grammar in a helper both call. **Precedent + the one difference that matters:** as+size
    looped ONE callback over `image`/`term_image`/`try_image` (`deprecated-tags.php:1296`) because its
    target form was IDENTICAL in all three (`as:url,full` bare or `2-as:…`). Here the forms differ BY
    DEPTH, so the split is warranted where as+size's would not have been. Do not cite as+size as
    precedent for a single callback without noting this.
  - **~~Tag LIST goes stale~~ → ✅ STALENESS IS A TEST OBLIGATION, not a registration-design problem.**
    The multislot list is **DERIVABLE**: `TagTemplateRegistry::get_modifier_templates()`
    (`class-tag-template-registry.php:67`) filtered on `supports_try`, plus `join` + `table`. No
    hand-maintenance. Base tags have no central list (literal
    `new GenerateBlocks_Register_Dynamic_Tag()` calls), so that list stays hand-written — **but the
    limit-default matrix already carries a row per family**, so a family added without a migration
    entry surfaces as a FAILING ROW rather than silent data loss. That is the guard, and it costs
    nothing extra.
  - **PHP/JS twin: NOT A DECISION — a known COST.** The scanner transform and the mount migrator are
    one grammar in two languages, unshareable — item 4's cross-copy obligation, now on AUTHOR-FACING
    wire. Recorded so it is budgeted, not resolved.
  - **~~Half-configured slots: drop silent or drop + flag~~ → ✅ CLOSED EARLIER TODAY** (Option A,
    container-aware, NO flag — see §RESOLVED above and §The FLAG surface).

- **Mount-time migration is the second migration path** (user, 2026-08-02: *"our new approach of
  also migrating on tag modal mount wherever possible"*). Precedent is SHIPPED:
  `serialization-order-normalizer.js:159-204` — an invisible `OrderNormalizer` control wrapped
  around the first option element via `tagSpecificControls` (priority 20), whose `useEffect`
  rewrites `extraTagParams` on mount when the order is off. Bulk path stays the scanner
  (`MigrationRegistry` + `transform_callback`, `deprecated-tags.php:1016`/:1304).

  **The two paths are COMPLEMENTARY, not belt-and-braces — mount is NOT a subset of the scanner.**
  The scanner reads `wpdb->posts.post_content` only (`class-tag-converter.php:76-78`,
  `post_type != 'revision'`, status not `auto-draft`/`trash`); block widgets live in the
  `widget_block` option and are reachable ONLY by mount.

  | Content location | Scanner | Mount |
  |---|---|---|
  | Posts/pages/CPTs (draft, private, scheduled) | ✅ | on open |
  | Reusable blocks (`wp_block`), FSE templates/parts | ✅ | on open |
  | Block widgets (`widget_block` in `wp_options`) | ❌ | ✅ |
  | Revisions | ❌ | ❌ |
  | Trash / auto-draft | ❌ | ❌ |
  | Tags stored in postmeta / ACF values | ❌ | ❌ |
  | Patterns registered in theme/plugin PHP | ❌ | ❌ |
  | Other sites in multisite | ❌ (per-site) | per-site |

  Mount also needs ≥1 option element to render (it WRAPS the first one) and `isBwsTag` to pass — a
  tag whose options all vanish under the fold would never mount its own migrator.

- **Mount-RECOVERABILITY audit (4a, 2026-08-02).** Checked the legacy→chain linearization first as
  the suspected trap; it is NOT one. Step order is ONE global rule in ONE builder
  (`field-helpers.php:357-375`: ref first, and only when `src === 'ref'`, then `srcTermIn`). No
  per-family variation, so linearization is deterministic.

  | Transform | Why deterministic on mount |
  |---|---|
  | `ref` → `refs,<f>` step | gated on `src==='ref'`; an orphan `ref` is already dead at render |
  | `srcTermIn` → `terms,<tax>` step | always follows ref, single builder |
  | slot `limit` → per-step | **legacy cannot fan twice** — no chain syntax pre-fold ⇒ ≤1 fanning step ⇒ unambiguous attachment. The ambiguity that FORCED per-step cannot exist in legacy data |
  | `{N}-src/use/key` → `{N}:` | positional regroup, no inference |
  | absent slot src (N≥2) → `src(same)` | legacy semantics explicit: `base-tags.php:1042` `'' src = inherit prior resolved source` |

  **THREE genuinely unrecoverable:**
  1. **Half-configured slots — ⚠ CORRECTED 2026-08-03 (user): this is JOIN-ONLY, and the real
     finding is a CONTAINER DIVERGENCE.** First drafted from the join loop and wrongly generalized
     to try_.
     - **try_ (`class-tag-template-registry.php:705-719`): `3-src:ref|3-ref:office` with no
       `use`/`key` IS fully configured and already renders that way.** `$has_new` is true on
       `src_raw` alone so the slot is NOT skipped; carry-forward then sets `$last_src`/`$last_ref`
       from the slot while `$last_key`/`$last_use` carry through untouched — i.e. exactly
       `use:same`, source overridden + read inherited. Migrates cleanly to
       `N:src(refs,office);use(same)`. Nothing is dropped and nothing is lost.
     - **join (`base-tags.php:1025-1037`) has NO read carry-forward at all** — no
       `$last_use`/`$last_key`, just `'' === $key && '' === $use → continue`. The inert slot exists
       only here, and the code's own comment calls the skip DEFENSIVE ("Reveal keeps gaps from
       occurring mid-chain"), i.e. likely unreachable through the UI — so the population is
       hand-edited wire, where drop + flag is cheap and safe.
     - **✅ NOT AN OPEN DECISION — SETTLED 2026-07-31 at §Matrix B (§L2602-2637), reached via
       §L1993 (Absent-read REOPENED) and §L2095** (user pointed here 2026-08-03). Two earlier
       drafts in this item are SUPERSEDED: one framed it as "does the fold unify the containers?"
       with three options; the next claimed join migrates by PRESERVING absence. Both wrong. The
       cell was decided, and **Matrix B is closed — §L2620 states both matrices have no open
       cells** (an earlier draft here recorded an "open absence cell" residual to carry to item 4;
       withdraw it).
       - **FOLDED parse, combining, slot ≥2, read absent = UNCONFIGURED → skip.** Not inherit.
       - **LEGACY parse, same cell = inherit, synthesize `same`** ("legacy meaning, must be
         preserved on this path"; §L2625-2629 consequence 1: *do not "fix" it*).
       - **try_** legacy absence → materialize `use(same)`; behavior identical, intent explicit.
     - **⚠ DEFECT FOUND 2026-08-03 — Matrix B's combining row is INTERNALLY INCONSISTENT, and the
       spike code takes the wrong side.** The folded decision is justified at §L2618-2620 as
       *"Preserves shipped behaviour... `base-tags.php:1035` already `continue`s when both `use`
       and `key` are empty"* — i.e. shipped join SKIPS such a slot. But the LEGACY column of that
       same row maps it to synthesize-`same`, which makes a previously-skipped slot RENDER. **The
       two columns of one row cite the same shipped fact for opposite mappings**, and only the
       folded column's reading matches `base-tags.php:1035`.
       The code instance: `proto-fold-tag.php:233-234` —
       `} elseif ( '' === $key ) { $read = ( $n >= 2 ) ? array( 'kind' => 'same' ) : null;` —
       is **CONTAINER-BLIND**. The spike only ever ran one container (`proto_fold`), so try_'s
       meaning was generalized to all. Consequence 1's "do not fix it" was written to protect
       try_'s legacy meaning; applied to join it CHANGES OUTPUT.
       **This is the same over-generalization that produced the withdrawn drafts above — from one
       container's resolver, in the same direction, three times in this pass. Treat "verified
       against the shipped resolver" as incomplete unless it names WHICH resolver and checks the
       other.**
       **~~OPEN: does `fold_from_legacy` become container-aware~~ → ✅ RESOLVED 2026-08-03:
       CONTAINER-AWARE (Option A).** Combining + slot ≥2 + read absent ⇒ `read = null` (unset =
       skip, output preserved); selecting keeps the `same` synthesis. Four findings decided it —
       see §The flag surface below for the full derivation:
       1. **The render change CASCADES.** In shipped join the `continue` (`base-tags.php:1036`)
          precedes the carry-forward (`:1042-1047`), so a skipped slot **never updates
          `$last_src`/`$last_ref`**. Migrating slot 2 to `src(refs,office);use(same)` would make
          slot 2 render AND re-point slot 3's `src(same)` at `refs,office` instead of slot 1's
          source — an output change at slots the migration never touched.
       2. **The shape is UI-REACHABLE — this item's earlier "hand-edited wire, drop+flag is cheap"
          premise is WRONG.** Slots 1-2 are always visible (`:881-882`), so an author can set slot
          2's source and never its field; slot ≥3 likewise once revealed. The code's "defensive,
          likely unreachable" comment is about MID-CHAIN GAPS (slot 3 set, slot 2 empty), which
          reveal does prevent — not half-configured slots, which reveal does nothing against. The
          population includes ordinary abandoned edits.
       3. **The spike's own logic is incoherent across the sibling cell:** `key` set + `use` absent
          ⇒ FLAG, never guess (`:236-237`); BOTH absent ⇒ guess `same` (`:233-234`). Strictly less
          information, strictly more confident.
       4. **Option A closes the Matrix B inconsistency** instead of preserving two columns of one
          row that disagree.
       Minimal by construction: try_'s legacy absence genuinely IS inherit (`$has_new` true on
       `src_raw` alone, read carries forward), so only the join branch moves. `{{table}}` is
       greenfield and never reaches this path; it inherits the combining branch.
       **Representable:** `2:src(refs,office)` with unset read is LEGAL folded wire meaning skip —
       no new shape needed. **Container is free:** `transform_callback` registers per-tag in
       `MigrationRegistry`, so a `$combining` bool costs one parameter.
       **NO flag on this path.** Output is preserved exactly, so there is nothing to review — and
       under the repeater the slot renders visibly with an empty Read select, which surfaces the
       half-configuration better than the flat UI ever did. The UI does the job a flag would.

     - **⚠ The legacy mapper's INPUT SURFACE is 6 keys, not 4.** `bws_spike_fold_from_legacy` reads
       only `src`/`ref`/`use`/`key` (`proto-fold-tag.php:212-215`). Legacy join/try_ slots also carry
       **`{N}-srcTermIn`** (must become a chain step) and **`{N}-limit`** (must become bracket-kv on a
       step). Both are silently DROPPED today. Fair for a spike whose header declares migration out of
       scope, but `srcTermIn` dropping is an output change on its own — the real migrator must read
       all six.

  ### The FLAG surface — which scenarios actually warrant one (2026-08-03)

  **Two glyph classes in the spike, different lifecycles — do not conflate** (`proto-fold-tag.php:330-331`):
  `⚑` = MIGRATION refusal (legacy wire the mapper will not guess at); `⚠` = RENDER-side malformed
  FOLDED wire.

  **⚑ MIGRATION — exactly one site today, and it is WRONG.** `:237` flags slot ≥2 with `key` set +
  `use` absent as an "ambiguous FW-51 shape". **It is not ambiguous in ANY container** — all three
  meanings are definite:

  | Container | Shipped meaning | Migrates to |
  |---|---|---|
  | `{{join}}` | `use` defaults to `key` (`base-tags.php:1055`, `'' === $use ? 'key'`) ⇒ plain keyed read | `N:key(x)` |
  | `try_*` w/ `per_slot_use` | key is **DISCARDED** (`class-tag-template-registry.php:701-703`), both axes inherit; if key was the only thing set, `$has_new` is false ⇒ **slot SKIPPED** — this IS FW-51 | `N:src(same);use(same)`, or drop |
  | `try_*` psk-only (no psu) | no use axis; key overrides against the template's fixed read | `N:key(x)` |

  **Flagging this universally would flag NEARLY EVERY REAL JOIN TAG:** `use` is `_strip_default` with
  `key` first, so `2-key:b` with no `2-use` **is** the canonical join slot-2 wire — what you get from
  picking a field and touching nothing else. Same over-generalization as the defect above; **fourth
  instance this pass, same direction.**

  **⇒ With container knowledge, ZERO migration scenarios require a flag.** Every legacy shape is
  decidable. The `⚑` path may exist as a backstop, but no currently-known input should reach it.

  **Fallout — the fold CLOSES FW-51 by construction.** Migrating the try_ psu case faithfully
  preserves a shape FW-51 calls a defect, but only for EXISTING wire: the broken shape becomes
  **unexpressible** under the fold, because the field combo renders only when Read kind = `key`, so an
  author cannot set a key without setting the read. FW-51 is an editor-discoverability bug and the
  repeater removes the affordance that creates it. Claim this on the FW-51 tracker row.

  **⚠ RENDER — three scenarios, all folded-wire-only:**
  1. **Parse error** (`:354`) — grammar violation; includes reserved `+`/`/` at depth 0 (lab PFb1/PFb2, PFm4).
  2. **Slot ≥2 with NO `src` token** (`:369`) — under S1 `same` is always serialized, so absence means hand-edited (lab PFr5).
  3. **Incomplete hop** (`:375-378`) — `refs` with no reference field (lab PFr7/PFr8).

  **The adjacency that must not blur:** a COMBINING slot with a chain and no read is **NOT** a warn —
  it is legal folded wire meaning *skip*, and it is exactly what the seed produces. It sits one line
  from warn class 2 in the same code path. Conflate them and every freshly-added, not-yet-configured
  slot screams at the author.

  #### ⚠ CONSTRAINT — reserved/grammar chars are INERT inside TEXT-ENTRY VALUES (user, 2026-08-03)

  **Parse-error warnings must NEVER fire on `+`, `/`, `;`, `,`, or brackets appearing inside the VALUE
  of an author text field** — `sep`, `valueSep`, `timeSep`, `rangeSep`, `format`, `fallback`, and any
  future free-text option. There the chars are CONTENT, not grammar.

  This is not hypothetical — shipped defaults already contain them:
  - `format` placeholder `F j, Y g:i A` (`datetime-tags.php:70`) — commas and a colon.
  - `fallback` placeholder `Date/time TBA` (`:126`) — a **RESERVED `/`**.
  - `sep`/`timeSep` default `', '`, `rangeSep` an en-dash pair — separator chars as content.
  - Author text plausibly carries `+` ("18+", "3+ years").

  **Why it holds today, and what it constrains tomorrow.** These are TAG-LEVEL flat option keys, so
  their values never enter the folded slot value's tokenizer — the grammar only binds inside `{N}:`.
  The rule is therefore free right now, and its real force is FORWARD: **any text-entry option later
  folded INTO a slot value** — the deferred per-slot `sep` (ADR 0003 blocker dissolved at 1.16.0),
  or a datetime slot's `format` as a sibling token under §Multi-option terminal tags — **must be
  bracketed/escaped so its content can never be read as grammar.** A lenient parser that "helpfully"
  flags a `+` inside a fallback string would fire on ordinary author copy.
  Corollary for the reserved-char assertion (item 0): "`+`/`/` are inert" means inert AS SEPARATORS at
  structural positions. It does NOT license validating them out of value positions, and the
  harness/lab rows that assert inertness must not be read as licensing a global char check.

  ### `limit` — UNLIMITED encoding, table's default, and the regression floor (user, 2026-08-03)

  Prompted by the user: *"since we are changing `limit` and opening up per-tag implicit/unset limits
  (table wants the last step to fan out by default), we should add test fixtures to regression-check
  the `limit=1` default behavior on existing tags."* Warranted — audited below, coverage is ONE row.

  #### Blast radius — THREE independent default sites, no shared constant

  | Site | Path | Expression |
  |---|---|---|
  | `field-helpers.php:547` | seam (`bws_resolve_field_values`) — text/title/email/phone | `max( 1, (int) ( $options['limit'] ?? 1 ) )` |
  | `field-helpers.php:621` | `bws_collect_value_list` — datetime + the FW-49 list branches | `max( 1, (int) ( $options['limit'] ?? 1 ) )` |
  | `class-tag-template-registry.php:751` | try_ slot dispatch | `max( 1, (int) ( $limit ?: 1 ) )` |

  Three copies of one rule — **same drift class as item 6's read enum**, and the fold must change all
  three. Site 3 uses `?:` not `??`, so it also collapses `limit:''`: behaviorally equal today,
  DIVERGENT the moment "unset" and "0" stop meaning the same thing. ⇒ **Extract ONE clamp helper
  BEFORE the semantics change**, not alongside it. This began as hygiene; table's default (below)
  makes it LOAD-BEARING — a datetime column inside a table reaches site 2, a try_ slot reaches
  site 3, so `0` must mean the same thing at all three.

  #### The second-order hazard: the LINK GATE is count-based

  `field-helpers.php:584-585` (step 5 — top-level link = `values[0]['link']` iff count is exactly 1)
  and `class-tag-template-registry.php:744-748`. **A silent 1→many flip does not merely lengthen
  output — it DROPS ANCHORS.** This is the regression most likely to ship unnoticed: the text still
  reads plausibly, the link is just gone. ⇒ **Every regression row asserts TWO things: one value AND
  link present.**

  #### Existing coverage — audited, and it is ONE row

  | Tag | Unset-limit row today |
  |---|---|
  | `text` | **T7.1 only** (`src:ref`, "default limit 1, first target only"). T3.1-3.3 all SET `limit` — no unset `srcTermIn` row |
  | `title` | none |
  | `datetime_single` / `_range` | **covered** — D4.2 (no limit → 1), D4.9 (limit 1 → wrapped) |
  | `{{join}}` slot | none — J19 sets `limit:2` |
  | `try_*` slot | none — site 3 has ZERO coverage despite being a separate implementation |
  | `email` / `phone` | none — both register `limit` (`email-tags.php:81`, `phone-tags.php:82`) |

  `{{content}}` is **NOT list-capable** (registers no `limit`) — out of scope BY DESIGN; state it so
  nobody adds rows.

  #### UNLIMITED encoding — precedent, and why it does NOT collide

  User supplied the GB/WP precedents: GB's *Posts Per Page* treats **`-1`** as unlimited (the
  "max 50 in the editor" note appears only at that value); WP's core Query Loop **blocks `-1`** and
  documents **`0`** as unlimited. Both conventions are in the wild.

  **The plugin has NO `min`/`max`/`step` on any control** (verified — zero hits repo-wide), so `limit`
  is a bare number input: `0` and `-1` are typeable TODAY and both silently clamp to 1. GB serializes
  every value except strict `false` (`DynamicTagSelect.jsx:557-570`), so **`limit:0` both saves and
  survives** — this is reachable wire, not hypothetical.

  **DECIDED: PARSE both `0` and `-1`; EMIT `0`.** Matches this plan's established asymmetric rule
  (§Read-token exclusivity — parse tolerant, emit exclusive). Canonical `0` because WP's own block
  editor blocks `-1` and states 0 = unlimited, and because `limit[0]` hand-reads as "no limit" better
  than a minus sign in a value position (ADR 0004). GB-native authors typing `-1` are served by
  tolerance and normalized on next write. **Leave `min` UNSET on the control** — a control that fights
  a hand-typed `-1` works against ADR 0004, and tolerance already covers it.

  #### ⚠ CORRECTED — legacy `limit:0` migrates to UNLIMITED, not to 1 (user, 2026-08-03)

  An earlier draft in this session proposed mapping legacy `limit:0`/`-1` → `limit[1]` to "preserve
  output". **User: *"why map existing 0/-1 to 1? I didn't mean to change an intentionally unlimited
  existing tag."* Correct — the draft is WITHDRAWN.**
  The reasoning error is worth keeping: *preserve output* is the right default when the legacy meaning
  is a DESIGNED SEMANTIC. Here it is a **CLAMP** — `max(1, …)` silently discarding a written value —
  and freezing a clamp cements a bug instead of honoring wire. **`limit:0` cannot ever have MEANT 1:**
  an author wanting one result types 1 or leaves it unset. Preserving 1 preserves an outcome nobody
  requested.
  ⇒ **Legacy `limit:0` / `limit:-1` → `limit[0]` (unlimited).** Honor the written value under the new
  semantics.
  **This REMOVES work rather than adding it.** The draft's seam-collision worry (an un-migrated legacy
  `limit:0` hitting a seam that now reads 0 as unlimited) evaporates: under this decision that is the
  CORRECT result, so the legacy and folded paths AGREE, dual-read stays consistent, and no mapper
  clause or special case is needed anywhere.

  #### ⚠ NEW DEFECT the semantics introduce: `(int)'abc' === 0`

  Under the old clamp a garbage value landed harmlessly on 1. Under the new rule it lands on
  **UNLIMITED** — a typo silently fanning out a whole relationship. **The parse MUST gate on
  `is_numeric()` and treat non-numeric as UNSET (⇒ 1), never as 0.** No such guard exists today
  because it never mattered. Applies at all three sites (another reason the helper lands first).

  #### `{{table}}`'s terminal step defaults to UNLIMITED — granted, cost is LOW (user, 2026-08-03)

  User: *"I would PREFER table's terminal step to have an unset implicit limit value of 0, but I'll
  reconsider if the cost is high."* **Granted.** The default does NOT need to live inside shared
  resolution code: **table's column resolver MATERIALIZES `limit => 0` when the author left it unset**,
  before calling the absorb seam. The seam then needs exactly ONE new rule — `0` = unlimited — which it
  needs for folded `limit[0]` REGARDLESS, so it is not attributable to table. Container-specificity
  stays in table's own caller, which is the better factoring anyway. **Precedent: join already threads
  its slot limit this way** (`base-tags.php:1060`, `$slot_opts['limit'] = $lim`) — table does the same
  with a different unset default.

  | Change | Size |
  |---|---|
  | Seam/site rule: `0` = unlimited | required by `limit[0]` regardless — NOT table's cost |
  | `is_numeric()` guard | one branch, in the shared helper |
  | Table resolver materializes `limit => 0` when unset | a few lines, NEW code |
  | Regression fixtures pinning unset → 1 elsewhere | the work requested above |

  **Contingency: the clamp-helper refactor lands FIRST.** If it slips, the escape hatch is table
  emitting an explicit `limit[0]` on every terminal step — more verbose wire, identical behavior, no
  shared-code change. Prefer the helper.

  #### Fixture plan — new cross-cutting `tools/test/limit-default-test-matrix.md`

  Precedent `fw52-order-test-matrix.md`: named for the PROPERTY under test, not a tag family, because
  it spans five families and three code paths. ~13 rows — unset-limit × {text, title, datetime, email,
  phone} × {`srcTermIn`, `src:ref`}, plus a join-slot row and a try_-slot row (site 3 has no coverage
  at all), plus explicit-value rows. Fixtures already fan ≥2: `department` (Sales, Support) and
  `related_staff` (Jane Partner, Tom Associate) — no blueprint change expected.
  **Every row asserts BOTH single-value AND link-present** (the count-based gate above).
  **⚠ The clamp rows INVERT under the correction:** an earlier draft's `limit:0`/`limit:-3` → 1 rows
  are WRONG — they now assert UNLIMITED. Add a `limit:abc` row asserting **1** (the `is_numeric`
  guard), which is where the old and new rules genuinely differ from each other.
  Per CLAUDE.md the rows MUST also be generated as visible GB blocks in the blueprint's `blocks.php`,
  and the new matrix needs its own §Update-triggers row.

  #### User-facing obligation

  This CHANGES RENDERED OUTPUT on existing sites (`limit:0` tags start fanning). Per
  `feedback_upgrade_notice_mechanism`, it wants a `readme.txt` `== Upgrade Notice ==` entry
  (Updates-page only, 300-char cap, attention-grabber first) PLUS a CHANGELOG **Changed** entry — not
  a silent behavior fix. Both are user-facing prose ⇒ draft for user review at release time.

     - **⚠ ITEM 3 FALLOUT: the compaction probe pins the WRONG SEED as universal.**
       `compaction-probe.js:118-121` asserts `seed shape = src(same);use(same)` unconditionally.
       Per §L2084-2087 that is the **SELECTING** seed; combining containers seed `src(same)` + an
       UNSET read (the author must choose a field — that is the configuration act). Graduating the
       probe as-is encodes a try_-only rule as a general invariant. **Must be container-parameterized
       at graduation.** Same single-container origin as the defect above.
     - **Framing withdrawn:** an earlier draft called the container divergence "arguably a join
       DEFECT". It is deliberate and documented (§L2613-2616; `base-tags.php:895-897` *"source can
       sensibly carry forward in combining; field identity cannot"*).
     - **⚠ The emit rule was stated too broadly here. It is TWO rules split by LIFECYCLE**
       (§L2048-2060): *"absence never means inherit"* is an EMIT-side rule for folded values ONLY.
       The READ side must keep honouring absence-means-inherit for legacy-shaped input,
       permanently if unmigrated tags persist. Not in conflict — provided the emit rule is never
       written as though it were a universal parse rule, which is exactly the error that section
       was created to correct.
  2. **Stripped defaults** (CONTEXT.md I3 — a value equal to its default is not serialized). Mount
     reads ABSENCE and re-supplies the default AS IT STANDS AT FOLD TIME. If any default's value
     moves in the same release, the tag silently changes meaning. **CONSTRAINT: freeze defaults
     across the fold release, or migrate them in a prior one.**
  3. **The three no-successor families** (registry-only since 1.14.0, see
     `project_deprecated_tags_no_migration_path`). No fold target exists → they stay legacy
     permanently, so the render side can never drop legacy key reads outright.

- **Restore-hook detection (4b — slated, user-proposed 2026-08-02).** `untrashed_post( $post_id,
  $previous_status )` and `wp_restore_post_revision( $post_id, $revision_id )` both hand over a
  post id; the converter already has a per-post path (`class-tag-converter.php:283-328` — reads
  content, diffs, updates), so this is REUSE, not new detection logic. Notice via transient →
  `admin_notices`.
  **DETECT + WARN, do NOT auto-convert:** a restore is precisely when the author wants the old
  content back verbatim; silently rewriting it on the way in is the wrong default.
  **This closes more than the two rows it targets — `auto-draft` falls out entirely** (it never
  "restores"; it becomes a draft on save, by which point mount has already run). Residual gaps
  after 4b: postmeta-stored tags, PHP-registered patterns, other multisite sites.
  **Independent of coverage quality, revisions/trash argue the render side keeps a TOLERANT read of
  legacy keys for at least one cycle** — a restore can reintroduce legacy wire long after the fold
  ships.
- **Anti-drift obligation — `bws_build_slot_read_options()` twin (VERIFIED + SCOPED 2026-08-03).**
  Premise holds, and understates it: the obligation is **already violated in shipped code**, and the
  read enum is **4 copies deep** before the fold adds a 5th.

  **Container check (BOTH named, per the index preamble):**
  | Container | Slot read enum source | Obligation |
  |---|---|---|
  | `try_*` (selecting) | `class-tag-template-registry.php:517-523` — derives from `$tpl_options['use']['options']` | honored |
  | `{{join}}` (combining) | `base-tags.php:906-918` — hand-authored `key`/`title` literal | **violated today** |

  Base `{{text}}` gains an analog → `try_text` slots inherit it, join slots do not. Live drift
  surface, not prospective.

  **"Derives from base read definitions" was UNDERSPECIFIED — which base?** Text's `use` enum exists
  twice already, byte-identical, unenforced: `base-tags.php:93-101` (base `{{text}}` GB registration)
  and `:362-370` (the `text` MODIFIER TEMPLATE feeding `term_*` + `try_*`). try_ derives from the
  TEMPLATE copy, so its anti-drift is safe against the template only — and the template is itself an
  uncontrolled copy of the base tag. Join's literal is copy 3; the spike's `READ_OPTIONS`
  (`proto-fold-control.js:319-323`) is copy 4. **The spike copy has ALREADY drifted twice, visibly:**
  order `title,key` vs shipped `key,title`, and an explicit `''` "Default (intrinsic analog)" row
  where shipped uses `_strip_default`. Evidence the twin pays immediately, not just prospectively.

  **The container parameter is already documented as the fork's CAUSE.** `base-tags.php:842-847`
  PHPDoc: join deliberately omits `Same as Previous Field`, "a concrete reason join owns its build
  loop rather than reusing the try_ per_slot_use emit, **which hardcodes the same-prepend**." So the
  twin dissolves the fork rather than papering it:
  `bws_build_slot_read_options( int $n, array $base_read, bool $allow_same ): array` — mirroring
  `$allow_site` on the traversal twin (`base-shared.php:299`), which both containers already call.
  Divergence axis is `same`-prepend = the READ axis = Matrix B's axis. Consistent, NOT a new
  sensitivity. **Spike prepends `same` unconditionally at slot ≥2 (`proto-fold-control.js:840`) —
  container-blind, THIRD instance of the same defect** (with `proto-fold-tag.php:233`,
  `compaction-probe.js:118`).

  ### THE PATTERN IS ALREADY SHIPPED — datetime, since 1.6.0 (decides the "which base" question)

  Neither copy is the source. **Both sides compose from the same GROUP-PURE LEAF builders:**
  `bws_get_base_datetime_single_options()` (`datetime-tags.php:109-130`) = source + traversal + list
  + `field_key_options()` + `leading_options()` + link + fallback; `bws_get_datetime_single_template_options()`
  (`:40-53`) = `leading_options()` + `field_key_options()` + fallback. **Same leaves, different
  composition per context.** Zero duplication, in the messiest family. text/content/title/permalink/
  image are not architecturally different — they are the un-refactored remainder (datetime was built
  builder-first; the others were inlined and never converted).

  **FW-4 did NOT cause or resolve this** (user hypothesis, checked). FW-4 was Axis 2 (RESOLUTION —
  `try_site_fn` legs, thin closures over `bws_site_resolve_value`); option DEFINITIONS (Axis 1) were
  untouched before and after, so no refactor was omitted from its scope. **But it killed the last
  JUSTIFICATION:** while try_ arguably lacked capabilities the base tag had, the template's option set
  read as a deliberate subset. Post-FW-4 both paths resolve through one function per family — same
  capability, nothing defends two option sets. FW-4 removed the excuse, not the duplication.

  **Feasibility — the only divergences found are two kinds, both handled:**
  (a) **`show_if`, mechanically derivable.** Base text `key` carries `show_if use:not:title` (`:112`);
  the template encodes the identical fact declaratively as `try_use_no_key_values => ['title']`
  (`:393`); slots re-qualify via `bws_slot_qualify_show_if`. Three spellings of one fact. **Leaf
  returns enum + control shape; CALLERS overlay `show_if`** — the same split the traversal twin
  already uses.
  (b) **Label drift, i.e. a live defect.** Image `as` exists **3×** — `base-tags.php:274-284`,
  template `leading_options` `:477-487`, template `options` `:490-500` (two of those in the SAME array
  literal). Labels already diverged: `Return type:` vs `Return image as:`. Same control, two strings.

  Nothing found requires the sets to differ.

  ### Leaf-granularity discipline — leaves are GROUP-PURE (FW-52 / 1.16.0 review, user-required)

  1.16.0 split ordering into TWO independent mechanisms, and the leaf boundary must respect the split:
  - **SERIALIZATION order is GLOBAL and composition-independent** — `bws_serialization_order_sort()`
    sorts by the canonical KEY_MAP (`serialization-order.php:80-121`) plus its JS port, keyed by BARE
    option name with the `N-` slot prefix parsed off. **Leaf extraction therefore CANNOT break
    serialization order**: the sort never sees registration sequence.
  - **CONTROL order IS registration order** — GB renders `options` as declared, and it is
    **PER-CONTEXT, deliberately**: base = source → format → link → fallback (FW-52 canonical);
    `try_*` = Group 1 leading/format → Group 2 slots/source → Group 3 trailing
    (`class-tag-template-registry.php:390-392`). The contexts differ on purpose; a leaf must not
    assume either.

  **⇒ INVARIANT: a leaf builder returns keys from ONE canonical group only.** The caller places it at
  that group's position in its own control order. Current leaves already satisfy this —
  `field_key_options()` = `key`,`timeKey` (source, ranks 6-7); `leading_options()` = `as`,`format`,
  `timeSep`,`showCurrentYear`,`showMidnight` (all format). Multi-group returns
  (`..._template_options()`, base tag builders) are **COMPOSERS, not leaves** — the invariant binds
  leaves only, and the distinction must be kept explicit or the next extraction re-bundles.
  Corollary: the image template's `options` bundling `as` + `use`/`key` + `fallback` (3 groups, and it
  re-inlines its own `leading_options`) is exactly what a group-pure split fixes —
  `image_format_options()` (`as`) + `image_field_options()` (`use`,`key`), fallback composed by each
  caller. All keys involved (`use`/`key`/`as`/`fallback`) are in KEY_MAP, so none hit the
  unknown-key `source`-group default.

  **SCOPED for the fold release (ship-in-days): TEXT LEAF ONLY.** `bws_get_text_field_options()`
  (`use` + `key`). Base registration + modifier template + the new read twin all consume it: text goes
  **4 copies → 1**, so the fold REDUCES copies instead of adding a 5th.
  **Image is OUT — `{{table}}` columns do NOT take the image type** (user, 2026-08-03): a table cell
  needs a full `<img>` element, which the image tag does not emit — its `as` enum returns
  url/id/title/alt/caption, all scalars. So no fold consumer touches the image leaf, and its 3-copy
  `as` + `Return type:`/`Return image as:` label drift stays a KNOWN defect, deferred with
  content/title/permalink to an FW row rather than fixed opportunistically mid-release.
  (Image-in-table is a separate capability question — markup emission — not a leaf-extraction one.) **Join rejoins the twin IN THIS RELEASE** — `$allow_same = false` reproduces its current
  enum byte-for-byte (behavioral no-op), and shipping the twin with one consumer leaves the exact
  drift it exists to kill.
- **Label defects carried in from Pass 5** — TWO, not the three first drafted (see §Pass 5 item 5.4):
  `Read` select label (`proto-fold-control.js:845`) → the 5.1 tag noun, not a string;
  `Same as previous slot` (`:667`, `:834`) → the shipped PAIR, one per axis: `Same as Previous
  Source` (`base-shared.php:316`) on the source select, `Same as Previous Field`
  (`class-tag-template-registry.php:521`) on the read select. Both already exist — nothing to invent,
  and the axis word is generic, so this does NOT couple to 5.1's tag-dependent label. NB combining
  containers omit the read-axis row until per-slot handlers exist (`base-tags.php:843`). **NOT in this list:** the field-combo
  `Meta/Option Field` string (`:878`) — WITHDRAWN, it is dead code (`dynamicLabel: true` means
  `props.label` is never read, `field-combo-control.js:671-682`); and `Default (intrinsic analog)`,
  which falls out of the anti-drift derivation rather than a labels decision.
  **Audit rule this produced: a label's spelling is only a defect if the label is RENDERED** —
  check the control's labeling mechanism before comparing string literals.

  **⇒ VERIFIED 2026-08-03 — ITEM 7 HAS NO DECISION CONTENT. It is a SYMPTOM LIST FOR ITEM 6.**
  Both defects are hand-authoring artifacts of the spike, and both are discharged by the leaf +
  twin derivation, not by choosing strings:
  - **`Read`** — the shipped read-select label is ALREADY tag-derived:
    `class-tag-template-registry.php:457` `$use_label = $tpl_options['use']['label']` → "Text Field" /
    "Image Field" / "Content Field", emitted as `%2$d: %1$s` (`:529`); join hardcodes the same shape
    (`'%d: Text Field'`, `base-tags.php:910`). **The noun lives ON the `use` definition — precisely
    what the item-6 leaf owns.** Consume the leaf and the tag noun arrives with it.
  - **`Same as previous slot`** — the source row arrives from `bws_build_slot_traversal_options`
    (`base-shared.php:316`), the read row from the new twin under `$allow_same`
    (`class-tag-template-registry.php:521`). The spike wrote ONE generic string because it derived
    NEITHER axis.

  Port the strings by hand and they re-drift on the next base change — which is how all four copies
  arose. **Do not treat these as separate build tasks.**

  **Stale pointers corrected:** `Read` label is `proto-fold-control.js:851` (not :845);
  `Same as previous slot` is `:673` + `:840` (not :667/:834); field-combo `Meta/Option Field` is
  `:884` (not :878). The `dynamicLabel` withdrawal re-verified: `props.label` is read ONLY in the
  non-dynamic branch (`field-combo-control.js:682`), and text/join both set `dynamicLabel => true`.

  ### ⚠ PHPDoc carries a WITHDRAWN reason — obligation on the twin (found 2026-08-03)

  `base-tags.php:842-847` justifies join's missing same-row **semantically**: *"in combining,
  same-use is redundant [use:key = the default] or pointless [use:title twice reads the identical
  datum]."* **This plan WITHDREW that argument** (§`use(same)` in combining containers, L2156-2187):
  `use(same)` IS legal in combining, and its usefulness there is *same field + same source, DIFFERENT
  HANDLER* — a decomposition, not a duplication. The omission survives for a different reason:
  **NOT BUILT until per-slot handlers exist** (§2185-2187), which is also why item 6's
  `$allow_same = false` for join is CONSISTENT with this section rather than contradicting it.

  Same behavior, superseded reason. Left as-is, the next reader re-derives the withdrawn degeneracy
  argument straight from the PHPDoc — **this file's signature failure mode, reproduced in shipped
  code comments.** ⇒ The twin's `$allow_same` PHPDoc MUST state the current reason (not-built-yet,
  handler-gated), and join's loop comment updated in the same edit.
  **Also stale:** this plan cites `base-tags.php:895-897` for the omission; actual is `:905-918`
  (loop) + `:842-847` (PHPDoc).
- **Slot-noun registration parameter (Pass 5.1)** — ONE noun per container, f(container); button
  `+ Add <noun>` and heading `<Noun> N` both derive from it (rule at §Pass 5.1). Decided:
  `try_*` = **attempt**, `{{join}}` = **field**; `{{table}}` deferred to `table-tag.md` #8.
  Replace the spike's generic `Slot N` heading (`proto-fold-control.js:573`) — an unregistered noun
  is a registration bug, so the fallback should not be a generic word. Do NOT hand-author a
  per-container read label alongside it (anti-drift).

  **⇒ VERIFIED + AMENDED 2026-08-03.** Framing holds exactly: under the fold each slot registers ONE
  option key whose PHP `label` IS the heading (`proto-fold-tag.php:283`, `sprintf('Slot %d (folded)',
  $n)`), and the JS generic is a pure fallback (`proto-fold-control.js:579`, `props.label ||
  __('Slot') + ' ' + key`) that fires only when registration omitted the noun. Stale pointer: heading
  is `:579`, not `:573` (that is the wrapping div).
  "attempt" has NO shipped user-facing use — three internal comments in `datetime-helpers.php` only.
  New noun, already user-reviewed at Pass 5.1.

  **V10's `N: ` label prefix does NOT collide — checked, not assumed.** Under the fold the flat
  `N-src`/`N-use`/`N-key` definitions disappear (one key per slot), so `'%d: Source'`
  (`base-shared.php:322`) has no consumer there. Safe because **the fold consumes only the
  `['options']` ROWS, never the outer label** (`proto-fold-tag.php:435`). ⇒ The twin keeps returning a
  full definition WITH its prefixed label for the legacy flat callers; the fold reads rows. `$n` in
  the twin therefore serves exactly two purposes: the same-row gate and the flat-context prefix.
  State this on the twin, or someone "cleans up" the prefix and breaks join's shipped registration.

  ### `{{table}}`'s noun — ownership KEPT, and the dynamic case is FEASIBLE (user, 2026-08-03)

  **Premise that broke:** this item deferred table's noun to `table-tag.md` #8, which was written
  BEFORE item 5's ship-coupling correction. Table now ships WITH the fold, so the noun can no longer
  be deferred past a release it is in.
  **User ruling: ownership STAYS with `table-tag.md` #8.** Build sequence is src-fold + slot-fold
  FIRST, then return to `{{table}}` to complete it — so #8 resolves inside the same release without
  this plan taking the decision. `column` is usable, with the caveat below.

  **User's question — can the noun be DYNAMIC on a future orientation option?** (transposed table ⇒
  the repeated unit renders as rows, so `column` would misname it.) **Yes, but NOT as a registration
  parameter, and that distinction is the finding:**
  - GB registers options **STATICALLY** (`proto-fold-tag.php:60-63` — a control can never mint an
    option key at runtime), so a registration-time string can NEVER respond to a per-instance option
    VALUE. A noun that depends on `orientation` is therefore **control-side, by construction.**
  - **The mechanism already ships:** `dynamicLabel` on `bws-field-combo` is exactly this pattern —
    `props.label` is used ONLY in the non-dynamic branch, otherwise the label is derived client-side
    (`field-combo-control.js:671-682`).
  - **The data is already in hand:** `ctx.state` is the WHOLE option map, so any control reads sibling
    values directly (`term-hop-control.js:37-41`, `state[key]`). A table control reading
    `state.orientation` is trivial.
  - **Constraint: the override must NOT be the sole source.** Static registration default + control
    override, mirroring `dynamicLabel`'s else-branch — otherwise an unset orientation yields NO noun.
  - Both `+ Add <noun>` and `<Noun> N` render in the control, so both inherit the dynamic behavior
    for free once the derivation exists.

  **⇒ Item 8's registration contract is UNCHANGED by table's future need**, which is what makes the
  deferral safe: the fold ships the static per-container parameter (`try_*` = attempt, `{{join}}` =
  field), and table later adds a control-side override WITHOUT touching registration.
- Wire echo (Pass 5.3): **CUT — do NOT port** (`proto-fold-control.js:906-907`). Goes with the
  spike; no doc owner needed, no CLAUDE.md row. The keeper adjacent to it is the advisory cue line
  (next item).

  **⇒ VERIFIED 2026-08-03. CUT confirmed.** Stale pointer: the echo is `:912-914`, not `:906-907`
  (`:907-909` is the FIELD group box, a keeper). It is self-labeled *"spike-only debug aid so the
  value rewrite is visible live"* — no argument for porting.
  **One interaction worth naming:** the echo's `recovered` branch (`[legacy → …]`) is the ONLY
  surface in the spike that makes a legacy→folded rewrite VISIBLE, and item 5's mount-time migration
  is otherwise silent. Cutting it is still right (debug chrome does not ship), but it means
  **mount-migration verification has NO editor-side signal** — it rides entirely on the item-5
  harness + testbed rows. Do not let "the echo showed it working" become the only evidence the
  migration fired.
- `inferIntent` advisory (Pass 5.2): document in the control's own **JSDoc file header** (shipped
  practice — `term-hop-control.js:1-19`, `as-size-control.js:1-25`; NOT PHPDoc, there is no PHP
  class for a JS control) stating it DESCRIBES and never GATES — the cut radio's residue, the
  property that must not regrow.

  **⇒ VERIFIED 2026-08-03 — NOTHING TO AUTHOR, only to CARRY FORWARD.** The statement ALREADY EXISTS
  in the spike's own file header: `proto-fold-control.js:18` — *"intent cell survives only as
  advisory text (inferIntent), never as a gate."* It is restated inline at `:510-511` (*"retained
  below only as editor-time ADVISORY text — it describes the slot, it no longer gates"*). So the
  obligation is: **do not DROP it during the port**, not compose it.
  Mechanism confirmed: `inferIntent()` at `:159`, consumed once at `:615-623` to pick one of three
  "Varies: …" strings plus an all-inherit fallback (*"Inherits both axes — this slot duplicates the
  previous one until you change something"*). Pure read of the wire; no state written, no visibility
  gated — the property the header asserts is actually true of the code, so the header is a
  description rather than an aspiration.
  Both cited precedents verified as real `/** … */` file headers stating the control's contract
  (`as-size-control.js:1-6`, `term-hop-control.js:1-5`).

## BUILD ORDER — the sequence, derived (2026-08-03)

> **Why this section exists.** User, 2026-08-03: *"I'm not seeing a specific build order sequence in
> the plan by that name."* Correct — it was ABSENT. **§Pass 6 is a CHECKLIST, not a sequence** (ten
> prep items, unordered relative to each other). The ordering below was real but existed only as
> SCATTER: four separate sections each stating one constraint. Collected here so a build session
> reads it once instead of re-deriving it.
>
> Appended at the END deliberately — inserting near the top would drift every §SETTLED anchor.

**Each step cites the section that CONSTRAINS its position. A step may move only if that section moves.**

| # | Step | Ordering constraint | Source |
|---|---|---|---|
| 1 | **Clamp helper** — 3 `limit` sites → 1, plus the `is_numeric()` guard | MUST precede the `limit` semantics change; `0` has to mean the same thing at all three sites before any of them changes meaning | §Blast radius |
| 2 | **`limit` semantics** — `0`/`-1` ⇒ unlimited, legacy `limit:0` honored as unlimited, regression matrix + visible fixture rows | Depends on 1. Ships its own CHANGELOG **Changed** + `readme.txt` Upgrade Notice (rendered output changes on existing sites) | §UNLIMITED encoding, §⚠ CORRECTED |
| 3 | **Text leaf** — `bws_get_text_field_options()`; base registration + modifier template both consume it (4 copies → 1) | MUST precede 4: the twin derives FROM the leaf | §THE PATTERN IS ALREADY SHIPPED |
| 4 | **Read twin** — `bws_build_slot_read_options( $n, $base_read, $allow_same )`; **join rejoins at `false`** (behavioral no-op) | Depends on 3. MUST precede 5: the control consumes the twin's `['options']` rows. Discharges item 7's two label defects for free | §Anti-drift obligation, §Label defects |
| 5 | **The fold** — wire + parser + control + migrator + mount migration | Depends on 4. Migrator shape now settled (split registration loops, two callbacks) | §Remaining migrator-shape TBDs |
| 6 | **`{{table}}`** — returns to `table-tag.md`; #8 (slot noun) resolves THERE | User, 2026-08-03: *"implement the src and slot fold, then return to the table tag to complete it."* Table SHIPS WITH the fold (item 5 ship-coupling) but is BUILT after it | §`{{table}}`'s noun, §Item 5 ship coupling |

**BUILD STATUS.** Step 1 BUILT (`0075e3c`, `bws_clamp_limit` extracted as the single interpreter).
Step 2 BUILT 2026-08-03 (1.17.0, unreleased): `0`/`-1` ⇒ unlimited with the `is_numeric()` guard,
all four call sites slicing `?: null` + the try_ term-hop early-break guarded on `$slot_max &&`,
harness rows inverted in `limit-clamp-test.php` / `try-join-seam-test.php`, new
`tools/test/limit-default-test-matrix.md` (19 rows, all verified live on the testbed front end)
with visible GB rows in the `core-structures` blueprint, CHANGELOG **Changed** + `readme.txt`
Upgrade Notice under 1.17.0.
Steps 3-4 BUILT 2026-08-03 (1.17.0, unreleased), as one refactor pair: text leaf
`bws_get_text_field_options()` + read twin `bws_build_slot_read_options( $n, $base_read, $allow_same )`,
both in `base-shared.php`. Text is 4 copies → 1 (base registration + modifier template + join now
consume the leaf; the fold will be the 4th consumer, not a 5th copy). Join REJOINED the twin at
`$allow_same = false` and its own read emit is gone; the twin's PHPDoc + join's PHPDoc/loop comment
now carry the CURRENT reason (per-slot handlers not built) instead of the withdrawn degeneracy
argument. `$use_label` retired from the registry — the "N: <noun>" label derives from the base `use`
definition, which discharges both item-7 label defects. 24 new harness cases in
`slot-options-build-test.php` (44 total). **No-op PROVEN, not asserted:** a scratch harness stubbed
GB's registrar and dumped the option definitions of all 35 registered tags (base + `term_*` + `try_*`
+ join) on HEAD and on the working tree — byte-identical JSON. No CHANGELOG entry (zero user-visible
delta). Steps 5-6 remain.

**Step 5 IN PROGRESS (started 2026-08-03).** Sub-steps, in dependency order — 5a is the only one
that can be built without a decision from the one before it:

| # | Sub-step | State |
|---|---|---|
| 5a | **PHP grammar owner** — `includes/helpers/slot-fold.php` (constants, bracket-aware splitters, chain steps, slot parse/emit, `bws_fold_from_legacy`) + graduated harness `tools/test/slot-fold-test.php` | ✅ BUILT — 191 cases green, 11 mutations all caught |
| 5b | **JS grammar twin** — `assets/js/slot-fold-grammar.js` + the cross-copy agreement harness | ✅ BUILT — 215 twin cases green, 15 mutations caught (see below) |
| 5c | **Control** — `assets/js/slot-fold-control.js` (repeater, per-step controls, field-picker bridges, container seed/noun, advisory) + the field-combo `scopeKey` prop + `tools/test/slot-fold-repeater-test.js` | ✅ BUILT — 35 cases green, 10 mutations caught |
| 5d | **Per-container FLIP** — registration + resolver together, one container at a time: folded `{N}` keys registered, container-aware resolution with ONE carry-forward accumulator that folded AND legacy slots both feed, dual-read for unmigrated wire | ✅ DONE — **`{{join}}` FLIPPED** (seam + registration + preview + enqueue; 8 mutations caught) and **all nine `try_*` tags FLIPPED** (three read shapes, both previews on the seam; 271/225/98/75/39 green, 8 more mutations caught). `{{table}}` arrives folded in step 6 |
| 5e | **Migrator** — split registration loops (base depth-0 / multislot L1) + the `apply_option_migration` no-op-halts-cascade ENGINE fix and its harness case | ✅ DONE — **multislot half only**, ten entries (join + nine try_), engine fix + `parse_tag_string` rtrim fix, `fold-migration-test.php` 38 green, 8 mutations caught. The base depth-0 half is NOT registered: nothing reads a chain off a base tag's wire yet (see §Flip decisions — migrator) |
| 5f | **Mount migration** — invisible control on the `serialization-order-normalizer.js` precedent | ✅ DONE — `assets/js/slot-fold-migrate.js` (JS twin of the pure migrate layer + the mount control), `legacyAxes` shipped on the fold config, digits-first canonical order in BOTH ports, `fold-migration-test.php` §M7 twin block (55 green) + 13 mutations caught. Two bugs found and fixed on the way: the control's hand-kept legacy key list, and legacy `src:current` deleting a slot (see §Flip decisions — mount) |
| 5h | **wire→steps COMPILE** — `bws_fold_chain_to_steps()` (`refs`→`ref`, `terms`→`srcTermIn`, `entries`→`rows`, per-step `limit`) + depth-0 `src:` chain parse + the two flat assemblers retired behind it; unblocks the base depth-0 migration entry 5e held | ✅ DONE — `includes/helpers/slot-fold-compile.php` (compile + BOTH assemblers, moved here; no JS twin by construction), per-hop cap in `bws_run_traversal`, factory + seam site-gate read the chain ROOT, new `tools/test/fold-chain-compile-test.php` (98), `traversal-pipeline-test.php`'s two inline assembler COPIES replaced by a require (127), 15 mutations caught, live equivalence on the testbed. **Two of its own predictions did NOT hold, both recorded as flips:** the base depth-0 migration stays held (blocker is ARM dispatch, now measured live) and the P13.5 slot skips do NOT flip, for the same reason — §Flip decisions — compile (5h) |
| 5i | **Slot KEY RE-SPELL — digits → CAPITALS** (added 2026-08-04, after the §RESOLVED — CAPITALS decision). Not a design step: one mechanical flip with a hard atomicity rule. (a) `bws_slot_ordinal()` / `bws_slot_ordinal_num()` + JS twins as the SINGLE OWNER; (b) the 4 write/read sites (`base-shared.php:460`, `class-tag-template-registry.php:561`, `slot-fold.php:835`, `slot-fold-migrate.php:195,205`) + the JS equivalents in `slot-fold-control.js` / `slot-fold-migrate.js`; (c) BOTH order parsers (`serialization-order.php:149` + `serialization-order-normalizer.js:98`) and the fold-LIVENESS test that gates which resolver runs; (d) the token move in `bws_join_wire_format()` + its preview twin + the Format control's help/placeholder, digit read RETAINED; (e) the nine option LABELS (`base-shared.php` ×6, `table-tags.php` ×3); (f) harness expectations across 7 suites; (g) docs (`tag-reference.md` §Folded slot wire + §Option order, `editor-tag-previews.md`, `CLAUDE.md` fold + FW-52 trigger rows, `fold-test-matrix.md` wires) + the visible fixture rows + CHANGELOG. **(a) BUILT** — `9741ec4`, additive, 60 round-trips agree across both languages, five fold harnesses still green. **(b)+(c)+(f) BUILT** — `e5667b5`, one commit per the atomicity rule below. (f) rode along because the tree cannot be green without it. Three things the flip TURNED OUT to need that the row above did not name: the order normalizer's enqueue **dependency inverted** (it now depends on the grammar — it decodes slot keys on every `setState`, so an absent decoder is silently wrong, whereas the grammar's `bwsReorderKeys` use is emit-time and degrades visibly); the folded key needed a **KEY_MAP entry** (`'' => ['source', -1]`) rather than just losing the `fold` dimension, so a half-migrated slot orders deterministically; and the **fixture blueprint's folded F-rows** were re-spelled here rather than in (g), because leaving them digit-spelled would have left the testbed rendering nothing between commits. An all-digit key now parses as an unknown flat key at slot 0 — **the digit read is NOT retained for KEYS** (it never shipped; two spellings would mean every consumer handles both, which is the drift the single owner removes). The digit fallback in (d) is a different surface: two token alphabets collapse to one internal `{N}` at a single translation point. **(d) BUILT** — `aebd7d2`. The token move is migration-free for authors as expected, but it turned up a MANDATORY migration nobody had scoped: a `%` before A–J used to pass through untouched, so `10%APR` was legal stored wire whose meaning changes once letters tokenize. `bws_migrate_join_format_escape()` handles it, **gated on wire ERA (no folded slot key = pre-letters), never on content** — literal-or-token is undecidable from the format string, and a content test destroys an INTENDED token, i.e. breaks working output instead of fixing broken output. That gate forces the entry to register BEFORE the fold entry (the fold ADDS folded keys). The first draft of the M8 harness block is what caught it: it escaped its own tokens. Scanner path only; no JS mount twin (one rendering change in rare literal text, not lost configuration). **(e) BUILT** — `5777ea3`, and narrower than the row claimed: the nine labels were miscounted. The `%d:` prefixes on `bws_build_slot_traversal_options()` and `{{table}}`'s "Column %d" label LEGACY flat keys, which stay digits, so only the fold's own per-slot label moved (`%d` → `%s`, "Slot B"/"Field B"). Table's labels revisit at step 6. **(g) BUILT** — `40f080f`. Two things worth carrying: the changelog's "slots lead the saved string" caveat was DELETED not corrected (the digit era never shipped, so there is no delta against 1.16.0), and F14.7 is now framed as the ordering REGRESSION check rather than a statement of fact. Fixture rows need a RESEED to appear. **5i COMPLETE.** | ✅ DONE |
| 5g | **Spike removal + graduation tail** — delete `tools/spike/*` and the guarded require, graduate `compaction-probe.js` into `tools/test/` as the first JS harness, matrices + visible fixture rows, preview-tool cleanup, docs/CHANGELOG | ✅ DONE — `tools/spike/*` + `slot-fold-roundtrip-spike.php` + the guarded require gone (the probe graduated back in 5c); new `tools/test/fold-test-matrix.md` (≈70 MEASURED rows across §F1–§F14) generated as visible GB blocks on three fixture pages; blueprint **v6** adds the ref-hop OBJECT return formats; the inexpressible-chain PREVIEW FLAG built (seam out-param, 8 mutations caught, 6 new preview cases + P13.5b); preview tool re-bannered; `deprecated-tags-options.md` fold vocabulary section; README + CHANGELOG + Upgrade Notice. **Five discoveries, three of them defects in this build's own earlier artifacts** — §Flip decisions — graduation (5g) |

**Sub-step ORDER is 5a…5f → 5h → 5g → 5i.** 5g is the graduation TAIL by definition (matrices, visible
fixture rows, preview tool, docs/CHANGELOG), so it runs after the last code sub-step — otherwise it
documents a state that is still moving. 5h keeps its letter rather than renumbering 5g, which is
referenced by name elsewhere in this plan; 5i takes the next free letter for the same reason.

**5i runs AFTER 5g and BEFORE step 6, and that ordering is not arbitrary.** It landed after the
graduation tail because the spelling was only reopened once 5g's own artifacts (the preview tool's
side-by-side view, plus the join format rows added for it) made the ordering cost visible. It runs
before `{{table}}` because table arrives folded — building it first means re-spelling its new code
too. It re-opens 5g's outputs by design: the matrix wires, the visible fixture rows and the docs all
state digit keys, so **5i owns a second pass over 5g's deliverables**, not just over code.

**5i's atomicity rule, which is the only thing in it that can go wrong quietly:** registration,
resolver, migrator and BOTH order parsers flip in ONE commit. Modes do not mix — a tag is folded iff
a slot key is present, so folded keys registered against a flat resolver render nothing, and a
liveness test still looking for all-digit keys would route migrated wire to the legacy arm. Legacy
`N-` SIBLING prefixes stay DIGITS throughout (`slot-fold-migrate.js:61`, `slot-fold-grammar.js:469`,
`serialization-order.php:146`'s `^(\d+)-`): that wire was already written with digits, so re-spelling
its reader would orphan every pre-1.17.0 tag. Only the FOLDED key moves.

#### Step 5 build decisions (5a)

Three closures the design left to the build, each recorded because a later reader would otherwise
re-derive it from code:

- **A legacy `limit` with NO fanning step stays SLOT-LEVEL** (`limit(4)` as an ordinary source-group
  token). §Item 5 decided `limit` folds to per-step because a slot-level limit has no defined
  meaning "once a chain fans more than once" — with ZERO fanning steps it has exactly one meaning
  (cap a multi-value READ), and there is no step to attach it to. So the mapper attaches to the LAST
  fanning step when one exists and otherwise leaves the token where it was. Lossless either way.
- **The FW-51 shape maps to NOTHING.** §The FLAG surface offered `N:src(same);use(same)` *or* drop
  for a selecting slot ≥2 whose only legacy content was a `key`. DROP is the output-preserving
  branch: the shipped resolver discards the key first, finds `$has_new` false, and `continue`s
  BEFORE the carry-forward — so the slot contributes nothing AND does not feed the accumulator.
  Materializing it would make it render.
- **`limit` `-1` normalizes to `0` at PARSE**, not only at emit. §UNLIMITED encoding says parse both,
  emit `0`; keeping `-1` in the struct would push the two-spelling tolerance into every consumer.

#### RESEQUENCED at build (5c/5d): registration flips WITH the resolver, not with the control

The sub-step list first had the control own registration of the folded `{N}` keys. **Wrong split**,
caught before building it: registering folded keys and resolving folded values are the SAME
behavioural change — modes do not mix (a tag is folded iff any `N:` key exists), so a container whose
options are folded while its resolver still reads flat keys renders nothing. The control JS is
therefore inert until a container is flipped (no option declares `bws-slot-fold` yet, and the script
is not enqueued — an unused editor script is a cost with no payer), and 5d flips registration +
resolver + enqueue together, ONE CONTAINER AT A TIME. That also keeps each commit's blast radius to
one tag family instead of three.

#### Control decisions (5c)

- **Config rides the OPTION DEFINITION.** GB passes an option's whole config to the
  `tagSpecificControls` filter (`cfg.label`, `cfg.help`, … — `field-combo-control.js:734-752` is the
  shipped precedent), so a `fold` sub-array on the definition carries the container class, seed
  parameters, ceiling/floor, noun, and every ENUM already derived from the shipped builders. No
  `wp_localize_script` channel, and no vocabulary in JS — which is what makes the anti-drift
  derivation hold at the control instead of stopping at PHP.
- **The field-combo scope handle is now a PROP** (`scopeKey`), with the outward `state.key` read kept
  only as the fallback for the shipped flat `{N}-key` registrations. This is P3's fix as specified —
  the defect was the REACH, not the spelling — and the degradation path at
  `field-combo-control.js:537-541` (unmatched repeater key → full pool rather than a stranded author)
  is untouched.
- **The struct is preserved whole through compaction.** The spike's slot held only `{chain, read}`,
  so a table column's `label`, its type token and its link cluster had nothing to be dropped from;
  under the shipped grammar they do, and materialization touches the two AXES only. Pinned by a
  harness row.
- **The advisory's all-inherit copy is container-aware.** In a combining container the seed leaves the
  read unset deliberately, so "inherits both axes" would be wrong — it says "pick a field for this
  slot", which is the actual next action.
- **Per-step `limit` has no control surface yet** (deferred to the `{{table}}` authoring pass, as
  decided). The wire round-trips it and every step edit threads it through, so a hand-edited or
  migrated limit is never silently dropped — pinned by a harness row.

#### The compaction probe, graduated (5c)

`tools/spike/compaction-probe.js` → `tools/test/slot-fold-repeater-test.js`, as a **node harness** per
the item-3 decision, with the two corrections that item flagged:

- **CONTAINER-PARAMETERIZED.** The probe asserted `src(same);use(same)` as *the* seed shape, which is
  the SELECTING seed; graduating it unchanged would have encoded a try_-only rule as a general
  invariant. Both seeds are now pinned side by side.
- **It uses the control's own export** (`window.bwsSlotFoldRepeater`) instead of the probe's
  regex-rewrite of the IIFE tail, so the harness cannot drift from the file it loads.

Two guards were only-apparently covered again, and mutation found both: the **position-1 `same`
strip** (materialization normally replaces the successor's inherit with a real value, so a residual
inherit reaches position 1 only when the REMOVED slot was itself inheriting — a hand-edited slot 1)
and the non-axis-option preservation above.

#### Flip decisions — `{{join}}` (5d)

The container flip needed five closures the design left open. All five are now code + harness;
recorded because each is the kind of thing a later reader re-derives from the resolver.

- **The RENDER SEAM is two functions, and the carry-forward lives in ONE of them.**
  `bws_fold_slot_struct( $n, $options, $container )` decides the ERA per slot (folded value ⇒
  parse; absent ⇒ `bws_fold_from_legacy`), and `bws_fold_slot_flat_options( $slot, $carry,
  $combining )` flattens a slot to the flat option set the shipped read consumes while threading
  the accumulator. Era is per SLOT, not per tag — that is what makes the mixed-era wire resolve,
  and P13.2 pins BOTH directions (folded slot inheriting from legacy, legacy inheriting from
  folded), which no spike fixture reached.
  **The preview walk collapsed into the same seam.** `bws_build_join_preview_label()` carried its
  own transcription of the skip rule and the carry-forward; it now calls the seam, so "the preview
  matches the callback" is a property rather than a claim. The copy had already drifted (it seeded
  `$last_src = 'current'` where the callback seeds `''`).
- **A chain the flat seam cannot express SKIPS the slot** — a second `refs` hop, a second `terms`
  hop, an `entries` step. The flat read holds one of each; resolving the expressible PREFIX would
  silently read a different source than the wire states. Unreachable through the control (join's
  `hops` capability list offers `srcTermIn` only), reachable by hand-edit, and the honest answer to
  a hand-edit is to render nothing. Pinned by P13.5.
- **An ARGLESS `refs` step keeps the carried `ref`.** Found by the migration-equivalence property,
  not by reading: shipped `$last_ref` survives every src override, so legacy `3-src:ref` with no
  `3-ref` hops through slot 1's relationship field. Blanking it (the obvious reading of "this step
  has no argument") changed output on a shape the shipped UI produces.
- **An empty chain at slot ≥2 is a RESET to the ambient entity, not an inherit.** Legacy absence
  MATERIALIZES to `src(same)` through the mapper, so absence in folded wire is unambiguous and
  means what it says. The control's comment claiming the renderer FLAGS that shape was aspirational
  — the renderer resolves it; flags stay an editor-preview concern (5g).
- **Folded keys are now KNOWN to the canonical order** (FW-52). `bws_serialization_order_parse_slot`
  gained an all-digit branch → `[N, '']`, with `'' => ['source', 0]` in the KEY_MAP, so a folded key
  ranks where the `src` it replaces did and sorts by slot. Left as "unknown" it would have tailed
  every stray key on the tag. **PHP + the JS port changed in one edit, and the port is no longer
  verified by inspection:** `serialization-order-test.php` grew a twin block that runs the shipping
  normalizer under `node` (mutation-checked — reverting the JS branch fails 4).
- **Registration derives, the control types nothing.** `bws_build_fold_slot_options()` (base-shared,
  beside the twins it consumes) builds the `fold` config: source rows THROUGH
  `bws_build_slot_traversal_options()` at slots 1 and 2 (so the `site` filter and the inherit row
  are the shipped ones), read rows through `bws_build_slot_read_options()`, picker configs off the
  base definitions. Two things it must author, because no shipped builder has them: **step-shaped
  hop labels** (`srcTermIn`'s own label is a checkbox question — "Get from taxonomy term?" — unusable
  in a step list; the row reads "In Taxonomy Term", parallel to the shipped "In Reference/Relational
  Field") and a **combining unset READ row**, because absent means UNCONFIGURED there and that is not
  what the first enum row means. A selecting container gets no such row — absent IS the stripped
  default, exactly as in the flat UI.
- **`hops` is a CAPABILITY list, not decoration.** Join offers the term hop only: a second
  relationship hop is FW-32 work, and offering a step the seam cannot flatten would author
  unrenderable wire from the UI.

**PHP coerces an all-digit array key to an int**, so `bws_get_join_options()` returns keys `1,2,3`
while `$options['2']` still resolves. Harmless, pinned in `slot-options-build-test.php` rather than
papered over — the WIRE spelling is the string, and a reader comparing `array_keys()` against
`array('1','2')` gets a false failure otherwise.

**Reveal predicates are GONE, not ported.** Cardinality is explicit in the repeater, so the
combining `show_if_any` chain (slot N ≥ 3 arms when slot N-1 has a key or a non-default use) had
nothing left to express. Registered keys run to the ceiling; the control renders only up to the live
count.

**Not done in these two commits, deliberately:** CHANGELOG + README (the fold is user-visible but
not finished — two containers of three, no migrator, no mount migration; a net-delta entry written
now would describe a state that never ships), matrices + visible fixture rows, and the testbed
integration pass for `{{join}}` and the nine `try_*` tags. All are 5g scope and none is a code
dependency of 5e/5f. The try_ flip adds two things 5g must cover that join did not: the
**key-only** and **chain-only** read shapes have no editor coverage yet (the field picker with no
mode select; a slot that is a source chain and nothing else), and the `srcTermIn` leak fix changes
output on legacy wire — `2-src` with slot 1 holding a term hop now reads the post, not the term.

#### Flip decisions — `try_*` (5d, selecting)

Nine tags, and the flip's real finding is that **a selecting container is not one thing.** The
`try_per_slot_use` / `try_per_slot_key` pair names THREE read shapes, and each answers "what does an
absent read mean" differently:

| Shape | Tags | Slot read UI | Wire |
|---|---|---|---|
| enum + picker | `try_text`, `try_content`, `try_image` | mode select + field picker | `use(title)` / `key(sku)` / `use(same)` |
| picker alone | `try_email`, `try_phone` | field picker only, no mode axis | `key(sku)`; an EMPTY picker is the inherit |
| no read | `try_title`, `try_permalink`, `try_datetime_single`, `try_datetime_range` | none — read is TAG-level | bare chain |

Every shape is read off the DERIVED config (`readRows` / `keyOption` present or not), never off the
container name — the same discipline the enums follow. A change tested only against `try_text` misses
two thirds of the family.

- **Slot 1 of a selecting container is NEVER absent.** Every axis unset there IS a configuration —
  ambient source, template default read — which is what a bare `{{try_title}}` renders today. The
  seam returned null for "no keys at all", which would have made the first attempt of an
  unconfigured try_ tag vanish; `bws_fold_slot_struct` now hands back an empty struct for slot 1 of
  a selecting container. Combining needs no exception: an empty struct has no read, and an absent
  read is unconfigured there, so the flattener skips it one step later anyway. Caught by writing the
  bare-tag case, not by review (P14.2/P14.4/P14.6).
- **`use(same)` is written only where an axis exists to show it.** The materialization is COSMETIC —
  an absent read and `use(same)` resolve identically in a selecting container — so a container with
  no per-slot `use` enum emits no read token. Otherwise the migrator would write `use(same)` onto
  `try_permalink`, naming an axis the tag has no control for, and the repeater would carry a token it
  cannot display or remove.
- **An explicit `use:key` with an EMPTY key at slot ≥2 maps to INHERIT.** That legacy shape BORROWED
  the carried key: the picker sat there empty and the slot read a field named in another slot —
  FW-51's ambiguity from the other side. `use(same)` reproduces it exactly whenever the carried read
  was itself key-moded, which is every shape the shipped UI could author (an analog-mode slot HIDES
  its key field, so a stale key behind one is not an editor-reachable state). Without this the
  equivalence property failed on `key:a | 2-src:site | 2-use:key` — a real output change.
- **`srcTermIn` no longer leaks between slots.** The flat loop set `$slot_opts = $eval_opts`, which
  carried the tag's BARE `srcTermIn` into every later slot's core call, so slot 1's taxonomy silently
  hopped slot 2 — contradicting the documented "srcTermIn does not carry forward". The flipped loop
  writes `srcTermIn` on every slot, `''` included, which closes it for mixed-era wire too (where the
  bare legacy key is still present in `$opts`).
- **The try_ preview walk collapsed into the seam too** — the FOURTH copy of the carry-forward rules,
  and it had drifted the same way join's had (its own `$use_defaults` map, its own skip test, its own
  `same`-normalization). It now walks `bws_fold_slot_struct` + `bws_fold_slot_flat_options`, and the
  17 pre-existing try_ preview cases passing unchanged is the equivalence evidence.
- **Reveal predicates are gone here as well, and `limit`/`sep` are UNCONDITIONAL.** Their
  `show_if_any` spanned every slot's `N-srcTermIn`/`N-src` — keys the fold removed. A list axis now
  lives INSIDE a slot value and `show_if` compares whole option values, so no honest predicate
  exists: `not_empty` on slot `1` fires for every configured slot, list axis or not. Two
  always-visible controls beat a condition that lies.
- **Slot noun = "slot".** Already the word try_'s own preview warnings use ("⚠ Try: slot 2 no key"),
  so the fold introduces no second vocabulary. (Join took "field", which is what a join slot IS.)

**One accepted divergence, output-identical:** a slot that inherits on BOTH axes (`src(same)` +
`use(same)`). The flat resolver SKIPPED it ("nothing new"); the seam resolves it as a duplicate of
its predecessor — which returns what the previous slot already returned, or is empty like it. It
matters because that shape is exactly what the repeater SEEDS a new slot with, so the seam must
resolve it rather than treat it as absent. Reachable in legacy wire only by hand-written `same`
sentinels (the shipped UI strips them as the slot ≥2 default). Pinned by P14.1.

**Two traps found by running the registration, not by reading it:**

- **`array_merge()` renumbers the folded slot keys.** PHP stores all-digit keys as INTEGERS, so
  merging the slot definitions into a leading option group slid `1`..`5` down to `0`..`4` — a slot 0
  the grammar has no ordinal for, and the top slot dropped. Join escaped it only because it assigns
  its tag-level keys one at a time. Now appended by key, with the rule in the CLAUDE.md trigger and
  a harness case that demonstrates the renumbering.
- **An EMPTY `keyOption` is truthy in JS.** `array()` reaches the control as `[]`, which passed the
  `!!conf.keyOption` test and rendered a label-less field picker for containers whose read is
  tag-level. The config now OMITS the key entirely when there is no per-slot key axis.

#### Flip decisions — migrator (5e)

**Only ONE of the two registration loops has a live target, and the split's own rationale is what
shows it.** Container-ness is known at registration because `match_tag` is an exact compare — so the
question "which containers exist?" gets asked at registration too, and the base answer is: **no base
tag reads a chain off the wire.** `bws_build_traversal_steps()` builds the chain from the flat
`src`/`ref`/`srcTermIn` keys; a depth-0 `src:refs,office` would parse as an unknown source token.
Registering `bws_migrate_src_chain_base` today would rewrite working tags into wire the renderer
cannot resolve. It lands with the FW-56 authoring surface, and the SHAPE decision (two loops, shared
helper, no runtime branch) is unchanged — it just has one instance for now.

**`{{table}}` needs no entry, ever.** It ships folded, so no stored table tag ever carried flat slot
keys. Asserted (M3.7) rather than left as an omission a later reader would read as a gap.

- **The tag-level / slot-level split is PER CONTAINER, and it excludes at EVERY slot position.**
  `limit` is the case that forces it: join registered `limit`/`N-limit` PER SLOT and threaded each into
  that slot's text resolve, while try_ has never registered one and reads a BARE `limit` as every
  slot's default cap. One shared list corrupts whichever container it does not describe — folding
  try_'s tag-level `limit` into slot 1 takes the cap away from slots 2+; excluding join's drops a
  configured cap. The same rule covers the chain-only try_ templates, whose `use`/`key` are TAG-level:
  folding a `{{try_datetime_single key:event_date}}` relocates a working option into a slot value
  nothing reads, and folding a DEAD `3-key` (the psk/psu gate ignores it) would make it live. Owner is
  `TagTemplateRegistry::try_slot_axes()`, and the try_ REGISTRATION now consumes its `slot_read`
  complement — so the two agree by construction, not by comment.
- **The equivalence property is BLIND to the strip.** §P13.1/§P14 assert that migrated wire resolves
  like the legacy wire it replaced; a migrator that leaves the legacy keys sitting beside the folded
  value passes, because the folded value wins in the dual-read. Mutation confirmed it (the strip
  mutation is caught ONLY by `fold-migration-test.php`). Recorded because "the equivalence suite is
  green" is exactly the reasoning that would have shipped a migration that never cleaned anything.
- **An already-folded slot WINS over its legacy siblings** — dropped, never merged. Same rule the
  render dual-read applies, and merging would invent a configuration neither side wrote. This is the
  half-applied-migration shape, so it is the one the migrator is most likely to meet twice.
- **Canonical ORDER on output.** The emitted option set is sorted through
  `bws_serialization_order_sort()`, so the converter writes what the editor's normalizer would write
  on next save. Without it every migrated tag shows a spurious diff the first time it is opened. Note
  the shape this produces: a tag-level source-group key is slot 0, so `limit`/`sep` LEAD the folded
  slots.
- **`bws_fold_migrate_slots()` is pure (options in, options out)**, with the wire adapter above it.
  That is what lets the harness drive it with arrays, and what lets §P13.1/§P14 drive the SHIPPED
  migrator instead of a transcription — the two `t_migrate_*` prototypes are gone.

**Two defects the smoke found, both pre-existing and both widened by this entry:**

- **A no-op entry HALTED the cascade** (the hazard §item 5 predicted, with a confirmed live trigger).
  `{{try_image src:ref|ref:office|size:large|as:url}}` hits the as+size entry, which matches on `as`,
  survives its own transform, and returned "no change" — ending the loop before the fold entry, and
  before the four other `image` entries. Fixed in the ENGINE: walk every matching entry, take the
  first that CHANGES the string, re-derive matches, repeat. Iteration cap raised 16 → 32 because a
  chain is now bounded by entries-per-tag rather than by distinct firing entries.
- **`MigrationRegistry::parse_tag_string()` rtrimmed the LAST option's value.** GB does not —
  `parse_options()` (2.3.0) splits on `|` then `:` and stores the remainder verbatim — so an authored
  `sep:, ` renders as ", " and the converter turned it into ",". Harmless while option entries were
  narrow single-key renames; the fold entry matches nearly every join/try_ tag, which made it
  reachable at scale. Both `trim()`s are now `ltrim()`.

#### Flip decisions — mount (5f)

The second migration path, built as the twin of the first: `assets/js/slot-fold-migrate.js` holds the
pure layer (`slotAxes` / `legacyKeys` / `slotState` / `migrateSlots`, mirroring
`bws_fold_migrate_slots`) plus the invisible control that applies it, and
`tools/test/fold-migration-test.php` §M7 runs ONE shared corpus
(`fold-migration-corpus.json`, 14 cases) through both sides via `fold-migration-driver.js`. A missing
`node` FAILS. Inputs only, no expected values — the behavioural expectations stay in the PHP blocks
above it, exactly as `slot-fold-corpus.json` relates to `slot-fold-test.php`.

- **The mount migrator knows NO tag names.** The converter matches by tag because
  `MigrationRegistry` does; the editor reads the `fold` config GB hands to the filter, so container
  parameters arrive DERIVED from registration. Nothing in the JS file knows that `try_text` exists.
- **It anchors on the first FOLDED slot key**, not on the tag's first option (the FW-52 normalizer's
  anchor). That anchor IS the "is this tag folded" gate — modes do not mix — and slot 1 always
  renders, whereas a leading option can be hidden by a conditional filter.
- **Both invisible controls now use FUNCTION UPDATERS.** Two whole-object `setState` writes land on
  one tag in the same React batch once the fold ships, and a plain-object write discards whatever the
  other one just did. Composing off `prev` loses neither; returning `prev` unchanged is the loop
  guard (React bails on an identical reference), which is also what the old early-return did.

**⚠ DIGITS-FIRST IS AN ENVIRONMENT FACT, and the 5d rank was wrong.** An all-digit key is a JS
array-index property, which ECMAScript enumerates BEFORE every string key regardless of insertion
order; GB serializes the tag string with `Object.entries(extraTagParams)` (verified in
`dist/blocks/text/index.js`). So the editor cannot emit a named option ahead of a folded slot, and
neither the JS normalizer nor `bws_serialization_order_sort` can change that by rebuilding the object.
5d had ranked folded keys INSIDE the source group, which meant a tag-level `limit` led the slots in
the converter's output and trailed them in the editor's — the same tag stored two ways, and a spurious
diff on first open. Fixed by stating it: a leading `fold` dimension in both ports, the `''` KEY_MAP
entry deleted (one mechanism, not a rank a second mechanism overrides). Three harness expectations
and one migrator expectation flipped with it.

**⇒ 5g PROSE OBLIGATION.** 1.16.0's shipped Changed entry states *"the return type or format leads"*
the saved string. That stays true for every tag without folded slots, but a folded tag now leads with
its slots, so the fold's own CHANGELOG copy has to say so — otherwise a reader holds a released
statement that the new wire contradicts. Zero net delta for pre-1.17.0 wire (no such tag has an
all-digit key), so this is one sentence in the fold's entry, not a Changed entry of its own.

**Two bugs found by the 5f smoke, both pre-existing:**

- **The control hand-kept its legacy key list** (`legacyKeys()`, the six axes literally), so
  committing ANY slot deleted them — including a bare `limit`, which on every `try_*` template is the
  TAG-level cap, and a bare `key`, which on `try_datetime_*` is that tag's own field option. The same
  unfiltered state also went to `foldFromLegacy`, which folded a tag-level `key` into slot 1 as that
  slot's read. Fixed by shipping the surface: `bws_fold_slot_legacy_axes()` is the single owner of the
  tag-level subtraction, `bws_build_fold_slot_options()` ships the complement as `legacyAxes`, and
  both registration callers pass their `tag_level` (join's is empty — `limit` IS a join slot axis).
  Live check on the testbed: all ten containers register the three expected shapes.
- **An explicit legacy `src:current` DELETED the slot.** `bws_fold_from_legacy` mapped `current` to no
  step at all, on the reasoning that an empty chain already resolves against the ambient entity. True
  for a slot with another axis — but on a container with NO per-slot read axis (`try_permalink`,
  `try_title`, `try_datetime_*`) a fallback attempt's ENTIRE content can be `{N}-src:current`, and
  then the struct comes out empty, `emitSlot` returns `''`, and the slot key is never written. Verified
  three ways with `bws render-tag`: the legacy wire renders, `{N}:src(current)` renders, the migrated
  output rendered NOTHING. `current` is now a step like any other source value (PHP + JS twin, one
  edit). **Why every equivalence case missed it:** all four `none`-shape cases carried a second axis,
  so the empty-struct path was never reached — the gap was in the corpus, not in the property.

#### Flip decisions — compile (5h)

`includes/helpers/slot-fold-compile.php` is the missing middle: one compile with two thin adapters
over it — `bws_field_values_assemble_steps()` (the seam's, MOVED here from `field-helpers.php`) and
`bws_wrapper_ref_steps()` (the post-semantic wrapper's, MOVED from `base-shared.php`).
`tools/test/fold-chain-compile-test.php` is the new harness (98 cases); the two inline COPIES of those
assemblers in `traversal-pipeline-test.php` are gone, replaced by a require of the real file — they
were copies of functions that have now changed, i.e. the drift the newer-harness rule prevents.

**NO JS TWIN, by construction.** Every other fold file has one because the editor parses and rewrites
the same wire. It never RUNS a chain — engine step types are render-side vocabulary — so this half is
PHP-only, and the header says so rather than leaving a reader hunting for the mirror.

- **The ROOT is not a step.** A chain's step 0 is either an entity ROOT the source FACTORY consumes
  (`site`, `current`, a registry source) or already a hop off the ambient entity. So the compile
  SPLITS them — `bws_fold_chain_root()` for the factory, `bws_fold_chain_to_steps()` for the engine —
  instead of teaching the factory to understand chains. DECISION 3's singular/plural disjointness is
  what makes the split decidable from the slug alone, which is the first time that rule pays rent.
- **EQUIVALENCE is the property, not the new capability.** Two assemblers on paths every tag renders
  through were replaced, so the harness pins that every legacy option shape compiles to the
  byte-identical step array and resolves to the byte-identical BASE SOURCE. A chain that hops twice is
  worth nothing if `src:ref|ref:x` moved a millimetre. Three rules exist only to hold that line: an
  ARGLESS fanning step is dropped (legacy `src:ref` with no `ref` emitted no step and read the ambient
  entity — a field-less `ref` step would short-circuit the fold to empty, changing what a stored
  garbage wire renders); `limit` rides a step only when it CAPS (absent is how the engine spells
  no-cap, and `0`/`-1` mean unlimited); and #44's compound order comes from WIRE order rather than
  being re-imposed per assembler.
- **`src:ref` is the ONE token that changes, and the factory is what proves it inert.** Its root reads
  `''` now, because `ref` is a hop. The old factory already excluded it from the registry lookup
  (`'' !== $src && 'ref' !== $src`) precisely because a ref hop bases on the ambient entity, so both
  spellings take the same branch — asserted against the real `bws_resolve_base_source()` with injected
  signals (§C2c), not left as an argument.
- **An unknown hop slug compiles to an unknown engine TYPE, never to nothing.** The engine answers an
  unknown type with an empty list, so the chain short-circuits and the tag renders nothing. Dropping
  the step would read a DIFFERENT source than the wire states — the same principle behind the slot
  skip. This also covers a ROOT slug appearing at a hop position (`refs,office;site`).
- **The wrapper takes the chain's LEADING RUN of `ref` steps, and STOPS rather than filters.** §V13/B2
  still holds (a term hop is the callers' job on the returned post id), and a multi-ref chain now hops
  every step instead of the first. Filtering past a term/rows hop would run the later ref steps
  against the wrong entity.
- **A per-hop cap is applied to the step's WHOLE output**, in `bws_run_traversal`, not per input
  source: that is the quantity the wire names ("at most N of these"), and it is what the legacy flat
  `limit` sliced when the fan-out was the last step. The TERMINAL `limit` option stays the caller's
  value-list cap — two different quantities, verified live: `src:refs,related_staff,limit(1)` with
  `limit:0` renders ONE address, `limit(2)` renders both.
- **A legacy `srcTermIn` beside chain wire APPENDS a hop; a chain that already hops terms WINS.** It
  is a separate option KEY describing a hop, so dropping it silently loses a configured hop — but
  appending twice would double-hop. Only reachable by hand-edit today, and it is the shape the base
  depth-0 migration will produce mid-flight.

**⚠ BASE DEPTH-0 MIGRATION IS STILL HELD — its blocker was only HALF what 5e recorded, and the second
half is now MEASURED, not surveyed.** 5e said no base tag reads a chain off the wire; that is now
false (the seam compiles one, and `bws render-tag` confirms `src:refs,related_staff` renders what
`src:ref|ref:related_staff` renders, on `{{email}}`, `{{phone}}` and `{{text}}`'s keyed read). The
second blocker: **~10 callback sites read `$options['srcTermIn']` and ~6 compare `$options['src']` to
`'ref'`/`'site'` to pick an ARM.** Two live rows on `matrix-*`:

| wire | renders | why |
|---|---|---|
| `{{text src:ref\|ref:related_staff\|key:name_last\|limit:0}}` | `Johnson, Smith` | `$is_ref` true ⇒ list mode offered |
| `{{text src:refs,related_staff\|key:name_last\|limit:0}}` | `Johnson` | same source, list mode NOT offered |
| `{{text srcTermIn:department\|use:title\|limit:0}}` | `Sales, Support` | term-hop arm |
| `{{text src:terms,department\|use:title\|limit:0}}` | `Matrix: Terms (all valid)` | arm not taken ⇒ the PAGE title |

The second pair is the important one: it does not render nothing, it renders a DIFFERENT value. So
migrating a base tag's `src`/`srcTermIn` today would silently change WHICH ARM renders it, not merely
which steps run. **No control and no migration writes a base chain**, so this is reachable only by
hand-authoring — accepted, unguarded, and deliberately not papered over with a "chain-shaped src
refuses to render" hack, which would pre-empt the design of the fix. It lands when those arms dispatch
on the chain's TERMINAL STEP KIND — the verb-agnostic resolver refactor already tracked as a separate
unfiled item — the same prerequisite as the row below.

**⚠ THE 5d SLOT SKIP DOES NOT FLIP, contrary to the §MISSING MIDDLE row.** That row predicted P13.5's
inexpressible-chain cases would become RESOLVE cases at 5h. They do at DEPTH 0; they do NOT in a
SLOT, for the same reason the base migration is held: a slot's output is produced by container arms
that gate on the flat `src`/`srcTermIn` tokens `bws_fold_slot_flat_options()` returns, and handing
them the nearest token IS the truncated prefix the 5d rule rejected. The compiler gave the ENGINE
arbitrary hops; it cannot give a slot an arm to dispatch to. Recorded as a flip of the PREDICTION, not
of the rule — the rule was right, its stated CAUSE was incomplete.

**A harness SHIM was wrong, and only a capitalized fixture found it.** Both this harness and
`traversal-pipeline-test.php` shimmed `sanitize_key` as `strtolower( preg_replace(...) )` — stripping
BEFORE lowercasing, which deletes every capital letter (`My Tax!` → `yax`, not `mytax`). Latent in the
traversal harness since 1.14.0 because every taxonomy slug in it was already lowercase. Fixed in both.
The lesson is the fixture, not the shim: a sanitizer case whose input is already sanitary asserts
nothing.

#### The cross-copy obligation, discharged (5b)

Item 4's "name ONE owner and carry a cross-copy assertion for every copy it cannot eliminate" is now
a HARNESS, not a discipline: `tools/test/slot-fold-twin-test.php` runs ONE shared input corpus
(`slot-fold-corpus.json`) through two thin drivers that load the SHIPPING files — the PHP owner and
`assets/js/slot-fold-grammar.js` — and diffs canonicalized results plus the grammar CONSTANTS field
by field. **Inputs only, no expected values**: expectations stay in `slot-fold-test.php`, so widening
twin coverage costs one corpus line. Scope is agreement, not correctness (both sides wrong the same
way passes here and fails there — both harnesses are required).

Three things this pass settled that the checklist left implicit:

- **`node` is now a test dependency, and a missing `node` FAILS.** The convention change item 3
  anticipated for the compaction probe lands here first. Skipping would hide precisely the drift the
  file exists to catch.
- **The JS emitter REUSES `window.bwsReorderKeys`** (the FW-52 normalizer) for canonical token order
  instead of carrying a second JS KEY_MAP. The twin harness exercises that reuse — with the
  dependency stubbed out, emit falls back to insertion order and the diff against PHP shows it.
- **A parse-only corpus cannot see the falsy-`limit` bug class.** Parse normalizes every value to a
  string, and `'0'` is truthy in JS — so a `if (step.limit)` guard passes a parse-driven corpus while
  dropping a control-side NUMERIC `0` (author-pinned unlimited). Fixed by an `emitStructs` corpus
  section that enters from a struct, with numbers. **This is the fourth instance of the falsy-0
  collision on this axis** (`''` vs `null` for `arg`; positional `,,`; the `control.js:135` truthy
  guard) — the pattern is now covered mechanically rather than remembered.

**Method note carried out, not just cited.** Every guard in 5a was verified by MUTATION (break the
property, confirm the suite fails), per item 0's rule. Two guards were only-apparently covered until
mutation showed otherwise: the bracket-aware STEP splitter (nothing exercised a step token with a
separator inside brackets — `limit[5]` is safe under a naive split by accident, a bare integer
cannot contain one) and the depth-return guard (its existing fixture was caught by a different check
first; the case only it catches is `key(a)x(b)`, where both halves are individually balanced).

**Steps 1-2 are separable from 3-6** — they share no code and can land as their own release. Steps
3-4 are a refactor pair with no user-visible change. Only step 5 needs the grammar.

**Not in the sequence — build TASKS that attach to a step rather than ordering it:**
- ~~`apply_option_migration` no-op-halts-cascade ENGINE fix + its harness case~~ → ✅ DONE in 5e (as+size was the live case stranding it)
- ~~Compaction-probe seed container-parameterization~~ → ✅ DONE at graduation in 5c (four configs)
- ~~Preview-tool cleanup~~ → ✅ DONE in 5g (re-bannered SHIPPED; page kept as the exploration surface)
- `sep` + the 5 datetime keys under the fold → DEFERRED scope, not this release

**Freeze note.** Steps 3-4 change option DEFINITIONS while step 5 changes the WIRE. Do not interleave
them: a definition change mid-wire-migration makes a failing fixture ambiguous between the two.

#### Flip decisions — graduation tail (5g)

The tail was mostly mechanical, and the mechanical parts are not recorded here. What IS recorded: five
things the tail DISCOVERED, three of them defects in artifacts written earlier in this same build.

- **The seam now REPORTS WHY it skipped** (`$skip_reason` out-param on
  `bws_fold_slot_flat_options()`: `''` / `'read'` / `'chain'`). 5d closed "an inexpressible chain SKIPS
  the slot"; what it left open is what the AUTHOR sees, and the answer had been silence. A silently
  omitted slot reads as a tag with one slot fewer than was configured. So `'chain'` is FLAGGED
  (`slot N source not supported`, both previews) and `'read'` stays SILENT — an unconfigured slot is a
  normal in-progress state, and flagging it would fire on every half-built join. **The reason is
  reported BY THE OWNER, never derived in the preview**, because deriving it is a second copy of the
  skip rule, i.e. the exact drift collapsing the preview walks into the seam removed. The reset at
  function entry is CONTRACT, not decoration: the param is by reference, so a caller may reuse one
  variable across a walk, and a reason written only on a skip leaks the previous slot's answer.
  Mutation found that the reset was untested (every caller happens to re-init) — pinned at P13.5b.
- **A SKIP is INDISTINGUISHABLE from an empty read in rendered output.** Both print nothing, so front-end
  output cannot be the evidence for a skip. The matrix now carries a NEGATIVE CONTROL
  (`src(refs,x;terms,y)` IS expressible, resolves, finds nothing) beside the two real skips, and all
  three print `Captain`. This also killed a vacuous row: the first draft claimed the ref+term chain AS
  the skip case — wrong, because the flat triple holds one hop of EACH axis. Inexpressible means a
  second hop on ONE axis, or `entries`. The preview harness caught it; no render row could have.
- **The legacy↔folded pairs CROSS on the source axis, and the first draft of the matrix mis-paired
  them.** The underlying rule was settled at 5d (legacy absence = inherit; folded absence = RESET), but
  its consequence for PAIRING was never written down: folded `N:key(x)` twins legacy
  `N-src:current|N-key:x`, and folded `N:src(same);key(x)` twins bare legacy `N-key:x`. Caught by
  eyeballing the visible fixture rows — both spellings resolve correctly, so only the PAIRING claim was
  wrong, which no pure harness can see. This is the strongest argument yet for the visible-rows rule.
- **The ref-hop OBJECT return formats had never been exercised against real ACF** (user, mid-5g). The
  reader type-guards `relationship|post_object` and the coercer has `WP_Post` arms, but every fixture
  field returned an ID, so those arms were asserted only against a test SHIM's guess at ACF's shape —
  the same class of hole as the inverted `sanitize_key` shim found in 5h. Blueprint v6 adds
  `related_staff_obj` (relationship + `object` ⇒ `WP_Post[]`) and `lead_staff_obj` (post_object +
  `object` ⇒ ONE `WP_Post`, the only shape reaching the reader's non-array wrap), both carrying the SAME
  targets so the rows are equivalences with no expected values. A non-vacuity check is written into the
  matrix, because a format case whose fixture quietly returns ids asserts nothing.
- **`{{join}}`'s `fallback_text` → `fallback` rename (1.16.0, FW-50) never reached its matrix or its
  fixture row.** Five `join-test-matrix.md` rows and the visible J3 row still spelled the dead key, so
  they rendered EMPTY while claiming `—`, and had done since 1.16.0. Found by probing for the fold's own
  fallback rows. The renderer ignores the dead key; the PREVIEW still tolerates it
  (`fallback ?? fallback_text`), so a hand-authored `fallback_text` previews a fallback that will not
  apply — noted on the FW-50 lane, not changed here.

Two fixture-authoring rules fell out, both now in the blueprint:

- **An expected-EMPTY row needs its label in its OWN block** (`bws_fixture_gb_empty_row`). GB hides a
  text block whose dynamic tag resolves to nothing, and it hides the WHOLE block — so a one-block row
  takes its own static label with it and the case reads as MISSING FIXTURE. A row that empties
  UNEXPECTEDLY still vanishes, which is the signal.
- **A `{{…}}` inside a row LABEL is live wire.** The F9.4 label mentioned `{{table}}` by spelling; GB
  rendered it, the empty table emptied the paragraph, and the label disappeared. Name tags in prose.

**Placement decision (fixtures + matrix).** Fold rows are a WIRE-FORM axis, not a tag family, so
Catalog order has no slot of its own for them. They sit as one cluster BETWEEN the join group and the
table group: the containers are join and `try_` (both multislot) and `{{table}}` arrives folded, so
"multislot wire" reads correctly exactly there. `try_` gets no group of its own elsewhere.

**One stale doc contradiction fixed, from 5d's own entry.** `tag-reference.md` §Folded slot wire still
said a folded key "ranks where the `src` it replaces did — source group, leading". 5f superseded that
(digits-first is an environment fact; the `''` KEY_MAP entry was deleted), and the index row was
flipped then, but the doc paragraph was not. §Option order now carries a `fold` group at rank 0 with
the qualification that "format leads" still holds on a tag without folded slots.

## REOPENED — slot KEY spelling (2026-08-04, user)

**Reopened on a correct reading of the 5f finding.** "Folded keys lead the serialized string" was
recorded as an environment FACT, and it is one — but it is a consequence of the key being an
**array index**, not of folding. `1:` is a canonical numeric string, so ECMAScript enumerates it
ahead of every string key and GB's `Object.entries()` serializer prints it first. Change the
spelling to anything that is not an array index and the constraint evaporates: rank returns to
`bws_serialization_order_sort()` and its JS port, and `format` can lead as §Option order intends.
The 5f row is therefore CONDITIONAL, not absolute, and it was written as if the spelling were fixed.

**Leading candidate: CAPITAL LETTERS (`A:`, `B:`, …).** User's proposal, "unless blocked".

### Not blocked — verified 2026-08-04, not argued

| Check | Result |
|---|---|
| GB PHP `parse_options( 'A:key(x)\|format:%1 (%2)\|B:key(y)' )` | keys `A`, `format`, `B` preserved VERBATIM and in wire order. The parser splits on `\|` then `:` and does `$result[$key] = $value` — no `sanitize_key`, no `strtolower`, no key allowlist |
| JS enumeration | `Object.entries({format,B,A})` → `format B A`, i.e. INSERTION order. Contrast the same object with digit keys → `1 2 format` regardless of insertion. So with letters, the normalizer decides rank |
| Our own parsers | the only `sanitize_key` in the fold code is on a taxonomy slug (a VALUE). `MigrationRegistry::parse_tag_string()` does not touch key case either |
| GB reserved keys | all lowercase words (`source`, `link`, `tax`, `id`, …). Single capitals cannot collide |
| Grammar chars | a capital is not in any separator/bracket/reserved class, and the option-KEY position has no character restriction beyond GB's `\|`/`:`/`}` |

**Still needs the user's editor test, and it is the only real unknown:** the tag-modal ROUND TRIP
(wire → React state → re-serialize on commit). The serializer is confirmed
`Object.entries`, and our normalizer rebuilds the object inside `setState`, so the mechanism is ours
— what nobody has watched yet is whether a capital key survives a reopen-and-commit cycle unchanged.
Watch for: a spurious diff on first open, a key silently lowercased on save, and the invisible
migration control firing on a tag it should now consider already-migrated.

### The candidates

| | `1:` (shipped) | `A:` (candidate) | `s1:` / `slot1:` |
|---|---|---|---|
| Serialization rank | PINNED first, unmovable | ours | ours |
| Wire | `{{join 1:key(a)\|2:key(b)}}` | `{{join A:key(a)\|B:key(b)}}` | `{{join s1:key(a)\|s2:key(b)}}` |
| Slot ordinal legibility | direct | a lookup past ~C, and slot 10 is `J` | direct |
| Correspondence with `%N` template tokens | exact (`%2` ↔ slot `2`) | ~~BROKEN~~ → **exact, tokens move too** (`%B` ↔ slot `B`) — user 2026-08-04, see §Candidate elimination below | exact |
| Sort among themselves | numeric ascending, free | both order parsers DECODE to an int and sort numerically, so raw-string comparison never matters (ASCII would only have held to `Z` anyway) | needs the `\d+` parse to allow a prefix — and the WORD form `slot1`…`slot10` MIS-SORTS (`slot1, slot10, slot2`), i.e. it needs a numeric-aware comparator in PHP *and* the JS port |
| Work | none | ~8 centralized sites + harness expectations + docs | same |
| Migration debt | none | none IF pre-release | none IF pre-release |

**⚠ SUPERSEDED 2026-08-04 (user) — the tokens move with the keys.** Kept as the reasoning that got
tested. The resolution and its costs are in §Candidate elimination + token decision below; the short
version is that `%N` was only ever the digit alphabet, so re-spelling it with the keys leaves ONE
alphabet rather than two, and keeping digits readable as a fallback makes that move migration-free.

~~**The `%N` correspondence is the strongest argument against letters**~~, and it is not cosmetic:
`{{join}}`'s template mode addresses slots as `%1`…`%10` on the wire (brace tokens are impossible —
GB's parser rejects `}` inside options), and `{{table}}` addresses columns by number. Under `A:`,
a template-mode join reads `format:%1 (%2)` beside `A:`/`B:`, so the author holds two ordinal
alphabets for one set of slots. Either the template tokens change too (a second, larger wire change,
and `%A` is not obviously better) or the mismatch is accepted as the price of the ordering.

**Sites the spelling touches** (all centralized — this is why the change is cheap):
`bws_build_fold_slot_options`/registration (`base-shared.php:460`),
`TagTemplateRegistry` slot registration (`:561`), the render seam's key read (`slot-fold.php:835`),
the migrator's read+write (`slot-fold-migrate.php:195,205`), the canonical-order slot parse
(`serialization-order.php:202` + its JS port `serialization-order-normalizer.js:96-99`), the
control's ordinal read (`slot-fold-control.js:389`), plus the JS migrate twin, harness expectations
and the docs that state digits-first (`tag-reference.md` §Folded slot wire + §Option order,
`editor-tag-previews.md`, `CLAUDE.md`'s FW-52 and fold trigger rows, and this plan's 5f rows).

**DEADLINE — this is the cheap moment and the only one.** 1.17.0 is unreleased, so no stored wire
exists outside the testbed and a spelling change costs zero migration. After release it needs a
migration entry on BOTH paths (converter + mount) plus a dual-read era, i.e. the same machinery the
fold itself just built, for a cosmetic gain. Decide before ship.

### ✅ RESOLVED 2026-08-04 (user) — CAPITALS (`A:`…`J:`)

> *"I find the capital prefix slightly less readable than the digit prefix for the folded slots,
> though actually more readable as format tokens. The cost of losing the format-first ordering AND
> the `{{table}}` tag-level options-first ordering is too high for the digits. As a consolation, the
> folded syntax with capitals is still more readable than the previous unfolded syntax with digits."*

**Decided on ORDERING, not on prefix legibility** — and the two axes were weighed separately, which is
why the row reads the way it does:

- Slot KEYS: capitals are **slightly WORSE** to read than digits. Conceded, not argued away.
- Format TOKENS: capitals are **better** (`%A` beside `A:`, one alphabet).
- Ordering: decisive. Digits forfeit `format`-first on join/`try_` **and** tag-level-first on
  `{{table}}` (`src:`/`caption:` forced behind the columns). That is a permanent, unfixable cost —
  no sort can move an array-index key — traded against a small legibility loss.
- Baseline that makes the trade affordable: folded-with-capitals still reads better than the
  UNFOLDED-with-digits wire it replaces, so the comparison is not capitals-vs-status-quo.

Implementation follows §Candidate elimination below (tokens move with the keys, digit read retained
permanently). Single owner: `bws_slot_ordinal( $n )` + its JS twin for keys, labels and tokens alike.

#### The letter class is `A`–`Z`, NOT `A`–`J` — the CAP IS A CONTAINER PROPERTY (user 2026-08-04)

> *"Don't lock it to A-J. We haven't decided a cap for all time, so it should be A-Z. This effectively
> limits us to 26 slots, but I think that'll never be a practical issue."*

`A`–`J` was leaking today's `BWS_JOIN_MAX_SLOTS` (10) into the GRAMMAR, which is the wrong layer: the
cap belongs to each container (join 10, `try_` 5, `{{table}}` its own), and a grammar that encodes one
container's limit has to be re-cut every time a container changes. Consequences worth stating because
they are not obvious:

- **Validator/parser match `^[A-Z]+$`, not `^[A-Z]$`.** The encoder is a spreadsheet ordinal
  (27 → `AA`), so a single-letter validator could REJECT wire its own encoder produced. Matching the
  general form means encoder and validator cannot disagree, and no cap lives in the grammar at all —
  26 is simply where the practical cap sits. Author-facing this is invisible: nothing reaches `Z`.
- **No collision risk.** Every option key in the plugin (and every GB reserved key) is lowercase or
  lowercase-initial camelCase, so an all-caps key cannot be anything but a slot.
- **The "ASCII sort equals slot order" bonus is IRRELEVANT, and that is fine.** It only held to `Z`
  (`AA` < `B` as strings). Both order parsers DECODE the key to an int and sort numerically
  (`serialization-order.php:149` already does this for digits), so nothing depends on the raw string
  comparing correctly. Recorded because the bonus was cited when capitals beat `slot1:` — the real
  reason `slot1:` lost is that it needs a numeric-aware comparator, which decoding gives us for free.
- **The `%%` escape surface follows the CONTAINER, not the letter class.** The tokenizer rewrites
  `%A`…`%<max>` for that container's slot count, so `%K` is literal in a 10-slot join. Help text
  therefore says "before a slot letter", never an enumerated range.

### Candidate elimination + token decision (2026-08-04, user)

**User's criteria for a non-digit spelling**, stated when asking for alternatives to capitals: it must
escape the array-index trap, it must **connect with something author-facing** (which is what kills
`s1:` — `s` names nothing), and the prefix must be **visually distinct between the pipe and the
colon** (in `|1:` the ordinal is squeezed between two punctuation marks and disappears).

One framing correction that came out of this: escaping the digit trap does not buy the folded keys a
*leading* position, it buys **a position that is ours to choose**. Rank returns to
`bws_serialization_order_sort()` and its port, where the format group leads.

| Candidate | Author-facing hook | Verdict |
|---|---|---|
| `A:` | new alphabet — resolved by moving the tokens with it | **LEADING** |
| `%1:` | *identical* to the Format control's tokens — the best hook available | **REJECTED — URL hazard.** GB supports a dynamic tag inside an `href` (`class-register-dynamic-tag.php:212` matches `http://` + tag), and `%1` is an invalid percent-escape that `esc_url` can rewrite to `%251`. A folded `{{try_permalink}}` in a link is ordinary |
| `#1:` | "number" | **REJECTED — URL hazard.** `#` starts a fragment; the wire truncates at the first slot key |
| `slot1:` | "slot" is already the plugin's own noun (warning text, `bws_fold_slot_*`) and it KEEPS the number | **REJECTED — mis-sorts.** `slot1, slot10, slot2` under any string comparator, so it needs a numeric-aware sort in `serialization-order.php` and its JS port. `A`…`J` sort right for free |
| `1st:` / `n1:` / `1_:` | — | rejected: i18n-hostile ordinals (`10th` irregular, reads as a typo); the "what does that letter mean" failure again; unreadable |

No parser blocks ANY of them — GB's `parse_options()` is `explode(':', $pair, 2)` with no sanitizing,
its security scanner only inspects `link:` options, and `# @ % ^ * + = - _ .` are all in our own
serialization-safe set (`gb-constraints.md` §Separator-safe). The eliminations above are hazard and
sort-order, not capability.

#### SETTLED — format tokens follow the key spelling, with a digit FALLBACK

> User: *"The token format was strictly based on the digit system; if we change to capitals, the
> tokens go with it (though supporting the digits as a fallback would be nice)."*

`%N` was never an independent alphabet — it was the digit ordinal spelled for the wire because GB's
parser rejects `}`. So under capitals the Format control writes `%A`…`%<max>` and the author reads `A:`
beside `%A`: one alphabet, which dissolves the two-alphabet objection entirely.

**The fallback makes the token move migration-free**, and that is the load-bearing part.
`bws_join_wire_format()` (`join-helpers.php:142`) maps the wire alphabet onto the internal `{N}`
token; teach it letters AND keep digits, and every stored `format:%1 (%2)` keeps rendering. No
converter entry, no mount entry, no dual-read era — only the control's help text and placeholder
change. Mixed formats (`%1 (%B)`) resolve too, since letters and digits are disjoint.

Two costs, both accepted:

1. **The `%%` escape widens** from "literal `%` before a digit" to "before a digit or `A`–`J`". This
   is the one thing with no fallback trick, and it is a behaviour change on a shipped feature (1.15.0)
   — prose like `%APR` inside a format now needs `%%APR`. Note the fallback is what *forces* the
   widening: while both alphabets are live, the escape has to cover both.
2. **Panel LABELS must move with the keys, or the mapping tax just relocates into the editor.** Six
   `sprintf( '%2$d: %1$s', …, $n )` sites in `base-shared.php` (`:269,463,628,635,642,644`) print
   `"2: Source"`, and `table-tags.php` (`:146,156,174`) prints `Column %d Header`.

**Shape this dictates: ONE `bws_slot_ordinal( $n )` helper owns digit→letter, and keys, labels,
tokens and previews all read it** (JS twin = `alphaOrdinal()`, already in the preview tool).
Hand-typing `chr( 64 + $n )` at nine sites is precisely the drift the leaf/twin extractions removed.

Two incidental wins, worth recording because they are reasons and not decoration:

- **The `%10` prefix foot-gun dies.** `bws_join_wire_format()` substitutes high→low *only* because
  `%1` is a prefix of `%10` (low-first rewrites `%10` into `{1}0`). Single-char letters have no
  prefix relation, so the ordering rule stops being load-bearing.
- **Alphabetical sort equals slot order** for `A`…`J`, so no comparator anywhere needs to know the
  keys are ordinals. This is what eliminated `slot1:`.

#### Preview tool — the surface this is judged on (2026-08-04)

`{{join}}` had **no format-string scenarios at all**, so the tool could not show the question: with no
tag-level option on a folded join, there was nothing for the slot keys to be *ordered against*, and
the digit pinning was invisible. Added group **`join — TEMPLATE MODE`** (7 rows), plus a
`format`-collision row in the folded-datetime group and a colon-bearing format in the escape-hazards
group. Formats are authored in DIGIT form and rewritten by `formatTokens()`, so the sidebar picker
moves keys and tokens together.

Three rows carry the arguments above rather than illustrating them: the `%%` row (the escape widening,
readable under both spellings), the 10-slot row (`%J`, the dead prefix hazard), and the
`format`-collision row (join's template `format` vs a datetime slot's date `format` — unambiguous
folded, because the date format rides INSIDE the slot value).

**The shipped column keeps DIGIT tokens under every picker setting.** The token move ships with the
fold, so `format:%A (%B)|2-key:nickname` — letter tokens beside digit slot prefixes — is a string
nothing writes. Keeping it digits also puts the fallback on screen: the two columns disagree on the
token alphabet and both render.

Also fixed while there: flat free-form values (`format`, `fallback`, `label`, the separators) were
NOT `\:`-escaped on the shipped side, only inside folded values. Not a fold property — GB's JS-side
KV split is limit-2 and discards the tail, so a tag-level `format:Price: %1` loses `" %1"` on editor
reopen (`gb-constraints.md` §Separator-safe). The shipped column was showing a wire that does not
survive a round trip.

Panel labels are NOT modelled in the tool — it renders wires only. Cost 2 above stays unverified by
this page.
