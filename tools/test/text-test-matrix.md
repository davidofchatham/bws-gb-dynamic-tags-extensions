# `{{text}}` Read-Seam Regression Matrix

**Standing manual regression suite** for the base text read seam (`bws_base_text_resolve_value`,
the absorb seam extracted 1.14.1) and the link-wrap gate in its shell callback
(`bws_base_text_callback`). Originated with the 1.14.1 extraction; becomes the re-run pass for
anything that touches the text value path — including `{{join}}` slots once they absorb it.

> **Re-run trigger:** any change to `bws_base_text_resolve_value()`, `bws_base_text_callback()`,
> `bws_wrap_with_link()` / `bws_resolve_link_url()`, or a new absorber of the seam (e.g. the
> `{{join}}` per-slot resolve). Rows target the wrap-gate contract (`link_id` 0 = multi-result =
> never wrap; sentinel `link_id` 1 = site) and the value invariants (`'0'` preserved, list modes
> use text's own `sep`/`limit`).

**How to run:** rows are `render-tag` one-liners against the seeded testbed
(state: `core-structures` blueprint — `bin/seed.sh testbed core-structures`). From the
wp-litespeed env:

```bash
bin/wp.sh testbed bws render-tag '{{TAG}}' --url=https://testbed.test/CONTEXT/ --porcelain
```

Contexts used: `/matrix-post-meta/` (post arm; carries Support + Sales department terms,
`related_staff` → Jane Partner, Tom Associate — jane first) and `/department/support/` (term
archive → term-analog arm). T6 is editor-only (open a block on the testbed editor).

> Verified 2026-07-17 against the 1.14.1 extraction: T1, T3, T4, T5, T7 all pass via
> `render-tag`; T6 pending an editor pass.

---

## T1 — post arm + wrap gate

| # | Tag (on `/matrix-post-meta/`) | Expected |
|---|---|---|
| T1.1 | `{{text key:main_line}}` | `(987) 654-3210` — bare value, no anchor |
| T1.2 | `{{text key:main_line\|linkTo:permalink}}` | value wrapped: `<a href="…/matrix-post-meta/">(987) 654-3210</a>` |
| T1.3 | `{{text use:title\|linkTo:permalink\|newTab}}` | page title wrapped, `target="_blank" rel="noopener noreferrer"` present |

## T2 — site arm (sentinel link_id)

Covered by [`src-site-test-matrix.md`](src-site-test-matrix.md) R0.1–R0.2 / R4.7 — re-run those
rows alongside this matrix; the sentinel `link_id = 1` ('site') path lives there. No duplicate
rows here.

## T3 — srcTermIn list mode: multi never wraps, single wraps

| # | Tag (on `/matrix-post-meta/`) | Expected |
|---|---|---|
| T3.1 | `{{text srcTermIn:department\|use:title\|limit:2}}` | `Sales, Support` — text's own `sep` default; term order = WP default (alphabetical by name) |
| T3.2 | `{{text srcTermIn:department\|use:title\|limit:2\|linkTo:permalink}}` | `Sales, Support` — **NO anchor** (multi-result → `link_id` 0 → wrap suppressed) |
| T3.3 | `{{text srcTermIn:department\|use:title\|limit:1\|linkTo:permalink}}` | `Sales` wrapped in the Sales term-archive link (single result → term wrap) |

## T4 — term-analog arm (bare tag on a term archive)

| # | Tag (on `/department/support/`) | Expected |
|---|---|---|
| T4.1 | `{{text key:email}}` | `support@example.test` — term ACF field via the analog arm |
| T4.2 | `{{text key:email\|linkTo:permalink}}` | value wrapped in the Support term-archive link (term entity type) |

## T5 — `'0'` preservation

| # | Tag (on `/matrix-post-meta/`) | Expected |
|---|---|---|
| T5.1 | `{{text key:bws_zero_probe}}` | renders `0` — must NOT be empty. (`render-tag` shows bare `0`; in a real GB block render the hooks.php `'0'`→`'0 '` falsy guard applies downstream.) |

## T6 — editor preview fallback (editor-only)

| # | Action | Expected |
|---|---|---|
| T6.1 | `{{text key:nonexistent_key}}` in a block on the testbed editor | preview label renders (bracket placeholder), block not blank |

## T7 — src:ref list mode (1.14.0 fix lives in the moved code)

| # | Tag (on `/matrix-post-meta/`) | Expected |
|---|---|---|
| T7.1 | `{{text src:ref\|ref:related_staff\|use:title}}` | `Jane Partner` — default limit 1, first target only |
| T7.2 | `{{text src:ref\|ref:related_staff\|use:title\|limit:5}}` | `Jane Partner, Tom Associate` — ALL targets listed |
| T7.3 | `{{text src:ref\|ref:related_staff\|use:title\|limit:5\|linkTo:permalink}}` | `Jane Partner, Tom Associate` — **NO anchor** (multi-result) |
| T7.4 | `{{text src:ref\|ref:related_staff\|use:title\|linkTo:permalink}}` | `Jane Partner` wrapped in Jane's staff permalink (single result → post wrap) |

---

## Fail triage

- **T1.2/T3.3/T4.2/T7.4 value right but unlinked:** shell wrap gate — `link_id`/`link_type` not
  threading out of `bws_base_text_resolve_value` for that arm.
- **T3.2/T7.3 anchor around a joined list:** multi-result branch leaked a non-zero `link_id` —
  the `1 === count($out)` single-result guard regressed.
- **Site rows (src-site R0.2) unlinked:** sentinel `link_id = 1` lost — site arm must return
  `{link_id:1, link_type:'site'}`.
- **T4.x reads a post value on the term archive:** term-analog arm bypassed — factory/ambient
  detection regression, see traversal pipeline (CONTEXT.md L1).
- **T5.1 empty:** `'0'` coerced to empty somewhere in the seam — violates the absorb invariant
  (PHPDoc on `bws_base_text_resolve_value`); check nothing re-decides emptiness before the
  hooks.php `'0'`→`'0 '` guard.
- **T7.2 shows only Jane:** src:ref list regression (the 1.14.0 fix — plural traversal
  `bws_base_post_ids_from_source` not honored).
- **T6.1 blank block:** preview fallback moved to the shell — `$is_preview` branch after resolve
  not reached.
