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
`core-structures` blueprint **v6** — `bin/seed.sh testbed core-structures`). From the wp-litespeed
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
  `contact_email` `jane@example.test`, `event_datetime` `2030-05-01 10:00`. **No department terms.**
- `/staff/tom-associate/` — DENSE person (every `name_*` populated).

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
slot with no folded key maps its legacy axes through `bws_fold_from_legacy()`. Every §F1 row is
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

---

## §F1 — join: folded ≡ legacy

Each row is two tag strings that must produce identical output. Context `/matrix-post-meta/` unless
stated.

| # | Legacy | Folded | Expected |
|---|---|---|---|
| F1.1 | `{{join key:name_first\|2-key:name_last}}` | `{{join A:key(name_first)\|B:key(name_last)}}` | `Jane` here; `Jane, Johnson` on jane; `Tom, Smith` on tom |
| F1.2 | `{{join use:title\|2-use:key\|2-key:role\|valueSep: / }}` | `{{join A:use(title)\|B:use(key);key(role)\|valueSep: / }}` | `Matrix: Post Meta / Captain` |
| F1.3 | `{{join key:main_line\|2-src:same\|2-key:booking_line}}` | `{{join A:key(main_line)\|B:src(same);key(booking_line)}}` | `(987) 654-3210, 987.654.3210` |
| F1.4 | `{{join src:ref\|ref:related_staff\|use:key\|key:main_line\|2-src:same\|2-key:contact_email}}` | `{{join A:src(refs,related_staff);use(key);key(main_line)\|B:src(same);key(contact_email)}}` | `(555) 200-3000, jane@example.test` — slot 2 INHERITS the ref hop |
| F1.5 | `{{join key:name_first\|2-src:ref\|2-ref:related_staff\|2-use:title}}` | `{{join A:key(name_first)\|B:src(refs,related_staff);use(title)}}` | `Jane, Jane Partner` |
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
| F6.3 | `{{join A:src(refs,related_staff);use(key);key(main_line)\|B:key(contact_email)}}` | post-meta | `(555) 200-3000` — slot 2 resets to the page, which has no `contact_email`, so it drops out |
| F6.4 | `{{join A:src(refs,related_staff);use(title)\|B:src(same);use(same)}}` | post-meta | `Jane Partner, Jane Partner` — both axes inherited, i.e. the same datum twice. The control's `inferIntent` advisory DESCRIBES this; it does not block it |
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

## §F8 — depth-0 src chain on base tags (5h)

The compiler translates chain wire into engine steps on every base tag. Pairs again — the legacy
spelling is the reference.

| # | Legacy | Chain | Expected |
|---|---|---|---|
| F8.1 | `{{email src:ref\|ref:related_staff\|key:contact_email}}` | `{{email src:refs,related_staff\|key:contact_email}}` | jane's address as a `mailto:` anchor. **Compare DECODED** — `antispambot` randomizes the entity encoding per render, so the two raw strings differ by design |
| F8.2 | `{{phone src:ref\|ref:related_staff\|key:main_line}}` | `{{phone src:refs,related_staff\|key:main_line}}` | `(555) 200-3000`, tel-linked |
| F8.3 | `{{text src:ref\|ref:related_staff\|use:title}}` | `{{text src:refs,related_staff\|use:title}}` | `Jane Partner` |
| F8.4 | `{{phone src:ref\|ref:related_staff\|key:main_line\|limit:0}}` | `{{phone src:refs,related_staff\|key:main_line\|limit:0}}` | both numbers — `(555) 200-3000, (555) 200-4000` |
| F8.5 | — | `{{phone src:refs,related_staff,limit(1)\|key:main_line\|limit:0}}` | ONE number. **Per-hop cap** — bounds the fan-out's spread |
| F8.6 | — | `{{phone src:refs,related_staff,limit(2)\|key:main_line\|limit:0}}` | both numbers. F8.4/F8.5/F8.6 together are what separate the hop cap from the terminal `limit` |

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

