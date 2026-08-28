<?php

namespace MeuMouse\WC_Custom_Installments\Admin\Settings;

defined('ABSPATH') || exit;

/**
 * Move a configuration between stores.
 *
 * The export is a plain JSON document carrying the settings and enough context
 * to refuse an import that would not mean what it says — a file from a much
 * newer plugin, or from something else entirely.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Admin\Settings
 * @author MeuMouse.com
 */
class Transfer {

	/**
	 * Marker identifying a file this plugin produced.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SIGNATURE = 'wc-custom-installments-settings';


	/**
	 * Build the export payload.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed>
	 */
	public static function export() {
		return array(
			'signature' => self::SIGNATURE,
			'version' => WC_CUSTOM_INSTALLMENTS_VERSION,
			'exported_at' => gmdate('c'),
			'settings' => Repository::get_settings(),
		);
	}


	/**
	 * Read an export payload back into the settings.
	 *
	 * The settings go through the repository, so a file carrying a Pro setting
	 * imported into an unlicensed store leaves that setting untouched, exactly
	 * as if it had been submitted from the settings screen.
	 *
	 * @since 1.0.0
	 * @param mixed $payload | Decoded export document
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function import( $payload ) {
		if ( ! is_array( $payload ) ) {
			return new \WP_Error(
				'wcci_invalid_import',
				__( 'That file could not be read as settings.', 'wc-custom-installments' )
			);
		}

		if ( ( $payload['signature'] ?? '' ) !== self::SIGNATURE ) {
			return new \WP_Error(
				'wcci_foreign_import',
				__( 'That file was not exported by this plugin.', 'wc-custom-installments' )
			);
		}

		if ( empty( $payload['settings'] ) || ! is_array( $payload['settings'] ) ) {
			return new \WP_Error(
				'wcci_empty_import',
				__( 'That file carries no settings.', 'wc-custom-installments' )
			);
		}

		return Repository::save_settings( $payload['settings'] );
	}
}
