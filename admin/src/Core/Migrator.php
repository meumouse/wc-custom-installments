<?php

namespace MeuMouse\WC_Custom_Installments\Core;

use MeuMouse\WC_Custom_Installments\Admin\Default_Options;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;

defined('ABSPATH') || exit;

/**
 * Carry a 5.x installation's configuration into the new option.
 *
 * The predecessor plugin kept its settings in `woo-custom-installments-setting`
 * plus three sidecar options for the gateway rates and the interest table. A
 * store upgrading has years of configuration in there, and losing any of it
 * means a wrong price on a live shop.
 *
 * Two rules govern everything here:
 *
 *   1. **Nothing legacy is deleted.** The old options stay exactly where they
 *      were, so rolling back to 5.x finds its data intact and a failed
 *      migration can simply be run again.
 *   2. **Stored beats default, always.** A value the store owner set wins over
 *      whatever we think the sensible default is, including for settings whose
 *      default changed between the two plugins.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Core
 * @author MeuMouse.com
 */
class Migrator {

	/**
	 * Option recording the schema version the database is at.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const VERSION_OPTION = 'wc_custom_installments_db_version';

	/**
	 * Current schema version.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const VERSION = 1;

	/**
	 * The 5.x main settings option.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const LEGACY_OPTION = 'woo-custom-installments-setting';

	/**
	 * The 5.x sidecar options, mapped to their new setting names.
	 *
	 * @since 1.0.0
	 * @var array<string,string>
	 */
	const LEGACY_SIDECARS = array(
		'woo_custom_installments_discounts_setting' => 'gateway_discounts',
		'woo_custom_installments_interests_setting' => 'gateway_interests',
		'woo_custom_installments_custom_fee_installments' => 'custom_fee_installments',
	);


	/**
	 * Run the migration as early as an admin request allows.
	 *
	 * `admin_init` rather than an activation hook: a store that upgrades by
	 * uploading a ZIP over the old plugin never fires an activation, and a
	 * network activation fires it on the wrong site.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate' ), 5 );
	}


	/**
	 * Migrate when the database is behind, and only then.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function maybe_migrate() {
		if ( (int) get_option( self::VERSION_OPTION, 0 ) >= self::VERSION ) {
			return;
		}

		$report = self::migrate();

		update_option( self::VERSION_OPTION, self::VERSION, false );

		if ( $report['migrated'] > 0 ) {
			Logger::warning( 'Imported settings from the 5.x plugin.', $report );
		}
	}


	/**
	 * Copy whatever a 5.x installation left behind.
	 *
	 * Safe to call twice: it reads the legacy options and writes the new one,
	 * with no destructive step anywhere.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed> What was imported, what was skipped.
	 */
	public static function migrate() {
		$report = array(
			'migrated' => 0,
			'skipped' => array(),
			'sidecars' => array(),
		);

		$legacy = get_option( self::LEGACY_OPTION, array() );
		$legacy = is_array( $legacy ) ? $legacy : array();

		if ( empty( $legacy ) && ! self::has_sidecars() ) {
			return $report;
		}

		$defaults = Default_Options::get_defaults();
		$settings = Repository::get_settings();

		foreach ( $legacy as $key => $value ) {
			$key = (string) $key;

			// A setting the new plugin does not have is left behind rather than
			// stored: an option array full of keys nothing reads is how the 5.x
			// option grew to the size it did.
			if ( ! array_key_exists( $key, $defaults ) ) {
				$report['skipped'][] = $key;

				continue;
			}

			$settings[ $key ] = $value;
			$report['migrated']++;
		}

		foreach ( self::LEGACY_SIDECARS as $option => $setting ) {
			$stored = maybe_unserialize( get_option( $option, '' ) );

			if ( ! is_array( $stored ) || empty( $stored ) ) {
				continue;
			}

			$settings[ $setting ] = $stored;
			$report['sidecars'][] = $setting;
			$report['migrated']++;
		}

		/**
		 * Filter the settings assembled from a 5.x installation.
		 *
		 * Runs before anything is written, so an add-on can map a setting the
		 * free plugin dropped into one of its own.
		 *
		 * @since 1.0.0
		 * @param array<string,mixed> $settings | Settings about to be stored
		 * @param array<string,mixed> $legacy | The 5.x option, as found
		 * @return array<string,mixed>
		 */
		$settings = (array) apply_filters( 'WC_Custom_Installments/Migrate/Settings', $settings, $legacy );

		update_option( Repository::OPTION, $settings );

		/**
		 * Fires after a 5.x installation has been imported.
		 *
		 * @since 1.0.0
		 * @param array<string,mixed> $report | What was imported and what was skipped
		 */
		do_action( 'WC_Custom_Installments/Migrate/Completed', $report );

		return $report;
	}


	/**
	 * Whether any of the 5.x sidecar options exists.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private static function has_sidecars() {
		foreach ( array_keys( self::LEGACY_SIDECARS ) as $option ) {
			if ( false !== get_option( $option, false ) ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Whether this site carries a 5.x configuration.
	 *
	 * The setup wizard asks: an install that already has settings should not be
	 * walked through choosing them again.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function has_legacy_data() {
		$legacy = get_option( self::LEGACY_OPTION, array() );

		return ( is_array( $legacy ) && ! empty( $legacy ) ) || self::has_sidecars();
	}
}
