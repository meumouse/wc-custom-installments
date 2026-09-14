<?php

namespace MeuMouse\WC_Custom_Installments\Compatibility;

use MeuMouse\WC_Custom_Installments\Core\Helpers;
use MeuMouse\WC_Custom_Installments\Core\Migrator;
use MeuMouse\WC_Custom_Installments\Frontend\Headless_Mode;

defined('ABSPATH') || exit;

/**
 * Stand down while the 5.x plugin is still running.
 *
 * Both plugins hook `woocommerce_get_price_html`, so with the two active a
 * shopper sees the price group twice — and, because each renders from its own
 * copy of the settings, sometimes with two different discounts. That is a
 * broken shop, and it is the most likely state during an upgrade: a store owner
 * installs the new plugin to look at it before switching.
 *
 * So the new plugin renders nothing until the old one is switched off. The
 * admin still works, the settings are still there, and the import has already
 * run — only the storefront waits.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Compatibility
 * @author MeuMouse.com
 */
class Conflict_Guard {

	/**
	 * Withhold the storefront classes, before anything is built.
	 *
	 * Called from the bootstrap rather than from this class's constructor,
	 * because the constructor runs *during* the instantiation pass — a filter
	 * registered there would arrive after the list it is meant to shorten had
	 * already been read.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public static function bootstrap() {
		if ( ! self::has_conflict() ) {
			return;
		}

		add_filter( 'WC_Custom_Installments/Init/Instance_Classes', array( __CLASS__, 'withhold_storefront' ), 99 );
	}


	/**
	 * Register the admin surface.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		if ( ! self::has_conflict() ) {
			return;
		}

		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'admin_post_wc_custom_installments_deactivate_legacy', array( $this, 'handle_deactivate' ) );
	}


	/**
	 * Whether the 5.x plugin is running alongside this one.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function has_conflict() {
		return Helpers::is_legacy_plugin_active();
	}


	/**
	 * Keep the storefront classes from being built.
	 *
	 * Withholding them is better than letting each one check for itself: the
	 * check lives in one place, and a class added later cannot forget it —
	 * it simply is not on the list and renders, which is the safe default for
	 * anything that is not a price. The list is the one headless mode uses, so
	 * the settings screen, the REST routes and the migrator carry on here too.
	 *
	 * @since 6.0.0
	 * @param array<int,string> $classes | Classes about to be instantiated
	 * @return array<int,string>
	 */
	public static function withhold_storefront( $classes ) {
		return Headless_Mode::withhold_storefront( $classes );
	}


	/**
	 * Explain the situation, and offer the one action that resolves it.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function render_notice() {
		if ( ! current_user_can('activate_plugins') ) {
			return;
		}

		$imported = Migrator::has_legacy_data()
			? esc_html__( 'Suas configurações já foram importadas, então nada se perde com a troca.', 'wc-custom-installments' )
			: '';

		$message = sprintf(
			'<p><strong>%s</strong> %s</p><p>%s</p><p><a class="button button-primary" href="%s">%s</a></p>',
			esc_html__( 'Duas versões do Parcelas Customizadas estão ativas.', 'wc-custom-installments' ),
			esc_html__( 'Apenas o antigo está exibindo preços na sua loja. Este aguarda até que o plugin antigo seja desativado, para que os clientes nunca vejam o preço duas vezes.', 'wc-custom-installments' ),
			$imported,
			esc_url( wp_nonce_url(
				admin_url('admin-post.php?action=wc_custom_installments_deactivate_legacy'),
				'wc_custom_installments_deactivate_legacy'
			) ),
			esc_html__( 'Desativar o plugin antigo', 'wc-custom-installments' )
		);

		wp_admin_notice( $message, array(
			'type' => 'warning',
			'paragraph_wrap' => false,
		) );
	}


	/**
	 * Switch the 5.x plugin off.
	 *
	 * Deactivating, never deleting. The old plugin's files and options are what
	 * a rollback needs, and a store owner who wants them gone can remove it
	 * from the plugins screen like anything else.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function handle_deactivate() {
		check_admin_referer('wc_custom_installments_deactivate_legacy');

		if ( ! current_user_can('activate_plugins') ) {
			wp_die( esc_html__( 'Você não tem permissão para fazer isso.', 'wc-custom-installments' ) );
		}

		$legacy = self::find_legacy_plugin();

		if ( $legacy ) {
			// Make sure the import has happened before the old plugin stops
			// being the one in charge. It is idempotent, so running it again
			// costs nothing and closes the window where a store could end up
			// with neither plugin rendering and no settings carried across.
			Migrator::migrate();

			deactivate_plugins( $legacy );
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url('plugins.php') );
		exit;
	}


	/**
	 * The 5.x plugin's file, as WordPress knows it.
	 *
	 * Found by looking rather than assumed: the folder can be renamed, and a
	 * hard-coded path would quietly fail on exactly the sites that need this.
	 *
	 * @since 6.0.0
	 * @return string Empty when it is not installed.
	 */
	public static function find_legacy_plugin() {
		if ( ! function_exists('get_plugins') ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( array_keys( get_plugins() ) as $file ) {
			if ( 'woo-custom-installments.php' === basename( $file ) ) {
				return $file;
			}
		}

		return '';
	}
}
