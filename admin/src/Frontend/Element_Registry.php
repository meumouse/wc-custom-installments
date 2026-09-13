<?php

namespace MeuMouse\WC_Custom_Installments\Frontend;

defined('ABSPATH') || exit;

/**
 * The elements of the storefront price group, and how each one is styled.
 *
 * The 5.x plugin kept this structure inside the settings default, which meant a
 * new element could not be added without editing the option every store had
 * already written to. Here it is a registry: the defaults live in code, the
 * stored settings only carry what the store owner changed, and an add-on can
 * append an element through a filter.
 *
 * The array shape is the 5.x one, so migrated `elements_design` data drops
 * straight in.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Frontend
 * @author MeuMouse.com
 */
class Element_Registry {

	/**
	 * Cached registry for the current request.
	 *
	 * @since 6.0.0
	 * @var array<string,array<string,mixed>>|null
	 */
	private static $elements = null;


	/**
	 * Get every registered element.
	 *
	 * @since 6.0.0
	 * @return array<string,array<string,mixed>>
	 */
	public static function all() {
		if ( null !== self::$elements ) {
			return self::$elements;
		}

		$elements = array(
			'price' => self::element(
				'wci_product_price',
				'.woo-custom-installments-group-main-price',
				__( 'Product price', 'wc-custom-installments' ),
				1,
				array( 'class' => '', 'image' => '' ),
				array( 'font_size' => '1.225', 'font_weight' => '600', 'font_color' => '#343A40' )
			),
			'installments' => self::element(
				'wci_best_installments',
				'.woo-custom-installments-card-container',
				__( 'Best installment', 'wc-custom-installments' ),
				2,
				array( 'class' => 'fa-regular fa-credit-card', 'image' => '' ),
				array( 'font_weight' => '400', 'margin' => array( 'top' => '0.5', 'bottom' => '1' ) )
			),
			'discount_pix' => self::element(
				'wci_discount_pix',
				'.woo-custom-installments-offer',
				__( 'Pix discount', 'wc-custom-installments' ),
				3,
				array( 'class' => 'fa-brands fa-pix', 'image' => '' ),
				array(
					'font_weight' => '500',
					'font_color' => 'rgba(38, 171, 91, 1)',
					'background_color' => 'rgba(34, 197, 94, 0.15)',
					'margin' => array( 'top' => '0.5', 'bottom' => '1' ),
					'padding' => array( 'top' => '0.65', 'right' => '0.85', 'bottom' => '0.65', 'left' => '0.85' ),
					'border_radius' => array( 'top' => '0.3', 'right' => '0.3', 'bottom' => '0.3', 'left' => '0.3' ),
				)
			),
			'pix_economy' => self::element(
				'wci_pix_economy',
				'.woo-custom-installments-economy-pix-badge',
				__( 'Pix savings', 'wc-custom-installments' ),
				4,
				array( 'class' => 'fa-solid fa-circle-info', 'image' => '' ),
				array(
					'font_weight' => '500',
					'font_color' => '#fff',
					'background_color' => '#22c55e',
					'margin' => array( 'bottom' => '1' ),
					'padding' => array( 'top' => '0.65', 'right' => '0.85', 'bottom' => '0.65', 'left' => '0.85' ),
					'border_radius' => array( 'top' => '0.3', 'right' => '0.3', 'bottom' => '0.3', 'left' => '0.3' ),
				)
			),
			'discount_slip_bank' => self::element(
				'wci_discount_slip_bank',
				'.woo-custom-installments-ticket-discount',
				__( 'Bank slip discount', 'wc-custom-installments' ),
				5,
				array( 'class' => 'fa-solid fa-barcode', 'image' => '' ),
				array(
					'font_weight' => '500',
					'font_color' => 'rgba(163, 132, 41, 1)',
					'background_color' => 'rgba(255, 186, 8, 0.15)',
					'margin' => array( 'bottom' => '1' ),
					'padding' => array( 'top' => '0.65', 'right' => '0.85', 'bottom' => '0.65', 'left' => '0.85' ),
					'border_radius' => array( 'top' => '0.3', 'right' => '0.3', 'bottom' => '0.3', 'left' => '0.3' ),
				)
			),
		);

		/**
		 * Filter the elements of the price group.
		 *
		 * @since 6.0.0
		 * @param array<string,array<string,mixed>> $elements | Registered elements
		 * @return array<string,array<string,mixed>>
		 */
		self::$elements = (array) apply_filters( 'WC_Custom_Installments/Frontend/Elements', $elements );

		return self::$elements;
	}


