<?php

/**
 * Minimal WordPress stubs for the standalone test harnesses.
 *
 * The harnesses run against a plain PHP binary, with no WordPress and no
 * database, so the classes under test have to find the handful of functions
 * they call. Only what is actually used is stubbed, and each stub behaves the
 * way WordPress does for the inputs the tests exercise — a stub that quietly
 * diverges would make the suite lie.
 *
 * @since 1.0.0
 */

defined('ABSPATH') || define( 'ABSPATH', __DIR__ . '/' );

define( 'WC_CUSTOM_INSTALLMENTS_VERSION', '1.0.0' );
define( 'WC_CUSTOM_INSTALLMENTS_DEBUG_MODE', false );
define( 'WC_CUSTOM_INSTALLMENTS_DEV_MODE', false );
define( 'WC_CUSTOM_INSTALLMENTS_SLUG', 'wc-custom-installments' );
define( 'WC_CUSTOM_INSTALLMENTS_REST', 'wc-custom-installments/v1' );
define( 'WC_CUSTOM_INSTALLMENTS_ASSETS', 'https://example.test/wp-content/plugins/wc-custom-installments/assets/' );
define( 'WC_CUSTOM_INSTALLMENTS_TEMPLATES', dirname( __DIR__ ) . '/templates/' );
define( 'WC_CUSTOM_INSTALLMENTS_LANGUAGES', dirname( __DIR__ ) . '/languages' );
define( 'WC_CUSTOM_INSTALLMENTS_DOCS_URL', 'https://ajuda.meumouse.com/docs/wc-custom-installments/overview' );
define( 'WC_CUSTOM_INSTALLMENTS_STORE_URL', 'https://meumouse.com/plugins/wc-custom-installments/' );
define( 'WC_CUSTOM_INSTALLMENTS_API_URL', 'https://cloud.meumouse.com' );

// Empty on purpose: with no product configured the trial step offers a link
// and never contacts anything, which is the state the preview should show.
define( 'WC_CUSTOM_INSTALLMENTS_PRO_PRODUCT_ID', '' );

$GLOBALS['wp_test_options'] = array();
$GLOBALS['wp_test_filters'] = array();

if ( ! function_exists('add_filter') ) {
	/**
	 * Register a callback on a hook.
	 *
	 * @param string $hook | Hook name
	 * @param callable $callback | Callback to run
	 * @param int $priority | Execution priority
	 * @return bool
	 */
	function add_filter( $hook, $callback, $priority = 10 ) {
		$GLOBALS['wp_test_filters'][ $hook ][ $priority ][] = $callback;

		return true;
	}
}

if ( ! function_exists('add_action') ) {
	/**
	 * Register a callback on an action.
	 *
	 * @param string $hook | Hook name
	 * @param callable $callback | Callback to run
	 * @param int $priority | Execution priority
	 * @return bool
	 */
	function add_action( $hook, $callback, $priority = 10 ) {
		return add_filter( $hook, $callback, $priority );
	}
}

if ( ! function_exists('has_filter') ) {
	/**
	 * Whether anything is listening on a hook.
	 *
	 * @param string $hook | Hook name
	 * @return bool
	 */
	function has_filter( $hook ) {
		return ! empty( $GLOBALS['wp_test_filters'][ $hook ] );
	}
}

if ( ! function_exists('apply_filters') ) {
	/**
	 * Run every callback registered on a hook, in priority order.
	 *
	 * @param string $hook | Hook name
	 * @param mixed $value | Value being filtered
	 * @param mixed ...$args | Additional arguments
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		if ( empty( $GLOBALS['wp_test_filters'][ $hook ] ) ) {
			return $value;
		}

		$by_priority = $GLOBALS['wp_test_filters'][ $hook ];
		ksort( $by_priority );

		foreach ( $by_priority as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value = $callback( $value, ...$args );
			}
		}

		return $value;
	}
}

if ( ! function_exists('do_action') ) {
	/**
	 * Run every callback registered on an action.
	 *
	 * @param string $hook | Hook name
	 * @param mixed ...$args | Arguments passed to the callbacks
	 * @return void
	 */
	function do_action( $hook, ...$args ) {
		if ( empty( $GLOBALS['wp_test_filters'][ $hook ] ) ) {
			return;
		}

		$by_priority = $GLOBALS['wp_test_filters'][ $hook ];
		ksort( $by_priority );

		foreach ( $by_priority as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$callback( ...$args );
			}
		}
	}
}

