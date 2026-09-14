<?php

namespace MeuMouse\WC_Custom_Installments\Engine;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Compatibility\Hook_Aliases;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * Build the instalment plan of a product, and describe it.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Engine
 * @author MeuMouse.com
 */
class Calculate_Installments {

	/**
	 * How many price groups have been rendered on this request.
	 *
	 * The first one on a product page is the main price; everything after is a
	 * loop item, and the two use different copy.
	 *
	 * @since 6.0.0
	 * @var int
	 */
	public static $count = 0;


	/**
	 * Which context the current price group is being rendered in.
	 *
	 * @since 6.0.0
	 * @return string Either `main_price` or `loop`.
	 */
	public static function hook() {
		return self::is_main_product_price() ? 'main_price' : 'loop';
	}


	/**
	 * Whether this is the main price of a product page.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function is_main_product_price() {
		return function_exists('is_product') && is_product() && 0 === self::$count;
	}


	/**
	 * Build every instalment the store offers for a price.
	 *
	 * @since 6.0.0
	 * @param array $append | Extra instalments to merge in
	 * @param float|null $price | Price to build from; resolved from the product when null
	 * @param object|null $product | Product object; resolved from the request when null
	 * @return array<int,array<string,mixed>>
	 */
	public static function installments_list( $append = array(), $price = null, $product = null ) {
		if ( ! $product ) {
			$product = Helpers::get_product_from_request();
		}

		if ( ! $product || ! Helpers::is_product_available( $product ) ) {
			return array();
		}

		if ( null === $price ) {
			$args = array();

			if ( $product->is_type('variable') && ! Helpers::variations_has_same_price( $product ) ) {
				$args['price'] = $product->get_variation_price('max');
			}

			$price = wc_get_price_to_display( $product, $args );
		}

		/**
		 * Filter the base price the instalments are built from.
		 *
		 * @since 6.0.0
		 * @param float $price | Base price
		 * @param object $product | Product object
		 * @return float
		 */
		$price = Hook_Aliases::apply_filters( 'WC_Custom_Installments/Price/Set_Values_Price', $price, $product );

		$config = Product_Config::installments( $product );
		$installments = array();

		for ( $i = 1; $i <= $config['max']; $i++ ) {
			$fee_rate = Product_Config::fee_rate( $config, $i );
			$fee_fixed = Product_Config::fee_fixed( $config, $i );

			if ( $i <= $config['without_fee'] || ( 0.0 === (float) $fee_rate && 0.0 === (float) $fee_fixed ) ) {
				$info = self::get_installments_without_interest( $price, $i );
			} else {
				$info = self::get_installments_with_interest( $price, $fee_rate, $i, $config['fee_per_installment'], $fee_fixed );
			}

			// The single payment is always offered: it is the price itself, and
			// a minimum instalment value has nothing to say about it.
			if ( 1 === $i || $info['installment_price'] >= $config['min_value'] ) {
				$installments[] = $info;
			}
		}

		$result = array_merge( $installments, $append );

		/**
		 * Filter the full instalment list.
		 *
		 * @since 6.0.0
		 * @param array $result | Every instalment
		 * @param object $product | Product object
		 * @return array
		 */
		return Hook_Aliases::apply_filters( 'WC_Custom_Installments/Installments/All_Installments', $result, $product );
	}


	/**
	 * Render the largest interest-free instalment.
	 *
	 * @since 6.0.0
	 * @param array $installments | Every instalment
	 * @param object $product | Product object
	 * @return string
	 */
	public static function best_without_interest( $installments, $product ) {
		if ( ! is_array( $installments ) || empty( $installments ) || ( $product && $product->get_price() <= 0 ) ) {
			return '';
		}

		$best = self::pick_best_without_interest( $installments );

		if ( null === $best ) {
			return '';
		}

		return self::render_best( $best, $product, 'without-fee' );
	}


	/**
	 * The largest interest-free instalment.
	 *
	 * @since 6.0.0
	 * @param array $installments | Every instalment
	 * @return array<string,mixed>|null Null when every instalment carries interest.
	 */
	public static function pick_best_without_interest( $installments ) {
		$without_interest = array_filter( (array) $installments, static function( $installment ) {
			return 'no-fee' === $installment['class'];
		} );

		$best = end( $without_interest );

		return false === $best ? null : $best;
	}


	/**
	 * Render the largest instalment that carries interest.
	 *
	 * @since 6.0.0
	 * @param array $installments | Every instalment
	 * @param object $product | Product object
	 * @return string
	 */
	public static function best_with_interest( $installments, $product ) {
		if ( ! is_array( $installments ) || empty( $installments ) || ( $product && $product->get_price() <= 0 ) ) {
			return '';
		}

		$best = self::pick_best_with_interest( $installments, $product );

		if ( null === $best ) {
			return '';
		}

		return self::render_best( $best, $product, 'with-fee' );
	}


	/**
	 * The largest instalment, the one that carries interest when there is one.
	 *
	 * @since 6.0.0
	 * @param array $installments | Every instalment
	 * @param object|null $product | Product the plan was built for, whose minimum instalment applies
	 * @return array<string,mixed>|null Null when none is worth showing.
	 */
	public static function pick_best_with_interest( $installments, $product = null ) {
		$installments = (array) $installments;

		// When both are shown, this one must not repeat the interest-free row.
		if ( 'both' === Repository::get('get_type_best_installments') ) {
			$installments = array_filter( $installments, static function( $installment ) {
				return 'fee-included' === $installment['class'];
			} );
		}

		$installments = array_values( $installments );
		$best = end( $installments );

		if ( false === $best || $best['installment_price'] < Product_Config::installments( $product )['min_value'] ) {
			return null;
		}

		return $best;
	}


