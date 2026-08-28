<?php

/**
 * Plugin Name: WC Custom Installments — extension example
 * Description: A runnable example of every extension point. Drop it in wp-content/plugins and activate it beside WC Custom Installments.
 * Version:     1.0.0
 * Author:      MeuMouse.com
 * License:     GPLv2 or later
 *
 * Nothing here edits the plugin. Every one of these is a supported surface, and
 * a copy patched by hand stops receiving updates.
 *
 * @package WC_Custom_Installments_Example
 */

defined('ABSPATH') || exit;

/**
 * Register on `init` at priority 1.
 *
 * The settings schema and the REST catalogue are built after that, and anything
 * registered later misses them.
 */
add_action( 'init', function() {
	if ( ! function_exists('wcci_register_feature') ) {
		return;
	}

	// A gate of your own. `pro` means something has to answer for it; `free`
	// is always available.
	wcci_register_feature( 'example_feature', array(
		'label' => __( 'Example capability', 'wcci-example' ),
		'tier'  => 'free',
	) );

	// A card in an existing tab, and a field inside it. Registering a field
	// with a `default` registers the default too — a field without one reads
	// as an empty string on a fresh install.
	wcci_register_settings_card( 'installments', array(
		'id'          => 'example-card',
		'title'       => __( 'Example settings', 'wcci-example' ),
		'description' => __( 'Added by the example extension.', 'wcci-example' ),
		'fields'      => array(),
	) );

	wcci_register_settings_field( 'example-card', array(
		'type'        => 'toggle',
		'key'         => 'example_setting',
		'label'       => __( 'Add a note under the price', 'wcci-example' ),
		'description' => __( 'Renders a line of your own inside the price group.', 'wcci-example' ),
		'default'     => 'no',
		'feature'     => 'example_feature',
	) );

	// A field in the product data panel. Saving is handled for you, gate
	// included.
	wcci_register_product_setting( array(
		'key'         => 'example_product_note',
		'type'        => 'text',
		'label'       => __( 'Note for this product', 'wcci-example' ),
		'description' => __( 'Shown instead of the store-wide note.', 'wcci-example' ),
		'feature'     => '',
		'quick_edit'  => false,
	) );
}, 1 );

/**
 * Put something inside the price group.
 *
 * `Append_Group` fires after the price itself and before the instalment line.
 * Every hook the plugin publishes also fires under its 5.x name, so a listener
 * written against the previous plugin keeps working.
 */
add_action( 'WC_Custom_Installments/Price/Append_Group', function( $product ) {
	if ( ! function_exists('wcci_is_feature_available') || ! wcci_is_feature_available('example_feature') ) {
		return;
	}

	$settings = \MeuMouse\WC_Custom_Installments\Admin\Settings\Repository::class;

	// is_active(), not get(): it answers false when the gating feature is
	// unavailable, so a value stored while licensed stops taking effect.
	if ( ! $settings::is_active('example_setting') ) {
		return;
	}

	$note = get_post_meta( $product->get_id(), 'example_product_note', true );

	printf(
		'<span class="example-note">%s</span>',
		esc_html( $note ?: __( 'Ships in 24 hours.', 'wcci-example' ) )
	);
} );

/**
 * Change the price the instalment plan is built from.
 *
 * This is the hook a dynamic-pricing plugin uses: whatever it returns is what
 * the customer will be charged, so it is what the plan should be built on.
 */
add_filter( 'WC_Custom_Installments/Price/Set_Values_Price', function( $price, $product ) {
	return $price;
}, 10, 2 );

/**
 * Add a rate the storefront runtime should know about.
 *
 * The runtime rebuilds the plan in the browser when the price changes, so
 * anything the server used has to reach it too — otherwise the number moves to
 * somewhere the server would not have put it.
 */
add_filter( 'WC_Custom_Installments/Assets/Frontend_Params', function( $params ) {
	$params['example_flag'] = true;

	return $params;
} );

/**
 * Answer the feature gate.
 *
 * This is how a licensing add-on unlocks paid capabilities — one listener, for
 * all of them. Never return false over a true: something else may have granted
 * the feature, and lowering it takes away what they gave.
 */
add_filter( 'WC_Custom_Installments/Features/Is_Available', function( $available, $feature_id ) {
	if ( $available ) {
		return true;
	}

	// return my_plugin_is_licensed();
	return $available;
}, 10, 2 );

/**
 * Correct a theme that fights the price group.
 *
 * Scope every rule to the theme that needs it. A correction that leaks onto a
 * theme it was not written for is a bug reported as "the plugin broke my
 * layout".
 */
add_filter( 'WC_Custom_Installments/Integrations/Theme_Rules', function( $rules ) {
	$rules['my-theme'] = array(
		'detect' => static function() {
			return function_exists('my_theme_setup');
		},
		'css' => '.my-theme .woo-custom-installments-group{display:block;}',
	);

	return $rules;
} );
