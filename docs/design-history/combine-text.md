# Plan: `{{join}}` tag — standalone combining tag

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

> **SHIPPED 1.15.0 (2026-07-17, branch `feat/join-tag`, issue #46) — ARCHIVED.** Live homes:
> `docs/tag-reference.md` §join (catalog + algorithm), `tools/test/join-template-test.php` (pure
> Steps 1–5) + `tools/test/join-test-matrix.md` (22 integration rows, all verified),
> `docs/gb-constraints.md` (the NEW `}`-kills-tag-match wire constraint found during build —
> template tokens are `%1`…`%8` on the wire, NOT `{N}` as planned below), ADR 0003. Follow-ups
> filed: FW-43 (verb-agnostic resolver), FW-44 (per-slot inner sep), FW-45 (dynamic slots),
> FW-46 (name preset); FW-23 pre-existing. Algorithm deviation from §Smart literal removal as
> drafted: Step 1 splits punct classes (unit `.`'`"` sheds trailing-attached; connective `,`/`:`
> collapses only BETWEEN two connectives) — the drafted fully-symmetric shed contradicted J23/J24.

> **Grill-hardened 2026-06-26** (`/grill-with-docs`). Structural vocabulary + build locality +
> per-slot model + zero-handling all settled. Terminology lives in
> [CONTEXT.md §Tag structural vocabulary]; this plan is the build detail.
>
> **Testbed-routed 2026-07-17.** §Verification is now concrete `render-tag` rows against the seeded
> `testbed` site (`core-structures` blueprint), not abstract prose. §Fixture additions specs the
> name/height fields the assembly rows need; standing matrix = `tools/test/join-test-matrix.md`.
>
> **Grill-hardened again 2026-07-17** (`/grill-with-docs`). Nine decision branches walked;
> closed: J23/J24 → pure harness (render-tag has no per-field blank, verified); sparse name reuses
> `staff-tom-associate` (additive, text T7 safe); J4 → `height_in_zero`+`role` single-context;
> three phantom keys killed (`middle_name`, `first_name` ×2 → real seeded keys, `name_first` seeded
> on the page for J17/J18). **Two real defects caught:** J17/J18 asserted impossible slot-1 values
> off unseeded keys; **per-slot `{N}-limit` was never threaded** → J19 would silently truncate a
> list slot to one item. Both fixed. Per-slot inner `sep` deferred with a naming-collision reason
> ([ADR 0003](../../docs/adr/0003-join-per-slot-limit-not-sep.md)); a GB fact captured
> (`parse_options()` does not trim option values → sep-with-space is wire-safe, `gb-constraints.md`).
> J16b added (real `src:same` carry-forward, shared with future try_ rows).
>
> **Full-name stress case + Gap fixes 2026-07-17.** The full personal name (honorific / first /
> middle-initial / last / generation / credential / service) is join's richest template use-case and
> the primary fixture. It surfaced two design changes, both now folded in: **(1) slot cap 5 → 8**
> (`BWS_JOIN_MAX_SLOTS`, name needs 7) — see §Slot count; **(2) Step 1 now sheds attached punctuation
> on BOTH sides** (leading + trailing), fixing a mid-string orphan-comma bug (`{1}, {2}, {3}` with
> empty `{2}` was leaving `Jane, , Smith`). Name-format PRESET vs dedicated `{{name}}` TAG tracked in
> §Open/deferred; unbounded slots tracked there too.

## Context

A new tag that assembles several resolved field values into ONE output string. Two assembly
modes, one tag:

- **Separator mode** (default): join non-empty values with a separator string.
- **Template mode**: substitute positional tokens `{1}`, `{2}` … into a format string, with
  smart removal of surrounding literal punctuation when tokens are empty.

### Structural identity (settled)

- **`join` is NOT a modifier.** "modifier" = the PREFIX/fan-out sense (`register_modifier()` →
  `term_text`/`try_text`). join is a SINGLE standalone GB tag — no prefix, no `join_*` fan-out.
- **`join` is NOT a base tag.** Base tags are atoms resolving ONE read of their own. join
  resolves no read; it **absorbs multiple base tags as slots and assembles** their reads. A third
  structural position (no genus noun coined — join is the only instance; a future standalone
  `{{try}}` would be the second to earn it).
- **Absorb (the key property):** each slot consumes a registered base-tag template — v1 hardwired
  to `text`. When `text` gains a feature, join's text slots inherit it free. The future
  heterogeneous case (slot 2 = image, slot 3 = datetime) swaps the hardwired `text` for a per-slot
  type picker — SAME wire format, no migration.
- **Editor grouping ≠ structural class.** join probably shares the base-tag picker GROUP for UX
  (precedent: email/phone), without BEING a base tag.

### Composition verb (the behavior axis, not-yet-canonical)

- `try_` = **selecting**: first-non-empty slot wins (short-circuit). Output = one slot.
- `join` = **combining**: ALL non-empty slots kept, assembled. Output = composite of all slots.

→ join's one genuinely new mechanic is a **collect-all** slot loop (visit every slot, accumulate
`$values[1..5]`, never short-circuit). "multi-slot" is INTERNAL machinery vocabulary, NOT a
classification term — try_ and join share the slot machinery but differ in output semantics.

---

## Build locality — own loop, reuse the #26 builders (settled)

join writes its OWN slot build+resolve loop but CALLS the shipped option-definition primitives.

- **#26 (CLOSED 2026-06-26)** delivered the reusable part: `bws_build_slot_traversal_options`
  ([base-tags.php:802]) — src/ref/srcTermIn derivation, `same`-prepend, `_strip_default`, "N:"
  label, show_if re-qualify. Extracted + harnessed. join calls it with `$allow_site = true`.
- #26 did **not** extract the slot RESOLVE loop or the `show_if_any` reveal orchestration — those
  remain inline + **selecting-shaped** in `generate_base_try_tags()`. A verb-agnostic resolver
  (parameterized fold + reveal-shape) is a SEPARATE, still-UNFILED refactor.
- join's loop differs from try_'s in two HONEST ways it'd write regardless (combining reveal +
  collect-all fold). So "own loop" duplicates only orchestration join needs anyway, touches ZERO
  shipped try_ code, and makes join the **second instance** that shapes the eventual
  verb-agnostic-resolver issue.
- **Follow-up to file** (once join ships): verb-agnostic slot-resolver extraction — mirrors how
  #26 spawned the #32 site-arm follow-up. Tracked in `docs/future-work.md`.

---

## Slot count — v1 cap 8 (`BWS_JOIN_MAX_SLOTS`)

