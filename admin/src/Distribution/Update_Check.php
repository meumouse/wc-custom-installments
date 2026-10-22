<?php

namespace MeuMouse\WC_Custom_Installments\Distribution;

use MeuMouse\MDS\SDK\Support\Cache;

defined('ABSPATH') || exit;

/**
 * "Verificar atualizações" on this plugin's row in the plugins list.
 *
 * WordPress only looks for updates twice a day. After a release, a store owner
 * who knows there is one should not have to wait for the schedule.
 *
 * The row meta (beside the version and author) rather than the action links: it
 * is about the plugin, not something done to it, and the filter serves the
 * network plugins list as well.
 *
 * The link points at the admin-post handler, which is what runs without
 * JavaScript. With it, update-check.js asks the REST route (Rest\Update_Check)
 * instead and answers in the row, so the page does not reload. Everything the
 * script shows travels on the link, translated here, so the script needs no
 * catalogue of its own.
 *
 * Part of the temporary distribution channel: it exists because this plugin
 * carries its own updates, and goes when that does. AGENTS.md section 6 has the
 * checklist. The add-on's own copy of this lives in its Admin\Menu, and the two
 * are deliberately independent - the free plugin must not grow a dependency on
 * code that may not be installed.
 *
 * @since 6.1.0
 * @package MeuMouse\WC_Custom_Installments\Distribution
 * @author MeuMouse.com
 */
class Update_Check {

	/**
	 * admin-post action behind the row link.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const ACTION = 'wc_custom_installments_check_updates';

	/**
	 * Query argument carrying the outcome back to the plugins list.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const RESULT = 'wcci-update-check';

	/**
	 * Script handle of the in-place check.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const HANDLE = 'wc-custom-installments-update-check';


	/**
	 * Register the hooks behind the link.
	 *
	 * @since 6.1.0
	 * @return void
	 */
	public function __construct() {
		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_post' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'network_admin_notices', array( $this, 'notice' ) );
		add_filter( 'removable_query_args', array( $this, 'removable_query_args' ) );
	}


	/**
	 * Add the link to this plugin's row.
	 *
	 * Priority 20, after Admin\Menu's documentation link, so the order reads
	 * "Documentação · Verificar atualizações".
	 *
	 * @since 6.1.0
	 * @param array<int|string,string> $meta | Existing row meta
	 * @param string $plugin_file | Plugin file of the row being rendered
	 * @return array<int|string,string>
	 */
	public function add_row_meta( $meta, $plugin_file ) {
		if ( WC_CUSTOM_INSTALLMENTS_BASENAME !== $plugin_file || ! current_user_can('update_plugins') ) {
			return $meta;
		}

		$url = wp_nonce_url(
			add_query_arg( 'action', self::ACTION, admin_url('admin-post.php') ),
			self::ACTION
		);

		$meta['check_updates'] = sprintf(
			'<a href="%s" data-wcci-update-check data-path="%s" data-checking="%s" data-failed="%s">%s</a>',
			esc_url( $url ),
			esc_attr( '/' . WC_CUSTOM_INSTALLMENTS_REST . \MeuMouse\WC_Custom_Installments\Rest\Update_Check::PATH ),
			esc_attr__( 'Verificando atualizações…', 'wc-custom-installments' ),
			esc_attr( self::describe_result('error')['message'] ),
			esc_html__( 'Verificar atualizações', 'wc-custom-installments' )
		);

		return $meta;
	}


