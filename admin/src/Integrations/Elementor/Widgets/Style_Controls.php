<?php

namespace MeuMouse\WC_Custom_Installments\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Compatibility\Hook_Aliases;

defined('ABSPATH') || exit;

/**
 * The Elementor style controls the plugin's widgets are built from.
 *
 * Every method takes the controls stack to write into, so the same definitions
 * serve the plugin's own widgets and the third-party widgets the plugin
 * decorates. The control IDs are the 5.x ones: Elementor stores each value
 * under its control ID inside the page, and a renamed control silently drops
 * the styling a store owner set.
 *
 * Lives under `\Widgets\` so class discovery never builds it: it has no state
 * and nothing to register on its own.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations\Elementor\Widgets
 * @author MeuMouse.com
 */
class Style_Controls {

	/**
	 * The units every spacing control offers.
	 *
	 * @since 6.0.0
	 * @var array<int,string>
	 */
	const SPACING_UNITS = array( 'px', '%', 'em', 'rem' );


	/**
	 * Scope one or more selectors to the widget wrapper.
	 *
	 * @since 6.0.0
	 * @param string ...$selectors | Selectors relative to the widget
	 * @return string
	 */
	public static function scoped( ...$selectors ) {
		return implode( ', ', array_map( static function( $selector ) {
			return '{{WRAPPER}} ' . $selector;
		}, $selectors ) );
	}


