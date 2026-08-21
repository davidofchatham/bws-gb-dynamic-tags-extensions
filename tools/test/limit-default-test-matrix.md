# `limit` default + UNLIMITED test matrix

Named for the PROPERTY under test, not a tag family (precedent:
[`fw52-order-test-matrix.md`](fw52-order-test-matrix.md)) — `limit` spans five tag families
and four code paths, and the regression this matrix guards is cross-cutting.

**What changed (1.17.0).** `limit` is now interpreted in ONE place,
[`bws_clamp_limit()`](../../includes/helpers/field-helpers.php), and `0` means **UNLIMITED**:

| Input | Before 1.17.0 | 1.17.0 |
|---|---|---|
| unset / `''` | 1 | **1 on FLAT wire (unchanged — what the L1 rows pin); 0 on CHAIN wire (§L4)** |
| non-numeric (`abc`) | 1 | 1 (explicit `is_numeric()` gate) |
| `0` | 1 (silently clamped) | **UNLIMITED** |
| `-1`, `-3` | 1 (silently clamped) | **UNLIMITED** (parse tolerant, emit `0`) |
| `1`, `5`, `999` | same | same (no ceiling) |

**Why these rows exist.** Opening up `0` moves the meaning of a value that already saves and
survives on the wire, and the tag families reach the rule by four different paths. The
regression it invites is quiet: **the top-level link gate is COUNT-BASED** (a single result is
link-wrapped, a joined multi-value composite is not), so an accidental 1→many flip does not
merely lengthen output — **it drops the anchor while the text still reads plausibly**.
⇒ **Every L1 row asserts TWO things: exactly one value AND the link present.**

**Four code paths, one rule.** A row is worth having per PATH, not per tag:

| Path | Site | Rows |
|---|---|---|
| the seam — `bws_resolve_field_values()` | `field-helpers.php` | text, title, email, phone |
| the shared list fold — `bws_collect_value_list()` | `field-helpers.php` | datetime_single / _range |
| try_ slot dispatch (own implementation) | `class-tag-template-registry.php` | L1.7, L3.4 |
| `bws_try_join_items()` | `base-shared.php` | covered via the try_ rows. **No longer a clamp site** (1.17.0): it holds no options, so it structurally cannot know which spelling the tag uses, and it now takes an already-resolved int |

Since 1.17.0 the DEFAULT each of those sites passes comes from one more function,
[`bws_limit_default()`](../../includes/helpers/field-helpers.php) — see §L4. No call site is
new-or-old; all of them serve both eras, so the era is read off the tag's wire rather than
chosen per site.

`{{content}}` is **NOT list-capable** (registers no `limit`) — out of scope BY DESIGN. Do not
add rows for it.

- **Pure algorithm:** `php tools/test/limit-clamp-test.php` (clamp rule + the caller
  slice/early-break contracts) and `php tools/test/try-join-seam-test.php` (the join seam).
  Those pin the rule; this matrix confirms the LIVE reads they cannot reach.
- **Visible blocks:** every row below is generated as a browsable/editable GB block on
  `matrix-post-meta` (blocks.php `matrix_post_meta` builder, sections `Limit L1/L2/L3`).

**Fixture state assumed** (`core-structures` blueprint — no blueprint change needed):
`matrix-post-meta` carries department terms **Support, Sales** (both valid) and
`related_staff` = **Jane Partner, Tom Associate** (jane FIRST — the unset-limit rows pin her).

**How to check:** reseed (`bin/seed.sh testbed core-structures`), then
`curl -sk "https://testbed.test/matrix-post-meta/?nocache=$RANDOM"` — the cache bust is
mandatory or brand-new rows read as missing. Single rows can also be run through
`bin/wp.sh testbed bws render-tag '{{…}}' --url=https://testbed.test/matrix-post-meta/`.

## L1 — unset `limit` MUST stay 1 (the regression floor)

Each row asserts **one value AND link present**. A row that renders two values has flipped the
default; a row that renders one value with no `<a>` has broken the count-based gate.

