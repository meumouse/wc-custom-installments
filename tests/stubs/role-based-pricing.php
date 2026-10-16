<?php

/**
 * The slice of Role Based Pricing for Woo the integration reaches for.
 *
 * Loaded part way through `tests/integrations-test.php`, so the harness can
 * first check what happens with the plugin absent and only then with it
 * present. Both classes carry the real names and the real method names: the
 * integration finds the plugin by `class_exists()` and reads the setting by
 * `method_exists()`, so a stub spelled differently would prove nothing.
 *
 * @since 6.0.0
 */

namespace MeowCrew\RoleAndCustomerBasedPricing {

	/**
	 * The plugin's bootstrap, which is what the integration looks for.
	 */
	class RoleAndCustomerBasedPricingPlugin {

		const VERSION = '2.1.0';
	}
}

namespace MeowCrew\RoleAndCustomerBasedPricing\Settings {

	/**
	 * The settings service, reading the same option the plugin writes.
	 */
	class Settings {

		const SETTINGS_PREFIX = 'role_and_customer_based_pricing_';


		/**
		 * Whether the store hides prices from visitors.
		 *
		 * @return bool
		 */
		public function doHidePricesForNonLoggedInUsers() {
			return 'yes' === get_option( self::SETTINGS_PREFIX . 'hide_prices_for_non_logged_in_users', 'no' );
		}
	}
}

namespace MeowCrew\RoleAndCustomerBasedPricing\Core {

	use MeowCrew\RoleAndCustomerBasedPricing\Settings\Settings;

	/**
	 * The plugin's service container.
	 *
	 * `$reachable` stands in for the release that reorganised its internals:
	 * the container is there, `getSettings()` answers null, and the integration
	 * has to fall back to the stored option.
	 */
	class ServiceContainer {

		/**
		 * Whether the settings service can be resolved.
		 *
		 * @var bool
		 */
		public static $reachable = true;

		/**
		 * The single instance.
		 *
		 * @var self|null
		 */
		private static $instance = null;


		/**
		 * Get the instance.
		 *
		 * @return self
		 */
		public static function getInstance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}


		/**
		 * Get the settings service.
		 *
		 * @return Settings|null
		 */
		public function getSettings() {
			return self::$reachable ? new Settings() : null;
		}
	}
}