	/**
	 * Get one element, merged with whatever the store owner changed.
	 *
	 * Always returns a complete element, so a caller never has to guard against
	 * a missing key on a store that saved a partial structure.
	 *
	 * @since 6.0.0
	 * @param string $key | Element key
	 * @param array<string,mixed> $stored | The stored `elements_design` array
	 * @return array<string,mixed>
	 */
	public static function get( $key, $stored = array() ) {
		$elements = self::all();
		$element = $elements[ $key ] ?? self::element( 'wci_' . $key, '', $key, 99, array( 'class' => '', 'image' => '' ), array() );

		if ( isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ) {
			$element = self::merge( $element, $stored[ $key ] );
		}

		return $element;
	}


	/**
	 * Get the icon of one element.
	 *
	 * @since 6.0.0
	 * @param string $key | Element key
	 * @param array<string,mixed> $stored | The stored `elements_design` array
	 * @return array<string,string>
	 */
	public static function icon( $key, $stored = array() ) {
		$element = self::get( $key, $stored );

		return array(
			'class' => (string) ( $element['icon']['class'] ?? '' ),
			'image' => (string) ( $element['icon']['image'] ?? '' ),
		);
	}


	/**
	 * The default value of the `elements_design` setting.
	 *
	 * @since 6.0.0
	 * @return array<string,array<string,mixed>>
	 */
	public static function defaults() {
		return self::all();
	}


	/**
	 * Forget the cached registry.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public static function flush() {
		self::$elements = null;
	}


	/**
	 * Build one element definition.
	 *
	 * Every element carries the same style keys for both breakpoints, so the
	 * CSS writer never has to ask whether a property exists.
	 *
	 * @since 6.0.0
	 * @param string $id | DOM id used by the settings preview
	 * @param string $selector | CSS selector the element's styles target
	 * @param string $title | Human-readable name
	 * @param int $order | Position inside the price group
	 * @param array<string,string> $icon | Icon class and image
	 * @param array<string,mixed> $styles | Style overrides applied to both breakpoints
	 * @return array<string,mixed>
	 */
	private static function element( $id, $selector, $title, $order, $icon, $styles ) {
		$base = self::style_defaults();
		$resolved = self::merge( $base, $styles );

		return array(
			'id' => $id,
			'selector' => $selector,
			'settings_title' => $title,
			'order' => $order,
			'icon' => $icon,
			'styles' => array(
				'mobile' => $resolved,
				'desktop' => $resolved,
			),
		);
	}


	/**
	 * The style properties every element starts from.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function style_defaults() {
		$box = array( 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'rem' );

		return array(
			'font_size' => '1',
			'font_unit' => 'rem',
			'font_weight' => '400',
			'font_color' => '#343A40',
			'background_color' => 'transparent',
			'margin' => $box,
			'padding' => $box,
			'border_radius' => $box,
		);
	}


	/**
	 * Merge one array over another, recursing into nested arrays.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $base | Values to start from
	 * @param array<string,mixed> $overrides | Values that win
	 * @return array<string,mixed>
	 */
	private static function merge( $base, $overrides ) {
		foreach ( $overrides as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
				$base[ $key ] = self::merge( $base[ $key ], $value );

				continue;
			}

			$base[ $key ] = $value;
		}

		return $base;
	}
}