**v1 = 8 fixed slots** (`define( 'BWS_JOIN_MAX_SLOTS', 8 )`), raised from the earlier 5. Driver: a
**full personal name** decomposes into up to 7 optional parts — honorific, first, middle initial,
last, generation suffix (Sr./Jr./III), professional credential (PhD/MD), service/status
(USN (Ret.)) — plus headroom. 5 could not hold it. The bound is a single constant threaded through
the resolve loop, the `{N}`-token scan, the option-definition emit loop, and the editor reveal
chain; raising it is cheap. Editor reveals slots progressively (combining reveal, §Reveal logic), so
8 is not editor clutter — unused slots stay hidden.

> **Future-work — unbounded slots (tracked, NOT v1).** The real end-state is *arbitrary N* slots via
> a dynamic add-slot editor control (append a slot on demand), not a hard cap. 8 is a pragmatic v1
> ceiling. File a `docs/future-work.md` row: "join dynamic slot count (drop `BWS_JOIN_MAX_SLOTS`
> for an add-slot control)" — blocked by the custom editor-control work (`docs/editor-controls.md`
> reserved owner). Detail home: this plan §Slot count + `deferred_features.md`.

---

## Per-slot model (settled)

Each slot is a `text`-base spec. v1 emits these per-slot option keys (slot 1 = bare key, slot ≥2
= `{N}-` prefixed — the SAME flat-prefix wire format try_ uses, so the #26 builders apply
directly):

| Per-slot key | Source | Notes |
|---|---|---|
| `{N}-src` | `bws_build_slot_traversal_options(... allow_site=true)` | incl. `same` row (slot ≥2) + `site` |
| `{N}-ref` | same builder | shown when `{N}-src:ref` |
| `{N}-srcTermIn` | same builder | term-hop |
| `{N}-use` | text template `use` options (`key`/`title`) | **NO `same` row** (see below) |
| `{N}-key` | text key control | per-slot, never inherited |
| `{N}-limit` | text `limit` control | list-mode cap (srcTermIn / src:ref) — how many targets the slot reads. Default 1. Threaded into the slot's text resolve. |

> **Per-slot INNER `sep` NOT in v1 (DECIDED 2026-07-17 — (B); [ADR 0003](../../docs/adr/0003-join-per-slot-limit-not-sep.md)).** A list-mode slot (srcTermIn /
> src:ref reading N targets) joins its own items with text's default inner separator `', '`. There
> is NO `{N}-sep` control in v1 — per-slot inner-sep tuning is deferred (edge: a term-list slot
> INSIDE a join wanting a non-default inner separator). This also sidesteps a naming collision:
> join's tag-level `sep` (assembly separator) vs a slot-1 bare `sep` (inner list separator) would
> clash on the wire. v1 threads `{N}-limit` (needed so a list slot returns >1 item) but leaves the
> inner sep at text's `', '` default. Future-work row: per-slot inner `sep` (resolve the slot-1
> collision then — likely rename the tag-level assembly key, §Open/deferred).

> **Forward-ref — per-slot `if:` (timing OPEN, v1-or-later):** when the base-tag `if:` conditional
> primitive lands, join inherits it per-slot as another `{N}-if`-style flat key in THIS same N-option
> scheme (per-slot conditional INCLUSION — suppress a slot by a condition on another field, DISTINCT
> from empty-value smart removal below). Interaction to settle then: an IF'd-out slot should likely
> behave like an empty slot for separator collapsing (§Smart literal removal). Concept home: memory
> `deferred_features.md` §`if:` as a base-tag option + `docs/future-work.md`. Not scoped into v1 here yet.

### `use`: full text enum, NO `same` row

join exposes text's full `use` per slot (`key` = Meta/Option Field, `title` = Title/Name) — a
slot may contribute a meta field OR the entity's title/name
(`{{join 1:text,use:title|2:text,use:key,key:role}}` → "Jane Smith / Captain"). So
`per_slot_key:true` AND `per_slot_use:true`.

**But NO "Same as Previous Field" `use` row** (unlike try_). In COMBINING `same`-use is a footgun
or redundant:
- `use:title` + `same` next slot → re-reads the IDENTICAL datum (Jane / Jane). Pointless.
- `use:key` + `same` → "same use (meta field), different key" — but meta-field IS the default;
  `same` adds nothing over leaving `use` at its key default.

The shipped try_ builder hardcodes the `same`-use prepend whenever `per_slot_use` (NOT
flag-gated, [registry:493-498]) — so join CANNOT get `per_slot_use` from the shared builder
without it. **This is a concrete reason join owns its build loop.**

### Source carry-forward asymmetry

- **`{N}-src`** keeps the `same`/inherit row (slot ≥2). Coherent in combining: weave several
  fields off the SAME entity (athletics = every slot `current`/`same`). Carry-forward semantics
  match try_ ([registry:687-691]): empty wire = inherit prior resolved source.
- **`{N}-use` / `{N}-key`** never inherit. Each slot's field identity is independent.

The asymmetry is the point: **source can sensibly carry forward; field-identity cannot.**

### Reveal logic — COMBINING-shaped

A join slot is "real" when it has a `key` OR a non-default `use` (a `use:title` slot has no key
but IS configured). So reveal slot N+1 when **`{prev}-key not_empty` OR `{prev}-use not_empty`** —
NOT `{prev}-src` (default-empty in combining; try_'s reveal keys on src because a fallback
alternative is defined by WHERE it reads — wrong axis for join).

### Key visibility within a slot

`use`-conditional (hidden for `use:title`, shown for key-mode) — but SIMPLER than try_'s: no
`same`-inherit mode, so slot ≥2 key-visibility = same rule as slot 1 (show when `use` is
key-needing). No inherit approximation.

### Site arm — MUST NOT repeat try_text's gap

try_text filters `site` out of slot src (omits `try_allow_site_slot`); the newer try_email/phone
opt in and wire the site arm (`try_allow_site_slot => true`, [phone-tags.php:652],
[email-tags.php:384]). join behaves like email/phone:

- Slot src builder called with `$allow_site = true` (join is standalone — passes the bool
  directly to `bws_build_slot_traversal_options()`, not via the modifier-only flag).
- join's collect-all loop **MUST port the `'site' === $last_src` resolver arm**
  ([registry:771-783]) — a slot reading a site option
  (`{{join 1:text,key,fname|2:text,src:site,key:company_name}}`) is a first-class assembly case.
  Resolve it via the text core with `$post_id = 0` and `src:site` in the slot options.

