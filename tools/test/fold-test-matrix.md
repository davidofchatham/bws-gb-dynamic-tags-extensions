# Folded-Slot Wire + Src-Chain Regression Matrix (FW-56/57)

**Standing manual regression suite** for the folded slot wire (`{{join A:…|B:…}}`, `try_* A:…`) and
the depth-0 source CHAIN on base tags (`src:refs,<field>`). Covers what the pure harnesses
structurally cannot: real ACF/meta reads, real ambient context, the container render arms, and the
editor controls.

> **Re-run trigger:** any change to the fold seam (`includes/helpers/slot-fold.php`,
> `slot-fold-compile.php`, `slot-fold-migrate.php`), a container's slot loop (`bws_join_callback`,
> `generate_base_try_tags()`), the fold config builder (`bws_build_fold_slot_options`), or the
> editor pair (`assets/js/slot-fold-control.js`, `slot-fold-migrate.js`).
> **Pure harnesses are the cheap gate — run them FIRST**, they own the algorithms:
> `slot-fold-test.php`, `slot-fold-twin-test.php` (needs `node`), `fold-chain-compile-test.php`,
> `fold-migration-test.php` (needs `node`), `slot-options-build-test.php`,
> `traversal-pipeline-test.php`, `node slot-fold-repeater-test.js`. Rows here assert only what
> needs real WP state. A row failure that implicates the ABSORB seam routes to
> `text-test-matrix.md`; one that implicates join assembly routes to `join-test-matrix.md`.

**How to run:** rows are `render-tag` one-liners against the seeded testbed (state:
`core-structures` blueprint **v7** — `bin/seed.sh testbed core-structures`). From the wp-litespeed
env:

```bash
bin/wp.sh testbed bws render-tag '{{TAG}}' --url=https://testbed.test/CONTEXT/ --porcelain
```

Contexts used:
- `/matrix-post-meta/` — post arm. `main_line` `(987) 654-3210`, `booking_line` `987.654.3210`,
  `role` `Captain`, `name_first` `Jane`, `related_staff` → **Jane Partner, Tom Associate** (that
  order), department terms **Sales + Support**, `team_members` repeater (Alice/Bob), site options.
- `/staff/jane-partner/` — SPARSE person: `name_first` `Jane`, `name_last` `Johnson`,
  `name_generation`/`name_credential` empty; `main_line` `(555) 200-3000`,
  `contact_email` `jane@example.test`, `event_datetime` `2030-05-01 10:00`. **No department terms**,
  and **no `reports_to`** — that emptiness is load-bearing for F8.8.
- `/staff/tom-associate/` — DENSE person (every `name_*` populated). Carries **`reports_to` → Jane**
  (blueprint v7), the blueprint's only staff→staff link and the second degree every two-relationship
  chain hops through. Every other relationship value in the fixture sits on `matrix-post-meta`, so
  before v7 a second `refs` step had nowhere to land and §F8.7's case was untestable.

**Also browsable + editable.** The seed builds the renderable rows as visible GB blocks
(`blocks.php`: a Fold section group on `matrix-post-meta`, folded name rows in the `staff_join`
builder on both staff singles). Open a page on the front end to eyeball output, or open it in the
editor for §F14 — the repeater, the per-slot pickers, the preview text and the mount migration are
reachable ONLY there.

**Rows whose expectation is EMPTY use a split label block** (`bws_fixture_gb_empty_row`): GB hides a
text block whose dynamic tag resolves to nothing, and it hides the whole block — so a one-block row
takes its own static label down with it and the case reads as MISSING FIXTURE. A row that empties
UNEXPECTEDLY still vanishes, which is the signal.

**Era note.** A tag is folded **iff any all-CAPS option key is present** (`A`…`Z`; the legacy `N-` sibling prefixes stay digits) — modes do not mix per
KEY, but they do mix per SLOT (§F2), and the renderer dual-reads: a folded slot parses its value, a
slot with no folded key maps its legacy axes through `bws_fold_from_flat()`. Every §F1 row is
therefore a PAIR: the legacy spelling and the folded one must render **byte-identically**. That
equivalence — not the new capability — is what this matrix mostly asserts.

**Wire note.** Slot options are `;`-separated `name(value)` tokens; chain steps live inside
`src(...)`, `;`-separated, each `slug[,arg][,limit[N]]`. `limit` alternates bracket by depth —
`limit(2)` as a slot option, `limit[2]` inside `src(...)`. A folded key ranks as its SLOT'S SOURCE in
the saved string — after `format`, after any tag-level source key, slots ascending; see §F14.7. (It
LED the whole string while the keys were digits, which is an array-index property JS enumerates
first — escaping that pin is why the keys are capitals.)

