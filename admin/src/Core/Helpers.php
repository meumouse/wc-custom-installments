<?php

namespace MeuMouse\WC_Custom_Installments\Core;

defined('ABSPATH') || exit;

/**
 * Small shared helpers used across the plugin.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Core
 * @author MeuMouse.com
 */
class Helpers {

	/**
	 * Whether the current request is one of the plugin's admin screens.
	 *
	 * @since 1.0.0
	 * @param string $page | Optional exact page slug to match
	 * @return bool
	 */
	public static function is_plugin_screen( $page = '' ) {
		$current = self::current_admin_page();

		if ( '' === $current ) {
			return false;
		}

		if ( '' !== $page ) {
			return $page === $current;
		}

		return 0 === strpos( $current, WC_CUSTOM_INSTALLMENTS_SLUG );
	}


	/**
	 * Read the `page` query argument of the admin screen being rendered.
	 *
	 * No nonce check applies: this only reports which screen WordPress already
	 * decided to render, and nothing is acted upon.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function current_admin_page() {
		if ( ! is_admin() ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only; see the docblock.
		if ( ! isset( $_GET['page'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only; see the docblock.
		return sanitize_text_field( wp_unslash( $_GET['page'] ) );
	}


	/**
	 * The capability required to manage the plugin.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filter the capability required to read and change plugin settings.
		 *
		 * @since 1.0.0
		 * @param string $capability | Capability name
		 * @return string
		 */
		return (string) apply_filters( 'WC_Custom_Installments/Admin/Capability', 'manage_woocommerce' );
	}


	/**
	 * Whether the Pro add-on is installed and running.
	 *
	 * Says nothing about the licence — ask Features for that.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_pro_active() {
		return defined('WC_CUSTOM_INSTALLMENTS_PRO_VERSION');
	}


	/**
	 * Whether the 5.x plugin is active alongside this one.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_legacy_plugin_active() {
		return defined('WOO_CUSTOM_INSTALLMENTS_VERSION') || class_exists('MeuMouse\\Woo_Custom_Installments\\Core\\Init');
	}


	/**
	 * Normalise a stored toggle into a boolean.
	 *
	 * Settings persist toggles as the strings `yes` and `no`, which the 5.x
	 * option format also used, so migrated data needs no conversion.
	 *
	 * @since 1.0.0
	 * @param mixed $value | Stored value
	 * @return bool
	 */
	public static function is_enabled( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( (string) $value ), array( 'yes', '1', 'true', 'on' ), true );
	}


	/**
	 * Convert a boolean into the stored toggle representation.
	 *
	 * @since 1.0.0
	 * @param mixed $value | Value to normalise
	 * @return string
	 */
	public static function to_toggle( $value ) {
		return self::is_enabled( $value ) ? 'yes' : 'no';
	}
}
