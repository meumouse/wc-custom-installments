<?php

namespace MeuMouse\WC_Custom_Installments\Compatibility;

defined('ABSPATH') || exit;

/**
 * Fire every plugin hook under its current name and its 5.x name.
 *
 * The predecessor plugin (`woo-custom-installments`) published 54 hooks under
 * the `Woo_Custom_Installments/...` namespace. Store owners have snippets in
 * `functions.php` bound to them, and agencies have child themes built on them.
 * Renaming our own classes is not a reason for any of that to stop working, so
 * every call site goes through this class: the canonical name runs first, the
 * 5.x alias runs second on the same value, and a listener on either one sees
 * and can change the result.
 *
 * The aliases carry **no deprecation notice**. They are supported, not
 * tolerated — a warning would be us asking the store owner to pay for our
 * refactor.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Compatibility
 * @author MeuMouse.com
 */
class Hook_Aliases {

	/**
	 * Namespace prefix used by the current plugin.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PREFIX = 'WC_Custom_Installments/';

	/**
	 * Namespace prefix used by the 5.x plugin.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const LEGACY_PREFIX = 'Woo_Custom_Installments/';

	/**
	 * Aliases that do not follow the plain prefix swap.
	 *
	 * A hook whose surrounding class moved to a different area keeps its old
	 * path here, because the name a third party wrote down is the contract, not
	 * the file it happened to live in.
	 *
	 * @since 1.0.0
	 * @var array<string,string>
	 */
	private static $overrides = array(
		'WC_Custom_Installments/Core/Before_Init' => 'Woo_Custom_Installments/Before_Init',
		'WC_Custom_Installments/Core/Init'        => 'Woo_Custom_Installments/Init',
	);


	/**
	 * Resolve the 5.x name of a hook.
	 *
	 * @since 1.0.0
	 * @param string $hook | Canonical hook name
	 * @return string Empty when the hook has no 5.x counterpart.
	 */
	public static function legacy_name( $hook ) {
		if ( isset( self::$overrides[ $hook ] ) ) {
			return self::$overrides[ $hook ];
		}

		if ( 0 !== strpos( $hook, self::PREFIX ) ) {
			return '';
		}

		return self::LEGACY_PREFIX . substr( $hook, strlen( self::PREFIX ) );
	}


	/**
	 * Apply a filter under both names.
	 *
	 * @since 1.0.0
	 * @param string $hook | Canonical hook name
	 * @param mixed $value | Value being filtered
	 * @param mixed ...$args | Additional arguments passed to the listeners
	 * @return mixed
	 */
	public static function apply_filters( $hook, $value, ...$args ) {
		$value = apply_filters( $hook, $value, ...$args );
		$legacy = self::legacy_name( $hook );

		if ( '' === $legacy ) {
			return $value;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Preserved 5.x alias; the canonical name ran above.
		return apply_filters( $legacy, $value, ...$args );
	}


	/**
	 * Fire an action under both names.
	 *
	 * @since 1.0.0
	 * @param string $hook | Canonical hook name
	 * @param mixed ...$args | Arguments passed to the listeners
	 * @return void
	 */
	public static function do_action( $hook, ...$args ) {
		do_action( $hook, ...$args );

		$legacy = self::legacy_name( $hook );

		if ( '' === $legacy ) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Preserved 5.x alias; the canonical name ran above.
		do_action( $legacy, ...$args );
	}


	/**
	 * Whether anything is listening on either name of a hook.
	 *
	 * Useful where building the filtered value is itself expensive.
	 *
	 * @since 1.0.0
	 * @param string $hook | Canonical hook name
	 * @return bool
	 */
	public static function has_listeners( $hook ) {
		if ( has_filter( $hook ) ) {
			return true;
		}

		$legacy = self::legacy_name( $hook );

		return '' !== $legacy && has_filter( $legacy );
	}


	/**
	 * Register an additional alias at runtime.
	 *
	 * An add-on that publishes a hook of its own can map it here so the same
	 * guarantee covers it.
	 *
	 * @since 1.0.0
	 * @param string $hook | Canonical hook name
	 * @param string $legacy | Name to keep firing alongside it
	 * @return void
	 */
	public static function register_alias( $hook, $legacy ) {
		if ( '' === (string) $hook || '' === (string) $legacy ) {
			return;
		}

		self::$overrides[ $hook ] = $legacy;
	}


	/**
	 * Get the full alias map, for documentation generation.
	 *
	 * @since 1.0.0
	 * @return array<string,string>
	 */
	public static function get_overrides() {
		return self::$overrides;
	}
}
