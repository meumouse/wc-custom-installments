<?php

namespace MeuMouse\WC_Custom_Installments\Campaigns;

use MeuMouse\WC_Custom_Installments\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * Where campaigns are stored: a private post type.
 *
 * A post gives a campaign a name, a status (published is active, a draft is
 * inactive), a trash that can be emptied or restored, and dates, for free. It
 * has no screen or REST route of its own - the campaigns screen and its
 * endpoints are the only way in - and every capability it checks is the
 * plugin's.
 *
 * The names below live in the database and cannot change (AGENTS.md §5).
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Campaigns
 * @author MeuMouse.com
 */
class Post_Type {

	/**
	 * Post type name.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const NAME = 'wcci_campaign';

	/**
	 * Meta key holding the campaign's rules (Rules::sanitize()).
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const META_RULES = '_wcci_campaign_rules';


	/**
	 * Register the post type.
	 *
	 * The plugin's classes are built on `init`, so the type is registered right
	 * away rather than on a later hook.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		if ( did_action('init') || doing_action('init') ) {
			self::register();
		} else {
			add_action( 'init', array( __CLASS__, 'register' ) );
		}
	}


	/**
	 * Register the post type, once.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public static function register() {
		if ( post_type_exists( self::NAME ) ) {
			return;
		}

		$capability = Helpers::capability();

		register_post_type( self::NAME, array(
			'labels' => array(
				'name' => __( 'Campanhas', 'wc-custom-installments' ),
				'singular_name' => __( 'Campanha', 'wc-custom-installments' ),
			),
			'public' => false,
			'show_ui' => false,
			'show_in_menu' => false,
			'show_in_rest' => false,
			'show_in_nav_menus' => false,
			'exclude_from_search' => true,
			'publicly_queryable' => false,
			'query_var' => false,
			'rewrite' => false,
			'can_export' => true,
			'supports' => array( 'title' ),
			'map_meta_cap' => false,
			'capabilities' => array(
				'edit_post' => $capability,
				'read_post' => $capability,
				'delete_post' => $capability,
				'edit_posts' => $capability,
				'edit_others_posts' => $capability,
				'delete_posts' => $capability,
				'publish_posts' => $capability,
				'read_private_posts' => $capability,
				'create_posts' => $capability,
			),
		) );
	}
}
