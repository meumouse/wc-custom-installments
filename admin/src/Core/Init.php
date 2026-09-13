<?php

namespace MeuMouse\WC_Custom_Installments\Core;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use MeuMouse\WC_Custom_Installments\Compatibility\Conflict_Guard;
use ReflectionClass;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Bootstrap the plugin and instantiate every discovered class.
 *
 * This is the only class instantiated from the plugin file. Everything else is
 * discovered from the Composer classmap and instantiated on the hook that suits
 * its namespace, so a new class is wired up simply by living under admin/src
 * with the right namespace.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Core
 * @author MeuMouse.com
 */
class Init {

	/**
	 * Minimum PHP version required to boot.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const MIN_PHP_VERSION = '8.1';

	/**
	 * Minimum WooCommerce version required to boot.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const MIN_WC_VERSION = '8.0';

	/**
	 * Plugin main file path.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin version.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	private $plugin_version;

	/**
	 * Cache of instantiated classes, keyed by fully qualified class name.
	 *
	 * @since 6.0.0
	 * @var array<string,object>
	 */
	private $instantiated_classes = array();


	/**
	 * Define constants, check requirements and schedule the boot.
	 *
	 * @since 6.0.0
	 * @param string $plugin_file | Absolute path of the plugin main file
	 * @param string $plugin_version | Plugin version
	 * @return void
	 */
	public function __construct( $plugin_file, $plugin_version ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_version = $plugin_version;

		/**
		 * Fires before anything else, while the plugin file is still loading.
		 *
		 * @since 6.0.0
		 */
		do_action('WC_Custom_Installments/Core/Before_Init');

		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'php_version_notice' ) );

			return;
		}

		$this->setup_constants();

		// The extension helpers are plain functions, so they have to exist
		// before anything could call them - which is any time from now on.
		require_once WC_CUSTOM_INSTALLMENTS_SRC . 'Core/Functions.php';

		add_action( 'init', array( $this, 'load_text_domain' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_boot' ), 20 );

		/**
		 * Fires after the plugin has scheduled its boot.
		 *
		 * @since 6.0.0
		 */
		do_action('WC_Custom_Installments/Core/Init');
	}


	/**
	 * Define every plugin constant, unless the site already defined it.
	 *
	 * Guarding each one lets wp-config.php override any of them, which is how a
	 * staging site points at a different API host.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	private function setup_constants() {
		$base_file = $this->plugin_file;
		$base_dir = plugin_dir_path( $base_file );
		$base_url = plugin_dir_url( $base_file );

		$constants = array(
			'WC_CUSTOM_INSTALLMENTS_FILE'       => $base_file,
			'WC_CUSTOM_INSTALLMENTS_BASENAME'   => plugin_basename( $base_file ),
			'WC_CUSTOM_INSTALLMENTS_DIR'        => $base_dir,
			'WC_CUSTOM_INSTALLMENTS_SRC'        => $base_dir . 'admin/src/',
			'WC_CUSTOM_INSTALLMENTS_URL'        => $base_url,
			'WC_CUSTOM_INSTALLMENTS_ASSETS'     => $base_url . 'assets/',
			'WC_CUSTOM_INSTALLMENTS_TEMPLATES'  => $base_dir . 'templates/',
			'WC_CUSTOM_INSTALLMENTS_LANGUAGES'  => $base_dir . 'languages',
			'WC_CUSTOM_INSTALLMENTS_SLUG'       => 'wc-custom-installments',
			'WC_CUSTOM_INSTALLMENTS_VERSION'    => $this->plugin_version,
			'WC_CUSTOM_INSTALLMENTS_REST'       => 'wc-custom-installments/v1',
			'WC_CUSTOM_INSTALLMENTS_DEBUG_MODE' => false,
			'WC_CUSTOM_INSTALLMENTS_DEV_MODE'   => false,
			'WC_CUSTOM_INSTALLMENTS_DOCS_URL'   => 'https://ajuda.meumouse.com/docs/wc-custom-installments/overview',
			'WC_CUSTOM_INSTALLMENTS_STORE_URL'  => 'https://meumouse.com/plugins/wc-custom-installments/',
			'WC_CUSTOM_INSTALLMENTS_API_URL'    => 'https://cloud.meumouse.com',

			// The MDS record id of the Pro add-on, which the trial form asks
			// for a licence against. Empty means the trial step offers a link
			// instead of a form, and nothing ever leaves the site.
			'WC_CUSTOM_INSTALLMENTS_PRO_PRODUCT_ID' => '',
		);

		foreach ( $constants as $key => $value ) {
			if ( ! defined( $key ) ) {
				define( $key, $value );
			}
		}
	}


	/**
	 * Load the plugin text domain.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function load_text_domain() {
		load_plugin_textdomain( 'wc-custom-installments', false, dirname( WC_CUSTOM_INSTALLMENTS_BASENAME ) . '/languages/' );
	}


	/**
	 * Declare compatibility with the WooCommerce features we are aware of.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function declare_woocommerce_compatibility() {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}

		FeaturesUtil::declare_compatibility( 'custom_order_tables', $this->plugin_file, true );
		FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', $this->plugin_file, true );
	}


	/**
	 * Boot only when every requirement is satisfied.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function maybe_boot() {
		if ( ! $this->is_woocommerce_compatible() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );

			return;
		}

		// Before the instantiation pass, because what it decides is which
		// classes that pass is allowed to build.
		Conflict_Guard::bootstrap();

		add_action( 'init', array( $this, 'instance_classes' ), 5 );
	}


	/**
	 * Whether WooCommerce is active and new enough.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	private function is_woocommerce_compatible() {
		if ( ! class_exists('WooCommerce') || ! defined('WC_VERSION') ) {
			return false;
		}

		return version_compare( WC_VERSION, self::MIN_WC_VERSION, '>=' );
	}


	/**
	 * Instantiate every plugin class found in the Composer classmap.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function instance_classes() {
		foreach ( $this->get_instantiable_classes() as $class ) {
			$hook = $this->get_class_hook( $class );

			// `init` is already running when this method fires, so a class bound
			// to it would never be instantiated by add_action().
			if ( 'init' === $hook ) {
				$this->safe_instance_class( $class );

				continue;
			}

			add_action( $hook, function() use ( $class ) {
				$this->safe_instance_class( $class );
			}, 10 );
		}
	}


	/**
	 * Collect the plugin classes the classmap knows about.
	 *
	 * Abstracts, interfaces and traits are skipped: they exist to be extended,
	 * not instantiated.
	 *
	 * @since 6.0.0
	 * @return array<int,string>
	 */
	private function get_instantiable_classes() {
		$classmap_file = WC_CUSTOM_INSTALLMENTS_DIR . 'admin/vendor/composer/autoload_classmap.php';
		$classes = array();

		if ( file_exists( $classmap_file ) ) {
			$classmap = require $classmap_file;

			foreach ( array_keys( (array) $classmap ) as $class ) {
				if ( 0 !== strpos( $class, 'MeuMouse\\WC_Custom_Installments\\' ) ) {
					continue;
				}

				if ( ! self::is_discoverable( $class ) ) {
					continue;
				}

				$classes[] = $class;
			}
		}

		/**
		 * Filter the list of classes instantiated on boot.
		 *
		 * This is how an add-on injects its own classes into the plugin's
		 * lifecycle without touching the core.
		 *
		 * @since 6.0.0
		 * @param array<int,string> $classes | Fully qualified class names
		 * @return array<int,string>
		 */
		return (array) apply_filters( 'WC_Custom_Installments/Init/Instance_Classes', $classes );
	}


	/**
	 * Whether a class should be built as a service on boot.
	 *
	 * Public and static because the Pro add-on hands its own classes to the
	 * same instantiation pass, and applying a second copy of this rule is how
	 * the two would drift apart.
	 *
	 * @since 6.0.0
	 * @param string $class | Fully qualified class name
	 * @return bool
	 */
	public static function is_discoverable( $class ) {
		// Abstracts, interfaces and traits exist to be extended.
		if ( false !== strpos( $class, 'Abstract' ) || false !== strpos( $class, 'Interface' ) || false !== strpos( $class, 'Trait' ) ) {
			return false;
		}

		// A page-builder widget is constructed by its own manager, with the
		// arguments that manager supplies. Building one here would produce a
		// second, unregistered copy of every widget.
		if ( false !== strpos( $class, '\\Widgets\\' ) ) {
			return false;
		}

		// The bootstrap is already running; it does not boot itself.
		return self::class !== $class;
	}


	/**
	 * Resolve the hook a class should be instantiated on.
	 *
	 * @since 6.0.0
	 * @param string $class | Fully qualified class name
	 * @return string
	 */
	private function get_class_hook( $class ) {
		$hook = 'init';

		if ( false !== strpos( $class, '\\Integrations\\Elementor' ) ) {
			$hook = 'elementor/init';
		}

		/**
		 * Filter the hook a class is instantiated on.
		 *
		 * @since 6.0.0
		 * @param string $hook | Hook name
		 * @param string $class | Fully qualified class name
		 * @return string
		 */
		return (string) apply_filters( 'WC_Custom_Installments/Init/Class_Hook', $hook, $class );
	}


	/**
	 * Instantiate a class, tolerating anything that cannot be instantiated.
	 *
	 * A class whose constructor requires arguments is skipped rather than
	 * fataled: discovery is automatic, so one malformed class must not take the
	 * whole store down.
	 *
	 * @since 6.0.0
	 * @param string $class | Fully qualified class name
	 * @return object|null
	 */
	private function safe_instance_class( $class ) {
		if ( isset( $this->instantiated_classes[ $class ] ) ) {
			return $this->instantiated_classes[ $class ];
		}

		if ( ! class_exists( $class ) ) {
			return null;
		}

		try {
			$reflection = new ReflectionClass( $class );

			if ( ! $reflection->isInstantiable() ) {
				return null;
			}

			$constructor = $reflection->getConstructor();

			if ( $constructor && $constructor->getNumberOfRequiredParameters() > 0 ) {
				return null;
			}

			$instance = $reflection->newInstance();
			$this->instantiated_classes[ $class ] = $instance;

			if ( method_exists( $instance, 'init' ) && is_callable( array( $instance, 'init' ) ) ) {
				$instance->init();
			}

			return $instance;
		} catch ( Throwable $exception ) {
			Logger::error( sprintf( 'Failed to instantiate %s: %s', $class, $exception->getMessage() ) );

			return null;
		}
	}


	/**
	 * Notice shown when the site runs an unsupported PHP version.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function php_version_notice() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					__( 'Custom Installments for WooCommerce requires PHP %1$s or higher. This site runs PHP %2$s.', 'wc-custom-installments' ),
					self::MIN_PHP_VERSION,
					PHP_VERSION
				)
			)
		);
	}


	/**
	 * Notice shown when WooCommerce is missing or too old.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function woocommerce_notice() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: required WooCommerce version */
					__( 'Custom Installments for WooCommerce requires WooCommerce %s or higher to be installed and active.', 'wc-custom-installments' ),
					self::MIN_WC_VERSION
				)
			)
		);
	}
}
