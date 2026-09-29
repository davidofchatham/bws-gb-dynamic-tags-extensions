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
