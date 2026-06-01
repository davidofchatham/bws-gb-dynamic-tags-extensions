# SPEC — `src:site` unified site source (v1.9.0, Stage A)

Source: `.claude/plans/src-site-unified-source.md`. Scope: **Stage A only** (base-tag `src:site`). Stage B (#26 try_ slot derive) + Stage C (slot dispatch arm, `class-site.php`) OUT — not in this spec.

## §G — Goal

One source `src:site` for all site-wide data behind existing base tags (text/permalink/image/content/datetime_single/datetime_range). One tag name, `use:` enum picks datum. Unlocks `{{option}}`-style key reads on datetime (ACF date opts) + content (block-markup opts) — combos GB Pro lacks.

## §C — Constraints

- C1 — No new source class, no registry registration in Stage A (decision S2). Registering pulls site into `get_effective_sources()` (try_ slot map) before Stage C dispatch arm exists → `resolve_id()`→`post_fn(0)` footgun. Class deferred to B/C.
- C2 — `site` works as `bws_base_source_option()` dropdown value + early-gate string alone. `bws_resolve_post_by_source` never reached for site (early gate short-circuits before it).
- C3 — Option reads OUR responsibility to allowlist-gate. `Meta_Handler::get_option()` enforces only blocklist (`DISALLOWED_KEYS`); does NOT consult `generateblocks_dynamic_tags_allowed_options` (that lives in GB Pro `get_post_option` callback, upstream, skipped when calling handler direct).
- C4 — Allowlist seed EMPTY (A2). `use:option` = pure escape hatch; dev opts each key in. Diverges from GB Pro `{{option key:blogname}}` deliberately — steer migrators to `use:title`.
- C5 — Datetime-site = ACF-options read (`get_field($key,'option')`), NOT `get_option`. ACF retains `return_format`; `get_option` loses it. Acceptable — datetime tags already ACF-bound everywhere, no NEW lock-in. Non-datetime site values stay ACF-free.
- C6 — No `meta`/`single_only` control on site `key`. `get_option` called `$single_only=true` (matches Pro). Array-root reads (no subkey) → empty; useful reads = scalar leaf via dot-path.
- C7 — `linkTo` static value list this release (L3a). Conditional JS gates whole controls by `element.key`, not individual `options[]` entries → dropdown shows all 4 values every context. Leakage harmless both ways. Per-value `show_if` gating = L3b, deferred (general capability → #27), do NOT block v1.9.0.
- C8 — Content pipeline UNCHANGED. `bws_render_block_content` shipped 1.8.0; Stage A = call site only, no refactor.
- C9 — Text domain `'generateblocks'`; all fns prefixed `bws_`; version 1.9.0.

## §I — Interfaces

- I.src — `bws_base_source_option()` (base-tags.php ~557): add `{value:'site', label:'Site'}`.
- I.resolve — new `bws_site_resolve_value($tag, $options, $instance)`. Used by text/title/permalink/image/content ONLY (not datetime). Switch on `use`:
  - title tag has NO `use` enum → `$tag==='title'` (or `use:title`) → `get_bloginfo('name')`. text `use:title` same.
  - `tagline`→`get_bloginfo('description')`; `title`→`get_bloginfo('name')`
  - `site_url`→`site_url()`; `home_url`→`home_url()`
  - `logo`→`(int)get_theme_mod('custom_logo')` → `bws_get_attachment_data($id, as ?? 'url', size ?? 'full')`
  - `option`→allowlist-gate (A2 empty seed), then `Meta_Handler::get_option($key,true,'')`. Content callback: pass raw through `bws_render_block_content($raw,'option:'.$key)` instead (NO extra `wp_kses_post`).
- I.gate — text/content/title/permalink/image callbacks (base-tags.php 725/797/841/898/932): early gate `if ('site'===($options['src']??'')) return bws_site_resolve_value(...)` before entity-resolve. title callback site path must still apply `bws_wrap_with_link(...,1,'site')` (link-eligible).
- I.dt — `bws_base_datetime_single_callback` (datetime-tags.php:988) / `_range_callback`: SEPARATE path. On `src===site` call `bws_datetime_single_core('option', bws_base_map_datetime_options($options), $instance)` (+range equiv), then `bws_wrap_with_link(...,1,'site')` tail. NO `use` enum — `key` is direct field-key control.
- I.read — **DT-1**: `bws_read_field` (field-helpers.php:~230, before `return null` tail) add: `if('option'===$post_id && function_exists('get_field')){ allowlist-gate $key; return get_field($key,'option'); }`. Sentinel currently dead-ends at null → zero behavior change for int/loop/term callers.
- I.link — **L3**: `bws_resolve_link_url` (link-helpers.php:37) add `'site'` entity branch: `linkTo:site`→`home_url()` (ignores `$id`); `linkTo:key`+site→allowlist-gate `$link_key` then `get_option($link_key)`. Site callbacks pass `$link_type='site'`, sentinel `$link_id=1` (defeats `if(!$id)` guard line 38). Add `site` value to `linkTo` dropdown (`bws_get_link_options`). Link options on text/title/datetime_* only (`supports_link_wrap`); content/permalink/image excluded.
- I.opts — per-tag `use:` enum (`show_if: src:site` per entry):
  - text: `tagline`,`title`,`option` | permalink: `site_url`,`home_url`,`option` | image: `logo`,`option` | content: `option` only | title: NO use enum (site→name) | datetime_single/range: NO use enum.
  - `key` option `show_if {src:site, use:option}` on text/permalink/image/content. title + datetime: NO `key` for site (title=name only; datetime existing `key` unhidden/relabeled, no `use:option` gate).
  - Suppress for site (`show_if src:not:site`): `ref`, `srcTermIn`, traversal options — on ALL site-capable tags incl. title.
- I.adr — [ADR 0001](docs/adr/0001-site-option-read-allowlist.md): empty-seed allowlist, "OUR resolver gates not handler", 3 gated read paths (`use:option`, site `linkTo:key`, datetime `get_field(…,'option')`).

## §V — Invariants

- V1 — `bws_site_resolve_value` MUST allowlist-gate every option read before `Meta_Handler::get_option`: `$parent=explode('.',$key)[0]; if(!in_array($parent,apply_filters('generateblocks_dynamic_tags_allowed_options',[]),true)) return '';`. Seed empty. !C3,C4,ADR 0001 — `@invariant` PHPDoc on `bws_site_resolve_value`.
- V2 — All THREE option-read paths gate through same `generateblocks_dynamic_tags_allowed_options` filter: `use:option`, site `linkTo:key`, datetime `get_field(…,'option')`. Consistent disclosure boundary. ACF keys flat → gate whole `$key` (no dot-path split).
- V3 — Datetime-site output formatting MUST flow through `bws_build_single_format` / `bws_build_range_format`. Site-format fallback (`get_option('date_format')`/`time_format`) = their tier-3; MUST NOT be re-implemented independently. Format precedence: (1) tag `custom_format` if `format_type:custom` → (2) ACF `combined_format` → (3) site format. !C5,I.dt — reason DT-1 (reuse `_core`) beats DT-2 (dup formatting regresses chain).
- V4 — Stage A introduces NO source class + NO registry registration. `site` is dropdown-value + early-gate-string only. !C1,C2.
- V5 — Site early gate MUST pre-empt any `SourceRegistry::get_source('site')` lookup (returns null → choke). Preview-label + GB-registration paths must not call it for site. Editor preview = likely edge.
- V6 — `content + use:option` recursion bounded by existing 1.8.0 guard via `'option:'.$key` cache key (key collision = guard hit, by design). Distinct keys bounded by `bws_content_max_recursion_depth` (default 3). No pipeline change. !C8.

## §T — Tasks

id|st|task|cites
T1|x|Add `site` value to `bws_base_source_option()` dropdown|I.src,V4
T2|x|Write `bws_site_resolve_value` (tagline/title/site_url/home_url/logo/option) w/ allowlist gate + `@invariant` PHPDoc → ADR 0001|I.resolve,V1,V2
T3|.|Early gate in text/content/title/permalink/image callbacks → `bws_site_resolve_value` (title path link-wraps via `,1,'site'`)|I.gate,C2,V5
T4|x|content callback site path routes raw opt through `bws_render_block_content($raw,'option:'.$key)`, no extra kses|I.resolve,C8,V6
T5|.|DT-1: `'option'` value-read branch in `bws_read_field` (allowlist-gated `get_field($key,'option')`)|I.read,V2
T6|.|Datetime callbacks site gate → `bws_datetime_single_core('option', bws_base_map_datetime_options(...))` + range; link-wrap `(...,1,'site')`|I.dt,V3
T7|.|L3: `'site'` entity branch in `bws_resolve_link_url` (site→home_url; key→gated get_option) + sentinel id=1 + `site` value in `bws_get_link_options`|I.link,V2
T8|.|Per-tag `use:` enum builders + `key` option (`show_if {src:site,use:option}`); add `site` to source dropdown reflected on title (no use enum); suppress ref/srcTermIn/traversal via `src:not:site` on ALL site-capable tags incl. title|I.opts,C6
T9|.|Build-time verify: `get_field($key,'option')` returns value + `get_field_object` returns `return_format` outside loop/admin on test instance (instrument, pull to test — not live)|I.read,V3
T10|.|Editor: src→Site hides ref/srcTermIn (all site-capable tags incl. title); use enum site-only; title src:site→site name (no use/key); datetime shows key direct; key only on use:option; round-trip save/reopen no GB strip|I.opts,V5
T11|.|Docs: tag-reference §Site Source (matrix/use enum/dot-path/allowlist/Pro coexist); plugin-integration filter note; CHANGELOG 1.9.0 entry (ref 1.8.0 pipeline, no refactor line)|—

## §B — bugs

id|date|cause|fix