| Row | Tag | Expected | Path |
|---|---|---|---|
| L1.1 | `{{text srcTermIn:department\|use:title\|linkTo:permalink}}` | ONE dept name (`Sales` — first term), wrapped in `<a>` | seam |
| L1.2 | `{{text src:ref\|ref:related_staff\|use:title\|linkTo:permalink}}` | `Jane Partner` only, wrapped in `<a>` | seam |
| L1.3 | `{{title src:ref\|ref:related_staff\|linkTo:permalink}}` | `Jane Partner` only, wrapped in `<a>` | seam |
| L1.4 | `{{title srcTermIn:department\|linkTo:permalink}}` | ONE dept name, wrapped in `<a>` | seam |
| L1.5 | `{{datetime_single src:ref\|ref:related_staff\|key:event_datetime\|linkTo:permalink}}` | ONE date (jane's), wrapped in `<a>` | list fold |
| L1.6 | `{{datetime_range srcTermIn:department\|startKey:event_date}}` | ONE date, no `; ` separator present | list fold |
| L1.7 | `{{try_text srcTermIn:department\|use:title}}` | ONE dept name, no `, ` separator | try_ dispatch |
| L1.8 | `{{email src:ref\|ref:related_staff\|key:contact_email}}` | ONE `mailto:` anchor (jane@example.test) | seam |
| L1.9 | `{{phone src:ref\|ref:related_staff\|key:main_line}}` | ONE `tel:` anchor (jane's line) | seam |
| L1.10 | `{{join srcTermIn:department\|use:title\|2-key:blurb}}` | `Sales, Sales handles quotes, renewals and the annual customer roadshow.` — slot 1 = ONE dept name, joined to THAT SAME term's blurb | seam (per-slot). The key must exist on the INHERITED source: this read `2-key:role` until 2026-08-21 and could never have joined anything, because `role` is post meta and slot 2 inherits slot 1's TERM. It passed for months on a bare `Sales` looking plausible |

**email / phone read differently and that is correct:** both wrap EVERY value in its own
`mailto:`/`tel:` anchor, so link presence there is not count-gated. L1.8/L1.9 are still count
rows — one anchor, not two.

## L2 — explicit values still behave

| Row | Tag | Expected |
|---|---|---|
| L2.1 | `{{text src:ref\|ref:related_staff\|use:title\|limit:2\|linkTo:permalink}}` | BOTH names, `, `-joined, **NO** `<a>` (multi-value composite is unwrappable) |
| L2.2 | `{{text src:ref\|ref:related_staff\|use:title\|limit:1\|linkTo:permalink}}` | `Jane Partner`, wrapped — explicit 1 === unset 1 |
| L2.3 | `{{text srcTermIn:department\|use:title\|limit:99}}` | both dept names — no ceiling, limit > count is not an error |

## L3 — the semantics change

| Row | Tag | Expected | What it proves |
|---|---|---|---|
| L3.1 | `{{text src:ref\|ref:related_staff\|use:title\|limit:0}}` | BOTH names (`Jane Partner, Tom Associate`) | `0` = unlimited. **Rendered ONE value before 1.17.0** — the deliberate break |
| L3.2 | `{{text src:ref\|ref:related_staff\|use:title\|limit:-1}}` | BOTH names | `-1` parses as unlimited (GB Posts-Per-Page convention), tolerated not emitted |
| L3.3 | `{{text src:ref\|ref:related_staff\|use:title\|limit:abc\|linkTo:permalink}}` | `Jane Partner` only, wrapped in `<a>` | the `is_numeric()` guard — a typo resolves to the DEFAULT, never to "no limit". `(int)'abc' === 0`, so without the guard this row fans out |
| L3.4 | `{{try_text srcTermIn:department\|use:title\|limit:0}}` | both dept names, `, `-joined | the try_ dispatch honors unlimited AND does not break out of the term hop after the first item (the `$slot_max &&` guard) |
| L3.5 | `{{datetime_single srcTermIn:department\|key:event_date\|limit:0}}` | both dept event dates | the list fold slices with `?: null` |
| L3.6 | `{{text srcTermIn:department\|use:title\|limit:0\|linkTo:permalink}}` | both names, **NO** `<a>` | unlimited feeds the same count gate — it drops the anchor legitimately, because the output really is multi-value |

## L4 — the SPELLING selects the default (1.17.0, base-tag source chains)

**The unset default is no longer one number.** Flat wire bounds its resolved-source list at 1;
chain wire does not. That single rule is the whole compatibility mechanism for base-tag source
chains, and it is chosen because it works on wire NO MIGRATION CAN REACH — a draft nobody opens,
a block widget the content scanner never sees, a tag stored inside an ACF field.

So the L1 rows above are only half the floor: they pin that FLAT wire still bounds at one. These
rows pin the other half, and every one is a **pair of spellings for the same source**.

⇒ **Rows here assert the link too**, for the same count-based reason L1 does — chain wire
defaulting to many means link-wrapping differs by spelling, on new wire.

> **RE-MEASURED 2026-08-21** on the 1.18.0 build, `/matrix-post-meta/` — L1, L2, L3 and L4 all
> render as stated. Worth the re-run rather than trusting the 2026-08-05 stamp: ADR 0007 changed
> what a limit COUNTS (usable sources, field population removed), and these rows are where a
> miscount would show. It also caught L1.10, whose expectation had never been achievable.
>
> **MEASURED 2026-08-05** against the branch on `/matrix-post-meta/`; every row below is an
> observed value. The two that carry the whole rule: L4.1 renders one name wrapped in `<a>`, L4.2
> renders both names with NO `<a>` — same source, different spelling, and the anchor is legitimately
> gone because the output really is multi-value.
>
> **L4.10 / L4.11 measured 2026-08-07 on the testbed**, when the limit moved from the tag onto the
> steps. They are the rows that pin what migration WRITES, as opposed to what a hand-authored limit
> does — L4.3 used to serve both and now serves only the second. Each migrated row is paired with the
> flat original it replaces AND with an unlimited partner, so neither pair can pass vacuously.

> **OPENING one of these rows in the editor MIGRATES it** (#62, 2026-08-07): a tag-level `limit` is
> legacy by POSITION, not by spelling, so a numeric one on chain wire is absorbed onto the step it
> bounds and the key is deleted. Affects L4.3 here, and F8.4 / F12.1-F12.3 in the fold matrix.
> Output is unchanged in every case, and a reseed restores the authored wire. Rows that STAND DOWN
> are untouched: L4.6 (non-numeric — there is no era default to carry on chain wire) and L4.8/L4.9
> (the chain already states its own step limit). So eyeball these on the FRONT END, or reseed after
> opening one; the editor-side behaviour is `fold-test-matrix.md` §F14.14b, whose subject is L4.3.

| Row | Tag | Expected | What it proves |
|---|---|---|---|
| L4.1 | `{{text src:ref\|ref:related_staff\|use:title\|linkTo:permalink}}` | `Jane Partner` only, in `<a>` | FLAT, unset — unchanged from L1.2. The floor |
| L4.2 | `{{text src:refs,related_staff\|use:title\|linkTo:permalink}}` | BOTH names, **NO** `<a>` | CHAIN, unset — unlimited. The anchor is legitimately gone: the output really is multi-value |
| L4.3 | `{{text src:refs,related_staff\|use:title\|limit:1\|linkTo:permalink}}` | `Jane Partner` only, in `<a>` | an EXPLICIT tag-level value beats the spelling-selected default — ordinary option precedence. NOT what a migrated tag looks like: since the limit moved onto the steps, a migrated tag is L4.10. This row is hand-authored wire, and it still has to work — until it is OPENED, which absorbs the `1` onto the step and makes it L4.10 (see the note above; reseed to get it back) |
| L4.4 | `{{text srcTermIn:department\|use:title}}` | ONE dept name | FLAT term hop, unset — still 1 |
| L4.5 | `{{text src:terms,department\|use:title}}` | `Sales, Support` | CHAIN term hop, unset — unlimited |
| L4.6 | `{{text src:refs,related_staff\|use:title\|limit:abc}}` | BOTH names | the `is_numeric()` guard falls to the CHAIN default, not to 1. A garbage value must not resurrect the legacy limit on chain wire |
| L4.7 | `{{text src:current\|key:role\|linkTo:permalink}}` | `Captain`, in `<a>` | a root-only chain does not fan, so nothing changes. `src:current` is chain-shaped in the control but a plain token on the wire |
| L4.8 | `{{text src:refs,related_staff,limit(1)\|use:title\|linkTo:permalink}}` | `Jane Partner` only, in `<a>` | the PER-STEP limit and the tag-level one are different quantities. This bounds the step at one source per input; the tag-level default stays unlimited and has nothing left to cut |
| L4.9 | `{{text src:refs,related_staff,limit(2)\|use:title}}` | BOTH names | L4.8's partner — without the pair, a per-step limit that did nothing at all would still pass L4.8 |
| L4.10 | `{{text src:refs,related_staff,limit(1)\|use:title\|linkTo:permalink}}` | `Jane Partner` only, in `<a>` | **what MIGRATION now writes.** Identical output to the flat L4.1 it replaces, which is the equivalence the rewrite claims. Same wire as L4.8 — the row is duplicated on purpose, because L4.8 asks whether a per-step limit works and this one asks whether migration produces it |
| L4.11a | `{{text src:ref\|ref:related_staff\|srcTermIn:portal_visibility\|use:title}}` | `All Users` | **TWO fanning steps, the shape the limit mapping exists for** — the FLAT original. Uses `portal_visibility` deliberately: `department` is a taxonomy the fixture's staff do not carry, so that spelling renders empty in BOTH eras and asserts nothing (the §F9.6 lesson) |
| L4.11b | `{{text src:refs,related_staff,limit(1);terms,portal_visibility,limit(1)\|use:title}}` | `All Users` — identical to L4.11a | what MIGRATION writes for L4.11a. BOTH steps are limited, because per-step limits are per-input and multiply |
| L4.11c | `{{text src:refs,related_staff;terms,portal_visibility\|use:title}}` | `All Users, All Users` | L4.11b's partner — the same chain with no step limits. Without it, a mapping that wrote nothing at all would still pass the pair |

## L4a — the SPELLING selects a SLOT's default too (#60)

`bws_clamp_limit`'s two container call sites changed with #60: a slot's own source spelling now
selects its default, exactly as a base tag's does. Before, the dispatch read the default off the
FLATTENED triple, whose `src` is a legacy token on every slot, so every slot answered 1 whatever
it was spelled as.

**Behaviour rows live in [`fold-test-matrix.md`](fold-test-matrix.md) §F7a** — that file owns the
fold, and duplicating them here is the copy this matrix has no reason to keep. What belongs HERE is
the link gate, for the same count-based reason L1 and L4 carry it: a slot that starts returning
several values stops being wrappable.

> **MEASURED 2026-08-07** on the testbed, `/matrix-terms-valid/`.

| Row | Tag | Expected | What it proves |
|---|---|---|---|
| L4a.1 | `{{try_text srcTermIn:department\|use:title\|linkTo:permalink}}` | ONE dept name, in `<a>` | FLAT slot, unset — the floor, unchanged |
| L4a.2 | `{{try_text A:src(terms,department);use(title)\|linkTo:permalink}}` | `Sales, Support`, **NO** `<a>` | CHAIN slot, unset — unlimited, and the anchor legitimately gone. The slot twin of L4.2 |
| L4a.3 | `{{try_text A:src(terms,department,limit[1]);use(title)\|linkTo:permalink}}` | ONE dept name, in `<a>` | what MIGRATION writes for L4a.1 — identical output, anchor included |
| L4a.4 | `{{join A:src(same);key(x)}}` after a fanning slot 1 | see §F7a.6 | a slot that fans only by INHERITING keeps the flat default: the slot it inherits from stated its own bound, and a limit does not carry forward |

## L5 — the author conversion (editor only)

Not reachable by `render-tag`. Open `/matrix-post-meta/` in the block editor.

| Row | What to do | Expected |
|---|---|---|
| L5.1 | Open a tag whose source is FLAT `src:ref\|ref:related_staff`; the Source control shows one step | the chain is READ from the flat keys — display only. Cancel the modal and the stored string is untouched |
| L5.2 | Commit any change to that source | the saved tag is `src:refs,related_staff,limit(1)`, the `ref` key is GONE, and **the step's own Limit field shows `1`** — the limit lands in the step row the author is looking at, and NO tag-level `limit` is written. Without it the tag would silently start rendering both names and drop its anchor |
| L5.3 | Clear the `1` from the step's Limit field | both names render, and the wire is back to `src:refs,related_staff`. The point of showing the number is that it is clearable |
| L5.4 | Re-commit the source on an ALREADY-chain tag whose step limit you cleared | the `1` does NOT come back. Only the conversion writes it |
| L5.5 | Set the source to `Current` (root only) and commit | no `limit` is written — a source with no step has nothing to bound, and a number there is noise a reader has to decide is meaningless |
| L5.6 | Add a step, leave its field empty | the step warns *"This tag will be skipped unless a field is set"*, and **Add hop is unavailable** until the step is complete |
| L5.7 | On a fanning step, check the limit input | placeholder reads `0 (all)`. Type `0`, commit, reopen — the field shows `0 (all)` again and the wire carries no limit. Same glyphs, same meaning, nothing silently lost |
| L5.8 | Convert the `L5.8 subject` row — a flat tag already carrying a tag-level `limit:3` | the `3` MOVES onto the step: the wire is `src:refs,related_staff,limit(3)` and the tag-level key is gone. One limit, one place — leaving it in both would state one bound in two spellings that mean different quantities |

## Editor check (no front-end surface)

**There is no tag-level Result Limit control any more** on the tags that build a source path
(v1.17.0, [#62](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/62)) — a
limit is stated where the source is stated, so the input to check is the STEP's own Limit field.
Its absence is asserted by `php tools/test/control-order-test.php` §5, since a missing control
leaves no trace in rendered output. A stored tag-level `limit` is still READ, which is what every
L1/L3 row above exercises.

Open a `limit`-bearing block on `/matrix-post-meta/` in the GB editor and check the step's Limit
input:

- the placeholder reads `0 (all)` and the step's own help names the per-input meaning (at most N
  from EACH incoming result);
- the input accepts `0` and `-1` — **there is deliberately no `min`** (a control that
  fights a hand-typed value works against
  [ADR 0004](../../docs/adr/0004-serialized-tag-string-human-readable.md), and the parse is already
  tolerant);
- `0` survives a save/reopen round trip (it normalizes back to absence, and absence and `0` read
  the same — L5.7).
