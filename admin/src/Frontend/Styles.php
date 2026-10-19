<?php

namespace MeuMouse\WC_Custom_Installments\Frontend;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Compatibility\Hook_Aliases;
use MeuMouse\WC_Custom_Installments\Core\Features;

defined('ABSPATH') || exit;

/**
 * Print the stylesheet the design settings describe.
 *
 * The 5.x version wrote one hand-maintained block per element per breakpoint,
 * which is how it reached four hundred lines to express five elements. Here the
 * elements are data and the CSS is a loop over them, so a new element brings its
 * styles with it.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Frontend
 * @author MeuMouse.com
 */
class Styles {

	/**
	 * Where the mobile rules stop applying.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const MOBILE_MAX = '992px';

	/**
	 * Where the desktop rules start applying.
	 *
	 * One pixel past `MOBILE_MAX`, and it has to stay that way. 5.x stopped the
	 * mobile rules at 992px and started the desktop ones at 1024px, so between
	 * the two no block applied and the storefront fell back to the shipped
	 * look — black prices and a green Pix pill, right in the middle of the
	 * tablet range. `tests/engine-test.php` walks the widths to keep the two
	 * touching.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const DESKTOP_MIN = '993px';


	/**
	 * Register the hook that prints the stylesheet.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'render_styles' ), 20 );
		add_action( 'admin_head', array( $this, 'product_list_styles' ) );
	}


	/**
	 * Keep the products list readable.
	 *
	 * WooCommerce prints each product's price HTML in the Price column, which
	 * runs through the same filter as the storefront. The badges belong to the
	 * shop, not to a table the store owner scans; the price itself stays.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function product_list_styles() {
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;

		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}

		$css = '.column-price .woo-custom-installments-offer,.column-price .woo-custom-installments-economy-pix-badge,.column-price .woo-custom-installments-ticket-discount,.column-price .wci-sale-badge,.column-price .woo-custom-installments-card-container{display:none;}';
		$css .= '.column-price .woo-custom-installments-starting-from{margin-right:0.25rem;}';
		$css .= 'table.wp-list-table .column-price{width:10rem;}';
		$css .= '.column-price .woo-custom-installments-price{display:block;}';
		$css .= '.column-price .woo-custom-installments-price.has-discount{text-decoration:line-through;}';

		printf( "<style id=\"wc-custom-installments-product-list\">%s</style>\n", $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static CSS.
	}


	/**
	 * Print the stylesheet.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function render_styles() {
		if ( is_admin() ) {
			return;
		}

		$css = $this->build_css();

		if ( '' === trim( $css ) ) {
			return;
		}

		printf( "<style id=\"wc-custom-installments-styles\">%s</style>\n", $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS assembled from sanitized settings.
	}


	/**
	 * Assemble the whole stylesheet.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public function build_css() {
		/**
		 * Filter whether every generated declaration carries `!important`.
		 *
		 * Off by default: a theme that wants to restyle the price group should
		 * be able to, and a plugin that shouts over every rule is a plugin the
		 * store owner ends up fighting.
		 *
		 * @since 6.0.0
		 * @param bool $force | Whether to force the declarations
		 * @return bool
		 */
		$important = Hook_Aliases::apply_filters( 'WC_Custom_Installments/Styles/Force_Important', false ) ? ' !important' : '';

		$stored = (array) Repository::get( 'elements_design', array() );
		$advanced = Features::is_available('advanced_design');

		$css = $this->order_rules( $stored );
		$css .= $this->breakpoint_rules( $stored, 'mobile', $important, $advanced );
		$css .= $this->breakpoint_rules( $stored, 'desktop', $important, $advanced );
		$css .= $this->popup_rules();
		$css .= $this->layout_rules();

