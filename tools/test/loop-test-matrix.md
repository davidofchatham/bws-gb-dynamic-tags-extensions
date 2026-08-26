# Query-loop test matrix (QL-rows)

Integration rows for tags rendered **inside a query loop whose items are not posts** — a loop
over terms and a loop over users, both supplied by the co-resident query extension the blueprint
declares required (`tools/fixtures/core-structures/env-versions.php`). All rows live on
`/matrix-loops/` as visible GB blocks; see
[`docs/testbed.md`](../../docs/testbed.md) for reseeding and the cache bust.

Named for the PROPERTY under test rather than a tag family (precedent:
[`limit-default-test-matrix.md`](limit-default-test-matrix.md)) — the finding is about the loop,
and the same wrongness reaches `{{title}}` and `{{permalink}}` alike.

**These rows cannot be run through `bws render-tag`.** Its `--loop-item` flag fakes a post query
only, so a term or user loop is not producible at any flag combination; a real block render is
the only instrument that reaches them. Rows are grouped by loop shape. Inside a group the
three-read trio keeps its positions (bare, explicit source, the extension's own tag) — that
adjacency is what makes the failure legible — and any further rows follow in
[`tag-reference.md`](../../docs/tag-reference.md) Catalog order.

**Staging pattern = expected-fail → flip on ship** (precedent:
[`context-test-matrix.md`](context-test-matrix.md) C-rows). The bare-tag rows recorded the WRONG
output as the pinned baseline, and the page snapshot
(`tools/test/snapshots/page-matrix-loops.html`) held those leaked values on purpose, so the fix's
diff was proof rather than assertion. **Flipped 2026-08-26**, measured through a real block
render on `/matrix-loops/`; every Expected column below now states the shipped value.

**Why the bare tag WAS wrong.** The extension supplies the loop item's id through
`generateblocks_dynamic_tag_id`, and GB passes that filter no fallback type — so a term id or a
user id overrode our POST fallback and the tag read whatever post carried that number. Term ids
and post ids collide constantly. Ours was the consuming half; the filter is theirs and it cannot
discriminate.

**What replaced it.** A bare tag inside a query loop now resolves the loop ITEM's own entity,
and an item shape nothing recognizes resolves to NOTHING instead of falling through to the
surrounding page. **What decides which entity an item is, is owned by `bws_classify_loop_item()`'s
PHPDoc** (`includes/helpers/field-helpers.php`) and stated nowhere else, this file included.

**These rows do not measure that rule, and reading them as if they did is the trap.** Every QL
row below runs under a real `queryType` string, so a recognizer keyed on the vendor's string
instead of on the item would pass all of them identically. What the rows measure is that the
LOOP'S OWN ENTITY is what each tag reads, on this site, with these extensions. The rule itself is
pinned WP-free in [`loop-item-classify-test.php`](loop-item-classify-test.php) — §C1–§C5 drive
the shapes directly and never mention a query type — and the mapping from a recognized kind onto
a source kind is pinned in [`traversal-pipeline-test.php`](traversal-pipeline-test.php)'s `#123`
section.

**The refusal has no row here, and cannot have one.** Producing an unrecognized item shape needs
a query type no plugin on the reference site supplies, so there is nothing to seed. Add a row
here the day the fixture site gains a loop type whose items are neither posts, terms, users nor
repeater rows. **WooCommerce product loops are that shape already, and they are the accepted cost of
the refusal rather than a future case** — `docs/future-work.md` FW-100 carries the measured item
shape and what a fixture for it would need.

**One consequence of the recognition is NOT reachable from this page, and that is why it went
unnoticed for a pass.** A term or user item now makes `in_loop` true with no post behind it, and
the render sites that skip their own "no entity" bail on that flag reach a post-meta read which
cannot serve it; that read then falls to its TERM-ARCHIVE fallback and returns the SURROUNDING
archive's meta. Reproducing it needs a non-post loop rendered on a term archive at once, and no
fixture page is an archive. Measured through `do_blocks()`/`replace_tags()` on
`/department/support/` instead (2026-08-26): a USER loop returned `October 5, 2030` — that
department's `event_date` — from `{{datetime_single key:event_date}}`. The predicate those sites
now gate on is `bws_loop_item_is_post_or_row()`, pinned in `loop-item-classify-test.php` §C6.

Baselines captured 2026-08-25, flipped 2026-08-26, against GenerateBlocks 2.4.1 / GB Pro 2.7.0 /
GB Query Enhancements 1.3.0 (the set `env-versions.php` records).

## QL1 — a query loop over TERMS

Two loops, because the leak had two appearances. The first is over the default `category` term,
where the collision is guaranteed by WordPress itself (Uncategorized is term 1, "Hello world!" is
post 1 on every install), so the leaked read printed a real title from the wrong entity. The
second is over the blueprint's `department` terms, whose ids no post carries — the same leak
landing on nothing. A reader saw an unrelated value in one and an empty row in the other; they
are one defect and they flipped together.

The three reads in the first group now AGREE, and that agreement is the assertion: a bare tag, an
explicit source and the extension's own tag all name the same term. A row disagreeing with its
neighbours is the leak returning.

| # | Tag (on `/matrix-loops/`) | Expected | Status |
|---|---|---|---|
| QL1.1 | `{{title}}`, inside the `category` loop | `Uncategorized` — the loop's own term. Was `Hello world!`, the post sharing the term id | **PASS** |
| QL1.2 | `{{title src:term}}`, same loop | `Uncategorized` — an explicit source resolved the loop's term throughout | **PASS** |
| QL1.3 | `{{term_archive_url}}`, same loop | `https://<site>/category/uncategorized/` — the extension's own term tag, correct throughout. Its ARCHIVE URL tag and not its title tag: we register a `term_title` of our own, so that name is a registration collision (spec D4) and which plugin answers depends on load order. This row shows a third-party read landing on the loop's term, not whose registration won | **PASS** |
| QL1.4 | `{{title}}`, inside the `department` loop | `Sales`, `Support`, `Warehouse` — the loop's own terms. Was **EMPTY**: the same leak with no post carrying the id (6/7/8 on the reference site) | **PASS** |
| QL1.4b | `{{title src:term}}`, same loop | `Sales`, `Support`, `Warehouse` — the non-vacuity control for QL1.4, and now also its equivalence control: the bare read and the explicit one must agree | **PASS** |

## QL2 — a query loop over USERS

The same mechanism, second item shape, and the rows sit in the same three positions so the two
groups read as one finding. Two author-role fixture users, ordered by display name.

**This group has two reads where QL1 has three, and QL2.2 is a row that says so.** There is no
explicit user source TOKEN — until this fix, user entities were reachable only ambiently, on an
author archive (`context-test-matrix.md` C3) — so QL1.2's middle read still cannot be written
here. The absence was the finding: QL2.1 is what makes a user readable inside a loop at all, and
a user query loop is therefore the plugin's first NON-ambient user source.

| # | Tag (on `/matrix-loops/`) | Expected | Status |
|---|---|---|---|
| QL2.1 | `{{title}}` | `Fixture Author`, `Other Author` — the loop's own users. Was `Sample Page` for the first user and EMPTY for the second, the posts sharing the user ids | **PASS** |
| QL2.2 | *(no tag — a stated absence)* | The row prints its label and nothing else. It is prose on the page for the same reason it is a row here: a group with one read missing looks like a group with a broken row | n/a |
| QL2.3 | `{{user_display_name}}` | `Fixture Author`, `Other Author` — the extension's own user tag, correct throughout, and now the agreement control for QL2.1 | **PASS** |
| QL2.4 | `{{permalink}}` | **EMPTY**, for both users — the one row that did NOT become correct, only honest. The loop's user IS recognized; a user has no permalink of ours to give, which is a deferred author analog reached by a new route rather than a regression. Was the permalink of the post sharing the user id | **PASS** |

## QL3 — a preserved zero in a term loop (not the leak)

Rides this page because it needs a term query loop and nothing else in the blueprint has one. It
is deliberately not in the leak groups: a reader should not have to decide whether a zero is the
leak.

`{{term_count}}` on an empty term returns a bare `'0'` and reaches the falsy-replacement guard in
[`includes/hooks.php`](../../includes/hooks.php) — the fourth tag not ours measured doing so,
and the last of the four to be pinned. What that guard applies to is decided at the guard; this
section neither states nor extends it.

The loop runs with `hide_empty` OFF, which is what reaches the unstaffed `Workshop` term at all,
and the staffed departments loop with it as the non-vacuity control. A zero beside real counts is
the guard working; a column of nothing is a loop that did not run.

| # | Tag (on `/matrix-loops/`) | Expected | Status |
|---|---|---|---|
| QL3.1 | `{{title src:term}}` | `Sales`, `Support`, `Warehouse`, `Workshop` — the identity for the count beside each | **PASS** |
| QL3.2 | `{{term_count}}` | real counts for the three staffed departments, then `0` for `Workshop`, which is assigned to no post. The zero survives GB's block-kill only because of the guard; without it the whole row disappears | **PASS** |