---

## Tag-level options (NOT per-slot)

```
mode           select — '' (Separator, default) / 'template'
sep            text   — separator string, default ', '   [show_if: mode not:template]
format         text   — format string with {1} {2} tokens [show_if: mode = 'template']
fallback_text  text   — returned when ALL slots resolve empty
```

```php
$tag_level = array(
    'mode' => array(
        'type'    => 'select',
        'label'   => __( 'Assembly Mode', 'generateblocks' ),
        'options' => array(
            array( 'value' => '',         'label' => __( 'Separator', 'generateblocks' ) ),
            array( 'value' => 'template', 'label' => __( 'Template', 'generateblocks' ) ),
        ),
        '_strip_default' => true, // '' is the visual default, not serialized.
    ),
    'sep' => array(
        'type'        => 'text',
        'label'       => __( 'Separator', 'generateblocks' ),
        'help'        => __( 'Text placed between non-empty values. Default: ", ".', 'generateblocks' ),
        'placeholder' => ', ',
        'show_if'     => array( 'mode' => 'not:template' ),
    ),
    'format' => array(
        'type'        => 'text',
        'label'       => __( 'Format', 'generateblocks' ),
        'help'        => __( 'Format string using {1}, {2} … as positional tokens.', 'generateblocks' ),
        'placeholder' => '{1} ({2})',
        'show_if'     => array( 'mode' => 'template' ),
    ),
    'fallback_text' => array(
        'type'  => 'text',
        'label' => __( 'Fallback Text', 'generateblocks' ),
        'help'  => __( 'Text to display when all fields are empty.', 'generateblocks' ),
    ),
);
```

**Positional token syntax:** `{1}` … `{5}` map to slots 1 … 5 by position. Named tokens (by field
key) NOT supported — positional is shorter on the wire and unambiguous regardless of key naming.

---

## Registration (standalone, NOT a modifier)

**File:** `includes/tags/base-tags.php` (alongside the other standalone base-tag registrations) —
NOT `register_modifier_template` (that fans out prefixes). One GB tag:

```php
new GenerateBlocks_Register_Dynamic_Tag( array(
    'title'    => __( 'Combine Fields', 'generateblocks' ),
    'tag'      => 'join',
    'type'     => 'cross-source',
    'supports' => array(),
    'options'  => bws_strip_default_select_values( bws_get_join_options() ),
    'return'   => 'bws_join_callback',
) );
```

No `join_term`, no `join_<source>`. Cross-source reach comes later via a future `src`-options
rework, not name-multiplied tags.

