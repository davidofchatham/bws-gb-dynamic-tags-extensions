# SPEC — `{{phone}}` base tag (1.10.0)

Source plan: `.claude/plans/phone-tag.md` (grilled, decisions locked 2026-06-08). Clone source: `includes/tags/email-tags.php`. Sibling of `email`; VE1-VE4 → VP1-VP4 + phone-specific VP-hyphen / VP-href-safe / VP-vis.

## §G — Goal

Add a `phone` base tag that outputs a stored phone number, by default wrapped in a `tel:` link. Cross-source like `email` (site / current / ref / term-hop). Build a clean `tel:` href from messy stored input by preserving the author's separators (C); resolve a country code from in-field intl prefix or a global setting (2-tier). Display text is the stored value verbatim — only the href is normalized.

---

## §C — Constraints

- **Country code is 2-tier this release.** Resolution order: in-field `+`/`00` international prefix → global `phone.country_code` setting. **Per-tag `cc:` is CUT** (deferred). Reason: a per-tag CC would flow through the global strip-leading-CC flag's prefix-match, where that flag's safety reasoning (NANP forbids a leading `1` in the significant number, so stripping CC `1` is safe) does NOT hold for an arbitrary per-tag country. Keeping CC global-only confines the strip's safety domain to the one configured country. Escape hatch for multi-country sites: store `+CC` in the field (tier 1).
- **Global `phone.country_code` default is EMPTY.** Locale ≠ telephone country; a seeded CC silently builds wrong hrefs. Empty + bare digits → national `tel:` (no `+`), which still dials for same-country callers. Set the CC for international-format hrefs.
- **Global `phone.strip_leading_cc` default is OFF (opt-in).** Matches the empty-CC philosophy: no silent transformation unless the admin opts in. Only fires when a CC is resolved (not intl), the national string begins with exactly the global-CC digits, and ≥7 digits remain. Matches the GLOBAL CC only.
- **`tel:` separators follow the author (C), never a locale guess.** Author wrote separators → reuse the digit-group boundaries as hyphens; author wrote bare digits → no internal hyphens. The `+CC-` boundary hyphen is always safe (CC is a known unit). No libphonenumber dependency.
- **Display = stored value verbatim; only the href is normalized.** The two can differ (display `(987) 654-3210`, href `tel:+1-987-654-3210`). Display formatting is deferred.
- **`tel:` is reserved by no GB mechanism** — phone uses the same presence-flag (`noLink`) + `visibility` precedents the email tag established. No GB `with_link()` involvement (bespoke `<a href="tel:…">`).
- **`noLink` is the inverted bare-key boolean** (clone email VE1): absence = wrap (default-on), present = plain text. GB drops `false`, so "default-on, serialize-when-off" is reachable only via an inverted-name bare key.
- **No obfuscation.** `antispambot()` is mailto-specific; phone numbers are not harvested the same way. No global obfuscate analog (unlike email).
- **No `use` enum.** Phone has one datum kind (the number); `key` is always required. A future `use:author_phone` is additive (C10 qualifying test).
- **Strict validation this release.** A number that does not normalize (assembled digits outside 7-15) is SKIPPED, never rendered as plain text. Lenient passthrough is deferred.
- **`fallback` is a fallback PHONE NUMBER** (not arbitrary text), like `email` fallback = address. Run through the same normalize; fires only on a fully-empty valid set, then `''`.
- **Junk-sever before group-split.** Inline extension junk (`x99`/`ext`/`#`/`,`/`;` after ≥7 digits) is severed and ignored this release (the raw stored value is preserved for the future extension feature). Group capture (`preg_split('/\D+/', PREG_SPLIT_NO_EMPTY)`) runs on the severed-clean national part. Parens break a group.
- **Registers UNCONDITIONALLY** (first-class base tag, no `is_modifier_enabled` gate). New file `includes/tags/phone-tags.php`, wired after `bws_register_email_tag()`.
- **Target version 1.10.0** (from 1.9.2 — minor bump; new tag family + two new global settings).

---

## §I — Surfaces