> **MEASURED 2026-08-05** against the branch, every pair matching. Two caveats a reader must carry:
> §F9a.3/§F9a.4 match on the WRONG entity ([#58](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/58), pre-existing on `main`), and §F9a.7/§F9a.8 are vacuous
> until the blueprint seeds attachments. An equivalence row proves the two spellings AGREE; it
> never proves either is right.

| # | Legacy | Chain | Expected |
|---|---|---|---|
| F9.1 | `{{text srcTermIn:department\|use:title\|limit:0}}` | `{{text src:terms,department\|use:title}}` | `Sales, Support`. **The chain spelling needs no `limit`** — flat wire caps at 1, chain wire does not (`bws_limit_default`), which is the whole compatibility mechanism. Pre-1.17.0 the chain rendered `Matrix: Post Meta`, the PAGE title |
| F9.2 | `{{text src:ref\|ref:related_staff\|use:title\|limit:0}}` | `{{text src:refs,related_staff\|use:title}}` | `Jane Partner, Tom Associate`. Pre-1.17.0 the chain rendered `Jane Partner` alone — list mode was gated on `src` being literally `'ref'` |
| F9.3 | — | `{{text src:refs,related_staff;terms,department\|use:title}}` | EMPTY (jane and tom carry no department terms). Pre-1.17.0 it rendered `Jane Partner`: the post-semantic wrapper took the chain's LEADING RUN of `ref` steps and stopped, so a non-leading hop was silently dropped and the tag read the ref'd POST. **Negative control below** |
| F9.3b | — | `{{text src:refs,related_staff;terms,department\|use:title\|fallback:NOHOP}}` | `NOHOP` — the row that makes F9.3 non-vacuous. An empty read and a dropped hop both print nothing, so F9.3 alone cannot tell them apart; this pins that the tag resolved and found nothing |
| F9.4 | `{{text src:site\|key:org_name}}` | — | the site value. **`src:site` still wins over a hand-edited `srcTermIn`** (`{{text src:site\|srcTermIn:department\|key:org_name}}` renders the same): the pair is hand-edit only (`show_if src: not:site`) and every arm has always let the site read win, so the compiler does not fold that hop in |

### §F9a — per-family equivalence

Arm dispatch is one query, but each family reaches it through its own callback, and a
family with no list mode takes a different branch from one that has. One `refs` pair and one
`terms` pair per family; `{{text}}`'s are F9.1/F9.2 above.

| # | Legacy | Chain | Expected |
|---|---|---|---|
| F9a.1 | `{{title src:ref\|ref:related_staff\|limit:0}}` | `{{title src:refs,related_staff}}` | `Jane Partner, Tom Associate` (list-capable) |
| F9a.2 | `{{title srcTermIn:department\|limit:0}}` | `{{title src:terms,department}}` | `Sales, Support` |
| F9a.3 | `{{content src:ref\|ref:related_staff}}` | `{{content src:refs,related_staff}}` | the two must MATCH. ⚠ They match on the AMBIENT page's content, not jane's — `{{content}}` ignores the relationship step entirely, measured identically on `main`, so it is [#58](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/58) and not an arm-dispatch failure. **Do not read this green pair as proof the hop works** |
| F9a.4 | `{{content srcTermIn:department\|use:key\|key:blurb}}` | `{{content src:terms,department\|use:key\|key:blurb}}` | first non-empty term blurb. Same caveat as F9a.3 |
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
> `limit:0`, and that asymmetry IS the equivalence: flat wire caps at 1, chain wire does not.
> A chain row that needs `limit:0` to match means `bws_limit_default` regressed.

**Still divergent, deliberately:**

| # | Tag | Renders | Cause |
|---|---|---|---|
| F9.5 | `{{text src:entries,team_members\|use:key\|key:name}}` | empty | no base-tag arm consumes a `meta_row` source. This is the gap `{{table}}` fills with its own assembly, not something the base text arm should grow — which is also why `entries` is absent from the base chain control's step enum. Authoring it requires a hand edit |
| F9.6 | `{{text src:ref\|ref:related_staff\|srcTermIn:portal_visibility\|use:title\|limit:0}}` | `All Users, All Users` (was `All Users`) | **A flat-wire behaviour change, stated rather than hidden — and MEASURED, both sides.** The term arm used to collapse the relationship step to its FIRST post (`bws_get_srcterm_terms` took one post id) and read that post's terms; it now runs the whole compiled chain, which fans (§V6). Reachable ONLY with an explicit `limit` above one: drop the `limit:0` and both `main` and this branch render `All Users`, which is the compatibility floor holding. The surveyed corpus contains **zero** explicit `limit` values. Accepted because the alternative is keeping a first-only collapse the plural source model already calls a defect, in the one arm still performing it. **`department` will NOT do for this row** — jane and tom carry none, so the pair is empty either way and asserts nothing; `portal_visibility` is the taxonomy the blueprint actually puts on them |

**The contrast this matrix used to draw** — F9.1 and F1.7 as one term hop with two answers, decided
by whether an arm was involved — is what closed. They are now the same hop with the same answer.

## §F10 — a slot the flat seam cannot express SKIPS

The flat triple holds ONE ref hop and ONE term hop, so `refs,x;terms,y` **is** expressible.
Inexpressible means a SECOND hop on the same axis, or an `entries` step. Join's `hops` capability
list offers the term hop only, so all of these are hand-edit-only shapes. The honest answer to a
hand-edit the seam cannot flatten is to render nothing rather than resolve the expressible PREFIX,
which would read a different source than the wire states.

| # | Tag | Expected |
|---|---|---|
| F10.1 | `{{join A:src(refs,related_staff;refs,related_staff);use(title)\|B:key(role)}}` | `Captain` — slot 1 skipped (second ref hop), slot 2 renders |
| F10.2 | `{{join A:src(entries,team_members);use(key);key(name)\|B:key(role)}}` | `Captain` — slot 1 skipped (`entries` is not flattenable) |
| F10.3 | `{{join A:src(refs,related_staff;terms,department);use(title)\|B:key(role)}}` | `Captain` — the NEGATIVE CONTROL. This chain is expressible, resolves, and finds nothing (jane carries no department terms). Identical output, different mechanism |

> **A skip is INDISTINGUISHABLE from an empty read on the front end**, which F10.3 makes concrete:
> all three rows print `Captain`. Render output therefore cannot be the evidence for a skip — the
> pure harness (`slot-fold-test.php` §P13.5) pins the mechanism, and the EDITOR PREVIEW is the
> author-facing signal: an inexpressible chain shows `[⚠ Join: slot N source not supported]`
> (§F14.9), while an expressible chain that happens to resolve empty previews normally. That
> asymmetry is why the flag exists; before 1.17.0 the preview silently omitted the slot and read as
> if the tag had one slot fewer than the author configured.
>
> A first draft of this matrix claimed F10.3 as the skip case. It was a vacuous row — empty for the
> other reason — and the preview harness is what caught it.

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
| F13.2 | `{{try_phone A:src(refs,related_staff);key(main_line)\|B:src(current);key(main_line)\|limit:2}}` | `(555) 200-3000` — a tag-level `limit` on a `try_` list template is the TAG cap, not a slot axis. `limit:0` gives the same single value here |
| F13.3 | `{{try_phone src:ref\|ref:related_staff\|key:main_line\|2-key:main_line\|limit:2}}` | legacy twin of F13.2 — same output, which is the property |
| F13.4 | Same as F13.1, then commit ANY slot in the editor | the tag-level `key` MUST still be present in the saved string. It is not in the delete-on-commit list because `bws_fold_slot_legacy_axes()` subtracts the container's `tag_level` set — editor row, see §F14.5 |

## §F14 — EDITOR-ONLY rows

Not reachable by `render-tag`. Open a page with fold rows in the block editor (the visible fixture
rows are the fastest way in) and check each.

| # | What to do | Expected |
|---|---|---|
| F14.1 | Open a folded `{{join}}` tag's modal | ONE control per live slot, not ten. "Add field" appends; remove compacts. The BUTTON and the panel HEADER come from one registered noun (`+ Add field` / `Field A`; `try_*` reads `+ Add attempt` / `Attempt A`) — two strings for one unit is how the header said "Slot A" over an "Add attempt" button. Registered keys run to the ceiling; the control renders only up to the live count |
| F14.2 | Add a slot on `{{join}}` (combining) | the new slot seeds with the READ UNSET — choosing a field IS the configuration act in a combining container. The advisory reads "pick a field for this slot" |
| F14.3 | Add a slot on `try_text` (selecting) | the new slot seeds `src(same);use(same)` — the inherit is the useful default for a fallback attempt |
| F14.4 | Remove a middle slot whose successor inherits | the successor's inherit is MATERIALIZED to a real value before compaction renumbers, so removal never silently re-points a slot. A residual inherit at position 1 is stripped |
| F14.5 | Open a LEGACY (unfolded) join or `try_*` tag, then commit any slot | the legacy `{N}-src`/`-ref`/`-srcTermIn`/`-use`/`-key`/`-limit` keys are deleted and replaced by folded values — EXCEPT the container's tag-level axes (F13.4). Both migration paths must agree: the mount migrator and the converter are twins over one corpus |
| F14.6 | Open a legacy tag, make NO change, close | no spurious diff. The mount migration writes through a function updater, and returning `prev` unchanged is the loop guard |
| F14.7 | Save a folded tag and read the tag string | the slot keys rank as their SLOT'S SOURCE: `format` group first, then any TAG-level (slot 0) source key, then `A`, `B`, … ascending, then `link`/`fallback`. **This is the regression the capitals bought** — while the keys were digits they were JS array-index properties, which ECMAScript enumerates before every string key, and GB serializes with `Object.entries()`, so the slots were PINNED ahead of `format` and neither the JS normalizer nor the PHP sort could move them. A digit-led save here means the spelling regressed |
| F14.7b | Save a `{{join}}` in template mode | the `format` string sits BEFORE the slots, and its tokens are `%A`…`%J`. A stored `%1` still resolves (both alphabets collapse to one internal token) but the control writes letters |
| F14.7c | Open a pre-1.17.0 `{{join}}` whose format holds a literal `%` before A–J (e.g. `10%APR from %1`) | the converter escapes it to `%%APR`, so the text still renders as typed. The escape is gated on wire ERA (no folded key = pre-letters), because literal-or-token is undecidable from the format string — so re-saving an ALREADY-folded tag must NOT escape its `%A` tokens |
| F14.8 | Check the field picker inside a slot | the picker is scoped by the `scopeKey` PROP, not by the outward `state.key`. An unmatched repeater key degrades to the full pool rather than stranding the author |
| F14.9 | Read the editor tag configuration preview text on a folded tag | it matches what the tag renders, because both preview builders now walk the SAME seam. Shapes the renderer SKIPS (§F10) are flagged rather than shown as if they resolve — see `docs/editor-tag-previews.md` |
| F14.10 | Hand-edit a slot value to a shape with a per-step `limit` | it round-trips. There is no control surface for a per-hop cap yet (deferred to the `{{table}}` authoring pass), so the guarantee is only that editing another slot does not silently drop it |
| F14.11 | In any slot, pick the read kind "Meta/Option Field" and pick nothing else | the select STAYS on it and the field picker appears. The control re-parses the value it just wrote to drive that select, so the pending state needs a wire spelling: `use(key)` with no `key(…)`. It is written only while the field is empty — once a field is picked the canonical bare `key(x)` is what saves. Picking an analog row (Title/Name) was never affected, which is what the bug looked like from outside. The empty picker also warns, in the hop warning's words: "This *&lt;noun&gt;* will be skipped unless a field is set". NOT shown on a picker-alone (`keyOnly`) container — there an empty field IS the inherit |
| F14.12 | Add a `terms` hop to a slot and leave the Taxonomy on "Select…" | it warns in the same words as the field warnings — "This *&lt;noun&gt;* will be skipped unless a taxonomy is set" — and the seam keeps that promise: `{{join A:src(terms);key(role)|B:key(name_first)}}` on `/matrix-post-meta/` renders `Jane`, NOT `Captain, Jane`. **`Captain` is the pre-fix answer** (the post's own `role`, read through a hop that silently vanished), so a row that renders it means the incomplete-step skip regressed. The preview says `[⚠ Join: slot 1 no taxonomy]` — flagged, unlike an unconfigured read, because the author configured a source and would otherwise hunt for the missing slot |

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