	/**
	 * Enqueue the in-place check on the plugins list.
	 *
	 * No build step: the script is a few lines of plain JavaScript over core's
	 * api-fetch, which already carries the REST nonce.
	 *
	 * @since 6.1.0
	 * @param string $hook_suffix | Current admin page
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {
		if ( 'plugins.php' !== $hook_suffix || ! current_user_can('update_plugins') ) {
			return;
		}

		$script = WC_CUSTOM_INSTALLMENTS_DIR . 'assets/admin/update-check.js';

		if ( ! file_exists( $script ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			WC_CUSTOM_INSTALLMENTS_ASSETS . 'admin/update-check.js',
			array( 'wp-api-fetch', 'wp-a11y' ),
			(string) filemtime( $script ),
			true
		);
	}


	/**
	 * Ask for updates now, then return to the plugins list with the answer.
	 *
	 * The no-JavaScript path of the row link. The script asks the REST route
	 * instead, which runs the same check.
	 *
	 * @since 6.1.0
	 * @return void
	 */
	public function handle_post() {
		if ( ! current_user_can('update_plugins') ) {
			wp_die( esc_html__( 'Você não tem permissão para atualizar plugins.', 'wc-custom-installments' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION );

		$result = self::run();
		$back = wp_get_referer();

		if ( ! $back || false === strpos( $back, 'plugins.php' ) ) {
			$back = admin_url('plugins.php');
		}

		wp_safe_redirect( add_query_arg( self::RESULT, $result, $back ) );
		exit;
	}


	/**
	 * Ask the distribution service for updates now.
	 *
	 * Two caches stand between the click and a fresh answer: WordPress's own
	 * update transient, and the SDK's copy of the last update check, which it
	 * otherwise serves for hours. Clearing only the first would ask WordPress
	 * to rebuild the list from a stale answer.
	 *
	 * @since 6.1.0
	 * @return string One of available, current, unavailable or error
	 */
	public static function run() {
		// Nothing was asked and nothing can be: saying "you are up to date"
		// would be a claim nobody checked.
		if ( ! MDS_Integration::integration() ) {
			return 'unavailable';
		}

		self::clear_sdk_cache();

		delete_site_transient('update_plugins');

		if ( ! function_exists('wp_update_plugins') ) {
			require_once ABSPATH . 'wp-includes/update.php';
		}

		wp_update_plugins();

		$updates = get_site_transient('update_plugins');

		if ( ! is_object( $updates ) ) {
			return 'error';
		}

		if ( isset( $updates->response[ WC_CUSTOM_INSTALLMENTS_BASENAME ] ) ) {
			return 'available';
		}

		// The updater writes one or the other once it has an answer it trusts.
		// Neither means the check never completed - a refused signature, say.
		if ( ! isset( $updates->no_update[ WC_CUSTOM_INSTALLMENTS_BASENAME ] ) ) {
			return 'error';
		}

		return 'current';
	}


	/**
	 * What to tell the reader about the outcome of a check.
	 *
	 * Shared by the notice of the no-JavaScript path and the REST route, so
	 * both say the same thing. The message is plain text; whoever prints it
	 * escapes it. An update comes with core's own "update now" address, which
	 * the script turns into core's in-place update.
	 *
	 * @since 6.1.0
	 * @param string $result | Outcome from run()
	 * @return array{type:string,message:string,action:array{url:string,label:string,update:bool}|null}
	 */
	public static function describe_result( $result ) {
		switch ( $result ) {
			case 'available':
				$updates = get_site_transient('update_plugins');
				$version = isset( $updates->response[ WC_CUSTOM_INSTALLMENTS_BASENAME ]->new_version )
					? (string) $updates->response[ WC_CUSTOM_INSTALLMENTS_BASENAME ]->new_version
					: '';

				// Not wp_nonce_url(): it escapes the ampersands for HTML, and
				// the script sets this address as it is.
				$update_url = add_query_arg( array(
					'action' => 'upgrade-plugin',
					'plugin' => rawurlencode( WC_CUSTOM_INSTALLMENTS_BASENAME ),
					'_wpnonce' => wp_create_nonce( 'upgrade-plugin_' . WC_CUSTOM_INSTALLMENTS_BASENAME ),
				), network_admin_url('update.php') );

				return array(
					'type' => 'warning',
					'message' => __( 'Há uma nova versão do Parcelas Customizadas para WooCommerce disponível.', 'wc-custom-installments' ),
					'action' => array(
						'url' => $update_url,
						/* translators: %s: version number */
						'label' => '' !== $version ? sprintf( __( 'Atualizar para a versão %s', 'wc-custom-installments' ), $version ) : __( 'Atualizar agora', 'wc-custom-installments' ),
						'update' => true,
					),
				);

			case 'current':
				return array(
					'type' => 'info',
					'message' => __( 'O Parcelas Customizadas para WooCommerce já está na versão mais recente.', 'wc-custom-installments' ),
					'action' => null,
				);

			case 'unavailable':
				return array(
					'type' => 'warning',
					'message' => __( 'As atualizações automáticas não estão disponíveis neste site. Baixe a versão mais recente na sua conta.', 'wc-custom-installments' ),
					'action' => array(
						'url' => WC_CUSTOM_INSTALLMENTS_STORE_URL,
						'label' => __( 'Abrir a página do plugin', 'wc-custom-installments' ),
						'update' => false,
					),
				);

			default:
				return array(
					'type' => 'error',
					'message' => __( 'Não foi possível verificar as atualizações agora. Tente novamente em instantes.', 'wc-custom-installments' ),
					'action' => null,
				);
		}
	}


	/**
	 * Drop the SDK's cached update answer.
	 *
	 * The SDK keeps it in a transient it names after the product; its own
	 * "check now" does the same through the updater, which it does not expose.
	 *
	 * @since 6.1.0
	 * @return void
	 */
	private static function clear_sdk_cache() {
		if ( ! class_exists( Cache::class ) ) {
			return;
		}

		$integration = MDS_Integration::integration();

		if ( ! $integration ) {
			return;
		}

		$cache = new Cache( $integration->product() );
		$cache->delete('update');
	}


	/**
	 * Report the outcome of the check on the plugins list.
	 *
	 * @since 6.1.0
	 * @return void
	 */
	public function notice() {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of an outcome the nonce-checked handler produced.
		$result = isset( $_GET[ self::RESULT ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT ] ) ) : '';

		if ( 'plugins.php' !== $pagenow || '' === $result || ! current_user_can('update_plugins') ) {
			return;
		}

		$outcome = self::describe_result( $result );
		$message = esc_html( $outcome['message'] );

		if ( $outcome['action'] ) {
			$message .= sprintf( ' <a href="%s">%s</a>', esc_url( $outcome['action']['url'] ), esc_html( $outcome['action']['label'] ) );
		}

		wp_admin_notice( $message, array(
			'type' => $outcome['type'],
			'dismissible' => true,
		) );
	}


	/**
	 * Strip the outcome from the address bar once the notice has shown.
	 *
	 * Otherwise a reload repeats a notice about a check nobody ran again.
	 *
	 * @since 6.1.0
	 * @param array<int,string> $args | Query arguments WordPress removes
	 * @return array<int,string>
	 */
	public function removable_query_args( $args ) {
		$args[] = self::RESULT;

		return $args;
	}
}
