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
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Admin\Settings
 * @author MeuMouse.com
 */
class Repository {

	/**
	 * Option holding every setting.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const OPTION = 'wc_custom_installments_settings';


	/**
	 * Read the current settings, merged with the defaults.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	public static function get_settings() {
		$stored = get_option( self::OPTION, array() );

		return wp_parse_args( is_array( $stored ) ? $stored : array(), Default_Options::get_defaults() );
	}


	/**
	 * Read one setting.
	 *
	 * @since 6.0.0
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
	 * @since 6.0.0
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
	 * @since 6.0.0
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
		 * @since 6.0.0
		 * @param array<string,mixed> $sanitized | Settings as stored
		 * @param array<string,mixed> $current | Settings as they were
		 */
		do_action( 'WC_Custom_Installments/Settings/Saved', $sanitized, $current );

		return $sanitized;
	}


	/**
	 * Restore every setting to its default.
	 *
	 * @since 6.0.0
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
	 * @since 6.0.0
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
				$value = self::resolve_alias( sanitize_text_field( (string) $value ), $definition );

				return in_array( $value, $allowed, true ) ? $value : (string) ( $definition['default'] ?? '' );

			case 'number':
			case 'unit':
				return self::sanitize_number( $value, $definition );

			case 'textarea':
				return ! empty( $definition['html'] ) ? wp_kses_post( (string) $value ) : sanitize_textarea_field( (string) $value );

			case 'color':
				$color = self::sanitize_color( $value );

				return '' !== $color ? $color : (string) ( $definition['default'] ?? '' );

			case 'gateway-rates':
				return self::sanitize_gateway_rates( $value, $definition );

			case 'installment-fees':
				return self::sanitize_installment_fees( $value );

			case 'element-design':
				return self::sanitize_elements_design( $value, $definition );

			default:
				if ( is_array( $value ) ) {
					return map_deep( $value, 'sanitize_text_field' );
				}

				// Store copy the 5.x plugin printed as written: a bold word or a
				// line break survives, a script does not.
				if ( ! empty( $definition['html'] ) ) {
					return trim( wp_kses_post( (string) $value ) );
				}

				return sanitize_text_field( (string) $value );
		}
	}


	/**
	 * Translate a value written under an earlier name into its current one.
	 *
	 * A select declares `aliases` when one of its values was renamed: the 5.x
	 * spelling of a placement, or a value offered while 6.0 was in development.
	 * Without this, an imported or migrated value that is not one the schema
	 * offers would fall back to the default on the first save.
	 *
	 * @since 6.0.0
	 * @param string $value | Stored or submitted value
	 * @param array<string,mixed> $definition | Field definition
	 * @return string
	 */
	public static function resolve_alias( $value, $definition ) {
		$aliases = (array) ( $definition['aliases'] ?? array() );

		return isset( $aliases[ $value ] ) ? (string) $aliases[ $value ] : $value;
	}


	/**
	 * Rewrite every aliased select value of a settings array.
	 *
	 * The settings screen needs the current spelling to show the right option
	 * selected; the stored option is left alone until the next save.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $settings | Settings to normalize
	 * @return array<string,mixed>
	 */
	public static function normalize_settings( $settings ) {
		foreach ( Registry::get_field_definitions() as $key => $definition ) {
			if ( 'select' !== ( $definition['type'] ?? '' ) || empty( $definition['aliases'] ) || ! isset( $settings[ $key ] ) || ! is_string( $settings[ $key ] ) ) {
				continue;
			}

			$settings[ $key ] = self::resolve_alias( $settings[ $key ], $definition );
		}

		return $settings;
	}


	/**
	 * Accept a colour in any of the notations the 5.x picker stored.
	 *
	 * The 5.x colour picker saved `rgb()` and `rgba()` as often as hex, and
	 * `sanitize_hex_color()` alone would silently throw those away.
	 *
	 * @since 6.0.0
	 * @param mixed $value | Incoming value
	 * @return string The colour, or an empty string when it is not one.
	 */
	public static function sanitize_color( $value ) {
		$value = strtolower( trim( (string) $value ) );

		if ( '' === $value ) {
			return '';
		}

		if ( 'transparent' === $value ) {
			return $value;
		}

		$hex = sanitize_hex_color( $value );

		if ( $hex ) {
			return $hex;
		}

		// #rrggbbaa, which sanitize_hex_color() does not know.
		if ( preg_match( '/^#[a-f0-9]{8}$/', $value ) ) {
			return $value;
		}

		$number = '\s*(\d{1,3}(\.\d+)?%?)\s*';

		if ( preg_match( '/^rgba?\(' . $number . ',' . $number . ',' . $number . '(,\s*(0|1|0?\.\d+|\d{1,3}%)\s*)?\)$/', $value ) ) {
			return $value;
		}

		return '';
	}


	/**
	 * Sanitize the per-element design structure.
	 *
	 * Shape (the 5.x one): `array( 'price' => array( 'order' => 1, 'icon' =>
	 * array( 'class' => '', 'image' => '' ), 'styles' => array( 'mobile' =>
	 * array(...), 'desktop' => array(...) ) ) )`. Only elements the registry
	 * knows and only the style properties the stylesheet writes are kept.
	 *
	 * An element with a `parent` inherits every property left empty, so for
	 * those an empty value is kept as empty rather than read as zero.
	 *
	 * @since 6.0.0
	 * @param mixed $value | Incoming value
	 * @param array<string,mixed> $definition | Field definition
	 * @return array<string,array<string,mixed>>
	 */
	private static function sanitize_elements_design( $value, $definition ) {
		if ( ! is_array( $value ) ) {
			return (array) ( $definition['default'] ?? array() );
		}

		$units = (array) ( $definition['units'] ?? Registry::UNITS );
		$weights = (array) ( $definition['weights'] ?? array( '400' ) );
		$clean = array();

		foreach ( \MeuMouse\WC_Custom_Installments\Frontend\Element_Registry::all() as $key => $element ) {
			$stored = isset( $value[ $key ] ) && is_array( $value[ $key ] ) ? $value[ $key ] : array();
			$icon = isset( $stored['icon'] ) && is_array( $stored['icon'] ) ? $stored['icon'] : array();
			$inherits = ! empty( $element['parent'] );

			$clean[ $key ] = array(
				'order' => isset( $stored['order'] ) ? max( 1, absint( $stored['order'] ) ) : (int) $element['order'],
				'icon' => array(
					'class' => isset( $icon['class'] ) ? trim( preg_replace( '/[^A-Za-z0-9_\- ]/', '', (string) $icon['class'] ) ) : (string) $element['icon']['class'],
					'image' => isset( $icon['image'] ) ? self::sanitize_image_url( $icon['image'] ) : (string) $element['icon']['image'],
				),
				'styles' => array(),
			);

			foreach ( array( 'mobile', 'desktop' ) as $device ) {
				$base = $element['styles'][ $device ];
				$styles = isset( $stored['styles'][ $device ] ) && is_array( $stored['styles'][ $device ] ) ? $stored['styles'][ $device ] : array();
				$font_color = self::sanitize_color( $styles['font_color'] ?? '' );
				$background = self::sanitize_color( $styles['background_color'] ?? '' );
				$font_unit = (string) ( $styles['font_unit'] ?? $base['font_unit'] );
				$font_weight = (string) ( $styles['font_weight'] ?? $base['font_weight'] );

				$clean[ $key ]['styles'][ $device ] = array(
					'font_size' => self::sanitize_length( $styles['font_size'] ?? $base['font_size'], $base['font_size'], $inherits ),
					'font_unit' => in_array( $font_unit, $units, true ) ? $font_unit : $base['font_unit'],
					'font_weight' => in_array( $font_weight, $weights, true ) ? $font_weight : $base['font_weight'],
					'font_color' => '' !== $font_color ? $font_color : $base['font_color'],
					'background_color' => '' !== $background ? $background : $base['background_color'],
					'margin' => self::sanitize_box( $styles['margin'] ?? array(), $base['margin'], $units, $inherits ),
					'padding' => self::sanitize_box( $styles['padding'] ?? array(), $base['padding'], $units, $inherits ),
					'border_radius' => self::sanitize_box( $styles['border_radius'] ?? array(), $base['border_radius'], $units, $inherits ),
				);
			}
		}

		return $clean;
	}


	/**
	 * Keep an icon image address only when it points at a web resource.
	 *
	 * @since 6.0.0
	 * @param mixed $value | Incoming value
	 * @return string
	 */
	private static function sanitize_image_url( $value ) {
		$url = esc_url_raw( trim( (string) $value ) );

		return preg_match( '#^(https?:)?//#i', $url ) || 0 === strpos( $url, '/' ) ? $url : '';
	}


	/**
	 * Sanitize a four-sided measurement: top, right, bottom, left and a unit.
	 *
	 * @since 6.0.0
	 * @param mixed $value | Incoming value
	 * @param array<string,string> $fallback | Values to fall back on
	 * @param array<int,string> $units | Accepted units
	 * @param bool $allow_empty | Whether an empty side is kept, meaning "inherit"
	 * @return array<string,string>
	 */
	private static function sanitize_box( $value, $fallback, $units, $allow_empty = false ) {
		$value = is_array( $value ) ? $value : array();
		$box = array();

		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$box[ $side ] = self::sanitize_length( $value[ $side ] ?? ( $fallback[ $side ] ?? '0' ), $fallback[ $side ] ?? '0', $allow_empty );
		}

		$unit = (string) ( $value['unit'] ?? ( $fallback['unit'] ?? 'rem' ) );
		$box['unit'] = in_array( $unit, $units, true ) ? $unit : ( $fallback['unit'] ?? 'rem' );

		return $box;
	}


	/**
	 * Sanitize one length amount, kept as a string the way 5.x stored it.
	 *
	 * @since 6.0.0
	 * @param mixed $value | Incoming value
	 * @param string $fallback | Value when the input is not a number
	 * @param bool $allow_empty | Whether an empty value is kept, meaning "inherit"
	 * @return string
	 */
	private static function sanitize_length( $value, $fallback, $allow_empty = false ) {
		$raw = str_replace( ',', '.', trim( (string) $value ) );

		if ( '' === $raw ) {
			return $allow_empty ? '' : '0';
		}

		return is_numeric( $raw ) ? (string) ( 0 + $raw ) : (string) $fallback;
	}


	/**
	 * Clamp a numeric setting to the range its definition declares.
	 *
	 * Values are stored as strings, the way the 5.x option did, so migrated
	 * data and freshly saved data are indistinguishable.
	 *
	 * @since 6.0.0
	 * @param mixed $value | Incoming value
	 * @param array<string,mixed> $definition | Field definition
	 * @return string
	 */
	private static function sanitize_number( $value, $definition ) {
		$raw = str_replace( ',', '.', (string) $value );

		if ( '' === trim( $raw ) || ! is_numeric( $raw ) ) {
			return (string) ( $definition['default'] ?? '0' );
		}

		$number = (float) $raw;

		if ( isset( $definition['min'] ) && $number < (float) $definition['min'] ) {
			$number = (float) $definition['min'];
		}

		if ( isset( $definition['max'] ) && $number > (float) $definition['max'] ) {
			$number = (float) $definition['max'];
		}

		// Keep whole numbers whole: "12" reads better than "12.0" in a field the
		// store owner will look at again.
		return ( floor( $number ) === $number ) ? (string) (int) $number : (string) $number;
	}


	/**
	 * Sanitize a per-gateway amount map.
	 *
	 * Shape: `array( 'gateway_id' => array( 'amount' => '10', 'type' => 'percentage' ) )`,
	 * which is the shape the 5.x option used.
	 *
	 * @since 6.0.0
	 * @param mixed $value | Incoming value
	 * @param array<string,mixed> $definition | Field definition
	 * @return array<string,array<string,string>>
	 */
	private static function sanitize_gateway_rates( $value, $definition ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$methods = array_column( (array) ( $definition['methods'] ?? array() ), 'value' );
		$clean = array();

		foreach ( $value as $gateway_id => $rate ) {
			$gateway_id = sanitize_text_field( (string) $gateway_id );

			if ( '' === $gateway_id || ! is_array( $rate ) ) {
				continue;
			}

			$amount = str_replace( ',', '.', (string) ( $rate['amount'] ?? '' ) );

			if ( ! is_numeric( $amount ) ) {
				continue;
			}

			$type = sanitize_text_field( (string) ( $rate['type'] ?? 'percentage' ) );

			$clean[ $gateway_id ] = array(
				'amount' => (string) max( 0, (float) $amount ),
				'type' => in_array( $type, $methods, true ) ? $type : 'percentage',
			);
		}

		return $clean;
	}


	/**
	 * Sanitize the per-installment interest table.
	 *
	 * Shape: `array( 2 => array( 'amount' => '1.99' ) )` — the shape the 5.x
	 * option used, kept so the ported calculation reads migrated data without a
	 * conversion step.
	 *
	 * An empty row is dropped rather than stored as zero: zero means "charge no
	 * interest", which is a different statement from "fall back to the global
	 * rate".
	 *
	 * @since 6.0.0
	 * @param mixed $value | Incoming value
	 * @return array<int,array<string,string>>
	 */
	private static function sanitize_installment_fees( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();

		foreach ( $value as $installment => $fee ) {
			$installment = (int) $installment;
			$amount = is_array( $fee ) ? ( $fee['amount'] ?? '' ) : $fee;
			$amount = str_replace( ',', '.', (string) $amount );

			if ( $installment < 1 || '' === trim( $amount ) || ! is_numeric( $amount ) ) {
				continue;
			}

			$clean[ $installment ] = array( 'amount' => (string) max( 0, (float) $amount ) );
		}

		ksort( $clean );

		return $clean;
	}
}
