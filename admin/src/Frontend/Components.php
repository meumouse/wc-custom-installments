<?php

namespace MeuMouse\WC_Custom_Installments\Frontend;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Compatibility\Hook_Aliases;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Core\Helpers;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Installments;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Values;

defined('ABSPATH') || exit;

/**
 * The pieces of markup the storefront is assembled from.
 *
 * Each builder returns a string and decides for itself whether it should render
 * at all, which is what lets the price group, the shortcodes and the page
 * builder widgets share one implementation.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Frontend
 * @author MeuMouse.com
 */
class Components {

	/**
	 * Whether an element should render on the current page.
	 *
	 * The stored values include the 5.x spellings, because a migrated store has
	 * them written into its settings and a display rule that silently stopped
	 * matching would blank the element.
	 *
	 * @since 6.0.0
	 * @param string $placement | The stored placement value
	 * @return bool
	 */
	public static function should_display( $placement ) {
		$placement = (string) $placement;

		if ( in_array( $placement, array( 'none', 'hide' ), true ) ) {
			return false;
		}

		// An editor has no archive and no product page, but the designer still
		// needs to see what they are laying out.
		if ( Helpers::is_builder_preview() ) {
			return true;
		}

		if ( in_array( $placement, array( 'display_loop_and_single_product', 'global' ), true ) ) {
			return true;
		}

		if ( 'only_single_product' === $placement ) {
			return function_exists('is_product') && is_product();
		}

		if ( in_array( $placement, array( 'only_loop', 'only_loop_products' ), true ) ) {
			return ! ( function_exists('is_product') && is_product() );
		}

		return false;
	}


	/**
	 * Whether a product opted out of an element.
	 *
	 * A variation inherits its parent's opt-out: the setting lives on the
	 * product a store owner edits, not on each generated variation.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @param string $meta_key | The opt-out meta key
	 * @return bool
	 */
	public static function is_opted_out( $product, $meta_key ) {
		if ( ! $product instanceof \WC_Product ) {
			return false;
		}

		if ( 'yes' === get_post_meta( $product->get_id(), $meta_key, true ) ) {
			return true;
		}

		$parent_id = $product->get_parent_id();

		return $parent_id && 'yes' === get_post_meta( $parent_id, $meta_key, true );
	}


	/**
	 * The highlighted instalment under the price.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return string
	 */
	public function display_best_installments( $product ) {
		$product = Helpers::get_product_from_request( $product );

		if ( ! $product || self::is_opted_out( $product, '__disable_installments' ) ) {
			return '';
		}

		if ( ! self::should_display( Repository::get('hook_display_best_installments') ) ) {
			return '';
		}

		$price = $product->is_type('variable')
			? (float) $product->get_variation_price( 'min', true )
			: (float) $product->get_price();

		$installments = Calculate_Installments::installments_list( array(), $price, $product );
		$type = Repository::get('get_type_best_installments');
		$best = '';

		if ( 'best_installment_without_fee' === $type ) {
			$best = Calculate_Installments::best_without_interest( $installments, $product );
		} elseif ( 'best_installment_with_fee' === $type ) {
			$best = Calculate_Installments::best_with_interest( $installments, $product );
		} elseif ( 'both' === $type ) {
			$best = Calculate_Installments::best_without_interest( $installments, $product );
			$best .= Calculate_Installments::best_with_interest( $installments, $product );
		}

		if ( '' === $best ) {
			return '';
		}

		return ' <span class="woo-custom-installments-card-container">' . $best . ' </span>';
	}


	/**
	 * The discounted price for paying upfront.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return string
	 */
	public function discount_main_price( $product ) {
		$product = Helpers::get_product_from_request( $product );

		if ( ! $product || ! Repository::is_active('enable_all_discount_options') ) {
			return '';
		}

		if ( self::is_opted_out( $product, '__disable_discount_main_price' ) ) {
			return '';
		}

		if ( ! self::should_display( Repository::get('display_discount_price_hook') ) ) {
			return '';
		}

		if ( ! Helpers::is_builder_preview() && $product->get_price() <= 0 ) {
			return '';
		}

		return $this->badge(
			'woo-custom-installments-offer',
			'discount_pix',
			'wci-icon-main-price',
			Repository::get('text_before_price'),
			wc_price( Calculate_Values::get_discounted_price( $product, 'main' ) ),
			Repository::get('text_after_price'),
			'discount-before-price',
			'discount-after-price'
		);
	}


