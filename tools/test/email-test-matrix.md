# `{{email}}` Regression Matrix

Standing manual regression suite for the `{{email}}` base tag's **fixed read** (FW-141). Rows anchor invariants VE1–VE4 (mailto wrap, subject, validate-before-obfuscate).

**Re-run trigger:** a change to `bws_base_email_resolve_value`, `bws_email_finish_values`, `bws_resolve_fixed_values` or `bws_email_callback`.

**How to run:** on the fixture testbed, seeded from the `core-structures` blueprint (see [`tools/fixtures/core-structures/README.md`](../fixtures/core-structures/README.md)) — page `/matrix-post-meta/` renders every row below (section "Email E1"). The fixture seeds email obfuscation OFF (see [`docs/testbed.md`](../../docs/testbed.md), the `{{email}}` obfuscation note), so addresses read plainly. Verified 2026-09-29 via `render-tag`.

## E1 — the fixed read (FW-141)

`use:fixed` shows the author's address once per resolved source, finished exactly as `fallback` is (validated, obfuscated per setting, `mailto:` with `subject`, `noLink`). Never written on the wire: `fixed:` alone implies the read.

| # | Tag | Expected |
|---|---|---|
| E1.1 | `{{email fixed:info@example.com}}` | `mailto:info@example.com` anchor, display `info@example.com` |
| E1.2 | `{{email fixed:info@example.com\|subject:Hello there}}` | `mailto:info@example.com?subject=Hello%20there` |
| E1.3 | `{{email fixed:info@example.com\|noLink}}` | plain `info@example.com`, no anchor |
| E1.4 | `{{email src:refs,related_staff\|fixed:info@example.com\|noLink\|sep: / }}` | `info@example.com / info@example.com` (one per staff post) |
| E1.5 | `{{email src:refs,no_such_rel\|fixed:info@example.com\|fallback:sales@example.test\|noLink}}` | `sales@example.test`: no source resolved, so the fixed address does not print and the fallback fires |
| E1.6 | `{{email fixed:not-an-email\|fallback:sales@example.test\|noLink}}` | `sales@example.test`: an invalid entry is empty |
| E1.7 | `{{email fixed:not-an-email}}` | empty (invalid, no fallback) |
| E1.8 | `{{email src:ref\|ref:related_staff\|key:contact_email\|fixed:info@example.com\|noLink}}` | `jane@example.test`: a stored `key`+`fixed` pair reads the key |
| E1.9 | `{{email src:site\|fixed:info@example.com\|noLink}}` | `info@example.com`: the site is a source |

## E2 — a fixed `try_email` attempt (FW-141 05)

A `try_email` attempt can be the fixed read, finished as in E1. It takes its turn like any attempt: a field attempt with a value before it wins, an empty or invalid one falls through, and a fixed attempt whose own chain resolves nothing is empty too. Visible on `/matrix-post-meta/` (section "Email E2"). Verified 2026-09-29 via `render-tag`.

| # | Tag | Expected |
|---|---|---|
| E2.1 | `{{try_email A:fixed(info@example.com)\|noLink}}` | `info@example.com` |
| E2.2 | `{{try_email A:src(refs,related_staff);key(contact_email)\|B:fixed(info@example.com)\|noLink}}` | `jane@example.test, tom@example.test`: the field attempt has a value, so the fixed attempt never runs |
| E2.3 | `{{try_email A:key(nonexistent_field)\|B:fixed(info@example.com)\|noLink}}` | `info@example.com`: the empty field attempt falls through |
| E2.4 | `{{try_email A:fixed(not-an-email)\|B:fixed(info@example.com)\|noLink}}` | `info@example.com`: an invalid entry is empty, so the next attempt runs |
| E2.5 | `{{try_email A:src(refs,no_such_rel);fixed(info@example.com)\|fallback:sales@example.test\|noLink}}` | `sales@example.test`: no source resolved, every attempt empty, so the fallback fires |
| E2.6 | `{{try_email A:fixed(info@example.com)\|subject:Hello}}` | `mailto:info@example.com?subject=Hello` |
| E2.7 | `{{try_email A:src(refs,related_staff);fixed(info@example.com)\|sep: / \|noLink}}` | `info@example.com / info@example.com`: once per staff post |
