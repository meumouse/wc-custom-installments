<?php

namespace MeuMouse\WC_Custom_Installments\Compatibility;

defined('ABSPATH') || exit;

/**
 * Answer the Plugin Dependencies lookup for this plugin's own slug.
 *
 * The Pro add-on declares `Requires Plugins: wc-custom-installments`, and from
 * that header WordPress asks api.wordpress.org for the dependency's name,
 * version and description every time the plugins screen or the plugin installer
 * is drawn. This plugin is not in the directory yet, so the API answers 404 —
 * and core does not cache a failed lookup: `get_dependency_api_data()` steps
 * past the `WP_Error` without storing anything, so the request goes out again on
 * every single load of plugins.php and plugin-install.php.
 *
 * Nothing has to be asked of a remote server to answer it. The plugin being
 * described is the one running, so its own header is a better source than the
 * directory would be, and the dependency row needs no more than the name, the
 * slug and the version. The filter answers `plugins_api` for this one slug and
 * leaves every other lookup untouched.
 *
 * It runs late, at priority 30, and *completes* an answer rather than replacing
 * it. The MDS SDK that carries this plugin's updates answers the same filter at
 * priority 20 without checking whether anything already had, and its payload is
 * deliberately narrow - the release, its changelog and the fields core needs to
 * offer an update. Everything the dependency card and the details modal read
 * besides that (the author, the description, the icon, the rating counts) it
 * does not set, and on PHP 8 an absent one surfaces as an undefined property
 * notice. So the SDK's half wins wherever it has something to say, this class
 * fills in the rest from the header, and the two sections sit side by side.
 *
 * @since 6.1.0
 * @package MeuMouse\WC_Custom_Installments\Compatibility
 * @author MeuMouse.com
 */
class Dependency_Api {

	/**
	 * Register the filter.
	 *
	 * `plugins_api` only ever runs in the admin, and the dependency pass itself
	 * is read on plugins.php and plugin-install.php — both well after `init`,
	 * where this class is built.
	 *
	 * Priority 30 puts this after the MDS SDK's own handler, which sits at 20.
	 *
	 * @since 6.1.0
	 * @return void
	 */
	public function __construct() {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'plugins_api', array( $this, 'answer_own_slug' ), 30, 3 );
	}


	/**
	 * Answer the directory request for this plugin's slug.
	 *
	 * @since 6.1.0
	 * @param false|object|array $result Result from an earlier filter, false when none answered.
	 * @param string $action The information being requested.
	 * @param object $args Request arguments, carrying the slug.
	 * @return false|object|array
	 */
	public function answer_own_slug( $result, $action, $args ) {
		// Let every other kind of request through.
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		$slug = is_object( $args ) && isset( $args->slug ) ? $args->slug : '';

		if ( WC_CUSTOM_INSTALLMENTS_SLUG !== $slug ) {
			return $result;
		}

		return $this->complete( $result, $this->plugin_information() );
	}


	/**
	 * Fill the gaps in an earlier answer with the header-derived one.
	 *
	 * Whatever the earlier filter had to say wins: it knows about a release
	 * this copy of the plugin does not. Only the keys it left empty are taken
	 * from the header, and `sections` is merged rather than chosen, so the
	 * description and the changelog both reach the modal - in that order,
	 * because the first section is the tab it opens on.
	 *
	 * @since 6.1.0
	 * @param false|object|array $answer Result from an earlier filter.
	 * @param object $base Everything this plugin can say about itself.
	 * @return object
	 */
	protected function complete( $answer, $base ) {
		if ( is_array( $answer ) ) {
			$answer = (object) $answer;
		}

		if ( ! is_object( $answer ) ) {
			return $base;
		}

		$sections = array_merge(
			(array) ( $base->sections ?? array() ),
			(array) ( $answer->sections ?? array() )
		);

		foreach ( get_object_vars( $answer ) as $key => $value ) {
			if ( 'sections' === $key || null === $value || '' === $value || array() === $value ) {
				continue;
			}

			$base->$key = $value;
		}

		$base->sections = $sections;

		return $base;
	}


	/**
	 * Build the response from the plugin's own header.
	 *
	 * Every field the details modal reads is declared, because an absent one
	 * surfaces as an undefined property notice on PHP 8. The counts stay at
	 * zero and the download link empty: there are no directory ratings to
	 * report, and this is what the plugin knows without asking anyone - the
	 * update channel's answer, when there is one, fills the link and the
	 * version in over it.
	 *
	 * @since 6.1.0
	 * @return object
	 */
	protected function plugin_information() {
		if ( ! function_exists('get_plugin_data') ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Translated, so an English store reads the bundled en_US header; and
		// without markup, which would append the author to the description.
		$plugin = get_plugin_data( WC_CUSTOM_INSTALLMENTS_FILE, false, true );

		// "Tested up to" is not among the headers get_plugin_data() returns.
		$headers = get_file_data( WC_CUSTOM_INSTALLMENTS_FILE, array( 'tested' => 'Tested up to' ), 'plugin' );

		$icon = WC_CUSTOM_INSTALLMENTS_ASSETS . 'brand/logo.svg';

		// The directory reports the author as a link, and the details modal
		// prints whatever it is given.
		$author = $plugin['Author'];

		if ( '' !== $plugin['AuthorURI'] && '' !== $author ) {
			$author = sprintf( '<a href="%s">%s</a>', esc_url( $plugin['AuthorURI'] ), $author );
		}

		return (object) array(
			'name'              => $plugin['Name'],
			'slug'              => WC_CUSTOM_INSTALLMENTS_SLUG,
			'version'           => $plugin['Version'],
			'author'            => $author,
			'requires'          => $plugin['RequiresWP'],
			'requires_php'      => $plugin['RequiresPHP'],
			'tested'            => $headers['tested'],
			'homepage'          => $plugin['PluginURI'],
			'short_description' => $plugin['Description'],
			'sections'          => array( 'description' => wpautop( $plugin['Description'] ) ),
			'icons'             => array(
				'svg'     => $icon,
				'default' => $icon,
			),
			'banners'           => array(),
			'contributors'      => array(),
			'donate_link'       => '',
			'download_link'     => '',
			'ratings'           => array(),
			'num_ratings'       => 0,
			'rating'            => 0,

			// Marks the plugin as hosted outside the directory, which keeps the
			// details modal from linking to a page that is not there yet.
			'external'          => true,
		);
	}
}
