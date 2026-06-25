# SPEC — #32 try_email/try_phone via base-derived slot machinery + list-join seam

Active spec. Issue #32 (enhancement, spine) + #24 (bug, folded — preview no-key warning). Prereq #26 (refactor) SHIPPED (slot src/ref/srcTermIn derive from base; see PHPDoc on `bws_build_slot_traversal_options` + `bws_slot_qualify_show_if`). Plan: `.claude/plans/try-email-phone-and-slot-derivation.md` §#32. Model: CONTEXT.md §I6 (try_ transparent wrapper) + §I7 (list-mode destination gate) + §L1/L2/L3, ADR 0002.

## §G — goal

Register `email`/`phone` as modifier templates → `try_email`/`try_phone` + `term_email`/`term_phone` fall out of existing machinery. Add the list-join seam to `generate_base_try_tags()` so a winning slot's finished per-item strings join (`implode($sep, slice($limit))`) — closes the I6 parity defect (try_text/try_title truncate lists their base tags join). try_email/phone MUST ship with `src:site` slot (canonical contact fallback). Full parity: term_ variants too.

## §C — constraints

- C1: text domain `'generateblocks'`; all fns `bws_`-prefixed.
- C2: NO version bump / CHANGELOG release until whole #26+#32 plan completes (user instr). Batch commits OK.
- C3: list-join seam is composition-BLIND (I6). Machinery does TWO things only: pick first non-empty slot + `implode($sep)` its finished items. NO per-item transform (`try_item_fn` cut — composition-in-resolve). ALL wrap/compose/range/ext-append lives in slot's own resolve, shared w/ base callback.
- C4: HARD GATE — existing try_text/try_content/try_image output byte-identical pre/post Phase 2. 1 finished string + default `limit` = today verbatim (no trailing sep, no wrap change).
- C5: seam `limit`/`sep` semantics MATCH base text core (base-tags.php:884): `limit = max(1,(int)($opts['limit']??1))` default 1; `sep = $opts['sep']??', '`. ⊥ "0=unlimited" (would diverge from base parity).
- C6: array contract lives at resolver/L2 layer (Phase 3/5 producers), NOT retrofitted into shipped dispatchers. Seam boundary helper accepts string OR array. text/content/image dispatchers stay string-returning until their phase rewires (stale plan step-2 "adapt dispatchers" line corrected — array produced at L1/L2, ADR 0002).
- C7: `src:site` slot for email/phone = slot-resolver site arm (item 1) ONLY. Dispatcher site-read already done (`bws_*_resolve_addresses` read site via `bws_site_read_option`). datetime-id-fork + home_url link-entity N/A for email/phone. datetime/text/image src:site stays DEFERRED (separate issue).
- C8: shared L1/L2 resolver (Phase 5) HARD PREREQ for email/phone site slot — retire clones `bws_email_resolve_addresses`/`bws_phone_resolve_numbers`, site falls out, `src:ref` plural lists "just work". base `{{email}}`/`{{phone}}` byte-identical after extraction.
- C9: term_email/term_phone IN SCOPE — full parity, no gate flag (decided 2026-06-12).

## §I — interfaces

