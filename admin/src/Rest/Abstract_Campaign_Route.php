<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Core\Features;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * What the campaign endpoints share: the feature gate.
 *
 * Campaigns are a Pro capability. Without it the screen still lists what is
 * stored - a lapsed licence must not hide a store's own data - and campaigns
 * can be switched off, trashed or deleted, but nothing can be created, changed
 * or switched on. The gate lives in Core\Features, like every other one.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
abstract class Abstract_Campaign_Route extends Abstract_Route {

	/**
	 * Whether campaigns are locked on this site.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	protected function is_locked() {
		return ! Features::is_available('campaigns');
	}


	/**
	 * The answer to a write the gate refuses.
	 *
	 * @since 6.0.0
	 * @return WP_Error
	 */
	protected function locked_error() {
		return new WP_Error(
			'wc_custom_installments_campaigns_locked',
			__( 'As campanhas fazem parte do Parcelas Customizadas Pro. Ative sua licença para criar, editar e ativar campanhas.', 'wc-custom-installments' ),
			array( 'status' => 403 )
		);
	}
}
