<?php

namespace MeuMouse\WC_Custom_Installments\Admin\Settings;

use MeuMouse\WC_Custom_Installments\Admin\Default_Options;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * Read and write the plugin settings.
 *
 * Everything lives in one option, and every write goes through here. That is
 * what makes the feature gate enforceable: a locked field is rejected in one
 * place, so no endpoint, importer or wizard step can slip past it.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Admin\Settings
 * @author MeuMouse.com
 */
class Repository {

	/**
	 * Option holding every setting.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION = 'wc_custom_installments_settings';


	/**
	 * Read the current settings, merged with the defaults.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed>
	 */
	public static function get_settings() {
		$stored = get_option( self::OPTION, array() );

		return wp_parse_args( is_array( $stored ) ? $stored : array(), Default_Options::get_defaults() );
	}


	/**
	 * Read one setting.
	 *
	 * @since 1.0.0
	 * @param string $key | Setting name
	 * @param mixed $fallback | Returned when the key is absent
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$settings = self::get_settings();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}


	/**
	 * Whether a toggle is on **and** its feature is available.
	 *
	 * This is what the engine asks. Reading the raw setting instead would let a
	 * value stored while the add-on was licensed keep taking effect after the
	 * licence lapsed.
	 *
	 * @since 1.0.0
	 * @param string $key | Setting name
	 * @return bool
	 */
	public static function is_active( $key ) {
		$definitions = Registry::get_field_definitions();
		$feature = $definitions[ $key ]['feature'] ?? '';

		if ( '' !== $feature && ! Features::is_available( $feature ) ) {
			return false;
		}

		return Helpers::is_enabled( self::get( $key ) );
	}


	/**
	 * Persist a settings payload and return what was stored.
	 *
	 * Unknown keys are ignored, locked fields keep their stored value, and every
	 * accepted value is sanitized by its declared type.
	 *
	 * @since 1.0.0
	 * @param array<string,mixed> $incoming | Payload submitted by the client
	 * @return array<string,mixed>
	 */
	public static function save_settings( $incoming ) {
		$incoming = is_array( $incoming ) ? $incoming : array();
		$definitions = Registry::get_field_definitions();
		$current = self::get_settings();
		$sanitized = $current;

		foreach ( $definitions as $key => $definition ) {
			$feature = $definition['feature'] ?? '';

			// A locked field is never written. The browser is told it is locked,
			// but the browser is not what enforces it.
			if ( '' !== $feature && ! Features::is_available( $feature ) ) {
				continue;
			}

			// An absent toggle means "off": an unchecked box submits nothing.
			if ( 'toggle' === ( $definition['type'] ?? '' ) && ! array_key_exists( $key, $incoming ) ) {
				$sanitized[ $key ] = 'no';

				continue;
			}

			if ( ! array_key_exists( $key, $incoming ) ) {
				continue;
			}

			$sanitized[ $key ] = self::sanitize_value( $incoming[ $key ], $definition );
		}

		update_option( self::OPTION, $sanitized );

		/**
		 * Fires after the settings are stored, with both sides of the change.
		 *
		 * The useful signal is rarely the new value on its own — it is the
		 * transition, so the previous values travel along.
		 *
		 * @since 1.0.0
		 * @param array<string,mixed> $sanitized | Settings as stored
		 * @param array<string,mixed> $current | Settings as they were
		 */
		do_action( 'WC_Custom_Installments/Settings/Saved', $sanitized, $current );

		return $sanitized;
	}


	/**
	 * Restore every setting to its default.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed>
	 */
	public static function reset_settings() {
		$defaults = Default_Options::get_defaults();

		update_option( self::OPTION, $defaults );

		do_action( 'WC_Custom_Installments/Settings/Reset', $defaults );

		return $defaults;
	}


	/**
	 * Sanitize one value according to its field definition.
	 *
	 * @since 1.0.0
	 * @param mixed $value | Incoming value
	 * @param array<string,mixed> $definition | Field definition
	 * @return mixed
	 */
	private static function sanitize_value( $value, $definition ) {
		$type = $definition['type'] ?? 'text';

		switch ( $type ) {
			case 'toggle':
				return Helpers::to_toggle( $value );

			case 'select':
				$allowed = array_column( (array) ( $definition['options'] ?? array() ), 'value' );
				$value = sanitize_text_field( (string) $value );

				return in_array( $value, $allowed, true ) ? $value : (string) ( $definition['default'] ?? '' );

			case 'textarea':
				return sanitize_textarea_field( (string) $value );

			case 'color':
				return sanitize_hex_color( (string) $value ) ?? (string) ( $definition['default'] ?? '' );

			default:
				if ( is_array( $value ) ) {
					return map_deep( $value, 'sanitize_text_field' );
				}

				return sanitize_text_field( (string) $value );
		}
	}
}
