# Archive: External sources as chain roots — FW-69 / FW-70 (SHIPPED 1.17.0)

> **DESIGN HISTORY — A RECORD, NOT A CURRENT-STATE SOURCE.** This file says what was decided and
> built when it was written, and is **not corrected** when policy or code later moves (`CLAUDE.md`
> §Spec lifecycle owns that rule). Cite it for PROVENANCE — what a decision was hardened against,
> what a build actually did — never as a statement of how the code works now. For current state:
> `docs/tag-reference.md`, `CONTEXT.md`, or the PHPDoc at the enforcing site.

**Archived 2026-08-19 with the 1.17.0 release, with NOTHING left open in this repo.** Both halves
shipped 2026-08-12 — root offering ([#83](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/83))
with its fixtures ([#85](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/85) +
[#87](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/87)), and migration
([#84](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/84) +
[#86](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/86)). The external half
shipped in bws-portal-system 5.7.0 and was exercised end to end in Experiment M2.

**Read this as the record of how the work was decided, not as a statement of how anything currently
works.** §SETTLED is the grill's output and every row of §OPEN is now closed: the sunset question
resolved to a PROGRESSION (see its row, and FW-67 for where retirement is parked), the advisory
channel was answered in the negative, and request context inside a testbed run went to
[PS#71](https://github.com/davidofchatham/bws-portal-system/issues/71). §Facts that shaped the spec
is the part still worth reading — each entry changed the work rather than confirming it, and two of
them (the registry keeps its dead by policy; the flat era cannot express "root here AND hop a
relationship") are load-bearing well beyond this ticket.

---

**The SPEC is [#80](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/80)**
(`ready-for-agent`). It owns the problem statement, user stories, implementation decisions, testing
decisions and scope. **This file does not restate it.** What lives here is the decision record: what
was settled in the 2026-08-11 grill, what is still open, and the facts found by reading code that
changed the shape of the work — the material a spec states as conclusions without showing how they
were reached.

Tracker rows **FW-69** (roots + registration routes) and **FW-70** (`view_*` migration). **FW-67**
parks behind the eventual family retirement, which is out of #80's scope.

> **BUILT 2026-08-12 — the ROOT-OFFERING half ([#83](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/83)),
> on `feat/registered-source-roots`.** Both routes, one appender, the preview, and coverage in
> `slot-options-build-test.php` / `traversal-pipeline-test.php` / `preview-label-test.php` (the
> registry bootstrap they share is `tools/test/lib-source-registry.php`). Four §SETTLED rows below
> are dated 2026-08-12 — they were decided during the build, not in the grill.
>
> **#80 IS CLOSED AND EVERY IN-REPO HALF SHIPPED** (corrected 2026-08-18 — this banner still read
> "Still pending on #80: the migration half (FW-70), and the in-repo fixture source + visible
> testbed rows", which had been true for six hours). The migration half landed 2026-08-12
> ([#84](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/84) +
> [#86](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/86)) as a
> WHOLE-STRING transform with a template-enumerating entry generator, and the fixture source +
> visible rows with it ([#85](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/85) +
> [#87](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/87)) — both fixture
> roots resolve from SEEDED content, which is what lets a row state its own expected value.
> **What is left is not in this repo:** bws-portal-system registers its own `is_selectable_root()`
> and one `bws_register_modifier_root_migrations( 'view', 'view' )` call
> ([bws-portal-system#71](https://github.com/davidofchatham/bws-portal-system/issues/71)), which
> shipped in its 5.7.0 and was exercised end to end on the SITE-B clone in Experiment M2
> (2026-08-18): `{{view_title}}` → `{{title src:view}}`, 10 tag strings rewritten, CHANGED 0.
> Family retirement stays parked behind FW-67.

---

## §SETTLED

| Decision | Settled |
|---|---|
| Offerability is **stated, not inferred** — a boolean on the source contract, default false | ✅ 2026-08-11 (user, Q1) |
| Shape: `is_selectable_root(): bool` + reuse the existing source-label accessor; **no second label method** | ✅ 2026-08-11 (user, Q2) |
| A **registration** filter named for roots, firing at registry init; **no row/presentation filter** | ✅ 2026-08-11 (user, Q3) |
| **No `$context`** on the filter — nothing exists to put in it, and WP lets a trailing arg be added later | ✅ 2026-08-11 (build reasoning, correcting an earlier claim that it could not) |
| Rows attach at the **chain-root layer**, never the shared base source-option builder | ✅ 2026-08-11 |
| **Slots included, ungated** | ✅ 2026-08-11 (user, Q4) |
| **No static kind** for registered roots — compiler keeps its render-time answer, editor stays permissive | ✅ 2026-08-11 (user, Q5) |
| Migration is **converter-only**; a rename cannot happen on mount | ✅ 2026-08-11 (user, Q6) |
| Mapping: `current` → the source's root; `site` → site root with inert sidecars **dropped** | ✅ 2026-08-11 (user, Q8) |
| Preview names the source from its **registered label** | ✅ 2026-08-11 (user, Q9) |
| The **owning plugin registers its own migration**, via a template-enumerating helper | ✅ 2026-08-11 (user, Q10) |
| **Two-track fixtures** — an in-repo fixture source and modifier family, plus the external plugin's own testbed rows (handed off to that project) | ✅ 2026-08-11 (user, Q11) |
| Ships in the **current unreleased version**, as a **second PR** after the release branch merges | ✅ 2026-08-11 (user, Q12/Q15) |
| Losing the external plugin's own picker group is **acceptable** | ✅ 2026-08-11 (user) |
| The in-repo term-rooting family is **deferred**, and does not follow | ✅ 2026-08-11 (user) |
| Retirement, when it comes, is **deprecated wrappers via a mode flag** on the modifier registrar — not hand-written wrappers, which would drop the relationship step, taxonomy step, link wrap and image dispatch | ✅ 2026-08-11 (user, Q7) — deferred to the sunset, not built in #80 |
| Filter is **`bws_dynamic_tags_chain_roots`**, a KEY-KEYED map (`key => { label, context, resolve }`) — the key being the array key makes collision-ignore structural (`isset()`) and a spec structurally cannot omit it; noun name matches the existing `bws_dynamic_tags_preview_modifier_map` | ✅ 2026-08-12 (user, build Q1) |
| Offered rows are filtered through **`is_source_enabled()`** as well as the opt-in — a term-context root follows the `term_` modifier toggle like every other term surface. Two gates on OFFERING; neither reaches resolution | ✅ 2026-08-12 (user, build Q2) |
| The preview reads its source as a **CHAIN, one read everywhere** — `bws_fold_chain_from_options()` feeds the root lookup AND the `ref`/`site` branches, rather than a registered-root lookup bolted beside a raw token compare. Multi-hop chains name every step; an argless step warns | ✅ 2026-08-12 (user, build Q4 — chose the fuller refactor over the minimal additive read) |
| #83's scope is **harnesses + docs**; the in-repo fixture source and visible testbed rows ride with FW-70, whose fixture modifier family they share | ✅ 2026-08-12 (user, build Q3) |

## §OPEN

| Question | Why it is open |
|---|---|
| ~~Complete cutoff vs wrapper retention~~ at sunset | **ANSWERED 2026-08-19 (user): it is a PROGRESSION, not a binary.** (1) The modifiers are fully replaced — which is not done, because it needs TERM SELECTION on the base tags (FW-33's path; the same capability the user chose over settling `term_`'s limit question). (2) The replacement is available for **at least one release**, so a site has a window to migrate. (3) Then unregister. **Two things the progression does not state and must not lose.** The settled retirement MECHANISM (Q7 above) gives step 3 a middle step: deprecated wrappers via a mode flag on the modifier registrar keep the family rendering while it is deprecated, so "unregister" is the END of the glide path, not the whole of it. And **a migration window does nothing for wire no migrator reaches** — the converter reads `post_content` only (not the options table, not block widgets) and the mount path only reaches what someone opens. Since an UNREGISTERED tag renders LITERALLY (`gb-constraints.md`), the failure mode of step 3 is DEFACEMENT, not absence, so it still gates on the unreachable surfaces being EMPTY — FW-73's enumeration half — rather than on elapsed releases. A release of availability is a necessary gate, not a sufficient one. |
| ~~Request context inside a testbed run~~ | **HANDED OFF 2026-08-19 (user)** to bws-portal-system ([PS#71](https://github.com/davidofchatham/bws-portal-system/issues/71)), which is where it always belonged: it gates that plugin's OWN fixture rows, and the in-repo fixture source sidesteps it by resolving deterministically. Nothing in this repo waits on it. |
| ~~Whether the advisory channel lands first~~ | **ANSWERED IN THE NEGATIVE 2026-08-19 (user): it does not.** FW-66 is a future enhancement and is not queued. It never gated #80 — recorded here so the row is closed rather than quietly outlived, since an OPEN row that nothing depends on reads as a live prerequisite to the next person sizing this work. |

---

## Facts that shaped the spec

Each of these changed the work rather than confirming it, and each cost a read of the code.

**The resolution half already ships.** The factory delegates any source token that is not the
ambient, relationship or site spelling to the registry, resolving through the source's own id. So a
base tag naming an external source renders correctly **today**, with no opt-in existing. #80 is an
authoring and migration change; a second resolution path would be a divergence, not a feature.

**The registry keeps its dead by policy.** Five registered sources are inert-by-decision (four
in-repo, one external), each retired when the generic relationship step subsumed it, each kept
because the standing rule forbids deleting a registration for lacking resolve logic. That is what
makes auto-derive permanently wrong rather than wrong-today, and it is the load-bearing argument for
opt-in — stronger than any individual example, including the one first offered here (an external
relationship-hop source, which turned out to be a retiring artifact of exactly the same class).

**The modifier machinery already IS root injection.** The generated modifier callback resolves its
base source key through the registry, takes the context type as the kind, and hands the pair to the
same pipeline every base tag uses; the traversal source key has been accepted-but-ignored since
1.14.0. A modifier family is the pre-chain way of spelling one option value.

**The flat era cannot express "root here AND hop a relationship".** The chain builder reads the
relationship key only when the source token *is* the relationship token. On a modifier tag the root
came from the tag, so a rename-plus-injected-root leaves the key unread and erases the hop — the
defect the relationship-repair entries exist to prevent, arriving through the rescue. This forced
the whole-string transform.

**The converter's reach is wider than the fold docs imply** — every non-revision row in the posts
table, so reusable blocks, template parts and theme-element post types are all covered. Only the
options table is out of reach.

**The prefix IS the source key**, and sources register well before migrations do, so this repo can
enumerate an external family's tag names without that plugin registering anything. An earlier
reading of the hardcoded-prefix precedent suggested otherwise; it was wrong.

**Survey of the only known deployment:** ten occurrences, five posts, three of nine templates, **no
source options at all**; four occurrences in theme-element posts; no older-prefix tags; nothing in
the options table; a second site clean. The mapping rules for the harder shapes are specified for
durability, not for a known population — and the one row that changes rendered output has nothing
to change.

**No dead picker.** Base tags register no supports, so GB's entity picker was never on them; the
exclusion the external plugin passes exists because the modifier registrar defaults that support
**on**. A concern raised early here and struck on inspection.

**Every option in the surveyed inventory carries over.** The read options are registered on the base
tags with identical values, the text field options come from the same shared definition the modifier
template consumes, the image dispatch is byte-identical between the two callbacks, and the one
GB-reserved key is destructured by GB before either sees it.

---

## Related rows

- **FW-53** (`{{table}}`) — this work is its prerequisite: repeater rows held on an externally-rooted
  entity need both a root and a chain-authoring table container. Table is deferred to the next
  version behind a registration filter gate, which is what lets it be built once against both.
- **FW-43** — unrelated to #80, but it inherited the term-family fan when that ticket closed.
- **FW-33** — the term family's own path. Deferred; it does not follow this one.