if ( ! function_exists('get_option') ) {
	/**
	 * Read a stored option.
	 *
	 * @param string $name | Option name
	 * @param mixed $fallback | Returned when the option is absent
	 * @return mixed
	 */
	function get_option( $name, $fallback = false ) {
		return array_key_exists( $name, $GLOBALS['wp_test_options'] ) ? $GLOBALS['wp_test_options'][ $name ] : $fallback;
	}
}

if ( ! function_exists('update_option') ) {
	/**
	 * Write an option.
	 *
	 * @param string $name | Option name
	 * @param mixed $value | Value to store
	 * @return bool
	 */
	function update_option( $name, $value ) {
		$GLOBALS['wp_test_options'][ $name ] = $value;

		return true;
	}
}

if ( ! function_exists('delete_option') ) {
	/**
	 * Remove an option.
	 *
	 * @param string $name | Option name
	 * @return bool
	 */
	function delete_option( $name ) {
		unset( $GLOBALS['wp_test_options'][ $name ] );

		return true;
	}
}

if ( ! function_exists('wp_parse_args') ) {
	/**
	 * Merge arguments over a set of defaults.
	 *
	 * @param array $args | Provided arguments
	 * @param array $defaults | Default arguments
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! function_exists('sanitize_key') ) {
	/**
	 * Reduce a string to lowercase alphanumerics, dashes and underscores.
	 *
	 * @param string $key | Raw key
	 * @return string
	 */
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists('sanitize_text_field') ) {
	/**
	 * Strip tags and trim a scalar value.
	 *
	 * @param string $value | Raw value
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists('sanitize_textarea_field') ) {
	/**
	 * Strip tags from a multi-line value.
	 *
	 * @param string $value | Raw value
	 * @return string
	 */
	function sanitize_textarea_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists('sanitize_hex_color') ) {
	/**
	 * Return the value when it is a hex colour, null otherwise.
	 *
	 * @param string $value | Raw value
	 * @return string|null
	 */
	function sanitize_hex_color( $value ) {
		return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $value ) ? $value : null;
	}
}

if ( ! function_exists('map_deep') ) {
	/**
	 * Apply a callback to every leaf of a nested value.
	 *
	 * @param mixed $value | Value to walk
	 * @param callable $callback | Callback applied to each leaf
	 * @return mixed
	 */
	function map_deep( $value, $callback ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = map_deep( $item, $callback );
			}

			return $value;
		}

		return $callback( $value );
	}
}

if ( ! function_exists('__') ) {
	/**
	 * Return the string unchanged; there is no catalogue in the harness.
	 *
	 * @param string $text | Text to translate
	 * @param string $domain | Text domain
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists('__return_true') ) {
	/**
	 * Always true.
	 *
	 * @return bool
	 */
	function __return_true() {
		return true;
	}
}

if ( ! function_exists('__return_false') ) {
	/**
	 * Always false.
	 *
	 * @return bool
	 */
	function __return_false() {
		return false;
	}
}

if ( ! function_exists('maybe_unserialize') ) {
	/**
	 * Unserialize a value that may or may not be serialized.
	 *
	 * @param mixed $value | Stored value
	 * @return mixed
	 */
	function maybe_unserialize( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$data = @unserialize( trim( $value ) );

		return ( false === $data && 'b:0;' !== trim( $value ) ) ? $value : $data;
	}
}

