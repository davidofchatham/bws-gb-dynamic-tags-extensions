# Editor Controls

Owner doc for the plugin's custom editor-control architecture: the `bws-*` control pattern, the
`tagSpecificControls` seam every control injects through, `setState` param authority + the
`delete`-omit idiom, the composite "two controls one key" pattern, dynamic labels / entry
filtering / reconcile-on-`src`-change, the option-group **wrapper** (`_group`/`_group_lead` +
`option-group.js`'s CSS-joined run, lead-boxes-alone, captions-belong-to-controls), the
wrapper-keying/node-lessness invariants every filter in the `tagSpecificControls` chain must
honour, and the folded-slot control's (`assets/js/slot-fold-control.js`) compaction mechanics and
registration/resolution flow.

Schemas (option names, labels, values, conditionals) stay in
[`docs/tag-reference.md`](tag-reference.md) — this doc owns *mechanism*, not the option catalog.
GB-imposed facts stay in [`docs/gb-constraints.md`](gb-constraints.md). Load-bearing invariants
scoped to one function/class live as PHPDoc on that function/class; this doc is for invariants that
cross several files or several tag families. **Field discovery is NOT here** — it is decoupled to
`field-selector.md` (own ship/lifecycle); its `bws-field-combo` control + REST endpoint own their
invariants via the spec issue → PHPDoc on ship.

---

## Shared option groups

Options common to most base tags, defined **once** here. Each per-tag section in
[`tag-reference.md`](tag-reference.md) lists only its tag-specific options and links back to these
groups. The control order these slot into is the
[Option layout & visibility](tag-reference.md#option-layout--visibility) model in
`tag-reference.md` Part I.

Option / required-option rules for deprecated N×M wrappers (e.g. `related_post_*`,
`term_related_post_*`, `custom_text`, `custom_image`, `term_custom_*`) live in
[`docs/deprecated-tags-options.md`](deprecated-tags-options.md), not here.

### Source group

The source selector and its conditional sub-options. Present on every base tag. In a **multislot**
container (`try_*`, `{{join}}`, `{{table}}`) the same source axis lives inside the folded slot
value as a chain — `src(refs,office;terms,category)`, [§Folded slot
wire](tag-reference.md#folded-slot-wire-multislot-containers) — so the `N-`prefixed keys below are
**legacy wire** (registered through v1.16.x, still read by the renderer, never written).

| Option name | Option label | Context | Notes |
|---|---|---|---|
| `src` | Source | Base / Slot 1 | `source` avoided — GB unconditionally strips it from extraTagParams before our controls can read it |
| `N-src` | [N]: Source | Slot 2+ *(legacy wire)* | Pre-1.17.0 multislot spelling; the folded chain replaces it |

**`src` option values — per-slot UI/serialization mechanics** (labels, the slot-2+ `same`/`current`
distinction, the editor-preview context segment each value produces). For **what each value
resolves to** (and implementation status), see [§`src` option
values](tag-reference.md#src-option-values) in `tag-reference.md` Part I.

| Option label | Option value | Base / Slot 1 | Slot 2+ | Context segment in editor preview label | Notes |
|---|---|---|---|---|---|
| Same as Previous Source | `same` | Current entity — not serialized | Inherit slot N−1 | N/A | Slot 2+: prepended entry, not in template definition |
| Current | `current` | stripped → unset | `current` | *(omitted)* | Slot 2+ only: explicit override back to current |
| In Reference/Relational Field | `ref` | `ref` | `ref` | `Ref 'X'` where X = `ref` field value | Triggers `ref` sub-option |
| Parent | `parent` | `parent` | `parent` | — | Future |
| Ancestor | `ancestor` | `ancestor` | `ancestor` | — | Future |
| Child(ren) | `child` | `child` | `child` | — | Future |

Note: For context-modifier tags, the modifier label is prepended as a context segment. Examples:
`[Title from Term]` for `{{term_title}}`, `[Content from Term Ref 'rel_post']` for `{{term_content
src:ref|ref:rel_post}}`. See [`editor-tag-previews.md`](editor-tag-previews.md) for assembly rules.

**Source secondary, conditional options:**

| Option name | Option label | Help text | Shown when | Notes |
|---|---|---|---|---|
| `ref` | Relationship Field Key | ACF relationship or post object field key. | `src` = `ref` | ACF relationship/relational field key for the traversal step. **Required** when `src:ref` selected. |
| `srcTermIn` | Get from taxonomy term? | Field is in a taxonomy term on this source. | Always; hidden for `term_` modifier tags (entity already a term) at `src:current`; shown at `src:ref` | Combined `bws-term-hop` control (CheckboxControl + ComboboxControl). Empty/unset = disabled; slug = enabled with that taxonomy (the slug encodes both "term step on" and the taxonomy — **required** when the step is on). Replaced prior `srcTerm` + `tax` pair (v1.6.0). |
| `limit` | ~~Result Limit~~ *(HISTORICAL label — no control registers it)* | Maximum number of results to return. Default: 1 on flat wire, unlimited on a source chain. Enter 0 for no limit. | **LIVE READ, RETIRED CONTROL.** No control on any chain-authoring tag (v1.17.0, [#62](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/62)): a limit is stated where the source is stated, and a chain states its source as steps, so the limit rides the fanning step. Unregistered rather than gated on flat wire — the mount migrator rewrites a flat tag to a chain before the panel paints, so a flat-only predicate would be unreachable. **No tag registers it** as of #62. A flat-select family, whose source is a single step, is where the key belongs; nothing offers it today | The VALUE is still read wherever it is written — removing a control never removes an option ([ADR 0004](adr/0004-serialized-tag-string-human-readable.md); GB seeds state from the tag string, not the registry), so unmigrated flat wire and hand-edited wire keep rendering. Placeholder `1`; not serialized when unset; **`0` (or a hand-typed `-1`) = UNLIMITED** since 1.17.0, non-numeric reads as unset — see [§List mode](tag-reference.md#list-mode-limit--sep). Bounds the WHOLE list; a chain's per-step limits are a different quantity (per-input) and live in the source value — their control is **Limit results**, [§Chain step controls](#chain-step-controls). **A label with no control answers to nothing** — this row was the only documented limit label while the per-step control had none, so a draft "Limit per source" sat against it for the whole of 1.17.0 development with nothing to disagree with it ([#95](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/95)). Hence the strikethrough rather than a quiet deletion, and hence the [§Chain step controls](#chain-step-controls) table. |
| `sep` | Result Separator | Separator between results (defaults to “, “). | `srcTermIn` set, or `src` = `ref` or a fanning chain (`chain_fans`) — unlike `limit` it DOES ask `chain_fans`, because it joins printed output whatever the source spelling; unconditional on a multislot container, where the list axis sits inside a slot value that `show_if` cannot inspect | `text`, `title`, `email`, `phone`, `datetime_single`, `datetime_range` and the `try_` list templates (`try_text`, `try_title`, `try_email`, `try_phone`). Alone in the source group on a `try_` tag, so it renders unboxed there — the attempts are that tag's source and draw their own boxes. |

#### Chain step controls

**These rows are POSITIONS INSIDE THE CHAIN VALUE, not option keys.** Every one of them edits part
of a single `src` (or folded slot) value — `src:refs,office,limit(1);terms,category` — so there is
no `limit` option, no `taxonomy` option and no per-step `ref` option to look for in the tables
above. The one `limit` OPTION KEY the plugin has is the tag-level row above it, which is a
different quantity with a retired control.

Rendered by one component in both places a chain is authored — the base tag's `bws-src-chain`
control and a multislot container's `bws-slot-fold` slot — so a step reads identically on
`{{text}}` and inside a `{{join}}` field.

**Every string that names a SCHEMA fact arrives on the PHP option definition** — the step enum's
row labels, the Relationship Field Key picker and the whole of Limit results, all from
`bws_fold_wire_vocabulary()` / `bws_base_traversal_options()`. The control hand-authors none of
those; a second copy there is how the image tag's `Return type:` / `Return image as:` labels
drifted. The two strings it does author — the step picker's own "Source" label and "Taxonomy" —
name the control rather than anything on the wire, so there is no definition for them to disagree
with.

| Control label | Position in the chain value | Help text | Shown when |
|---|---|---|---|
| Source | the step's own **slug** — `refs` / `terms` / `entries`, or at step 1 the chain ROOT (`current`, `site`, a registered root, or `same` in a slot ≥2) | — | Every step. The visible label is suppressed on a single-step chain (the group caption already says "Source"); the label still exists for screen readers |
| Relationship Field Key | the **arg** of a `refs` (or `entries`) step — `refs,<field>` | ACF relationship or post object field key. | Step slug is `refs` or `entries`. Same definition the flat `ref` option ships, so the picker reads alike either side of the fold |
| Taxonomy | the **arg** of a `terms` step — `terms,<taxonomy>` | — | Step slug is `terms`. Enum = public taxonomies, shipped with the definition |
| Limit results | the step's **`limit(N)` token** — `refs,office,limit(3)` | *Maximum number of results. Leave blank for all.* — or, where an earlier step fans, *Maximum number of results for each previous-step result. Leave blank for all.* | Every step (never a bare root: a source resolving one entity has nothing to bound). Blank = unlimited; `0` is normalized to blank and never serialized, `-1` parses the same way for hand-edited wire |

**Limit results carries two help forms, chosen by whether an earlier step actually FANS — not by
step position.** Per-step limits are per-input and multiply (`∏ limitₙ`), but where nothing
upstream fans there is exactly one input, so per-input and total coincide and the clause would ask
the author to reason about a distinction that cannot arise. A step at chain position 3 whose
predecessors are all single-valued gets the plain form; an argless fanning step upstream does not
count, because the compiler drops it. The predicate is `bws_fold_chain_fanning_steps()` — the same
one the migrator stamps by and the render seam defaults by — reached in the editor through its
shipped JS twin, never re-derived from the index.

The control carried a draft label of **Limit per source** for most of 1.17.0's development and was
corrected before release ([#95](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/95))
— it sat three rows below a control labelled *Source* meaning something else, named sources while
bounding results, and stated the per-input rule everywhere except the label. **No author ever saw
it, so it is not a rename**: nothing shipped under it, no CHANGELOG entry describes it, and it has
no row in [`deprecated-tags-options.md`](deprecated-tags-options.md). Recorded here only because
what let it stand that long was the absence of this table. The option key did not move and no
stored wire was rewritten.

##### Field configuration note

Between the Relationship Field Key control and Limit results, a selected field can carry a **field
configuration note**: a statement of what ACF does and does not enforce about how many entries that
field can hold ([#96](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/96)).
It exists because three facts that change what a `refs` step returns are invisible from the tag
modal — ACF's Max Posts is not enforced on any write path (`validate_value()` has a `min` branch and
no `max` branch), bidirectional writes bypass it in both plugins, and a single-value post object can
silently hold several entries — and the ACF admin is unreachable from the contexts field discovery
exists to serve.

**It describes and never gates.** No wire changes, no save is blocked, no rendered output moves. Its
value is that the *enforced* bound is the Limit results control sitting directly beneath it, so the
note reads as the setup for the number about to be chosen. That adjacency is left implicit; the note
carries no call to action.

**Definitions only, never a value read** — so it works identically in Patterns, Elements and
ordinary post edit screens, which is the point. Derived by `bws_field_discovery_field_note()` in
[`includes/rest/field-discovery.php`](../includes/rest/field-discovery.php) and shipped as ordered
**segments** on the field-discovery envelope, so every user-facing string stays in PHP and the
control renders what it is given.

| # | Field configuration | Note |
|---|---|---|
| 1 | Relationship, configured limit, bidirectional | *Bidirectional field with a configured limit of N. Edits to its bidirectional target field(s) on other posts, terms, or users can add more entries; the limit is enforced only when this field is edited directly, using ACF.* |
| 2 | Relationship, no configured limit, bidirectional | *Bidirectional field with no configured limit. Edits to its bidirectional target field(s) on other posts, terms, or users can add entries.* |
| 3 | Relationship, configured limit, not bidirectional | *Field with a configured limit of N. The limit is enforced only when this field is edited directly, using ACF.* |
| 4 | Post object, single-entry, ACF **native** bidirectional | *Bidirectional field configured as single-entry. Edits to its bidirectional target field(s) on other posts, terms, or users can add more entries; the limit is enforced only when this field is edited directly, using ACF.* **+ the consequence clause** |
| 5 | Post object, single-entry, **ACF Extended** bidirectional | *Bidirectional field configured as single-entry. Edits to its bidirectional target field(s) on other posts, terms, or users replace an existing entry.* |
| 6 | Post object, single-entry, not bidirectional | *Field configured as single-entry. The limit is enforced only when this field is edited directly, using ACF.* **+ the consequence clause** |

The **consequence clause**, emphasised, is: *The first stored entry will be the only result while
this field is single-entry; all entries will be results if it is reconfigured as multiple-entry.*

Everything else is silent, and **silence is information**: the presence of a note means there is
something to know.

**Derivation rules**, so a seventh case is added by rule rather than by taste:

- **Enforcement is stated positively.** "Enforced only when this field is edited directly, using
  ACF" correctly implies that imports, WP-CLI and every other programmatic write bypass it too,
  rather than pinning the bypass on bidirectionality.
- **Case 5 carries no enforcement clause on purpose.** ACF Extended honours the single-value setting
  at write; ACF native does not. That asymmetry is the finding, not an omission — and it is why the
  bidirectional flavour is carried rather than flattened to a boolean, since the two describe
  opposite behaviours on the same field. Each flavour is read from its own settings — native from
  `bidirectional` + `bidirectional_target`, ACF Extended from
  `acfe_bidirectional[acfe_bidirectional_enabled]` + `[acfe_bidirectional_related]` — matching the
  gate each plugin's own reciprocal writer applies. Where **both** are enabled the native description
  applies: silent retention is the harder condition to diagnose.
- **The consequence clause rides single-entry, not bidirectionality.** Hiding-then-resurrecting
  follows from the format-time collapse; bidi is only the likeliest writer. Having granted that
  something can store extras, stopping before saying what those extras do would leave the author
  with the risk and not its shape. Relationship cases get no analogue: no collapse there, so overflow
  simply renders.
- **Options-page fields suppress the bidirectional clause.** ACF resolves valid bidirectional
  targets by object type and has no case for options, so such a field never receives a reciprocal
  write even with the setting ticked. It takes the corresponding non-bidirectional case instead: case
  1 becomes case 3, case 4 becomes case 6, and **case 2 becomes silence**, there being no
  non-bidirectional case for a relationship field with nothing else to report. The envelope is
  already keyed by resolved-source kind, so the discriminator costs nothing. Suppression is
  `site`-only — a term field is a valid bidirectional target and keeps the clause.
- **Taxonomy and user fields** are valid bidirectional targets with no limit setting, so they take
  case 2 unchanged — as does a **multiple-entry post object**, by the same rule rather than by a case
  of its own.
- **A half-configured bidirectional setting is not bidirectional.** Both plugins gate their own
  reciprocal writer on the toggle *and* the target list, so a toggle with no target never writes
  back.

**Shape: a list of segments, not a string plus a trailing emphasis field.** A trailing field would
bake in "emphasis always falls last", which is true only of the cases that have any today. `null`
where there is nothing to say; a case with no emphasis emits one segment, never an empty second one.

```
note: [
  { text: "Bidirectional field configured as single-entry. Edits to its bidirectional target field(s) …", emph: false },
  { text: "The first stored entry will be the only result while this field is single-entry; …",           emph: true  }
]
```

**Ambiguity is silence.** The wire stores a bare field key, which entries of different
resolved-source kinds can share, and a note is a claim about one specific field's configuration — so
the control shows a note only where every discovered entry holding that key agrees. This mirrors the
field picker's own rule for an ambiguous key (show the bare key, assert nothing). Agreement is on
the note VALUE, so one field surfaced in several homes still shows it; only genuinely different
definitions sharing a key fall silent, and an entry *without* a note disagrees with one that has it.

**Presentation.** Unlabelled, in a neutral grey panel with a left rule: distinct from the muted help
text above it and from the red validation message, and carrying no second hue. No icons — every note
opens by naming its own kind, so an icon would repeat the first two words. The note and the red
validation message cannot appear together, since the note requires a selected field and the message
fires only when none is set.

**Out of scope, deliberately.** The note does not enforce ACF's configured limit at render time,
does not pre-fill Limit results from it, and does not appear on field pickers outside chain steps.

The surface that invites the question is the [Field group](#field-group)'s own `key`, where the
same picker offers the same relationship fields — and the note is right to stay away, because
**relating is a source step, not a target field**. A relationship value is a list of post ids rather
than a datum, so `{{text use:key|key:related_staff}}` renders EMPTY where `{{title
src:refs,related_staff}}` renders the related posts (verified on the testbed, `related_staff`
holding two ids). Relocating this note there would explain a limit on a read that produces nothing;
what that surface would want, if anything, is an advisory pointing at the source step.

### Field group

The field-type selector (`use`) + field key (`key`). Present on `text`, `image`, `content`.
`title`/`permalink` have no field options (their datum is the analog); `email`/`phone` have no
`use` enum (key-required, no analog); `datetime_*` use direct field keys (see their section). In a
**multislot** container the read axis lives inside the folded slot value (`use(title)` /
`key(sku)` / `use(same)`), so the `N-`prefixed keys below are **legacy wire**.

| Option name | Option label | Context | Notes |
|---|---|---|---|
| `use` | [Text/Image/Content] Field | Base / Slot 1 | |
| `N-use` | [N]: [Text/Image/Content] Field | Slot 2+ *(legacy wire)* | Pre-1.17.0 multislot spelling |

**`use` field-selector values (where applicable):**

| Applicable tags | Option name | Option label | Conditionals | Notes |
|---|---|---|---|---|
| `text`, `image`, `content` | `same` *(prepended, slot 2+)* | Same as Previous Field | Hides additional fields | Slot 2+ only, not in template. Folded spelling `use(same)` — written explicitly there, where the flat wire left it absent |
| `text`, `image`, `content` | `key` | Meta/Option Field | Shows/enables field key | — |
| `text` | `title` | Title/Name | Disables field key | Term name if source is term; site name if `src:site` |
| `content` | `content` | Post Content/Term Description | Disables field key | Term description if source is term; **empty if `src:site`** (no site content analog) |
| `content` | `excerpt` | Post Excerpt | Disables field key | Empty under `src:site` (no site excerpt) |
| `image` | `featured` | Featured Image/Site Logo | Disables field key | Site logo (`custom_logo` theme mod) if `src:site` |

**`key` field key:**

| Applicable tags | Option name | Option label | Context | Notes |
|---|---|---|---|---|
| `text`, `image`, `content` | `key` | Meta/Option Field Key | Base / Slot 1 | Aligns with and substitutes for GB native `key` option name generated by `supports => ['meta']`, to avoid issues with GB's filtering and set our own order. Reads post/term meta normally, or a wp_options / ACF-options value under `src:site` (the field-type prefix tracks source scope — V10). **Required** when `use:key` (or the stripped key-mode default for text/image). |
| `text`, `image`, `content` | `N-key` | [N]: Meta/Option Field Key | Slot 2+ *(legacy wire)* | Pre-1.17.0 multislot spelling; a folded slot spells it `key(sku)` |

See [`datetime_*` section](tag-reference.md#datetime_single-and-datetime_range) for the
datetime-context label and keys.

### Link wrap group

Available on `text`, `title`, `datetime_single`, `datetime_range` (base, `term_` modifier, and
`try_` variants). Excluded: `content`, `permalink`, `image`. (`email`/`phone` have their own
`mailto:`/`tel:` link mechanism — `noLink` — NOT the `linkTo` family; see their sections.) The
`link` group renders after `format` in control order, after `source` in serialization order.

| Option name | Option label | Notes |
|---|---|---|
| `linkTo` | Link To | Link-destination selector. Values enumerated below. First value `none` is the canonical token, stripped at registration per default-strip strategy. |
| `linkKey` | URL Meta/Option Field Key | Meta or option field key whose value is the URL (post/term meta, or a wp_options / ACF-options key under `src:site`). Shown when `linkTo:key`. If empty, link wrap skipped (never blocks tag output). For `try_` tags, this field is read from the entity that produced the winning slot's output — no per-slot `linkKey`. |
| `newTab` | Open in new tab | Boolean presence-flag. Shown when `linkTo` not empty. Emits `target=”_blank” rel=”noopener noreferrer”` on the anchor. |

**`linkTo` values:**

| Value | Label | Resolves to |
|---|---|---|
| `none` *(unset)* | No Link | No wrap. Canonical default, stripped at registration. |
| `permalink` | Permalink | Entity permalink (`get_permalink` / `get_term_link`); under `src:site` → `home_url()` (the site permalink-analog — there is no separate `linkTo:site`). |
| `key` | URL Meta/Option Field | URL read from the meta/option field named in `linkKey` (allowlist-gated under `src:site`). |

Link wrap is applied **after fallback resolves** — fallback text is also wrapped if a link resolves.
On `try_` tags, the single `linkTo`/`linkKey`/`newTab` applies to the winning slot's entity (post or
term). `term_` modifier tags resolve entity type from dispatch path (term entity for base-source
dispatch; post entity for `src:ref` dispatch).

**`email`/`phone` are the exception — their link is NOT a `linkTo` option.** They do not participate
in the `linkTo`/`linkKey`/`newTab` family above (those wrap an *entity URL*). Their only link is the
`mailto:`/`tel:` for the address/number itself, **default-ON** and toggled by the inverted `noLink`
bare key (absent = wrap, present = plain text). Note the **opposite polarity**: `linkTo` defaults to
*no* wrap, whereas `noLink` defaults to *wrapped* — because the email's/phone's own address is the
only sensible link. The anchor is built directly (no class/target), not via `bws_wrap_with_link`.
`newTab` does not apply to `mailto:` (opening a mail client does not navigate). See [§Email
tag](tag-reference.md#email-tag) / [§Phone tag](tag-reference.md#phone-tag).

### Fallback group

The `fallback` option (the `fallback` group — global, last in both control and serialization
order).

| Applicable tags | Option type | Notes |
|---|---|---|
| `text`, `content`, `title`, `datetime_single`, `datetime_range` | Text field | |
| `image` | Media library selector → image ID (see `custom-image-controls.md`) | |
| `email` | Text field → a fallback **email address** | Validated with `is_email()` + wrapped like a real address (not arbitrary text). Fires only when no valid address resolves. |
| `phone` | Text field → a fallback **phone number** | Normalized + wrapped like a real number (length-gated, not arbitrary text). Fires only when no valid number resolves. |
| `permalink` | TBD — can be text field initially | Add page/post selector? |

---

## Option grouping (visual)

GB renders every option as a flat sibling in a 15px-gap flex column (see [`gb-constraints.md`
§Option controls are flat
siblings](gb-constraints.md#option-controls-are-flat-siblings-in-a-15px-gap-flex-column)), so a
panel reads as an undifferentiated stack. The plugin boxes the controls that describe one decision
into four visual groups — **`source`** (`src`, `ref`, `srcTermIn`, `sep`), **`field`** (`use`,
`key`, and the datetime key family), **`format`** (`as`, `rangeSep`, `format`, `timeSep`, the two
checkboxes, and `{{join}}`'s `mode`/`valueSep`), and **`link`** (`linkTo`, `linkKey`, `newTab`).
`limit` is deliberately absent: no tag registers one, and the entry kept through v1.17.0 against a
family that was expected to gain it was removed when that expectation was withdrawn ([ADR
0005](adr/0005-limits-are-stated-where-the-source-is-stated.md)).

The `field` split is the one place this departs from the serialization model, which serializes the
field read *inside* the source group: "where do I read from" and "what do I read" are the two
questions an author actually asks, and the folded-slot control has boxed them separately since
v1.17.0 — so a base tag's panel and a `{{join}}` slot's panel read alike.

Owners: [`bws_option_visual_groups()`](../includes/helpers/registration-helpers.php) states the map
(option name → group + lead flag) and rides the registration pass every BWS registration already
goes through, so GB core tags' identically-named options are never touched;
[`assets/js/option-group.js`](../assets/js/option-group.js) is the sole owner of the presentation,
wrapping each grouped control at filter priority 30. A group's **lead** stays boxed when it is the
group's only visible member; every other lone member renders bare. `link` deliberately has no lead
— the box appears only once a link is configured.

**Captions belong to controls, not to groups.** The wrapper renders no caption; a control that draws
its own inside the wrapper's box is what puts one there. So the source group reads `SOURCE` /
`SOURCE PATH` on the tags whose source is the chain control, and the same box sits bare on
`term_*`, `try_*`, `{{table}}` and `{{call}}`, whose source is a plain select. Accepted for v1.17.0
and tracked on [FW-64](future-work.md): rendering the caption in the wrapper means reading the lead
control's state, because the chain caption changes with chain length.

---

## Wrapper mechanics — invisible per-tag controls

Every filter registered on `generateblocks.editor.tagSpecificControls` (the folded-slot mount
migrator, the serialization-order normalizer, `option-group.js`, and any future one) shares one
element chain, and two structural rules govern every wrapper in that chain:

**A wrapper must pass `{ key: element.key }` to its Fragment (or wrapping element).** GB keys each
control element by its option name, and every later filter in the chain anchors on
`element.key`. A keyless wrap nulls that anchor and silently switches off every filter behind it at
the same priority — invisibly, because the control still renders, it just stops being reachable by
name. Priority-20 ties break on ENQUEUE order.

**A wrapper must ALSO stay node-less, and that is a SEPARATE property from the key (#68).** The
option-group boxes ([§Option grouping (visual)](#option-grouping-visual)) join by CSS on ADJACENT
SIBLINGS, so each grouped option's `.bws-optgroup` div has to BE what GB's modal column receives.
`createElement('div', {key: element.key}, element)` satisfies the key rule exactly while nesting the
box a level down — which flattens every panel on every tag, because the CSS run can no longer see
across the extra div. What decides it is POSITION: `option-group.js` registers at priority 30 and is
the LAST filter on the hook, so a wrap below 30 lands INSIDE the box and is free, and only a wrap
ABOVE 30 can break the run.

**Which templates are at risk, and why.** `{{join}}` and the six leading-option-less `try_`
templates (`try_text`, `try_content`, `try_title`, `try_permalink`, `try_email`, `try_phone`) have a
folded slot as their FIRST option, so the serialization-order normalizer and the fold's mount
migrator contend for the SAME element at the same priority. `try_image` and `try_datetime_*` lead
with `as` instead and are not in contention. This list changes if a new template's first option is
a folded slot — check it when adding one.

Both invariants are asserted by `node tools/test/editor-filter-chain-test.js`, which loads the
shipping files against a priority-honouring `wp.hooks` stub and sweeps EVERY anchor (not just the
contested one), because a wrap that is load-bearing for nobody today is exactly the one a
reachability-only test lets rot.

---

## Fold control (JS)

`assets/js/slot-fold-control.js` is the composite control that owns one folded slot value — it
parses the value on mount and rewrites it in full on every commit (the control is the editor-side
source of truth; it never patches a fragment). Three invariants govern it, each documented at the
point it is enforced in that file (its own top-of-file header, and the `seedSlot()` / `legacyKeys()`
docblocks):

- **Compaction is JS-only by construction.** It lives entirely in the control, and the renderer only
  ever sees ALREADY-compacted wire — so no PHP harness can reach it; `node
  tools/test/slot-fold-repeater-test.js` is the only coverage.
- **The seed and the read axis are CONTAINER-DEPENDENT.** A selecting container seeds `use(same)`
  (a fallback chain reads the same field off a different source); a combining container leaves the
  read UNSET, because choosing a field IS the configuration act there. A selecting container with no
  per-slot read axis at all (`try_permalink` and its siblings) seeds no read either.
- **The control hand-authors NO vocabulary.** Every enum, label and noun arrives on the PHP option
  definition, derived from the shipped builders; re-typing one in JS re-creates the drift that
  produced four copies of the text read enum and the image tag's `Return type:` / `Return image as:`
  label mismatch. This includes the LEGACY KEY SURFACE: the delete-on-commit list and the mount
  read both go through `window.bwsSlotFoldMigrate` (`flatAxes`) rather than a hand-kept list, because
  a hand-kept list once deleted a `try_` template's TAG-level `limit` and folded a
  `try_datetime_*`'s TAG-level `key` into slot 1. `inferIntent()` is advisory text only and must
  never regrow authority over what renders or what serializes.

**An absent chain is DISPLAYED as the root it spells**, not as an empty picker — `defaultRoot` on
slot 1, `same` on slot ≥2 — because a `SelectControl` whose value matches no row paints its first
row while believing nothing is selected, making that row unpickable and hiding `+ Add step`. This is
handled once, in `chainSteps()` (shared by both the base-tag source control and the fold control),
and is display-only: a commit that would merely restate the displayed default is stripped back to
absence, so looking at a slot never changes its wire.

---

## Fold registration & resolution

`bws_build_fold_slot_options()` (`includes/tags/base-shared.php`) is the `fold` config every
container's control reads. `bws_fold_slot_struct()` / `bws_fold_slot_chain_options()`
(`includes/helpers/slot-fold.php`) are the render seam. Four invariants span the two:

- **Registration flips WITH the resolver, one container at a time.** Modes do not mix: a tag is
  folded iff any CAPITAL slot key is present (`A`..`Z`); `bws_slot_ordinal()` is the single owner of
  that spelling, and the legacy `N-` sibling prefixes stay DIGITS. Folded keys registered against a
  flat resolver render nothing. (Also stated at `tools/test/fold-test-matrix.md`'s Era note, which
  pins it as an assertion.)
- **Carry-forward lives ONCE, in the seam.** Before it existed, the source chain a slot inherits was
  computed four times — join's loop, `try_`'s loop, and both preview walks — and the preview copies
  had already drifted from each other.
- **A selecting container is not one thing.** The `try_per_slot_use`/`try_per_slot_key` pair
  (`TagTemplateRegistry::try_slot_axes()`) names three read shapes a `try_` template can have: enum
  + picker, picker alone, or no read at all. Each resolves an absent read differently — a change
  tested only against `try_text` misses two-thirds of the family.
- **The seam REPORTS why it skipped.** `bws_fold_slot_chain_options()`'s `$skip_reason` out-param is
  `'read'` for an unconfigured slot (a normal in-progress state, stays silent) or `'chain'` for an
  inexpressible chain (flagged to the author: "slot N source not supported"). Deriving that
  distinction again in the preview would be a second copy of the skip rule.

---

## Fold migration — two paths, one rule set

The FW-56/57 fold migration has two independent triggers reading the SAME rules
(`bws_fold_from_flat()` in `slot-fold.php`), and they are complementary, not redundant:

- The **converter/scanner** (`includes/helpers/slot-fold-migrate.php`, registered as a
  `type:'option'` transform per multislot tag) reads `post_content` only, so a block widget is
  reachable ONLY on tag-modal mount — the scanner never sees it.
- The **editor mount migrator** (`assets/js/slot-fold-migrate.js`) is reachable ONLY when a tag
  string is loaded into the modal — a draft nobody opens is reachable ONLY by the scanner.

A divergence between the two does not surface as one path being flagged wrong — it surfaces as one
tag stored two different ways depending on which path found it first. That is why
`slot-fold-twin-test.php`'s twin block exists (PHP↔JS agreement on one shared corpus), and why the
mount side hand-lists NOTHING: container config, including `flatAxes`, arrives on the option
definition rather than being duplicated in JS.
