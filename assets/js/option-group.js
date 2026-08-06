/**
 * `bws-optgroup` — VISUAL GROUPING for a tag's separately-rendered option controls.
 *
 * GB fires `generateblocks.editor.tagSpecificControls` once per option and renders each
 * returned element as a flat sibling in the modal column. So a tag's controls arrive as a
 * uniform stack with no structure, while the folded-slot control — which owns MANY axes
 * inside ONE option value — can draw boxes around them. That asymmetry is what made a
 * base tag's panel read as a flat list beside a `{{join}}` slot's grouped one, even though
 * the two describe the same decisions.
 *
 * There is no cross-element seam in the filter (each call sees one element and nothing
 * about its neighbours), so the box is drawn PER MEMBER and joined by CSS: consecutive
 * members of the same group lose their inner edges and close the column's row-gap, so a
 * run of members reads as one box. That is what keeps each option a first-class control —
 * its own `show_if` reveal, its own key, its own filters — where a composite control that
 * swallowed several options would have to re-implement the reveal rule, i.e. own a second
 * copy of it.
 *
 * WHAT IT DOES NOT OWN: which options group together. That map is PHP-derived
 * (`bws_option_visual_groups()`), applied at registration to OUR options only, and reaches
 * here as `_group` / `_group_lead` on the option definition. A map hand-kept in JS would
 * wrap GB core tags' identically-named options too (`key`, `source`), and would be a
 * second place for the canonical control order to drift.
 *
 * THE LEAD, and why a lone member is usually bare. A group's LEAD member is boxed even
 * when it is alone (a source chain is a group whether or not it currently fans, and its
 * box carries the caption). Any other member alone gets NO box, because a border around a
 * single stock control is noise rather than structure — which is also how a "Link To: No
 * Link" tag ends up with no empty link box, and how a `try_` template's tag-level `limit`
 * stays a plain field.
 *
 * @package BWS_Dynamic_Tags
 * @since   1.17.0
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.hooks || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;

	// Class names, exported so the controls that draw their OWN box (the folded slot's
	// source/field groups) use the same look without re-declaring it. Two copies of the
	// box is what this file exists to remove: the base-tag chain control shipped with its
	// own untinted, absolutely-positioned variant of the slot control's box.
	var CLS = {
		// A box a control draws itself, around content it already owns.
		group: 'bws-group',
		// The caption strip at the top of a box.
		cap: 'bws-group__cap',
		// One member of a CSS-joined run.
		member: 'bws-optgroup',
		// A member that is boxed even when alone.
		lead: 'bws-optgroup--lead'
	};

	/**
	 * The visual box, as CSS text.
	 *
	 * Per-group join rules are GENERATED from the group names actually registered, so a
	 * new group needs no rule here — the attribute selector cannot say "same value as my
	 * sibling" on its own, which is the only reason this is generated rather than static.
	 *
	 * @param {string[]} names Group names present in the shipped map.
	 * @return {string} Stylesheet text.
	 */
	function buildCss( names ) {
		var box = '.' + CLS.group + ',.' + CLS.member;
		var css =
			box + '{' +
				'border:1px solid #e0e0e0;border-radius:2px;padding:12px;' +
				'background:rgba(0,0,0,0.02);}' +
			'.' + CLS.cap + '{font-size:11px;text-transform:uppercase;letter-spacing:.4px;' +
				'opacity:.65;margin-bottom:10px;display:block;}' +
			// ComboboxControl's `__suggestions-container` carries the border and padding
			// but declares NO background, so the tint shows through it while the `__input`
			// within paints itself white — one control, two fills. The fix belongs to the
			// BOX (anything tinted has this problem) rather than to the control that
			// happens to sit in one, which is where it lived while only the folded slot
			// had boxes.
			'.' + CLS.group + ' .components-combobox-control__suggestions-container,' +
			'.' + CLS.member + ' .components-combobox-control__suggestions-container' +
				'{background:#fff;}' +
			// The combobox's own label sits flush against whatever precedes it (the two
			// filter selects) and lives inside the shipped control's markup, out of reach
			// of a wrapper margin.
			'.' + CLS.group + ' .components-combobox-control .components-base-control__label,' +
			'.' + CLS.member + ' .components-combobox-control .components-base-control__label' +
				'{margin-top:12px;display:inline-block;}';

		names.forEach( function ( name ) {
			var sel = '.' + CLS.member + '[data-bws-group="' + name + '"]';
			// A CONTINUATION: drop the shared edge and pull up by the column's row-gap so
			// the two boxes meet. The 15px is GB's, not a guess —
			// `.gb-dynamic-tag-modal__content{display:flex;flex-direction:column;gap:15px}`
			// (docs/gb-constraints.md §Option controls are flat siblings…). A var, because
			// cancelling a number owned by someone else should be one declaration to find.
			css += sel + '+' + sel + '{' +
				'border-top:none;border-top-left-radius:0;border-top-right-radius:0;' +
				'margin-top:calc(-1 * var(--bws-optgroup-gap,15px));}';
			// ...and the member it continues from gives up its bottom edge.
			css += sel + ':has(+' + sel + '){' +
				'border-bottom:none;border-bottom-left-radius:0;border-bottom-right-radius:0;' +
				'padding-bottom:0;}';
			// LONE, and not the lead: no same-group sibling either side, so there is no
			// group to draw — just the control.
			css += sel + ':not(' + sel + '+' + sel + '):not(:has(+' + sel + ')):not(.' + CLS.lead + ')' +
				'{border:none;background:none;padding:0;}';
		} );

		return css;
	}

	/** The PHP-derived map, read at call time (the filter runs long after enqueue). */
	function groupOf( optionConfig ) {
		return ( optionConfig && optionConfig._group ) ? String( optionConfig._group ) : '';
	}

	function isLead( optionConfig ) {
		return !! ( optionConfig && optionConfig._group_lead );
	}

	// Group names seen so far. The BASE rules go in at load — the box is also drawn by
	// controls that own their content (a folded slot), and those tags register no grouped
	// OPTIONS at all, so a stylesheet that waited for one would leave `{{join}}` unstyled.
	// Only the per-group JOIN rules have to wait, since they name a group in the selector.
	var seen = {};

	function writeCss() {
		if ( 'undefined' === typeof document ) {
			return;
		}
		var styleEl = document.getElementById( 'bws-option-group-css' );
		if ( ! styleEl ) {
			styleEl = document.createElement( 'style' );
			styleEl.id = 'bws-option-group-css';
			document.head.appendChild( styleEl );
		}
		styleEl.textContent = buildCss( Object.keys( seen ) );
	}

	/** Add any group names this tag introduces, rewriting the stylesheet if they are new. */
	function ensureCss( allOptions ) {
		var added = false;
		Object.keys( allOptions ).forEach( function ( key ) {
			var name = groupOf( allOptions[ key ] );
			if ( name && ! seen[ name ] ) {
				seen[ name ] = true;
				added = true;
			}
		} );
		if ( added ) {
			writeCss();
		}
	}

	writeCss();

	// Priority 30: AFTER the conditional-options gate (10, which returns null for a hidden
	// option — a wrapped null would draw an empty box) and after the invisible controls
	// (20), whose anchors are `element.key` and must keep seeing the elements they expect.
	// The wrapper carries the key forward for the same reason — a keyless wrap silently
	// switches off every filter behind it (tools/test/editor-filter-chain-test.js).
	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/option-group',
		function ( element, allOptions ) {
			if ( ! element || ! allOptions ) {
				return element;
			}
			var cfg = allOptions[ element.key ];
			var name = groupOf( cfg );
			if ( ! name ) {
				return element;
			}
			ensureCss( allOptions );
			return el( 'div', {
				key: element.key,
				className: CLS.member + ( isLead( cfg ) ? ' ' + CLS.lead : '' ),
				'data-bws-group': name
			}, element );
		},
		30
	);

	window.bwsOptionGroup = {
		CLS: CLS,
		buildCss: buildCss
	};
}() );
