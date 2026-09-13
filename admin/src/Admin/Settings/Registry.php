<?php

namespace MeuMouse\WC_Custom_Installments\Admin\Settings;

use MeuMouse\WC_Custom_Installments\Admin\Default_Options;
use MeuMouse\WC_Custom_Installments\Core\Features;

defined('ABSPATH') || exit;

/**
 * Build the settings schema the Vue app renders.
 *
 * The schema is the contract between the two halves of the plugin: PHP decides
 * which sections, cards and fields exist, what type each one is and which
 * feature gates it; the browser renders that and never invents a field of its
 * own. Every label and description is translated here, so the payload reaches
 * the browser already in the site's language.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Admin\Settings
 * @author MeuMouse.com
 */
class Registry {

	/**
	 * Build the full schema.
	 *
	 * @since 6.0.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_schema() {
		$schema = array(
			self::section_installments(),
			self::section_discounts(),
			self::section_interests(),
			self::section_payment_methods(),
			self::section_texts(),
			self::section_styles(),
			self::section_advanced(),
		);

		/**
		 * Filter the settings schema.
		 *
		 * This is how an add-on appends its own sections, cards and fields.
		 *
		 * @since 6.0.0
		 * @param array<int,array<string,mixed>> $schema | Settings schema
		 * @return array<int,array<string,mixed>>
		 */
		$schema = (array) apply_filters( 'WC_Custom_Installments/Admin/Settings_Schema', $schema );