	/**
	 * The discounted price for paying with a bank slip.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return string
	 */
	public function discount_ticket_badge( $product ) {
		if ( ! Features::is_available('ticket_discount') ) {
			return '';
		}

		$product = Helpers::get_product_from_request( $product );

		if ( ! $product || self::is_opted_out( $product, '__disable_discount_main_price' ) ) {
			return '';
		}

		if ( ! Repository::is_active('enable_ticket_method_payment_form') || ! Repository::is_active('enable_ticket_discount_main_price') ) {
			return '';
		}

		if ( ! self::should_display( Repository::get('display_discount_ticket_hook') ) ) {
			return '';
		}

		return $this->badge(
			'woo-custom-installments-ticket-discount',
			'discount_slip_bank',
			'wci-icon-ticket-discount',
			Repository::get('text_before_discount_ticket'),
			wc_price( Calculate_Values::get_discounted_price( $product, 'ticket' ) ),
			Repository::get('text_after_discount_ticket'),
			'discount-before-discount-ticket',
			'discount-after-discount-ticket'
		);
	}


	/**
	 * How much the customer saves by paying upfront.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return string
	 */
	public function economy_pix_badge( $product ) {
		if ( ! Features::is_available('pix_economy_badge') ) {
			return '';
		}

		$product = Helpers::get_product_from_request( $product );

		if ( ! $product || ! Repository::is_active('enable_economy_pix_badge') || ! Repository::is_active('enable_all_discount_options') ) {
			return '';
		}

		if ( self::is_opted_out( $product, '__disable_discount_main_price' ) ) {
			return '';
		}

		$economy = Calculate_Values::get_pix_economy( $product );

		if ( $economy <= 0 ) {
			return '';
		}

		$template = (string) Repository::get('text_economy_pix_badge');

		if ( '' === trim( $template ) ) {
			return '';
		}

		if ( ! self::should_display( Repository::get('display_economy_pix_hook') ) ) {
			return '';
		}

		// A template without the placeholder is shown as written: the store
		// owner may well have wanted a fixed sentence.
		$text = ( false !== strpos( $template, '%s' ) )
			? sprintf( $template, wc_price( $economy ) )
			: $template;

		$html = '<span class="woo-custom-installments-economy-pix-badge">';
		$html .= Helpers::render_icon( 'pix_economy', 'wci-icon-economy-pix' );
		$html .= '<span class="discount-before-economy-pix">' . $text . '</span>';
		$html .= '</span>';

		return $html;
	}


	/**
	 * The percentage-off badge over a product on sale.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return string
	 */
	public function sale_badge( $product ) {
		if ( ! Features::is_available('sale_badge') || ! $product || ! $product->is_on_sale() ) {
			return '';
		}

		$percentage = 0;

		if ( $product->is_type('variable') ) {
			$percentages = array();
			$prices = $product->get_variation_prices();

			foreach ( $prices['price'] as $key => $price ) {
				$regular = isset( $prices['regular_price'][ $key ] ) ? (float) $prices['regular_price'][ $key ] : 0;
				$sale = isset( $prices['sale_price'][ $key ] ) ? (float) $prices['sale_price'][ $key ] : 0;

				// A variation with no regular price would divide by zero, and a
				// price of zero is a real state: a free variation.
				if ( $regular <= 0 ) {
					continue;
				}

				if ( $prices['regular_price'][ $key ] !== $price ) {
					$percentages[] = round( 100 - ( $sale / $regular * 100 ) );
				}
			}

			$percentage = ! empty( $percentages ) ? max( $percentages ) : 0;
		} else {
			$regular = (float) $product->get_regular_price();
			$sale = (float) $product->get_sale_price();

			if ( $regular > 0 ) {
				$percentage = round( 100 - ( $sale / $regular * 100 ) );
			}
		}

		return sprintf(
			'<span class="wci-sale-badge">%s</span>',
			sprintf(
				/* translators: %s: discount percentage, e.g. "20%" */
				esc_html__( '%s OFF', 'wc-custom-installments' ),
				esc_html( $percentage . '%' )
			)
		);
	}