> Verified 2026-08-04 against the 1.17.0 build (`feat/table-tag`): every §F1–§F8 and §F10–§F13 row
> below is a MEASURED value, not a predicted one. §F9 recorded four DIVERGENCES at that point; the
> arm refactor (FW-63) turned three of them into equivalences, and those rows are now ACCEPTANCE
> CRITERIA rather than a record. The fourth (`entries` on a base tag) stays divergent by decision.
> **Re-measure §F9 after any arm change** — a wrong arm renders a plausible value, not an empty one.
>
> **Re-run 2026-08-07 (#64 release pass).** Every legacy/folded PAIR in this file — 42 of them — was
> re-measured in all three contexts (`/matrix-post-meta/`, `/staff/jane-partner/`, `/staff/tom-associate/`)
> and all 42 are equivalent in all three. Compare DECODED where a row says so: `antispambot`
> randomizes email entity encoding per render, so F8.1 and F9a.9 differ as raw strings on every run
> and are equal decoded. The pass found and fixed one defect — **§F8.1-F8.3 were stale**, written
> 2026-08-04 and invalidated by `bws_limit_default()` the next day; see the note on §F8. Nothing else
> moved. **The EDITOR rows (§F14) are not covered by this run** and still need a human.

---

## §F1 — join: folded ≡ legacy

Each row is two tag strings that must produce identical output. Context `/matrix-post-meta/` unless
stated.

| # | Legacy | Folded | Expected |
|---|---|---|---|
| F1.1 | `{{join key:name_first\|2-key:name_last}}` | `{{join A:key(name_first)\|B:key(name_last)}}` | `Jane` here; `Jane, Johnson` on jane; `Tom, Smith` on tom |
| F1.2 | `{{join use:title\|2-use:key\|2-key:role\|valueSep: / }}` | `{{join A:use(title)\|B:use(key);key(role)\|valueSep: / }}` | `Matrix: Post Meta / Captain` |
| F1.3 | `{{join key:main_line\|2-src:same\|2-key:booking_line}}` | `{{join A:key(main_line)\|B:src(same);key(booking_line)}}` | `(987) 654-3210, 987.654.3210` |
| F1.4 | `{{join src:ref\|ref:related_staff\|use:key\|key:main_line\|2-src:same\|2-key:contact_email}}` | `{{join A:src(refs,related_staff,limit[1]);use(key);key(main_line)\|B:src(same);key(contact_email)}}` | `(555) 200-3000, jane@example.test` — slot 2 INHERITS the ref hop. **The folded column is what MIGRATION writes** (#60): a chain-spelled slot returns everything, so the flat era's implied 1 has to be stated or the pair stops being one — see §F7a |
| F1.5 | `{{join key:name_first\|2-src:ref\|2-ref:related_staff\|2-use:title}}` | `{{join A:key(name_first)\|B:src(refs,related_staff,limit[1]);use(title)}}` | `Jane, Jane Partner`. Drop the `limit[1]` and the folded side reads `Jane, Jane Partner, Tom Associate` — measured, and the point of §F7a |
| F1.6 | `{{join key:name_first\|2-src:site\|2-key:organization_email}}` | `{{join A:key(name_first)\|B:src(site);key(organization_email)}}` | `Jane, info@example.test` |
| F1.7 | `{{join srcTermIn:department\|use:title\|limit:2}}` | `{{join A:src(terms,department);use(title);limit(2)}}` | `Sales, Support` — the term hop WORKS in a slot (contrast §F9.1) |
| F1.8 | `{{join mode:template\|format:%1 (%2)\|key:name_first\|2-key:name_last}}` | `{{join mode:template\|format:%A (%B)\|A:key(name_first)\|B:key(name_last)}}` | `Jane (Johnson)` on jane; `Tom (Smith)` on tom. **The tokens follow the KEYS** — the legacy column keeps `%1` and the folded column uses `%A`, and equal output is the property |
| F1.8b | — | `{{join mode:template\|format:%1 (%2)\|A:key(name_first)\|B:key(name_last)}}` | same. The DIGIT token spelling is read forever, on folded wire too: both alphabets collapse to one internal token, which is what makes the 1.17.0 move migration-free for hand-pasted wire |
| F1.8c | — | `{{join mode:template\|format:%A (%%B)\|A:key(name_first)}}` | `Jane (%B)` on jane, `Tom (%B)` on tom — `%%` is a literal percent, so `%B` is TEXT and slot 2 is never read. Pins the widened escape surface: `%%` protects a letter now, not just a digit. Without the escape this renders `Jane ()`, which is why a stored pre-1.17.0 literal needs the converter (§F14.7c) |
| F1.9 | J21/J22's 7-slot full name, both spellings (see `join-test-matrix.md` for the format string) | | `Jane Johnson` on jane; `Dr. Tom M. Smith Jr., PhD, USN (Ret.)` on tom |
| F1.10 | `{{join key:name_generation\|2-key:name_credential\|fallback:—}}` | `{{join A:key(name_generation)\|B:key(name_credential)\|fallback:—}}` | `—` on jane (both slots empty → fallback); `Jr., PhD` on tom |

> **The option is `fallback`, not `fallback_text`** (renamed 1.16.0, FW-50 removed the active read
> path). Rows in `join-test-matrix.md` and the visible J3 fixture row still carried the dead key and
> so rendered EMPTY where they claimed `—`; fixed with this matrix. A stored tag is converted by the
> migration entry, but a hand-authored `fallback_text` is simply inert.

## §F2 — mixed-era wire

Era is per SLOT, not per tag. Both directions, `/matrix-post-meta/`.

| # | Tag | Expected |
|---|---|---|
| F2.1 | `{{join A:key(main_line)\|2-src:same\|2-key:booking_line}}` | `(987) 654-3210, 987.654.3210` — folded slot 1, legacy slot 2 inheriting from it |
| F2.2 | `{{join key:main_line\|B:src(same);key(booking_line)}}` | same — legacy slot 1, folded slot 2 inheriting from it |

## §F3 — try_: enum + picker read shape

`try_text` / `try_content` / `try_image`. Context `/matrix-post-meta/`.

| # | Tag | Expected |
|---|---|---|
| F3.1 | `{{try_text A:key(missing_field)\|B:key(role)}}` | `Captain` — slot 1 empty, slot 2 wins |
| F3.2 | `{{try_text A:key(role)\|B:key(name_first)}}` | `Captain` — slot 1 resolves, slot 2 never runs |
| F3.3 | `{{try_text A:key(missing_field)\|B:src(site);key(organization_email)}}` | `info@example.test` |
| F3.4 | `{{try_text A:key(missing_field)\|B:src(refs,related_staff);use(title)}}` | `Jane Partner` |
| F3.5 | `{{try_text A:src(refs,related_staff);key(missing_field)\|B:src(same);key(main_line)}}` | `(555) 200-3000` — slot 2 inherits slot 1's hop |
| F3.6 | `{{try_text key:missing_field\|2-use:key\|2-key:role}}` | `Captain` — legacy twin of F3.1 |
| F3.7 | `{{try_content A:key(missing_field)\|B:key(role)}}` | `Captain` |

## §F4 — try_: picker-alone read shape

`try_email` / `try_phone` — no `use` axis exists; an EMPTY picker is the inherit.

| # | Tag | Context | Expected |
|---|---|---|---|
| F4.1 | `{{try_email A:key(missing_field)\|B:src(refs,related_staff);key(contact_email)}}` | post-meta | `jane@example.test` as a `mailto:` anchor (entities randomized per render — compare the DECODED address) |
| F4.1b | `{{try_email key:missing_field\|2-src:ref\|2-ref:related_staff\|2-key:contact_email}}` | post-meta | legacy twin of F4.1 |
| F4.2 | `{{try_phone A:key(unused_line)\|B:key(main_line)}}` | post-meta | `(987) 654-3210` tel-linked — `unused_line` is seeded EMPTY, so slot 1 is a real skip |
| F4.3 | `{{try_phone A:src(refs,related_staff);key(missing_field)\|B:src(same);key(main_line)}}` | post-meta | `(555) 200-3000` |
| F4.4 | `{{try_phone key:unused_line\|2-key:main_line}}` | post-meta | legacy twin of F4.2 |
| F4.5 | `{{try_phone A:src(refs,related_staff)\|B:src(current)\|key:main_line}}` | post-meta | **EMPTY, and correct** — `key` is a SLOT axis on `try_phone`, so a tag-level `key` configures nothing and both slots have no read. Contrast F5.4, where `key` IS tag-level |

## §F5 — try_: no-read shape

`try_title` / `try_permalink` / `try_datetime_single` / `try_datetime_range` — the read is a
TAG-level option; a slot is a bare source chain.

| # | Tag | Context | Expected |
|---|---|---|---|
| F5.1 | `{{try_title A:\|B:src(site)}}` | post-meta | `Matrix: Post Meta` — an EMPTY slot 1 value is the default attempt |
| F5.2 | `{{try_title A:src(current)\|B:src(site)}}` | post-meta | same. **The 5f bug:** `current` must be a real step — mapping it to "no step" emitted an empty slot value, which is never written, which deletes the whole attempt |
| F5.3 | `{{try_permalink A:src(refs,related_staff)\|B:src(site)}}` | post-meta | `https://testbed.test/staff/jane-partner/` |
| F5.4 | `{{try_datetime_single A:src(refs,missing_rel)\|B:src(current)\|key:event_datetime}}` | post-meta | `August 12, 2030 9:00 AM` — slot 1's hop finds nothing, slot 2 reads the current post. TAG-level `key` survives the fold (§F13) |
| F5.5 | `{{try_datetime_single A:src(refs,related_staff)\|B:src(current)\|key:event_datetime}}` | post-meta | `May 1, 2030 10:00 AM` — jane's value, so slot 1 genuinely won |
| F5.6 | `{{try_datetime_single src:ref\|ref:missing_rel\|2-src:current\|key:event_datetime}}` | post-meta | legacy twin of F5.4 |
| F5.7 | `{{try_permalink A:src(current)\|B:src(site)}}` | jane | `https://testbed.test/staff/jane-partner/` |

## §F6 — carry-forward, inherit, and reset

An absent chain at slot ≥2 is a **RESET to the ambient entity**, not an inherit — absence in folded
wire means what it says, because legacy absence MATERIALIZES to `src(same)` through the mapper.

| # | Tag | Context | Expected |
|---|---|---|---|
| F6.1 | `{{try_text A:src(refs,related_staff);key(missing)\|B:key(main_line)}}` | post-meta | `(987) 654-3210` — slot 2 RESET to the page, NOT jane |
| F6.2 | `{{try_text A:src(refs,related_staff);key(missing)\|B:src(same);key(main_line)}}` | post-meta | `(555) 200-3000` — explicit `same` inherits jane |
| F6.3 | `{{join A:src(refs,related_staff);use(key);key(main_line)\|B:key(contact_email)}}` | post-meta | `(555) 200-3000, (555) 200-4000` — slot 1 is chain-spelled and unbounded, so it returns BOTH staff numbers (#60; it read one before). Slot 2 still resets to the page, which has no `contact_email`, so it drops out — which is what the row is for |
| F6.4 | `{{join A:src(refs,related_staff);use(title)\|B:src(same);use(same)}}` | post-meta | `Jane Partner, Tom Associate, Jane Partner` — slot 1 unbounded (#60), slot 2 inherits BOTH axes and reads the same datum once, because a slot that fans only by inheritance keeps the flat default of 1. That asymmetry is the row's new content. The control's `inferIntent` advisory DESCRIBES it; it does not block it |
| F6.5 | `{{try_phone A:src(refs,related_staff);key(unused_line)\|B:key(main_line)}}` | post-meta | `(987) 654-3210` — reset, on the picker-alone shape |

## §F7 — slot-level `limit`, and the pairs that CROSS

A legacy `limit` with no fanning step stays a slot-level token; with one, the mapper attaches it to
the LAST fanning step. Both spellings are lossless.

**The legacy↔folded pairing crosses on the source axis, and it is the easiest thing in the fold to
get wrong.** Legacy ABSENCE means inherit (it materializes to `src(same)` through the mapper);
folded absence means RESET to the ambient entity. So:

| folded | legacy twin |
|---|---|
| `N:key(x)` | `N-src:current\|N-key:x` |
| `N:src(same);key(x)` | `N-key:x` |

jane carries no `role`, which makes the two readings differ VISIBLY rather than academically —
reset reads the page (`Captain`), inherit reads jane (nothing). All four rows, `/matrix-post-meta/`:

| # | Tag | Expected |
|---|---|---|
| F7.1 | `{{join A:src(refs,related_staff);use(title);limit(2)\|B:key(role)}}` | `Jane Partner, Tom Associate, Captain` — slot 2 RESETS to the page |
| F7.1b | `{{join src:ref\|ref:related_staff\|use:title\|limit:2\|2-src:current\|2-key:role}}` | same — the legacy twin needs the EXPLICIT `2-src:current` |
| F7.2 | `{{join src:ref\|ref:related_staff\|use:title\|limit:2\|2-key:role}}` | `Jane Partner, Tom Associate` — legacy absence INHERITS jane, who has no `role`, so slot 2 drops. **This is the shape the shipped join UI writes** |
| F7.2b | `{{join A:src(refs,related_staff);use(title);limit(2)\|B:src(same);key(role)}}` | same — `src(same)` is how the fold spells that inherit |
| F7.3 | `{{join A:src(terms,department);use(title);limit(2)\|B:key(role)}}` | `Sales, Support, Captain` |

> Caught by eyeballing the visible fixture rows, not by the harness: F7.1 and F7.2 were first
> written into this matrix as a legacy/folded PAIR, and they are not one — they differ by exactly
> the reset-vs-inherit rule §F6 states. The pure harness could not have caught it (both spellings
> resolve correctly; only the PAIRING claim was wrong), which is the argument for the visible rows.

## §F7a — a slot's own spelling decides its own limit (#60)

**A SLOT'S SOURCE SPELLING DECIDES ITS OWN DEFAULT, exactly as a base tag's does.** A chain-spelled
slot with no limit returns everything; a flat-spelled one bounds at 1. Before #60 the dispatch read
its default off the FLATTENED triple, which is structurally blind to how the slot was spelled, so
every slot answered 1 whatever it was. The first pair is the measurement the ticket was filed on.

Context `/matrix-terms-valid/` for the `terms` rows, `/matrix-post-meta/` for the `refs` rows.

| # | Tag | Expected |
|---|---|---|
| F7a.1 | `{{text src:terms,department\|use:title}}` | `Sales, Support` — the base tag, unchanged; the reference the slots must now match |
| F7a.2 | `{{try_text A:src(terms,department);use(title)}}` | `Sales, Support` — **was `Sales`**. Identical spelling, identical answer |
| F7a.3 | `{{join A:src(terms,department);use(title)}}` | `Sales, Support` — same, in the combining container |
| F7a.4 | `{{join A:src(refs,related_staff);use(key);key(main_line)}}` | `(555) 200-3000, (555) 200-4000` — the `refs`-spelled twin of F7a.3 |
| F7a.5 | `{{try_text srcTermIn:department\|use:title}}` | `Sales` — the FLAT spelling still bounds at 1. This row is what makes the four above non-vacuous |
| F7a.6 | `{{join A:src(same);key(b)}}` after a fanning slot 1 | see §F6.4 — a slot that fans only by INHERITING keeps the flat default, because the slot it inherits from stated its own bound. A limit does not carry forward, and never did |

**Migration states what the old spelling implied**, so no stored tag changes output. Each pair below
was run on the testbed and renders identically; the folded column is the shipped migrator's actual
output, taken from `MigrationRegistry::apply_option_migration()` rather than hand-written:

| # | Legacy | Migrated | Expected |
|---|---|---|---|
| F7a.7 | `{{try_text srcTermIn:department\|use:title}}` | `{{try_text A:src(terms,department,limit[1]);use(title)}}` | `Sales` |
| F7a.8 | `{{try_text srcTermIn:department\|use:title\|limit:2}}` | `{{try_text A:src(terms,department,limit[2]);use(title)}}` | `Sales, Support`. The tag-level `limit` reaches every attempt — it was each attempt's own default, not a bound across them — so it lands on the slot's own fanning step, and the key itself is retired (#61, §F7b) |
| F7a.9 | `{{join srcTermIn:department\|use:title}}` | `{{join A:src(terms,department,limit[1]);use(title)}}` | `Sales` |
| F7a.10 | `{{join srcTermIn:department\|use:title\|limit:2}}` | `{{join A:src(terms,department,limit[2]);use(title)}}` | `Sales, Support` |
| F7a.11 | `{{try_text srcTermIn:department\|use:title\|limit:0}}` | `{{try_text A:src(terms,department,limit[0]);use(title)}}` | `Sales, Support`. The explicit `0` KEEPS its carrier on the STEP: the same mapper renders UNMIGRATED flat wire, which takes the flat era's 1, so dropping the token would re-bound a tag its author deliberately unbounded |
| F7a.12 | `{{try_text key:role\|limit:4}}` | `{{try_text A:key(role)}}` | `Captain` on `/matrix-post-meta/`. **A slot with no fanning step gets no limit, and the key goes anyway** — it bounded nothing, so nothing is lost. Slot 1's prefix is `''`, so without the tag-level exclusion it would swallow the key as a slot-level token bounding nothing |
| F7a.13 | `{{join key:main_line\|limit:4\|2-key:booking_line}}` | `{{join A:limit(4);key(main_line)\|B:src(same);key(booking_line)}}` | `(987) 654-3210, 987.654.3210`. The COMBINING contrast: `{{join}}` owns `limit` per slot, so slot 1's bare key IS its own and stays a slot-level token |

> **The `try_` `refs` arm WAS first-only, and #103 is what cleared it.** Through 1.17.0-dev
> `{{try_text A:src(refs,related_staff);use(title)}}` rendered `Jane Partner`, and so did the flat
> spelling with an EXPLICIT `limit:0` — which is what proved it was the ARM rather than the default.
> Same family as the §F9 divergences, and it did NOT clear with FW-63 as this note used to predict:
> FW-63 converted the BASE arms, and `try_`'s four were still testing flat tokens. The arm collapse
> (§F9b) is what closed it. The `terms` arm (F7a.2) already fanned, which is why #60's measurement
> used it.

## §F7b — the `try_` tag-level `limit` is retired (#61)

**`try_`'s tag-level `limit` was never a bound ACROSS attempts — it was each attempt's own default.**
Once an attempt's source is a chain, nothing says which step such a number aims at and there is no
per-step lever to aim it with, so it stops existing: the number is pushed into the slots that
consumed it and the key is deleted. `{{join}}` is untouched — its `limit` has always been a SLOT
axis, which is what F7a.13 pins.

Every pair below was RUN on the testbed and renders identically; the migrated column is the shipped
migrator's actual output. Context `/matrix-terms-valid/` unless noted.

| # | Legacy / pre-#61 | Migrated | Expected |
|---|---|---|---|
| F7b.1 | `{{try_text srcTermIn:department\|use:title\|limit:2}}` | `{{try_text A:src(terms,department,limit[2]);use(title)}}` | `Sales, Support` — F7a.8 with the key now gone |
| F7b.2 | `{{try_text A:src(terms,department);use(title)\|limit:2}}` | `{{try_text A:src(terms,department,limit[2]);use(title)}}` | `Sales, Support`. **The shape the ticket names**: slots ALREADY folded, only the key left. It carries no legacy slot key at all, so it reaches the entry only because `limit` is on the MATCH surface (`bws_fold_migration_match_keys`) |
| F7b.3 | `{{try_text key:role\|limit:4}}` | `{{try_text A:key(role)}}` | `Captain` on `/matrix-post-meta/` — nothing to bound, so nothing is pushed and the key still goes |
| F7b.4 | `{{try_text srcTermIn:department\|use:title\|limit:0}}` | `{{try_text A:src(terms,department,limit[0]);use(title)}}` | `Sales, Support` — an explicit unlimited moves onto the step like any other number |
| F7b.5 | `{{join A:src(terms,department);use(title)\|limit:3}}` | unchanged wire; the migrator only DROPS the bare `limit` as slot 1's legacy sibling | `Sales, Support` both ways — the COMBINING contrast. The bare key is slot 1's own axis, never pushed into a folded slot, and join's arm has no tag-level fallback to read it with, so the folded slot is unlimited before and after |

**What the front end cannot show, and why that is not a gap.** The one shape where output could have
moved is a slot that fans only by INHERITING (`src(same)`, or an argless `refs`): it has no fanning
step of its own to take the number. It does not move, because `src(same)` means the same SOURCE and a
limit is one of a source's parameters — `bws_fold_slot_chain_options()` carries the bound along with
the source, on a selecting container only. That is unobservable here for a structural reason worth
recording: `srcTermIn` does not carry forward in a selecting container (§P14.5), so an inheriting
slot after a `terms` slot reads the ambient entity rather than the terms; and the `refs` arm is
first-only (the note above). So the evidence is `slot-fold-test.php` §P15, which walks the resolved
quantity slot by slot, plus the pairs above. Both spellings measured identical either way:

| # | Legacy | Migrated | Measured |
|---|---|---|---|
| F7b.6 | `{{try_text src:ref\|ref:related_staff\|use:key\|key:no_such\|2-src:same\|2-use:title\|limit:2}}` | `{{try_text A:src(refs,related_staff,limit[2]);key(no_such)\|B:src(same);use(title)}}` | `Jane Partner` both, on `/matrix-post-meta/` — first-only arm, so the carried bound is invisible until FW-63 |
| F7b.7 | `{{join srcTermIn:department\|use:title\|limit:2\|2-key:blurb}}` | `{{join A:src(terms,department,limit[2]);use(title)\|B:src(same);key(blurb)}}` | `Sales, Support, Sales handles quotes, renewals and the annual customer roadshow.` both — slot B inherits the HOP (#74) but not the BOUND, so it reads one term's blurb rather than two. Before #74 it read the page and contributed nothing, so this row did not exercise the property its label claimed |

## §F7c — the tag-level Result Limit CONTROL is gone; the VALUE is not (#62)

**Removing an option never removes its value.** GB seeds `extraTagParams` from the parsed tag
string, not from the option registry, and re-serializes the whole state object — so a stored
`limit` on any of the six chain-authoring base tags still round-trips and still bounds the list,
with no control anywhere in the panel. That is what keeps unmigrated flat wire (the scanner reads
`post_content` only, so wire in an ACF field is unreachable) and hand-edited wire meaning what they
say (ADR 0004).

The reader is untouched by #62 — these rows exist to PROVE that, so each family gets its own,
and F7c.2 is what makes the rest non-vacuous: unset still bounds at 1 on flat wire, so a row
printing two terms is the limit being read rather than the tag fanning by default.

Context `/matrix-post-meta/`. All RUN, and the visible blocks EYEBALLED on the front end (user, 2026-08-07).

| # | Stored wire (no control writes this any more) | Expected |
|---|---|---|
| F7c.1 | `{{text srcTermIn:department\|use:title\|limit:2}}` | `Sales, Support` |
| F7c.2 | `{{text srcTermIn:department\|use:title}}` | `Sales` — unset is still 1 on flat wire, which is what makes F7c.1 mean something |
| F7c.3 | `{{text srcTermIn:department\|use:title\|limit:0}}` | `Sales, Support` — `0` is still UNLIMITED. **On MIGRATION this key is deleted, not carried** (#62): the wire becomes `{{text src:terms,department\|use:title}}`, which means the same thing (chain spelling selects unlimited) and leaves nothing the panel cannot reach. Measured identical for all three spellings, including the intermediate `src:terms,department,limit(0)` |
| F7c.4 | `{{title srcTermIn:department\|limit:2}}` | `Sales, Support` |
| F7c.5 | `{{email src:ref\|ref:related_staff\|key:contact_email\|limit:2\|noLink}}` | `jane@example.test, tom@example.test` (entity-encoded by `antispambot`) |
| F7c.6 | `{{phone src:ref\|ref:related_staff\|key:main_line\|limit:2\|noLink}}` | `(555) 200-3000, (555) 200-4000` |
| F7c.7 | `{{datetime_single src:ref\|ref:related_staff\|key:event_datetime\|limit:2\|as:date}}` | `May 1, 2030, June 1, 2030` |

`try_`'s half of the same property is §F7b (the value survives the key's retirement). The
registration side — that no panel offers the control, and that `sep` stayed — is a pure assertion,
`php tools/test/control-order-test.php` §5, because the ABSENCE of a control is not visible in
rendered output.

## §F7d — `src(same)` inherits the TERM HOP (#74)

`src(same)` names the same SOURCE, and a taxonomy step is part of what the source IS — unlike
`limit`, which is a parameter *of* a source and stays container-sensitive (§F7b/§F7c). Before #74 a
leading `terms` step left `src` unset, so an inheriting slot inherited an empty source and read the
AMBIENT entity: a plausible value from the wrong place, which is why nothing looked broken.

Run on `/matrix-terms-mixed/`. The `Before` column is what shipped through 1.16.x, kept because the
whole point is that it rendered something rather than nothing.

| # | Tag | Before (1.16.x) | Now |
|---|---|---|---|
| F7d.1 | `{{join A:src(terms,department,limit[2]);use(title)\|B:src(same);key(phone)}}` | `Sales, Support` — slot B silently contributed nothing | `Sales, Support, (987) 333-4444` |
| F7d.2 | `{{join A:src(terms,department,limit[2]);use(title)\|B:src(same);use(same)}}` | `Sales, Support, Matrix: Terms (mixed junk)` — the PAGE title, which is what named the bug | `Sales, Support, Sales` |
| F7d.3 | `{{join A:src(terms,department,limit[2]);use(title)\|B:src(current);key(phone)}}` | — | `Sales, Support` — a slot stating its OWN root does not acquire the carried hop, and the page has no `phone` |
| F7d.4 | `{{join A:src(terms,department);use(title)\|B:src(same;terms,office);use(title)}}` | — | `Sales, Support, Warehouse` — an inherited hop is a DEFAULT: slot B's own `terms` REPLACES it rather than colliding, so this is a term read of `office`, not a skipped slot |

The legacy twin of F7d.1/.2 is `{{join srcTermIn:department|use:title|limit:2|2-src:same|2-key:phone}}`
and renders identically — the fix is uniform across both eras, so there is no era gate and the
legacy↔folded equivalence property holds unchanged (`slot-fold-test.php` §P13.1/§P14).

Not container-sensitive: the selecting twin behaves the same, and §P15 is the test that tells this
apart from the two axes that ARE split (`limit` and the read axis are both about what ABSENCE means,
and differ only because the families registered those keys differently).

## §F8 — depth-0 src chain on base tags (5h)

The compiler translates chain wire into engine steps on every base tag. Pairs again — the legacy
spelling is the reference.

⚠ **The pair is legacy vs MIGRATED, not legacy vs bare chain** (corrected 2026-08-07 by re-running
these rows). F8.1-F8.3 were written 2026-08-04, one day before `bws_limit_default()` landed, and
they asserted that a bare chain matches the legacy spelling it replaces. It does not, deliberately:
a flat source bounds its list at 1 and a chain source is unlimited, which is the whole compatibility
mechanism (`tag-reference.md` §List mode, [ADR 0005](../../docs/adr/0005-limits-are-stated-where-the-source-is-stated.md)).
The equivalence that holds is against what MIGRATION writes — `limit(1)` on the fanning step — and
the bare chain is a THIRD, different expectation rather than a failure. The rows below now state all
three, which is also what makes them catch a migration that stops writing the step limit. `{{text}}`'s
twin of this is `limit-default-test-matrix.md` §L4.1/L4.2/L4.10; §F8's value is the OTHER families.

| # | Legacy | Chain | Expected |
|---|---|---|---|
| F8.1 | `{{email src:ref\|ref:related_staff\|key:contact_email}}` | `{{email src:refs,related_staff,limit(1)\|key:contact_email}}` | both: jane's address as a `mailto:` anchor. Bare `src:refs,related_staff` renders **jane AND tom** — unlimited, by design. **Compare DECODED** — `antispambot` randomizes the entity encoding per render, so the two raw strings differ even when equal |
| F8.2 | `{{phone src:ref\|ref:related_staff\|key:main_line}}` | `{{phone src:refs,related_staff,limit(1)\|key:main_line}}` | both: `(555) 200-3000`, tel-linked. Bare `src:refs,related_staff` renders **both numbers** — see F8.4, which is that expectation stated on purpose |
| F8.3 | `{{text src:ref\|ref:related_staff\|use:title}}` | `{{text src:refs,related_staff,limit(1)\|use:title}}` | both: `Jane Partner`. Bare `src:refs,related_staff` renders `Jane Partner, Tom Associate`. This row and `limit-default-test-matrix.md` §L4.1/L4.2 are the same three tags; if they ever disagree, L4 is the newer statement |
| F8.4 | `{{phone src:ref\|ref:related_staff\|key:main_line\|limit:0}}` | `{{phone src:refs,related_staff\|key:main_line\|limit:0}}` | both numbers — `(555) 200-3000, (555) 200-4000` |
| F8.5 | — | `{{phone src:refs,related_staff,limit(1)\|key:main_line\|limit:0}}` | ONE number. **Per-hop limit** — bounds the fan-out's spread |
| F8.6 | — | `{{phone src:refs,related_staff,limit(2)\|key:main_line\|limit:0}}` | both numbers. F8.4/F8.5/F8.6 together are what separate the hop limit from the terminal `limit` |
| F8.7 | — (INEXPRESSIBLE) | `{{text src:refs,related_staff;refs,reports_to\|use:title}}` | `Jane Partner`. **THE SPEC'S OWN HEADLINE CASE** (#55: "the office of the staff member this event references") — data two relationships away, which the flat spelling cannot state at all, hence the empty Legacy column. Blueprint **v7** added the second-degree link: `reports_to` (staff→staff) on tom only, so Tom→Jane resolves and Jane's own empty branch DROPS rather than erroring |
| F8.8 | — | `{{text src:refs,related_staff,limit(1);refs,reports_to\|use:title}}` | **EMPTY.** Step 1 is bounded to Jane (first target), Jane reports to nobody, so the chain short-circuits. F8.7's partner: without it, a step limit that bounded the WRONG step — or nothing — still passes F8.7 |
| F8.9 | — | `{{text src:refs,related_staff;refs,reports_to\|use:key\|key:main_line}}` | `(555) 200-3000`, Jane's line. Reads a FIELD off the second-degree post rather than its title, so the chain is proven to land on a real entity and not merely to produce a plausible string |
| F8.10 | — | `{{text src:refs,related_staff;terms,portal_visibility,limit(1)\|use:title}}` | `All Users, All Users` — **a LATER step's limit is PER-INPUT (#72)**: one term from EACH ref'd staff, not one overall. The whole-output engine rendered `All Users` here — the semantic the decision record, the migration's stamps and the Limit control's help text had already denied. Step 1 must stay UNBOUNDED (two inputs reach the limited step); `portal_visibility` because jane and tom carry no department terms (same fixture fact as F9.6). Pure pins: `fold-chain-compile-test.php` §C7 per-input cases |

### §F8b — an ARGLESS step on a base tag reads EMPTY, not the ambient entity (#74)

The base-tag half of the same fix as §F7d, and the only part of it that moves BASE-tag output.
Through 1.16.x the compiler DROPPED an argument-less fanning step, which left the chain with no
steps — and a chain with no steps resolves the ambient entity. So a tag whose wire said "follow a
relationship" read the entry it sat on.

**A skip and an empty read are indistinguishable in rendered output**, so these rows use
`bws_fixture_gb_empty_row`: GB hides a block whose tag renders nothing, taking a single-block row's
own label with it, and the row would read as a MISSING FIXTURE. The pure pins are
`fold-chain-compile-test.php` §C3/§C6 and `traversal-pipeline-test.php`.

Run on `/matrix-post-meta/`, where `related_staff` resolves and the page carries its own `main_line`
— that contrast is the whole point, since the defect returned the PAGE's value.

| # | Tag | Before (1.16.x) | Now |
|---|---|---|---|
| F8b.1 | `{{text src:ref\|use:key\|key:main_line}}` | `(987) 654-3210` — the PAGE's own field, from a tag naming a relationship | **EMPTY** |
| F8b.2 | `{{text src:refs\|use:key\|key:main_line}}` | same, in chain spelling | **EMPTY** |
| F8b.3 | `{{text src:ref\|ref:related_staff\|use:key\|key:main_line}}` | `(555) 200-3000` | unchanged — the negative control: a step WITH its argument is untouched |
| F8b.4 | `{{text src:terms\|use:title}}` (on `/matrix-terms-mixed/`) | the page title | **EMPTY.** Hand-edit-only, twice over: the `bws-term-hop` control never writes an empty slug, and the chain control never commits a step it cannot complete |
| F8b.5 | `{{text src:terms,department\|use:title}}` (on `/matrix-terms-mixed/`) | — | `Sales, Support, Warehouse` — the negative control for F8b.4 |

**A flat `srcTermIn:` set to empty is NOT this shape and did not change.** The compiler appends a
term step only when the value is non-empty, so an empty one never becomes an argless step — it means
"no term step", which is exactly what the flat spelling has always meant. Only chain wire can state a
`terms` step without its taxonomy.

The SLOT half of the same rule is §F7d's neighbourhood, and differs in one way worth restating: on a
slot an argless `refs` step is COMPLETE when the carry supplies its field, so it is skipped only when
nothing was ever carried. A base tag has no carry, so it is always unfinished.

## §F9 — ARM DISPATCH: chain wire on a BASE tag (FW-63)

**These rows were the recorded failing state, and they are now the acceptance criteria.** They used
to carry an instruction not to patch them with guards, because a base tag's rendered output was
chosen by ARMS gating on the flat `src`/`srcTermIn` option tokens (~10 sites read `srcTermIn`; ~6
compared `src` to `'ref'`/`'site'`) while the compiler gave the ENGINE arbitrary hops. Since 1.17.0
every arm asks `bws_fold_src_resolution()` — the chain's resolved-source KIND plus whether it fans —
so the two spellings take the same arm.

**Run each pair and compare. A divergence here is the arm refactor regressing**, and the failure
shape is the bad one: a wrong arm renders a PLAUSIBLE value, not an empty one, so a row that "looks
fine" is not evidence.

> **MEASURED 2026-08-05** against the branch, every pair matching. One caveat a reader must
> carry: §F9a.7/§F9a.8 are vacuous until the blueprint seeds attachments. An equivalence row
> proves the two spellings AGREE; it never proves either is right — which is exactly how
> §F9a.3/§F9a.4 sat green for a release while `{{content}}`'s hop rendered the ambient entity's
> VALUES inside the hopped post's structure ([#58](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/58), fixed 1.17.0; correctness now lives in
> [`content-test-matrix.md`](content-test-matrix.md), which is where a wrong entity fails).

| # | Legacy | Chain | Expected |
|---|---|---|---|
| F9.1 | `{{text srcTermIn:department\|use:title\|limit:0}}` | `{{text src:terms,department\|use:title}}` | `Sales, Support`. **The chain spelling needs no `limit`** — flat wire bounds at 1, chain wire does not (`bws_limit_default`), which is the whole compatibility mechanism. Pre-1.17.0 the chain rendered `Matrix: Post Meta`, the PAGE title |
| F9.2 | `{{text src:ref\|ref:related_staff\|use:title\|limit:0}}` | `{{text src:refs,related_staff\|use:title}}` | `Jane Partner, Tom Associate`. Pre-1.17.0 the chain rendered `Jane Partner` alone — list mode was gated on `src` being literally `'ref'` |
| F9.3 | — | `{{text src:refs,related_staff;terms,department\|use:title}}` | EMPTY (jane and tom carry no department terms). Pre-1.17.0 it rendered `Jane Partner`: the post-semantic wrapper took the chain's LEADING RUN of `ref` steps and stopped, so a non-leading hop was silently dropped and the tag read the ref'd POST. **Negative control below** |
| F9.3b | — | `{{text src:refs,related_staff;terms,department\|use:title\|fallback:NOHOP}}` | `NOHOP` — the row that makes F9.3 non-vacuous. An empty read and a dropped hop both print nothing, so F9.3 alone cannot tell them apart; this pins that the tag resolved and found nothing |
| F9.4 | `{{text src:site\|key:organization_email}}` | — | `info@example.test`. **`src:site` still wins over a hand-edited `srcTermIn`** (`{{text src:site\|srcTermIn:department\|key:organization_email}}` renders the same): the pair is hand-edit only (`show_if src: not:site`) and every arm has always let the site read win, so the compiler does not fold that hop in. **The key must be one the blueprint SEEDS** — this row read `org_name` until 2026-08-17, which `core-structures` has never carried, so both spellings rendered empty and the row asserted nothing while looking green. The visible rows in `blocks.php` had the seeded key all along; only the matrix text drifted |

### §F9a — per-family equivalence

Arm dispatch is one query, but each family reaches it through its own callback, and a
family with no list mode takes a different branch from one that has. One `refs` pair and one
`terms` pair per family; `{{text}}`'s are F9.1/F9.2 above.

| # | Legacy | Chain | Expected |
|---|---|---|---|
| F9a.1 | `{{title src:ref\|ref:related_staff\|limit:0}}` | `{{title src:refs,related_staff}}` | `Jane Partner, Tom Associate` (list-capable) |
| F9a.2 | `{{title srcTermIn:department\|limit:0}}` | `{{title src:terms,department}}` | `Sales, Support` |
| F9a.3 | `{{content src:ref\|ref:related_staff}}` | `{{content src:refs,related_staff}}` | the two must MATCH, on JANE's content — her `J1` row reads `Jane, Johnson`. The pair is an EQUIVALENCE only; that the entity is right is [`content-test-matrix.md`](content-test-matrix.md) §CT1/§CT2's property, and was [#58](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/58) |
| F9a.4 | `{{content srcTermIn:department\|use:key\|key:blurb}}` | `{{content src:terms,department\|use:key\|key:blurb}}` | first non-empty term blurb — the Sales blurb (support carries none). Correctness lives in §CT5 |
| F9a.5 | `{{permalink src:ref\|ref:related_staff}}` | `{{permalink src:refs,related_staff}}` | jane's URL. Not list-capable |
| F9a.6 | `{{permalink srcTermIn:department}}` | `{{permalink src:terms,department}}` | first term URL |
| F9a.7 | `{{image src:ref\|ref:related_staff\|use:featured\|as:url}}` | `{{image src:refs,related_staff\|use:featured\|as:url}}` | jane's featured image URL. Not list-capable. ⚠ **VACUOUS TODAY** — the blueprint seeds no attachments, so both sides render empty and the row asserts nothing. It needs a fixture (a featured image on a staff single and an image field on a department term) before it is worth trusting |
| F9a.8 | `{{image srcTermIn:department\|key:term_image\|as:url}}` | `{{image src:terms,department\|key:term_image\|as:url}}` | first non-empty term image. Same vacuity as F9a.7 |
| F9a.9 | `{{email src:ref\|ref:related_staff\|key:contact_email\|limit:0}}` | `{{email src:refs,related_staff\|key:contact_email}}` | both addresses, each `mailto:`-wrapped. **Compare DECODED** — `antispambot` randomizes the encoding per render |
| F9a.10 | `{{phone src:ref\|ref:related_staff\|key:main_line\|limit:0}}` | `{{phone src:refs,related_staff\|key:main_line}}` | `(555) 200-3000, (555) 200-4000` |
| F9a.11 | `{{phone srcTermIn:department\|key:dept_line\|limit:0}}` | `{{phone src:terms,department\|key:dept_line}}` | whatever the department terms carry; the PAIR matching is the property |
| F9a.12 | `{{datetime_single src:ref\|ref:related_staff\|key:event_datetime\|limit:0}}` | `{{datetime_single src:refs,related_staff\|key:event_datetime}}` | both dates |
| F9a.13 | `{{datetime_single srcTermIn:department\|key:event_date\|limit:0}}` | `{{datetime_single src:terms,department\|key:event_date}}` | both term dates |
| F9a.14 | `{{datetime_range srcTermIn:department\|startKey:event_date\|limit:0}}` | `{{datetime_range src:terms,department\|startKey:event_date}}` | both ranges, `sep`-joined |

> The chain column carries no `limit` on the list-capable rows and the legacy column carries
> `limit:0`, and that asymmetry IS the equivalence: flat wire bounds at 1, chain wire does not.
> A chain row that needs `limit:0` to match means `bws_limit_default` regressed.

**Still divergent, deliberately:**

| # | Tag | Renders | Cause |
|---|---|---|---|
| F9.5 | `{{text src:entries,team_members\|use:key\|key:name}}` | empty | **NOT IMPLEMENTED YET — FW-74 — rather than unsupported** (reason rewritten 2026-08-15, #105). The wire is well formed, the chain compiles, the engine runs the step and `bws_fold_chain_resolution()` answers kind `meta_row`; both `bws_read_resolved_source()` and `traversal-pipeline.php` already carry a live `case 'meta_row'`. What is missing is the ARM — `bws_base_text_resolve_value()` dispatches on `site`/ambient-term/ambient-user/`term`/`post` and this kind falls through. `{{table}}` wants a repeater row as its cells' read CONTEXT and a fanning tag would concatenate it like any other step, so nothing here is an author error, which is why the inert-chain warning deliberately does NOT flag it. `entries` stays off the base chain control's step enum until the arm lands; authoring it requires a hand edit. Design: `.claude/plans/table-tag.md` §FW-74 |
| F9.6 | `{{text src:ref\|ref:related_staff\|srcTermIn:portal_visibility\|use:title\|limit:0}}` | `All Users, All Users` (was `All Users`) | **A flat-wire behaviour change, stated rather than hidden — and MEASURED, both sides.** The term arm used to collapse the relationship step to its FIRST post (`bws_get_srcterm_terms` took one post id) and read that post's terms; it now runs the whole compiled chain, which fans (§V6). Reachable ONLY with an explicit `limit` above one: drop the `limit:0` and both `main` and this branch render `All Users`, which is the compatibility floor holding. The surveyed corpus contains **zero** explicit `limit` values. Accepted because the alternative is keeping a first-only collapse the plural source model already calls a defect, in the one arm still performing it. **`department` will NOT do for this row** — jane and tom carry none, so the pair is empty either way and asserts nothing; `portal_visibility` is the taxonomy the blueprint actually puts on them |

**The contrast this matrix used to draw** — F9.1 and F1.7 as one term hop with two answers, decided
by whether an arm was involved — is what closed. They are now the same hop with the same answer.

## §F9b — ARM DISPATCH: the `try_` slot arms (#103, FW-71)

FW-63 converted the BASE arms; `try_`'s four were still testing the flat tokens
(`'' !== $stm_raw`, `'site' === $last_src`, `'current' === $last_src`, else post). #103 collapsed
them onto one dispatch keyed by resolved source kind, through the pure table in
`includes/helpers/try-slot-arms.php`.

**The property is EQUIVALENCE, and it was measured as a before/after diff** — 34 (tag × URL) pairs
across `/matrix-post-meta/`, `/department/sales/`, `/author/fixture-author/`,
`/staff/jane-partner/` and `/matrix-terms-valid/`, rendered through `wp bws render-tag` on the
same testbed with only the plugin source swapped. Every pair was byte-identical except the ones
below. Re-run the sweep, not one row, after any arm change: a wrong arm renders a plausible value
rather than an empty one, so a single green row proves very little.

**Stated behaviour changes, both measured on both sides:**

| # | Tag | Was → is | Cause |
|---|---|---|---|
| F9b.1 | `{{try_text A:src(refs,related_staff);use(title)}}` | `Jane Partner` → `Jane Partner, Tom Associate` | the `refs` arm was FIRST-ONLY: it collapsed the relationship to one post id (`bws_resolve_post_by_source`) and called the core once. It reads every fanned target now (`bws_base_post_ids_from_source`, §V6), which is what the base tag has done since FW-63 (§F9.2). **Reachable only where the slot is UNBOUNDED** — chain-spelled here (chain wire's default is unlimited), or flat with an explicit `limit:0`/`limit:2` |
| F9b.2 | `{{try_text src:ref\|ref:related_staff\|use:title\|limit:0}}` | `Jane Partner` → `Jane Partner, Tom Associate` | same arm, flat spelling with an explicit unlimited. **The compatibility floor is the row beside it**: drop the `limit:0` and both sides render `Jane Partner`, because flat wire still bounds at 1 (`bws_limit_default`). The surveyed two-database corpus contains zero explicit `limit` values |
| F9b.3 | `{{try_phone src:ref\|ref:related_staff\|key:main_line\|limit:0}}` | one number → both numbers | F9b.2 in another family; kept because the phone core self-wraps each value, so it is the row that shows the change is in the ID RESOLUTION and not in the join seam |
| F9b.4 | `{{try_text src:ref\|ref:related_staff\|srcTermIn:portal_visibility\|use:title\|limit:0}}` | `All Users` → `All Users, All Users` | the `try_` twin of §F9.6, arriving for the same reason and one release later. The term arm ran one post→terms hop off a collapsed post id; it runs the whole compiled chain now |
| F9b.5 | `{{try_phone src:site\|srcTermIn:department\|key:org_phone}}` | empty → the site value | **A PRECEDENCE change, not a fanning one.** The old arms tested `srcTermIn` FIRST, so a site-rooted slot carrying a hand-edited `srcTermIn` took the term arm, resolved no post, and skipped the attempt whole. Kind dispatch answers `site` for that wire — because `bws_fold_chain_from_options()` refuses to append a term step to a site root — so the slot now reads the site, which is **exactly what the base tag has always done** (§F9.4: `{{phone src:site\|srcTermIn:department\|key:org_phone}}` renders the site value on both sides of this change). The pair is hand-edit-only (`srcTermIn` is registered `show_if src: not:site`), and closing it is [I6] slot transparency rather than a regression: `try_` was the one container where the site read did NOT win |

**What did NOT move, and each is a row the collapse could plausibly have broken:**

| # | Tag | Renders |
|---|---|---|
| F9b.6 | `{{try_text src:ref\|ref:related_staff\|use:title}}` | `Jane Partner` — the compatibility floor. Flat, unbounded wire still bounds at 1 |
| F9b.7 | `{{try_phone src:site\|key:org_phone}}` | the site number, `tel:`-wrapped — the site arm's SECOND leg (no `try_site_fn` on phone; `try_core_fn( 0, … )` serves it and takes no link identity of its own) |
| F9b.8 | `{{try_title}}` on `/department/sales/` | `Sales` — the ambient-term arm, which is now a BRANCH off the root-only `base` kind rather than a `'current' === $last_src` test |
| F9b.9 | `{{try_title linkTo:post}}` on `/staff/jane-partner/` | the linked title — per-arm link-wrap entity survived the merge into one emit |
| F9b.10 | `{{try_text srcTermIn:department\|use:title\|linkTo:term}}` | the linked term title — the same, on the arm with a different entity type |
| F9b.11 | `{{try_text A:src(entries,team_members);use(key);key(name)\|B:key(role)}}` | `Captain` — a repeater-row source is still refused, and slot 2 still runs |
| F9b.12 | `{{try_text A:src(refs,related_staff;terms,department);use(title)\|B:key(role)}}` | `Captain` — an inexpressible chain still skips at the SEAM, which #103 did not touch (#104 dissolves it) |
| F9b.13 | `{{try_text use:title}}` / `{{try_title}}` / `{{try_content}}` on `/author/fixture-author/` | **RENDER-TAG ONLY, stated exception** (an author ARCHIVE is the ambient context and has no page content to hang a fixture row on — the same exception text T4 takes for a term archive). EMPTY, all three — the [I6] parity defect is deliberately still open here. The `user` row exists in the arm table and is `branchable`; no template carries a user function yet, so the dispatcher's fn-absent fallthrough sends it to the post arm exactly as the token arms did. **#108 is what flips these three**, with its own replay run whose expected diff is exactly these rows |

## §F9c — MODE 2b: the flat ACF repeater row (the loop fallthrough)

**`meta_row` names ONE resolved-source kind, and a slot can arrive at it two ways.** The two need
opposite answers, which is the whole content of this section:

- **Off the WIRE** — `src(entries,…)`, so `bws_fold_chain_resolution()` answers `meta_row`
  statically, before anything renders. The author asked for repeater rows, and no `try_` arm
  assembles those. **Refuse**; `{{table}}` owns that.
- **Off the AMBIENT CONTEXT** — the wire is silent (`src:current`), the chain kind is `base`, and
  the factory comes back with a `meta_row` because the tag is standing inside a GB Pro repeater
  loop. The author asked for *here*. **Continue to the post arm**, which resolves no id, at which
  point the loop fallthrough hands the row to the core fn, which reads `$loop_item[$key]`.

Measured inside the loop: `in_loop=true`, `row_post_id=false`, base kind `meta_row`,
`bws_base_post_ids_from_source()` `[]`, `bws_resolve_post_by_source()` `false`. So the fallthrough
is not a defensive branch — it is the only thing that renders these rows at all.

**NOTHING ELSE REACHES THIS.** A `WP_Query` loop's rows are `WP_Post` objects, so a post id always
resolves and the branch never runs; `wp bws render-tag --loop-item=<id>` takes a post id by
construction. Until #103 the branch had **no rendered coverage on any tag family** — `{{call}}`'s
[R1.4](call-test-matrix.md) names the case but records it as a known limit rather than exercising
it. The rows below are on `/matrix-post-meta/`, inside a GB Pro `post_meta` query loop over the
seeded `team_members` repeater.

| # | Tag | Expected |
|---|---|---|
| F9c.1 | `{{text key:name}}` | `Alice Adams` / `Bob Brown`, one per loop row — the BASE tag's own path, which is a control rather than the subject |
| F9c.2 | `{{try_text A:key(name)}}` | the SAME two names. This is the `try_` fallthrough |
| F9c.3 | `{{try_text A:key(nope)\|B:key(role)}}` | `Engineering` / `Operations` — the attempt chain still advances inside a row: slot 1 takes the fallthrough and finds nothing, slot 2 takes it and hits |
| F9c.4 | `{{try_text A:src(entries,team_members);use(key);key(name)\|B:key(role)}}` | `Engineering` / `Operations` — **the row where both arrival routes meet and stay apart.** Slot 1 states a repeater source ON THE WIRE while STANDING IN a repeater row: refused as a chain kind. Slot 2's silent wire takes the fallthrough and resolves |
| F9c.5 | `{{try_text A:src(refs,lead_ref);use(title)}}` | `Jane Partner`, then EMPTY — a relationship sub-field still hops out of the row. Row 2 leaves `lead_ref` blank, and GB hides the empty block, so only one row shows |

> **VERIFIED BY MUTATION, and the first attempt was an ARTIFACT.** Two were run and both blank the
> section: deleting the fallthrough gate (F9c.2/3/4 render nothing; F9c.1 survives, which is what
> shows the base tag has its own path), and refusing a `meta_row` BASE in
> `bws_try_slot_base_branch_kind()` instead of branching it to the post arm (every row goes, and
> `try-slot-arms-test.php` §A4.4 fails beside it).
>
> **Both mutations first appeared to change NOTHING, and the reason is worth carrying:** the
> container runs `opcache.revalidate_freq = 120`, so a front-end request inside two minutes of an
> edit executes STALE BYTECODE while the disk bytes are already correct. Restart the container
> between mutation arms (`docker restart wp-litespeed-litespeed-1`), or wait out the window. WP-CLI
> is unaffected (`opcache.enable_cli = Off`), which is why the `render-tag` sweeps in §F9b needed no
> such handling.

## §F10 — a multi-step slot source RESOLVES (#104, FW-71)

**INVERTED at 1.17.0, and this section is the acceptance signal.** It used to read "a slot the flat
seam cannot express SKIPS": a slot's chain was re-spelled as a flat `src`/`ref`/`srcTermIn` triple
before any container arm saw it, the triple held ONE relationship step and ONE term step, and
anything else rendered nothing rather than resolve a truncated prefix. The seam hands the whole
chain on as depth-0 chain wire now, so a slot's source is a base tag's source
([`CONTEXT.md` I16](../../CONTEXT.md)) and there is nothing left to refuse.

**Run each pair.** The base-tag column is not decoration — it IS the property: identical wire in a
slot and on a base tag must render identically, and a row that only asserts the slot side cannot
tell "resolved correctly" from "resolved plausibly".

> **MEASURED 2026-08-15** on `testbed`, every row as stated, front end and `render-tag` alike.
> §F9b and §F9c were re-swept unchanged in the same pass (F9c needed the container restarted — see
> the opcache note there), and a 35-case `render-tag` before/after diff across `/matrix-post-meta/`,
> `/department/sales/`, `/staff/jane-partner/` and `/matrix-terms-valid/` came back 34 byte-identical,
> the one difference being `{{try_email}}`'s per-render `antispambot` encoding.
>
> **§F9's own two divergences did NOT flip and were never meant to** — F9.5 and F9.6 are BASE-tag
> facts, unrelated to the slot seam. The four that flipped are this section's, and their harness
> twins are `slot-fold-test.php` §P13.5.

| # | Slot tag | Base twin | Expected |
|---|---|---|---|
| F10.1 | `{{join A:src(refs,related_staff;terms,portal_visibility);use(title)}}` | `{{text src:refs,related_staff;terms,portal_visibility\|use:title}}` | `All Users, All Users` — two hops, both taken. Pre-1.17.0 the slot printed nothing (skipped); `department` will NOT do here, since jane and tom carry none and the row would be empty either way |
| F10.2 | `{{try_text A:src(refs,related_staff;terms,portal_visibility);use(title)\|B:key(role)}}` | as F10.1 | `All Users, All Users` — the selecting container, same chain. Slot 2 must NOT run: slot 1 resolved |
| F10.3 | `{{join A:src(refs,related_staff;refs,related_staff);use(title)\|B:key(role)}}` | — | slot 1 resolves and finds nothing (staff carry no `related_staff` of their own), so `Captain`. The MECHANISM changed and the output did not: `slot-fold-test.php` §P13.5 is what says the chain was run rather than refused |
| F10.4 | `{{join A:src(entries,team_members);use(key);key(name)\|B:key(role)}}` | `{{text src:entries,team_members\|use:key\|key:name}}` | `Captain` / empty. `entries` still returns nothing on both — **not implemented yet (FW-74), not unsupported** (§F9.5 carries the full reason). The refusal MOVED out of the flattening and into the container that consumes the kind (`try-slot-arms.php`), which is why it survived the inversion. When the arm lands this row stops being an empty one |
| F10.5 | `{{join A:src(refs,related_staff;terms,department);use(title)\|B:key(role)}}` | — | `Captain` — the row that was the negative control and is now just a row. Expressible before, resolved before, empty because jane and tom carry no department terms |
| F10.6 | `{{join src:site\|srcTermIn:department\|key:org_phone}}` | `{{phone src:site\|srcTermIn:department\|key:org_phone}}` | the site number, on BOTH. A site root never takes the legacy term step, which is what §F9b.5 closed one release earlier — and #104 briefly re-opened from the other side, because the mapping that builds a slot's chain from its flat keys appended the step and the retired flatten used to drop it again. `slot-fold-test.php` §P18.6 is the pin, mutation-verified |
| F10.6b | `{{join srcTermIn:department\|use:title\|2-src:same\|2-srcTermIn:portal_visibility\|2-use:title}}` | — | on `/matrix-terms-valid/`: `Sales, All Users`. **An inherited hop is a DEFAULT, so slot 2's own hop REPLACES it rather than following it** — and this pair is what the old editor authored directly (leave slot 2's source alone, pick a different taxonomy). #104's first draft appended: `terms,department;terms,office` hops off a TERM input, which has no post to read, so slot 2 resolved EMPTY and vanished from the join. Measured both ways; the folded twin `A:src(terms,department);use(title)\|B:src(same;terms,portal_visibility);use(title)` reads `Sales, Support, All Users` and must move with it. **The COUNTS differ on purpose** — flat wire bounds at 1 and chain wire does not (`bws_limit_default`), so slot 1 contributes one term in the legacy spelling and both in the folded one. What must match is that slot 2 RESOLVES in each; a row read as a count comparison fails for the wrong reason |
| F10.6c | `{{join src:ref\|ref:related_staff\|srcTermIn:department\|use:title\|2-src:same\|2-srcTermIn:portal_visibility\|2-use:title}}` | — | `All Users`, unchanged. **The shape §F10.6b's rule exists for**: a rooted BASE plus two different taxonomies, the second slot inheriting. Slot 2 must resolve `refs,related_staff;terms,portal_visibility` — the inherited base kept, the inherited taxonomy replaced. It needs no duplication of the base in the wire, which is the alternative encoding that was considered: the migrator writes `src(same;terms,portal_visibility)` and the rule supplies the base |
| F10.6d | `{{join A:src(refs,related_staff);use(title)\|B:src(same;refs,reports_to);use(title)}}` | `{{text src:refs,related_staff;refs,reports_to\|use:title}}` | `Jane Partner, Tom Associate, Jane Partner` — slot B is the base twin's chain and must equal it (`Jane Partner`). **The row that bounds §F10.6b's rule**: the inherited tail gives way only where this slot's first step cannot RUN off it, and `refs` accepts a post input, so the inherited hop stays and the chain is two relationships deep. `bws_fold_chain_join()`, derived from the engine's own input-kind list — not a slug test, which would drop this. Slot B rendered NOTHING before #104 (two ref steps had no flat spelling) |
| F10.7 | `{{join A:src(site;terms,department);key(org_phone)}}` | `{{phone src:site;terms,department\|key:org_phone}}` | EMPTY on both, and that is the deliberate contrast to F10.6: hand-written chain wire SAYS the term step, so it keeps it and resolves nothing (a term step needs a post input). Wire means what it says (ADR 0004); what F10.6's rule protects is the flat KEYS |

> **A SKIP IS STILL INDISTINGUISHABLE FROM AN EMPTY READ on the front end**, which is why F10.3/F10.4
> print the same thing they printed when they were skips. Rendered output was never the evidence for
> the inversion — `slot-fold-test.php` §P13.5 (mechanism) and the editor preview (author-facing) are.
> The four `[⚠ Join: slot N source not supported]` flags §F14.9 asserted are GONE with the refusal
> that produced them. FIVE reasons remain — four survived and `no repeater field` arrived with the emit
> change — and FOUR of the five SPEAK, each in its own words; `read` (an unconfigured combining slot) is
> silent by design, because it is a resting state.
>
> A first draft of the old section claimed F10.5 as the skip case. It was vacuous — empty for the
> other reason — and the preview harness is what caught it. The same trap is live here: F10.1 needs
> `portal_visibility` and not `department`.

### §F9d — per-step bounds now reach the ENGINE (a stated behaviour change)

A slot's per-step `limit` used to be collapsed into the ONE number the flat triple could hold: every
hop ran unbounded and the finished items were sliced at the end. The bound rides the wire now, so the
engine bounds each hop, exactly as it does for the identically-spelled base tag.

Reachable ONLY where a slot fans TWICE — `refs` + `terms`, the one two-fanning-step shape the flat
triple could express — and `bws_fold_from_flat()` materializes `1` on every earlier fanning step, so
a legacy `limit:N` beside a compound source is the shape that moves.

| # | Tag | Was → is |
|---|---|---|
| F9d.1 | `{{join src:ref\|ref:related_staff\|srcTermIn:portal_visibility\|use:title\|limit:2}}` | terms of ALL related staff, first 2 → terms of the FIRST related staff member, first 2. Identical wherever the first target supplies enough; the two differ only when it does not |
| F9d.2 | `{{join A:src(refs,related_staff,limit[1];terms,portal_visibility,limit[2]);use(title)}}` | the migrated twin of F9d.1, and the wire that says what now happens. It read the same as F9d.1 before, because the flatten kept only the LAST step's number |
| F9d.3 | `{{join src:ref\|ref:related_staff\|use:title\|limit:0}}` | unchanged — ONE fanning step, so per-hop and total coincide. This is the compatibility floor and covers every shape in the surveyed corpus, which contains no explicit `limit` at all |

## §F11 — unknown vocabulary short-circuits, never falls through

| # | Tag | Expected |
|---|---|---|
| F11.1 | `{{phone src:refs,related_staff;bogus,x\|key:main_line}}` | EMPTY — an unknown hop slug compiles to an unknown engine TYPE, the engine answers empty, the chain short-circuits. Dropping the step would read a different source than the wire states |
| F11.2 | `{{phone src:refs,related_staff;site\|key:main_line}}` | EMPTY — a ROOT slug at a HOP position takes the same path |

## §F12 — ref-hop return formats (blueprint v6)

`bws_get_related_posts_data` type-guards `relationship|post_object` and the coercer handles `WP_Post`
as well as ids — but until v6 every fixture field returned an ID, so the object arms were asserted
only against a harness shim's GUESS at ACF's shape. All three fields carry the SAME targets, so
these are equivalences with no expected values of their own.

| # | Tag | Expected |
|---|---|---|
| F12.1 | `{{phone src:refs,related_staff\|key:main_line\|limit:0}}` | `(555) 200-3000, (555) 200-4000` — the `id` reference |
| F12.2 | `{{phone src:refs,related_staff_obj\|key:main_line\|limit:0}}` | identical to F12.1 — `relationship` + `return_format:object` → `WP_Post[]` |
| F12.3 | `{{phone src:refs,lead_staff_obj\|key:main_line\|limit:0}}` | `(555) 200-3000` alone — `post_object` + `object` is SINGULAR (one `WP_Post`, not a list), the only fixture shape that reaches the reader's non-array wrap |
| F12.4 | `{{phone src:ref\|ref:related_staff_obj\|key:main_line\|limit:0}}` | identical to F12.2 — the format is invisible to the flat spelling too |

> Non-vacuity check (the fixture must really deliver the shapes, or these rows assert nothing):
> `wp eval` on `/matrix-post-meta/` returns `related_staff` = `array(2) of integer`,
> `related_staff_obj` = `array(2) of WP_Post`, `lead_staff_obj` = one `WP_Post`. Re-check after any
> schema edit — a sanitizer case whose input is already sanitary asserts nothing, and so does a
> format case whose fixture quietly returns ids.

## §F13 — tag-level axes must survive the fold

A TAG-level axis is spelled exactly like slot 1's, so a mapper that folds by position swallows the
option the resolver actually reads. Both live traps, found by the 5f smoke:

| # | Tag | Expected |
|---|---|---|
| F13.1 | `{{try_datetime_single A:src(refs,related_staff)\|B:src(current)\|key:event_datetime}}` | `May 1, 2030 10:00 AM` — the tag-level `key` on `try_datetime_*` is NOT slot 1's read |
| F13.2 | `{{try_phone A:src(refs,related_staff);key(main_line)\|B:src(current);key(main_line)\|limit:2}}` | `(555) 200-3000` — a tag-level `limit` on a `try_` list template is the TAG limit, not a slot axis. `limit:0` gives the same single value here |
| F13.3 | `{{try_phone src:ref\|ref:related_staff\|key:main_line\|2-key:main_line\|limit:2}}` | legacy twin of F13.2 — same output, which is the property |
| F13.4 | Same as F13.1, then commit ANY slot in the editor | the tag-level `key` MUST still be present in the saved string. It is not in the delete-on-commit list because `bws_fold_slot_flat_axes()` subtracts the container's `tag_level` set — editor row, see §F14.5 |

## §F14 — EDITOR-ONLY rows

Not reachable by `render-tag`. Open a page with fold rows in the block editor (the visible fixture
rows are the fastest way in) and check each.

| # | What to do | Expected |
|---|---|---|
| F14.1 | Open a folded `{{join}}` tag's modal | ONE control per live slot, not ten. "Add field" appends; remove compacts. The BUTTON and the panel HEADER come from one registered noun (`+ Add field` / `Field A`; `try_*` reads `+ Add attempt` / `Attempt A`) — two strings for one unit is how the header said "Slot A" over an "Add attempt" button. Registered keys run to the ceiling; the control renders only up to the live count |
| F14.2 | Add a slot on `{{join}}` (combining) | the new slot seeds with the READ UNSET — choosing a field IS the configuration act in a combining container. The advisory reads "pick a field for this slot" |
| F14.3 | Add a slot on `try_text` (selecting) | the new slot seeds `src(same);use(same)` — the inherit is the useful default for a later attempt |
| F14.4 | Remove a middle slot whose successor inherits | the successor's inherit is MATERIALIZED to a real value before compaction renumbers, so removal never silently re-points a slot. A residual inherit at position 1 is stripped |
| F14.5 | Open a LEGACY (unfolded) join or `try_*` tag, then commit any slot | the legacy `{N}-src`/`-ref`/`-srcTermIn`/`-use`/`-key`/`-limit` keys are deleted and replaced by folded values — EXCEPT the container's tag-level axes (F13.4). Both migration paths must agree: the mount migrator and the converter are twins over one corpus |
| F14.6 | Open a legacy tag, make NO change, close | no spurious diff. The mount migration writes through a function updater, and returning `prev` unchanged is the loop guard |
| F14.7 | Save a folded tag and read the tag string | the slot keys rank as their SLOT'S SOURCE: `format` group first, then any TAG-level (slot 0) source key, then `A`, `B`, … ascending, then `link`/`fallback`. **This is the regression the capitals bought** — while the keys were digits they were JS array-index properties, which ECMAScript enumerates before every string key, and GB serializes with `Object.entries()`, so the slots were PINNED ahead of `format` and neither the JS normalizer nor the PHP sort could move them. A digit-led save here means the spelling regressed |
| F14.7b | Save a `{{join}}` in template mode | the `format` string sits BEFORE the slots, and its tokens are `%A`…`%J`. A stored `%1` still resolves (both alphabets collapse to one internal token) but the control writes letters |
| F14.7c | Open a pre-1.17.0 `{{join}}` whose format holds a literal `%` before A–J (e.g. `10%APR from %1`) | the converter escapes it to `%%APR`, so the text still renders as typed. The escape is gated on wire ERA (no folded key = pre-letters), because literal-or-token is undecidable from the format string — so re-saving an ALREADY-folded tag must NOT escape its `%A` tokens |
| F14.8 | Check the field picker inside a slot | the picker is scoped by the `scopeKey` PROP, not by the outward `state.key`. An unmatched repeater key degrades to the full pool rather than stranding the author |
| F14.9 | Read the editor tag configuration preview text on a folded tag | it matches what the tag renders, because both preview builders walk the SAME seam. A slot the renderer SKIPS is flagged rather than shown as if it resolves — four reasons speak since #104 (`no ref`, `no taxonomy`, `no repeater field`, `no previous source`) and the fifth, `source not supported`, retired with the flatten that produced it (§F10). A slot is named by LETTER since #105, and several slots with different problems collapse to the letters alone. What replaced `source not supported` is a NARROWER and differently-shaped signal — the inert-chain warning, F14.18–22 below, which reaches base tags too. See `docs/editor-tag-previews.md` |
| F14.10 | Hand-edit a slot value to a shape with a per-step `limit` | it round-trips, and the step's own Limit field shows it. Placeholder `0 (all)`; typing `0` or `-1` normalizes back to absence, so the field reads `0 (all)` before and after and nothing is silently lost |
| F14.11 | In any slot, pick the read kind "Meta/Option Field" and pick nothing else | the select STAYS on it and the field picker appears. The control re-parses the value it just wrote to drive that select, so the pending state needs a wire spelling: `use(key)` with no `key(…)`. It is written only while the field is empty — once a field is picked the canonical bare `key(x)` is what saves. Picking an analog row (Title/Name) was never affected, which is what the bug looked like from outside. The empty picker also warns, in the hop warning's words: "This *&lt;noun&gt;* will be skipped unless a field is set". NOT shown on a picker-alone (`keyOnly`) container — there an empty field IS the inherit |
| F14.12 | Add a `terms` hop to a slot and leave the Taxonomy on "Select…" | it warns in the same words as the field warnings — "This *&lt;noun&gt;* will be skipped unless a taxonomy is set" — and the seam keeps that promise: `{{join A:src(terms);key(role)|B:key(name_first)}}` on `/matrix-post-meta/` renders `Jane`, NOT `Captain, Jane`. **`Captain` is the pre-fix answer** (the post's own `role`, read through a hop that silently vanished), so a row that renders it means the incomplete-step skip regressed. The preview says `[⚠ Join: A no taxonomy]` — flagged, unlike an unconfigured read, because the author configured a source and would otherwise hunt for the missing slot |
| F14.13 | Open a legacy BASE tag (`{{text src:ref\|ref:related_staff\|use:title}}`) and commit | the mount migrator rewrites it to `src:refs,related_staff,limit(1)` — the limit on the STEP, no tag-level `limit` written, the flat `ref` gone. The wire must match byte-for-byte what the converter writes for the same tag (`fold-migration-corpus.json` §baseSrc holds the pair): a divergence stores one tag two ways depending on which path reached it first, and neither path is wrong in isolation |
| F14.14 | Open a FLAT-wire tag that stores a `limit` (any §F7c row) | NO "Result Limit" control anywhere in the panel (#62). The open MIGRATES it, exactly as F14.13 says: the number lands on the fanning step and shows in that step's own **Limit** field, or, for an explicit `0`/`-1`, is deleted outright — chain wire already means unlimited and there is no field left to see the key in (§F7c.3). "Result Separator" is unchanged; on a `try_*` it renders BARE (no box), the attempts being that tag's source and drawing their own boxes. **VERIFIED, user 2026-08-07** |
| F14.14b | Open **L4.3** — the seeded chain-wire row that carries a tag-level limit, `{{text src:refs,related_staff\|use:title\|limit:1\|linkTo:permalink}}` on `/matrix-post-meta/` (`limit-default-test-matrix.md` §L4). No migration path WRITES that shape, so the fixture is the only way in without hand-typing a tag | the number is ABSORBED onto the step it bounds — `src:refs,related_staff,limit(1)` — the tag-level key gone, the step's own **Limit** field showing `1` and clearing. Output does not move: `Jane Partner`, still in an `<a>` (the link gate is count-based, so a silent unbounding would drop the anchor too). **VERIFIED on mount, user 2026-08-07.** **A tag-level limit is legacy by POSITION, not by spelling**: before this the chain branch was skipped whole, which left the one shape where a bound is INVISIBLE — the step field read `0 (all)` while the tag rendered one result, and #62 had removed the only control that could reach the key. Three stand-downs, each a NO-OP rather than a rewrite: a non-numeric value (chain wire is not changing era, so there is no default to carry — materializing 1 would bound a tag that renders unlimited today, L4.6), a chain that already states its own step limits (L4.8), and a chain that does not fan |
| F14.15 | Open any chain-authoring tag and read the per-step limit control's LABEL and HELP, on a one-step chain (`src:refs,related_staff`) and then after adding a `terms` step | the label reads **Limit results** on every step (#95) — never "Limit per source", which sat three rows under a `Source` control meaning something else. The `refs` step's help reads *"Maximum number of results. Leave blank for all."*; the `terms` step BELOW it reads *"…for each previous-step result…"*, while the `refs` step keeps the plain form. The condition is whether an earlier step actually FANS, so leaving the `refs` step's field EMPTY puts the `terms` step back on the plain form — the compiler drops an argless step, so nothing upstream fans. **No new fixture**: this is a §F14 editor row, so it rides the visible fold rows the blueprint already seeds (see this section's header) rather than needing a `blocks.php` group of its own. **Eyeball-only above the harness**: `slot-fold-repeater-test.js` asserts the rendered strings, so this row is for the two things it cannot see — that the control is the one on screen, and that the sentence reads right in the panel |
| F14.16 | On any chain-authoring tag, add a `refs` step and pick **Partner Staff** in its field picker (blueprint v9, `partner_staff` — page + staff groups; a bidirectional relationship field with a configured limit of 3, though **nothing in the picker row says so** — that is the point of the note) | a **field configuration note** appears BETWEEN the field picker and **Limit results**, reading *"Bidirectional field with a configured limit of 3. Edits to its bidirectional target field(s) on other posts, terms, or users can add more entries; the limit is enforced only when this field is edited directly, using ACF."* (note case 1). Grey panel with a left rule, no icon, no label. Then switch the picker to **Lead Staff** (`lead_staff_obj`, single-entry, not bidirectional): the note becomes case 6, and its CLOSING sentence is **emphasised**. Then switch to **Related Staff** (`related_staff` — plain relationship, neither setting): the note DISAPPEARS entirely. Then clear the field: no note, and the "will be skipped unless a field is set" warning takes its place. **Requires a reseed** (`bin/seed.sh testbed core-structures`) — `partner_staff` is new in blueprint v9. **Definitions only**: the note reads no value, so it must render identically on a page with no `partner_staff` value stored, and in a WP Pattern with no post in scope at all — which is the case worth checking, since it is the whole reason the note exists. **VERIFIED, user 2026-08-14** |
| F14.17 | With the note showing (F14.16), save the tag and read the tag string; then reopen | the wire is **byte-identical** to what the same tag saves with no note on screen. The note describes and never gates: no key is written, no save is blocked, and Limit results, Add step and the step picker all behave as they do without one. `slot-fold-repeater-test.js` asserts the last part on the rendered tree; this row is for the wire. **VERIFIED, user 2026-08-14** |
| F14.18 | Open a tag whose source is a typo'd root — `{{text src:currnet\|use:key\|key:bio}}` | `[⚠ Unknown source 'currnet']`. **This was invisible before #105**: the namer emits no segment for a root it cannot find, so the tag previewed exactly like a bare `{{text key:name_first}}` while rendering nothing. ADR 0004 makes the wire hand-authorable, so a typo'd source name is what an author actually produces. A registered-but-UNOFFERED root must NOT flag — offering is not resolving (`/matrix-fixture-roots/` §FR rows are the live negatives) |
| F14.19 | Open a tag with an unknown step BEHIND a good one — `{{text src:refs,related_staff;bogus,x\|use:key\|key:bio}}` | `[⚠ Unknown source step 'bogus']`. The row that says the check WALKS the chain rather than reading a kind. THIS tag's kind is `''` (the unknown slug is the tail), but move the unknown slug into the middle — `testroot;bogus,x;refs,y` — and the kind comes back `post` off the TAIL while nothing resolves, so a flag derived from `kind === ''` reads that tag as fine. `preview-label-test.php` pins the mid-chain case, which no fixture can reach without a registered root. `BWS_FOLD_STEP_TYPES` owns what counts as known |
| F14.20 | Open a tag naming a RETIRED source token — `{{text src:related_post\|use:key\|key:bio}}` | `[⚠ Source 'related_post' is no longer supported — run the Tag Converter]`, NOT "unknown". The token IS registered (the registry keeps its dead by policy) and inert by decision (#56), so "unknown" would be a false statement — and this is the one warning here with a repair the author can go and run. Then RUN the converter and reopen: the tag is rewritten to `src:ref` wire and the warning is gone, which is the row's second half |
| F14.21 | Open `{{join A:src(bogus,x);key(bio)\|B:key(bio)}}` | `[⚠ Join: A unknown source 'bogus']` — the slot phrasing, named by LETTER. The inert warning reports **alone**: slot A's key is set here, but even unset it would not add `A no key`, because the slot reads nothing whatever its key says. Detection sits ABOVE the `roots` display switch, which the slot door turns off — a check under it stops flagging on every slot and on nothing else |
| F14.22 | Open `{{try_text A:src(bogus,x);use(key);key(bio)\|B:src(currnet);use(key);key(bio)}}` | `[⚠ Try: A, B misconfigured]` — two slots, two different unknown tokens, so the detail drops and the letters remain. Change B's source to `bogus` as well and the bracket becomes `[⚠ Try: A, B unknown source 'bogus']`: one distinct issue, so the detail comes back. That pair is the collapse rule in one interaction |
|  |  | **`bio` IS ON NO FIXTURE, AND THAT IS FORCED — read this before "improving" the keys.** A base tag with an unresolvable source does NOT render nothing, and the two ways it fails are DIFFERENT reads — measured 2026-08-17 on `/matrix-post-meta/`, whose own title is `Matrix: Post Meta` and whose `related_staff` holds Jane Partner and Tom Associate. An unidentifiable SOURCE token reads the AMBIENT entity (`{{title src:currnet}}` → `Matrix: Post Meta`): [#75](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/75), filed before this work, measurements on it. An unknown STEP slug is silently DROPPED and the chain's PREFIX is read, collapsed to its first result (`{{title src:refs,related_staff;bogus,x}}` → `Jane Partner`, and still one under `limit:0`): [#109](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/109) — the arm has no case for the `''` kind, so it takes the same catch-all branch as a chain with no steps. `{{email}}`/`{{phone}}` are correct on both, being on the other seam (§F11.1). Known steps that resolve nothing are all correct — `refs,no_such_field` and `terms,no_such_tax` both answer empty. Two consequences here: the preview is built ONLY where resolution came back empty, so a row keyed on a field the page HAS would render a plausible wrong value and show no preview at all; and every row above therefore names a field nothing carries. When the leak is fixed the keys can go back and the rows keep working. **VERIFIED, user 2026-08-17** — all five bracket strings read in the editor exactly as stated above, which is the only evidence these rows have. (Also swept with `render-tag`, all five resolving empty, and curled back on `/matrix-post-meta/` after a reseed — but neither of those can see a preview.) |
---

## Fail triage

1. **A §F1/§F2/§F8 pair diverges** → the fold seam or the compiler. Run `slot-fold-test.php` +
   `fold-chain-compile-test.php` first; a green harness with a red matrix row means the CONTAINER
   arm, not the grammar.
2. **A §F9 pair diverges** → arm dispatch regressed. `bws_fold_src_resolution()` is the single
   question every arm asks, so start at `fold-chain-compile-test.php` §C8; a green §C8 with a red
   §F9 row means an arm stopped asking it (grep for a revived `'ref' === $src` /
   `$options['srcTermIn']` test) rather than that the query is wrong.
3. **A §F10 skip row starts rendering** → the seam grew a partial-resolve path. That is the failure
   mode the skip exists to prevent (rendering a different source than the wire states).
4. **§F12 rows all pass but the non-vacuity check fails** → the fixture regressed to ids; the rows
   are asserting nothing.
5. **Editor rows only** → `node tools/test/slot-fold-repeater-test.js` owns cardinality, seeding and
   compaction; `fold-migration-test.php` owns the two migration paths' agreement.