- `includes/tags/phone-tags.php` — NEW. `bws_register_phone_tag()`, `bws_phone_resolve_numbers()`, `bws_phone_normalize_tel()`, `bws_phone_render_one()`, `bws_phone_callback()`, guarded settings wrappers `bws_phone_default_cc()` + `bws_phone_strip_leading_cc()`.
  - `bws_phone_normalize_tel( string $raw, string $cc, bool $stripCc ): string` — returns the dial VALUE (no `tel:` scheme prefix); non-empty = valid, `''` = invalid. Trunk-strip + length-gate are named private sub-helpers (for #3 per-country swap).
  - `bws_phone_render_one( string $raw, string $cc, bool $link, bool $stripCc ): string` — display = `esc_html($raw)`; validity = `'' !== normalize`; if `$link` AND valid → `<a href="tel:VALUE">DISPLAY</a>` with `esc_attr` href.
  - `bws_phone_callback( $options, $block, $instance ): string` — resolves CC + strip-flag ONCE; per-number normalize-as-gate; skip invalid; fallback; list-mode join `sep`.
- `bws-gb-dynamic-tags-extensions.php` — `require_once` after `:128`; `bws_register_phone_tag()` call after `:136`.
- `includes/classes/admin/class-settings-page.php` — `phone` group: schema docblock (~13), default (~125), sanitize (~145), accessors `get_phone_country_code(): string` + `is_phone_strip_leading_cc_enabled(): bool` (~236), "Phone" settings section after "Email" (~622).
- `includes/helpers/preview-helpers.php` — `phone` warning branch (~551), field-part case (~687). Label-only (no sample href, no global-CC nag).
- `docs/tag-reference.md` — registry row (add `phone` implemented), source matrices, options table, `tel:` normalization (C) + 2-tier CC + strip-flag + national-fallback note.
- `docs/gb-constraints.md` — no new GB constraint (reuses presence-flag + visibility precedents).
- `CHANGELOG.md` — `[1.10.0] ### Added`. `README.md` — `{{phone}}` overview line.

---

## §V — Invariants

**VP1** `tel:` wrap is DEFAULT-ON; link is on iff the `noLink` bare key is ABSENT from options (`$link = empty($options['noLink'])`). Never modeled as a positive `link:true` default. (= email VE1)

**VP2** Display text is the stored value verbatim, escaped with `esc_html()`. ONLY the href is normalized; display and href can differ. Display formatting is a future additive option, never a default this release.

**VP-hyphen** Href hyphens follow the AUTHOR'S separators, never a locale guess. Author wrote separators → reuse the captured digit-group boundaries as hyphens. Author wrote bare digits (`count(groups) <= 1`) → no internal hyphens (pure E.164 / national). The `+CC-` boundary hyphen is always added (CC is a known unit). No libphonenumber dependency.

**VP3** Country code resolves first-match: in-field intl prefix (`+`/`00`) → global `phone.country_code`. `+`/`00` are the ONLY international signals; no heuristic stripping of bare leading CC-digits except via the opt-in strip flag (VP-strip). No CC + not-international → national `tel:` (digits, no `+`), never a `+`-less-but-CC-assumed href. Per-tag `cc:` is deferred (strip-flag safety). Trunk-0: strip a single leading `0` when a CC is applied (intl or global CC); national-fallback (empty CC) KEEPS the `0`.

**VP-strip** `phone.strip_leading_cc` (global bool, default OFF) strips a leading country-code run ONLY when: the flag is ON, a CC is resolved (number not already intl), the flat national string begins with exactly the GLOBAL-CC digits, AND ≥7 digits remain after stripping. Strips once, against the GLOBAL CC only (never a per-tag CC). No-ops safely when the global CC is empty. Guards the US `1-800-555-1212` + global-CC-`1` double-prefix.

**VP4** Validate via a loose length gate: a number is valid iff its final assembled digit count (CC + national, post-strip) is `7 ≤ n ≤ 15` (E.164 max 15). An unparseable number returns `''` from normalize and is never wrapped → fallback number (run through the same normalize) → else `''`. No group-shape policing — only total digit count (absurd author grouping still dials). `<7` is a documented heuristic floor (short codes / tiny countries are the known false-reject → deferred #3). (= email VE4 analog, length-gated not `is_email`)

**VP-href-safe** The `tel:` href is constructed from digits + boundary hyphens BY CONSTRUCTION — groups are digit-runs only, every non-digit is a discarded separator, so no raw field text reaches the href. `esc_attr()` on the href is defense-in-depth. The DISPLAY side carries raw field text (VP2), defended by `esc_html()`. Two distinct sinks, two distinct defenses.

**VP-vis** Registers with native GB `visibility` `tagName NOT_IN ['a','button','img','picture']`, mirroring GB core `term_list` and the email tag (VE3). The default-ON `tel:` wrap emits an `<a>`, so placing the tag inside an anchor/button is nested/invalid interactive markup and inside img/picture is text in a void/replaced element. Block-attribute gate (NOT the JS `show_if` option gate).

---

## §T — Tasks

| id | status | task | cites |
|----|--------|------|-------|
| T1 | x | New file `includes/tags/phone-tags.php`; `require_once` after the email require (`bws-gb-dynamic-tags-extensions.php:128`); call `bws_register_phone_tag()` after `bws_register_email_tag()` (`:136`). `static $registered` guard, unconditional registration. | I.phone-tags |
| T2 | x | `bws_register_phone_tag()` — clone `bws_register_email_tag()`: `tag:phone`, `title:Phone`, `type:cross-source`, `visibility` tagName NOT_IN `['a','button','img','picture']`. Options: `src` + traversal + `key` ("Meta/Option Field") + `noLink` + list-mode `limit`/`sep` + `fallback` ("Fallback Phone Number"). NO `cc`, NO `subject`, NO `use`. Panel order src→traversal→key→noLink→limit/sep→fallback. | VP1,VP-vis,I.phone-tags |
| T3 | x | `bws_phone_resolve_numbers( $options, $instance ): string[]` — verbatim clone of `bws_email_resolve_addresses` (site / term-hop / scalar field-read paths), name changed. | I.phone-tags |
| T4 | x | `bws_phone_normalize_tel( string $raw, string $cc, bool $stripCc ): string` — 9-step algorithm: detect `+`/`00` intl; sever extension junk first (after ≥7 digits); capture separator map via `preg_split('/\D+/', PREG_SPLIT_NO_EMPTY)` (parens break group); flatten to digits; strip-leading-CC (VP-strip, marker-shift); trunk-0 strip when CC applied (marker-shift, keep-0 on national fallback); apply CC; reassemble with author hyphen markers; length-gate 7-15. Returns dial VALUE (no `tel:` prefix), `''`=invalid. Trunk-strip + length-gate as named private sub-helpers. **Thorough PHPDoc with worked examples** for the trunk-strip/marker-shift/strip-CC logic; note "Europe-correct trunk heuristic; Italy/others may need the #3 table." | VP-hyphen,VP3,VP-strip,VP4,VP-href-safe,I.phone-tags |
| T5 | x | `bws_phone_render_one( string $raw, string $cc, bool $link, bool $stripCc ): string` — display = `esc_html($raw)`; validity = `'' !== normalize`; if `$link` AND valid → `<a href="tel:VALUE">DISPLAY</a>` (`esc_attr` href). CC + strip passed in (read once in callback). | VP2,VP-href-safe,I.phone-tags |
| T6 | x | `bws_phone_callback( $options, $block, $instance )` — clone email callback: `$link = empty($options['noLink'])`; resolve `$cc`/`$strip` ONCE; per-number normalize-as-gate (run even in noLink), skip invalid; fallback on fully-empty valid set (normalized); list-mode render each valid → join `sep` (default `', '`); empty → preview label or `''`. No obfuscation. | VP1,VP4,I.phone-tags |
| T7 | x | Two global settings — add `phone` group to `SettingsPage` (mirror email plumbing ×2): schema `phone:{country_code:string, strip_leading_cc:bool}` (~13); default `'phone'=>array()` (~125); sanitize `country_code = preg_replace('/\D/','',…)`, `strip_leading_cc = !empty(…)` (~145); accessors `get_phone_country_code()` + `is_phone_strip_leading_cc_enabled()` (~236); "Phone" section after "Email" (~622) — CC text input + strip checkbox (always rendered, help notes CC dependency, no-ops when CC empty). | VP3,VP-strip,I.class-settings-page |
| T8 | x | Guarded wrappers `bws_phone_default_cc(): string` + `bws_phone_strip_leading_cc(): bool` in phone-tags.php (mirror `bws_email_obfuscation_enabled` class_exists/method_exists guard; defaults `''` / `false`). | VP3,VP-strip,I.phone-tags |
| T9 | x | Preview label (`preview-helpers.php`): warning branch `elseif ('phone'===$base_template && ''===$key){ $missing[]='field key'; }` (~551); field-part `case 'phone': $field_part = ''!==$key ? "Phone: '".$key."'" : 'Phone'; break;` (~687). Label-only — no sample href, no global-CC nag. | I.preview-helpers |
| T10 | . | Docs: `docs/tag-reference.md` registry row + source matrices + options table + C-normalization/2-tier-CC/strip-flag/national-fallback note; `CHANGELOG.md` `[1.10.0] ### Added`; `README.md` `{{phone}}` overview line. No new `gb-constraints.md` entry. | I.tag-reference |
| T11 | . | Version bump 1.9.2 → 1.10.0 (plugin header + `BWS_DYNAMIC_TAGS_VERSION` const + readme stable tag if present). | — |
| T12 | . | Regression matrix (new section): in-field intl / global CC / none→national; trunk-0 strip (UK) + national-fallback keep-0; strip-leading-CC US (`1-800…`, on/off, length-gate backstop); C author-group reuse (parens/space/dash/dot) vs bare-digit no-hyphen; `noLink` plain; list join + skip-invalid + all-invalid→fallback; empty→fallback; fallback normalize; length reject (<7,>15); junk-sever (`x99`/`ext`); `00`→`+`; src:site + src:current + term-hop; VP-href-safe (no raw text in href). | VP1,VP3,VP-strip,VP4,VP-hyphen,VP-href-safe |

---

## §B — Bugs

| id | date | cause | fix |
|----|------|-------|-----|