		return self::resolve_locks( $schema );
	}


	/**
	 * Cached field definitions for the current request.
	 *
	 * @since 6.0.0
	 * @var array<string,array<string,mixed>>|null
	 */
	private static $definitions = null;


	/**
	 * Flatten the schema into a field definition map, keyed by setting name.
	 *
	 * The repository sanitizes against this, so a field absent from the schema
	 * cannot be written through the settings endpoint.
	 *
	 * @since 6.0.0
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_field_definitions() {
		if ( null !== self::$definitions ) {
			return self::$definitions;
		}

		$definitions = array();

		foreach ( self::get_schema() as $section ) {
			foreach ( (array) ( $section['cards'] ?? array() ) as $card ) {
				foreach ( (array) ( $card['fields'] ?? array() ) as $field ) {
					if ( empty( $field['key'] ) ) {
						continue;
					}

					$definitions[ $field['key'] ] = $field;

					// A measurement field carries two settings: the amount and
					// the unit it is expressed in. Without its own definition
					// the unit would never be written, because the repository
					// only writes what the schema declares.
					if ( ! empty( $field['unit_key'] ) ) {
						$definitions[ $field['unit_key'] ] = array(
							'type' => 'select',
							'key' => $field['unit_key'],
							'label' => $field['label'],
							'description' => '',
							'default' => Default_Options::get_defaults()[ $field['unit_key'] ] ?? 'rem',
							'feature' => $field['feature'] ?? '',
							'options' => array_map(
								static function( $unit ) {
									return array( 'value' => $unit, 'label' => $unit );
								},
								(array) ( $field['units'] ?? array() )
							),
						);
					}
				}
			}
		}

		self::$definitions = $definitions;

		return self::$definitions;
	}


	/**
	 * Forget the cached field definitions.
	 *
	 * Needed after a licence change, which flips the locked state of every
	 * gated field, and by the tests.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public static function flush() {
		self::$definitions = null;
	}


	/**
	 * Installments section.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function section_installments() {
		return array(
			'id' => 'installments',
			'icon' => 'credit-card',
			'title' => __( 'Parcelas', 'wc-custom-installments' ),
			'description' => __( 'Quantas parcelas a loja oferece, quanto custam e onde aparecem.', 'wc-custom-installments' ),
			'cards' => array(
				array(
					'id' => 'installments-plan',
					'title' => __( 'Parcelamento', 'wc-custom-installments' ),
					'description' => __( 'As regras usadas para montar cada parcela exibida na loja.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_installments_all_products',
							__( 'Exibir parcelas nos produtos', 'wc-custom-installments' ),
							__( 'Exibe o parcelamento na listagem de produtos e na página do produto.', 'wc-custom-installments' )
						),
						self::number(
							'max_qtd_installments',
							__( 'Máximo de parcelas', 'wc-custom-installments' ),
							__( 'O maior número de parcelas que a loja aceita.', 'wc-custom-installments' ),
							array( 'min' => 1, 'max' => 48, 'placeholder' => '12' )
						),
						self::number(
							'max_qtd_installments_without_fee',
							__( 'Parcelas sem juros', 'wc-custom-installments' ),
							__( 'Quantas delas são oferecidas sem juros.', 'wc-custom-installments' ),
							array( 'min' => 0, 'max' => 48, 'placeholder' => '3' )
						),
						self::number(
							'fee_installments_global',
							__( 'Taxa de juros mensal (%)', 'wc-custom-installments' ),
							__( 'Aplicada a todas as parcelas além das sem juros.', 'wc-custom-installments' ),
							array( 'step' => '0.01', 'min' => 0, 'placeholder' => '2.0' )
						),
						self::number(
							'min_value_installments',
							__( 'Valor mínimo da parcela', 'wc-custom-installments' ),
							__( 'Parcelas menores que este valor não são oferecidas.', 'wc-custom-installments' ),
							array( 'step' => '0.01', 'min' => 0, 'placeholder' => '20' )
						),
					),
				),
				array(
					'id' => 'installments-fees',
					'title' => __( 'Juros por parcela', 'wc-custom-installments' ),
					'description' => __( 'Substitua a taxa mensal única por uma taxa para cada quantidade de parcelas, igual à que sua adquirente cobra.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'set_fee_per_installment',
							__( 'Definir os juros de cada parcela', 'wc-custom-installments' ),
							__( 'Cobra uma taxa diferente para cada quantidade de parcelas, em vez de uma taxa mensal única.', 'wc-custom-installments' ),
							array( 'feature' => 'fee_per_installment' )
						),
						self::field(
							'installment-fees',
							'custom_fee_installments',
							__( 'Tabela de juros', 'wc-custom-installments' ),
							__( 'Uma taxa por quantidade de parcelas. Deixe uma linha vazia para usar a taxa global.', 'wc-custom-installments' ),
							array(
								'feature' => 'fee_per_installment',
								'depends_on' => array( 'set_fee_per_installment' => 'yes' ),
							)
						),
					),
				),
				array(
					'id' => 'installments-display',
					'title' => __( 'Onde as parcelas aparecem', 'wc-custom-installments' ),
					'description' => __( 'Posição e apresentação das informações de parcelamento.', 'wc-custom-installments' ),
					'fields' => array(
						self::select(
							'get_type_best_installments',
							__( 'Parcela em destaque', 'wc-custom-installments' ),
							__( 'Qual parcela aparece em destaque abaixo do preço.', 'wc-custom-installments' ),
							array(
								array( 'value' => 'best_installment_without_fee', 'label' => __( 'A maior parcela sem juros', 'wc-custom-installments' ) ),
								array( 'value' => 'best_installment_with_fee', 'label' => __( 'A maior parcela, com juros', 'wc-custom-installments' ) ),
								array( 'value' => 'min_value_installment', 'label' => __( 'A menor parcela permitida', 'wc-custom-installments' ) ),
							)
						),
						self::select(
							'hook_display_best_installments',
							__( 'Exibir a parcela em destaque em', 'wc-custom-installments' ),
							__( 'Em quais páginas da loja ela aparece.', 'wc-custom-installments' ),
							self::display_hook_options()
						),
						self::select(
							'display_installment_type',
							__( 'Detalhes das parcelas', 'wc-custom-installments' ),
							__( 'Como a tabela completa de parcelas é apresentada ao cliente.', 'wc-custom-installments' ),
							array(
								array( 'value' => 'popup', 'label' => __( 'Em um popup', 'wc-custom-installments' ) ),
								array( 'value' => 'table', 'label' => __( 'Como tabela na página', 'wc-custom-installments' ) ),
								array( 'value' => 'none', 'label' => __( 'Não exibir os detalhes', 'wc-custom-installments' ) ),
							)
						),
						self::toggle(
							'display_installments_cart',
							__( 'Exibir parcelas no carrinho', 'wc-custom-installments' ),
							__( 'Exibe o parcelamento do total do carrinho.', 'wc-custom-installments' )
						),
						self::select(
							'hook_payment_form_single_product',
							__( 'Posição do bloco de formas de pagamento', 'wc-custom-installments' ),
							__( 'Onde as formas de pagamento aceitas aparecem na página do produto.', 'wc-custom-installments' ),
							array(
								array( 'value' => 'before_cart', 'label' => __( 'Antes do formulário de adicionar ao carrinho', 'wc-custom-installments' ) ),
								array( 'value' => 'after_cart', 'label' => __( 'Depois do formulário de adicionar ao carrinho', 'wc-custom-installments' ) ),
								array( 'value' => 'custom', 'label' => __( 'Em um gancho personalizado', 'wc-custom-installments' ) ),
							)
						),
						self::text(
							'set_custom_hook_payment_form',
							__( 'Gancho personalizado', 'wc-custom-installments' ),
							__( 'O gancho de ação ao qual o bloco de formas de pagamento é anexado.', 'wc-custom-installments' ),
							array(
								'placeholder' => 'woocommerce_single_product_summary',
								'depends_on' => array( 'hook_payment_form_single_product' => 'custom' ),
							)
						),
					),
				),
				array(
					'id' => 'installments-variable',
					'title' => __( 'Produtos variáveis', 'wc-custom-installments' ),
					'description' => __( 'Como os preços se comportam quando o cliente escolhe uma variação ou altera a quantidade.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'remove_price_range',
							__( 'Substituir a faixa de preço', 'wc-custom-installments' ),
							__( 'Exibe um único preço inicial em vez da faixa "de — até" nos produtos variáveis.', 'wc-custom-installments' ),
							array( 'feature' => 'variable_price_range' )
						),
						self::toggle(
							'update_price_with_quantity',
							__( 'Atualizar o preço com a quantidade', 'wc-custom-installments' ),
							__( 'Recalcula o preço exibido e as parcelas conforme o cliente altera a quantidade.', 'wc-custom-installments' ),
							array( 'feature' => 'variable_price_range' )
						),
						self::text(
							'update_range_price_triggers',
							__( 'Gatilhos extras de variação', 'wc-custom-installments' ),
							__( 'Seletores CSS, separados por vírgula, para temas que trocam as variações do próprio jeito em vez de usar o formulário do WooCommerce.', 'wc-custom-installments' ),
							array(
								'placeholder' => '.my-theme-swatch, .variation-button',
								'feature' => 'variable_price_range',
							)
						),
					),
				),
			),
		);
	}


	/**
	 * Discounts section.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function section_discounts() {
		return array(
			'id' => 'discounts',
			'icon' => 'tag',
			'title' => __( 'Descontos', 'wc-custom-installments' ),
			'description' => __( 'Recompense os clientes que pagam à vista.', 'wc-custom-installments' ),
			'cards' => array(
				array(
					'id' => 'discounts-main',
					'title' => __( 'Desconto no preço do produto', 'wc-custom-installments' ),
					'description' => __( 'Um único desconto aplicado ao preço exibido, para pagamentos à vista como o Pix.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_all_discount_options',
							__( 'Ativar descontos', 'wc-custom-installments' ),
							__( 'Ativa os recursos de desconto para toda a loja.', 'wc-custom-installments' )
						),
						self::select(
							'product_price_discount_method',
							__( 'Tipo de desconto', 'wc-custom-installments' ),
							__( 'Se o desconto é uma porcentagem do preço ou um valor fixo.', 'wc-custom-installments' ),
							self::discount_method_options()
						),
						self::number(
							'discount_main_price',
							__( 'Valor do desconto', 'wc-custom-installments' ),
							__( 'A porcentagem ou o valor descontado do preço.', 'wc-custom-installments' ),
							array( 'step' => '0.01', 'min' => 0, 'placeholder' => '10' )
						),
						self::select(
							'display_discount_price_hook',
							__( 'Exibir o preço com desconto em', 'wc-custom-installments' ),
							__( 'Em quais páginas da loja ela aparece.', 'wc-custom-installments' ),
							self::display_hook_options()
						),
						self::toggle(
							'include_shipping_value_in_discounts',
							__( 'Incluir o frete no desconto', 'wc-custom-installments' ),
							__( 'Aplica o desconto também ao frete, além dos produtos.', 'wc-custom-installments' )
						),
						self::toggle(
							'display_tag_discount_price_checkout',
							__( 'Exibir o desconto na finalização da compra', 'wc-custom-installments' ),
							__( 'Exibe uma linha nos totais do pedido informando quanto foi descontado.', 'wc-custom-installments' )
						),
					),
				),
				array(
					'id' => 'discounts-gateways',
					'title' => __( 'Descontos por forma de pagamento', 'wc-custom-installments' ),
					'description' => __( 'Um desconto diferente para cada forma de pagamento, aplicado aos totais do carrinho e da finalização da compra.', 'wc-custom-installments' ),
					'fields' => array(
						self::field(
							'gateway-rates',
							'gateway_discounts',
							__( 'Desconto por forma de pagamento', 'wc-custom-installments' ),
							__( 'Defina um valor para cada forma de pagamento aceita pela loja.', 'wc-custom-installments' ),
							array(
								'feature' => 'gateway_discounts',
								'gateways' => self::get_payment_gateways(),
								'methods' => self::discount_method_options(),
							)
						),
					),
				),
				array(
					'id' => 'discounts-quantity',
					'title' => __( 'Descontos por quantidade', 'wc-custom-installments' ),
					'description' => __( 'Recompense os clientes que compram mais unidades do mesmo produto.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_functions_discount_per_quantity',
							__( 'Ativar descontos por quantidade', 'wc-custom-installments' ),
							__( 'Aplica um desconto quando o cliente atinge uma quantidade mínima.', 'wc-custom-installments' ),
							array( 'feature' => 'quantity_discounts' )
						),
						self::select(
							'enable_discount_per_quantity_method',
							__( 'Aplica-se a', 'wc-custom-installments' ),
							__( 'Se a regra é definida uma vez para a loja ou por produto.', 'wc-custom-installments' ),
							array(
								array( 'value' => 'global', 'label' => __( 'Todos os produtos', 'wc-custom-installments' ) ),
								array( 'value' => 'per_product', 'label' => __( 'Cada produto individualmente', 'wc-custom-installments' ) ),
							),
							array(
								'feature' => 'quantity_discounts',
								'depends_on' => array( 'enable_functions_discount_per_quantity' => 'yes' ),
							)
						),
						self::number(
							'set_quantity_enable_discount',
							__( 'Quantidade mínima', 'wc-custom-installments' ),
							__( 'Quantas unidades o cliente precisa comprar para o desconto valer.', 'wc-custom-installments' ),
							array(
								'min' => 1,
								'feature' => 'quantity_discounts',
								'depends_on' => array( 'enable_discount_per_quantity_method' => 'global' ),
							)
						),
						self::select(
							'discount_per_quantity_method',
							__( 'Tipo de desconto', 'wc-custom-installments' ),
							__( 'Se o desconto por quantidade é uma porcentagem ou um valor fixo.', 'wc-custom-installments' ),
							self::discount_method_options(),
							array(
								'feature' => 'quantity_discounts',
								'depends_on' => array( 'enable_discount_per_quantity_method' => 'global' ),
							)
						),
						self::number(
							'value_for_discount_per_quantity',
							__( 'Valor do desconto', 'wc-custom-installments' ),
							__( 'A porcentagem ou o valor descontado quando o mínimo é atingido.', 'wc-custom-installments' ),
							array(
								'step' => '0.01',
								'min' => 0,
								'feature' => 'quantity_discounts',
								'depends_on' => array( 'enable_discount_per_quantity_method' => 'global' ),
							)
						),
						self::toggle(
							'enable_discount_per_unit_discount_per_quantity',
							__( 'Aplicar a cada unidade', 'wc-custom-installments' ),
							__( 'Aplica o desconto em cada unidade em vez de uma vez só na linha. Em três unidades a 10%, é a diferença entre 10% e 30%.', 'wc-custom-installments' ),
							array(
								'feature' => 'quantity_discounts',
								'depends_on' => array( 'enable_functions_discount_per_quantity' => 'yes' ),
							)
						),
						self::toggle(
							'message_discount_per_quantity',
							__( 'Anunciar o desconto por quantidade', 'wc-custom-installments' ),
							__( 'Exibe uma mensagem dizendo ao cliente como ganhá-lo.', 'wc-custom-installments' ),
							array( 'feature' => 'quantity_discounts' )
						),
						self::select(
							'discount_per_qtd_message_method',
							__( 'Onde a mensagem aparece', 'wc-custom-installments' ),
							__( 'Automaticamente ao lado do preço, ou onde você colocar o widget do Elementor.', 'wc-custom-installments' ),
							array(
								array( 'value' => 'hook', 'label' => __( 'Automaticamente, ao lado do preço', 'wc-custom-installments' ) ),
								array( 'value' => 'widget', 'label' => __( 'Somente onde eu colocar o widget', 'wc-custom-installments' ) ),
							),
							array(
								'feature' => 'quantity_discounts',
								'depends_on' => array( 'message_discount_per_quantity' => 'yes' ),
							)
						),
					),
				),
				array(
					'id' => 'discounts-ticket',
					'title' => __( 'Desconto no boleto', 'wc-custom-installments' ),
					'description' => __( 'Um desconto separado para clientes que pagam com boleto bancário.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_ticket_discount_main_price',
							__( 'Ativar o desconto no boleto', 'wc-custom-installments' ),
							__( 'Exibe um segundo preço com desconto para pagamentos no boleto.', 'wc-custom-installments' ),
							array( 'feature' => 'ticket_discount' )
						),
						self::select(
							'discount_method_ticket',
							__( 'Tipo de desconto', 'wc-custom-installments' ),
							__( 'Se o desconto no boleto é uma porcentagem ou um valor fixo.', 'wc-custom-installments' ),
							self::discount_method_options(),
							array(
								'feature' => 'ticket_discount',
								'depends_on' => array( 'enable_ticket_discount_main_price' => 'yes' ),
							)
						),
						self::number(
							'discount_ticket',
							__( 'Valor do desconto', 'wc-custom-installments' ),
							__( 'A porcentagem ou o valor descontado nos pagamentos com boleto.', 'wc-custom-installments' ),
							array(
								'step' => '0.01',
								'min' => 0,
								'feature' => 'ticket_discount',
								'depends_on' => array( 'enable_ticket_discount_main_price' => 'yes' ),
							)
						),
						self::select(
							'display_discount_ticket_hook',
							__( 'Exibir o preço no boleto em', 'wc-custom-installments' ),
							__( 'Em quais páginas da loja ela aparece.', 'wc-custom-installments' ),
							self::display_hook_options(),
							array(
								'feature' => 'ticket_discount',
								'depends_on' => array( 'enable_ticket_discount_main_price' => 'yes' ),
							)
						),
					),
				),
				array(
					'id' => 'discounts-custom-price',
					'title' => __( 'Reescrever o preço exibido', 'wc-custom-installments' ),
					'description' => __( 'Substitua o preço exibido na loja por um seu e adicione uma observação depois dele. O carrinho e a finalização da compra não são afetados.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'custom_text_after_price',
							__( 'Reescrever o preço exibido', 'wc-custom-installments' ),
							__( 'Exibe o seu próprio preço no lugar do preço do WooCommerce em produtos simples e variáveis.', 'wc-custom-installments' )
						),
						self::text(
							'custom_text_after_price_front',
							__( 'Observação após o preço', 'wc-custom-installments' ),
							__( 'Por exemplo: "no Pix".', 'wc-custom-installments' ),
							array( 'depends_on' => array( 'custom_text_after_price' => 'yes' ) )
						),
						self::toggle(
							'add_discount_custom_product_price',
							__( 'Aplicar um desconto', 'wc-custom-installments' ),
							__( 'Subtrai uma porcentagem do preço antes de exibi-lo.', 'wc-custom-installments' ),
							array( 'depends_on' => array( 'custom_text_after_price' => 'yes' ) )
						),
						self::number(
							'discount_value_custom_product_price',
							__( 'Desconto (%)', 'wc-custom-installments' ),
							__( 'Quanto descontar do preço exibido.', 'wc-custom-installments' ),
							array(
								'step' => '0.01',
								'min' => 0,
								'max' => 100,
								'depends_on' => array( 'add_discount_custom_product_price' => 'yes' ),
							)
						),
					),
				),
				array(
					'id' => 'discounts-badges',
					'title' => __( 'Selos de economia', 'wc-custom-installments' ),
					'description' => __( 'Destaca quanto o cliente economiza.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_economy_pix_badge',
							__( 'Exibir o selo de economia no Pix', 'wc-custom-installments' ),
							__( 'Mostra quanto o cliente economiza pagando com Pix.', 'wc-custom-installments' ),
							array( 'feature' => 'pix_economy_badge' )
						),
						self::select(
							'display_economy_pix_hook',
							__( 'Exibir o selo de economia em', 'wc-custom-installments' ),
							__( 'Em quais páginas da loja ela aparece.', 'wc-custom-installments' ),
							self::display_hook_options(),
							array(
								'feature' => 'pix_economy_badge',
								'depends_on' => array( 'enable_economy_pix_badge' => 'yes' ),
							)
						),
						self::toggle(
							'enable_sale_badge',
							__( 'Exibir o selo de promoção', 'wc-custom-installments' ),
							__( 'Exibe a porcentagem de desconto sobre os produtos em promoção.', 'wc-custom-installments' ),
							array( 'feature' => 'sale_badge' )
						),
					),
				),
			),
		);
	}


	/**
	 * Interests section.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function section_interests() {
		return array(
			'id' => 'interests',
			'icon' => 'percentage',
			'title' => __( 'Juros', 'wc-custom-installments' ),
			'description' => __( 'Repassa ao pedido o custo de processamento de uma forma de pagamento.', 'wc-custom-installments' ),
			'cards' => array(
				array(
					'id' => 'interests-general',
					'title' => __( 'Juros no total do pedido', 'wc-custom-installments' ),
					'description' => __( 'Adiciona uma taxa aos totais do carrinho e da finalização da compra conforme a forma de pagamento escolhida.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_all_interest_options',
							__( 'Ativar juros', 'wc-custom-installments' ),
							__( 'Ativa os recursos de juros para toda a loja.', 'wc-custom-installments' )
						),
						self::toggle(
							'display_tag_interest_checkout',
							__( 'Exibir os juros na finalização da compra', 'wc-custom-installments' ),
							__( 'Exibe uma linha nos totais do pedido informando quanto foi acrescentado.', 'wc-custom-installments' )
						),
					),
				),
				array(
					'id' => 'interests-gateways',
					'title' => __( 'Juros por forma de pagamento', 'wc-custom-installments' ),
					'description' => __( 'Uma taxa diferente para cada forma de pagamento, aplicada aos totais do carrinho e da finalização da compra.', 'wc-custom-installments' ),
					'fields' => array(
						self::field(
							'gateway-rates',
							'gateway_interests',
							__( 'Taxa de juros por forma de pagamento', 'wc-custom-installments' ),
							__( 'Defina um valor para cada forma de pagamento aceita pela loja.', 'wc-custom-installments' ),
							array(
								'feature' => 'gateway_interests',
								'gateways' => self::get_payment_gateways(),
								'methods' => self::discount_method_options(),
							)
						),
					),
				),
			),
		);
	}


	/**
	 * Payment methods section.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function section_payment_methods() {
		return array(
			'id' => 'payment-methods',
			'icon' => 'wallet',
			'title' => __( 'Formas de pagamento', 'wc-custom-installments' ),
			'description' => __( 'O bloco de formas de pagamento aceitas exibido na página do produto.', 'wc-custom-installments' ),
			'cards' => array(
				array(
					'id' => 'payment-methods-containers',
					'title' => __( 'Quais formas exibir', 'wc-custom-installments' ),
					'description' => __( 'Cada uma vira uma linha no bloco de formas de pagamento.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_pix_method_payment_form',
							__( 'Pix', 'wc-custom-installments' ),
							__( 'Exibe a linha do Pix, com o selo e as instruções.', 'wc-custom-installments' )
						),
						self::toggle(
							'enable_instant_approval_badge',
							__( 'Selo de aprovação imediata', 'wc-custom-installments' ),
							__( 'Adiciona um aviso de "aprovação imediata" ao lado do selo do Pix.', 'wc-custom-installments' ),
							array( 'depends_on' => array( 'enable_pix_method_payment_form' => 'yes' ) )
						),
						self::toggle(
							'enable_ticket_method_payment_form',
							__( 'Boleto bancário', 'wc-custom-installments' ),
							__( 'Exibe a linha do boleto, com o selo e as instruções.', 'wc-custom-installments' )
						),
						self::toggle(
							'enable_credit_card_method_payment_form',
							__( 'Cartão de crédito', 'wc-custom-installments' ),
							__( 'Exibe a linha do cartão de crédito e as bandeiras que você aceita.', 'wc-custom-installments' )
						),
						self::toggle(
							'enable_debit_card_method_payment_form',
							__( 'Cartão de débito', 'wc-custom-installments' ),
							__( 'Exibe a linha do cartão de débito e as bandeiras que você aceita.', 'wc-custom-installments' )
						),
					),
				),
				array(
					'id' => 'payment-methods-credit-brands',
					'title' => __( 'Bandeiras de cartão de crédito', 'wc-custom-installments' ),
					'description' => __( 'Os selos exibidos na linha do cartão de crédito.', 'wc-custom-installments' ),
					'depends_on' => array( 'enable_credit_card_method_payment_form' => 'yes' ),
					'fields' => self::brand_fields('credit'),
				),
				array(
					'id' => 'payment-methods-debit-brands',
					'title' => __( 'Bandeiras de cartão de débito', 'wc-custom-installments' ),
					'description' => __( 'Os selos exibidos na linha do cartão de débito.', 'wc-custom-installments' ),
					'depends_on' => array( 'enable_debit_card_method_payment_form' => 'yes' ),
					'fields' => self::brand_fields('debit'),
				),
			),
		);
	}


	/**
	 * Texts section.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function section_texts() {
		return array(
			'id' => 'texts',
			'icon' => 'paragraph',
			'title' => __( 'Textos', 'wc-custom-installments' ),
			'description' => __( 'Todos os textos que o plugin exibe na loja.', 'wc-custom-installments' ),
			'cards' => array(
				array(
					'id' => 'texts-price',
					'title' => __( 'Em volta do preço', 'wc-custom-installments' ),
					'description' => __( 'As palavras em volta do preço com desconto e da linha de parcelamento.', 'wc-custom-installments' ),
					'fields' => array(
						self::text( 'text_before_price', __( 'Antes do preço com desconto', 'wc-custom-installments' ), __( 'Por exemplo: "À vista".', 'wc-custom-installments' ) ),
						self::text( 'text_after_price', __( 'Depois do preço com desconto', 'wc-custom-installments' ), __( 'Por exemplo: "no Pix".', 'wc-custom-installments' ) ),
						self::text( 'text_initial_variables', __( 'Antes do preço de um produto variável', 'wc-custom-installments' ), __( 'Por exemplo: "A partir de".', 'wc-custom-installments' ) ),
						self::text( 'text_with_fee_installments', __( 'Sufixo com juros', 'wc-custom-installments' ), __( 'Adicionado ao final de uma parcela com juros.', 'wc-custom-installments' ) ),
						self::text( 'text_without_fee_installments', __( 'Sufixo sem juros', 'wc-custom-installments' ), __( 'Adicionado ao final de uma parcela sem juros.', 'wc-custom-installments' ) ),
					),
				),
				array(
					'id' => 'texts-templates',
					'title' => __( 'Modelos de parcela', 'wc-custom-installments' ),
					'description' => __( 'Reescreva a linha de parcelamento por completo. Variáveis disponíveis: {{ parcelas }}, {{ valor }}, {{ juros }}.', 'wc-custom-installments' ),
					'fields' => array(
						self::placeholder_text( 'text_display_installments_loop', __( 'Na listagem de produtos', 'wc-custom-installments' ), '', array( 'feature' => 'custom_placeholders' ) ),
						self::placeholder_text( 'text_display_installments_single_product', __( 'Na página do produto', 'wc-custom-installments' ), '', array( 'feature' => 'custom_placeholders' ) ),
						self::placeholder_text( 'text_display_installments_payment_forms', __( 'Na tabela de parcelas', 'wc-custom-installments' ), '', array( 'feature' => 'custom_placeholders' ) ),
					),
				),
				array(
					'id' => 'texts-payment-methods',
					'title' => __( 'Bloco de formas de pagamento', 'wc-custom-installments' ),
					'description' => __( 'Títulos dentro do bloco de formas de pagamento aceitas.', 'wc-custom-installments' ),
					'fields' => array(
						self::text( 'text_container_payment_forms', __( 'Título do bloco', 'wc-custom-installments' ), '' ),
						self::text( 'text_pix_container', __( 'Título do Pix', 'wc-custom-installments' ), '' ),
						self::text( 'text_ticket_container', __( 'Título do boleto', 'wc-custom-installments' ), '' ),
						self::textarea( 'text_instructions_ticket_container', __( 'Instruções do boleto', 'wc-custom-installments' ), __( 'Exibido abaixo do selo do boleto.', 'wc-custom-installments' ) ),
						self::text( 'text_credit_card_container', __( 'Título do cartão de crédito', 'wc-custom-installments' ), '' ),
						self::text( 'text_debit_card_container', __( 'Título do cartão de débito', 'wc-custom-installments' ), '' ),
						self::text( 'text_table_installments', __( 'Título da tabela de parcelas', 'wc-custom-installments' ), '' ),
						self::text( 'text_button_installments', __( 'Link de detalhes das parcelas', 'wc-custom-installments' ), __( 'O link que abre a tabela completa de parcelas.', 'wc-custom-installments' ) ),
					),
				),
				array(
					'id' => 'texts-badges',
					'title' => __( 'Selos e mensagens', 'wc-custom-installments' ),
					'description' => __( 'Textos do selo de economia, do preço no boleto e da mensagem de desconto por quantidade.', 'wc-custom-installments' ),
					'fields' => array(
						self::text(
							'text_economy_pix_badge',
							__( 'Selo de economia no Pix', 'wc-custom-installments' ),
							__( 'Use %s onde o valor economizado deve aparecer.', 'wc-custom-installments' ),
							array( 'feature' => 'pix_economy_badge' )
						),
						self::text( 'text_before_discount_ticket', __( 'Antes do preço no boleto', 'wc-custom-installments' ), '', array( 'feature' => 'ticket_discount' ) ),
						self::text( 'text_after_discount_ticket', __( 'Depois do preço no boleto', 'wc-custom-installments' ), '', array( 'feature' => 'ticket_discount' ) ),
						self::text(
							'text_discount_per_quantity_message',
							__( 'Mensagem de desconto por quantidade', 'wc-custom-installments' ),
							__( 'Use %d para a quantidade e %s para o desconto.', 'wc-custom-installments' ),
							array( 'feature' => 'quantity_discounts' )
						),
					),
				),
			),
		);
	}


	/**
	 * Styles section.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function section_styles() {
		return array(
			'id' => 'styles',
			'icon' => 'palette',
			'title' => __( 'Aparência', 'wc-custom-installments' ),
			'description' => __( 'Como o plugin aparece dentro do seu tema.', 'wc-custom-installments' ),
			'cards' => array(
				array(
					'id' => 'styles-popup',
					'title' => __( 'Popup de parcelas', 'wc-custom-installments' ),
					'description' => __( 'O link que abre a tabela de parcelas e o espaçamento em volta dele.', 'wc-custom-installments' ),
					'fields' => array(
						self::color( 'button_popup_color', __( 'Cor do link', 'wc-custom-installments' ), __( 'Cor do link de detalhes das parcelas.', 'wc-custom-installments' ) ),
						self::select(
							'button_popup_size',
							__( 'Tamanho do link', 'wc-custom-installments' ),
							'',
							array(
								array( 'value' => 'small', 'label' => __( 'Pequeno', 'wc-custom-installments' ) ),
								array( 'value' => 'normal', 'label' => __( 'Normal', 'wc-custom-installments' ) ),
								array( 'value' => 'large', 'label' => __( 'Grande', 'wc-custom-installments' ) ),
							)
						),
						self::unit( 'margin_top_popup_installments', 'unit_margin_top_popup_installments', __( 'Espaço acima', 'wc-custom-installments' ), '' ),
						self::unit( 'margin_bottom_popup_installments', 'unit_margin_bottom_popup_installments', __( 'Espaço abaixo', 'wc-custom-installments' ), '' ),
						self::unit( 'border_radius_popup_installments', 'unit_border_radius_popup_installments', __( 'Arredondamento dos cantos', 'wc-custom-installments' ), '' ),
					),
				),
				array(
					'id' => 'styles-loop',
					'title' => __( 'Listagem de produtos', 'wc-custom-installments' ),
					'description' => __( 'Alinhamento do grupo de preços dentro dos cards de produto.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'center_group_elements_loop',
							__( 'Centralizar o grupo de preços', 'wc-custom-installments' ),
							__( 'Útil para temas cujos cards de produto são centralizados.', 'wc-custom-installments' )
						),
						self::textarea(
							'selectors_group_for_center_elements',
							__( 'Seletores CSS', 'wc-custom-installments' ),
							__( 'Os seletores aos quais a regra de centralização é aplicada. Altere apenas se o seu tema precisar.', 'wc-custom-installments' ),
							array( 'depends_on' => array( 'center_group_elements_loop' => 'yes' ) )
						),
					),
				),
				array(
					'id' => 'styles-icons',
					'title' => __( 'Ícones', 'wc-custom-installments' ),
					'description' => __( 'Como são exibidos os ícones ao lado de cada elemento.', 'wc-custom-installments' ),
					'fields' => array(
						self::select(
							'icon_format_elements',
							__( 'Formato dos ícones', 'wc-custom-installments' ),
							__( 'O SVG embutido funciona em qualquer lugar; as fontes de ícones precisam que o tema carregue o Font Awesome.', 'wc-custom-installments' ),
							array(
								array( 'value' => 'svg', 'label' => __( 'SVG embutido', 'wc-custom-installments' ) ),
								array( 'value' => 'class', 'label' => __( 'Classe do Font Awesome', 'wc-custom-installments' ) ),
							)
						),
					),
				),
				array(
					'id' => 'styles-elements',
					'title' => __( 'Design dos elementos', 'wc-custom-installments' ),
					'description' => __( 'Tipografia, espaçamento e cores para cada elemento do grupo de preços, por breakpoint.', 'wc-custom-installments' ),
					'fields' => array(
						self::field(
							'element-design',
							'elements_design',
							__( 'Controles de design', 'wc-custom-installments' ),
							__( 'Ordene os elementos e estilize cada um para celular e desktop.', 'wc-custom-installments' ),
							array( 'feature' => 'advanced_design' )
						),
					),
				),
			),
		);
	}


	/**
	 * Advanced section.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function section_advanced() {
		return array(
			'id' => 'advanced',
			'icon' => 'cog',
			'title' => __( 'Avançado', 'wc-custom-installments' ),
			'description' => __( 'Integrações, dados estruturados e manutenção.', 'wc-custom-installments' ),
			'cards' => array(
				array(
					'id' => 'advanced-integrations',
					'title' => __( 'Integrações', 'wc-custom-installments' ),
					'description' => __( 'Construtores de páginas e plugins de SEO.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_elementor_widgets',
							__( 'Widgets do Elementor', 'wc-custom-installments' ),
							__( 'Registra os widgets do plugin no editor do Elementor.', 'wc-custom-installments' )
						),
						self::toggle(
							'enable_price_grid_in_widgets',
							__( 'Grupo de preços dentro dos widgets', 'wc-custom-installments' ),
							__( 'Exibe o grupo de preços completo onde um widget mostra o preço de um produto.', 'wc-custom-installments' )
						),
						self::toggle(
							'display_discount_price_schema',
							__( 'Preço com desconto nos dados estruturados', 'wc-custom-installments' ),
							__( 'Informa aos buscadores o preço com desconto em vez do preço de tabela.', 'wc-custom-installments' ),
							array( 'feature' => 'seo_schema' )
						),
						self::toggle(
							'enable_post_meta_feed_xml_price',
							__( 'Preço com desconto nos feeds de produtos', 'wc-custom-installments' ),
							__( 'Armazena o preço com desconto em um metadado para os plugins de feed de compras lerem.', 'wc-custom-installments' ),
							array( 'feature' => 'seo_schema' )
						),
					),
				),
				array(
					'id' => 'advanced-maintenance',
					'title' => __( 'Manutenção', 'wc-custom-installments' ),
					'description' => __( 'Diagnóstico e dados.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_debug_mode',
							__( 'Modo de depuração', 'wc-custom-installments' ),
							__( 'Registra detalhes adicionais em WooCommerce > Status > Logs.', 'wc-custom-installments' )
						),
					),
				),
				array(
					'id' => 'advanced-transfer',
					'title' => __( 'Importar e exportar', 'wc-custom-installments' ),
					'description' => __( 'Transfira esta configuração para outra loja ou restaure os padrões.', 'wc-custom-installments' ),
					'component' => 'settings-transfer',
				),
			),
		);
	}


	/**
	 * Build the badge toggles of one card row.
	 *
	 * @since 6.0.0
	 * @param string $type | Either `credit` or `debit`
	 * @return array<int,array<string,mixed>>
	 */
	private static function brand_fields( $type ) {
		$fields = array();

		foreach ( Default_Options::get_card_brands() as $brand => $label ) {
			$fields[] = self::toggle( 'enable_' . $brand . '_flag_' . $type, $label, '' );
		}

		return $fields;
	}


	/**
	 * The storefront placements a display setting can take.
	 *
	 * @since 6.0.0
	 * @return array<int,array<string,string>>
	 */
	private static function display_hook_options() {
		return array(
			array( 'value' => 'display_loop_and_single_product', 'label' => __( 'A listagem de produtos e a página do produto', 'wc-custom-installments' ) ),
			array( 'value' => 'only_single_product', 'label' => __( 'Somente a página do produto', 'wc-custom-installments' ) ),
			array( 'value' => 'only_loop', 'label' => __( 'Somente a listagem de produtos', 'wc-custom-installments' ) ),
			array( 'value' => 'none', 'label' => __( 'Em nenhum lugar', 'wc-custom-installments' ) ),
		);
	}


	/**
	 * Percentage or fixed amount.
	 *
	 * @since 6.0.0
	 * @return array<int,array<string,string>>
	 */
	private static function discount_method_options() {
		return array(
			array( 'value' => 'percentage', 'label' => __( 'Porcentagem', 'wc-custom-installments' ) ),
			array( 'value' => 'fixed', 'label' => __( 'Valor fixo', 'wc-custom-installments' ) ),
		);
	}


	/**
	 * List the store's enabled payment gateways.
	 *
	 * WooCommerce is not always loaded when the schema is built — during a CLI
	 * run, for instance — so an empty list is a valid answer rather than a fatal.
	 *
	 * @since 6.0.0
	 * @return array<int,array<string,string>>
	 */
	private static function get_payment_gateways() {
		if ( ! function_exists('WC') || ! WC()->payment_gateways() ) {
			return array();
		}

		$gateways = array();

		foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $gateway ) {
			$gateways[] = array(
				'id' => $gateway->id,
				'label' => $gateway->get_title(),
			);
		}

		return $gateways;
	}


	/**
	 * Mark every field whose feature is unavailable as locked.
	 *
	 * The value still travels to the browser, so the store owner sees what the
	 * feature does; the repository is what refuses to write it.
	 *
	 * @since 6.0.0
	 * @param array<int,array<string,mixed>> $schema | Settings schema
	 * @return array<int,array<string,mixed>>
	 */
	private static function resolve_locks( $schema ) {
		foreach ( $schema as $section_index => $section ) {
			foreach ( (array) ( $section['cards'] ?? array() ) as $card_index => $card ) {
				$card_locked = true;
				$has_fields = false;

				foreach ( (array) ( $card['fields'] ?? array() ) as $field_index => $field ) {
					$has_fields = true;
					$feature = $field['feature'] ?? '';
					$locked = ( '' !== $feature ) && ! Features::is_available( $feature );

					if ( ! $locked ) {
						$card_locked = false;
					}

					$schema[ $section_index ]['cards'][ $card_index ]['fields'][ $field_index ]['locked'] = $locked;
					$schema[ $section_index ]['cards'][ $card_index ]['fields'][ $field_index ]['tier'] =
						( '' !== $feature && Features::is_pro( $feature ) ) ? Features::TIER_PRO : Features::TIER_FREE;
				}

				// A card whose every field is locked is announced as a whole,
				// so a Pro-only card does not become a wall of badges.
				$schema[ $section_index ]['cards'][ $card_index ]['locked'] = $has_fields && $card_locked;
			}
		}

		return $schema;
	}


	/**
	 * Build a toggle field definition.
	 *
	 * @since 6.0.0
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function toggle( $key, $label, $description, $extra = array() ) {
		return self::field( 'toggle', $key, $label, $description, $extra );
	}


	/**
	 * Build a text field definition.
	 *
	 * @since 6.0.0
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function text( $key, $label, $description, $extra = array() ) {
		return self::field( 'text', $key, $label, $description, $extra );
	}


	/**
	 * Build a select field definition.
	 *
	 * @since 6.0.0
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<int,array<string,string>> $options | Selectable options
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function select( $key, $label, $description, $options, $extra = array() ) {
		return self::field( 'select', $key, $label, $description, array_merge( array( 'options' => $options ), $extra ) );
	}


	/**
	 * Build a numeric field definition.
	 *
	 * @since 6.0.0
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function number( $key, $label, $description, $extra = array() ) {
		return self::field( 'number', $key, $label, $description, $extra );
	}


	/**
	 * Build a textarea field definition.
	 *
	 * @since 6.0.0
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function textarea( $key, $label, $description, $extra = array() ) {
		return self::field( 'textarea', $key, $label, $description, $extra );
	}


	/**
	 * Build a colour field definition.
	 *
	 * @since 6.0.0
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function color( $key, $label, $description, $extra = array() ) {
		return self::field( 'color', $key, $label, $description, $extra );
	}


	/**
	 * Build a text field that offers the installment tokens.
	 *
	 * @since 6.0.0
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function placeholder_text( $key, $label, $description, $extra = array() ) {
		return self::field( 'placeholder-text', $key, $label, $description, array_merge( array(
			'tokens' => array( '{{ parcelas }}', '{{ valor }}', '{{ juros }}' ),
		), $extra ) );
	}


	/**
	 * Build a measurement field: a number plus the unit it is expressed in.
	 *
	 * @since 6.0.0
	 * @param string $key | Setting name holding the amount
	 * @param string $unit_key | Setting name holding the unit
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function unit( $key, $unit_key, $label, $description, $extra = array() ) {
		return self::field( 'unit', $key, $label, $description, array_merge( array(
			'unit_key' => $unit_key,
			'units' => array( 'px', 'rem', 'em', '%' ),
			'step' => '0.05',
		), $extra ) );
	}


	/**
	 * Build a field definition of any type.
	 *
	 * @since 6.0.0
	 * @param string $type | Field type
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function field( $type, $key, $label, $description, $extra = array() ) {
		$defaults = Default_Options::get_defaults();

		return array_merge( array(
			'type' => $type,
			'key' => $key,
			'label' => $label,
			'description' => $description,
			'default' => $defaults[ $key ] ?? '',
			'feature' => '',
		), $extra );
	}
}