		return $css;
	}


	/**
	 * The order each element takes inside the price group.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $stored | Stored element design
	 * @return string
	 */
	private function order_rules( $stored ) {
		$css = '';

		foreach ( array_keys( Element_Registry::all() ) as $key ) {
			$element = Element_Registry::get( $key, $stored );

			// A part of another element sits wherever its parent puts it.
			if ( empty( $element['selector'] ) || ! empty( $element['parent'] ) ) {
				continue;
			}

			$css .= sprintf(
				'.woo-custom-installments-group %s{order:%d;}',
				$element['selector'],
				(int) $element['order']
			);
		}

		return $css;
	}


	/**
	 * The typography and spacing rules of one breakpoint.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $stored | Stored element design
	 * @param string $breakpoint | Either `mobile` or `desktop`
	 * @param string $important | The `!important` suffix, or an empty string
	 * @param bool $advanced | Whether the store may use the fine-grained controls
	 * @return string
	 */
	private function breakpoint_rules( $stored, $breakpoint, $important, $advanced ) {
		$query = ( 'mobile' === $breakpoint )
			? '@media screen and (max-width:' . self::MOBILE_MAX . ')'
			: '@media screen and (min-width:' . self::DESKTOP_MIN . ')';

		$rules = '';

		foreach ( array_keys( Element_Registry::all() ) as $key ) {
			$element = Element_Registry::get( $key, $stored );

			if ( empty( $element['selector'] ) ) {
				continue;
			}

			$styles = $element['styles'][ $breakpoint ] ?? array();

			if ( empty( $styles ) ) {
				continue;
			}

			// Spacing, background and radius are the fine-grained half of the
			// design controls, and changing them is a Pro capability. What the
			// element ships with is not: the Pix badge is a green pill by
			// design, and dropping its background on an unlicensed store would
			// leave the storefront looking broken rather than un-upgraded.
			if ( ! $advanced ) {
				$shipped = Element_Registry::get( $key )['styles'][ $breakpoint ] ?? array();
				$styles = array_merge( $styles, array(
					'background_color' => $shipped['background_color'] ?? $styles['background_color'],
					'margin' => $shipped['margin'] ?? $styles['margin'],
					'padding' => $shipped['padding'] ?? $styles['padding'],
					'border_radius' => $shipped['border_radius'] ?? $styles['border_radius'],
				) );
			}

			$selector = '.woo-custom-installments-group ' . $element['selector'];
			$font = '' !== (string) $styles['font_size'] ? $styles['font_size'] . ( $styles['font_unit'] ?? 'rem' ) : '';

			// An element that is part of another one writes only what the store
			// set on it; an empty property inherits from the parent. The others
			// are always complete, the sanitizer sees to that.
			$declarations = $this->declarations( array(
				'font-size' => $font,
				'font-weight' => $styles['font_weight'],
				'color' => $styles['font_color'],
				'background-color' => $styles['background_color'],
				'margin' => $this->box( $styles['margin'] ),
				'padding' => $this->box( $styles['padding'] ),
				'border-radius' => $this->box( $styles['border_radius'] ),
			) );

			if ( '' !== $declarations ) {
				$rules .= $selector . '{' . $declarations . '}';
			}

			// WooCommerce wraps every formatted price in `.amount`, and its own
			// styles are specific enough that the parent rule alone loses.
			$amount = $this->declarations( array(
				'font-size' => '' !== $font ? $font . $important : '',
				'font-weight' => $styles['font_weight'],
				'color' => $styles['font_color'],
			) );

			if ( '' !== $amount ) {
				$rules .= $selector . ' .amount{' . $amount . '}';
			}

			// The struck-out regular price reads as secondary next to the
			// current one, as it did in 5.x.
			if ( 'price' === $key && 'desktop' === $breakpoint ) {
				$rules .= sprintf(
					'%1$s .woo-custom-installments-price.original-price.has-discount .amount,%1$s del .amount{font-size:calc(%2$s - 0.3rem)%3$s;}',
					$selector,
					$font,
					$important
				);
			}
		}

		return '' === $rules ? '' : $query . '{' . $rules . '}';
	}


	/**
	 * The instalment popup trigger.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	private function popup_rules() {
		$type = Repository::get('display_installment_type');

		if ( ! in_array( $type, array( 'popup', 'accordion' ), true ) ) {
			return '';
		}

		$color = Repository::sanitize_color( Repository::get('button_popup_color') );
		$color = '' !== $color ? $color : '#008aff';

		// The trigger and the accordion sit in the same spot, so the spacing
		// the store set applies to whichever of the two is shown.
		$css = sprintf(
			'button.wci-open-popup,#wci-accordion-installments{margin-top:%s;margin-bottom:%s;}',
			Repository::get('margin_top_popup_installments') . Repository::get('unit_margin_top_popup_installments'),
			Repository::get('margin_bottom_popup_installments') . Repository::get('unit_margin_bottom_popup_installments')
		);

		if ( 'popup' !== $type ) {
			return $css;
		}

		$css .= sprintf(
			'button.wci-open-popup{color:%1$s;border-color:%1$s;border-radius:%2$s;}button.wci-open-popup:hover{background-color:%1$s;}',
			$color,
			Repository::get('border_radius_popup_installments') . Repository::get('unit_border_radius_popup_installments')
		);

		// The three button sizes of 5.x: an outlined button that fills on hover.
		$sizes = array(
			'small' => 'padding:0.475rem 1.25rem;font-size:0.75rem;',
			'normal' => 'padding:0.625rem 1.75rem;font-size:0.875rem;',
			'large' => 'padding:0.785rem 2rem;font-size:1rem;',
		);

		$size = (string) Repository::get('button_popup_size');

		if ( isset( $sizes[ $size ] ) ) {
			return $css . 'button.wci-open-popup{' . $sizes[ $size ] . '}';
		}

		// The link style: no box, an underline that redraws itself on hover.
		$css .= sprintf(
			'button.wci-open-popup{display:inline-block;position:relative;font-weight:500;text-decoration:none;border:0;padding:0;color:%s;}',
			$color
		);

		$css .= sprintf(
			'button.wci-open-popup::after{content:"";position:absolute;bottom:0;right:0;width:100%%;height:0.0625rem;background-color:%s;}',
			$color
		);

		$css .= sprintf(
			'button.wci-open-popup:hover{text-decoration:none;color:%s;background-color:transparent;}',
			$color
		);

		$css .= 'button.wci-open-popup:hover::after{animation:wciLinkUnderline .6s ease-in-out;}';
		$css .= '@keyframes wciLinkUnderline{0%{width:100%;}50%{width:0;}100%{left:0;width:100%;}}';

		return $css;
	}


	/**
	 * Alignment and range-price rules.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	private function layout_rules() {
		$css = '';

		if ( Repository::is_active('center_group_elements_loop') ) {
			$selectors = trim( (string) Repository::get('selectors_group_for_center_elements') );

			if ( '' !== $selectors ) {
				$css .= $selectors . '{justify-items:center !important;align-items:center !important;justify-content:center !important;}';
			}
		}

		// With the range replaced by a single starting price, WooCommerce's own
		// variation price would contradict it on the product page.
		if ( Repository::is_active('remove_price_range') ) {
			$css .= '.woocommerce-variation-price{display:none !important;}';
		}

		return $css;
	}


	/**
	 * Join CSS declarations, leaving out the ones without a value.
	 *
	 * @since 6.0.0
	 * @param array<string,string> $properties | Property => value
	 * @return string
	 */
	private function declarations( $properties ) {
		$css = '';

		foreach ( $properties as $property => $value ) {
			if ( '' !== (string) $value ) {
				$css .= $property . ':' . $value . ';';
			}
		}

		return $css;
	}


	/**
	 * Format a four-sided box property.
	 *
	 * A box with every side empty inherits, so it has no value; once one side
	 * is set, the empty ones read as zero.
	 *
	 * @since 6.0.0
	 * @param array<string,string> $box | Top, right, bottom, left and the unit
	 * @return string
	 */
	private function box( $box ) {
		$sides = array();

		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$sides[ $side ] = (string) ( $box[ $side ] ?? '0' );
		}

		if ( '' === implode( '', $sides ) ) {
			return '';
		}

		$unit = $box['unit'] ?? 'rem';

		return implode( ' ', array_map( static function( $amount ) use ( $unit ) {
			return ( '' !== $amount ? $amount : '0' ) . $unit;
		}, $sides ) );
	}
}