if ( ! function_exists('wp_json_encode') ) {
	/**
	 * Encode a value as JSON.
	 *
	 * @param mixed $value | Value to encode
	 * @return string|false
	 */
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! class_exists('WP_Error') ) {
	/**
	 * The subset of WP_Error the plugin uses.
	 */
	class WP_Error {

		/**
		 * Error code.
		 *
		 * @var string
		 */
		private $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		private $message;


		/**
		 * Build the error.
		 *
		 * @param string $code | Error code
		 * @param string $message | Human-readable message
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->code = $code;
			$this->message = $message;
		}


		/**
		 * Read the error code.
		 *
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}


		/**
		 * Read the error message.
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists('is_wp_error') ) {
	/**
	 * Whether a value is a WP_Error.
	 *
	 * @param mixed $value | Value to inspect
	 * @return bool
	 */
	function is_wp_error( $value ) {
		return $value instanceof WP_Error;
	}
}

/* ── WooCommerce surface ─────────────────────────────────────────────────── */

if ( ! function_exists('wc_format_decimal') ) {
	/**
	 * Normalise a decimal separator.
	 *
	 * @param mixed $value | Raw value
	 * @return string
	 */
	function wc_format_decimal( $value ) {
		return str_replace( ',', '.', (string) $value );
	}
}

if ( ! function_exists('wc_price') ) {
	/**
	 * Format an amount as currency.
	 *
	 * @param float $value | Amount
	 * @return string
	 */
	function wc_price( $value ) {
		return 'R$' . number_format( (float) $value, 2, ',', '.' );
	}
}

if ( ! function_exists('get_woocommerce_currency_symbol') ) {
	/**
	 * The store's currency symbol.
	 *
	 * @return string
	 */
	function get_woocommerce_currency_symbol() {
		return 'R$';
	}
}

if ( ! function_exists('wc_get_price_to_display') ) {
	/**
	 * The price a customer is shown.
	 *
	 * @param object $product | Product object
	 * @param array $args | Optional price override
	 * @return float
	 */
	function wc_get_price_to_display( $product, $args = array() ) {
		if ( isset( $args['price'] ) ) {
			return (float) $args['price'];
		}

		return (float) $product->get_price();
	}
}

if ( ! function_exists('wc_get_price_excluding_tax') ) {
	/**
	 * The price before tax.
	 *
	 * @param object $product | Product object
	 * @return float
	 */
	function wc_get_price_excluding_tax( $product ) {
		return (float) $product->get_price();
	}
}

if ( ! function_exists('wc_get_product') ) {
	/**
	 * Resolve a product; the harness has no store, so there is never one.
	 *
	 * @param int|bool $id | Product ID
	 * @return false
	 */
	function wc_get_product( $id = false ) {
		return false;
	}
}

if ( ! function_exists('get_post_meta') ) {
	/**
	 * Read product meta from the in-memory store.
	 *
	 * @param int $id | Object ID
	 * @param string $key | Meta key
	 * @param bool $single | Whether one value is wanted
	 * @return mixed
	 */
	function get_post_meta( $id, $key = '', $single = false ) {
		return $GLOBALS['wp_test_meta'][ $id ][ $key ] ?? '';
	}
}

if ( ! function_exists('is_product') ) {
	/**
	 * Whether a product page is being rendered.
	 *
	 * @return bool
	 */
	function is_product() {
		return ! empty( $GLOBALS['wp_test_is_product'] );
	}
}

if ( ! function_exists('is_archive') ) {
	/**
	 * Whether an archive is being rendered.
	 *
	 * @return bool
	 */
	function is_archive() {
		return ! empty( $GLOBALS['wp_test_is_archive'] );
	}
}

if ( ! function_exists('is_admin') ) {
	/**
	 * Whether this is an admin request.
	 *
	 * @return bool
	 */
	function is_admin() {
		return false;
	}
}

if ( ! function_exists('wp_doing_ajax') ) {
	/**
	 * Whether this is an AJAX request.
	 *
	 * @return bool
	 */
	function wp_doing_ajax() {
		return false;
	}
}

if ( ! function_exists('esc_attr') ) {
	/**
	 * Escape a value for an attribute.
	 *
	 * @param string $value | Raw value
	 * @return string
	 */
	function esc_attr( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES );
	}
}

if ( ! function_exists('esc_html') ) {
	/**
	 * Escape a value for markup.
	 *
	 * @param string $value | Raw value
	 * @return string
	 */
	function esc_html( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES );
	}
}

if ( ! function_exists('esc_url') ) {
	/**
	 * Escape a URL.
	 *
	 * @param string $value | Raw URL
	 * @return string
	 */
	function esc_url( $value ) {
		return (string) $value;
	}
}

