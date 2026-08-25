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
[`context-test-matrix.md`](context-test-matrix.md) C-rows). The bare-tag rows record today's
WRONG output as the pinned baseline, and the page snapshot
(`tools/test/snapshots/page-matrix-loops.html`) holds those leaked values on purpose: the fix's
diff is then proof rather than assertion. Flip each Expected column when item-shape recognition
ships.

**Why the bare tag is wrong.** The extension supplies the loop item's id through
`generateblocks_dynamic_tag_id`, and GB passes that filter no fallback type — so a term id or a
user id overrides our POST fallback and the tag reads whatever post carries that number. Term
ids and post ids collide constantly. Ours is the consuming half; the filter is theirs and it
cannot discriminate.

Baselines captured 2026-08-25 against GenerateBlocks 2.4.1 / GB Pro 2.7.0 / GB Query
Enhancements 1.3.0 (the set `env-versions.php` records).

## QL1 — a query loop over TERMS

Two loops, because the leak has two appearances. The first is over the default `category` term,
where the collision is guaranteed by WordPress itself (Uncategorized is term 1, "Hello world!" is
post 1 on every install), so the leaked read prints a real title from the wrong entity. The
second is over the blueprint's `department` terms, whose ids no post carries — the same leak
landing on nothing. A reader sees an unrelated value in one and an empty row in the other; they
are one defect and they flip together.

| # | Tag (on `/matrix-loops/`) | Expected | Status |
|---|---|---|---|
| QL1.1 | `{{title}}`, inside the `category` loop | `Hello world!` — the post sharing the term id. **WRONG**; ships as the term name (`Uncategorized`) | EXPECTED-FAIL |
| QL1.2 | `{{title src:term}}`, same loop | `Uncategorized` — an explicit source resolves the loop's term today | **PASS** |
| QL1.3 | `{{term_archive_url}}`, same loop | `https://<site>/category/uncategorized/` — the extension's own term tag, correct today. Its ARCHIVE URL tag and not its title tag: we register a `term_title` of our own, so that name is a registration collision (spec D4) and which plugin answers depends on load order. This row shows a third-party read landing on the loop's term, not whose registration won | **PASS** |
| QL1.4 | `{{title}}`, inside the `department` loop | **EMPTY** — the same leak with no post carrying the id (6/7/8 on the reference site). **WRONG**; ships as the term name | EXPECTED-FAIL |
| QL1.4b | `{{title src:term}}`, same loop | `Sales`, `Support`, `Warehouse` — the non-vacuity control for QL1.4. Without it, an empty QL1.4 reads identically to a loop that never ran | **PASS** |

## QL2 — a query loop over USERS

The same mechanism, second item shape, and the rows sit in the same three positions so the two
groups read as one finding. Two author-role fixture users, ordered by display name.

**This group has two reads where QL1 has three, and QL2.2 is a row that says so.** There is no
explicit user source token — user entities are reachable only ambiently, on an author archive
(`context-test-matrix.md` C3) — so QL1.2's middle read cannot be written here. The absence is the
finding: closing QL2.1 is what makes a user readable inside a loop at all.

| # | Tag (on `/matrix-loops/`) | Expected | Status |
|---|---|---|---|
| QL2.1 | `{{title}}` | `Sample Page` for the first user, EMPTY for the second — posts sharing the user ids. **WRONG**; ships as the user's display name | EXPECTED-FAIL |
| QL2.2 | *(no tag — a stated absence)* | The row prints its label and nothing else. It is prose on the page for the same reason it is a row here: a group with one read missing looks like a group with a broken row | n/a |
| QL2.3 | `{{user_display_name}}` | `Fixture Author`, `Other Author` — the extension's own user tag, correct today | **PASS** |
| QL2.4 | `{{permalink}}` | The permalink of the post sharing the user id. **WRONG**, and the one row that does NOT become correct: it ships as **EMPTY**, because a user has no permalink of ours to give. A known gap reached by a new route, not a regression | EXPECTED-FAIL |

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