	/**
	 * Add a typography group control.
	 *
	 * @since 6.0.0
	 * @param object $element | Elementor controls stack
	 * @param string $name | Control name
	 * @param string $selector | Full CSS selector
	 * @param string|null $label | Control label, `Tipografia` when omitted
	 * @return void
	 */
	public static function typography( $element, $name, $selector, $label = null ) {
		$element->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'label' => $label ?? esc_html__( 'Tipografia', 'wc-custom-installments' ),
				'name' => $name,
				'selector' => $selector,
			)
		);
	}


	/**
	 * Add a colour control.
	 *
	 * @since 6.0.0
	 * @param object $element | Elementor controls stack
	 * @param string $id | Control ID
	 * @param array<string,string> $selectors | Selector => declaration
	 * @param string|null $label | Control label, `Cor do texto` when omitted
	 * @return void
	 */
	public static function color( $element, $id, $selectors, $label = null ) {
		$element->add_control(
			$id,
			array(
				'label' => $label ?? esc_html__( 'Cor do texto', 'wc-custom-installments' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => $selectors,
			)
		);
	}


	/**
	 * Add a background colour control.
	 *
	 * @since 6.0.0
	 * @param object $element | Elementor controls stack
	 * @param string $id | Control ID
	 * @param array<string,string> $selectors | Selector => declaration
	 * @param string|null $label | Control label, `Cor de fundo` when omitted
	 * @return void
	 */
	public static function background_color( $element, $id, $selectors, $label = null ) {
		self::color( $element, $id, $selectors, $label ?? esc_html__( 'Cor de fundo', 'wc-custom-installments' ) );
	}


	/**
	 * Add a border group control.
	 *
	 * @since 6.0.0
	 * @param object $element | Elementor controls stack
	 * @param string $name | Control name
	 * @param string $selector | Full CSS selector
	 * @param string|null $label | Control label, `Borda` when omitted
	 * @return void
	 */
	public static function border( $element, $name, $selector, $label = null ) {
		$element->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => $name,
				'label' => $label ?? esc_html__( 'Borda', 'wc-custom-installments' ),
				'selector' => $selector,
			)
		);
	}


	/**
	 * Add a four-sided dimensions control.
	 *
	 * @since 6.0.0
	 * @param object $element | Elementor controls stack
	 * @param string $id | Control ID
	 * @param string $label | Control label
	 * @param string $selector | Full CSS selector
	 * @param string $property | CSS property the four values feed
	 * @param bool $important | Whether the declaration is `!important`
	 * @param bool $responsive | Whether the value can differ per breakpoint
	 * @return void
	 */
	public static function dimensions( $element, $id, $label, $selector, $property, $important = false, $responsive = true ) {
		$args = array(
			'label' => $label,
			'type' => Controls_Manager::DIMENSIONS,
			'size_units' => self::SPACING_UNITS,
			'selectors' => array(
				$selector => $property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}' . ( $important ? ' !important;' : ';' ),
			),
		);

		if ( $responsive ) {
			$element->add_responsive_control( $id, $args );
		} else {
			$element->add_control( $id, $args );
		}
	}


	/**
	 * Add the outer spacing control.
	 *
	 * @since 6.0.0
	 * @param object $element | Elementor controls stack
	 * @param string $id | Control ID
	 * @param string $selector | Full CSS selector
	 * @param bool $important | Whether the declaration is `!important`
	 * @return void
	 */
	public static function margin( $element, $id, $selector, $important = false ) {
		self::dimensions( $element, $id, esc_html__( 'Margem externa', 'wc-custom-installments' ), $selector, 'margin', $important );
	}


	/**
	 * Add the inner spacing control.
	 *
	 * @since 6.0.0
	 * @param object $element | Elementor controls stack
	 * @param string $id | Control ID
	 * @param string $selector | Full CSS selector
	 * @param bool $important | Whether the declaration is `!important`
	 * @return void
	 */
	public static function padding( $element, $id, $selector, $important = false ) {
		self::dimensions( $element, $id, esc_html__( 'Margem interna', 'wc-custom-installments' ), $selector, 'padding', $important );
	}


	/**
	 * Add the border radius control.
	 *
	 * Not responsive, as in 5.x: the value is stored under the same key either
	 * way, but a responsive control would add breakpoint keys 5.x never wrote.
	 *
	 * @since 6.0.0
	 * @param object $element | Elementor controls stack
	 * @param string $id | Control ID
	 * @param string $selector | Full CSS selector
	 * @param bool $important | Whether the declaration is `!important`
	 * @return void
	 */
	public static function border_radius( $element, $id, $selector, $important = false ) {
		self::dimensions( $element, $id, esc_html__( 'Raio da borda', 'wc-custom-installments' ), $selector, 'border-radius', $important, false );
	}


	/**
	 * Add a switch that hides an element.
	 *
	 * The switch writes `none` when on. The default is the display value the
	 * element gets when the switch is off, which is what lets a filter turn an
	 * element off by default without the switch reading as on.
	 *
	 * @since 6.0.0
	 * @param object $element | Elementor controls stack
	 * @param string $id | Control ID
	 * @param string $label | Control label
	 * @param string $selector | Full CSS selector
	 * @param string $default | Display value while shown
	 * @param bool $important | Whether the declaration is `!important`
	 * @return void
	 */
	public static function hide_switch( $element, $id, $label, $selector, $default = 'block', $important = false ) {
		$element->add_control(
			$id,
			array(
				'label' => $label,
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Ocultar', 'wc-custom-installments' ),
				'label_off' => esc_html__( 'Mostrar', 'wc-custom-installments' ),
				'return_value' => 'none',
				'default' => $default,
				'selectors' => array(
					$selector => 'display: {{VALUE}}' . ( $important ? ' !important;' : ';' ),
				),
			)
		);
	}


	/**
	 * Add a section styling one badge-shaped element.
	 *
	 * Typography, text colour, background, border, radius, both spacings and a
	 * hide switch: the same eight controls for every badge the price group
	 * carries, under IDs built from one prefix.
	 *
	 * @since 6.0.0
	 * @param object $element | Elementor controls stack
	 * @param array<string,mixed> $args {
	 *     @type string $section Section ID.
	 *     @type string $label Section label.
	 *     @type string $prefix Prefix of every control ID but the hide switch.
	 *     @type string $selector The badge selector, relative to the widget.
	 *     @type string $hidden_id ID of the hide switch.
	 *     @type string $hidden_label Label of the hide switch.
	 *     @type bool $with_amount Whether text rules also reach `.amount`.
	 *     @type bool $important Whether declarations are `!important`.
	 * }
	 * @return void
	 */
	public static function badge_section( $element, $args ) {
		$args = wp_parse_args( $args, array(
			'section' => '',
			'label' => '',
			'prefix' => '',
			'selector' => '',
			'hidden_id' => '',
			'hidden_label' => '',
			'with_amount' => true,
			'important' => true,
		) );

		$prefix = $args['prefix'];
		$important = (bool) $args['important'];
		$box = self::scoped( $args['selector'] );
		$text = $args['with_amount'] ? self::scoped( $args['selector'], $args['selector'] . ' .amount' ) : $box;
		$suffix = $important ? ' !important;' : ';';

		$element->start_controls_section(
			$args['section'],
			array(
				'label' => $args['label'],
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		self::typography( $element, $prefix . 'typography', $text );
		self::color( $element, $prefix . 'text_color', array( $text => 'color: {{VALUE}}' . $suffix ) );
		self::background_color( $element, $prefix . 'background_color', array( $box => 'background-color: {{VALUE}}' . $suffix ) );
		self::border( $element, $prefix . 'border', $box );
		self::border_radius( $element, $prefix . 'border_radius', $box, $important );
		self::margin( $element, $prefix . 'margin', $box, $important );
		self::padding( $element, $prefix . 'padding', $box, $important );

		if ( '' !== $args['hidden_id'] ) {
			self::hide_switch( $element, $args['hidden_id'], $args['hidden_label'], $box );
		}

		$element->end_controls_section();
	}


	/**
	 * Add every control styling the price group.
	 *
	 * The plugin's price widget and each decorated third-party widget carry
	 * the same seven sections. Only the IDs differ: 5.x prefixed the injected
	 * ones with `wci_` so they could not collide with the host widget's own,
	 * and both spellings are in saved pages.
	 *
	 * @since 6.0.0
	 * @param object $element | Elementor controls stack
	 * @param bool $injected | Whether the stack belongs to a third-party widget
	 * @return void
	 */
	public static function price_group( $element, $injected = false ) {
		$id = static function( $key ) use ( $injected ) {
			if ( ! $injected || 0 === strpos( $key, 'wci_' ) ) {
				return $key;
			}

			return 'wci_' . $key;
		};

		// The widget always started left-aligned and showing the old price;
		// only the decorated widgets asked the store's settings.
		$alignment = $injected ? Hook_Aliases::apply_filters( 'WC_Custom_Installments/Widgets/Align_Price_Group', 'left' ) : 'left';
		$old_price_display = $injected ? Hook_Aliases::apply_filters( 'WC_Custom_Installments/Widgets/Hidden_Old_Price', 'block' ) : 'block';

		$element->start_controls_section(
			$id('general_style_section'),
			array(
				'label' => esc_html__( 'Configurações gerais', 'wc-custom-installments' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$element->add_control(
			$injected ? 'wci_alignment_price_group' : 'alignment',
			array(
				'label' => esc_html__( 'Alinhamento do grupo de preços', 'wc-custom-installments' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'left' => array(
						'title' => esc_html__( 'Esquerda', 'wc-custom-installments' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centralizado', 'wc-custom-installments' ),
						'icon' => 'eicon-text-align-center',
					),
					'right' => array(
						'title' => esc_html__( 'Direita', 'wc-custom-installments' ),
						'icon' => 'eicon-text-align-right',
					),
				),
				'default' => $alignment,
				'selectors' => array(
					self::scoped('.woo-custom-installments-group') => 'justify-items: {{VALUE}};',
				),
			)
		);

		$element->end_controls_section();

		$element->start_controls_section(
			$id('price_style_section'),
			array(
				'label' => esc_html__( 'Estilos do preço', 'wc-custom-installments' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$element->start_controls_tabs('price_settings_tabs');

		$element->start_controls_tab(
			$id('main_price_tab'),
			array(
				'label' => esc_html__( 'Preço', 'wc-custom-installments' ),
			)
		);

		self::typography( $element, $id('main_price_typography'), self::scoped( '.woo-custom-installments-price', '.woo-custom-installments-price .amount' ) );
		self::color( $element, $id('main_price_text_color'), array(
			self::scoped( '.woo-custom-installments-price', '.woo-custom-installments-price .amount', '.woo-custom-installments-price.has-discount' ) => 'color: {{VALUE}}',
		) );

		$element->end_controls_tab();

		$element->start_controls_tab(
			$id('old_price_tab'),
			array(
				'label' => esc_html__( 'Preço antigo', 'wc-custom-installments' ),
			)
		);

		$old_price = self::scoped( '.woo-custom-installments-price.has-discount', '.woo-custom-installments-price.has-discount .amount' );

		self::typography( $element, $id('old_price_typography'), $old_price );
		self::color( $element, $id('old_price_text_color'), array( $old_price => 'color: {{VALUE}}' ) );
		self::hide_switch(
			$element,
			'wci_hidden_old_product_price',
			esc_html__( 'Ocultar preço antigo', 'wc-custom-installments' ),
			self::scoped('.woo-custom-installments-price.has-discount'),
			$old_price_display,
			true
		);

		$element->end_controls_tab();

		$element->start_controls_tab(
			$id('prefix_and_suffix_tab'),
			array(
				'label' => esc_html__( 'Prefixo e sufixo', 'wc-custom-installments' ),
			)
		);

		$affixes = self::scoped( '.woocommerce-price-suffix', '.woo-custom-installments-group.variable-range-price .woo-custom-installments-starting-from' );

		self::typography( $element, $id('prefix_and_suffix_typography'), $affixes );
		self::color( $element, $id('prefix_and_suffix_text_color'), array( $affixes => 'color: {{VALUE}} !important;' ) );

		$element->end_controls_tab();
		$element->end_controls_tabs();

		if ( Repository::is_active('enable_price_grid_in_widgets') ) {
			$element->add_control(
				$injected ? 'wci_enable_grid_price' : 'enable_grid_display',
				array(
					'label' => esc_html__( 'Ativar empilhamento de preços', 'wc-custom-installments' ),
					'type' => Controls_Manager::SWITCHER,
					'label_on' => esc_html__( 'Ativo', 'wc-custom-installments' ),
					'label_off' => esc_html__( 'Inativo', 'wc-custom-installments' ),
					'return_value' => 'yes',
					'default' => Hook_Aliases::apply_filters( 'WC_Custom_Installments/Widgets/Enable_Grid_Price', 'yes' ),
					'selectors' => array(
						self::scoped('.woo-custom-installments-group .woo-custom-installments-group-main-price') => 'flex-direction: column; align-items: flex-start;',
					),
				)
			);
		}

		self::margin( $element, $id('price_margin'), self::scoped('.woo-custom-installments-price'), true );
		self::padding( $element, $id('price_padding'), self::scoped('.woo-custom-installments-price'), true );
		self::hide_switch(
			$element,
			'wci_hidden_product_price',
			esc_html__( 'Ocultar preço do produto', 'wc-custom-installments' ),
			self::scoped('.woo-custom-installments-group-main-price'),
			'flex'
		);

		$element->end_controls_section();

		self::badge_section( $element, array(
			'section' => $id('pix_discount_style_section'),
			'label' => esc_html__( 'Preço com desconto no Pix', 'wc-custom-installments' ),
			'prefix' => $id('pix_discount_'),
			'selector' => '.woo-custom-installments-offer',
			'hidden_id' => 'wci_hidden_pix_discount',
			'hidden_label' => esc_html__( 'Ocultar desconto no Pix', 'wc-custom-installments' ),
		) );

		self::badge_section( $element, array(
			'section' => $id('ticket_discount_style_section'),
			'label' => esc_html__( 'Preço com desconto no Boleto', 'wc-custom-installments' ),
			'prefix' => $id('ticket_discount_'),
			'selector' => '.woo-custom-installments-ticket-discount',
			'hidden_id' => 'wci_hidden_ticket_discount',
			'hidden_label' => esc_html__( 'Ocultar desconto no boleto', 'wc-custom-installments' ),
		) );

		self::badge_section( $element, array(
			'section' => $id('best_installments_style_section'),
			'label' => esc_html__( 'Melhores parcelas', 'wc-custom-installments' ),
			'prefix' => $id('best_installments_'),
			'selector' => '.woo-custom-installments-card-container',
			'hidden_id' => 'wci_hidden_best_installments',
			'hidden_label' => esc_html__( 'Ocultar melhores parcelas', 'wc-custom-installments' ),
		) );

		self::badge_section( $element, array(
			'section' => $id('pix_economy_style_section'),
			'label' => esc_html__( 'Economia no Pix', 'wc-custom-installments' ),
			'prefix' => $id('pix_economy_'),
			'selector' => '.woo-custom-installments-economy-pix-badge',
			'hidden_id' => 'wci_hidden_pix_economy',
			'hidden_label' => esc_html__( 'Ocultar economia no Pix', 'wc-custom-installments' ),
		) );

		self::badge_section( $element, array(
			'section' => 'wci_sale_badge_style_section',
			'label' => esc_html__( 'Emblema de desconto', 'wc-custom-installments' ),
			'prefix' => 'wci_sale_badge_',
			'selector' => '.wci-sale-badge',
			'hidden_id' => 'wci_hidden_sale_badge',
			'hidden_label' => esc_html__( 'Ocultar emblema de desconto', 'wc-custom-installments' ),
			'with_amount' => false,
			'important' => false,
		) );
	}
}
