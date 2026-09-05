# Site option VALUES are withheld from untrusted users under REST

**Status:** accepted (v1.19.2). Hardened against [`design-history/dynamic-data-trust-predicate.md`](../design-history/dynamic-data-trust-predicate.md), the build record of the FW-125 pass that measured the divergence this closes.

`src:site` option reads carry two independent gates. [ADR 0001](0001-site-option-read-allowlist.md) owns the first — **which keys** may ever be read, via a GB-Pro-parity allowlist. This ADR owns the second — **which user** may be shown a value, and when. Both apply at every option-read site; neither substitutes for the other.

## The rule

Under a REST request, for a user who can `edit_posts` but may not author dynamic data, a site option read resolves to empty unless the key is one GB Pro would still disclose to that user. Enforced at `bws_site_option_user_gated()` ([`includes/helpers/field-helpers.php`](../../includes/helpers/field-helpers.php)), consulted by both option-read paths: `bws_site_read_option()` (key-mode plus site `linkTo:key`) and the datetime `'option'` branch of `bws_read_field()`, which reads an ACF field rather than a wp_options row and therefore restates both gates instead of inheriting them.

The per-user question is asked through `bws_gb_option_allowed_for_current_user()` ([`includes/helpers/gb-trust-boundary.php`](../../includes/helpers/gb-trust-boundary.php)) — the one place this plugin asks GenerateBlocks anything about the current user. That seam calls GB Pro's own `is_option_allowed_for_current_user()` where it exists, so the *content* of the carve-out is Pro's and not ours: the shared allowlist minus every registered ACF options-page key, with Pro's `generateblocks_dynamic_tags_allowed_options_for_current_user` filter as the way back for a specific key.

## Why the two axes are independent

ADR 0001's allowlist is stricter than GB's blocklist on *which* keys, and completely silent on *who*. Those do not trade off. An allowlisted key is still a cross-boundary read when an untrusted user previews it in the editor, and a key nobody may read stays unreadable however trusted the viewer. Reasoning that "we are already stricter than GB Pro on keys, so we need no user gate" is the specific error this ADR exists to prevent — it was the working assumption in FW-125's first pass, and it was wrong.

## Why the scope is REST + `edit_posts`, and not wider

This mirrors GB Pro 2.7's own conditional exactly, including both halves of its scope, and Pro's reasoning is the reasoning here.

- **Only `edit_posts`+ users reach REST routes that render UNSAVED content** — the dynamic-tag preview endpoint and core's block-renderer. That is the disclosure being closed: a value the author never saved, resolved into a preview for someone who may not author dynamic data.
- **Anonymous REST is deliberately left alone.** It only ever renders content that is already saved and already public on the front end. Blanking it would break headless parity for a site reading its own published pages over the API, and would protect nothing.
- **Front-end rendering is untouched.** A published page's saved output is public by definition; the taint model, not this gate, is what governs whether an untrusted user's saved content resolves at all (see [`gb-constraints.md`](../gb-constraints.md) §Dynamic data is suppressed by POST SOURCE).

## Consequences

- An untrusted `edit_posts` user's editor preview of `{{text src:site|key:<acf_options_field>}}` now resolves to the tag's configured fallback rather than the value. Core options in the parity seed — `blogname`, `home`, `siteurl`, `blogdescription`, `time_format`, `user_count` — still resolve for them, because Pro keeps those readable and being gratuitously stricter than Pro turns a security gate into a support ticket.
- The gate returns `''` and lets the existing fallback path apply, which is the same shape an allowlist miss already had, and the same shape Pro produces (it returns `''` into `Callbacks::output()`, where the tag's `default` applies). No new branch, no new value shape, and nothing for a caller to special-case.
- A blanked preview carries **no explanation of its own**. GB's editor already tells such a user *"Your account can't add or edit dynamic tags"*, which is the honest place for that message; a per-read reason code of ours would need surfacing somewhere and is not built. Tracked as future work rather than owed here.
- Nothing about the *front end* changes, and nothing about which keys are readable changes. A reviewer looking for a behavior delta should look at REST previews only.

## What this is not

Not a re-implementation of GB's trust model. The predicate is one filterable call and the carve-out is one public method; consuming them means asking and honoring the answer at a surface of ours. Where GB Pro is too old to have the method, the seam mirrors Pro's rule and records the version it was copied from — that copy is the only piece here that can drift, and `tools/test/gb-trust-boundary-test.php` pins it against the fixture environment's recorded GB Pro version.
