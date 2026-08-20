# Plan — Issues #26 + #32 (+ #24 folded): try_email/try_phone via base-derived slot machinery

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

**Status:** READY to build. Branch off `main`. `feature/phone-tag` LANDED (merged #36, 1.10.0 shipped 2026-06-12).
**Issues:** #26 (refactor, prereq) · #32 (enhancement, spine) · #24 (bug, sub-decision — RESOLVED below, no behavior change).

### Reassessment 2026-06-12 (post phone-merge)

- **Premise holds.** All three issues still open. Both #26 drift claims still live:
  - slot source list (registry :369-372) = `current`+`ref`; base `bws_base_source_option` (base-tags.php:587) has `site` → still missing.
  - slot `srcTermIn` (:489-498) merges only `$slot_trigger`; base `srcTermIn` (base-tags.php:626) carries `show_if => not:site` → still no guard.
- **Phone shipping made #32 EASIER — step 6 (the High-risk piece) is already done.** Both base callbacks already delegate per-item compose to shared helpers and are already thin (resolve → validate → `foreach` render-one → `implode($sep)`):
  - email: `bws_email_render_one($address,$subject,$link,$obfuscate)` (email-tags.php:277), callback :237.
  - phone: `bws_phone_render_one($raw,$cc,$link,$stripCc)` (phone-tags.php:462), callback :495.
  - **No risky byte-identical callback surgery left.** try_ dispatch just calls the same `*_render_one` per item. Phase 5 risk drops M-L/High → M/Low; the "highest-risk item" in the #32 risk ledger evaporates.
- **Path corrections** (plan cites shifted/old paths — re-anchor at build, don't trust line numbers verbatim):
  - registry: `includes/classes/class-tag-template-registry.php` (784 lines)
  - preview-helpers: `includes/helpers/preview-helpers.php` (713 lines)
  - email/phone live in `includes/tags/email-tags.php` + `includes/tags/phone-tags.php` (per-tag files, not base-tags.php)
- **Re-verify at build:** resolver post/site fall-through (srcTermIn dispatch now :682-685) still has no site arm → #26 `site`-filter mandate stands.
- **Decision #4 RESOLVED (user, 2026-06-12): ship term_email/term_phone this pass — FULL PARITY.** Phase 8 is in-scope, not gated off.

### Grill outcome 2026-06-12 (source-resolution model — `grill-with-docs`) — RESHAPES the site work

Drove the entity-resolution contract to ground. Outcome lives in **[CONTEXT.md](../../CONTEXT.md) §Language + §L1/L2/L3 + I7**, **[ADR 0002](../../docs/adr/0002-resolved-source-variable-payload.md)**. Key results that change this plan:

- **The site work is NOT a bespoke try_-resolver arm — it is the L1 seam.** A shared source-resolution pipeline: **L1** resolve source → `ResolvedSource[]`; **L2** read field off it (post/term→meta, site→option); **L3** per-tag assemble. The clones `bws_email_resolve_addresses` (email-tags.php:143) + `bws_phone_resolve_numbers` (phone-tags.php:136, "verbatim clone") ARE an inlined L1+L2 — extract once, all consumers share. try_ site slot = "call the shared L1/L2," not an `if site` in the slot loop.
- **`{kind,id}` REJECTED (ADR 0002).** Resolved source carries **variable payload by kind** (post/term=id, site=option namespace / ACF `'option'` object-id, #19 date/search=context value). This is why the seam serves try_/join/phone-ext/#19 from one contract — `{kind,id}` would choke on site + #19's non-id contexts.
- **#19 = the SAME seam, grown.** #19 context-awareness = read targets with an **implicit source** resolved by WP context → L1 grows source kinds incrementally. NOT a separate mechanism. ∴ build order: **L1 seam now (post/term/site kinds) → #19 adds kinds later.** Ship the seam, walk the #19 rail incrementally (each kind independently testable, no big-bang — the testing-cost reason #19 was deferred).
- **Vocabulary (use these terms, retire "entity"):** read target (shorthand *target*) = declared intent; resolved source = L1 where; resolved field = L2a which-field (I2 Model-B home); field value = L2b datum; output destination = I7 list-mode gate. `target cardinality`: plural source (`ref`, `srcTermIn`) → list mode.
- **List-mode divider is DESTINATION-based (I7), not entity-count.** text-flow→joinable, attribute-slot (image URL)→singular, body/document (content)→not joinable. Supersedes the earlier "query-loop / entity-count" framing in the §32 prose below — when editing §32, prefer I7.
- **Two contradictions are REFACTORABLE, not constraints to model around** (user principle 2026-06-12): datetime overloads the post_id param with literal `'option'` (datetime-tags.php:1005) — retire at the seam; `ref` collapses to one target (`bws_extract_post_id`) — the plural-source model makes `src:ref` lists "just work" (free parity win, same shape as try_text srcTermIn list). **The L1 seam doubles as the refactor vehicle for both.**
- **Build-order impact:** the site-arm "item 1" (slot resolver site short-circuit) below is REPLACED by "extract + call the shared L1/L2 seam." Items 2 (dispatcher site read) stays done; items 3/4 still N/A for email/phone. Net: site for try_email/phone = consume the seam; smaller + principled vs a bespoke arm. **Re-frame the §32 site phase as "extract shared L1/L2 resolver (retire the 2 clones), site falls out" — see Phase 5.**

---

## TL;DR

Three issues, one coherent thread:

- **#26** is a **prerequisite** — derive try_ slot `src`/`ref`/`srcTermIn` option definitions from the base builders (`bws_base_source_option()`, `bws_base_traversal_options()`) instead of hand-maintained inline copies. Kills live drift (`not:site` guard already missing from try_ slots). Pure editor-option surface; no resolver change. **Must filter `site` back out** of the derived list — the try_ slot resolver has no site arm, so an exposed `src:site` silently reads current-post meta (wrong-read landmine, not empty). `src:site` in try_ slots stays UNFINISHED until a separate follow-up wires the resolver (see #26 section). #26 ships the plumbing minus the one un-wired value.
- **#32** is the **spine** — register `email`/`phone` as `register_modifier_template()` entries so `try_email`/`try_phone` (and `term_email`/`term_phone`) fall out of existing machinery. Biggest + riskiest piece. **Complication: email/phone are list-producing single-callback tags, not the two-arm `term_fn`/`post_fn` shape the registry assumes** — see §32-RISK.
- **#24** is **RESOLVED as not-a-bug** — investigation below proves both `try_text` (warn) and `try_content` (collapse) are already correct. No behavior change. Email/phone go in the text-like `$needs_key=true` branch when #32 lands.

Order: **#26 first** (clean machinery) → **#32** (rides it) → **#24** folds into #32's preview touch.

---

## #24 — RESOLVED: both behaviors correct, try_content is NOT wrong

Investigated whether `try_content`'s collapse-to-`[Try Content]` is the bug. **It is not.**

| Tag | default `use` | `try_use_no_key_values` | empty-key slot resolves to | correct preview |
|---|---|---|---|---|
| `try_text` | `key` | `['title']` | **nothing** — key-mode, no key → empty | warn `⚠ slot 1 no key` ✓ |
| `try_content` | `content` | `['content','excerpt']` | full post content / term description ✓ | collapse `[Try Content]` ✓ |

Proof — `bws_try_content_post_dispatch()` (base-tags.php:1270-1281): empty `use`/`use:content` with no key returns `bws_post_content_core($post_id,...)` = **full rendered post content**. A working tag. Collapse is the correct preview of working output.
`try_content` lists `content`+`excerpt` as no-key values (base-tags.php:380) — both legitimately need no key.
`try_text` lists only `title` (base-tags.php:343) — its default `use:key` DOES need a key, so empty-key is genuinely unconfigured → warning is the right signal.

**Conclusion:** asymmetry is structural (differing default `use` key-need), not a defect. Issue #24 option **(C) document-as-intentional** is correct. Option (A) "make content warn" would be WRONG (warns on a working tag).

**Action for #24:** no behavior change. When #32 adds email/phone, put them in the text-like branch (`$needs_key=true`, no-key values empty) since their default is key-mode with no native default field — empty slot resolves to nothing → warning correct & consistent with try_text. Add one doc line to `docs/editor-tag-previews.md` recording the per-template `$needs_key` rationale. Close #24 referencing this analysis.

---

## #26 — Derive try_ slot source/traversal options from base builders

### Current drift (live, confirmed)

- Base `bws_base_source_option()` (base-tags.php:579-592) `src.options` = `current`, `ref`, **`site`**.
- try_ slot `$base_source_options` (class-tag-template-registry.php:369-372) = `current`, `ref` only. **`site` missing.**
- Base `srcTermIn` carries `show_if => ['src' => 'not:site']` (base-tags.php:626). try_ slot `srcTermIn` (class-tag-template-registry.php:489-498) has **no `not:site` guard.** Second live drift.

(Both moot *today* only because slots lack the `site` value — but that's exactly the coupling #26 removes.)

### Scope — NARROW to `src` + `ref` + `srcTermIn` only

Do **not** absorb `key`/`use` into the base-derive path. Issue #26 lists `key`,`use` as "likely applies" — narrowing is correct.

**Precise derivability criterion** (sharper than "try_-native semantics"): a child option (`ref`,`key`) is base-derivable iff **its parent selector never defaults into the child-triggering value.**
- `ref` PASSES — parent `src` defaults to `current` (never `ref`); `ref` hidden by default, shown only on explicit `src:ref`, *identical both slots* → flat `show_if => [{N}-src => 'ref']`, derivable (requalify key per §Transform.1).
- `key` FAILS — parent `use`'s default is **implicit** (unserialized `''`-wire → key-mode for text/image at slot 1), so slot 1 shows `key` on the default via `not_in:<no-key>`, while slot ≥2 `''`-wire means `same`/inherit → `in:<key-needers>` whitelist (excludes `same`). That slot-1-vs-≥2 `show_if` asymmetry (:544-548) is irreducible.
- `use` itself: its VALUE LIST already flows from `$tpl_options['use']['options']` (NOT hand-copied — no drift). Slot-native parts are only the `same`-prepend + trigger + the `key` show_if it drives.

**`key`/`use` carve-out is OPEN, mechanism TBD — do NOT pre-build a seam now.** The asymmetry above stems from implicit-mode (default is *unserialized*, NOT a `default` token — that stays implicit; only `{{image}}` is the current explicit exception) combined with slot carry-forward. What (if anything) lets slot-1 and ≥2 unify so `key`/`use` become base-derivable is **unresolved**. The broader control cluster ([`handoff-1-source-analog-and-contextual-controls.md`](handoff-1-source-analog-and-contextual-controls.md) — esp. Decision 5 stale-field cleanup) *may* absorb it, but that's unscheduled + multi-phase; no lever is asserted here. #26 absorbs the three options whose criterion passes today; `key`/`use` stay hand-maintained until a future phase resolves the mechanism.

### Transform

1. New helper `bws_slot_qualify_show_if( array $show_if, int $n, array $sibling_keys ): array`
   - Walk condition keys; for any key in `$sibling_keys` (`src`,`ref`,`srcTermIn`), rewrite `k` → `{N}-k` (slot 1: leave bare). Condition **values** (`'ref'`, `'not:site'`) unchanged.
   - ~20 lines. Pure array transform.
2. In the slot loop (class-tag-template-registry.php:415+):
   - Pull `bws_base_source_option()['src']['options']`. For `$n >= 2`, prepend `['value'=>'same','label'=>'Same as Previous Source']`. Re-key to `{N}-src`. Keep `_strip_default`.
   - Pull `bws_base_traversal_options()`. For each of `ref`, `srcTermIn`: re-key to `{N}-ref`/`{N}-srcTermIn`, run `bws_slot_qualify_show_if` on its `show_if`, then `array_merge` the slot-visibility `$slot_trigger` (a separate `show_if_any` key — must coexist, not overwrite).
3. Delete the local `$base_source_options` array (:369) + inline `ref` block (:469-476) + inline `srcTermIn` block (:489-498).

### Risk ledger (#26)

- **`show_if` + `show_if_any` coexistence** — base option carries `show_if`; slot machinery adds `show_if_any` ($slot_trigger). They're distinct keys; merge must preserve both. No base option currently carries `show_if_any`, so no collision — but assert / comment this.
- **Carry-forward semantics** unaffected — those live in the resolver + preview, not option defs. This is option-surface only.
- **`try_per_slot_key`/`per_slot_use` key block** (:526-561) — leave untouched. Out of scope per narrowing.
- **`_strip_default`** on `src` — base has it, slot must keep it (slot-1 first-option strip).

### Verify (#26)

- Diff registered try_ tag option JSON before/after for `current`/`ref` slots → **identical** (regression gate).
- Confirm `site` now appears in try_ slot source dropdowns.
- Confirm slot `srcTermIn` hidden when slot `src:site` (the newly-derived `not:site` guard).
- Editor smoke: try_text / try_content / try_image slot 1+2 render unchanged.

### `src:site` in try_ slots is UNFINISHED — #26 must NOT expose it

**This is not a "dead option," it is a silent wrong-read.** Verified against the resolver.

The try_ slot resolver (class-tag-template-registry.php:684-728) has exactly **two dispatch arms**: srcTermIn → term path (:684), and post-based current/ref (:705-722). **There is no `site` arm.** A slot with `src:site` is not caught anywhere, so it falls into the post path → `bws_resolve_post_by_source($slot_opts)` (base-tags.php:648). That function also has no `site` branch: `'site'` is not `'ref'`, so it drops to the `current` path (:677-686) and returns **`get_the_ID()`** — the current post.

Net effect: a try_ slot set to `src:site` reads the field off **the current post**, silently — e.g. `key:tagline` reads post meta `tagline`, not the site option. Wrong data, no error. A correctness landmine, not an empty result.

**So #26 (option-surface only) MUST filter `site` out of the derived slot source list.** This is a *guard against the wrong-read*, not cosmetic hiding of a non-functional choice:

```php
// #26 slot-src derivation — drop 'site' until the resolver site-arm lands.
// Without this, a slot src:site silently reads current-post meta (resolver has no
// site arm → falls through to get_the_ID()). See follow-up issue.
$slot_src_options = array_values( array_filter(
    $base_src_options,
    static fn( $o ) => 'site' !== ( $o['value'] ?? '' )
) );
```

#26 still gets its win — drift killed (`site` now flows from one base source of truth), `not:site` srcTermIn guard derived correctly. It withholds `site` **by default** from the generic derived slot list; per-template **re-allow** (email/phone, see below) is an explicit opt-in once that template's site arm is wired.

**The filter is now a per-template ALLOW, not a global block** (scope correction 2026-06-12). User requirement: **try_email/try_phone must NOT ship without site** — site is the canonical fallback slot for a contact-chain (personal → site-wide address). So #32 re-allows `site` for the email/phone templates specifically, gated on the slot-resolver site arm (item 1 below). datetime/text/image try_ slots stay filtered until their heavier site-arm work (items 3/4) lands. Mechanism: derive generic list with `site` filtered, then for templates flagged site-wired, append `site` back. NOT global removal of the filter.

### `src:site` for try_email / try_phone — IN SCOPE for #32 (reduced to item 1 only)

**The site-arm work collapses for email/phone.** Of the four-point list below, only **item 1** is new work; item 2 is already done, items 3 & 4 are N/A:

| Site-arm item | email/phone |
|---|---|
| 1. slot resolver site arm | **ONLY new work** — one `src:site` short-circuit in the registry slot loop |
| 2. dispatcher `src:site` branch | **DONE** — `bws_email_resolve_addresses` (email-tags.php:149) + `bws_phone_resolve_addresses` (phone-tags.php:142, "verbatim clone") already read site via `bws_site_read_option`. The try_ dispatch calls the same resolve → inherits site-awareness free. |
| 3. datetime option-id fork | **N/A** — datetime-only concern |
| 4. site link-entity (`home_url` wrap) | **N/A** — email/phone wrap `mailto:`/`tel:` from the resolved value itself; no permalink entity, no `home_url`. |

So item 1 (the resolver short-circuit) is the whole job. On a slot `src:site`: set `$slot_opts['src']='site'`, call the try_core_fn with `$post_id=0` (site has no entity), let the already-site-aware resolve read the option, per-item compose (`bws_*_render_one`) wraps `mailto:`/`tel:` as normal. **Verify at build:** slot resolver threads `src` through to the dispatcher's resolve (which reads `$options['src']==='site'`).

**Phase:** add a #32 phase "slot resolver site arm + re-allow `site` on email/phone templates" — BEFORE the email/phone template phases (their site slot depends on it). Gate: a try_email slot `src:site|key:<option>` reads the site option, NOT current-post meta.

### The FULL `src:site`-in-try_ fix (deferred — datetime/text/image remainder)

For the OTHER try_ families (datetime, text, image, content), `src:site` is **still deferred** — they need the heavier items. `src:site` in those slots is **not done** until ALL of:

1. **Site arm in the slot resolver** (class-tag-template-registry.php, before the post path at :704) — site has no entity ID; like srcTermIn short-circuits to terms, site short-circuits to a direct option read:
   ```php
   if ( 'site' === $last_src ) {
       $slot_opts['src'] = 'site';
       $result = $cf( 0, $slot_opts, $inst );   // try_core_fn must honor src:site
       if ( '' !== $result && false !== $result ) {
           // site link-entity (home_url / none) — see item 4.
           return $result;
       }
       continue;
   }
   ```
2. **Every `try_core_fn`/`try_term_fn` grows a `src:site` branch** — today's dispatchers (bws_try_text_post_dispatch :1242, etc.) take `$post_id` and read post/term meta. Each must detect `src:site` and route to the site reader (`bws_site_resolve_value` / `bws_site_read_option`, base-tags.php:846), ignoring `$post_id`. This is the bulk of the work (one branch per template).
3. **datetime option-id sub-fork** — datetime try_ tags resolve an entity ID for date fields; `src:site` needs the ACF-'option' object-id path (mirrors src:site DT-1 in the base datetime tags).
4. **Site link-entity handling** — when `supports_link_wrap`, the winning site value wraps to `home_url()` (or no-link), not a post/term permalink. The `bws_wrap_with_link` call (:724) assumes a post/term entity ID; site needs its own entity kind.

For datetime/text/image, the filter stays until 1-4 land for those families (item 1 is shared — built in #32 for email/phone — but items 2/3/4 are per-family). Re-allowing `site` on those templates is the last step of their follow-up, gated on their 2-4. Email/phone exit this early (item 1 + already-done item 2; 3/4 N/A) and ship site THIS pass.

**Filing decision (updated 2026-06-12):** the **email/phone site arm (item 1) is no longer deferred — it's in #32.** The remaining deferred work is `src:site` for **datetime/text/image** try_ families (items 2-4 per family). File THAT as its own issue once #26+#32 land, cross-referencing #26 (the per-template filter) + the item-1 resolver arm #32 builds (which those families reuse). No existing issue covers it (#28 `src:site→ref` is base-tag path; #30 datetime list-mode is the list axis, distinct).

**Adjacency to note when filing #32 work:**
- **#30** (datetime list mode) is the **base-tag axis** of the same list-mode gap the §32 list-seam fixes on the **try_ axis**. They are siblings, NOT a fold-in (different callbacks, and #30 carries a `rangeSep` interaction §32 doesn't touch). Two axes of one pattern:

  | | base tag | try_ tag |
  |---|---|---|
  | text/title | ✓ done (`implode` base-tags.php:797) | ✗ **§32 fixes** |
  | datetime | ✗ **#30** (`bws_base_datetime_single_callback` short-circuits first-match in srcTermIn loop) | ✗ rides §32 seam *iff* resolve exposes pre-join list |
  | email/phone | ✓ done | ✗ **§32 fixes** |

  Shared design: collect-then-join + `limit`/`sep` gated `show_if_any{srcTermIn:not_empty, src:ref}`. **Coupling:** if #30 lands (base datetime collect-then-join, with the per-item compose producing finished date/range strings), datetime's `try_*` rides the §32 join seam for free — the slot returns finished composite strings, machinery joins them (I6 transparency, no special-casing). So **sequence #30 with §32's try_text/title list work** (Phase 3) — same machinery, do datetime base + try_ together or back-to-back. Don't fold #30 INTO §32; cross-reference and share the collect-then-join helper.
- **#28** (src:site→ref) shares the "src:site needs more wiring" theme but is base-tag scope — keep separate from the try_-resolver follow-up above.

---

## #32 — try_email + try_phone via full template-registry port

### The design model — try_ is a TRANSPARENT fallback wrapper (settled via grill 2026-06-09)

**Governing invariant: [CONTEXT.md](../CONTEXT.md) I6.** A `try_` chain selects WHICH slot's result surfaces (first non-empty slot wins); it does not compose, decompose, or transform that result. A slot resolves **identically to the same underlying tag used standalone** — full parity. All composition (mailto/tel link-wrap, extension append, range formatting, list-join) happens INSIDE the slot's own resolve/core. try_ machinery is composition-blind.

This is the **Resolution X / A2** outcome of the grill (the earlier `try_item_fn` per-item-transform hook was **considered and CUT** — composition-in-resolve is simpler and is the truest expression of transparency).

### What a slot returns: finished strings

A try_ slot's dispatch returns `array<string>` — **fully-composed, finished per-item strings** (already link-wrapped, already extension-appended, already range-formatted, exactly as the standalone tag would emit each result). try_ machinery never sees structure, never wraps, never transforms.

| Concern | Where it happens |
|---|---|
| resolve raw value(s) for a slot | slot's own resolve/core (`bws_email_resolve_addresses`, text core, etc.) |
| per-item composition (mailto/tel wrap, ext append, range fmt) | **inside the slot's resolve/core** — SAME code the base callback uses |
| pick first non-empty slot | try_ machinery |
| join a winning slot's finished strings (`implode($sep)`, `limit`) | try_ machinery (the ONLY thing it adds) |

### Output-shape framing (locked vocabulary — tag-reference §Output shape)

- A slot's output is one **single-result** string, which MAY be a **composite string** (datetime_range `start–end`, phone+ext) — try_ is transparent to composition.
- A slot in **list mode** yields finished per-item strings that try_ joins with `sep` — try_ is transparent to list mode (a try_ tag that truncated a list its base tag would join is a **parity defect**, I6).
- **query loop** (repeated entity markup — staff cards) is OUT of try_ scope entirely (I6 scope boundary). Not addressed by this plan; tracked separately as the unbuilt fallback-query capability.

### Latent gap this fixes

`text`/`title` base tags already list (`implode($sep,$out)` base-tags.php:797,930 on srcTermIn/ref), but the try_ dispatchers drop `sep`/`limit` (bws_try_text_post_dispatch :1242 calls cores directly, no join) — so `try_text`/`try_title` **truncate today**. Per I6 that is a parity defect. The machinery join (below) closes it: a winning slot's finished string-list is joined, identical to the standalone tag.

Backward compatibility: a slot returning ONE finished string + no `sep` = today's behavior (join of a 1-element list is the element verbatim, no separator). Existing try_text/try_content/try_image output unchanged.

### Steps (#32)

1. **Land #26 first.** Email/phone slots then get correct base-derived `src`/`ref`/`srcTermIn` free.
2. **Add the list-join seam to `generate_base_try_tags()`** — slot dispatch returns `array<string>` of **finished** strings; machinery picks the first non-empty slot, then `implode($sep, array_slice($items, 0, $limit))`. **No `try_item_fn`** — slots arrive fully composed (I6). `sep`/`limit` recognized as trailing options when present in `tpl['options']`. **Adapt existing dispatchers to the array contract** (text/content/image return `[ $finished_string ]`). Backward-compatible: 1-element list + no `sep` = today's verbatim value.
3. **Give try_text/try_title their list (closes the I6 parity defect):** refactor text/title cores so the try_ path receives the already-composed per-item strings (the cores build them today before `implode`; expose pre-join, OR have the try_ dispatcher call the same per-item render the core uses). Add `sep`/`limit` to their try_ trailing options. Low-risk standalone win; can land in its own commit before email/phone.
4. **Slot resolver site arm + per-template `site` re-allow (PREREQUISITE for email/phone site — user requirement).** Add a `src:site` short-circuit to the slot loop (registry, before the post path): on a slot `src:site`, set `$slot_opts['src']='site'`, call the try_core_fn with `$post_id=0`, let the already-site-aware resolve read the option, return the finished string(s). Re-allow `site` in the email/phone templates' derived slot source list (append back past the #26 filter). Items 3/4 of the full site-arm (datetime ID fork, `home_url` link-entity) are N/A for email/phone. **Gate:** try_email slot `src:site|key:<option>` reads the site option, not current-post meta. This phase lands BEFORE the email/phone template phases.
5. Register `email` modifier template:
   - `key=email`, `supports_try=true`, `is_image=false`.
   - `try_per_slot_key=true`, `try_per_slot_use=false` (email has no `use` enum — single key-mode). `try_use_no_key_values=[]`.
   - `try_core_fn=bws_try_email_post_dispatch` / `try_term_fn=bws_try_email_term_dispatch` — each returns the slot's list of **finished mailto-wrapped (or noLink plain) address strings**. Internally: `bws_email_resolve_addresses` → per-item compose (mailto/obfuscate/subject/noLink) → return `array<string>`. **The per-item compose is the SAME function the base `{{email}}` callback uses** (extracted, shared — see step 6), not a try_-private copy.
   - `options`: `key` + trailing `subject`/`noLink`/`limit`/`sep`/`fallback`. `subject`/`noLink` `show_if` carried as-is (chain-level — these are inputs to the slot's own compose, not try_ machinery).
   - `visibility`: same `tagName NOT_IN [a,button,img,picture]` — **must thread through `generate_base_try_tags()`** to the `try_email` registration. Check: does the registry currently pass `visibility`? If not, add a `visibility` passthrough from template → try_ tag. (VE3 / VP-vis invariant — try_email/try_phone must keep the gate.)
6. Register `phone` modifier template — symmetric; slot dispatch returns finished `tel:`-wrapped (or plain) number strings, strip_leading_cc + model-C separator applied per item inside the slot's compose (shared with base callback).
7. **Per-item compose is ALREADY shared — no extraction needed (corrected 2026-06-12).** Phone shipped with `bws_phone_render_one` (phone-tags.php:462); email has `bws_email_render_one` (email-tags.php:277). Both base callbacks already `foreach`→render-one→`implode($sep)`. The try_ dispatch just CALLS the existing `bws_*_render_one` per item. No callback surgery, no byte-identical-extraction risk (the original High-risk item — now gone). Verify the try_ dispatch's resolve→render-one→array path matches the base callback's per-item output for parity.
8. **Preview** (folds in #24): add `email`/`phone` to `bws_build_try_preview_label` — text-like `$needs_key=true` branch (preview-helpers.php), `try_use_no_key_values=[]`. Add `try_preview_field_part` / `template_label` cases. Empty slot → `⚠ slot N no key` (correct, per #24 analysis).
9. **term_email/term_phone** fall out of `register_modifier()` once templated. **DECIDED (2026-06-12): ship them — full parity this pass.** No gate flag. Add preview cases + register/verify rows for both.

### Risk ledger (#32)

- ~~**Base-tag regression** — extract per-item compose from shipped callbacks~~ **GONE (2026-06-12):** compose already shared (`bws_*_render_one` exist, base callbacks already delegate). No extraction, no byte-identical-surgery risk. This was the original highest-risk item; it evaporated when phone shipped with the helper already factored out.
- **Slot resolver site arm (new step 4)** — touches the live slot resolver to add a `src:site` short-circuit. Verify it threads `src` to the dispatcher resolve and does NOT regress the existing post/srcTermIn arms (current/ref/term slots unchanged). The already-site-aware `bws_*_resolve_addresses` does the read; risk is in the resolver wiring, not the read.
- **Array contract on existing dispatchers** — adapting text/content/image dispatchers to return `[ $finished_string ]` (step 2) touches *shipped* try_ behavior. Join of a 1-element list with no `sep` must equal today's verbatim value (no trailing separator, no wrapping). Regression-gate existing try_ tags.
- **List-wins semantics — SETTLED (I6):** a slot wins when its finished-string list is non-empty; the WHOLE list (sliced to `limit`, joined via `sep`) surfaces — transparency to the slot's own list mode. Truncating to first would be a parity defect.
- **`visibility` passthrough** — verify try_ registration threads it (VE3/VP-vis). Likely a gap today (text/content have no visibility gate, so untested path).
- **subject `:`/`|` escaping (VE2)** — `bws-format-input` survives GB parseTag; the unescape happens before the compose function reads `subject`. Since try_ shares that compose (step 6), the escape path is identical — confirm no double-unescape in the try_ option pipeline.
- **Obfuscation** — `bws_email_obfuscation_enabled()` global gate lives inside the shared compose; applies identically to base + try_. No per-slot change.

### Verify (#32)

- `{{email}}` / `{{phone}}` base output unchanged (diff against 1.10.0).
- `try_email`/`try_phone` register, appear in editor, slots derive src from base (via #26).
- Multi-slot chain: personal meta email → term email → **site option email** → fallback. First non-empty slot wins; its finished string(s) surface verbatim.
- **Site slot WORKS (hard gate, user requirement):** a try_email slot `src:site|key:<option>` reads the site option (not current-post meta). Site appears in email/phone slot source dropdowns; still absent from datetime/text/image try_ slots (deferred).
- Transparency check: a single `try_email` slot resolving a term-hop list outputs the SAME joined string as the standalone `{{email}}` with identical options (I6 parity).
- Visibility gate present on try_email/try_phone.
- Preview labels: configured → field/source render; empty key → `⚠ slot N no key`.
- term_email/term_phone register + preview (full parity, decided).

---

## Open decisions (resolve before build)

1. **#26 site-in-slot:** ~~surface vs filter~~ **SETTLED — filter out BY DEFAULT, per-template re-allow.** #26 filters `site` from the generic derived list (exposing it = silent current-post wrong-read, resolver has no site arm). **BUT (2026-06-12) email/phone re-allow it in #32** via the slot-resolver site arm (step 4) — user requirement, site is the canonical contact-fallback slot. datetime/text/image stay filtered (their site-arm deferred). So the filter is a per-template allow, not a permanent global block.
2. **#32 list-wins semantics:** ~~whole list vs first~~ **SETTLED — whole list (CONTEXT.md I6 transparency).** A winning slot's full finished-string list surfaces, joined; truncation = parity defect.
3. **#32 design model:** ~~`try_item_fn` hook vs in-resolve~~ **SETTLED — composition-in-resolve (Resolution X / A2).** try_ machinery only picks-slot + joins; all wrap/compose lives in the slot's resolve, shared with the base callback. `try_item_fn` cut. (Grill 2026-06-09 → I6.)
4. **#32 term_ variants:** ~~ship vs gate~~ **SETTLED 2026-06-12 — ship term_email/term_phone, full parity this pass.**
5. **#32 scope confirm:** ~~full port vs lighter adapter~~ **SETTLED — full port.** Phone already shipped with `bws_*_render_one` per-item helpers + base callbacks already delegate, so the "extract shared compose" step is a NO-OP (helpers exist). Base-refactor regression risk GONE.
6. **try_email/phone site slot:** **SETTLED 2026-06-12 — REQUIRED, in #32 (not deferred).** User: don't ship try_email/phone without site (canonical contact fallback). Scope = slot-resolver site arm (item 1) only; dispatcher site-read already done (`bws_*_resolve_addresses`), datetime/permalink items N/A. datetime/text/image site-in-try_ stays deferred.

## Sequencing & sizing

| Phase | Issue | Size | Risk | Gate |
|---|---|---|---|---|
| 1 | #26 helper + slot derive (filter `site` by default) | S-M | Low | option-JSON diff identical for current/ref |
| 2 | #32 list-join seam (pick-slot + sep/limit join, NO item hook) + array contract on existing dispatchers | S-M | Med | existing try_ tags byte-identical (1 item, no sep) |
| 3 | #32 try_text/try_title list parity (closes I6 defect) | S | Low | try_text lists on srcTermIn, single-result unchanged |
| 4 | #32 visibility passthrough to try_ | S | Low | try_ gate present |
| 5 | #32 **extract shared L1/L2 source-resolver** (retire the 2 clones `bws_email_resolve_addresses`/`bws_phone_resolve_numbers`), try_ slots + base callbacks consume it → **site falls out** + `src:ref` plural lists "just work" (ADR 0002 seam). Re-allow `site` on email/phone templates. | M | Med | base `{{email}}`/`{{phone}}` byte-identical; slot `src:site` reads option not current-post; `src:ref` list no longer collapses to first; current/term arms unregressed |
| 6 | #32 email template — try_email dispatch calls existing `bws_email_render_one` per item (compose already shared, site read inherited) | M | Low | base email parity; site slot resolves |
| 7 | #32 phone template (twin of 6) — reuse `bws_phone_render_one` | M | Low | base parity; site slot resolves |
| 8 | #24+#32 preview email/phone cases + doc | S | Low | empty-slot warns, configured renders |
| 9 | #32 term_email/term_phone — **IN SCOPE (full parity, decided)** | S | Low | term_email/term_phone register + preview |

Each phase = own commit, version bump per project workflow. Phase 1 lands independently (closes #26) even if #32 stalls. **Phase 5 (site arm) is a hard prereq for 6/7** — email/phone don't ship until the site slot resolves (user requirement).
