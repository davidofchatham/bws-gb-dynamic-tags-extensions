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
[`docs/tag-reference.md`](tag-reference.md) — this doc owns *mechanism*, not the option catalog. **The
line is the OPTION KEY:** a row with a key is catalog and belongs there; a row describing a control,
or a POSITION INSIDE A VALUE (a chain step's slug, its arg, its `limit(N)` token), is mechanism and
belongs here. Stated because the first cut of this doc took the catalog with it and had to be
handed back.
GB-imposed facts stay in [`docs/gb-constraints.md`](gb-constraints.md). Load-bearing invariants
scoped to one function/class live as PHPDoc on that function/class; this doc is for invariants that
cross several files or several tag families. The field-discovery **mechanism** is NOT here — it is decoupled to
`field-selector.md` (own ship/lifecycle); its `bws-field-combo` control + REST endpoint own their
invariants via the spec issue → PHPDoc on ship. What that mechanism *emits* is a different thing:
[§Field configuration note](#field-configuration-note) is here, beside the chain-step control that
renders it.

---

## Source and field control mechanics

What the editor RENDERS for the shared option groups: which component owns a group, how its controls
behave per slot, and what a chain step's controls edit. The option **catalog** those controls
serialize — names, labels, help text, values, conditionals — is [§Shared option
groups](tag-reference.md#shared-option-groups) in `tag-reference.md`. The line between the two is the
one [§Chain step controls](#chain-step-controls) already draws: a row with an option KEY is catalog,
a row describing a control or a POSITION INSIDE A VALUE is mechanism.

### Source control — per-slot UI and serialization

In a **multislot** container (`try_*`, `{{join}}`, `{{table}}`) the same source axis lives inside the
folded slot value as a chain — `src(refs,office;terms,category)`, [§Folded slot
wire](tag-reference.md#folded-slot-wire-multislot-containers) — so the `N-`prefixed keys the catalog
lists for slot 2+ are **legacy wire** (registered through v1.16.x, still read by the renderer, never
written).

**`src` option values — per-slot UI/serialization mechanics** (labels, the slot-2+ `same`/`current`
distinction, the editor-preview context segment each value produces). For **what each value
resolves to** (and implementation status), see [§`src` option
values](tag-reference.md#src-option-values).

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

**Control composition.** `srcTermIn` renders as the combined `bws-term-hop` control (CheckboxControl
+ ComboboxControl) — one key, two widgets, the composite pattern this doc's preamble names. It
replaced the prior `srcTerm` + `tax` pair in v1.6.0: the slug encodes both "term step on" and which
taxonomy, so empty/unset is disabled and a slug is enabled-with-that-taxonomy. `sep` is alone in the
source group on a `try_` tag, so it renders **unboxed** there: the attempts are that tag's source and
draw their own boxes.

**The tag-level `limit` is a LIVE READ with a RETIRED CONTROL.** No control on any chain-authoring
tag registers it (v1.17.0,
[#62](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/62)): a limit is stated
where the source is stated, and a chain states its source as steps, so the limit rides the fanning
step. It is **unregistered rather than gated on flat wire** — the mount migrator rewrites a flat tag
to a chain before the panel paints, so a flat-only predicate would be unreachable. Removing a control
never removes an option ([ADR 0004](adr/0004-serialized-tag-string-human-readable.md); GB seeds state
from the tag string, not the registry), so unmigrated flat wire and hand-edited wire keep rendering —
the key's schema is in the catalog for that reason. A flat-select family, whose source is a single
step, is where the key belongs; nothing offers it today.

**A label with no control answers to nothing.** The catalog's historical *Result Limit* was the only
documented limit label while the per-step control had none, so a draft "Limit per source" sat against
it for the whole of 1.17.0 development with nothing to disagree with it
([#95](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/95)). Hence the
strikethrough in the catalog rather than a quiet deletion, and hence the table below.

### Chain step controls

**These rows are POSITIONS INSIDE THE CHAIN VALUE, not option keys.** Every one of them edits part
of a single `src` (or folded slot) value — `src:refs,office,limit(1);terms,category` — so there is
no `limit` option, no `taxonomy` option and no per-step `ref` option to look for in the catalog.
The one `limit` OPTION KEY the plugin has is the tag-level one above, which is a different quantity
with a retired control.

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

**Limit results is SUPPRESSED on a collapsing tag** — `content` / `permalink` / `image` and their
`try_` slots, the templates whose `takes_first_usable` capability makes the render ignore every
step limit ([tag-reference.md §Collapsing tags](tag-reference.md#collapsing-tags-first-usable-result)).
Suppressed, not reworded: rewording asks the author to read an explanation of why a visible control
does nothing. The mechanism is an **explicit conditional in the shared step renderer** reading
`takesFirstUsable` off the fold config (threaded from the template record through
`bws_build_src_chain_option()` / `bws_build_fold_slot_options()`), never an omitted `limitOption`
vocabulary — an absent config still renders an unlabelled text box, and an explicit conditional
means a template gaining or losing the capability moves the control with no second list to
remember. Stored wire is untouched: a saved `limit(N)` survives the round-trip and applies again if
the tag type changes. Pinned by `control-order-test.php` §8 (which surfaces carry the flag) and
`editor-filter-chain-test.js` (that the flag removes the control).

The Limit results control carried a draft label of **Limit per source** for most of 1.17.0's
development and was corrected before release
([#95](https://github.com/davidofchatham/bws-gb-dynamic-tags-extensions/issues/95)) — it sat three
rows below a control labelled *Source* meaning something else, named sources while bounding results,
and stated the per-input rule everywhere except the label. **No author ever saw it, so it is not a
rename**: nothing shipped under it, no CHANGELOG entry describes it, and it has no row in
[`deprecated-tags-options.md`](deprecated-tags-options.md). Recorded here only because what let it
stand that long was the absence of this table. The option key did not move and no stored wire was
rewritten.

#### Group-end fanning advisory

On the three collapsing tags (`content` / `permalink` / `image` — the `takes_first_usable`
templates), one display-only line closes the source group whenever the tag's chain actually fans:

> *This source can match more than one item. This tag shows the first one that has a value.*

It exists because the field configuration note structurally cannot carry this fact: a note is
attached to a FIELD, while fanning is a property of the CHAIN — a `terms` step has no field key for
a note to attach to, and a chain can fan with no multi-value field involved. One advisory at the
group's end covers both holes and the ordinary case alike, once per chain rather than once per
step. The wording states what the author observes and deliberately does not name the rule that
decides fanning — that axis is owned at the predicate (`bws_fold_chain_fanning_steps()`), reached
here through its grammar twin, the same one the Limit results help forms and the migrator's stamp
use.

Mechanics: option `srcFanNote`, type `bws-fanning-advisory`, registered at the end of the source
group with a row in `bws_option_visual_groups()` (an ungrouped control spliced between grouped ones
splits the box). The copy arrives on the option definition (`help`) — the control hand-authors no
vocabulary. The control writes nothing, the option is never serialized, and the filter returns
`null` on a non-fanning chain (the conditional-options pattern) so the group wrapper — which boxes
any non-null element — never draws an empty member. Pinned by `editor-filter-chain-test.js`
(conditional both ways, once-per-chain, composition with the group wrapper) and
`control-order-test.php` §1 (contiguity).

#### Field configuration note

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

**On a collapsing tag the clause is dropped and the rest of the note stands.** Its prediction —
all entries becoming results — is false on a tag that renders one result, while the multi-value
fact above it stays true and useful. The envelope is tag-blind (no tag identity reaches the REST
route), so PHP MARKS the segment (`consequence => true` beside `emph`) and the note renderer drops
marked segments where the fold config carries `takesFirstUsable`. The note says nothing about
collapsing itself — that is a fact about the CHAIN, carried by the group-end fanning advisory, not
by a note attached to a field.

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

The surface that invites the question is the [Field group](tag-reference.md#field-group)'s own `key`,
where the same picker offers the same relationship fields — and the note is right to stay away,
because **relating is a source step, not a target field**. A relationship value is a list of post ids
rather than a datum, so `{{text use:key|key:related_staff}}` renders EMPTY where `{{title
src:refs,related_staff}}` renders the related posts (verified on the testbed, `related_staff`
holding two ids). Relocating this note there would explain a limit on a read that produces nothing;
what that surface would want, if anything, is an advisory pointing at the source step.

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
`fold-migration-test.php` §M7 exists — the twin block that runs `fold-migration-driver.js` under
`node` and holds both paths to one answer on the shared `fold-migration-corpus.json` (the *grammar*
twin over `slot-fold-corpus.json` is a different harness, `slot-fold-twin-test.php`) — and why the
mount side hand-lists NOTHING: container config, including `flatAxes`, arrives on the option
definition rather than being duplicated in JS.


### Why the image composite does NOT migrate on mount (1.17.1)

The `bws-as-size` composite owns the `as` token end to end, so completing a legacy bare `as:url` to
`as:url,full` on mount looks like the same two-path shape as above, on a key the editor genuinely
can write. It was built that way and backed out. The reason is the mechanism this section is for:

**A control can only make decisions about what the filter hands it, and this one's correctness
depends on a key GB keeps private.** `tagSpecificControls` receives `{ state: extraTagParams,
setState }`; `size` is destructured into GB-private `imageSize` before `extraTagParams` exists
([`gb-constraints.md` §Reserved keys](gb-constraints.md#reserved-keys-are-destructured-into-gb-private-state-and-re-serialized-even-when-unsupported)).
So a legacy split tag (`as:url` plus a separate `size:medium`) and a plain size-less `as:url` are
THE SAME OBJECT in the editor. Completing to `url,full` is right for one and silently pins the
other's render to full. The converter distinguishes them by reading the raw tag string, and orders
its two entries so the fold runs first; a mount effect has nothing to order against.

The generalisation, which outlives this control: **an invisible or on-mount write is licensed by
the value being legacy, and "legacy" has to be decidable from what the filter passes.** Where it is
not, the write belongs on the converter, whatever the option's ownership. The fold control's
`stripDefaultRoot` holds the neighbouring rule for the same reason — looking at a tag must not
change it.

Standing consequence, not introduced by this: the composite writes the whole token on any change,
so touching a control on a legacy split tag writes `as:url,full` and the tag renders at full size
from that moment (a size inside `as` wins over a separate `size:` in the read seam). The `size:`
token itself survives in the string, GB re-emitting it from private state, until the converter
folds it. Running the Migration Tool first is the fix, and `tag-reference.md` §`as` serialization
opt-out says so.
