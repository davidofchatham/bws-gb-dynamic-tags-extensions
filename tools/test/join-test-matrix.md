# `{{join}}` Assembly + Absorb Regression Matrix

**Standing manual regression suite** for the `{{join}}` standalone combining tag: the
collect-all slot loop (`bws_join_callback`), the absorb seam per slot
(`bws_join_resolve_slot` → `bws_base_text_resolve_value`), and both assembly modes
(`bws_join_assemble` — separator / template with smart literal removal).

> **Re-run trigger:** any change to `bws_join_callback()` / `bws_get_join_options()`
> (base-tags.php), the pure assembly helpers (`includes/helpers/join-helpers.php`), or the
> absorbed text read seam (then ALSO re-run `text-test-matrix.md` — a join-row failure that
> implicates the seam itself routes to THAT matrix's fail-triage, not new join rows).
> The pure Step 1–5 punctuation algorithm is owned by `php tools/test/join-template-test.php`
> (run it first — it is the cheap gate); rows here assert only what needs real WP state.

**How to run:** rows are `render-tag` one-liners against the seeded testbed
(state: `core-structures` blueprint **v2** — `bin/seed.sh testbed core-structures`). From the
wp-litespeed env:

```bash
bin/wp.sh testbed bws render-tag '{{TAG}}' --url=https://testbed.test/CONTEXT/ --porcelain
```

Contexts used:
- `/staff/jane-partner/` — DENSE person (`name_*` all populated) — full-name + separator rows
- `/staff/tom-associate/` — SPARSE person (only `name_first`/`name_last`; other `name_*` `''`)
- `/matrix-post-meta/` — post arm (height/role/absorb rows; carries Support+Sales terms,
  `related_staff` → Jane Partner first, site options page reachable regardless of context)

**Wire note:** template-mode tokens are `%1`…`%8` on the wire — GB's tag parser rejects `}`
anywhere inside a tag's options (`find_matches` captures options as `[^}]+`,
`docs/gb-constraints.md`), so brace tokens `{N}` exist only INTERNALLY (translated by
`bws_join_wire_format`). `%%` escapes a literal percent before a digit.

> Verified 2026-07-17 against the initial 1.15.0 build: J1–J19, J21, J22 all pass via
> `render-tag`; J20 is editor-only. J23/J24 (single-empty-middle-part collapse) live in the
> pure harness by decision — `render-tag` has no per-field blanking.

---

## Separator mode

| # | Tag | Context | Expected |
|---|---|---|---|
| J1 | `{{join key:name_first\|2-key:name_last}}` | jane | `Jane, Smith` (default sep `, `) |
| J1b | `{{join key:name_first\|2-key:name_last\|sep: }}` | jane | `Jane Smith` (space sep — option values are not trimmed) |
| J2 | `{{join key:name_first\|2-key:name_generation\|3-key:name_last}}` | tom | `Tom, Associate` — empty middle slot dropped, no doubled sep |
| J3 | `{{join key:name_generation\|2-key:name_credential\|fallback_text:—}}` | tom | `—` — all slots empty → fallback |
| J3b | `{{join key:name_generation\|2-key:name_credential}}` | tom | `` (empty — no fallback → GB hides the block) |
| J4 | `{{join key:height_in_zero\|2-key:role}}` | `/matrix-post-meta/` | `0, Captain` — `'0'` is a REAL value (survives the empty-filter) |

## Template mode — brackets / separators

| # | Tag | Context | Expected |
|---|---|---|---|
| J5 | `{{join mode:template\|format:%1 (%2)\|key:name_first\|2-key:name_last}}` | jane | `Jane (Smith)` |
| J6 | `{{join mode:template\|format:%1 (%2)\|key:name_first\|2-key:name_generation}}` | tom | `Tom` — bracket group removed (`%2` empty) |
| J7 | `{{join mode:template\|format:%1 · %2\|key:name_generation\|2-key:name_last}}` | tom | `Associate` — floating separator removed (`%1` empty) |
| J8 | `{{join mode:template\|format:%1 (%2.)\|key:name_first\|2-key:name_generation}}` | tom | `Tom` — punct + brackets removed with empty `%2` |
| J9 | `{{join mode:template\|format:%1 (%2.)\|key:name_generation\|2-key:name_first}}` | tom | `(Tom.)` — bracket KEPT around surviving token |
| J10 | `{{join mode:template\|format:%1 (%2)\|key:name_generation\|2-key:name_credential\|fallback_text:—}}` | tom | `—` — all tokens empty → fallback |

## Full-name assembly — the primary stress case

Same tag string on both contexts. Format (7 slots): `%1 %2 %3. %4 %5, %6, %7`
(honorific / first / middle-initial / last / generation / credential / service):

