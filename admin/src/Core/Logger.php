<?php

namespace MeuMouse\WC_Custom_Installments\Core;

defined('ABSPATH') || exit;

/**
 * Write plugin log entries through the WooCommerce logger.
 *
 * Everything goes to the `wc-custom-installments` source so a store owner can
 * read it from WooCommerce > Status > Logs. Debug entries are written only when
 * debug mode is on; warnings and errors always are, because a silent failure in
 * a pricing plugin is worse than a noisy one.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Core
 * @author MeuMouse.com
 */
class Logger {

	/**
	 * Log source used for every entry.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const SOURCE = 'wc-custom-installments';


	/**
	 * Write a debug entry, when debug mode is enabled.
	 *
	 * @since 6.0.0
	 * @param string $message | Message to record
	 * @param array $context | Additional context to append
	 * @return void
	 */
	public static function debug( $message, $context = array() ) {
		if ( ! defined('WC_CUSTOM_INSTALLMENTS_DEBUG_MODE') || ! WC_CUSTOM_INSTALLMENTS_DEBUG_MODE ) {
			return;
		}

		self::write( 'debug', $message, $context );
	}


	/**
	 * Write a warning entry.
	 *
	 * @since 6.0.0
	 * @param string $message | Message to record
	 * @param array $context | Additional context to append
	 * @return void
	 */
	public static function warning( $message, $context = array() ) {
		self::write( 'warning', $message, $context );
	}


	/**
	 * Write an error entry.
	 *
	 * @since 6.0.0
	 * @param string $message | Message to record
	 * @param array $context | Additional context to append
	 * @return void
	 */
	public static function error( $message, $context = array() ) {
		self::write( 'error', $message, $context );
	}


	/**
	 * Hand one entry to the WooCommerce logger.
	 *
	 * Falls back to error_log() only when WooCommerce is not loaded yet, which
	 * happens for bootstrap-level failures.
	 *
	 * @since 6.0.0
	 * @param string $level | PSR-3 log level
	 * @param string $message | Message to record
	 * @param array $context | Additional context to append
	 * @return void
	 */
	private static function write( $level, $message, $context = array() ) {
		$message = (string) $message;

		if ( ! empty( $context ) ) {
			$message .= ' ' . wp_json_encode( $context );
		}

		if ( ! function_exists('wc_get_logger') ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- WooCommerce is not loaded yet; there is nowhere else to write.
			error_log( sprintf( '[%s] %s', self::SOURCE, $message ) );

			return;
		}

		wc_get_logger()->log( $level, $message, array( 'source' => self::SOURCE ) );
	}
}