	/**
	 * The Pix row of the payment methods block.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return string
	 */
	public function render_pix_flag( $product ) {
		if ( ! Repository::is_active('enable_pix_method_payment_form') ) {
			return '';
		}

		$economy_active = Repository::is_active('enable_economy_pix_badge') && Features::is_available('pix_economy_badge');
		$economy_value = Calculate_Values::get_pix_economy( $product );

		$html = '<div class="woo-custom-installments-pix-section">';
		$html .= '<h4 class="pix-method-title">' . wp_kses_post( Repository::get('text_pix_container') ) . '</h4>';
		$html .= '<div class="pix-method-container">';
		$html .= '<span class="pix-method-name">' . sprintf(
			/* translators: %s: discounted price */
			esc_html__( 'Pix: %s', 'wc-custom-installments' ),
			wc_price( Calculate_Values::get_discounted_price( $product, 'main' ) )
		) . '</span>';

		if ( Repository::is_active('enable_instant_approval_badge') ) {
			$html .= '<span class="instant-approval-badge">' . esc_html__( 'Aprovação imediata', 'wc-custom-installments' ) . '</span>';
		}

		$html .= '</div>';

		$container_class = ( $economy_active && $economy_value > 0 )
			? 'container-badge-icon pix-flag pix-info instant-approval-badge'
			: 'container-badge-icon pix-flag pix-info';

		$html .= '<div class="' . esc_attr( $container_class ) . '">';

		if ( $economy_value > 0 ) {
			$html .= '<i class="pix-icon-badge fa-brands fa-pix"></i>';

			if ( $economy_active ) {
				$html .= '<div class="economy-pix-info">' . $this->economy_pix_badge( $product ) . '</div>';
			}
		}

		$html .= '</div></div>';

		return $html;
	}


	/**
	 * The bank slip row of the payment methods block.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return string
	 */
	public function render_ticket_flag( $product ) {
		if ( ! Repository::is_active('enable_ticket_method_payment_form') ) {
			return '';
		}

		$price = Features::is_available('ticket_discount')
			? Calculate_Values::get_discounted_price( $product, 'ticket' )
			: ( $product instanceof \WC_Product ? $product->get_price() : 0 );

		$html = '<div class="woo-custom-installments-ticket-section">';
		$html .= '<h4 class="ticket-method-title">' . esc_html__( 'Cobranças:', 'wc-custom-installments' ) . '</h4>';
		$html .= '<span class="ticket-method-name">' . wp_kses_post( Repository::get('text_ticket_container') ) . ' ' . wc_price( $price ) . '</span>';
		$html .= '<div class="ticket-method-container">';
		$html .= '<span class="ticket-instructions">' . wp_kses_post( Repository::get('text_instructions_ticket_container') ) . '</span>';
		$html .= '</div>';
		$html .= '<div class="container-badge-icon ticket-flag">';
		$html .= '<img class="size-badge-icon" src="' . esc_url( WC_CUSTOM_INSTALLMENTS_ASSETS . 'storefront/img/boleto-badge.svg' ) . '"/>';
		$html .= '</div></div>';

		return $html;
	}


	/**
	 * The brand badges of one card row.
	 *
	 * @since 6.0.0
	 * @param string $card_type | Either `credit-card` or `debit-card`
	 * @param string $type | Either `credit` or `debit`
	 * @return string
	 */
	public function get_card_flags( $card_type, $type ) {
		$flags = array();

		foreach ( \MeuMouse\WC_Custom_Installments\Admin\Default_Options::get_card_brands() as $brand => $label ) {
			$flags[ $brand ] = WC_CUSTOM_INSTALLMENTS_ASSETS . 'storefront/img/' . str_replace( '_', '-', $brand ) . '-badge.svg';
		}

		/**
		 * Filter the badge image of each brand.
		 *
		 * @since 6.0.0
		 * @param array<string,string> $flags | Brand key => image URL
		 * @param string $card_type | Row type
		 * @param string $type | Either `credit` or `debit`
		 * @return array<string,string>
		 */
		$flags = (array) Hook_Aliases::apply_filters( 'WC_Custom_Installments/Elements/Card_Flags', $flags, $card_type, $type );

		$html = '';

		foreach ( $flags as $brand => $url ) {
			if ( ! Repository::is_active( 'enable_' . $brand . '_flag_' . $type ) ) {
				continue;
			}

			$html .= '<div class="container-badge-icon ' . esc_attr( $card_type ) . ' ' . esc_attr( $brand ) . '-flag">';
			$html .= '<img class="size-badge-icon" src="' . esc_url( $url ) . '"/>';
			$html .= '</div>';
		}

		return $html;
	}


