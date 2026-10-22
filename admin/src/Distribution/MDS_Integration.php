<?php

namespace MeuMouse\WC_Custom_Installments\Distribution;

use MeuMouse\MDS\SDK\SDK;

defined('ABSPATH') || exit;

/**
 * Carry the plugin's own updates until it is listed on WordPress.org.
 *
 * The plugin header declares `Update URI: cloud.meumouse.com`, which tells
 * WordPress to stop asking the directory about this plugin - so without
 * something answering it, no store can update at all. The 5.x installed base is
 * being migrated onto this plugin, so that is not an option.
 *
 * This is a *distribution* channel, not a licence. The plugin is free: the SDK
 * runs in its `updates_only` preset, which leaves the whole licence module inert
 * - no key is read or written, no activation is attempted, no heartbeat is
 * scheduled - and sends the update check without a `license_key`, which is how
 * the API tells a free product from a missing key. Signature verification is
 * untouched either way: every response is ed25519-verified against the embedded
 * public key, and no flag can switch that off.
 *
 * The whole class is temporary. AGENTS.md section 6 carries the checklist for
 * taking it out on the day the plugin is submitted to WordPress.org, which is
 * why it lives in a directory of its own rather than under `Core/`.
 *
 * @since 6.1.0
 * @package MeuMouse\WC_Custom_Installments\Distribution
 * @author MeuMouse.com
 */
class MDS_Integration {

	/**
	 * Product slug, as registered in MDS.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const PRODUCT_SLUG = 'wc-custom-installments';


	/**
	 * Register the product with the SDK.
	 *
	 * Called from `mds_sdk_loaded`, which is the only moment the SDK accepts a
	 * registration: it fires on `plugins_loaded` priority -100, after the newest
	 * embedded copy of the SDK has been elected across every MeuMouse product on
	 * the site. That is far earlier than the class discovery pass, which is why
	 * the call lives in the plugin file and this class is static.
	 *
	 * @since 6.1.0
	 * @param string $plugin_file | Absolute path of the plugin main file
	 * @param string $version | Plugin version
	 * @return void
	 */
	public static function register( $plugin_file, $version ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		/**
		 * Filter the configuration handed to the MDS SDK.
		 *
		 * @since 6.1.0
		 * @param array<string,mixed> $config | SDK configuration
		 * @return array<string,mixed>
		 */
		$config = (array) apply_filters( 'WC_Custom_Installments/MDS_Config', self::config( $plugin_file, $version ) );

		SDK::register( $config );
	}


	/**
	 * The configuration the SDK is registered with.
	 *
	 * Separate from register() so a test harness can assert its shape without a
	 * WordPress runtime. The preset is the whole of it: `updates_only` turns off
	 * `license`, `rollback`, `heartbeat`, `admin_menu`, `notices` and both admin
	 * panels, and leaves `updates` and `update_details` on. Spelling any of them
	 * out again in a `features` array would only invite the two to drift.
	 *
	 * @since 6.1.0
	 * @param string $plugin_file | Absolute path of the plugin main file
	 * @param string $version | Plugin version
	 * @return array<string,mixed>
	 */
	public static function config( $plugin_file, $version ) {
		return array(
			'product_slug' => self::PRODUCT_SLUG,
			'type' => 'plugin',
			'file' => plugin_basename( $plugin_file ),
			'current_version' => $version,
			'api_base_url' => WC_CUSTOM_INSTALLMENTS_API_URL,
			'api_key' => WC_CUSTOM_INSTALLMENTS_MDS_API_KEY,
			'public_key' => WC_CUSTOM_INSTALLMENTS_MDS_PUBLIC_KEY,

			// A plain literal, not a translated string: this runs on
			// plugins_loaded priority -100, and the text domain is not loaded
			// until `init`. It is only a fallback anyway - the details modal
			// prefers the name the API sends.
			'item_name' => 'Parcelas Customizadas para WooCommerce',
			'text_domain' => 'wc-custom-installments',
			'mode' => 'updates_only',
		);
	}


	/**
	 * Whether the update channel should be opened at all.
	 *
	 * Every reason to say no leaves the site making no request whatsoever,
	 * which is the failure a free plugin should have.
	 *
	 * @since 6.1.0
	 * @return bool
	 */
	public static function is_enabled() {
		$enabled = true;

		// A build staged without its Composer dependencies.
		if ( ! class_exists( SDK::class ) ) {
			$enabled = false;
		}

		// The constants are defined by Core\Init, which returns before defining
		// anything when the host runs an unsupported PHP version - and this
		// method is reached from a hook that fires either way.
		if ( ! defined('WC_CUSTOM_INSTALLMENTS_MDS_API_KEY') || ! defined('WC_CUSTOM_INSTALLMENTS_MDS_PUBLIC_KEY') ) {
			return false;
		}

		// No keys yet means the product is not published in MDS. An empty key
		// would make an unsigned request rather than a signed one, so the
		// channel stays shut instead.
		if ( '' === (string) WC_CUSTOM_INSTALLMENTS_MDS_API_KEY || '' === (string) WC_CUSTOM_INSTALLMENTS_MDS_PUBLIC_KEY ) {
			$enabled = false;
		}

		// Without sodium the SDK's verifier fails closed, so every check would
		// be refused after the request had already gone out - once an hour,
		// forever. Better to never ask.
		if ( ! function_exists('sodium_crypto_sign_verify_detached') ) {
			$enabled = false;
		}

		/**
		 * Filter whether the plugin checks for its own updates.
		 *
		 * A site owner can also switch the channel off from wp-config.php with
		 * `define( 'MDS_WC_CUSTOM_INSTALLMENTS_FEATURE_UPDATES', false )`, which
		 * the SDK reads as the final word - no filter can turn it back on.
		 *
		 * @since 6.1.0
		 * @param bool $enabled | Whether the channel is open
		 * @return bool
		 */
		return (bool) apply_filters( 'WC_Custom_Installments/MDS/Enabled', $enabled );
	}


	/**
	 * Get the registered SDK integration.
	 *
	 * @since 6.1.0
	 * @return \MeuMouse\MDS\SDK\Integration|null
	 */
	public static function integration() {
		if ( ! class_exists( SDK::class ) ) {
			return null;
		}

		return SDK::get( self::PRODUCT_SLUG );
	}


	/**
	 * Release whatever the SDK scheduled for this product.
	 *
	 * The `updates_only` preset schedules no cron event, so today this has
	 * nothing to clean up. It is wired anyway: the day the preset changes, a
	 * deactivation that left an orphan event behind would be a silent leak.
	 *
	 * @since 6.1.0
	 * @return void
	 */
	public static function shutdown() {
		$integration = self::integration();

		if ( $integration ) {
			$integration->shutdown();
		}
	}
}
