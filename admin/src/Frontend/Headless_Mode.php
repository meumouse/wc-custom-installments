<?php

namespace MeuMouse\WC_Custom_Installments\Frontend;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * Run without a storefront, for a shop that renders its own.
 *
 * A headless store draws its pages somewhere else and reads WooCommerce through
 * the Store API. Everything this plugin prints there - the price group, the
 * payment methods block, the shortcodes, the widgets, the CSS and the runtime -
 * would be markup nobody sees, or worse, markup inside the `price_html` the
 * frontend does read. So none of it is built, while the engine, the admin, the
 * REST routes and the Store API data carry on.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Frontend
 * @author MeuMouse.com
 */
class Headless_Mode {

	/**
	 * Classes that put something on the storefront.
	 *
	 * Only markup and assets. What decides a price - the cart fees, the tier
	 * and dynamic pricing integrations, the structured data - stays, because a
	 * headless checkout still charges through WooCommerce.
	 *
	 * @since 6.0.0
	 * @var array<int,string>
	 */
	const STOREFRONT_CLASSES = array(
		'MeuMouse\\WC_Custom_Installments\\Frontend\\Render_Elements',
		'MeuMouse\\WC_Custom_Installments\\Frontend\\Shortcodes',
		'MeuMouse\\WC_Custom_Installments\\Frontend\\Styles',
		'MeuMouse\\WC_Custom_Installments\\Frontend\\Templates',
		'MeuMouse\\WC_Custom_Installments\\Assets\\Storefront_Assets',
		'MeuMouse\\WC_Custom_Installments\\Integrations\\Elementor',
		'MeuMouse\\WC_Custom_Installments\\Integrations\\Elementor\\Inject_Controllers',
		'MeuMouse\\WC_Custom_Installments\\Integrations\\Theme_Compatibility',
		'MeuMouse\\WC_Custom_Installments\\Integrations\\Woodmart',
	);


	/**
	 * Withhold the storefront classes when the mode is on.
	 *
	 * Registered from the bootstrap, like `Conflict_Guard`, so the filter is in
	 * place before the instantiation pass reads the list. The setting itself is
	 * read inside the callback, on `init`: reading it here, on
	 * `plugins_loaded`, would load the translations too early.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public static function bootstrap() {
		add_filter( 'WC_Custom_Installments/Init/Instance_Classes', array( __CLASS__, 'maybe_withhold_storefront' ), 99 );
	}


	/**
	 * Whether the store runs headless.
	 *
	 * Read from the stored value rather than through `Repository::is_active()`:
	 * the first call happens before the Pro add-on has registered its fields,
	 * and `is_active()` would cache a field map that is missing them for the
	 * rest of the request. The setting has no feature gate to resolve anyway.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function is_enabled() {
		/**
		 * Filter whether the store runs headless.
		 *
		 * Lets a deployment force the mode from code, whatever the setting says.
		 *
		 * @since 6.0.0
		 * @param bool $enabled | Whether the setting is on
		 * @return bool
		 */
		return (bool) apply_filters( 'WC_Custom_Installments/Headless/Is_Enabled', Helpers::is_enabled( Repository::get('headless_mode') ) );
	}


	/**
	 * The classes that render on the storefront.
	 *
	 * @since 6.0.0
	 * @return array<int,string>
	 */
	public static function storefront_classes() {
		/**
		 * Filter the classes that render on the storefront.
		 *
		 * An add-on that prints markup of its own appends its classes here, and
		 * they stand down with the rest in headless mode and beside the 5.x
		 * plugin.
		 *
		 * @since 6.0.0
		 * @param array<int,string> $classes | Fully qualified class names
		 * @return array<int,string>
		 */
		return (array) apply_filters( 'WC_Custom_Installments/Storefront/Classes', self::STOREFRONT_CLASSES );
	}


	/**
	 * Keep the storefront classes from being built.
	 *
	 * @since 6.0.0
	 * @param array<int,string> $classes | Classes about to be instantiated
	 * @return array<int,string>
	 */
	public static function withhold_storefront( $classes ) {
		return array_values( array_diff( (array) $classes, self::storefront_classes() ) );
	}


	/**
	 * Withhold the storefront classes, if the store runs headless.
	 *
	 * @since 6.0.0
	 * @param array<int,string> $classes | Classes about to be instantiated
	 * @return array<int,string>
	 */
	public static function maybe_withhold_storefront( $classes ) {
		return self::is_enabled() ? self::withhold_storefront( $classes ) : $classes;
	}
}