	/**
	 * Render one highlighted instalment.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $best | The instalment to show
	 * @param object $product | Product object
	 * @param string $variant | Either `with-fee` or `without-fee`
	 * @return string
	 */
	private static function render_best( $best, $product, $variant ) {
		$hook = self::hook();
		$text = self::get_template( $hook );

		$replacements = Helpers::strings_to_replace( $best );
		$text = str_replace( array_keys( $replacements ), array_values( $replacements ), $text );

		$filter = ( 'with-fee' === $variant )
			? 'WC_Custom_Installments/Installments/Best_With_Fee_' . $hook
			: 'WC_Custom_Installments/Installments/Best_Without_Fee_' . $hook;

		/**
		 * Filter the highlighted instalment text.
		 *
		 * @since 6.0.0
		 * @param string $text | Rendered text
		 * @param array $best | The instalment being shown
		 * @param object $product | Product object
		 * @return string
		 */
		$rendered = Hook_Aliases::apply_filters( $filter, $text, $best, $product );

		$html = sprintf(
			'<span class="woo-custom-installments-details-%s" data-end-installments="%s">',
			esc_attr( $variant ),
			esc_attr( $best['installments_total'] )
		);

		$html .= Helpers::render_icon( 'installments', 'wci-icon-best-installments' );

		// The two variants differ by one class, and the difference is not
		// cosmetic: theme CSS in the wild targets `woo-custom-installments-details`.
		$inner_class = ( 'without-fee' === $variant )
			? 'woo-custom-installments-details best-value ' . $best['class']
			: 'best-value ' . $best['class'];

		$html .= '<span class="' . esc_attr( $inner_class ) . '">' . $rendered . '</span>';
		$html .= '</span>';

		return $html;
	}


	/**
	 * The copy template for the highlighted instalment.
	 *
	 * Rewriting it is a Pro capability. Without it the store gets the default
	 * wording, never an empty line.
	 *
	 * @since 6.0.0
	 * @param string $hook | Either `main_price` or `loop`
	 * @return string
	 */
	private static function get_template( $hook ) {
		$key = ( 'main_price' === $hook )
			? 'text_display_installments_single_product'
			: 'text_display_installments_loop';

		$template = Repository::get( $key );

		if ( ! Features::is_available('custom_placeholders') || '' === trim( (string) $template ) ) {
			$defaults = \MeuMouse\WC_Custom_Installments\Admin\Default_Options::get_defaults();
			$template = $defaults[ $key ] ?? '';
		}

		return (string) $template;
	}


	/**
	 * The interest suffix of one instalment.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $installment | The instalment
	 * @return string
	 */
	public static function get_fee_info( $installment ) {
		$hook = self::hook();

		$text = ! empty( $installment['interest_fee'] )
			? (string) Repository::get('text_with_fee_installments')
			: ' ' . (string) Repository::get('text_without_fee_installments');

		/**
		 * Filter the interest suffix.
		 *
		 * @since 6.0.0
		 * @param string $text | Suffix text
		 * @param bool $interest_fee | Whether this instalment carries interest
		 * @param string $hook | Rendering context
		 * @return string
		 */
		return Hook_Aliases::apply_filters( 'WC_Custom_Installments/Price/Fee_Label', $text, $installment['interest_fee'], $hook );
	}


	/**
	 * Describe one instalment.
	 *
	 * @since 6.0.0
	 * @param float $price | Value of one instalment
	 * @param float $final_price | Total paid
	 * @param bool $interest_fee | Whether interest applies
	 * @param string $class | CSS class describing it
	 * @param int $i | Number of instalments
	 * @return array<string,mixed>
	 */
	public static function set_installment_info( $price, $final_price, $interest_fee, $class, $i ) {
		/**
		 * Filter one instalment's description.
		 *
		 * @since 6.0.0
		 * @param array $installment_info | The instalment
		 * @return array
		 */
		return Hook_Aliases::apply_filters( 'WC_Custom_Installments/Price/Installment_Info', array(
			'installment_price' => $price,
			'installments_total' => $i,
			'final_price' => $final_price,
			'interest_fee' => $interest_fee,
			'class' => $class,
		) );
	}


	/**
	 * Build an interest-free instalment.
	 *
	 * @since 6.0.0
	 * @param float $total | Base amount
	 * @param int $i | Number of instalments
	 * @return array<string,mixed>
	 */
	public static function get_installments_without_interest( $total, $i ) {
		$price = Calculate_Values::calculate_installment_no_fee( $total, $i );
		$final_price = Calculate_Values::calculate_final_price( $price, $i );

		return self::set_installment_info( $price, $final_price, false, 'no-fee', $i );
	}


	/**
	 * Build an instalment that carries interest.
	 *
	 * @since 6.0.0
	 * @param float $total | Base amount
	 * @param float $fee | Interest rate
	 * @param int $i | Number of instalments
	 * @param bool|null $fee_per_installment | Whether the rate comes from a per-instalment table; the store's setting when null
	 * @param float $fixed | Amount a per-instalment table adds once to the total
	 * @return array<string,mixed>
	 */
	public static function get_installments_with_interest( $total, $fee, $i, $fee_per_installment = null, $fixed = 0.0 ) {
		$price = Calculate_Values::calculate_installment_with_fee( $total, $fee, $i, $fee_per_installment, $fixed );
		$final_price = Calculate_Values::calculate_final_price( $price, $i );

		return self::set_installment_info( $price, $final_price, true, 'fee-included', $i );
	}
}
