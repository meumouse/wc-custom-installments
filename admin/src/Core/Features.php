<?php

namespace MeuMouse\WC_Custom_Installments\Core;

defined('ABSPATH') || exit;

/**
 * Registry of gated features and the single place that decides availability.
 *
 * Every capability the plugin offers is registered here with a tier. A `free`
 * feature is always available; a `pro` feature is available only when something
 * answers the availability filter — in practice, the Pro add-on reporting a
 * valid licence.
 *
 * Routing every gate through one function is what makes the free/Pro split
 * safe: the settings schema derives the locked state from it, the repository
 * refuses to persist a locked field from it, and the engine consults it before
 * applying a behaviour. There is no second place to keep in step, and no way to
 * unlock a feature by editing markup in the browser.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Core
 * @author MeuMouse.com
 */
class Features {

	/**
	 * Tier of a feature included in the free plugin.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TIER_FREE = 'free';

	/**
	 * Tier of a feature that requires the Pro add-on.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TIER_PRO = 'pro';

	/**
	 * Registered features, keyed by id.
	 *
	 * @since 1.0.0
	 * @var array<string,array<string,mixed>>
	 */
	private static $features = array();

	/**
	 * Availability cache for the current request.
	 *
	 * @since 1.0.0
	 * @var array<string,bool>
	 */
	private static $availability = array();

	/**
	 * Whether the built-in definitions have been registered.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private static $bootstrapped = false;


	/**
	 * Register the built-in feature definitions, once.
	 *
	 * These live here rather than in a discovered class on purpose. An
	 * unregistered id resolves to "available", so a feature that had not been
	 * registered yet when the schema was built would silently unlock. Doing it
	 * lazily makes that ordering impossible to get wrong.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function bootstrap() {
		if ( self::$bootstrapped ) {
			return;
		}

		self::$bootstrapped = true;

		$definitions = array(
			'fee_per_installment' => __( 'Interest per installment', 'wc-custom-installments' ),
			'gateway_discounts' => __( 'Discounts per payment method', 'wc-custom-installments' ),
			'gateway_interests' => __( 'Interest per payment method', 'wc-custom-installments' ),
			'quantity_discounts' => __( 'Discounts per quantity', 'wc-custom-installments' ),
			'ticket_discount' => __( 'Bank slip discount', 'wc-custom-installments' ),
			'pix_economy_badge' => __( 'Pix savings badge', 'wc-custom-installments' ),
			'sale_badge' => __( 'Sale badge', 'wc-custom-installments' ),
			'custom_placeholders' => __( 'Custom installment text templates', 'wc-custom-installments' ),
			'variable_price_range' => __( 'Variable product price range control', 'wc-custom-installments' ),
			'seo_schema' => __( 'Discounted price in structured data', 'wc-custom-installments' ),
			'advanced_design' => __( 'Advanced design controls', 'wc-custom-installments' ),
			'product_overrides' => __( 'Per-product discount overrides', 'wc-custom-installments' ),
			'elementor_widgets_pro' => __( 'Additional Elementor widgets', 'wc-custom-installments' ),
		);

		foreach ( $definitions as $id => $label ) {
			self::register( $id, array(
				'label' => $label,
				'tier' => self::TIER_PRO,
			) );
		}

		/**
		 * Fires once the built-in features are registered.
		 *
		 * An add-on registering features of its own hooks here, so its
		 * definitions exist before any schema is built.
		 *
		 * @since 1.0.0
		 */
		do_action('WC_Custom_Installments/Features/Register');
	}


	/**
	 * Register a feature.
	 *
	 * @since 1.0.0
	 * @param string $id | Feature identifier
	 * @param array $args | Feature arguments: label, description, tier
	 * @return void
	 */
	public static function register( $id, $args = array() ) {
		$id = sanitize_key( $id );

		if ( '' === $id ) {
			return;
		}

		$args = wp_parse_args( $args, array(
			'label'       => $id,
			'description' => '',
			'tier'        => self::TIER_FREE,
		) );

		$args['id'] = $id;
		$args['tier'] = ( self::TIER_PRO === $args['tier'] ) ? self::TIER_PRO : self::TIER_FREE;

		self::$features[ $id ] = $args;

		unset( self::$availability[ $id ] );
	}


	/**
	 * Whether a feature may run on this site.
	 *
	 * An unregistered id resolves to true: a feature nobody gated is not a
	 * feature anybody should be denied.
	 *
	 * @since 1.0.0
	 * @param string $id | Feature identifier
	 * @return bool
	 */
	public static function is_available( $id ) {
		self::bootstrap();

		$id = sanitize_key( $id );

		if ( '' === $id || ! isset( self::$features[ $id ] ) ) {
			return true;
		}

		if ( isset( self::$availability[ $id ] ) ) {
			return self::$availability[ $id ];
		}

		$available = ( self::TIER_FREE === self::$features[ $id ]['tier'] );

		if ( ! $available ) {
			/**
			 * Filter whether a Pro feature is available on this site.
			 *
			 * The Pro add-on answers this with its licence status. Nothing else
			 * should: a filter that returns true unconditionally unlocks paid
			 * behaviour, which is the site owner's decision to make, not a
			 * theme's.
			 *
			 * @since 1.0.0
			 * @param bool $available | Whether the feature is available
			 * @param string $id | Feature identifier
			 * @param array $feature | The registered feature definition
			 * @return bool
			 */
			$available = (bool) apply_filters( 'WC_Custom_Installments/Features/Is_Available', false, $id, self::$features[ $id ] );
		}

		self::$availability[ $id ] = $available;

		return $available;
	}


	/**
	 * Whether a feature belongs to the Pro tier.
	 *
	 * @since 1.0.0
	 * @param string $id | Feature identifier
	 * @return bool
	 */
	public static function is_pro( $id ) {
		self::bootstrap();

		$id = sanitize_key( $id );

		return isset( self::$features[ $id ] ) && self::TIER_PRO === self::$features[ $id ]['tier'];
	}


	/**
	 * Get one registered feature.
	 *
	 * @since 1.0.0
	 * @param string $id | Feature identifier
	 * @return array<string,mixed>|null
	 */
	public static function get( $id ) {
		self::bootstrap();

		$id = sanitize_key( $id );

		return isset( self::$features[ $id ] ) ? self::$features[ $id ] : null;
	}


	/**
	 * Get every registered feature, with its resolved availability.
	 *
	 * This is what the settings bootstrap hands to the browser, so the UI can
	 * lock a field without knowing anything about licences.
	 *
	 * @since 1.0.0
	 * @return array<string,array<string,mixed>>
	 */
	public static function all() {
		self::bootstrap();

		$features = array();

		foreach ( self::$features as $id => $feature ) {
			$feature['available'] = self::is_available( $id );
			$features[ $id ] = $feature;
		}

		return $features;
	}


	/**
	 * Forget the resolved availability of every feature.
	 *
	 * Called after a licence changes, so the next read reflects it.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function flush() {
		self::$availability = array();
	}
}
