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

			if ( empty( $element['selector'] ) ) {
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
			$font = $styles['font_size'] . ( $styles['font_unit'] ?? 'rem' );

			$declarations = sprintf(
				'font-size:%s;font-weight:%s;color:%s;background-color:%s;margin:%s;padding:%s;border-radius:%s;',
				$font,
				$styles['font_weight'],
				$styles['font_color'],
				$styles['background_color'],
				$this->box( $styles['margin'] ),
				$this->box( $styles['padding'] ),
				$this->box( $styles['border_radius'] )
			);

			$rules .= $selector . '{' . $declarations . '}';

			// WooCommerce wraps every formatted price in `.amount`, and its own
			// styles are specific enough that the parent rule alone loses.
			$rules .= sprintf(
				'%s .amount{font-size:%s%s;font-weight:%s;color:%s;}',
				$selector,
				$font,
				$important,
				$styles['font_weight'],
				$styles['font_color']
			);
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
		if ( 'popup' !== Repository::get('display_installment_type') ) {
			return '';
		}

		$color = Repository::get('button_popup_color');
		$sizes = array( 'small' => '0.8rem', 'normal' => '0.9rem', 'large' => '1.05rem' );
		$size = $sizes[ Repository::get('button_popup_size') ] ?? $sizes['normal'];

		$css = sprintf(
			'button.wci-open-popup{position:relative;display:inline-block;padding:0;border:0;background:transparent;cursor:pointer;font-size:%s;color:%s;}',
			$size,
			$color
		);

		$css .= sprintf(
			'button.wci-open-popup::after{content:"";position:absolute;bottom:0;right:0;width:100%%;height:0.0625rem;background-color:%s;}',
			$color
		);

		$css .= sprintf(
			'button.wci-open-popup:hover{text-decoration:none;background-color:transparent;color:%s;}',
			$color
		);

		$css .= 'button.wci-open-popup:hover::after{animation:wciLinkUnderline .6s ease-in-out;}';
		$css .= '@keyframes wciLinkUnderline{0%{width:100%;}50%{width:0;}100%{left:0;width:100%;}}';

		$css .= sprintf(
			'.wci-popup-container{margin-top:%s;margin-bottom:%s;border-radius:%s;}',
			Repository::get('margin_top_popup_installments') . Repository::get('unit_margin_top_popup_installments'),
			Repository::get('margin_bottom_popup_installments') . Repository::get('unit_margin_bottom_popup_installments'),
			Repository::get('border_radius_popup_installments') . Repository::get('unit_border_radius_popup_installments')
		);

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
	 * Format a four-sided box property.
	 *
	 * @since 6.0.0
	 * @param array<string,string> $box | Top, right, bottom, left and the unit
	 * @return string
	 */
	private function box( $box ) {
		$unit = $box['unit'] ?? 'rem';

		return sprintf(
			'%s%s %s%s %s%s %s%s',
			$box['top'] ?? '0',
			$unit,
			$box['right'] ?? '0',
			$unit,
			$box['bottom'] ?? '0',
			$unit,
			$box['left'] ?? '0',
			$unit
		);
	}
}
