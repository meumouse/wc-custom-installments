/**
 * The plugin's fields on WooCommerce's product screens.
 *
 * WooCommerce owns these screens, so this stays a small jQuery script beside
 * its own rather than a Vue app (AGENTS.md §7). It does two things:
 *
 * 1. In the product data panel, applies the display rules each field declares
 *    (`depends_on`, `visible`, `discount`; see Admin\Product_Settings), which
 *    the panel prints as JSON on its wrapper. PHP already rendered the starting
 *    state, so without this script the panel is correct, just not live.
 * 2. In the products list, copies a row's stored values into the quick edit
 *    boxes when it opens, the way WooCommerce's quick-edit.js does for its own
 *    fields. WordPress builds that form once for the whole list; unfilled, its
 *    boxes would open unticked and saving would switch the options off.
 *
 * No user-facing strings live here: every message is printed by PHP.
 *
 * @since 6.0.0
 */
/* global jQuery */
( function ( $ ) {
	'use strict';

	/**
	 * The free plugin's own "hide the discount" option. A 5.x meta key, which
	 * cannot change (AGENTS.md §5).
	 */
	var DISABLE_DISCOUNT = '__disable_discount_main_price';

	/**
	 * Quick edit field that tells the save the boxes were filled.
	 */
	var QUICK_EDIT_LOADED = 'wc_custom_installments_quick_edit';

	/**
	 * Find a field's control by its key.
	 *
	 * getElementById rather than a selector: keys are meta keys, and nothing
	 * guarantees one is free of characters a selector would read as syntax.
	 *
	 * @param {string} key Field key.
	 * @returns {jQuery} The control, possibly empty.
	 */
	function control( key ) {
		return $( document.getElementById( key ) );
	}

	/**
	 * A field's current value, with a checkbox read as `yes` or `no`.
	 *
	 * @param {string} key Field key.
	 * @returns {string} The value.
	 */
	function valueOf( key ) {
		var $control = control( key );

		if ( ! $control.length ) {
			return '';
		}

		if ( $control.is( ':checkbox' ) ) {
			return $control.is( ':checked' ) ? 'yes' : 'no';
		}

		return String( $control.val() || '' );
	}

	/**
	 * Whether a field should be showing.
	 *
	 * A field hidden by its own rule hides whatever depends on it as well.
	 *
	 * @param {string} key Field key.
	 * @param {Object} rules Display rules by field key.
	 * @param {number} depth Guard against rules that point at each other.
	 * @returns {boolean} Whether it is shown.
	 */
	function isShown( key, rules, depth ) {
		var rule = rules[ key ];

		if ( ! rule ) {
			return true;
		}

		if ( false === rule.visible ) {
			return false;
		}

		if ( ! rule.depends_on || depth > 5 ) {
			return true;
		}

		return Object.keys( rule.depends_on ).every( function ( other ) {
			var expected = [].concat( rule.depends_on[ other ] ).map( String );

			return -1 !== expected.indexOf( valueOf( other ) ) && isShown( other, rules, depth + 1 );
		} );
	}

	/**
	 * Apply every rule to the panel.
	 *
	 * @param {jQuery} $panel The plugin's options group.
	 * @param {Object} rules Display rules by field key.
	 * @returns {void}
	 */
	function refresh( $panel, rules ) {
		var discountHidden = 'yes' === valueOf( DISABLE_DISCOUNT );

		Object.keys( rules ).forEach( function ( key ) {
			var $control = control( key );
			var shown = isShown( key, rules, 0 );

			if ( ! $control.length ) {
				return;
			}

			$control.closest( '.form-field' ).toggleClass( 'hidden', ! shown );

			if ( ! rules[ key ].discount ) {
				return;
			}

			$panel
				.find( '.wc-custom-installments-discount-warning' )
				.filter( function () {
					return $( this ).attr( 'data-field' ) === key;
				} )
				.toggleClass( 'hidden', ! ( shown && discountHidden && 'yes' === valueOf( key ) ) );
		} );
	}

	/**
	 * Wire the product data panel.
	 *
	 * @returns {void}
	 */
	function initPanel() {
		$( '.wc-custom-installments-options' ).each( function () {
			var $panel = $( this );
			var rules = {};

			try {
				rules = JSON.parse( $panel.attr( 'data-wcci-fields' ) || '{}' ) || {};
			} catch ( error ) {
				return;
			}

			if ( Array.isArray( rules ) || ! Object.keys( rules ).length ) {
				return;
			}

			$panel.on( 'change', 'input, select', function () {
				refresh( $panel, rules );
			} );

			refresh( $panel, rules );
		} );
	}

	/**
	 * Fill the quick edit boxes from the row being edited.
	 *
	 * Runs on the same click as WordPress's inline edit. Whichever handler goes
	 * first, both the form template and the copy WordPress makes of it match
	 * `.inline-edit-row`, and a cloned checkbox keeps its checked state, so the
	 * open form ends up with the row's values either way.
	 *
	 * @returns {void}
	 */
	function initQuickEdit() {
		$( '#the-list' ).on( 'click', '.editinline', function () {
			var $data = $( this ).closest( 'tr' ).find( '.wc-custom-installments-inline-data' );
			var $forms = $( '.inline-edit-row' );
			var $loaded = $forms.find( 'input[type="hidden"]' ).filter( function () {
				return this.name === QUICK_EDIT_LOADED;
			} );

			if ( ! $data.length ) {
				$loaded.val( '' );

				return;
			}

			$data.children( '[data-key]' ).each( function () {
				var key = $( this ).attr( 'data-key' );
				var checked = 'yes' === $.trim( $( this ).text() );

				$forms
					.find( 'input[type="checkbox"]' )
					.filter( function () {
						return this.name === key;
					} )
					.prop( 'checked', checked );
			} );

			$loaded.val( '1' );
		} );
	}

	$( function () {
		initPanel();
		initQuickEdit();
	} );
}( jQuery ) );