if ( ! function_exists('esc_html__') ) {
	/**
	 * Translate and escape.
	 *
	 * @param string $text | Text
	 * @param string $domain | Text domain
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists('esc_attr__') ) {
	/**
	 * Translate and escape for an attribute.
	 *
	 * @param string $text | Text
	 * @param string $domain | Text domain
	 * @return string
	 */
	function esc_attr__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! class_exists('WC_Product') ) {
	/**
	 * The slice of WC_Product the engine reads.
	 */
	class WC_Product {

		/**
		 * Product attributes.
		 *
		 * @var array<string,mixed>
		 */
		private $data;


		/**
		 * Build a product.
		 *
		 * @param array<string,mixed> $data | Attributes
		 */
		public function __construct( $data = array() ) {
			$this->data = array_merge( array(
				'id' => 1,
				'parent_id' => 0,
				'type' => 'simple',
				'price' => 0.0,
				'regular_price' => 0.0,
				'sale_price' => 0.0,
				'on_sale' => false,
				'purchasable' => true,
				'variation_prices' => array(),
			), $data );
		}


		/**
		 * The product ID.
		 *
		 * @return int
		 */
		public function get_id() {
			return (int) $this->data['id'];
		}


		/**
		 * The parent product ID.
		 *
		 * @return int
		 */
		public function get_parent_id() {
			return (int) $this->data['parent_id'];
		}


		/**
		 * The product type.
		 *
		 * @return string
		 */
		public function get_type() {
			return (string) $this->data['type'];
		}


		/**
		 * Whether the product is of a type.
		 *
		 * @param string|array $type | Type or types
		 * @return bool
		 */
		public function is_type( $type ) {
			return in_array( $this->get_type(), (array) $type, true );
		}


		/**
		 * The current price.
		 *
		 * @return float
		 */
		public function get_price() {
			return (float) $this->data['price'];
		}


		/**
		 * The regular price.
		 *
		 * @return float
		 */
		public function get_regular_price() {
			return (float) $this->data['regular_price'];
		}


		/**
		 * The sale price.
		 *
		 * @return float
		 */
		public function get_sale_price() {
			return (float) $this->data['sale_price'];
		}


		/**
		 * Whether the product is on sale.
		 *
		 * @return bool
		 */
		public function is_on_sale() {
			return (bool) $this->data['on_sale'];
		}


		/**
		 * Whether the product can be bought.
		 *
		 * @return bool
		 */
		public function is_purchasable() {
			return (bool) $this->data['purchasable'];
		}


		/**
		 * The lowest or highest variation price.
		 *
		 * @param string $which | Either `min` or `max`
		 * @param bool $display | Ignored in the harness
		 * @return float
		 */
		public function get_variation_price( $which = 'min', $display = false ) {
			$prices = $this->data['variation_prices']['price'] ?? array();

			if ( empty( $prices ) ) {
				return $this->get_price();
			}

			return (float) ( 'max' === $which ? max( $prices ) : min( $prices ) );
		}


		/**
		 * The lowest or highest regular variation price.
		 *
		 * @param string $which | Either `min` or `max`
		 * @param bool $display | Ignored in the harness
		 * @return float
		 */
		public function get_variation_regular_price( $which = 'min', $display = false ) {
			$prices = $this->data['variation_prices']['regular_price'] ?? array();

			if ( empty( $prices ) ) {
				return $this->get_regular_price();
			}

			return (float) ( 'max' === $which ? max( $prices ) : min( $prices ) );
		}


		/**
		 * Every variation price, keyed by variation.
		 *
		 * @param bool $display | Ignored in the harness
		 * @return array<string,array<int|string,mixed>>
		 */
		public function get_variation_prices( $display = false ) {
			return $this->data['variation_prices'];
		}
	}
}

if ( ! function_exists('wc_test_product') ) {
	/**
	 * Build a product for a test.
	 *
	 * @param float $price | Current price
	 * @param array<string,mixed> $data | Attribute overrides
	 * @return WC_Product
	 */
	function wc_test_product( $price, $data = array() ) {
		return new WC_Product( array_merge( array(
			'price' => $price,
			'regular_price' => $price,
		), $data ) );
	}
}

$GLOBALS['wp_test_meta'] = array();
$GLOBALS['wp_test_is_product'] = false;
$GLOBALS['wp_test_is_archive'] = false;

if ( ! function_exists('add_shortcode') ) {
	/**
	 * Record a shortcode registration.
	 *
	 * @param string $tag | Shortcode tag
	 * @param callable $callback | What renders it
	 * @return void
	 */
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['wp_test_shortcodes'][ $tag ] = $callback;
	}
}

if ( ! function_exists('shortcode_atts') ) {
	/**
	 * Merge shortcode attributes over their defaults.
	 *
	 * @param array $pairs | Supported attributes and defaults
	 * @param array $atts | Provided attributes
	 * @return array
	 */
	function shortcode_atts( $pairs, $atts ) {
		return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) );
	}
}

