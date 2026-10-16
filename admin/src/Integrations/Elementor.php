<?php

namespace MeuMouse\WC_Custom_Installments\Integrations;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Compatibility\Hook_Aliases;
use MeuMouse\WC_Custom_Installments\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * Register the plugin's Elementor widgets.
 *
 * Widget names are the contract here: they are stored inside every page that
 * uses one, so a rename deletes the widget from those pages. The 5.x names are
 * kept exactly, and the Pro add-on registers the rest of them under theirs.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations
 * @author MeuMouse.com
 */
class Elementor {

	/**
	 * The widget category the plugin's widgets appear under.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const CATEGORY = 'wc-custom-installments';

	/**
	 * Preview product IDs already resolved, per Elementor document.
	 *
	 * Resolving one can query the catalogue, and every component on the
	 * preview asks for it.
	 *
	 * @since 6.0.0
	 * @var array<int,int>
	 */
	private static $preview_products = array();


	/**
	 * Register the Elementor hooks.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		if ( ! self::is_active() || ! Catalog::is_enabled('elementor') ) {
			return;
		}

		if ( ! Repository::is_active('enable_elementor_widgets') ) {
			return;
		}

		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );

		// Ahead of the theme integrations, which fall back to any product: the
		// product the template itself names comes first.
		add_filter( 'WC_Custom_Installments/Assets/Set_Product_Id', array( $this, 'set_preview_product' ), 5 );
		add_filter( 'WC_Custom_Installments/Product/Set_Product', array( $this, 'set_preview_product_object' ), 5 );
	}


	/**
	 * Whether Elementor is active.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function is_active() {
		return defined('ELEMENTOR_VERSION');
	}


	/**
	 * Describe the integration for the settings screen.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	public static function describe() {
		return array(
			'id' => 'elementor',
			'title' => 'Elementor',
			'description' => __( 'Widgets do plugin no editor, e o grupo de preços dentro dos widgets de produto.', 'wc-custom-installments' ),
			'url' => 'https://wordpress.org/plugins/elementor/',
			'active' => self::is_active(),
			'fields' => array(
				array(
					'type' => 'toggle',
					'key' => 'enable_elementor_widgets',
					'label' => __( 'Widgets do Elementor', 'wc-custom-installments' ),
					'description' => __( 'Registra os widgets do plugin no editor do Elementor.', 'wc-custom-installments' ),
				),
				array(
					'type' => 'toggle',
					'key' => 'enable_price_grid_in_widgets',
					'label' => __( 'Grupo de preços dentro dos widgets', 'wc-custom-installments' ),
					'description' => __( 'Exibe o grupo de preços completo onde um widget mostra o preço de um produto.', 'wc-custom-installments' ),
				),
			),
		);
	}


	/**
	 * Add the plugin's own category to the widget panel.
	 *
	 * @since 6.0.0
	 * @param object $manager | Elementor elements manager
	 * @return void
	 */
	public function register_category( $manager ) {
		$manager->add_category( self::CATEGORY, array(
			'title' => esc_html__( 'Parcelas Customizadas', 'wc-custom-installments' ),
			'icon' => 'eicon-product-price',
		) );
	}