```
{{join mode:template|format:%1 %2 %3. %4 %5, %6, %7|key:name_honorific|2-key:name_first|3-key:name_middle_initial|4-key:name_last|5-key:name_generation|6-key:name_credential|7-key:name_service}}
```

| # | Context | Expected |
|---|---|---|
| J21 | jane (DENSE) | `Dr. Jane M. Smith Jr., PhD, USN (Ret.)` — every part rendered, literal `.`/`,` kept |
| J22 | tom (SPARSE) | `Tom Associate` — mid-initial `.` shed, commas around empty credential/service collapsed, no orphan `, ,`, no trailing punctuation |
| J23 | pure harness | `Dr. Jane M. Smith, PhD, USN (Ret.)` — empty `{5}` only (see harness) |
| J24 | pure harness | `Dr. Jane M. Smith Jr., USN (Ret.)` — empty `{6}` sheds ONE comma (Gap-2 core; see harness) |

> **J23/J24 → pure harness (decided 2026-07-17).** One empty middle part against an
> otherwise-dense name is a 100% pure string transform; `render-tag` has no per-field blanking
> knob and gets no fidelity the harness lacks. `tools/test/join-template-test.php` owns them.
> Revisit only if synthetic blanking becomes a recurring cross-tag need (then a general
> `--blank=<key>` render-tag feature, not a join hack).

## Unit suffix — height (`/matrix-post-meta/`)

| # | Tag | Expected |
|---|---|---|
| J11 | `{{join mode:template\|format:%1'%2"\|key:height_ft\|2-key:height_in}}` | `5'11"` |
| J12 | `{{join mode:template\|format:%1'%2"\|key:height_ft\|2-key:height_in_blank}}` | `5'` — dangling `"` shed (Step 1) |
| J13 | `{{join mode:template\|format:%1'%2"\|key:name_generation\|2-key:height_in_blank\|fallback_text:—}}` | `—` — both quote marks shed, all empty → fallback (`name_generation` unseeded on this page) |
| J14 | `{{join mode:template\|format:%1'%2"\|key:height_ft\|2-key:height_in_zero}}` | `5'0"` — absorbed `'0'` renders; `5'` needs author `''` or the future base-text zero-empty opt-in |

## Per-slot src / use / site / list (absorb — `/matrix-post-meta/`)

| # | Tag | Expected |
|---|---|---|
| J15 | `{{join use:title\|2-use:key\|2-key:role\|sep: / }}` | `Matrix: Post Meta / Captain` — slot 1 = page title, slot 2 = meta field |
| J16 | `{{join key:main_line\|2-src:same\|2-key:booking_line}}` | `(987) 654-3210, 987.654.3210` — `src:same` is a no-op when slot 1 is ambient |
| J16b | `{{join src:ref\|ref:related_staff\|use:key\|key:main_line\|2-src:same\|2-key:contact_email}}` | `(555) 200-3000, jane@example.test` — REAL carry-forward: slot 2 re-reads the SAME ref target (jane, first), different key. Shared machinery with future try_ carry-forward rows. |
| J17 | `{{join key:name_first\|2-src:ref\|2-ref:related_staff\|2-use:title}}` | `Jane, Jane Partner` — slot 2 hops the relationship (text ref parity, first target) |
| J18 | `{{join key:name_first\|2-src:site\|2-key:organization_email}}` | `Jane, info@example.test` — site arm present (try_text gap NOT repeated) |
| J19 | `{{join srcTermIn:department\|use:title\|limit:2}}` | `Sales, Support` — per-slot `limit` threaded; term list joined by text's default inner sep `', '` (ADR 0003), independent of join's `sep` |

## Reveal (editor-only — open a join block on the testbed editor)

| # | Case | Expected |
|---|---|---|
| J20 | slot 2 has neither `key` nor non-default `use` | slot 3 controls hidden (reveal keys on `2-key not_empty` OR `2-use not_empty`, NOT `2-src`) |

## Fail triage

Fill in per failure; seam-implicating failures route to `text-test-matrix.md` §Fail triage.

| Symptom | Likely cause |
|---|---|
| J15/J18/J19 right value but missing arm behavior | absorb seam not threading the src:site / list arm — check `bws_join_resolve_slot` still delegates to `bws_base_text_resolve_value` |
| J14 shows `5'` | join re-decided `'0'` emptiness — VIOLATES absorb; hunt for a truthiness check on slot values |
| J12 keeps `"` | Step-1 unit-quote shed regressed (`bws_join_remove_empty_token`) — run the pure harness |
| J19 shows one term | per-slot `limit` no longer threaded into `$slot_opts` |
| Template rows render the raw tag string | `}`/brace leaked into the wire format — GB kills the tag match (`[^}]+`); check `%N` translation + help text |
| J16b second value from ambient page | `$last_ref` cleared on carry-forward — see the callback PHPDoc (deliberately NOT cleared) |