- `generate_base_try_tags()` — registry:359. Slot callback :554, post path :686, srcTermIn arm :648-665. MUTATE: slot-result boundary → collect items → join.
- new `bws_try_normalize_items( $raw ): array` — string→`['']`-stripped `[$s]`; array→array filtered non-`''`; `''`/false→`[]`. Pure.
- new `bws_try_join_items( array $items, $sep, $limit ): string` — `implode($sep, array_slice($items,0,max(1,(int)$limit?:1)))`. Pure. `sep` default `', '`.
- `try_core_fn`/`try_term_fn` — fn($id,$opts,$inst): string|array<string>. Returns finished strings (link-wrapped/composed). [contract widened: string|array]
- base text/title list shape: `bws_post_custom_text_core` (base-tags.php:882-899) term-loop + slice + implode + single-result link-wrap — the parity target Phase 3 matches.
- email/phone per-item compose ALREADY shared: `bws_email_render_one` (email-tags.php:277), `bws_phone_render_one` (phone-tags.php:462). base callbacks already `foreach`→render-one→`implode($sep)`. try_ dispatch CALLS same — no extraction risk.
- `register_modifier_template()` — registry:52. email: `key=email`,`supports_try`,`try_per_slot_key=true`,`try_per_slot_use=false`,`try_use_no_key_values=[]`,`is_image=false`. phone twin.
- `bws_build_try_preview_label($opts,$tpl_key)` — preview-helpers.php. Add email/phone text-like `$needs_key=true` cases (#24 fold).
- `visibility` passthrough — verify template→try_ tag threads `tagName NOT_IN [a,button,img,picture]` gate (VP-vis).

## §V — invariants

V1: try_ machinery is composition-blind (I6). Picks first non-empty slot + joins its finished items. NEVER wraps/composes/transforms an item. Enforced: `generate_base_try_tags()` PHPDoc + this §.
V2: slot non-empty IFF `bws_try_normalize_items($raw)` yields ≥1 item. Winning slot = first non-empty. Whole list surfaces (sliced to limit, joined) — truncation-to-first = parity defect (I6).
V3: BYTE-IDENTICAL gate — 1 finished string + default limit=1 + no explicit sep → output == pre-seam verbatim. ∀ existing try_text/try_content/try_image. [C4 regression gate]
V4: seam limit/sep match base text core EXACTLY (C5): `max(1,(int)($limit??1))`, `sep??', '`. try_ join == base list-mode join for same options (I6 parity).
V5: srcTermIn arm collects term results into list (not return-first) THEN joins via seam — but default limit=1 keeps it == today (first term). Parity defect closes only when author sets limit>1 (Phase 3 text/title producer exposes >1 item).
V6: array contract at L2/resolver layer (C6). `try_core_fn` MAY return array; shipped dispatchers return string until their phase. Seam helper string|array agnostic. ⊥ retrofit 5 dispatchers in Phase 2.
V7: email/phone src:site = slot-resolver site arm only (C7). On slot `src:site`: set `$slot_opts['src']='site'`, call try_core_fn `$post_id=0`, already-site-aware resolve reads option, finished string(s) return. Threads `src` to dispatcher resolve. ⊥ regress current/ref/srcTermIn arms.
V8: site re-allowed per-TEMPLATE (email/phone) past the #26 generic filter — append `site` back in those templates' derived slot src list. datetime/text/image stay filtered. ⊥ global filter removal.
V9: shared L1/L2 resolver (Phase 5) HARD PREREQ for V7/V8 ship. base email/phone byte-identical post-extraction; `src:ref` list stops collapsing to first; current/term arms unregressed. [C8]
V10: email/phone per-item compose == base callback's (`bws_*_render_one`, shared). try_ dispatch resolve→render-one→array matches base per-item output (parity). ⊥ try_-private compose copy.
V11: visibility gate threads template→try_email/try_phone (`tagName NOT_IN [a,button,img,picture]`). [VP-vis — likely untested path; text/content have no gate]
V12: email/phone preview = text-like `$needs_key=true`, `try_use_no_key_values=[]`. Empty-key slot → `⚠ slot N no key` (#24: correct — default key-mode, no native default field → empty slot genuinely unconfigured). ⊥ collapse-to-label (that is content's correct shape, not email/phone's).
V13: term_email/term_phone register + preview, full parity (C9). No gate flag.

## §T — tasks

id|status|task|cites
T1|x|Phase 2: add `bws_try_normalize_items` + `bws_try_join_items` (pure, string\|array→joined). Harness `tools/test/try-join-seam-test.php` 27/27. 1-elem+default=verbatim, N-elem joins, limit slice (floor 1 no ceiling), sep default, ''→empty, array passthrough|V2,V3,V4,I
T2|x|Phase 2: wire seam into `generate_base_try_tags()` slot boundary — post path + srcTermIn arm (collect-into-list, slice-then-count for link-wrap, early-break at slot_max). sep/limit from opts; default limit 1 = today verbatim. lint clean, #26 harnesses unregressed|V1,V2,V4,V5
T3|x|Phase 2 GATE: unit byte-identical PROVEN + live VERIFIED — front-end render unchanged; editor smoke try_text (slot1 key, slot2 2-src:ref), srcTermIn first-term, empty-chain ⚠ preview, try_content/try_image load+render all pass (user 2026-06-17)|V3,C4
T4|x|Phase 3: try_text/try_title list parity — `try_list_options` flag (try_ only, term_text untouched per scope decision) appends chain-level limit/sep with multi-slot show_if_any OR. Producer = seam's srcTermIn term-hop collection (no dispatcher change). LIVE-VERIFIED (user 2026-06-17): limit/sep appear on srcTermIn slot; limit:2 joins term list on front-end. I6 parity defect closed|V5,V4
T5|x|Phase 4: `visibility` passthrough — `register_gb_tag` takes optional $visibility, omits when empty (byte-identical for gateless tags). Both callers (`register_modifier` term_*, `generate_base_try_tags` try_) pass `$tpl['visibility']`. Confirmed GB constructor honors `visibility`=>['attributes'=>[...]] (email-tags.php:61 shape). lint+harnesses green. Gate-present LIVE-verify deferred to email/phone land (nothing sets visibility yet)|V11
T6|x|Phase 5: `bws_resolve_field_values` extracted to field-helpers.php (verbatim shared L1/L2 body, function_exists-guarded). Both clones DELETED; email/phone callbacks call shared. LIVE-VERIFIED (user 2026-06-17): {{phone}} tel, {{email}} post-meta mailto, {{email src:site}} all unchanged — extraction faithful, V9 closed|V9,C8
T7|x|Phase 5: slot-resolver site arm added (registry, after srcTermIn before post path) — `src:site` short-circuit, `$cf(0,...)`, no link-wrap. Per-template re-allow: `try_allow_site_slot` flag → `bws_build_slot_traversal_options($n,...,$allow_site)` 4th param skips #26 site filter. text/image stay filtered. LIVE-VERIFIED: try_email/try_phone site slot reads option not current-post (user hard gate ✓)|V7,V8
T8|x|Phase 6: `email` template registered via `bws_register_email_template()`. Shared `bws_email_finish_values` (validate+render-one); base callback refactored to reuse (V10). try_/post/term dispatchers + media-block backstop. LIVE-VERIFIED: try_email chain + site slot, base {{email}} unchanged|V10,V12,I
T9|x|Phase 7: `phone` template twin. Shared `bws_phone_finish_values`; base callback reuse. LIVE-VERIFIED: try_phone chain + site, base {{phone}} unchanged|V10,V12,I
T10|x|Phase 8: preview email/phone — needs_key=true branch + template_label + field_part. #24 fold: empty→`⚠ slot N no key`. Harness 62/62|V12
T11|x|Phase 9: term_email/term_phone fall out of register_modifier (term_fn/post_fn present). LIVE-VERIFIED render off term meta. NOTE: term_ src:site wrong-reads (pre-existing term_*-wide, §B1)|V13
T12| |VERIFY: multi-slot chain personal→term→site→fallback first-non-empty wins; site slot reads option not current-post (hard gate); transparency (single slot term-hop list == standalone {{email}} joined); visibility present; subject `:`/`|` escape no double-unescape; obfuscation gate inside shared compose|V2,V7,V10,V11

## §B — bugs

id|date|cause|fix
B1|2026-06-17|term_* tags expose `src:site` but `make_modifier_callback` has NO site arm (only ref / base-context current). A term_email/term_phone (or term_text) slot set src:site resolves the entity via the term/base source, ignoring site → reads term/post meta under the option key, not the site option. PRE-EXISTING term_*-wide (not introduced by #32); term_email/term_phone inherit it. NO new invariant. → FILE GH issue post-#32: add a site arm to make_modifier_callback (mirrors the try_ slot site arm T7) OR filter site from term_ source list. Email/phone term_fn/post_fn ALREADY site-capable (delegate to bws_resolve_field_values) — only the callback's source dispatch lacks the route.|deferred → GH issue