	/**
	 * The credit card row of the payment methods block.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public function render_credit_card_flags() {
		return $this->render_card_section( 'credit' );
	}


	/**
	 * The debit card row of the payment methods block.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public function render_debit_card_flags() {
		return $this->render_card_section( 'debit' );
	}


	/**
	 * Build one card row.
	 *
	 * @since 6.0.0
	 * @param string $type | Either `credit` or `debit`
	 * @return string
	 */
	private function render_card_section( $type ) {
		if ( ! Repository::is_active( 'enable_' . $type . '_card_method_payment_form' ) ) {
			return '';
		}

		$title = Repository::get( 'text_' . $type . '_card_container' );

		$html = '<div class="woo-custom-installments-' . esc_attr( $type ) . '-card-section">';
		$html .= '<h4 class="' . esc_attr( $type ) . '-card-method-title">' . wp_kses_post( $title ) . '</h4>';

		if ( Repository::is_active('enable_instant_approval_badge') ) {
			$html .= '<div class="' . esc_attr( $type ) . '-card-method-container">';
			$html .= '<span class="instant-approval-badge">' . esc_html__( 'Aprovação imediata', 'wc-custom-installments' ) . '</span>';
			$html .= '</div>';
		}

		$html .= '<div class="' . esc_attr( $type ) . '-card-container-badges">';
		$html .= $this->get_card_flags( $type . '-card', $type );
		$html .= '</div></div>';

		return $html;
	}


