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
first — escaping that trap is why the keys are capitals.)

> Verified 2026-08-04 against the 1.17.0 build (`feat/table-tag`): every §F1–§F8 and §F10–§F13 row
> below is a MEASURED value, not a predicted one. §F9 recorded four DIVERGENCES at that point; the
> arm refactor (FW-63) turned three of them into equivalences, and those rows are now ACCEPTANCE
> CRITERIA rather than a record. The fourth (`rows` on a base tag) stays divergent by decision.
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
| F1.4 | `{{join src:ref\|ref:related_staff\|use:key\|key:main_line\|2-src:same\|2-key:contact_email}}` | `{{join A:src(refs,related_staff,limit[1]);use(key);key(main_line)\|B:src(same);key(contact_email)}}` | `(555) 200-3000, jane@example.test` — slot 2 CARRIES OVER the ref hop. **The folded column is what MIGRATION writes** (#60): a chain-spelled slot returns everything, so the flat era's implied 1 has to be stated or the pair stops being one — see §F7a |
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
| F2.1 | `{{join A:key(main_line)\|2-src:same\|2-key:booking_line}}` | `(987) 654-3210, 987.654.3210` — folded slot 1, legacy slot 2 carrying over from it |
| F2.2 | `{{join key:main_line\|B:src(same);key(booking_line)}}` | same — legacy slot 1, folded slot 2 carrying over from it |

## §F3 — try_: enum + picker read shape

`try_text` / `try_content` / `try_image`. Context `/matrix-post-meta/`.

| # | Tag | Expected |
|---|---|---|
| F3.1 | `{{try_text A:key(missing_field)\|B:key(role)}}` | `Captain` — slot 1 empty, slot 2 wins |
| F3.2 | `{{try_text A:key(role)\|B:key(name_first)}}` | `Captain` — slot 1 resolves, slot 2 never runs |
| F3.3 | `{{try_text A:key(missing_field)\|B:src(site);key(organization_email)}}` | `info@example.test` |
| F3.4 | `{{try_text A:key(missing_field)\|B:src(refs,related_staff);use(title)}}` | `Jane Partner` |
| F3.5 | `{{try_text A:src(refs,related_staff);key(missing_field)\|B:src(same);key(main_line)}}` | `(555) 200-3000` — slot 2 carries over slot 1's hop |
| F3.6 | `{{try_text key:missing_field\|2-use:key\|2-key:role}}` | `Captain` — legacy twin of F3.1 |
| F3.7 | `{{try_content A:key(missing_field)\|B:key(role)}}` | `Captain` |

## §F4 — try_: picker-alone read shape

`try_email` / `try_phone` — no `use` axis exists; an EMPTY picker is the carry-over.

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

## §F6 — carry-forward, carry over, and reset

An absent chain at slot ≥2 is a **RESET to the ambient entity**, not a carry-over — absence in folded
wire means what it says, because legacy absence MATERIALIZES to `src(same)` through the mapper.

| # | Tag | Context | Expected |
|---|---|---|---|
| F6.1 | `{{try_text A:src(refs,related_staff);key(missing)\|B:key(main_line)}}` | post-meta | `(987) 654-3210` — slot 2 RESET to the page, NOT jane |
| F6.2 | `{{try_text A:src(refs,related_staff);key(missing)\|B:src(same);key(main_line)}}` | post-meta | `(555) 200-3000` — explicit `same` carries over jane |
| F6.3 | `{{join A:src(refs,related_staff);use(key);key(main_line)\|B:key(contact_email)}}` | post-meta | `(555) 200-3000, (555) 200-4000` — slot 1 is chain-spelled and unbounded, so it returns BOTH staff numbers (#60; it read one before). Slot 2 still resets to the page, which has no `contact_email`, so it drops out — which is what the row is for |
| F6.4 | `{{join A:src(refs,related_staff);use(title)\|B:src(same);use(same)}}` | post-meta | `Jane Partner, Tom Associate, Jane Partner` — slot 1 unbounded (#60), slot 2 carries over BOTH axes and reads the same datum once, because a slot that fans only by carry-over keeps the flat default of 1. That asymmetry is the row's new content. The control's `inferIntent` advisory DESCRIBES it; it does not block it |
| F6.5 | `{{try_phone A:src(refs,related_staff);key(unused_line)\|B:key(main_line)}}` | post-meta | `(987) 654-3210` — reset, on the picker-alone shape |

## §F7 — slot-level `limit`, and the pairs that CROSS

A legacy `limit` with no fanning step stays a slot-level token; with one, the mapper attaches it to
the LAST fanning step. Both spellings are lossless.

**The legacy↔folded pairing crosses on the source axis, and it is the easiest thing in the fold to
get wrong.** Legacy ABSENCE means carry over (it materializes to `src(same)` through the mapper);
folded absence means RESET to the ambient entity. So:

| folded | legacy twin |
|---|---|
| `N:key(x)` | `N-src:current\|N-key:x` |
| `N:src(same);key(x)` | `N-key:x` |

jane carries no `role`, which makes the two readings differ VISIBLY rather than academically —
reset reads the page (`Captain`), carry over reads jane (nothing). All four rows, `/matrix-post-meta/`:

| # | Tag | Expected |
|---|---|---|
| F7.1 | `{{join A:src(refs,related_staff);use(title);limit(2)\|B:key(role)}}` | `Jane Partner, Tom Associate, Captain` — slot 2 RESETS to the page |
| F7.1b | `{{join src:ref\|ref:related_staff\|use:title\|limit:2\|2-src:current\|2-key:role}}` | same — the legacy twin needs the EXPLICIT `2-src:current` |
| F7.2 | `{{join src:ref\|ref:related_staff\|use:title\|limit:2\|2-key:role}}` | `Jane Partner, Tom Associate` — legacy absence CARRIES OVER jane, who has no `role`, so slot 2 drops. **This is the shape the shipped join UI writes** |
| F7.2b | `{{join A:src(refs,related_staff);use(title);limit(2)\|B:src(same);key(role)}}` | same — `src(same)` is how the fold spells that carry over |
| F7.3 | `{{join A:src(terms,department);use(title);limit(2)\|B:key(role)}}` | `Sales, Support, Captain` |

> Caught by eyeballing the visible fixture rows, not by the harness: F7.1 and F7.2 were first
> written into this matrix as a legacy/folded PAIR, and they are not one — they differ by exactly
> the reset-vs-carry-over rule §F6 states. The pure harness could not have caught it (both spellings
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
| F7a.6 | `{{join A:src(same);key(b)}}` after a fanning slot 1 | see §F6.4 — a slot that fans only by CARRYING OVER keeps the flat default, because the slot it carries over from stated its own bound. A limit does not carry forward, and never did |

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
moved is a slot that fans only by CARRYING OVER (`src(same)`, or an argless `refs`): it has no fanning
step of its own to take the number. It does not move, because `src(same)` means the same SOURCE and a
limit is one of a source's parameters — `bws_fold_slot_chain_options()` carries the bound along with
the source, on a selecting container only. That is unobservable here for a structural reason worth
recording: `srcTermIn` does not carry forward in a selecting container (§P14.5), so an carrying over
slot after a `terms` slot reads the ambient entity rather than the terms; and the `refs` arm is
first-only (the note above). So the evidence is `slot-fold-test.php` §P15, which walks the resolved
quantity slot by slot, plus the pairs above. Both spellings measured identical either way:

| # | Legacy | Migrated | Measured |
|---|---|---|---|
| F7b.6 | `{{try_text src:ref\|ref:related_staff\|use:key\|key:no_such\|2-src:same\|2-use:title\|limit:2}}` | `{{try_text A:src(refs,related_staff,limit[2]);key(no_such)\|B:src(same);use(title)}}` | `Jane Partner` both, on `/matrix-post-meta/` — first-only arm, so the carried bound is invisible until FW-63 |
| F7b.7 | `{{join srcTermIn:department\|use:title\|limit:2\|2-key:blurb}}` | `{{join A:src(terms,department,limit[2]);use(title)\|B:src(same);key(blurb)}}` | `Sales, Support, Sales handles quotes, renewals and the annual customer roadshow.` both — slot B carries over the HOP (#74) but not the BOUND, so it reads one term's blurb rather than two. Before #74 it read the page and contributed nothing, so this row did not exercise the property its label claimed |

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
| F7c.5 | `{{email src:ref\|ref:related_staff\|key:contact_email\|limit:2\|noLink}}` | `jane@example.test, tom@example.test` (plain — the fixture seeds obfuscation OFF; `antispambot` entity-encodes these when the toggle is on) |
| F7c.6 | `{{phone src:ref\|ref:related_staff\|key:main_line\|limit:2\|noLink}}` | `(555) 200-3000, (555) 200-4000` |
| F7c.7 | `{{datetime_single src:ref\|ref:related_staff\|key:event_datetime\|limit:2\|as:date}}` | `May 1, 2030, June 1, 2030` |

`try_`'s half of the same property is §F7b (the value survives the key's retirement). The
registration side — that no panel offers the control, and that `sep` stayed — is a pure assertion,
`php tools/test/control-order-test.php` §5, because the ABSENCE of a control is not visible in
rendered output.

## §F7d — `src(same)` carries over the TERM HOP (#74)

`src(same)` names the same SOURCE, and a taxonomy step is part of what the source IS — unlike
`limit`, which is a parameter *of* a source and stays container-sensitive (§F7b/§F7c). Before #74 a
leading `terms` step left `src` unset, so a slot that carries over carried an empty source and read the
AMBIENT entity: a plausible value from the wrong place, which is why nothing looked broken.

Run on `/matrix-terms-mixed/`. The `Before` column is what shipped through 1.16.x, kept because the
whole point is that it rendered something rather than nothing.

| # | Tag | Before (1.16.x) | Now |
|---|---|---|---|
| F7d.1 | `{{join A:src(terms,department,limit[2]);use(title)\|B:src(same);key(phone)}}` | `Sales, Support` — slot B silently contributed nothing | `Sales, Support, (987) 333-4444` |
| F7d.2 | `{{join A:src(terms,department,limit[2]);use(title)\|B:src(same);use(same)}}` | `Sales, Support, Matrix: Terms (mixed junk)` — the PAGE title, which is what named the bug | `Sales, Support, Sales` |
| F7d.3 | `{{join A:src(terms,department,limit[2]);use(title)\|B:src(current);key(phone)}}` | — | `Sales, Support` — a slot stating its OWN root does not acquire the carried hop, and the page has no `phone` |
| F7d.4 | `{{join A:src(terms,department);use(title)\|B:src(same;terms,office);use(title)}}` | — | `Sales, Support, Warehouse` — a carried hop is a DEFAULT: slot B's own `terms` REPLACES it rather than colliding, so this is a term read of `office`, not a skipped slot |

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
| F8b.4 | `{{text src:terms\|use:title}}` (on `/matrix-terms-mixed/`) | the page title | **EMPTY.** Hand-edit-only, and more so since v1.21.0: no control authors the flat key at all now, and the chain control never commits a step it cannot complete |
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
| F9.5 | — | `{{text src:rows,team_members\|use:key\|key:name\|limit:0}}` | `Alice Adams, Bob Brown`. **A `rows` chain reads its repeater rows (FW-74, 1.21.0); this rendered EMPTY before**, and the empty was pinned here as a decided divergence for two releases. The arm reads resolved SOURCES rather than ids, because a row has no id — `bws_base_sources_of_kind()` is that selector. No legacy spelling exists: `rows` has never had a flat key |
| F9.5b | — | `{{text src:rows,team_members\|use:key\|key:name\|limit:1}}` | `Alice Adams` — the tag's own list seam applies across rows, exactly as it does across posts and terms. `sep` likewise (`sep: / ` over `key:role` renders `Engineering / Operations`, measured through `render-tag`) |
| F9.5c | — | `{{text src:rows,team_members\|use:title\|limit:0}}` | EMPTY, and the emptiness is the assertion: **an ANALOG REFUSES on a row.** A row is not an entity and has no title, permalink, date or author, so the analog arms get no row fn at all. The hop a `use:title` would imply is already spellable with no new vocabulary — `src:rows,team_members;refs,lead_ref\|use:title`. The failure this pins is the other one: a row branch that fell back to some entity's title would print a plausible wrong value |
| F9.5d | — | `{{content src:rows,team_members\|use:key\|key:description}}` | `Founding partner` — the FIRST row's sub-field and no more. **A collapsing family's row read COLLAPSES** (`takes_first_usable`, ADR 0007): `{{content}}` registers no `limit` and no `sep`, so where `{{text}}`'s F9.5 fans across the rows this one takes the first, exactly as its post and term routes do. Rendered EMPTY before 1.21.0 (FW-74 ticket 04) |
| F9.5e | — | `{{content src:rows,team_members}}` | EMPTY — and this row is a WRONG-ENTITY FIX, not a new refusal. Before 1.21.0 it printed **the whole surrounding page's content**: the rows chain resolved no row to a post id, the collapsing selector's empty-fan leg fell back to the ambient post, and `use:content` read it. A row has no content of its own, so the analog refuses (the F9.5c rule, on the family that had been leaking instead of rendering empty). `use:excerpt` is the same assertion and was measured with it |
| F9.5f | — | `{{email src:rows,team_members\|key:email\|noLink:1}}` | displays `alice@example.test, bob@example.test` (plain — the fixture seeds obfuscation OFF; with the toggle on `antispambot` entity-encodes the characters and a browser still shows the addresses). `limit:1` displays alice alone. **THE BASE TAG NEEDED NO ARM AND THIS ROW IS WHY IT IS WRITTEN DOWN.** `{{email}}` reads through `bws_resolve_field_values()`, which dispatches nothing on source kind and hands every resolved source to the L2 seam, whose `meta_row` case has been live since 1.17.0 — so this rendered the same two addresses on the tree BEFORE ticket 04, measured. Nothing pinned it and no doc said it; that is the gap the row closes, not a behaviour change |
| F9.5g | — | `{{phone src:rows,team_members\|key:phone\|noLink:1}}` | `(617) 555-0142, (617) 555-0187`. Same seam and same note as F9.5f — `{{phone}}` read rows before ticket 04 too, unpinned. With the `tel:` wrap on, each renders `href="tel:+1-617-555-0142"` / `…0187` |
| F9.5h | — | `{{try_content A:src(rows,team_members);use(key);key(description)}}` | `Founding partner`. The `try_` twins of F9.5d/f/g, and the half that DID need work: the `try_` machinery dispatches per ARM, so a template with no `try_row_fn` fell through to the post arm, resolved no id off a `rows` chain and rendered empty. `{{try_email A:src(rows,team_members);key(email)}}` and `{{try_phone A:src(rows,team_members);key(phone)}}` render F9.5f/g's values; `limit(1)` slices and a stated `sep` joins |
| F9.5i | — | `{{try_content A:src(rows,team_members);use(content)\|B:use(key);key(main_line)}}` | `(987) 654-3210` — the ambient page's own field, from slot B. Slot A is a row asking for an analog: it REFUSES, renders nothing, and the attempt chain advances. The failure this pins is a refusing arm that returns something anyway, which a selecting `try_` treats as a WIN so the author's later attempts never run ([I15]) |
| F9.5j | — | `{{title src:rows,team_members}}` | EMPTY — **a family with NO row arm REFUSES, and the paired control is what makes that readable.** `{{title}}` on the same page renders `Matrix: Post Meta`, so the emptiness above is a refusal and not a dead fixture. Before 1.21.0 the refusing row rendered `Matrix: Post Meta` too: a `rows` chain resolves no post, the post tail's empty-fan leg read the SURROUNDING PAGE, and an author saw a plausible value from an entity the wire never named. `{{title}}` will never gain a row arm — a row is not an entity and has no title, and the hop that spelling implies is already sayable (`src:rows,team_members;refs,lead_ref\|use:title`) |
| F9.5k | — | `{{permalink src:rows,team_members}}` | EMPTY, F9.5j's assertion on the URL family. Control: `{{permalink}}` renders `https://testbed.test/matrix-post-meta/`, which is what this row printed before 1.21.0. `{{permalink}}` registers no `fallback` and no preview label, so `''` is the whole of its refusal |
| F9.5l | — | `{{image src:rows,team_members\|use:key\|key:photo\|as:url}}` | `…/fixture-photo-alice.png` — the FIRST row's photo sub-field, read as an ACF `array` return format. **The control beside it is the whole point of the row**: `{{image use:key\|key:feature_image\|as:url}}` renders `…/fixture-photo.png`, the page's OWN image, so a row read that printed that one would be the pre-1.21.0 leak wearing the new arm's clothes. Row 1 and row 2 carry different attachments and neither is the page's, so this row states three things at once — the read is the row's, it is the FIRST row's (`takes_first_usable`, ADR 0007: `{{image}}` registers no `limit` and no `sep`), and the array format survived. Until ticket 05 this whole pair was a refusal row (`key:feature_image`, EMPTY), and the refusal replaced a leak |
| F9.5l2 | — | `{{image src:rows,team_members\|use:key\|key:photo_id\|as:url}}` | The same URL as F9.5l, off the same attachment through ACF's `id` return format. **The three format rows are not redundancy**: ACF formats a sub-field on the way out of `get_field()`, so the stored shape the row read has to survive is the FIELD's property, not the tag's — an int here, an array at F9.5l, a URL string at F9.5l3 |
| F9.5l3 | — | `{{image src:rows,team_members\|use:key\|key:photo_url\|as:url}}` | The same URL again, through the `url` return format — the only one of the three the string read seam could ever have carried. F9.5l is the one that needed `bws_read_resolved_source_value()`, the raw sibling ticket 01 split out; these two are what says the raw path did not break the formats that already worked. The `as` option behaves on a row exactly as on a post (measured: `as:id` → the attachment id, `as:alt` / `as:caption` → the attachment's, `as:url,thumbnail` → the sized URL, and `as:title` returns the URL on BOTH routes, a shared quirk of `bws_get_attachment_data()` and not a row defect) |
| F9.5l4 | — | `{{image src:rows,team_members\|use:featured\|as:url}}` | EMPTY — F9.5c's analog rule on the image family. A row has no featured image, so `use:featured` refuses rather than reading the surrounding post's, which is the plausible wrong value this pins against. A stated `fallback` still fires on an empty row read and on a repeater with no rows at all, matching the post route's two fallback occasions (measured through `render-tag`) |
| F9.5l5 | — | `{{try_image A:src(rows,team_members);use(key);key(photo)}}` | F9.5l's URL — the `try_` twin, and the half that needed wiring: the `try_` dispatcher runs per ARM, so the image template had to name a `try_row_fn` or its rows slot fell through to the post arm. Both halves run the SAME function (`bws_try_image_row_dispatch`), which is what keeps the base tag and its twin reading one way |
| F9.5m | — | `{{datetime_single src:rows,team_members\|key:contract_start}}` | `March 4, 2029, June 10, 2030` — a date sub-field per row, FANNING, which is where this family parts from `{{image}}`/`{{content}}`: `{{datetime_single}}` is not `takes_first_usable` and keeps its `sep`, so its row read lists exactly as its post and term reads do. **The control beside it is the whole point of the row**: `{{datetime_single key:event_date_dmy}}` renders `15/08/2030`, the page's OWN date field and this row's pre-1.21.0 output, so a row read that printed that one is the old leak wearing the arm's clothes. REFUSED between ticket 04b and ticket 06 |
| F9.5m2 | — | `{{datetime_single src:rows,team_members,limit(1)\|key:contract_start}}` | `March 4, 2029` alone — the fan is bounded where a chain states its bound, on the STEP. There is no tag-level `limit` on a base datetime tag to state it with (#62) |
| F9.5m3 | — | `{{datetime_single src:rows,team_members\|key:contract_start\|sep: / }}` | `March 4, 2029 / June 10, 2030` — the tag's own `sep` joins the row fan, same seam as the term and post branches |
| F9.5m4 | — | `{{datetime_single src:rows,team_members\|key:review_dmy}}` | `April 3, 2030, November 22, 2030` — **a KNOWN BOUNDARY, seeded so it is readable rather than described.** `review_dmy` has ACF return format `d/m/Y`, and a row reaches no sub-field config: the object id a datetime tag derives addresses the entity, not a row's sub-field, so the parse falls to `bws_parse_acf_date_value()`'s common-format walk, which tries `m/d/Y` first. Row 1 (`04/03/2030`, meaning 4 March) is ambiguous under that walk and reads as the wrong month; row 2 (`22/11/2030`) is not and reads correctly. FW-3's field-object read is what closes it, and **this row MOVES when it does** — that is the signal, not a regression |
| F9.5m5 | — | `{{try_datetime_single A:src(rows,team_members)\|key:contract_start}}` | F9.5m's two dates — the `try_` twin, and the half that needed the wiring: the template had no `try_row_fn`, so a rows slot fell through to the post arm. `key` is TAG-level here and not per-slot, which is datetime's shipped shape (§F13), not a row rule |
| F9.5n | — | `{{datetime_range src:rows,team_members\|startKey:contract_start\|endKey:contract_end}}` | `March 4, 2029–March 3, 2031, June 10, 2030–June 9, 2032` — and it is the row nobody would think to add: `{{datetime_range}}` is a SEPARATE family with its own refusal call site and its own `$serves` list, so getting one wrong goes unpinned by F9.5m. `sep` joins whole ranges, `rangeSep` stays inside each. Control: the same tag without the chain renders `August 1–9, 2030`, this page's own pair |
| F9.5n2 | — | `{{try_datetime_range A:src(rows,team_members)\|startKey:contract_start\|endKey:contract_end}}` | F9.5n's two ranges — the `try_` twin of the second family, for the same reason F9.5m5 exists for the first |

### §F9a — per-family equivalence

Arm dispatch is one query, but each family reaches it through its own callback, and a
family with no list mode takes a different branch from one that has. One `refs` pair and one
`terms` pair per family; `{{text}}`'s are F9.1/F9.2 above.

| # | Legacy | Chain | Expected |
|---|---|---|---|
| F9a.1 | `{{title src:ref\|ref:related_staff\|limit:0}}` | `{{title src:refs,related_staff}}` | `Jane Partner, Tom Associate` (list-capable) |
| F9a.2 | `{{title srcTermIn:department\|limit:0}}` | `{{title src:terms,department}}` | `Sales, Support` |
| F9a.3 | `{{content src:ref\|ref:related_staff}}` | `{{content src:refs,related_staff}}` | the two must MATCH, on JANE's content — her `J1` row reads `Jane, Johnson`. The pair is an EQUIVALENCE only; that the entity is right is [`content-test-matrix.md`](content-test-matrix.md) §CT1/§CT2's property, and was [#58](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/58) |
| F9a.4 | `{{content srcTermIn:department\|use:key\|key:blurb}}` | `{{content src:terms,department\|use:key\|key:blurb}}` | the FIRST usable source's blurb — Sales, which WP returns first (by name) and which is the term carrying one. Not a search past Support: since the reversal (§F15) an empty first source renders empty. Correctness lives in §CT5 |
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
| F9b.4b | `{{try_title A:src(refs,link_staff)\|linkTo:permalink}}` on `/matrix-links/` (row LK.5) | the three names joined and UNLINKED → each name anchored to ITS OWN permalink | **FW-136 ticket 04**, and the second family FW-135 dissolves on (text was the first). A `try_title` attempt resolves through `bws_base_title_resolve_value()` now, so a fanning attempt rides the same `bws_collect_value_list()` fold the base tag does and inherits its per-item wrap. Its base twin is LK.2, which this row must match byte for byte |
| F9b.4c | `{{try_title A:src(refs,link_staff_gap)\|linkTo:key\|linkKey:profile_url}}` on `/matrix-links/` (row LK.6) | the three names joined and UNLINKED → two anchored, the member with no stored URL PLAIN between them | the same flip on the axis the fold owns and the old emit could not express: link identity is per VALUE, so one unresolvable member costs its own link and no sibling's. Base twin LK.4 |
| F9b.5 | `{{try_phone src:site\|srcTermIn:department\|key:org_phone}}` | empty → the site value | **A PRECEDENCE change, not a fanning one.** The old arms tested `srcTermIn` FIRST, so a site-rooted slot carrying a hand-edited `srcTermIn` took the term arm, resolved no post, and skipped the attempt whole. Kind dispatch answers `site` for that wire — because `bws_fold_chain_from_options()` refuses to append a term step to a site root — so the slot now reads the site, which is **exactly what the base tag has always done** (§F9.4: `{{phone src:site\|srcTermIn:department\|key:org_phone}}` renders the site value on both sides of this change). The pair is hand-edit-only (`srcTermIn` is registered `show_if src: not:site`), and closing it is [I6] slot transparency rather than a regression: `try_` was the one container where the site read did NOT win |

**What did NOT move, and each is a row the collapse could plausibly have broken:**

| # | Tag | Renders |
|---|---|---|
| F9b.6 | `{{try_text src:ref\|ref:related_staff\|use:title}}` | `Jane Partner` — the compatibility floor. Flat, unbounded wire still bounds at 1 |
| F9b.7 | `{{try_phone src:site\|key:org_phone}}` | the site number, `tel:`-wrapped — the site arm's SECOND leg (no `try_site_fn` on phone; `try_core_fn( 0, … )` serves it and takes no link identity of its own) |
| F9b.8 | `{{try_title}}` on `/department/sales/` | `Sales` — the ambient-term arm, which is now a BRANCH off the root-only `render_time` kind rather than a `'current' === $last_src` test |
| F9b.9 | `{{try_title linkTo:permalink}}` on `/staff/jane-partner/` | the linked title — per-arm link-wrap entity survived the merge into one emit |
| F9b.10 | `{{try_text srcTermIn:department\|use:title\|linkTo:permalink}}` | the linked term title — the same, on the arm with a different entity type |
| F9b.11 | `{{try_text A:src(rows,team_members);use(key);key(name)\|B:key(role)}}` | ~~`Captain`~~ → `Alice Adams, Bob Brown`. **MOVED WITH FW-74** (1.21.0) and belongs in the stated-changes table above now: the row arm consumes a `meta_row` chain, so attempt 1 RESOLVES and attempt 2 never runs. Kept at its own number because its point survives the change — the attempt chain's advance is decided by whether a slot resolves, and the row is now the case where it does |
| F9b.12 | `{{try_text A:src(refs,related_staff;terms,department);use(title)\|B:key(role)}}` | `Captain` — an inexpressible chain still skips at the SEAM, which #103 did not touch (#104 dissolves it) |
| F9b.13 | `{{try_text use:title}}` / `{{try_title}}` / `{{try_content}}` on `/author/fixture-author/` | **RENDER-TAG ONLY, stated exception** (an author ARCHIVE is the ambient context and has no page content to hang a fixture row on — the same exception text T4 takes for a term archive). EMPTY, all three — the [I6] parity defect is deliberately still open here. The `user` row exists in the arm table and is `branchable`; no template carries a user function yet, so the dispatcher's fn-absent fallthrough sends it to the post arm exactly as the token arms did. **#108 is what flips these three**, with its own replay run whose expected diff is exactly these rows |

## §F9c — the query loop's repeater row, the flat ACF shape (the loop fallthrough)

**`meta_row` names ONE resolved-source kind, and a slot can arrive at it two ways.** The two need
opposite answers, which is the whole content of this section:

- **Off the WIRE** — `src(rows,…)`, so `bws_fold_chain_resolution()` answers `meta_row`
  at parse time, before anything renders. The author asked for repeater rows. **Read them**
  (FW-74, 1.21.0) — from inside a row that means a NESTED repeater, which is legal wire
  (`BWS_TRAVERSAL_STEP_INPUT_KINDS['rows']` accepts `meta_row`) and is what F9c.4 exercises.
- **Off the AMBIENT CONTEXT** — the wire is silent (`src:current`), the chain kind is `render_time`, and
  the factory comes back with a `meta_row` because the tag is standing inside a GB Pro repeater
  loop. The author asked for *here*. **Continue to the post arm**, which resolves no id, at which
  point the loop fallthrough hands the row to the core fn, which reads `$loop_item[$key]`.

Measured inside the loop: `in_loop=true`, `item_post_id=false`, base kind `meta_row`,
`bws_base_post_ids_from_source()` `[]`, `bws_resolve_post_by_source()` `false`. So the fallthrough
is not a defensive branch — it is the only thing that renders these rows at all.

**NOTHING ELSE REACHES THIS.** A `WP_Query` loop's items are `WP_Post` objects, so a post id always
resolves and the branch never runs; `wp bws render-tag --loop-item=<post_id>` takes a post id by
construction. Until #103 the branch had **no rendered coverage on any tag family** — `{{call}}`'s
[R1.4](call-test-matrix.md) names the case but records it as a known limit rather than exercising
it. The rows below are on `/matrix-post-meta/`, inside a GB Pro `post_meta` query loop over the
seeded `team_members` repeater.

| # | Tag | Expected |
|---|---|---|
| F9c.1 | `{{text key:name}}` | `Alice Adams` / `Bob Brown`, one per repeater row — the BASE tag's own path, which is a control rather than the subject |
| F9c.2 | `{{try_text A:key(name)}}` | the SAME two names. This is the `try_` fallthrough |
| F9c.3 | `{{try_text A:key(nope)\|B:key(role)}}` | `Engineering` / `Operations` — the attempt chain still advances inside a row: slot 1 takes the fallthrough and finds nothing, slot 2 takes it and hits |
| F9c.4 | `{{try_text A:src(rows,team_members);use(key);key(name)\|B:key(role)}}` | `Engineering` / `Operations` — **the row where both arrival routes meet and stay apart.** Slot 1 states a repeater source ON THE WIRE while STANDING IN a repeater row: since FW-74 that is a REAL attempt at a NESTED `team_members`, which no row carries, so it finds nothing. Slot 2's silent wire takes the fallthrough and resolves. **The output did not move when the arm landed; the reason did** — it used to pass because slot 1 was refused as a chain kind |
| F9c.5 | `{{try_text A:src(refs,lead_ref);use(title)}}` | `Jane Partner`, then EMPTY — a relationship sub-field still hops out of the row. Row 2 leaves `lead_ref` blank, and GB hides the empty block, so only one row shows |
| F9c.6 | `{{text src:refs,lead_ref\|use:title}}` | `Jane Partner`, then EMPTY — F9c.5's BASE twin, **and the only row in this section that a conflated branch breaks.** Added with FW-74 because nothing here failed for it: a chained base tag inside a row follows the WIRE (`post`), so a branch dispatching on the resolved BASE (`meta_row`) filters the chain's post output to rows, finds none, and this hop disappears |
| F9c.7 | `{{image key:photo\|as:url}}` | the alice photo URL, then the bob one, one per row — the image family's half of the fallthrough, added with its row arm (ticket 05). A CONTROL of the same shape as F9c.1: it had no rendered coverage until the photo sub-fields existed |
| F9c.8 | `{{datetime_single key:contract_start}}` | `March 4, 2029`, then `June 10, 2030`, one per row — the datetime family's half, added with its row arm (ticket 06), and the path both datetime cores' INVARIANT is written about (issue #22: do not hard-bail on a falsy id when the field read can still be served off the loop item). A CONTROL, **measured to be one**: both mutation shapes were run against it and NEITHER moves it (see below) |

> **F9c.4's REASON WAS REWRITTEN WHEN FW-74 LANDED** (1.21.0), in the arm's own commit, exactly as the note that stood here required. Slot 1 makes a real attempt at a nested repeater now and finds none; the output never moved, so nothing went red — which is why this was a note rather than a test. **The GENUINE nested repeater is now §F23**, on its own page with its own fixture state (blueprint v26). That is where "a nested read works" is measured; F9c.4 keeps the job it has always had, which is that the two arrival routes meet on one page and stay apart.

> **THE NOTE THIS REPLACED PROMISED A HANDOVER, AND WHAT SHIPPED IS NARROWER — said here because the doc moved toward the code rather than the other way.** The old wording was that the genuine nested row "takes over what F9c.4 claims when it lands", and read literally that retires this row. It was not retired, and the reason is that F9c.4 carries TWO claims, only one of which was ever §F23's to take: that a nested read WORKS (which F9c.4 could only ever assert by finding nothing, and which §F23 now measures against real nested state), and that the two arrival routes meet on one page and stay apart (which needs a tag standing INSIDE a repeater row, something §F23's page has no loop to provide). Retiring the row would have deleted the second claim to satisfy the wording of the first. The narrowing was decided in ticket 08 rather than when the promise was written, so it is recorded here rather than left for a reader to infer from a row that outlived its own retirement notice.


> **VERIFIED BY MUTATION, and the first attempt was an ARTIFACT.** Two were run and both blank the
> section: deleting the fallthrough gate (F9c.2/3/4 render nothing; F9c.1 survives, which is what
> shows the base tag has its own path), and refusing a `meta_row` BASE in
> `bws_try_slot_base_branch_kind()` instead of branching it to the post arm (every row goes, and
> `try-slot-arms-test.php` §A4.4 fails beside it).
>
> **TWO MORE WERE RUN WITH FW-74, and what they measured is why F9c.6 exists.** Both are the
> wire-vs-base conflation this section's opening states, and NEITHER moves F9c.1–F9c.5:
>
> - the base text arm's row branch reads `$base['kind']` IN PLACE — nothing here moves at all,
>   because the wire's entity branches still run ahead of it and nothing reaches the mutated test.
>   §F9.5/§F9.5b/§F10.4/§F9b.11 go empty instead: **that quartet is the axis pin, not this section**;
> - the row branch is HOISTED ahead of the entity branches and dispatches off `$base` — the shape
>   "dispatch on the resolved source" produces naturally. F9c.1–5 still hold, because a bare tag's
>   `meta_row` base CARRIES the row and the row branch reads the same value the post-arm fallthrough
>   would. **F9c.6 is the only row that fails**, and it fails on value.
>
> **RUN AGAIN FOR THE DATETIME ARM (2026-09-17, ticket 06), both shapes, and the result is the same
> one:** in place, nothing in this section moves and §F9.5m/m2/m3/m4 go empty — the axis pin again,
> not this section; hoisted, nothing in this section moves either, F9c.8 included. **That is what
> makes F9c.8 a control rather than a pin, and it is why the row says so**: a bare tag's `meta_row`
> base carries the row, so the hoisted branch reads the very value the post-arm fallthrough would —
> the same coincidence this note already records for F9c.1–5. F9c.6 stays the only row in the
> section that fails, and it fails on the text family alone. The `try_` rows (§F9.5m5 / §F9.5n2)
> are untouched by either shape, because a `try_` slot dispatches through the arm table on the
> CHAIN's kind and never reaches a base callback's branch at all.
>
> Making the arm-table row `branchable` was run too and moves nothing, for the same coincidence: the
> branch target now renders what the post arm rendered. `branchable: false` is still the correct
> statement and is pinned purely (`try-slot-arms-test.php` §A5.4/§A5.5) — it is no longer pinned
> HERE, and a note claiming otherwise would be a claim nobody measured.
>
> **Both mutations first appeared to change NOTHING, and the reason is worth carrying:** the
> container runs `opcache.revalidate_freq = 120`, so a front-end request inside two minutes of an
> edit executes STALE BYTECODE while the disk bytes are already correct. Recycle the lsphp workers
> between mutation arms (`docker compose exec -T litespeed bash -c 'killall lsphp 2>/dev/null;
> true'`) rather than restarting the container or waiting out the window — see
> [`docs/testbed.md`](../../docs/testbed.md#the-bytecode-cache--quieter-and-it-invalidates-whole-experiments).
> WP-CLI is unaffected (`opcache.enable_cli = Off`), which is why the `render-tag` sweeps in §F9b
> needed no such handling.

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
| F10.4 | `{{join A:src(rows,team_members);use(key);key(name)\|B:key(role)}}` | `{{text src:rows,team_members\|use:key\|key:name}}` | `Alice Adams, Bob Brown, Captain` / `Alice Adams, Bob Brown`. **WAS `Captain` / empty until FW-74** (1.21.0). The join slot needed no arm of its own — it absorbs the read through the text seam, so wiring the base arm wired the slot, which is the [I16] identity this section exists to state. The base twin is §F9.5 |
| F10.5 | `{{join A:src(refs,related_staff;terms,department);use(title)\|B:key(role)}}` | — | `Captain` — the row that was the negative control and is now just a row. Expressible before, resolved before, empty because jane and tom carry no department terms |
| F10.6 | `{{join src:site\|srcTermIn:department\|key:org_phone}}` | `{{phone src:site\|srcTermIn:department\|key:org_phone}}` | the site number, on BOTH. A site root never takes the legacy term step, which is what §F9b.5 closed one release earlier — and #104 briefly re-opened from the other side, because the mapping that builds a slot's chain from its flat keys appended the step and the retired flatten used to drop it again. `slot-fold-test.php` §P18.6 is the pin, mutation-verified |
| F10.6b | `{{join srcTermIn:department\|use:title\|2-src:same\|2-srcTermIn:portal_visibility\|2-use:title}}` | — | on `/matrix-terms-valid/`: `Sales, All Users`. **A carried hop is a DEFAULT, so slot 2's own hop REPLACES it rather than following it** — and this pair is what the old editor authored directly (leave slot 2's source alone, pick a different taxonomy). #104's first draft appended: `terms,department;terms,office` hops off a TERM input, which has no post to read, so slot 2 resolved EMPTY and vanished from the join. Measured both ways; the folded twin `A:src(terms,department);use(title)\|B:src(same;terms,portal_visibility);use(title)` reads `Sales, Support, All Users` and must move with it. **The COUNTS differ on purpose** — flat wire bounds at 1 and chain wire does not (`bws_limit_default`), so slot 1 contributes one term in the legacy spelling and both in the folded one. What must match is that slot 2 RESOLVES in each; a row read as a count comparison fails for the wrong reason |
| F10.6c | `{{join src:ref\|ref:related_staff\|srcTermIn:department\|use:title\|2-src:same\|2-srcTermIn:portal_visibility\|2-use:title}}` | — | `All Users`, unchanged. **The shape §F10.6b's rule exists for**: a rooted BASE plus two different taxonomies, the second slot carrying over. Slot 2 must resolve `refs,related_staff;terms,portal_visibility` — the carried base kept, the carried taxonomy replaced. It needs no duplication of the base in the wire, which is the alternative encoding that was considered: the migrator writes `src(same;terms,portal_visibility)` and the rule supplies the base |
| F10.6d | `{{join A:src(refs,related_staff);use(title)\|B:src(same;refs,reports_to);use(title)}}` | `{{text src:refs,related_staff;refs,reports_to\|use:title}}` | `Jane Partner, Tom Associate, Jane Partner` — slot B is the base twin's chain and must equal it (`Jane Partner`). **The row that bounds §F10.6b's rule**: the carried tail gives way only where this slot's first step cannot RUN off it, and `refs` accepts a post input, so the carried hop stays and the chain is two relationships deep. `bws_fold_chain_join()`, derived from the engine's own input-kind list — not a slug test, which would drop this. Slot B rendered NOTHING before #104 (two ref steps had no flat spelling) |
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

### §F11a — a source that is PRESENT BUT UNUSABLE renders nothing (#75/#76/#109)

All on `/matrix-post-meta/`, whose own title is `Matrix: Post Meta`, whose `role` is `Captain`,
whose `name_first` is `Jane` and whose `related_staff` leads with Jane Partner.

**EVERY ROW NAMES A KEY THE PAGE ACTUALLY CARRIES, AND THAT IS THE WHOLE POINT.** Until 1.17.0 these
rows had to name a key nothing carried, because a leaking tag rendered a plausible value rather than
an empty one and a row keyed on a real field could not tell the two apart. The pre-fix answer is
stated per row: if one of those comes back, the refusal regressed, and a green "empty" on a key
nothing carries would not have noticed.

**THE TWO DOORS REACH `{{email}}`/`{{phone}}` DIFFERENTLY, and only one was already covered.** The
unknown-STEP door was: they read through the engine seam, which short-circuits an unknown step type
(§F11.1/.2). The unregistered-ROOT door was not — the token never reaches the engine, it is refused
by the factory, and these two families take neither of the seven arms. So they get their own rows
below rather than an argument for leaving them out, which is what the first draft of this section
had.

| # | Tag | Expected (pre-fix answer) |
|---|---|---|
| F11a.1 | `{{title src:currnet}}` | EMPTY — an unregistered token is a source the wire NAMES, not an absent one (*was* `Matrix: Post Meta`) |
| F11a.2 | `{{text src:currnet\|use:key\|key:role}}` | EMPTY (*was* `Captain`) |
| F11a.3 | `{{text src:related_post\|use:key\|key:role}}` | EMPTY — a REGISTERED source whose resolve is inert (#56). The standing pre-migration state of every tag the `related_post` entry exists to repair (*was* `Captain`) |
| F11a.4 | `{{text src:fixture_scoped\|use:key\|key:role}}` | EMPTY — a registered source, correctly written, resolving nothing on THIS page. The measured real-wire shape (blueprint v11) (*was* `Captain`) |
| F11a.4b | `{{image src:currnet\|use:key\|key:feature_image}}` | EMPTY — the image arm, on its own seam (*was* the page's `feature_image`) |
| F11a.5 | the same tag on `/matrix-fixture-roots/` | resolves. **The NON-VACUITY half of F11a.4**, which asserts nothing if the source can never resolve anywhere — owned by [`registered-roots-test-matrix.md`](registered-roots-test-matrix.md) §FR2b, where the fixture root's state table lives. Run both or neither |
| F11a.6 | `{{title src:refs,related_staff;bogus,x}}` | EMPTY — the unknown-STEP door, disjoint from the rows above by POSITION (*was* `Jane Partner`, the chain's prefix collapsed to its first result; still one under `limit:0`) |
| F11a.7 | `{{text use:key\|key:role}}` | `Captain` — the CONTROL row. An ABSENT source still means the ambient entity. If this goes empty the refusal over-reached and every bare tag on every site is blank |
| F11a.8 | `{{phone src:currnet\|key:main_line}}` | EMPTY — the unregistered-ROOT door on the engine-seam families, which §F11.1/.2 do not reach (*was* this page's own `main_line`) |
| F11a.9 | `{{email src:currnet\|key:contact_email}}` | EMPTY — as F11a.8. Its own row rather than a sibling of it, because the two families differ in how they wrap a value and a shared row would hide one |
| F11a.10 | `{{phone key:main_line}}` | the page's own number, `tel:`-wrapped — the CONTROL for F11a.8/.9 on this seam |

### §F11c — IN A QUERY LOOP, which is the only place the root-door guard is observable

**§F11a CANNOT catch a regression in the unregistered-root guard, and finding that out cost a
mutation.** Removing the text arm's guard entirely leaves every §F11a row green: off-loop, the
singular cores return before reading anything (`! $post_id && ! $read_may_serve`), so the arm guard and
the core's guard produce the same empty output. The guard earns its place only where the core would
have gone on to read something — a query-loop item the read can be served from
(`bws_loop_item_is_post_or_row()` is that question), or the queried TERM on an archive.

That asymmetry is not a defect in §F11a; those rows pin the unknown-STEP door, which IS observable
off-loop (removing the title arm's guard makes §F11a.6 render `Jane Partner`). It just means the two
doors need different rows, and only one of them was written first.

Loop is over the `staff` post type, so each item's ambient entity is a staff member with values of
its own. Jane's `main_line` is `(555) 200-3000`. `render-tag` reaches these with
`--loop-item=<staff id>`.

| # | Tag (inside the loop) | Expected |
|---|---|---|
| F11c.1 | `{{text use:key\|key:main_line}}` | the ITEM's number — the CONTROL. A bare tag in a loop reads the loop item, and must go on doing so |
| F11c.2 | `{{text src:currnet\|use:key\|key:main_line}}` | EMPTY. **This is the row that fails if the arm guard goes** (*was*, and is again without it, this item's own number) |
| F11c.3 | `{{text src:related_post\|use:key\|key:main_line}}` | EMPTY — the registered-but-inert token, same door, same exposure |
| F11c.4 | `{{title src:currnet}}` | EMPTY. `{{title}}`'s core has a plain falsy-id guard and no loop read, so this row is green either way — kept as the stated NEGATIVE, so a reader does not mistake it for coverage |

### §F11b — the stated fallback fires, and the other two containers do not blank

A refusal produces nothing, and "produced nothing" is what a fallback is FOR. Asserted separately
from §F11a because "renders empty" passes whether or not the fallback fired.

**One row runs the other way, and it is the only row here that is not about §F11a's refusal at
all.** F11b.3b's fallback CANNOT fire — the attachment it names does not exist — and what it
asserts is that nothing is printed, in particular not the fallback's own argument. It reaches the
fallback through an ordinary missing key rather than a refused source, which is why it needs no
seeded id and can be a static string. It sits in this section because this is where what a
fallback PRODUCES is asserted, and beside F11b.3 because the two are the same seam in opposite
directions.

| # | Tag | Expected |
|---|---|---|
| F11b.1 | `{{text src:currnet\|use:key\|key:role\|fallback:No source}}` | `No source` — not empty. Front end only; the editor shows the configuration preview instead (F14.18) |
| F11b.2 | `{{content src:currnet\|use:key\|key:role\|fallback:No source}}` | `No source` — content's fallback lives inside its core, which a refusal must not run, so this row is the one that catches a refusal that returned early |
| F11b.3 | `{{image src:currnet\|use:key\|key:feature_image\|fallback:<id>}}` | the fallback IMAGE renders. Same split as F11b.2 one seam over. **`render-tag` only, and the exception is stated here per the visible-rows rule**: the fallback is a Media Library ID assigned at seed time, so no static string in `blocks.php` can name it. Pass the seeded attachment's id (`wp post list --post_type=attachment`). Its refusal half IS visible, as F11a.4b |
| F11b.3b | `{{image key:feature_image_missing\|fallback:999999\|as:url}}` | **EMPTY**, and above all NOT `999999`. The page carries no `feature_image_missing` and the site has no attachment 999999, so the read is empty and the stated fallback cannot fire either. The raw id appearing as the image src is a co-resident extension re-applying a `fallback` this tag had already consumed and failed on. VISIBLE, unlike F11b.3, precisely because an id chosen never to exist needs no seed. Which options leave us at all is owned by `BWS_GB_TAG_OUTPUT_OPTIONS` ([`gb-output-boundary.php`](../../includes/helpers/gb-output-boundary.php)); its membership is pinned by [`gb-output-boundary-test.php`](gb-output-boundary-test.php) |
| F11b.4 | `{{datetime_single src:currnet\|key:event_datetime\|fallback:No date}}` | `No date`. Its arm's fallback is gated on the chain FANNING, which a root-refused tag does not do — so a refusal joins that gate explicitly |
| F11b.5 | `{{join A:src(bogus,x);key(role)\|B:key(name_first)}}` | `Jane` — the combining container DROPS the field. Not `Captain, Jane`, which is the pre-fix answer and the misattribution the whole fix is about |
| F11b.6 | `{{try_text A:src(currnet);use(key);key(role)\|B:use(key);key(name_first)}}` | `Jane` — the selecting container ADVANCES to attempt B. *Was* `Captain`: attempt A read the ambient entity, SUCCEEDED, and stopped the chain, so B never ran. This is the row where the fix changes output to a different real value rather than to nothing |

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
| F14.3 | Add a slot on `try_text` (selecting) | the new slot seeds `src(same);use(same)` — the carry-over is the useful default for a later attempt |
| F14.4 | Remove a middle slot whose successor carries over | the successor's carry over is MATERIALIZED to a real value before compaction renumbers, so removal never silently re-points a slot. A residual carry over at position 1 is stripped |
| F14.5 | Open a LEGACY (unfolded) join or `try_*` tag, then commit any slot | the legacy `{N}-src`/`-ref`/`-srcTermIn`/`-use`/`-key`/`-limit` keys are deleted and replaced by folded values — EXCEPT the container's tag-level axes (F13.4). Both migration paths must agree: the mount migrator and the converter are twins over one corpus |
| F14.6 | Open a legacy tag, make NO change, close | no spurious diff. The mount migration writes through a function updater, and returning `prev` unchanged is the loop guard |
| F14.7 | Save a folded tag and read the tag string | the slot keys rank as their SLOT'S SOURCE: `format` group first, then any TAG-level (slot 0) source key, then `A`, `B`, … ascending, then `link`/`fallback`. **This is the regression the capitals bought** — while the keys were digits they were JS array-index properties, which ECMAScript enumerates before every string key, and GB serializes with `Object.entries()`, so the slots were PINNED ahead of `format` and neither the JS normalizer nor the PHP sort could move them. A digit-led save here means the spelling regressed |
| F14.7b | Save a `{{join}}` in template mode | the `format` string sits BEFORE the slots, and its tokens are `%A`…`%J`. A stored `%1` still resolves (both alphabets collapse to one internal token) but the control writes letters |
| F14.7c | Open a pre-1.17.0 `{{join}}` whose format holds a literal `%` before A–J (e.g. `10%APR from %1`) | the converter escapes it to `%%APR`, so the text still renders as typed. The escape is gated on wire ERA (no folded key = pre-letters), because literal-or-token is undecidable from the format string — so re-saving an ALREADY-folded tag must NOT escape its `%A` tokens |
| F14.8 | Check the field picker inside a slot | the picker is scoped by the `scopeKey` PROP, not by the outward `state.key`. An unmatched repeater key degrades to the full pool rather than stranding the author |
| F14.9 | Read the editor tag configuration preview text on a folded tag | it matches what the tag renders, because both preview builders walk the SAME seam. A slot the renderer SKIPS is flagged rather than shown as if it resolves — four reasons speak since #104 (`no ref`, `no taxonomy`, `no repeater field`, `no previous source`) and the fifth, `source not supported`, retired with the flatten that produced it (§F10). A slot is named by LETTER since #105, and several slots with different problems collapse to the letters alone. What replaced `source not supported` is a NARROWER and differently-shaped signal — the inert-chain warning, F14.18–22 below, which reaches base tags too. See `docs/editor-tag-previews.md` |
| F14.10 | Hand-edit a slot value to a shape with a per-step `limit` | it round-trips, and the step's own Limit field shows it. Placeholder `0 (all)`; typing `0` or `-1` normalizes back to absence, so the field reads `0 (all)` before and after and nothing is silently lost |
| F14.11 | In any slot, pick the read kind "Meta/Option Field" and pick nothing else | the select STAYS on it and the field picker appears. The control re-parses the value it just wrote to drive that select, so the pending state needs a wire spelling: `use(key)` with no `key(…)`. It is written only while the field is empty — once a field is picked the canonical bare `key(x)` is what saves. Picking an analog row (Title/Name) was never affected, which is what the bug looked like from outside. The empty picker also warns, in the hop warning's words: "This *&lt;noun&gt;* will be skipped unless a field is set". NOT shown on a picker-alone (`keyOnly`) container — there an empty field IS the carry-over |
| F14.12 | Add a `terms` hop to a slot and leave the Taxonomy on "Select…" | it warns in the same words as the field warnings — "This *&lt;noun&gt;* will be skipped unless a taxonomy is set" — and the seam keeps that promise: `{{join A:src(terms);key(role)|B:key(name_first)}}` on `/matrix-post-meta/` renders `Jane`, NOT `Captain, Jane`. **`Captain` is the pre-fix answer** (the post's own `role`, read through a hop that silently vanished), so a row that renders it means the incomplete-step skip regressed. The preview says `[⚠ Join: A no taxonomy]` — flagged, unlike an unconfigured read, because the author configured a source and would otherwise hunt for the missing slot |
| F14.13 | Open a legacy BASE tag (`{{text src:ref\|ref:related_staff\|use:title}}`) and commit | the mount migrator rewrites it to `src:refs,related_staff,limit(1)` — the limit on the STEP, no tag-level `limit` written, the flat `ref` gone. The wire must match byte-for-byte what the converter writes for the same tag (`fold-migration-corpus.json` §baseSrc holds the pair): a divergence stores one tag two ways depending on which path reached it first, and neither path is wrong in isolation |
| F14.14 | Open a FLAT-wire tag that stores a `limit` (any §F7c row) | NO "Result Limit" control anywhere in the panel (#62). The open MIGRATES it, exactly as F14.13 says: the number lands on the fanning step and shows in that step's own **Limit** field, or, for an explicit `0`/`-1`, is deleted outright — chain wire already means unlimited and there is no field left to see the key in (§F7c.3). "Result Separator" is unchanged; on a `try_*` it renders BARE (no box), the attempts being that tag's source and drawing their own boxes. **VERIFIED, user 2026-08-07** |
| F14.14b | Open **L4.3** — the seeded chain-wire row that carries a tag-level limit, `{{text src:refs,related_staff\|use:title\|limit:1\|linkTo:permalink}}` on `/matrix-post-meta/` (`limit-default-test-matrix.md` §L4). No migration path WRITES that shape, so the fixture is the only way in without hand-typing a tag | the number is ABSORBED onto the step it bounds — `src:refs,related_staff,limit(1)` — the tag-level key gone, the step's own **Limit** field showing `1` and clearing. Output does not move: `Jane Partner`, still in an `<a>`, and in exactly ONE (linking is per item since FW-85, so a silent unbounding would print a second anchor as well as a second value). **VERIFIED on mount, user 2026-08-07.** **A tag-level limit is legacy by POSITION, not by spelling**: before this the chain branch was skipped whole, which left the one shape where a bound is INVISIBLE — the step field read `0 (all)` while the tag rendered one result, and #62 had removed the only control that could reach the key. Three stand-downs, each a NO-OP rather than a rewrite: a non-numeric value (chain wire is not changing era, so there is no default to carry — materializing 1 would bound a tag that renders unlimited today, L4.6), a chain that already states its own step limits (L4.8), and a chain that does not fan |
| F14.15 | Open any chain-authoring tag and read the per-step limit control's LABEL and HELP, on a one-step chain (`src:refs,related_staff`) and then after adding a `terms` step | the label NAMES WHAT THE STEP PRODUCES: **Limit Posts Read** on the `refs` step, **Limit Terms Read** on the `terms` step (1.18.0, ADR 0007 — the reframe, then the per-kind pass; #95's "Limit per source" stays dead, and the generic *Limit items read* is a fallback no shipped step reaches). The `refs` step's help reads *"How many items this step reads, in stored order. An item with an empty field keeps its place. Leave blank for all."*; the `terms` step BELOW it reads the *"…for each previous-step item…"* form, while the `refs` step keeps the plain form. The condition is whether an earlier step actually FANS, so leaving the `refs` step's field EMPTY puts the `terms` step back on the plain form — the compiler drops an argless step, so nothing upstream fans. **No new fixture**: this is a §F14 editor row, so it rides the visible fold rows the blueprint already seeds (see this section's header) rather than needing a `blocks.php` group of its own. **Eyeball-only above the harness**: `slot-fold-repeater-test.js` asserts the rendered strings, so this row is for the two things it cannot see — that the control is the one on screen, and that the sentence reads right in the panel. **VERIFIED, user 2026-08-21**, and running it is what found the suppression defect: reading this row on `{{content}}` is how a **Limit Posts Read** box that should not exist at all became visible (`foldConfig()` was dropping `takesFirstUsable`). Read it on a NON-collapsing tag for the labels and help, and on a collapsing one for the absence |
| F14.16 | On any chain-authoring tag, add a `refs` step and pick **Partner Staff** in its field picker (blueprint v9, `partner_staff` — page + staff groups; a bidirectional relationship field with a configured limit of 3, though **nothing in the picker row says so** — that is the point of the note) | a **field configuration note** appears BETWEEN the field picker and **Limit results**, reading *"Bidirectional field with a configured limit of 3. Edits to its bidirectional target field(s) on other posts, terms, or users can add more entries; the limit is enforced only when this field is edited directly, using ACF."* (note case 1). Grey panel with a left rule, no icon, no label. Then switch the picker to **Lead Staff** (`lead_staff_obj`, single-entry, not bidirectional): the note becomes case 6, and its CLOSING sentence is **emphasised**. Then switch to **Related Staff** (`related_staff` — plain relationship, neither setting): the note DISAPPEARS entirely. Then clear the field: no note, and the "will be skipped unless a field is set" warning takes its place. **Requires a reseed** (`bin/seed.sh testbed core-structures`) — `partner_staff` is new in blueprint v9. **Definitions only**: the note reads no value, so it must render identically on a page with no `partner_staff` value stored, and in a WP Pattern with no post in scope at all — which is the case worth checking, since it is the whole reason the note exists. **VERIFIED, user 2026-08-14** — the note's CASES. Its collapsing-tag half (the consequence clause dropping on `{{content}}` and returning on `{{text}}`) was verified separately on **2026-08-21**, and could not have been covered by the 2026-08-14 run: `takesFirstUsable` was not reaching the control at all then, so the clause rendered on every tag |
| F14.17 | With the note showing (F14.16), save the tag and read the tag string; then reopen | the wire is **byte-identical** to what the same tag saves with no note on screen. The note describes and never gates: no key is written, no save is blocked, and Limit results, Add step and the step picker all behave as they do without one. `slot-fold-repeater-test.js` asserts the last part on the rendered tree; this row is for the wire. **VERIFIED, user 2026-08-14** |
| F14.18 | Open a tag whose source is a typo'd root — `{{text src:currnet\|use:key\|key:bio}}` | `[⚠ Unknown source 'currnet']`. **This was invisible before #105**: the namer emits no segment for a root it cannot find, so the tag previewed exactly like a bare `{{text key:name_first}}` while rendering nothing. ADR 0004 makes the wire hand-authorable, so a typo'd source name is what an author actually produces. A registered-but-UNOFFERED root must NOT flag — offering is not resolving (`/matrix-fixture-roots/` §FR rows are the live negatives) |
| F14.19 | Open a tag with an unknown step BEHIND a good one — `{{text src:refs,related_staff;bogus,x\|use:key\|key:bio}}` | `[⚠ Unknown source step 'bogus']`. The row that says the check WALKS the chain rather than reading a kind. THIS tag's kind is `''` (the unknown slug is the tail), but move the unknown slug into the middle — `testroot;bogus,x;refs,y` — and the kind comes back `post` off the TAIL while nothing resolves, so a flag derived from `kind === ''` reads that tag as fine. `preview-label-test.php` pins the mid-chain case, which no fixture can reach without a registered root. `BWS_FOLD_STEP_TYPES` owns what counts as known |
| F14.20 | Open a tag naming a RETIRED source token — `{{text src:related_post\|use:key\|key:bio}}` | `[⚠ Source 'related_post' is no longer supported — run the Tag Converter]`, NOT "unknown". The token IS registered (the registry keeps its dead by policy) and inert by decision (#56), so "unknown" would be a false statement — and this is the one warning here with a repair the author can go and run. Then RUN the converter and reopen: the tag is rewritten to `src:ref` wire and the warning is gone, which is the row's second half |
| F14.21 | Open `{{join A:src(bogus,x);key(bio)\|B:key(bio)}}` | `[⚠ Join: A unknown source 'bogus']` — the slot phrasing, named by LETTER. The inert warning reports **alone**: slot A's key is set here, but even unset it would not add `A no key`, because the slot reads nothing whatever its key says. Detection sits ABOVE the `roots` display switch, which the slot door turns off — a check under it stops flagging on every slot and on nothing else |
| F14.22 | Open `{{try_text A:src(bogus,x);use(key);key(bio)\|B:src(currnet);use(key);key(bio)}}` | `[⚠ Try: A, B misconfigured]` — two slots, two different unknown tokens, so the detail drops and the letters remain. Change B's source to `bogus` as well and the bracket becomes `[⚠ Try: A, B unknown source 'bogus']`: one distinct issue, so the detail comes back. That pair is the collapse rule in one interaction |
|  |  | **`bio` IS ON NO FIXTURE, and it no longer has to be — but do not "improve" these keys without reading why they are here.** These five rows were written while a base tag with an unresolvable source did NOT render nothing, and the preview is built ONLY where resolution came back empty — so a row keyed on a field the page HAS would have rendered a plausible wrong value and shown no preview at all. Every row therefore names a field nothing carries. Since [#112](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/112) closed both leaks the constraint is gone and a real key would work, but the keys are LEFT AS THEY ARE: a `bio`-keyed row is empty for two independent reasons now, so it stays green under a regression in either, and the rows that would catch one are §F11a's, keyed on fields the page carries and stating each pre-fix answer. Rendered output cannot distinguish a skip from an empty read; these rows' evidence is the bracket string, and only the editor shows it. **VERIFIED, user 2026-08-17** — all five bracket strings read in the editor exactly as stated above. (Also swept with `render-tag`, all five resolving empty, and curled back on `/matrix-post-meta/` after a reseed — but neither of those can see a preview.) |
---

## §F15 — collapsing tags read the FIRST usable SOURCE (ADR 0007, the 2026-08-21 reversal)

**Restated AND re-measured 2026-08-21** for the determinism reversal — every row below rendered as
stated on the testbed (the 2026-08-20 measurements it replaced pinned the now-reversed
first-POPULATED build). Fixtures: blueprint v12 (`charter` on Warehouse ALONE; `feature_image` on Tom
alone — Jane, the first `related_staff` target, deliberately has none). `usable` = a source passing
the gate (resolvable × exists × visible) — NEVER "field populated"
([`tag-reference.md` §List mode](../../docs/tag-reference.md#list-mode-limit--sep)); an empty read
of a usable source renders empty rather than moving on, so which entity is read never depends on
which field is asked for.

Term-route rows run on **`/matrix-terms-mixed/`** (terms resolve alphabetically: Sales, Support,
Warehouse — Sales is the first source, and only Warehouse carries `charter`, so the collapsing
read lands on an EMPTY field by design). Post-route rows on `/matrix-post-meta/`.

| # | Context | Tag | Expect | Why |
|---|---|---|---|---|
| F15.1 | terms-mixed | `{{content src:terms,department,limit(1)\|use:key\|key:charter}}` | **EMPTY** | The stamped `limit(1)` is still IGNORED (S24) — but the first source is Sales, whose `charter` is empty, and the tag does NOT search past it. INVERTED from the reversed build, which rendered Warehouse's charter here |
| F15.1b | terms-mixed | `{{content src:terms,department,limit(2)\|use:key\|key:charter}}` | **EMPTY**, same as F15.1 | A typed limit is ignored on the same terms — one rule |
| F15.1c | terms-mixed | `{{content src:terms,department\|use:key\|key:charter}}` | **EMPTY**, same as F15.1 | The unlimited twin — all three rows EQUAL because selection is deterministic, not because a search succeeded |
| F15.2 | post-meta | `{{permalink src:refs,related_staff,limit(1);refs,reports_to}}` | `https://testbed.test/staff/jane-partner/` | UNCHANGED by the reversal, and the distinction is the row's point: Jane (first ref) has no `reports_to`, so her PATH produces no source at all — fan dead-ends vanish at RESOLUTION, which is path-dependent and deterministic. Tom's path yields Jane. The intermediate `limit(1)` is still ignored |
| F15.3 | post-meta | `{{content src:refs,related_staff\|use:key\|key:name_honorific}}` | **EMPTY** | Jane is the first usable source and her honorific is empty; the tag does not skip to Tom. INVERTED from the reversed build (`Dr.`) |
| F15.4 | post-meta | `{{image src:refs,related_staff\|use:key\|key:feature_image\|as:url,full}}` | **EMPTY** | Jane has no `feature_image`, and the tag reads HER, not whichever post has a photo. INVERTED. The removed search survives only as FW-88's dormant opt-in |
| F15.5 | post-meta | `{{content src:refs,related_staff\|use:key\|key:name_first}}` | `Jane` | First source has a value → renders it. Unchanged in output; now also the DETERMINISM PIN with F15.3/F15.4: all three rows read JANE — a title from one post can no longer pair with an image from another |
| F15.6 | post-meta | `{{try_content A:src(refs,related_staff);use(key);key(name_honorific)}}` | **EMPTY** | `try_` inherits base behaviour WITHIN the slot: the slot reads Jane, gets nothing, the ATTEMPT loses — and with no attempt B, nothing renders. INVERTED (`Dr.` on the reversed build). Across-slot first-populated fallback is pinned by the existing §F13 rows |
| F15.7 | terms-mixed | `{{text src:terms,department,limit(1)\|use:key\|key:charter}}` | **EMPTY** | POSITIVE pin now, not a disclosure: `limit(1)` reads the first source (Sales), whose field is empty — the DESIGNED deterministic rule, identical for list-mode and collapsing tags. Slice C is retired |

**Stated exception (ticket 07 — a slot limit binds its own step):** the probative render shape —
an earlier step stating a limit with an unlimited later step whose fan EXCEEDS it — has no
fixture: no seeded relationship target carries more than one term, so old and new selection render
identically on every expressible row. Pinned purely instead (`slot-fold-test.php` §P13.6c, five
rows), per this file's exception convention. The common shape (limit on the LAST step) is already
covered by §F7a/§F7b, re-measured below. **Wire round-trip under suppression (ticket 05)** is pinned
by the grammar's own emit rows (`slot-fold-test.php` §P9 — `limit` tokens survive parse/emit) plus
the control's keep-limit-on-slug-change rule; the suppressed control writes nothing by construction.

**Byte-identity spot-run, re-run 2026-08-21 on the REVERSED build (ticket 04):** the same ten
pre-existing rows — F7a.1/.2/.3/.5/.7 and F7b.7 on `/matrix-terms-valid/`, F7d.1/.2 on
`/matrix-terms-mixed/`, the `{{content}}` blurb walk on `/matrix-content/`, phone R3.2 — all render
their stated values unchanged, as they did on 2026-08-20. Every one reads a POPULATED first source,
which is why the two builds agree on them and disagree on §F15: the only rows the reversal can move
are those whose first usable source has an empty field.

Editor-eyeball (open a collapsing tag on any page): no limit field (**Limit Posts Read** and its per-step siblings) on any
step of `{{content}}` / `{{permalink}}` / `{{image}}` or their `try_` slots; present with the
REFRAMED label and help on `{{text}}` etc.; the group-end advisory appears once when the chain
fans (copy: "…Only the first item is read.") and not otherwise; the field configuration note on a
collapsing tag keeps its multi-value sentence and drops "all entries will be results…".
**VERIFIED, user 2026-08-21 — and it FAILED first.** The suppression and the note's consequence
drop both read a `takesFirstUsable` that `foldConfig()` was dropping (it rebuilds the config from a
named key list), so both rendered the non-collapsing branch on a testbed whose PHP was correct.
Pick a field for the note half that HAS a consequence clause: `partner_staff` is case 1, one
segment, nothing to drop — `lead_staff_obj` is the single-entry case that carries one.

## §F16 — the site branch is taken by RESOLVED KIND (email/phone)

Both spellings of one source take the same branch (`try-slot-arms-test.php` §A6/§A7 is the pure
pin; these are the render proof). `/matrix-post-meta/`; site options: `organization_email`
`info@example.test`, `org_phone` `(987) 555-0000`. Measured 2026-08-20, **re-measured 2026-08-21 on
the post-reversal build, all six rows as stated**. The re-run is not ceremony: the 2026-08-20 stamp
predates the determinism reversal, which moved the try_ slot arms F16.5/F16.6 ride. Compare email
rows DECODED (`antispambot` randomizes entity encoding per render).

| # | Tag | Expect | Why |
|---|---|---|---|
| F16.1 | `{{email src:site\|key:organization_email}}` | mailto `info@example.test` | Flat spelling — the previously-working one, must not move |
| F16.2 | `{{email src:site,limit(2)\|key:organization_email}}` | same as F16.1, decoded | DECORATED root-only site chain — the compare-miss shape that read the AMBIENT entity on the previous build |
| F16.3 | `{{phone src:site\|key:org_phone}}` | tel `+1-987-555-0000` / `(987) 555-0000` | Flat spelling, unchanged |
| F16.4 | `{{phone src:site,limit(2)\|key:org_phone}}` | same as F16.3 | Decorated twin |
| F16.5 | `{{try_email A:src(fixture_scoped);key(contact_email)\|B:src(site);key(organization_email)}}` | B's site email | An attempt whose source cannot resolve (scope-bound root off its page) is SKIPPED — the NEXT attempt renders, and the assertion is WHICH attempt won, not that output is non-empty |
| F16.6 | `{{try_phone A:src(site,limit[2]);key(org_phone)}}` | site phone, as F16.3 | The decorated site slot takes the site arm's read — the [I15] wrong-entity leak this section pins closed |

## §F17 — the source gate: exists + visible (ADR 0007, [I19]; blueprint v14)

All rows on **`/matrix-gate/`**, whose `gate_staff` names three staff singles differing in ONE
property — a DRAFT (`Dana Draft`), a PRIVATE (`Paul Private`) and a PUBLISHED one
(`Grace Published`), in that order. `stale_ref` is PLAIN meta (ACF's own formatter drops a deleted
post before the gate could see it) holding a genuinely deleted id ahead of Grace; `via_draft` names
the draft alone, and the draft's `reports_to` is Grace.

**v14 adds the three shapes where the answer is not "is the status in a list".** `trash_ref` is
plain meta naming a TRASHED staff post ahead of Grace; `feature_image` on the page names the seeded
ATTACHMENT, whose stored status is the internal `inherit`; and the draft is now AUTHORED BY
`fixture-author`, with a second author-role user (`fixture-other-author`) seeded as the viewer who
is equally logged in and still may not read it.

**THE ONLY ROWS IN THE SUITE THAT READ DIFFERENTLY PER VIEWER**, which is the visible level working
rather than a flaky fixture. Two runs, and both are needed — a row that agreed across them would
mean the viewer-relative arm had been deleted:

```
bin/wp.sh testbed bws render-tag '<tag>' --url=https://testbed.test/matrix-gate/                             # anonymous
bin/wp.sh testbed bws render-tag '<tag>' --url=https://testbed.test/matrix-gate/ --user=1                    # administrator
bin/wp.sh testbed bws render-tag '<tag>' --url=https://testbed.test/matrix-gate/ --user=fixture-author       # the draft's OWNER
bin/wp.sh testbed bws render-tag '<tag>' --url=https://testbed.test/matrix-gate/ --user=fixture-other-author # a different author
```

WP-CLI runs with NO current user unless `--user` is passed, so the bare command is the logged-out
arm — the same arm a front-end curl reads, and the opposite of what the editor shows.

The two author arms are what make "viewer-relative" mean anything: both are logged in and neither
can read every draft, so ownership is the only difference between them. The editor role cannot
serve as the negative — `edit_others_posts` reads the draft, so `bwsut-editor` would print the
owner's answer and the row would pass while measuring the opposite fact.

Every row is also a VISIBLE block on `/matrix-gate/` (open it in the editor for the administrator
arm), and `verify.php` renders every arm off one ambient post so a hand run cannot measure one
viewer and call it the property. **Measured 2026-08-21, every arm, all ten rows as stated.**
§F17.8 and §F17.9 were also measured against the PRE-FIX gate, which printed `Trish` to the
administrator and an empty string to the visitor — the rows fail without the fix, which is the only
evidence that a passing row is testing anything.

| # | Viewer | Tag | Expect | Why |
|---|---|---|---|---|
| F17.1 | anonymous | `{{content src:refs,gate_staff\|use:key\|key:name_first}}` | `Grace` | Draft and private targets fail VISIBLE for a visitor and consume no slot, so the third target IS the first usable source |
| F17.2 | administrator | same tag | `Dana` | Viewer-relative by design (plan §S19) — an author previewing their own draft resolves it. Per-viewer divergence is the assertion; the page-cache consequence is stated beside the gate in `tag-reference.md` |
| F17.1b | both | `{{text src:refs,gate_staff\|use:key\|key:name_first\|limit:0\|sep:, }}` | anon `Grace`; admin `Dana, Paul, Grace` | NON-VACUITY. Without it every row above passes just as well with `gate_staff` unseeded, which looks identical from the front end. It also names WHICH sources survived, so a gate that dropped the wrong one is legible |
| F17.3 | both (identical) | `{{text src:refs,stale_ref\|use:key\|key:name_first\|limit:1}}` | `Grace` | EXISTS, not visible: a deleted id fails the gate for everyone and spends no limit budget, so `limit:1` still reaches a real entity. A row that diverged by viewer here would mean existence had been folded into the viewer-relative arm |
| F17.4 | anonymous | `{{content src:refs,via_draft;refs,reports_to\|use:key\|key:name_first}}` | **EMPTY** | STEPPING-STONE CUT: the chain is cut at the unreadable hop even though the hop's own target is published |
| F17.5 | administrator | same tag | `Grace` | The other half of F17.4, and what keeps its empty honest: the destination is reachable, so F17.4 is about the stone and not about a missing `reports_to` |
| F17.6 | `fixture-author` (the draft's OWNER) | `{{content src:refs,gate_staff\|use:key\|key:name_first}}` | `Dana` | The test is WHO IS ASKING. A non-admin with no power over other people's drafts still reads their own, which is what makes previewing work |
| F17.7 | `fixture-other-author` | same tag | `Grace` | The pair is the assertion, not either half: `Grace` alone would also be printed by a gate refusing every logged-in non-admin, and `Dana` alone by one resolving any draft for anyone signed in |
| F17.8 | both (identical) | `{{text src:refs,trash_ref\|use:key\|key:name_first\|limit:1}}` | `Grace` | TRASHED: the one status where `exists` passes and `visible` fails for EVERYONE. WP maps `read_post` on trash toward `edit_post`, so a capability-only gate prints `Trish` to an administrator and nothing to a visitor — while WP's own front end 404s a trashed permalink for both. A deletion state is not a publication state |
| F17.9 | both (identical) | `{{text src:refs,feature_image\|use:title}}` | `Fixture Photo` | ATTACHMENT AS A SOURCE: an attachment stores the INTERNAL `inherit`, which resolves to the parent's status (or `publish` when unattached). Raw-column testing dropped every attachment for visitors only. Invisible to every other row, because plain `{{image}}` reads never enter the pipeline |

## §F18 — the same gate, on a QUERY LOOP item (#122)

All rows on **`/matrix-gate/`**, over the same four staff singles §F17 uses, reached the other way: not through a chain the wire states but through the loop's own item, which `bws_read_field()` used to read straight to a meta call with no gate at all. The loop is scoped by `post_name__in`, not by id, so a reseed cannot repoint it, and it names `post_status` explicitly.

**THE LOGGED-OUT ARM IS VACUOUS, AND THAT IS THE FINDING RATHER THAN A FIXTURE FAULT.** GenerateBlocks clamps a non-`publish` `post_status` back to `publish` for any viewer lacking `read_private_posts` (`class-query-loop.php` and `class-query-utils.php` both carry the clamp), so a visitor's loop is handed one item and the three unreadable ones never arrive. Measured 2026-08-28: with the gate call deleted, the whole section renders byte-identically for an anonymous viewer. **The leak was never reachable through an ordinary GB query loop by a logged-out visitor**, which settles the reachability question issue #122 left open and is the opposite of what the issue's headline implied.

**The arm where it IS reachable is a viewer WITH `read_private_posts`.** There the clamp does not fire, all four items present, and the pre-fix build printed **Trish** off a TRASHED post beside the three readable ones. That is the live defect: not a visitor reading drafts, but a privileged viewer reading trash, which is the one status 1.18.0 promised resolves for nobody at all. Draft and private printing to that viewer is the gate working, not the leak.

**The clamp is GB's, not ours, and is not a second gate we may lean on.** It bounds one query builder's args; a loop fed by a query extension, a `pre_get_posts` filter, or GB Pro's own related-query classes is not bounded by it, and neither is `wp bws render-tag --loop-item=<id>`, which is how the leak was found. The guard's absence was the defect whatever any one caller happens to pass.

**The privileged arm was measured on the FRONT END, not in the editor**, and the distinction is load-bearing here in a way it is not in §F17: the editor is a surface §F18's own divergence row expects to disagree, so measuring the mutation evidence there would prove the wrong thing. The run was the page's own content through `do_blocks()` under `wp_set_current_user( 1 )`, which is a front-end render with a privileged viewer — the arm a logged-in visitor gets, reproducible without a session cookie. The logged-out arm is curl `/matrix-gate/?nocache=`, as §F17's is. The synthetic arm reaches the read on ANY viewer and is how the leak was found:

```
bin/wp.sh testbed bws render-tag '{{text key:name_first}}' --loop-item=<staff id>            # anonymous
bin/wp.sh testbed bws render-tag '{{text key:name_first}}' --loop-item=<staff id> --user=1   # administrator
```

The staff ids come from `wp post list --post_type=staff --post_status=publish,draft,private,trash --fields=ID,post_name,post_status`; never hard-code them, since a reseed is free to move them. **Measured 2026-08-28, both arms, all rows as stated, against the fix and against the gate call deleted.**

| # | Viewer | Tag (inside the loop) | Expect | Why |
|---|---|---|---|---|
| F18.1 | anonymous | `{{text key:name_first}}` | `Grace` | VACUOUS, deliberately kept: GB's clamp leaves one item, so this arm reads the same with the gate deleted. It is here because a row that only ever ran privileged would read as an admin-only concern, and F18.2 is what says why this arm proves nothing |
| F18.1b | administrator | same tag | `Dana`, `Paul`, `Grace` | THE ROW. Four items present; the two the viewer may read resolve, the published one resolves, and the TRASHED one refuses. Pre-fix this printed a fourth line, `Trish` — the mutation evidence, without which a passing row is testing nothing |
| F18.2 | anonymous | `{{loop_index}}` | `1` | NON-VACUITY, and the reachability answer. The index counts what the QUERY presented, before any gate of ours, so a one-line answer names GB's clamp as the reason F18.1 is quiet rather than our gate |
| F18.2b | administrator | same tag | `1`, `2`, `3`, `4` | The other half: four items really do reach the loop for this viewer, so F18.1b's three lines are a refusal and not a short query |
| F18.1c | administrator, **in the EDITOR** | same tag as F18.1b | `Dana`, `Paul`, `Grace` | THE DIVERGENCE ROW, and it is EXPECTED rather than a defect. The gate ends in `current_user_can( 'read_post' )`, so an editor preview shows an administrator values a logged-out visitor's front end does not — the same viewer-relative behaviour 1.18.0 accepted for the chain route (§F17.2), reached through the loop instead. Trash is refused in BOTH surfaces, which is what separates "this viewer may read it" from "this content resolves for nobody". A run finding the editor and the front end disagreeing on draft or private has measured the design, not a bug |
| F18.3 | both | `{{try_text A:key(name_first)}}` | as F18.1 / F18.1b | The `try_` family reaches the same read through the loop fallthrough (a slot stating no source of its own), so it must blank with it. A `try_` arm that kept rendering here would mean the fallthrough had grown its own ungated read |

> **The repeater-row path must NOT move, and §F9c is what says so.** A GB Pro repeater row has no post behind it, so the loop branch it uses never reaches the gate at all — what the gate would do with a `meta_row` source is `bws_source_gate()`'s own PHPDoc, and no row here pins it. A fix that refused falsy ids wholesale would blank every §F9c row, which is the failure to check for first if this section and that one ever go red together.

## §F19 — the `try_` query-context arm (1.19.0 FW-9)

**render-tag rows on `/staff/`** (the archive-context rule, T4/T8's exception) — plus one VISIBLE twin: the C-element's C-X2 row renders the F19.2 composition on every query-context page and the page snapshots pin it. Per-context VALUES are `context-test-matrix.md`'s business; these rows pin the ARM — a root-only slot on a query context takes the `query_context` arm (`branchable: true`), never the post arm with a leaked base.

| # | Tag (on `/staff/`) | Expected |
|---|---|---|
| F19.1 | `{{try_text use:title}}` | `Staff` — the [I6] parity row: same value as `{{text use:title}}` (text matrix T9.1) and bare `{{title}}` |
| F19.2 | `{{try_text A:key(context_custom_heading)\|B:use(title)}}` | `Staff` — the composition the arm exists for: key-first attempt finds no entity and falls through, the canonical title answers. `{{title}}` cannot express it (no key-first path) |
| F19.3 | `{{try_title}}` / `{{try_content}}` | `Staff` / the staff type description (`<p>`-wrapped) — the other two templates carrying a `try_query_fn` |
| F19.4 | `{{try_permalink}}` / `{{try_image}}` / `{{try_datetime_single key:event_date}}` | **empty** — the six families with no `try_query_fn` take the fn-absent fallthrough to the post arm, which resolves no id off a query-context base and cannot serve off a loop item on an archive. Empty, never a leaked entity (pre-1.19.0 these read the main query's first row) |
| F19.5 | `{{try_text use:title\|linkTo:permalink}}` | `Staff` **unwrapped** — the arm row's empty `link` column: a query context has no link identity |

Verified 2026-08-29 via `render-tag`. Arm-table membership, columns and branchability are pinned pure in `try-slot-arms-test.php` (§A1–§A5); the WIRING (which template carries a `try_query_fn`, the fn-absent fallthrough) shares the accepted coverage gap `text-test-matrix.md` T8's note records — these rows are its only pins.

## §F20 — a SPECIFIC TERM root, end to end (FW-39, ticket 02)

**VISIBLE at `/matrix-pinned-roots/`** (blueprint v20 — `matrix_pinned_roots` content builder,
`tools/fixtures/core-structures/blocks.php`). The selection's whole promise is that it resolves the SAME
wherever it is authored, so the page's own field values (its title, "Matrix: Pinned Entity Roots")
are deliberately unlike the selected term's ("Sales") — a row that happened to match ambient content
would pass whether the selection resolved or not. The seeded `department` taxonomy's `sales` term is
resolved by SLUG at fixture build time (`bws_fixture_seeded_term_id()`), never hand-typed, since a
selection is authored by numeric ID (D9) and a fresh install's term is not guaranteed any particular one.

| # | Tag | Expected |
|---|---|---|
| F20.1 | `{{text src:term,<sales-id>\|use:title}}` | `Sales` — the tracer bullet: the selected term, not ambient |
| F20.2 | `{{text use:title}}` | `Matrix: Pinned Entity Roots` — the ambient contrast, same page |
| F20.3 | `{{text src:term\|use:title}}` (bare, no argument — D2/D8/D33; hand-wire only, nothing offers this) | **empty** — an argless declaring root refuses at the factory seam. The editor's own preview of the same tag reads `[⚠ Term: nothing selected]` rather than looking like a healthy bare tag (D8) |
| F20.4 | `{{text src:term,999999\|use:title}}` | **empty** — an argument naming a nonexistent term refuses too; the editor's own preview marks this `term 999999 (missing)` |
| F20.5 | `{{try_text A:src(term,<sales-id>);use(title)}}` | `Sales` — the SAME picker's offering resolves identically inside a `try_` attempt (D11, D18) |
| F20.6 | `{{join mode:template\|A:src(term,<sales-id>);use(title)\|B:src(current);use(title)\|format:%A / %B}}` | `Sales / Matrix: Pinned Entity Roots` — one composed string names the selected term AND the ambient page, proving they are two independent reads |
| F20.7 | `{{text src:term,<sales-id>;refs,dept_lead\|use:title}}` | `Tom Associate` — D3: a RELATIONSHIP STEP running off a specific term root. `dept_lead` (v20, a `relationship` field, max 1) is the one term-meta field in the blueprint answering a POST reference rather than the reverse hop every other term field here carries |

**F20.7's field TYPE is load-bearing, not incidental** — a `post_object` field silently read EMPTY through this exact path (a `refs` step off a term-kind source), and `relationship` does not. The GB fact, the measurement and the rule live at [`docs/gb-constraints.md` §GenerateBlocks_Meta_Handler::get_value() drops a SCALAR once single_only is false](../../docs/gb-constraints.md#generateblocks_meta_handlerget_value-drops-a-scalar-once-single_only-is-false) — not restated here.

**A SECOND, MORE CONSEQUENTIAL DISCOVERY** surfaced building this section: an EXPLICIT, HAND-TYPED
`src:term` (no argument) used to reach `TaxonomyTerm::resolve_id()` — which is loop- and
ambient-context-aware — and therefore correctly resolved a query loop's own term item
(`loop-test-matrix.md` QL1.2, "correct today" through 1.20.0-pre). D8's factory-seam refusal now
intercepts BEFORE `resolve_id()` is ever reached for ANY argless declaring root, so that read is now
EMPTY too. **This was put to the user as an explicit choice** (D2's "bare `term` already does what a
bare base tag does" vs D8's literal "an argless root refuses, full stop") and the answer was to keep
D8's rule as written — QL1.2 and QL1.4b were updated to the new behavior (v20) rather than treated
as a regression to revert. See `loop-test-matrix.md` §QL1's own note and CONTEXT.md I15's fifth
shape.

**Verified live** (`wp eval` + `render-tag`, admin user, 2026-09-07, blueprint v20): every row above
against real seeded content; the entity-lookup REST route's two modes (`bws_entity_lookup_browse_terms()`
returns every `department` term grouped and ID-prefixed per D15, `bws_entity_lookup_resolve_term()`
answers a real term's row and `null` for a nonexistent id, registered and correctly `401`s an
unauthenticated request); `bws_build_preview_label()` on a tag naming an entity reads `['sku' from Term:
Sales]`; and the full `verify.php` + `page-snapshots.php` suite (18 pages, including this one)
against the reseeded testbed, all green.

## §F21 — a SPECIFIC POST root, end to end (FW-39, ticket 03)

**Same page as §F20** (`/matrix-pinned-roots/`) — the tracer bullet's twin for the second entity kind, proving the seam §F20 built is genuinely kind-generic rather than term-shaped, exactly as ticket 03 stated. Reuses EXISTING staff fixture state rather than seeding anything new: `staff-tom-associate` (real id resolved at BUILD TIME via `bws_fixture_seeded_post_id( 'tom-associate', 'staff' )`, D9 — a selection is authored by numeric id) and its own `reports_to` field (→ `staff-jane-partner`, v7) stand in for the "relationship step off the selected entity" row, where §F20.7 needed a purpose-built term-meta field (`dept_lead`) that did not already exist. `post` has no migration half (D13), so there is no converter/`fixture_` corpus equivalent to add here.

| # | Tag | Expected |
|---|---|---|
| F21.1 | `{{text src:post,<tom-id>\|use:title}}` | `Tom Associate` — the tracer bullet: the selected post, not ambient |
| F21.2 | `{{text use:title}}` | `Matrix: Pinned Entity Roots` — the ambient contrast, same page |
| F21.3 | `{{text src:post\|use:title}}` (bare, no argument — D2/D8; hand-wire only, nothing offers this) | **empty** — an argless declaring root refuses at the factory seam, same as §F20.3. The editor's own preview reads `[⚠ Post: nothing selected]` |
| F21.4 | `{{text src:post,999999\|use:title}}` | **empty** — an argument naming a nonexistent post refuses too; the editor's own preview marks this `post 999999 (missing)` |
| F21.5 | `{{try_text A:src(post,<tom-id>);use(title)}}` | `Tom Associate` — the SAME picker's offering resolves identically inside a `try_` attempt |
| F21.6 | `{{join mode:template\|A:src(post,<tom-id>);use(title)\|B:src(current);use(title)\|format:%A / %B}}` | `Tom Associate / Matrix: Pinned Entity Roots` — one composed string names the selected post AND the ambient page |
| F21.7 | `{{text src:post,<tom-id>;refs,reports_to\|use:title}}` | `Jane Partner` — D3: a RELATIONSHIP STEP running off a specific POST root, the mirror direction of §F20.7 (a post hopping to a post, rather than a term hopping to one) |

**Verified live** (`wp eval` + `render-tag`, admin user, 2026-09-07/08, blueprint v20): every row above against real seeded content; `bws_entity_lookup_browse_posts()` returns every readable `staff` post grouped and ID-prefixed per D15, with a draft's and a private post's row correctly suffixed ` (draft)` / ` (private)` (D18) when browsed as an administrator and both ABSENT for an anonymous request (D19, no per-post check — the status set is derived once per post type); `bws_entity_lookup_resolve_post()` answers a real post's row and `null` for a nonexistent id; `bws_build_preview_label()` on a tag naming an entity reads `['title' from Post: Tom Associate]`; and the full `verify.php` + `page-snapshots.php` suite (18 pages, including this one) against the reseeded testbed, all green (one unrelated pre-existing failure — GB's own P3 taint-suppression check — reproduces identically on the pre-ticket-03 commit and is not this ticket's regression).

## §F22 — STEPS run off a specific entity root (FW-39, ticket 04)

**Same page as §F20/§F21** (`/matrix-pinned-roots/`). D3: a declaring root is a REAL chain root, so hops run off it exactly as off any other root — which is the whole reason entity selection belongs on base tags rather than staying a separate tag family. **No engine change was needed for any legal case**: `refs` and `rows` already accept a `term` input, and the refusals below are what `bws_run_step()` already declines — the rule deciding that lives with `BWS_TRAVERSAL_STEP_INPUT_KINDS` in [`traversal-pipeline.php`](../../includes/helpers/traversal-pipeline.php), which is where to read it, not here. The ONE-STEP half is §F20.7 / §F21.7 and is not repeated here; these rows are the two-step chains, the same chain in all three containers, and the refused step with its non-vacuity partner.

The ids below are resolved at BUILD TIME (`bws_fixture_seeded_term_id()` / `bws_fixture_seeded_post_id()`), never hand-typed, for the reason §F20 states.

| # | Tag | Expected |
|---|---|---|
| F22.1 | `{{text src:term,<sales-id>;refs,dept_lead;refs,reports_to\|use:title}}` | `Jane Partner` — TWO steps off the selected term: Sales → its `dept_lead` (Tom) → Tom's `reports_to`. Each hop is admitted on the kind the previous one produced, and the selected term is only where the chain starts |
| F22.2 | `{{text src:term,<sales-id>;refs,dept_lead;terms,portal_visibility\|use:title}}` | `All Users` — the literal D3 shape, term → post → term. `portal_visibility` and NOT `department`: jane and tom carry no department terms (§F9.3), so that taxonomy would render empty for a reason having nothing to do with the root |
| F22.3 | `{{try_text A:src(term,<sales-id>;refs,dept_lead;refs,reports_to);use(title)}}` | `Jane Partner` — identical to F22.1 inside a `try_` attempt |
| F22.4 | `{{join mode:template\|A:src(term,<sales-id>;refs,dept_lead;refs,reports_to);use(title)\|B:src(current);use(title)\|format:%A / %B}}` | `Jane Partner / Matrix: Pinned Entity Roots` — the same chain inside a `{{join}}` field, composed with the ambient title. F22.1/F22.3/F22.4 together are the "same chain on a base tag, in a `{{join}}` field and in a `try_` attempt" criterion |
| F22.5 | `{{text src:term,<sales-id>;terms,department\|use:title}}` (hand-wire only — the editor does not offer this step off a term-kind root) | **empty** — there is no term→term edge. §F20.1 already proves the root itself resolves, so this row is the STEP being refused, not the root failing |
| F22.5b | `{{text src:post,<tom-id>;terms,portal_visibility\|use:title}}` | `All Users` — F22.5's non-vacuity partner: the SAME step type off a specific POST root runs. Without it F22.5 reads identically to "the `terms` step is broken" |
| F22.6 | `{{table src:post,<matrix-post-meta-id>\|key:team_members\|1-label:Name\|1-key:name\|2-label:Role\|2-key:role}}` | a 2-row table (`Alice Adams`/`Engineering`, `Bob Brown`/`Operations`) — a `rows` step off a specific POST root, reading a repeater that lives on a DIFFERENT page. Through `{{table}}` because that is the container that ASSEMBLES a row across columns; the base/join/try_ arms read ONE key per row (§F9.5/§F10.4, live since FW-74) and would concatenate rather than tabulate |

**The editor half is pinned PURE, not here.** That the offer off a root argument is computed with no render having occurred is exactly what makes it unobservable on a rendered page: `slot-fold-repeater-test.js` drives the control off the shipped config alone (no query, no resolver) and asserts `refs` offered off a specific term root, `rows` offered off it too (the kind admits both), `terms` NOT offered off it, `terms` offered off a specific POST root, and `terms` offered one `refs` hop later — the F22.1/F22.2 chain, offered a step at a time. **The `rows` step joining every offer in 1.21.0 is editor-only for the same reason** — the wire it authors is the wire a hand edit already wrote, so no row here moves, and `page-snapshots.php` measured that rather than the shape of the diff implying it. `fold-chain-compile-test.php` holds the other side: the refused step still COMPILES, so the refusal stays the engine's answer at run rather than a grammar error at parse.

**Verified live** (`render-tag`, admin user, 2026-09-08, blueprint v20): every row above against real seeded content, each string measured rather than predicted.

**The `term_*` MIGRATION's rows are not here.** Converting an argless `{{term_*}}` tag to a base tag emits chain wire every section above already covers; what the migration has to show is something else entirely — that the old tag and the tag it becomes differ in ONE DIRECTION, across every page context. That is a context-indexed measurement, so it lives with the context rows: [`context-test-matrix.md`](context-test-matrix.md) §C-CONV.

## §F23 — a REAL nested repeater, row inside a row (FW-74, ticket 08)

**Its own page** (`/matrix-repeaters/`, blueprint v26) carrying `duty_roster`: two member rows, each holding a `shifts` repeater of two. The engine has admitted `meta_row` as a `rows` input since 1.17.0 and the base arm has read rows since 1.21.0, but no blueprint post carried a repeater inside a repeater, so the nested case was only ever reachable as a MISS — §F9c.4 states a nested source and passes by finding nothing, which reads identically whether the nested read works or is broken. These rows are the ones that say it works.

**The seeded days INTERLEAVE across the two parents** (Mon/Thu under Priya, Tue/Fri under Luis), so the fan-out order is readable off the string: a read that reached only the first parent, or that grouped the parents wrongly, prints a different sequence rather than the same one. The page carries no other fixture state, which is why F23.0 is there — on a page with one repeater, "the nested read returned nothing" and "this page renders no tags at all" look the same.

| # | Tag | Expected |
|---|---|---|
| F23.0 | `{{text use:title}}` | `Matrix: Nested Repeaters` — the non-vacuity control, and the only row here that does not touch the repeater |
| F23.1 | `{{text src:rows,duty_roster;rows,shifts\|use:key\|key:shift_day\|limit:0}}` | `Monday, Thursday, Tuesday, Friday` — **THE nested read**: two `rows` steps, the second running off a row rather than a post, in document order across BOTH parents |
| F23.2 | `{{text src:rows,duty_roster;rows,shifts\|use:key\|key:shift_hours\|limit:0}}` | `8am to 4pm, 10am to 6pm, 7am to 3pm, 12pm to 8pm` — the same chain on the other inner sub-field. A read returning the ROW rather than the named cell cannot pass on one key alone |
| F23.3 | `{{text src:rows,duty_roster;rows,shifts\|use:key\|key:shift_day\|limit:3}}` | `Monday, Thursday, Tuesday` — the tag's list seam slices ACROSS the parent boundary: both of the first parent's rows and one of the second's. The inner rows are ONE flat list in document order, not a list per parent |
| F23.4 | `{{text src:rows,duty_roster\|use:key\|key:member\|limit:0}}` | `Priya Raman, Luis Ortega` — the ONE-step control. Without it, an empty F23.1 cannot separate "the second step" from "the repeater" |
| F23.5 | `{{text src:rows,duty_roster;rows,nope\|use:key\|key:shift_day\|limit:0}}` | **empty** — a nested repeater the rows do not carry, i.e. the shape §F9c.4 has been passing on, here beside a nested read that works |
| F23.6 | `{{try_text A:src(rows,duty_roster;rows,shifts);use(key);key(shift_day);limit(0)}}` | the SAME four days — the `try_` slot spelling, which is the container §F9c.4 states its claim in |

**`limit` is written on every fanning row and that is not decoration.** Chain wire selects an unset default of 0 (§L4), so these rows would fan with no `limit` at all; spelling it keeps each row's slice its own statement rather than a consequence of which spelling the `src` happened to take.

**PROVENANCE IS NOT HERE, because no rendered row can show it.** The four keys a row carries (`parent_kind`, `parent_id`, `repeater`, `index`) are consumed by nothing until FW-3, so the assertion that an inner row's parent is the OUTER ROW rather than the post lives in `verify.php`, reading the real store through `bws_run_step()`. `traversal-pipeline-test.php` pins the same shape against a synthetic reader; what only the testbed reaches is the custom-fields plugin's own nested read, which decides whether the inner repeater arrives as an array of rows at all.

**Verified live** (`render-tag`, 2026-09-17, blueprint v26): every row above measured against real seeded content. F23.3's original spelling was an unset `limit` expecting one value; the measurement said four, §L4 says why, and the row was rewritten to state a slice it actually makes.

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