	/**
	 * Register every widget the site is entitled to.
	 *
	 * @since 6.0.0
	 * @param object $manager | Elementor widgets manager
	 * @return void
	 */
	public function register_widgets( $manager ) {
		/**
		 * Filter the Elementor widget classes to register.
		 *
		 * The Pro add-on appends its own here. Each entry is a fully qualified
		 * class name extending Elementor's widget base.
		 *
		 * @since 6.0.0
		 * @param array<int,string> $widgets | Widget class names
		 * @return array<int,string>
		 */
		$widgets = (array) apply_filters( 'WC_Custom_Installments/Elementor/Widgets', array(
			Elementor\Widgets\Single_Product_Price::class,
			Elementor\Widgets\Installments_Table::class,
		) );

		foreach ( $widgets as $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}

			$manager->register( new $class() );
		}
	}


	/**
	 * Give the storefront runtime a product to configure for in the editor.
	 *
	 * @since 6.0.0
	 * @param int $product_id | Product ID resolved so far
	 * @return int
	 */
	public function set_preview_product( $product_id ) {
		if ( $product_id || ! self::is_preview() ) {
			return $product_id;
		}

		$preview_id = self::get_preview_product_id();

		return $preview_id ? $preview_id : $product_id;
	}


	/**
	 * Give the price group a product to render against in the editor.
	 *
	 * @since 6.0.0
	 * @param object|false $product | Product resolved so far
	 * @return object|false
	 */
	public function set_preview_product_object( $product ) {
		if ( $product instanceof \WC_Product || ! self::is_preview() ) {
			return $product;
		}

		$preview_id = self::get_preview_product_id();
		$preview = $preview_id ? wc_get_product( $preview_id ) : false;

		return $preview instanceof \WC_Product ? $preview : $product;
	}


	/**
	 * Whether Elementor is rendering its editor or its preview frame.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function is_preview() {
		if ( Helpers::is_builder_preview() ) {
			return true;
		}

		if ( ! class_exists('\Elementor\Plugin') ) {
			return false;
		}

		$elementor = \Elementor\Plugin::instance();

		return isset( $elementor->preview ) && $elementor->preview->is_preview_mode();
	}


	/**
	 * The product the editor should render against.
	 *
	 * In order: the preview product the Elementor template names (5.x read
	 * the same `preview_id` setting of Elementor Pro's product template), the
	 * preview product of a Woodmart single-product layout, then the newest
	 * product in the catalogue.
	 *
	 * @since 6.0.0
	 * @return int
	 */
	public static function get_preview_product_id() {
		$document = self::get_current_document();
		$cache_key = $document && method_exists( $document, 'get_main_id' ) ? (int) $document->get_main_id() : 0;

		if ( isset( self::$preview_products[ $cache_key ] ) ) {
			return self::$preview_products[ $cache_key ];
		}

		$product_id = self::get_document_preview_id( $document );

		if ( ! $product_id ) {
			$product_id = self::get_woodmart_preview_id();
		}

		if ( ! $product_id ) {
			$product = Helpers::get_first_product();
			$product_id = $product ? $product->get_id() : 0;
		}

		self::$preview_products[ $cache_key ] = $product_id;

		return $product_id;
	}


	/**
	 * The Elementor document being edited or rendered.
	 *
	 * @since 6.0.0
	 * @return object|null
	 */
	private static function get_current_document() {
		if ( ! class_exists('\Elementor\Plugin') ) {
			return null;
		}

		$elementor = \Elementor\Plugin::instance();

		if ( ! isset( $elementor->documents ) ) {
			return null;
		}

		$document = method_exists( $elementor->documents, 'get_current' ) ? $elementor->documents->get_current() : null;

		if ( $document ) {
			return $document;
		}

		$post_id = get_the_ID();

		return $post_id ? $elementor->documents->get( $post_id ) : null;
	}


	/**
	 * The product a template document previews, when it names one.
	 *
	 * Only a single-item preview is trusted: an archive template stores a term
	 * ID in the same setting, and a term ID can belong to an unrelated post.
	 *
	 * @since 6.0.0
	 * @param object|null $document | Elementor document
	 * @return int
	 */
	private static function get_document_preview_id( $document ) {
		if ( ! $document || ! method_exists( $document, 'get_settings' ) ) {
			return 0;
		}

		$preview_type = (string) $document->get_settings('preview_type');

		if ( '' !== $preview_type && 0 !== strpos( $preview_type, 'single' ) ) {
			return 0;
		}

		$preview_id = (int) $document->get_settings('preview_id');

		return ( $preview_id && 'product' === get_post_type( $preview_id ) ) ? $preview_id : 0;
	}


	/**
	 * The preview product of a Woodmart single-product layout.
	 *
	 * @since 6.0.0
	 * @return int
	 */
	private static function get_woodmart_preview_id() {
		$layouts = 'XTS\\Modules\\Layouts\\Main';
		$single_product = 'XTS\\Modules\\Layouts\\Single_Product';

		if ( ! is_callable( array( $single_product, 'get_preview_product_id' ) ) ) {
			return 0;
		}

		try {
			if ( is_callable( array( $layouts, 'is_layout_type' ) ) && ! $layouts::is_layout_type('single_product') ) {
				return 0;
			}

			$product_id = (int) $single_product::get_preview_product_id();
		} catch ( \Throwable $exception ) {
			return 0;
		}

		return ( $product_id && 'product' === get_post_type( $product_id ) ) ? $product_id : 0;
	}


	/**
	 * The widgets other plugins asked us to decorate.
	 *
	 * Themes and page builders register their own product widgets, and each one
	 * names the controls section its price styling belongs in. The defaults are
	 * the Elementor Pro product widgets 5.x decorated.
	 *
	 * @since 6.0.0
	 * @return array<string,string|array<int,string>>
	 */
	public static function get_injected_controllers() {
		/**
		 * Filter the third-party widgets the plugin adds controls to.
		 *
		 * @since 6.0.0
		 * @param array<string,string|array<int,string>> $widgets | Widget name => style section ID, or several IDs
		 * @return array<string,string|array<int,string>>
		 */
		return (array) Hook_Aliases::apply_filters( 'WC_Custom_Installments/Elementor/Inject_Controllers', array(
			'woocommerce-products' => 'section_design_box',
			'woocommerce-product-price' => 'section_price_style',
			'woocommerce-product-related' => 'section_design_box',
			'woocommerce-product-upsell' => 'section_design_box',
			'wc-archive-products' => 'section_design_box',
			'loop-carousel' => 'section_layout_style',
			'loop-grid' => 'section_layout_style',
		) );
	}
}