	/**
	 * The full instalment table.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return string
	 */
	public function render_installments_table( $product ) {
		if ( ! $product ) {
			return '';
		}

		if ( $product->is_type('variable') && ! Helpers::variations_has_same_price( $product ) ) {
			$price = wc_get_price_to_display( $product, array( 'price' => $product->get_variation_price('max') ) );
		} else {
			$price = wc_get_price_to_display( $product );
		}

		$all_installments = Calculate_Installments::installments_list( array(), $price, $product );

		if ( empty( $all_installments ) ) {
			return '';
		}

		$text = (string) Repository::get('text_display_installments_payment_forms');

		if ( ! Features::is_available('custom_placeholders') || '' === trim( $text ) ) {
			$defaults = \MeuMouse\WC_Custom_Installments\Admin\Default_Options::get_defaults();
			$text = $defaults['text_display_installments_payment_forms'];
		}

		$html = '<h4 class="installments-title">' . wp_kses_post( Repository::get('text_table_installments') ) . '</h4>';
		$html .= '<div id="table-installments">';
		$html .= '<table class="table table-hover woo-custom-installments-table">';
		$html .= '<tbody data-default-text="' . esc_attr( $text ) . '">';

		foreach ( $all_installments as $installment ) {
			$replacements = Helpers::strings_to_replace( $installment );
			$row_text = str_replace( array_keys( $replacements ), array_values( $replacements ), $text );

			$html .= '<tr class="' . esc_attr( $installment['class'] ) . '">';
			$html .= '<th class="final-text">' . $row_text . '</th>';
			$html .= '<th class="final-price">' . wc_price( $installment['final_price'] ) . '</th>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';

		return $html;
	}


	/**
	 * The payment methods block, as a collapsible panel.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return string
	 */
	public function payment_methods_accordion( $product ) {
		$product = Helpers::get_product_from_request( $product );

		ob_start();

		?>
		<div id="wci-accordion-installments" class="accordion">
			<div class="wci-accordion-item">
				<button type="button" class="wci-accordion-header">
					<?php echo wp_kses_post( Repository::get('text_button_installments') ); ?>
					<svg class="wci-accordion-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g><path d="M18 14L12 20L6 14" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></g></svg>
				</button>

				<div class="wci-accordion-content">
					<?php
					/**
					 * Fires at the top of the payment methods panel.
					 *
					 * @since 6.0.0
					 * @param object $product | Product object
					 */
					Hook_Aliases::do_action( 'WC_Custom_Installments/Elements/Accordion_Header', $product );

					echo $this->payment_methods_body( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
					?>
				</div>

				<?php
				/**
				 * Fires at the bottom of the payment methods panel.
				 *
				 * @since 6.0.0
				 * @param object $product | Product object
				 */
				Hook_Aliases::do_action( 'WC_Custom_Installments/Elements/Accordion_Footer', $product );
				?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}


	/**
	 * The payment methods block, as a dialog.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return string
	 */
	public function payment_methods_modal( $product ) {
		$product = Helpers::get_product_from_request( $product );

		ob_start();

		?>
		<button type="button" class="wci-open-popup">
			<span class="open-popup-text"><?php echo wp_kses_post( Repository::get('text_button_installments') ); ?></span>
		</button>

		<div class="wci-popup-container">
			<div class="wci-popup-content">
				<div class="wci-popup-header">
					<h5 class="wci-popup-title"><?php echo wp_kses_post( Repository::get('text_container_payment_forms') ); ?></h5>
					<button type="button" class="btn-close wci-close-popup" aria-label="<?php esc_attr_e( 'Fechar', 'wc-custom-installments' ); ?>"></button>
				</div>

				<?php
				/**
				 * Fires at the top of the payment methods dialog.
				 *
				 * @since 6.0.0
				 * @param object $product | Product object
				 */
				Hook_Aliases::do_action( 'WC_Custom_Installments/Elements/Modal_Header', $product );
				?>

				<div class="wci-popup-body">
					<?php echo $this->payment_methods_body( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts. ?>
				</div>

				<?php
				/**
				 * Fires at the bottom of the payment methods dialog.
				 *
				 * @since 6.0.0
				 * @param object $product | Product object
				 */
				Hook_Aliases::do_action( 'WC_Custom_Installments/Elements/Modal_Footer', $product );
				?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}


	/**
	 * What goes inside either payment methods container.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return string
	 */
	private function payment_methods_body( $product ) {
		$html = $this->render_pix_flag( $product );
		$html .= $this->render_credit_card_flags();
		$html .= $this->render_debit_card_flags();
		$html .= $this->render_ticket_flag( $product );
		$html .= $this->render_installments_table( $product );

		return $html;
	}


	/**
	 * The message telling a customer how to earn the quantity discount.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return string
	 */
	public function message_for_discount_per_quantity( $product ) {
		if ( ! Features::is_available('quantity_discounts') ) {
			return '';
		}

		$product = Helpers::get_product_from_request( $product );

		if ( ! $product ) {
			return '';
		}

		$product_id = $product->get_id();
		$global = 'global' === Repository::get('enable_discount_per_quantity_method');
		$per_product = get_post_meta( $product_id, 'enable_discount_per_quantity', true );

		if ( ! $global && ! $per_product ) {
			return '';
		}

		if ( $global ) {
			$method = Repository::get('discount_per_quantity_method');
			$value = Repository::get('value_for_discount_per_quantity');
			$minimum = Repository::get('set_quantity_enable_discount');
		} else {
			$method = get_post_meta( $product_id, 'discount_per_quantity_method', true );
			$value = get_post_meta( $product_id, 'quantity_discount_amount', true );
			$minimum = get_post_meta( $product_id, 'minimum_quantity_discount', true );
		}

		$amount = ( 'percentage' === $method ) ? $value . '%' : get_woocommerce_currency_symbol() . $value;
		$template = (string) Repository::get('text_discount_per_quantity_message');

		if ( '' === trim( $template ) ) {
			return '';
		}

		// Both placeholders have to be there, in that order, or sprintf would
		// throw. A template missing one is shown as written.
		$text = ( 1 === substr_count( $template, '%s' ) && 1 === substr_count( $template, '%d' ) )
			? sprintf( $template, $minimum, $amount )
			: $template;

		$html = '<div class="woo-custom-installments-discount-per-quantity-message">';
		$html .= '<i class="fa-solid fa-circle-exclamation"></i>';
		$html .= '<span>' . $text . '</span>';
		$html .= '</div>';

		return $html;
	}


	/**
	 * Build one of the price badges.
	 *
	 * The four badges differ only in their classes, icon and copy, and having
	 * written them four times is how the 5.x file reached 1,300 lines.
	 *
	 * @since 6.0.0
	 * @param string $wrapper_class | Class of the outer span
	 * @param string $element | Element key in the registry
	 * @param string $icon_class | Class of the icon
	 * @param string $before | Copy before the price
	 * @param string $price | The formatted price
	 * @param string $after | Copy after the price
	 * @param string $before_class | Class of the leading copy
	 * @param string $after_class | Class of the trailing copy
	 * @return string
	 */
	private function badge( $wrapper_class, $element, $icon_class, $before, $price, $after, $before_class, $after_class ) {
		$html = '<span class="' . esc_attr( $wrapper_class ) . '">';
		$html .= Helpers::render_icon( $element, $icon_class );

		if ( '' !== trim( (string) $before ) ) {
			$html .= '<span class="' . esc_attr( $before_class ) . '">' . wp_kses_post( $before ) . '</span>';
		}

		$html .= '<span class="discounted-price">' . $price . '</span>';

		if ( '' !== trim( (string) $after ) ) {
			$html .= '<span class="' . esc_attr( $after_class ) . '">' . wp_kses_post( $after ) . '</span>';
		}

		$html .= '</span>';

		return $html;
	}
}