`bws_get_join_options()` builds: the per-slot keys (own loop emitting `{N}-src/-ref/-srcTermIn/
-use/-key` via the #26 builders + the combining reveal triggers) followed by the tag-level
options above.

---

## Resolution — collect-all over the ABSORBED text resolve

The collect-all loop is the new mechanic. Per slot it resolves EXACTLY as `{{text}}` would (so
join absorbs every text behavior — link-wrap is intentionally NOT applied at the join layer; see
note), then collects the finished string into `$values[$n]`. It NEVER short-circuits.

```php
function bws_join_callback( $options, $block, $instance ): string {
    $values = array(); // 1-based; $values[$n] = finished slot string or ''.
    $last_src = $last_ref = '';   // carry-forward (src only; use/key never inherit)

    for ( $n = 1; $n <= BWS_JOIN_MAX_SLOTS; $n++ ) {  // 8 in v1 — see §Slot count
        $src = ( 1 === $n ) ? ( $options['src'] ?? '' )      : ( $options["{$n}-src"] ?? '' );
        $ref = ( 1 === $n ) ? ( $options['ref'] ?? '' )      : ( $options["{$n}-ref"] ?? '' );
        $use = ( 1 === $n ) ? ( $options['use'] ?? '' )      : ( $options["{$n}-use"] ?? '' );
        $key = ( 1 === $n ) ? ( $options['key'] ?? '' )      : ( $options["{$n}-key"] ?? '' );
        $stm = ( 1 === $n ) ? ( $options['srcTermIn'] ?? '' ): ( $options["{$n}-srcTermIn"] ?? '' );
        $lim = ( 1 === $n ) ? ( $options['limit'] ?? '' )    : ( $options["{$n}-limit"] ?? '' );

        // Slot is "real" iff it has a key OR a non-default use. Else stop (no more slots).
        if ( '' === $key && '' === $use ) {
            continue; // reveal guarantees gaps don't occur mid-chain; be defensive.
        }

        // Carry-forward: '' src = inherit prior resolved source ('same'); else override.
        // $last_ref is NOT cleared on a non-ref src override — it's safe: the text resolve
        // reads `ref` ONLY when src:ref ($is_ref gate, base-tags.php:649), so a stale
        // $last_ref threaded alongside src:current/site is inert. Do NOT "fix" by clearing it
        // (would break a later slot that carries back to the same ref).
        if ( '' !== $src && 'same' !== $src ) { $last_src = $src; }
        if ( '' !== $ref )                    { $last_ref = $ref; }

        // Build a single-slot text-tag option set and absorb the text resolve.
        $slot_opts = array(
            'src' => $last_src, 'ref' => $last_ref,
            'use' => '' === $use ? 'key' : $use,   // I3: empty use = stripped key default
            'key' => $key, 'srcTermIn' => $stm,
        );
        // List-mode cap: thread the slot's own limit so a srcTermIn/src:ref slot reads
        // >1 target. Absent → text's default (1). Inner list sep stays text-default (', ');
        // no per-slot inner sep in v1 (see §Per-slot model).
        if ( '' !== $lim ) { $slot_opts['limit'] = $lim; }

        $values[ $n ] = bws_join_resolve_slot( $slot_opts, $instance ); // see below
    }

    $assembled = bws_join_assemble( $values, $options ); // separator | template
    $fallback  = sanitize_text_field( $options['fallback_text'] ?? '' );

    if ( '' === $assembled ) {
        return '' !== $fallback
            ? GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $options, $instance )
            : '';
    }
    return GenerateBlocks_Dynamic_Tag_Callbacks::output( $assembled, $options, $instance );
}
```

### `bws_join_resolve_slot()` — the absorb seam

Resolves ONE slot identically to `{{text}}`. v1 routes through the SAME post/term/site dispatch
the text callback uses, so a slot inherits text's field-read, src:site arm, srcTermIn list mode,
and `'0'` handling for free.

```php
function bws_join_resolve_slot( array $slot_opts, $instance ): string {
    // Absorb seam (shipped 1.14.1): the FULL text read — post/term/ref/srcTermIn
    // dispatch AND the site arm (the try_text gap is closed by construction).
    // link_id/link_type ignored: NO per-slot link-wrap (join composes raw values;
    // wrapping — if ever — happens once at the join layer). '0' preserved.
    $resolved = bws_base_text_resolve_value( $slot_opts, $instance );
    return $resolved['value'];
}
```

> **SHIPPED 1.14.1 — the absorb seam exists.** `bws_base_text_resolve_value( $options, $instance )`
> (base-tags.php) is the extracted text read path — returns `{value, link_id, link_type}`,
> no link-wrap, no preview fallback; `bws_base_text_callback` is now a shell over it. join's
> slot resolve calls it directly and IGNORES `link_id`/`link_type` (no per-slot wrap). This
> REPLACES the planned `bws_resolve_text_value_for_join` wrapper and the inline-dispatch
> fallback — do NOT copy the dispatch. Absorb invariant documented in the fn's PHPDoc. The
> list-mode (srcTermIn / src:ref) join uses text's own `limit` (threaded per-slot as `{N}-limit`)
> and its INNER list separator is text's default `', '` — independent of join's tag-level `sep`
> (assembly). Per-slot inner sep is NOT author-tunable in v1 (§Per-slot model, (B)).

### Assembly

```php
function bws_join_assemble( array $values, array $options ): string {
    $mode = $options['mode'] ?? '';
    if ( 'template' === $mode ) {
        $format = $options['format'] ?? '';
        return '' === $format ? '' : bws_join_template( $values, $format );
    }
    $sep = isset( $options['sep'] ) && '' !== $options['sep'] ? $options['sep'] : ', ';
    return bws_join_separator( $values, $sep );
}

function bws_join_separator( array $values, string $sep ): string {
    return implode( $sep, array_filter( $values, static fn( $v ) => '' !== $v ) );
}
```

`bws_join_template()` + the smart-literal-removal helpers are pure string functions (no WP/GB
symbols) — locally harnessable. Algorithm below.

---

## Smart literal removal algorithm (template mode)

Applied after token substitution, operating on empty-token positions. **"Empty" means exactly
`''`** — `'0'` is a real value and is KEPT (see §Empty-value detection). Processing order:

**Step 1 — Attached punctuation, BOTH sides (symmetric shed).**
Punctuation `:`, `,`, `.`, `'`, `"` attached to an empty token position (no whitespace between token
and punctuation) is removed WITH it — on EITHER side:

- **Trailing** (punct immediately AFTER the token): `{1}, {2}`, `{1}: {2}`, `{1}. {2}`. Quote marks
  `'` / `"` included so unit-suffixed tokens shed a dangling unit mark: `{3}'{4}"` `{4}` empty →
  `{3}'` (the `"` goes with empty `{4}`); `{3}` empty → `{4}"` (the `'` goes with empty `{3}`).
- **Leading** (punct immediately BEFORE the token, incl. the `<ws>?,<ws>?` shape): `{1}, {2}` with
  `{2}` empty sheds the preceding `,` → `{1}`. This is the credential/generation case
  (`{last}, {credential}` → `Smith, PhD`; empty credential → `Smith`, no orphan comma).

**Why symmetric (Gap fix — mid-string orphan comma):** a name format threads commas BEFORE optional
parts (`{first}, {mid}, {last}` — or `{last}, {gen}, {cred}`). With only trailing-shed + the
trailing-only Step 4b, an empty MIDDLE part left `Jane, , Smith` (Step 4b is end-of-string only;
Step 3's separator set omits `,`). Leading-shed closes it: empty `{2}` consumes its preceding `, `
→ `Jane, Smith`. Process empty tokens left-to-right so cascades (two adjacent empties) resolve
cleanly. Leading-shed fires ONLY on whitespace-adjacent or directly-attached punct, so `Dr.`
(literal, no token before the `.`) is never touched.

- `{1}, {2}` `{1}` empty → `{2}` (trailing shed off `{1}`)
- `{1}, {2}` `{2}` empty → `{1}` (leading shed: `{2}` eats the preceding `, `)
- `{1}, {2}, {3}` `{2}` empty → `{1}, {3}` (leading shed on the middle comma — the Gap-2 fix)
- `{1}: {2}` `{1}` empty → `{2}`
- `{1}. {2}` `{1}` empty → `{2}` (mid-string period removed with empty token)
- `{3}'{4}"` `{4}` empty, `{3}`='5' → `5'` (dangling `"` removed with `{4}`)
- `{3}'{4}"` both empty → `''`

**Step 2 — Bracket pairs around empty tokens.**
Scan outward from each empty token, consuming whitespace, until a paired bracket (`(`,`)`,`[`,`]`)
is found each side. If matched, remove both brackets + trim inner whitespace.
- `{1} ({2})` `{2}` empty → `{1}`
- `{1} ({2}.)` `{2}` empty → `{1}`

**Step 3 — Floating separators adjacent to empty tokens.** Separators: `·` `•` `/` `|` `-` `–` `—`.
Direction: look RIGHT of the empty token for whitespace-separator-whitespace and remove it;
EXCEPTION — if the empty token is the **last token in the format string** (highest `{N}` present,
regardless of value), look LEFT instead. Process empty tokens left-to-right (each removal reflects
current state).
- `{1} · {2}` `{2}` empty → last → look left → `{1}`
- `{1} · {2} – {3}` `{2}` empty → not last → look right → `{1} · {3}`
- `{1} · {2} – {3}` `{3}` empty → last → look left → `{1} · {2}`
- `{1} · {2} · {3}` `{1}`&`{3}` empty → `{2}`

**Step 4 — Whitespace collapse.** Collapse multiple spaces to one; trim ends.

**Step 4b — Trailing orphan punctuation cleanup.** Strip a trailing `:`, `,`, or `.` (+ trailing
whitespace) UNLESS the ORIGINAL format string (pre-substitution) ends with `.` (intentional
sentence terminator). Check the *original* format for authorial intent.
Quote marks `'` / `"` are deliberately NOT stripped here — a surviving trailing unit mark (`5'`)
is intentional output. (Empty-token-side cleanup is Step 1; Step 4b only fires on punctuation that
survived attached to a NON-empty value.)
- `{1}: {2}` `{2}` empty → `Smith:` → `Smith`
- `{1}, {2}` `{2}` empty → `Smith,` → `Smith`
- `{1} {2}, {3}.` `{1}`&`{2}` empty, format ends `.` → `{3}.`
- `{1}. {2}, {3}` `{1}`&`{3}` empty, no trailing `.` → Step 1 drops `.` after `{1}`, 4b strips `,` → `{2}`

**Step 5 — Single surviving token strips connective separators.** If exactly one token resolved
non-empty, remove remaining connective separators (`·`•`/`|`-`–`—`) + surrounding whitespace NOT
part of a bracket group. Literal text (`Mr.`, `Author:`) adjacent to the surviving token is kept.
Brackets only removed by Step 2 (when empty); a surviving token inside brackets keeps them.
- `{1} · ({2}.)` `{1}` empty → `({2}.)`
- `Mr. {1} · {2}` `{2}` empty → `Mr. {1}` → `Mr. Smith`
- `{1} | {2}` `{1}` empty → `{2}`

Helpers: `bws_join_remove_empty_token($result, $token, $is_last_token)` = Steps 1–3 (Step 1 now
sheds attached punct on BOTH sides of the token; `$is_last_token` drives Step 3 direction).
`bws_join_strip_connective_separators()` = Step 5. Steps 4 / 4b inlined in `bws_join_template()`.
The symmetric Step 1 subsumes most of what Step 4b did — 4b stays as the end-of-string safety net
for punct that survived attached to a NON-empty value.

```php
function bws_join_template( array $values, string $format ): string {
    $result = $format;
    $empty  = array();
    for ( $n = 1; $n <= BWS_JOIN_MAX_SLOTS; $n++ ) {
        if ( '' !== ( $values[ $n ] ?? '' ) ) {
            $result = str_replace( "{{$n}}", $values[ $n ], $result );
        } else {
            $empty[] = $n;
        }
    }
    if ( empty( $empty ) ) {
        return $result;
    }
    $last_token = 0;
    for ( $n = BWS_JOIN_MAX_SLOTS; $n >= 1; $n-- ) {
        if ( str_contains( $format, "{{$n}}" ) ) { $last_token = $n; break; }
    }
    foreach ( $empty as $n ) {
        $result = bws_join_remove_empty_token( $result, "{{$n}}", $n === $last_token );
    }
    $result = trim( preg_replace( '/  +/', ' ', $result ) );
    $ends_period = str_ends_with( rtrim( $format ), '.' );
    $result = preg_replace( $ends_period ? '/[,:]\s*$/' : '/[,:\.]\s*$/', '', $result );
    if ( 1 === count( array_filter( $values, static fn( $v ) => '' !== $v ) ) ) {
        $result = bws_join_strip_connective_separators( $result );
    }
    return trim( $result );
}
```

---

## Empty-value detection — `'0'` is a REAL value (absorbed), NOT empty

"Empty" everywhere = exactly `''` (after sanitize). A field value of `'0'` is a **real,
non-empty value** and is KEPT.

- join **absorbs** the text base resolve. The base text path has a SHIPPED, deliberate `'0'` hook:
  `generateblocks_dynamic_tag_replacement` ([hooks.php:37-39]) maps a `'0'` return to `'0 '` so
  GB's falsy block-kill doesn't eat it. **Base behavior = `'0'` renders.**
- join MUST NOT re-decide value-emptiness — that would VIOLATE absorb (a join slot treating `0`
  differently from standalone `{{text}}`). No `'0'`→`''` coercion anywhere in join.

**Athletics height (`{3}'{4}"`):** if `roster_height_in` STORES `0`, join renders `5'0"`, NOT
`5'`. Suppression needs the author to store `''` (not `0`), OR the future base-text opt-in below.
Correct-by-absorb, not a join bug.

**TRACKED (base text tag, NOT join):** a future opt-in on the BASE text tag to treat `0` as empty
(augmenting [hooks.php:37]) would be absorbed by join for free — every text slot suppresses `0`,
yielding the athletics `5'`. File against base text; join inherits. In `docs/future-work.md`.
(The Step-1 quote-mark handling is independent and stays — it fires when inches resolves to `''`,
the author-stores-blank path.)

---

## Files to create / modify

| File | Action |
|---|---|
| `includes/tags/base-tags.php` | Standalone `{{join}}` registration; `bws_get_join_options()` (own slot loop + tag-level opts); `bws_join_callback()` |
| `includes/helpers/content-helpers.php` (or a new `join-helpers.php`) | `bws_join_resolve_slot()`, `bws_resolve_text_value_for_join()` (absorb seam), `bws_join_assemble()`, `bws_join_separator()`, `bws_join_template()`, `bws_join_remove_empty_token()`, `bws_join_strip_connective_separators()` |
| `tools/test/join-template-test.php` | Harness the pure smart-literal helpers (Steps 1–5 + verification rows). MUST cover the symmetric Step-1 leading-shed: `{1}, {2}, {3}` with empty `{2}` → `Jane, Smith` (the Gap-2 mid-string orphan-comma fix), the full-name dense/sparse collapse (`Dr. Jane M. Smith Jr., PhD, USN (Ret.)` → `Tom Associate`), and the `M.` middle-initial `.` shed. Because J23/J24 may live here (see §Verification note) rather than as `render-tag` rows, this harness owns the mid-string-single-empty assertions. |
| `docs/tag-reference.md` | Add `{{join}}` to the catalog (standalone, per-slot, modes) |
| `docs/editor-tag-previews.md` | join preview-text shape (per-slot + assembled) if it surfaces in preview |
| `bws-gb-dynamic-tags-extensions.php` | Version bump |
| `CHANGELOG.md` | Entry (surface user-facing prose for review per house style) |
| `docs/future-work.md` | Already rowed; flip to SHIPPED on release. File the base-text zero-empty opt-in + verb-agnostic-resolver-extraction rows. |
| `tools/fixtures/core-structures/` | **Fixture additions for join assembly rows** (schema + manifest + reseed) — see §Fixture additions below. |
| `tools/test/join-test-matrix.md` | Standing manual matrix (assembly + absorb rows) — `render-tag` one-liners against the testbed. Mirrors `text-test-matrix.md`. |

---

## Open / deferred (NOT v1)

- **Per-slot TYPE picker** (heterogeneous slots — image/datetime/etc.). Same wire format; future
  picker swaps the hardwired `text`. The tag-in-slot-composition north-star (memory
  `deferred_features.md`).
- **Verb-agnostic slot-resolver extraction** (selecting|combining fold + reveal-shape) — file
  once join ships; join is the 2nd instance shaping it.
- **Base-text `0`-as-empty opt-in** — absorbed by join; yields athletics `5'`.
- **Link-wrap on join output** — v1 composes raw values, no wrap. If wanted later, wrap ONCE at
  the join layer (never per-slot), and only for single-value output.
- **Dynamic slot count** (drop `BWS_JOIN_MAX_SLOTS` for an add-slot editor control) — see
  §Slot count. Blocked by the custom editor-control work.
- **Per-slot inner list `sep`** (`{N}-sep`) — v1 threads `{N}-limit` but NOT a per-slot inner
  separator; a list-mode slot joins its items with text's default `', '`. Adding a per-slot inner
  sep requires resolving the slot-1 `sep` vs tag-level assembly-`sep` wire collision (likely rename
  the tag-level assembly key, e.g. `glue`). Deferred — edge case (a term/ref list slot inside a
  join wanting a non-default inner separator). File a `docs/future-work.md` row.
- **Name-format preset / dedicated name tag** (from the full-name driver). The full personal name
  is join's richest single use-case — 7 optional parts, all the punctuation-collapse rules at once.
  Two possible ends, TRACK BOTH, pick on evidence:
  - **Preset** (cheaper): a canned name template — a labeled option that pre-fills `mode:template`
    + the `{1} {2} {3}. {4} {5}, {6}, {7}` format + the 7 slot `use`/`key` defaults, so an author
    picks "Full name" instead of hand-wiring. Pure config sugar over join; no new resolve path.
    Lives as a preset in the join editor control.
  - **Dedicated `{{name}}` tag** (explore LATER, currently disfavored): a standalone tag whose
    options ARE the name parts (Honorific / First / Middle / Last / Generation / Credential /
    Service), hiding the positional-token format. Cleaner UX in the abstract; join stays the
    primitive underneath (name could ABSORB join — a 4th structural-position instance).
    **Blocked by a NAMING COLLISION:** "name" is already overloaded across the codebase —
    term-NAME (`use:title` on terms), post_name, the repeater `name` subfield (`team_members`,
    `feature_list`). A `{{name}}` tag reads as "the name of the current thing," NOT "assemble a
    personal name from parts." High mislearn risk. A dedicated tag, if ever, needs a
    collision-free name (e.g. `{{person}}` / `{{fullname}}`) — decide then.
  - **LEAN (2026-07-17): preset, not a tag.** Preferred direction is the name-format PRESET —
    avoids the `{{name}}` collision entirely, ships as pure config sugar over join, no new resolve
    path, no new structural position. The dedicated tag is parked as "explore later" ONLY if the
    preset proves insufficient (authors still mis-wire, or the token format leaks confusion) AND a
    non-colliding name is chosen. Decision axis: cosmetic sugar suffices → preset (default path);
    persistent mis-wiring → revisit a well-named dedicated tag. File a `docs/future-work.md` row
    (detail home: this section + `deferred_features.md`); neither is in join v1 — v1 just proves
    the collapse rules the name needs.

### Explicitly OUT of join scope — conditional / computed assembly

Driver: athletics `bws_get_game_result()`. Do NOT try to force this into join. join = **combining**
(assemble a FIXED shape over all slots). That function is mostly a DIFFERENT primitive:

- **Branch on a read value** (term name → which template) — value-conditional dispatch. Today:
  external GP/Block term-visibility on separate Elements, each holding its own tag. join has no
  branch-on-data mechanism.
- **Computation** (reorder scores by magnitude) — no tag assembles this; precompute a field.
- **All-or-nothing group** (show scores iff BOTH present) — join's smart-removal is PER-TOKEN,
  not group-gated.
- **Nested selecting** (abbreviation ELSE term-name) — a per-slot try inside a join slot; not v1.

Only the FINAL-string layer (`disp,&nbsp;scores — info` over already-decided values) is join.
Pragmatic path for this case: precompute ordered `score_display` + populate `abbreviation` on
every term, use GP term-visibility to pick the branch, one `{{join}}` per branch. The conditional/
computed logic is parked as a THIRD verb candidate (selecting/combining/**conditional**) —
`docs/future-work.md` + memory `deferred_features.md`.

**Second case, same boundary — `bws_build_base_title`** (+ `_location`/`_outcome` wrappers).
Even more conditional/computed: multi-taxonomy branch (skip location-indicator if event-type is
tournament/meet), a location-type→indicator LOOKUP fn, status-only-for-non-standard-results
(term-membership test), ref-hop title, nested `bws_get_game_result`. join could only assemble the
final markup of decided values. **These helpers are `{{call}}` territory** (function-passthrough,
`.claude/plans/fn-passthrough-tag.md`) — opaque PHP with loop-correct post_id — NOT join/if. The
pattern is now confirmed across two drivers: site-specific taxonomy-conditional + lookup-table
display helpers do not decompose into join/if; reach them via `{{call}}`.

---

## Fixture additions (core-structures blueprint)

join's assembly rows need flat person-name + athletics-height fields the current blueprint
lacks. **Add them to the existing `Staff Contact` group** (page+staff location — so they land on
`page-matrix-post-meta`, the same post-arm context every other join row uses) and seed values on
`page-matrix-post-meta`. One reseed (`bin/seed.sh testbed core-structures`); re-run `verify.php`.
The read-seam fields (`main_line`, `related_staff`, dept terms, `bws_zero_probe`, site
`organization_*`) already present cover the absorb rows.

The **primary assembly stress case is a full personal name** — the richest real-world template:
up to 7 optional parts with punctuation between every pair, so it exercises empty-collapse on both
sides (leading + trailing shed), bracket groups (`(Ret.)`), unit-style attached punct (`M.`), and
the mid-string orphan-comma path all at once. Seed ONE person's full name + a sparse person (only
first/last populated) so the same format string renders both dense and collapsed.

**`schema.php` — append to `group_bwsfx_staff_contact` fields** (via the `$text(...)` helper):

| Field name | Label | Purpose (rows) |
|---|---|---|
| `name_honorific` | Honorific | `Dr.` — literal `.` kept when present, whole token sheds when empty |
| `name_first` | First Name | dense + sparse person (J1, J5–J9, J21–J24) |
| `name_middle_initial` | Middle Initial | `M` → `{N}.` unit-style; empty → sheds the `.` (J22) |
| `name_last` | Last Name | dense + sparse (J1, J5–J9, J21–J24) |
| `name_generation` | Generation | `Jr.` — leading-comma shed when empty (J23) |
| `name_credential` | Credential | `PhD` — mid-string orphan-comma path (J21, J24) |
| `name_service` | Service/Status | `USN (Ret.)` — bracket-group interplay (J21) |
| `role` | Role | `use:key` slot alongside `use:title` (J15) |
| `height_ft` | Height (ft) | unit-suffix template `{N}'` (J11–J14) |
| `height_in` | Height (in) | unit-suffix template `{N}"` (J11) — seeded `'11'` |
| `height_in_blank` | Height (in, blank) | Step-1 dangling-`"` drop (J12/J13) — seeded `''` |
| `height_in_zero` | Height (in, zero) | absorbed `'0'` renders `5'0"` (J14) — seeded `'0'` |

**`manifest.php` — seed on the TWO EXISTING staff fixtures (DECIDED 2026-07-17 — reuse tom, no new
fixture).** `staff-jane-partner` = the DENSE full name (every part); `staff-tom-associate` = SPARSE
(first+last only, all other name parts `''`), so one format string demonstrates dense vs collapsed.
Reusing tom (not a dedicated `staff-full-name-sparse`) is purely ADDITIVE — the name fields don't
touch his `post_title` or his load-bearing role as text T7's 2nd `related_staff` ref target (T7 keys
on title + relationship, verified), so no matrix regresses; and tom's existing shape (title
"First Last", nothing else) already IS the sparse-person semantic. Full-name rows run on the staff
single (`/staff/jane-partner/`, `/staff/tom-associate/`), NOT `/matrix-post-meta/`, because the name
fields live on the staff post.

```php
// staff-jane-partner  (DENSE — the full-name stress fixture)
'name_honorific'      => 'Dr.',
'name_first'          => 'Jane',
'name_middle_initial' => 'M',
'name_last'           => 'Smith',
'name_generation'     => 'Jr.',
'name_credential'     => 'PhD',
'name_service'        => 'USN (Ret.)',

// staff-tom-associate  (SPARSE — same format collapses)
'name_first' => 'Tom',
'name_last'  => 'Associate',
// name_honorific / _middle_initial / _generation / _credential / _service all '' (unset)
```

Height + `role` + a slot-1 `name_first` stay on `page-matrix-post-meta` (existing post-arm rows):

```php
'role'            => 'Captain',
'name_first'      => 'Jane',  // J17/J18 slot-1 value on the post-arm context (ref/site hop rows)
'height_ft'       => '5',
'height_in'       => '11',
'height_in_blank' => '',      // J12/J13 dangling-quote drop
'height_in_zero'  => '0',     // J14 absorbed-'0' renders
```

> `name_first` on the page is a deliberate post-arm slot-1 value for J17/J18 (whose slot-2 does the
> ref/site hop) — distinct entity from the staff `name_first`, no conflict. The other `name_*` parts
> stay UNSEEDED on the page (so J13's `name_generation` slot 1 reads empty there).

Bump `manifest.php` `version` (breaking key addition). Update the blueprint `README.md` seeded-surface
summary + the `Consumed by:` list in `schema.php`/`manifest.php` to include `join-test-matrix.md`.

> **Gap — person name via repeater rows not covered.** `team_members` (`sample-event`) has
> `name`/`role`/`description` per row, but join reads FLAT fields, not loop-row repeater subfields.
> The flat `name_*` fields above are the join person-name surface; the repeater path (join inside a
> query loop over rows) is a future loop-item row, not v1 here.

---

## Verification — route through the testbed (`render-tag`)

Standing matrix: `tools/test/join-test-matrix.md` (mirror `text-test-matrix.md`'s shape). Every row
is a `render-tag` one-liner against the seeded `testbed` site — **never** a hand-built page or the
live/cached site (CLAUDE.md §Development).

```bash
bin/wp.sh testbed bws render-tag '{{join …}}' --url=https://testbed.test/matrix-post-meta/ --porcelain
```

Two contexts. **Name rows** run on the staff singles — `/staff/jane-partner/` (DENSE: all `name_*`
populated) and `/staff/tom-associate/` (SPARSE: only `name_first`/`name_last`), so the SAME format
string shows dense vs collapsed. **Height / role / absorb rows** run on `/matrix-post-meta/` (the
post-arm context, existing read-seam fields). Site arm reads the options page regardless of context;
term-hop uses `/matrix-post-meta/`'s Support+Sales terms. Wire: `|`=option-separator inside the tag,
`{N}-`-prefixed keys for slot ≥2. Reveal rows (J20) are editor-only — open a join block on the
testbed editor (like text T6). `SPARSE` empty-slot cases key on a `name_*` part that is `''` on tom
(e.g. `name_generation`).

**Separator mode (name — `/staff/jane-partner/` dense, `/staff/tom-associate/` sparse):**

| # | Tag | Context | Expected |
|---|---|---|---|
| J1 | `{{join key:name_first\|2-key:name_last}}` | jane | `Jane, Smith` (default sep `, `) |
| J1b | `{{join key:name_first\|2-key:name_last\|sep: }}` | jane | `Jane Smith` (space sep) |
| J2 | `{{join key:name_first\|2-key:name_generation\|3-key:name_last}}` | tom | `Tom, Associate` — empty generation slot dropped |
| J3 | `{{join key:name_generation\|2-key:name_credential\|fallback_text:—}}` | tom | `—` — all slots empty → fallback |
| J3b | `{{join key:name_generation\|2-key:name_credential}}` | tom | `` (empty, no fallback → block hidden) |
| J4 | `{{join key:height_in_zero\|2-key:role}}` | `/matrix-post-meta/` | `0, Captain` — `'0'` is a REAL value, kept (survives the separator's `array_filter`) |

> **J4 context (DECIDED 2026-07-17 — (b)).** `height_in_zero` and `role` both live on
> `page-matrix-post-meta`, so this is a single-context row proving `'0'` is NOT eaten by the
> separator's empty-filter. The earlier cross-fixture form (`height_in_zero` + a `name_*` key) was
> impossible — those fields live on different fixtures — and is dropped. No `height_in_zero` seed on
> jane needed.

**Template mode (name — `/staff/jane-partner/` unless noted):**

| # | Tag | Context | Expected |
|---|---|---|---|
| J5 | `{{join mode:template\|format:{1} ({2})\|key:name_first\|2-key:name_last}}` | jane | `Jane (Smith)` |
| J6 | `{{join mode:template\|format:{1} ({2})\|key:name_first\|2-key:name_generation}}` | tom | `Tom` — bracket group removed (`{2}` empty) |
| J7 | `{{join mode:template\|format:{1} · {2}\|key:name_generation\|2-key:name_last}}` | tom | `Associate` — floating separator removed (`{1}` empty) |
| J8 | `{{join mode:template\|format:{1} ({2}.)\|key:name_first\|2-key:name_generation}}` | tom | `Tom` |
| J9 | `{{join mode:template\|format:{1} ({2}.)\|key:name_generation\|2-key:name_first}}` | tom | `(Tom.)` — bracket kept around surviving token |
| J10 | `{{join mode:template\|format:{1} ({2})\|key:name_generation\|2-key:name_credential\|fallback_text:—}}` | tom | `—` — all tokens empty → fallback |

**Full-name assembly — the primary stress case (`/staff/jane-partner/` dense, `/staff/tom-associate/` sparse):**

Same format string, both contexts. Format (7 slots):
`{1} {2} {3}. {4} {5}, {6}, {7}` = honorific / first / middle-initial / last / generation / credential / service.
Slot wiring: `key:name_honorific | 2-key:name_first | 3-key:name_middle_initial | 4-key:name_last | 5-key:name_generation | 6-key:name_credential | 7-key:name_service`.

| # | Context | Expected |
|---|---|---|
| J21 | jane (DENSE, all parts) | `Dr. Jane M. Smith Jr., PhD, USN (Ret.)` — every part rendered, literal `.`/`,` kept |
| J22 | tom (SPARSE — honorific/mid/gen/cred/service all `''`) | `Tom Associate` — mid-initial `.` shed (Step 1), leading commas before empty credential/service shed, no orphan `, ,` (the Gap-2 fix), no trailing punctuation |
| J23 | pure harness (empty `{5}`, all other parts dense) | `Dr. Jane M. Smith, PhD, USN (Ret.)` — generation gone, its surrounding `, ` collapses cleanly (leading-comma shed on the empty `{5}`) |
| J24 | pure harness (empty `{6}`, all other parts dense) | `Dr. Jane M. Smith Jr., USN (Ret.)` — mid-string orphan-comma path: empty `{6}` sheds ONE comma, not both neighbors (the core Gap-2 assertion) |

> **J23/J24 → pure harness (DECIDED 2026-07-17).** These isolate one empty middle part against an
> otherwise-dense name — the exact mid-string orphan-comma path, a 100% pure string-transform
> assertion (`bws_join_template()`, no WP read). They live in `tools/test/join-template-test.php`,
> NOT as `render-tag` rows. Reasons: (1) `render-tag` has NO per-field blanking knob (verified —
> its options are `<tag>`/`--url`/`--loop-item`/`--porcelain`), so blanking one name part on the
> dense jane fixture is impossible without either seeding sparse-in-one-spot fixtures (bloat: a
> staff post existing only to blank one part) or teaching render-tag a synthetic-blank filter
> (scope creep against its "REAL ambient context, not faked" charter). (2) The empty is synthetic
> under EVERY option, so the WP round-trip buys no fidelity the harness lacks — the harness feeds
> `$values` directly and blanks any token trivially. J21 (dense) + J22 (sparse) already prove the
> absorb+assembly integration end-to-end via render-tag; J23/J24 add only the string-collapse edge,
> which the harness isolates best. Revisit ONLY if synthetic-blanking becomes a recurring
> cross-tag need → then a general `--blank=<key>` render-tag feature, not a join hack.

**Unit suffix — height (`/matrix-post-meta/`):**

| # | Tag | Expected |
|---|---|---|
| J11 | `{{join mode:template\|format:{1}'{2}"\|key:height_ft\|2-key:height_in}}` | `5'11"` |
| J12 | `{{join mode:template\|format:{1}'{2}"\|key:height_ft\|2-key:height_in_blank}}` | `5'` — dangling `"` shed (Step 1) |
| J13 | `{{join mode:template\|format:{1}'{2}"\|key:name_generation\|2-key:height_in_blank\|fallback_text:—}}` | `—` — both quote marks shed, all empty → fallback (slot 1 `name_generation` unseeded on this page, slot 2 blank) |
| J14 | `{{join mode:template\|format:{1}'{2}"\|key:height_ft\|2-key:height_in_zero}}` | `5'0"` — absorbed `'0'` renders ([hooks.php:37]); `5'` needs author `''` or the future base zero-empty opt-in |

**Per-slot src / use / site (absorb — `/matrix-post-meta/`):**

| # | Tag | Expected |
|---|---|---|
| J15 | `{{join use:title\|2-use:key\|2-key:role\|sep: / }}` | `Matrix: Post Meta / Captain` — slot 1 = page title, slot 2 = meta field |
| J16 | `{{join key:main_line\|2-src:same\|2-key:booking_line}}` | reads the SAME entity (this page), different key: `(987) 654-3210, 987.654.3210` — `src:same` is a no-op when slot 1 is ambient |
| J16b | `{{join src:ref\|ref:related_staff\|use:key\|key:main_line\|2-src:same\|2-key:contact_email}}` | REAL carry-forward: slot 1 hops the relationship (jane, first target), slot 2 `src:same` re-reads the SAME ref target, different key → `(555) 200-3000, jane@example.test`. Exercises the combining `src`-carry-forward asymmetry (§Source carry-forward). **Shared with try_:** carry-forward is common slot machinery — this fixture (jane's two fields off one ref) serves the future try_ carry-forward rows too. |
| J17 | `{{join key:name_first\|2-src:ref\|2-ref:related_staff\|2-use:title}}` | `Jane, Jane Partner` — slot 2 hops the relationship (text ref parity, first target). Slot 1 `name_first` seeded on `page-matrix-post-meta` (see §Fixture) |
| J18 | `{{join key:name_first\|2-src:site\|2-key:organization_email}}` | `Jane, info@example.test` — site arm present (the try_text site gap NOT repeated) |
| J19 | `{{join srcTermIn:department\|use:title\|limit:2}}` | `Sales, Support` — slot-1 `limit:2` threaded to the slot's text resolve (per-slot `{N}-limit`); term list joined by text's default inner sep `', '`, independent of join's tag-level `sep` |

**Reveal (editor-only — open a join block on the testbed editor):**

| # | Case | Expected |
|---|---|---|
| J20 | slot 2 has neither `key` nor non-default `use` | slot 3 controls hidden (reveal keys on `{prev}-key not_empty` OR `{prev}-use not_empty`, NOT `{prev}-src`) |

> **Fail triage** (fill in on first run, mirror `text-test-matrix.md` §Fail triage): J15/J18/J19
> unlinked-but-right → absorb seam not threading the src:site/list arm; J14 shows `5'` → join
> re-decided `'0'` emptiness (VIOLATES absorb); J12 keeps `"` → Step-1 quote-mark handling regressed.