if ( ! function_exists('update_post_meta') ) {
	/**
	 * Write product meta into the in-memory store.
	 *
	 * @param int $id | Object ID
	 * @param string $key | Meta key
	 * @param mixed $value | Value to store
	 * @return bool
	 */
	function update_post_meta( $id, $key, $value ) {
		$GLOBALS['wp_test_meta'][ $id ][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists('current_user_can') ) {
	/**
	 * Whether the current user may do something; always yes in the harness.
	 *
	 * @param string $capability | Capability name
	 * @param mixed ...$args | Additional arguments
	 * @return bool
	 */
	function current_user_can( $capability, ...$args ) {
		return true;
	}
}

if ( ! function_exists('absint') ) {
	/**
	 * Cast to a non-negative integer.
	 *
	 * @param mixed $value | Raw value
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists('wp_unslash') ) {
	/**
	 * Undo the slashes WordPress adds to request data.
	 *
	 * @param mixed $value | Raw value
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

$GLOBALS['wp_test_shortcodes'] = array();

if ( ! function_exists('admin_url') ) {
	/**
	 * Build an admin URL.
	 *
	 * @param string $path | Path relative to the admin
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists('home_url') ) {
	/**
	 * Build a site URL.
	 *
	 * @param string $path | Path relative to the site root
	 * @return string
	 */
	function home_url( $path = '' ) {
		return 'https://example.test/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists('rest_url') ) {
	/**
	 * Build a REST URL.
	 *
	 * @param string $path | REST namespace and route
	 * @return string
	 */
	function rest_url( $path = '' ) {
		return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists('esc_url_raw') ) {
	/**
	 * Sanitise a URL for storage.
	 *
	 * @param string $url | Raw URL
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists('wp_create_nonce') ) {
	/**
	 * Build a nonce; the harness has no session to tie one to.
	 *
	 * @param string $action | Nonce action
	 * @return string
	 */
	function wp_create_nonce( $action = '' ) {
		return 'preview';
	}
}

if ( ! function_exists('wc_get_price_decimal_separator') ) {
	/**
	 * The store's decimal separator.
	 *
	 * @return string
	 */
	function wc_get_price_decimal_separator() {
		return ',';
	}
}

if ( ! function_exists('wc_get_price_thousand_separator') ) {
	/**
	 * The store's thousand separator.
	 *
	 * @return string
	 */
	function wc_get_price_thousand_separator() {
		return '.';
	}
}

if ( ! function_exists('wp_parse_url') ) {
	/**
	 * Parse a URL.
	 *
	 * @param string $url | The URL
	 * @param int $component | Which part to return
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists('set_transient') ) {
	/**
	 * Store a transient in memory.
	 *
	 * @param string $name | Transient name
	 * @param mixed $value | Value to store
	 * @param int $ttl | Lifetime, ignored here
	 * @return bool
	 */
	function set_transient( $name, $value, $ttl = 0 ) {
		$GLOBALS['wp_test_transients'][ $name ] = $value;

		return true;
	}
}

if ( ! function_exists('get_transient') ) {
	/**
	 * Read a transient.
	 *
	 * @param string $name | Transient name
	 * @return mixed
	 */
	function get_transient( $name ) {
		return $GLOBALS['wp_test_transients'][ $name ] ?? false;
	}
}

if ( ! function_exists('delete_transient') ) {
	/**
	 * Remove a transient.
	 *
	 * @param string $name | Transient name
	 * @return bool
	 */
	function delete_transient( $name ) {
		unset( $GLOBALS['wp_test_transients'][ $name ] );

		return true;
	}
}

if ( ! function_exists('is_email') ) {
	/**
	 * Whether a string looks like an e-mail address.
	 *
	 * @param string $value | Raw value
	 * @return bool
	 */
	function is_email( $value ) {
		return (bool) filter_var( $value, FILTER_VALIDATE_EMAIL );
	}
}

$GLOBALS['wp_test_transients'] = array();

// The classes under test, loaded directly: the harness has no autoloader.
require_once __DIR__ . '/../admin/src/Core/Features.php';
require_once __DIR__ . '/../admin/src/Core/Helpers.php';
require_once __DIR__ . '/../admin/src/Compatibility/Hook_Aliases.php';
require_once __DIR__ . '/../admin/src/Frontend/Element_Registry.php';
require_once __DIR__ . '/../admin/src/Engine/Calculate_Values.php';
require_once __DIR__ . '/../admin/src/Engine/Calculate_Installments.php';
require_once __DIR__ . '/../admin/src/Admin/Default_Options.php';
require_once __DIR__ . '/../admin/src/Admin/Settings/Registry.php';
require_once __DIR__ . '/../admin/src/Admin/Settings/Repository.php';
require_once __DIR__ . '/../admin/src/Admin/Settings/Transfer.php';
require_once __DIR__ . '/../admin/src/Core/Logger.php';
require_once __DIR__ . '/../admin/src/Core/Migrator.php';
require_once __DIR__ . '/../admin/src/Compatibility/Conflict_Guard.php';
require_once __DIR__ . '/../admin/src/Frontend/Components.php';
require_once __DIR__ . '/../admin/src/Frontend/Render_Elements.php';
require_once __DIR__ . '/../admin/src/Frontend/Shortcodes.php';
require_once __DIR__ . '/../admin/src/Admin/Menu.php';
require_once __DIR__ . '/../admin/src/Core/Onboarding.php';
require_once __DIR__ . '/../admin/src/Admin/Onboarding/Trial.php';
require_once __DIR__ . '/../admin/src/Admin/Onboarding/Registry.php';
