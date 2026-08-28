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

// The classes under test, loaded directly: the harness has no autoloader.
require_once __DIR__ . '/../admin/src/Core/Features.php';
require_once __DIR__ . '/../admin/src/Core/Helpers.php';
require_once __DIR__ . '/../admin/src/Compatibility/Hook_Aliases.php';
require_once __DIR__ . '/../admin/src/Admin/Default_Options.php';
require_once __DIR__ . '/../admin/src/Admin/Settings/Registry.php';
require_once __DIR__ . '/../admin/src/Admin/Settings/Repository.php';
