<?php

namespace MeuMouse\WC_Custom_Installments\Campaigns;

defined('ABSPATH') || exit;

/**
 * Announce the moment a campaign starts or ends.
 *
 * Prices never wait for this: Resolver judges every campaign against the clock
 * when a price is built. What cannot see the clock is a copy of a price made
 * earlier - a cached page, a shopping feed - so at each boundary the index is
 * rebuilt and `WC_Custom_Installments/Campaigns/Changed` fires for whatever
 * keeps such copies.
 *
 * Action Scheduler runs the boundaries when WooCommerce provides it, WP-Cron
 * otherwise.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Campaigns
 * @author MeuMouse.com
 */
class Scheduler {

	/**
	 * Hook each boundary runs on.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const HOOK = 'wc_custom_installments_campaign_boundary';

	/**
	 * Action Scheduler group.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const GROUP = 'wc-custom-installments';


	/**
	 * Register the hooks.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		add_action( self::HOOK, array( __CLASS__, 'run_boundary' ) );
		add_action( 'WC_Custom_Installments/Campaigns/Index_Rebuilt', array( __CLASS__, 'schedule' ) );
	}


	/**
	 * Schedule the next start and end of every indexed campaign.
	 *
	 * Everything scheduled before is dropped first, so an edited date never
	 * leaves its old boundary behind.
	 *
	 * @since 6.0.0
	 * @param array<int,array<string,mixed>> $entries | Compiled active campaigns
	 * @return void
	 */
	public static function schedule( $entries ) {
		$use_action_scheduler = function_exists('as_schedule_single_action') && function_exists('as_unschedule_all_actions');

		if ( $use_action_scheduler ) {
			as_unschedule_all_actions( self::HOOK );
		} elseif ( function_exists('wp_unschedule_hook') ) {
			wp_unschedule_hook( self::HOOK );
		}

		$now = Resolver::now();

		foreach ( (array) $entries as $entry ) {
			// The end is inclusive to the second, so it takes effect a second later.
			$moments = array( (int) $entry['starts'], $entry['ends'] ? (int) $entry['ends'] + 1 : 0 );

			foreach ( $moments as $moment ) {
				if ( $moment <= $now ) {
					continue;
				}

				$args = array( (int) $entry['id'] );

				if ( $use_action_scheduler ) {
					as_schedule_single_action( $moment, self::HOOK, $args, self::GROUP );
				} else {
					wp_schedule_single_event( $moment, self::HOOK, $args );
				}
			}
		}
	}


	/**
	 * A campaign started or ended: refresh the index and announce it.
	 *
	 * @since 6.0.0
	 * @param int $campaign_id | Campaign ID
	 * @return void
	 */
	public static function run_boundary( $campaign_id ) {
		Repository::changed( array( (int) $campaign_id ) );
	}
}
