<?php

namespace MeuMouse\WC_Custom_Installments\Compatibility;

defined('ABSPATH') || exit;

/**
 * Fire every plugin hook under its current name and its 5.x name.
 *
 * The predecessor plugin (`woo-custom-installments`) published 54 hooks under
 * the `Woo_Custom_Installments/...` namespace. Store owners have snippets in
 * `functions.php` bound to them, and agencies have child themes built on them.
 * Renaming our own classes is not a reason for any of that to stop working, so
 * every call site goes through this class: the canonical name runs first, the
 * 5.x alias runs second on the same value, and a listener on either one sees
 * and can change the result.
 *
 * The `woo_custom_installments_*` names that predate 5.4 come along too, after
 * the 5.x name, because 5.x still answered to them.
 *
 * The aliases carry **no deprecation notice**. They are supported, not
 * tolerated — a warning would be us asking the store owner to pay for our
 * refactor.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Compatibility
 * @author MeuMouse.com
 */
class Hook_Aliases {

	/**
	 * Namespace prefix used by the current plugin.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const PREFIX = 'WC_Custom_Installments/';

	/**
	 * Namespace prefix used by the 5.x plugin.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const LEGACY_PREFIX = 'Woo_Custom_Installments/';

	/**
	 * Aliases that do not follow the plain prefix swap.
	 *
	 * A hook whose surrounding class moved to a different area keeps its old
	 * path here, because the name a third party wrote down is the contract, not
	 * the file it happened to live in.
	 *
	 * @since 6.0.0
	 * @var array<string,string>
	 */
	private static $overrides = array(
		'WC_Custom_Installments/Core/Before_Init' => 'Woo_Custom_Installments/Before_Init',
		'WC_Custom_Installments/Core/Init'        => 'Woo_Custom_Installments/Init',
		'WC_Custom_Installments/Admin/Default_Options' => 'Woo_Custom_Installments/Admin/Set_Default_Options',
	);

	/**
	 * The names older than 5.4, which 5.x itself kept honouring.
	 *
	 * Until 5.4 the hooks were named `woo_custom_installments_*`. 5.4 moved them
	 * to the `Woo_Custom_Installments/...` namespace and bridged the old names,
	 * so snippets written against 4.x and early 5.x are still in child themes
	 * today. They fire after the 5.x name, on the same value, and like it they
	 * carry no deprecation notice.
	 *
	 * Keyed by canonical name. Two old names can share one hook: 5.x fed both
	 * of its old storefront parameter filters into the same one.
	 *
	 * @since 6.0.0
	 * @var array<string,array<int,string>>
	 */
	private static $underscore_aliases = array(
		// Filters.
		'WC_Custom_Installments/Admin/Default_Options'          => array( 'woo_custom_installments_set_default_options' ),
		'WC_Custom_Installments/Assets/Frontend_Params'            => array( 'woo_custom_installments_front_params', 'woo_custom_installments_dynamic_table_params' ),
		'WC_Custom_Installments/Cart/Apply_Discount'               => array( 'woo_custom_installments_apply_discount' ),
		'WC_Custom_Installments/Cart/Apply_Interest'               => array( 'woo_custom_installments_apply_interest' ),
		'WC_Custom_Installments/Cart/Total_Title'                  => array( 'woo_custom_installments_cart_total_title' ),
		'WC_Custom_Installments/Elementor/Inject_Controllers'      => array( 'woo_custom_installments_inject_elementor_controllers' ),
		'WC_Custom_Installments/Elements/Card_Flags'               => array( 'woo_custom_installments_card_flags' ),
		'WC_Custom_Installments/Installments/All_Installments'     => array( 'woo_custom_installments_all_installments' ),
		'WC_Custom_Installments/Installments/Final_Price'          => array( 'woo_custom_installments_final_price' ),
		'WC_Custom_Installments/Installments/Get_Fee'              => array( 'woo_custom_installments_fee' ),
		'WC_Custom_Installments/Installments/With_Fees'            => array( 'woo_custom_installments_with_fees' ),
		'WC_Custom_Installments/Installments/Without_Fee'          => array( 'woo_custom_installments_no_fee' ),
		'WC_Custom_Installments/Payment_Methods/Set_Product'       => array( 'woo_custom_installments_full_installment_product' ),
		'WC_Custom_Installments/Price/Calculate_Total_Discount'    => array( 'woo_custom_installments_calculate_total_discount' ),
		'WC_Custom_Installments/Price/Discounted_Price'            => array( 'woo_custom_installments_discounted_price' ),
		'WC_Custom_Installments/Price/Economy_Pix_Price'           => array( 'woo_custom_installments_economy_pix_price' ),
		'WC_Custom_Installments/Price/Fee_Label'                   => array( 'woo_custom_installments_fee_label' ),
		'WC_Custom_Installments/Price/Group_Classes'               => array( 'woo_custom_installments_group_custom_classes' ),
		'WC_Custom_Installments/Price/Installment_Info'            => array( 'woo_custom_installments_installment_info' ),
		'WC_Custom_Installments/Price/Set_Values_Price'            => array( 'woo_custom_installments_set_values_price' ),
		'WC_Custom_Installments/Price/Strings_To_Replace'          => array( 'woo_custom_installments_strings_to_replace' ),
		'WC_Custom_Installments/Product/Is_Available'              => array( 'woo_custom_installments_is_available' ),
		'WC_Custom_Installments/Widgets/Align_Price_Group'         => array( 'woo_custom_installments_align_price_group_widgets' ),
		'WC_Custom_Installments/Widgets/Enable_Grid_Price'         => array( 'woo_custom_installments_enable_grid_price_widgets' ),
		'WC_Custom_Installments/Widgets/Hidden_Old_Price'          => array( 'woo_custom_installments_hidden_old_price_widget' ),

		// Actions.
		'WC_Custom_Installments/Elements/Accordion_Footer'              => array( 'woo_custom_installments_accordion_bottom' ),
		'WC_Custom_Installments/Elements/Accordion_Header'              => array( 'woo_custom_installments_accordion_header' ),
		'WC_Custom_Installments/Elements/After_Installments_Container'  => array( 'woo_custom_installments_after_installments_container' ),
		'WC_Custom_Installments/Elements/Before_Installments_Container' => array( 'woo_custom_installments_before_installments_container' ),
		'WC_Custom_Installments/Elements/Modal_Footer'                  => array( 'woo_custom_installments_popup_bottom' ),
		'WC_Custom_Installments/Elements/Modal_Header'                  => array( 'woo_custom_installments_popup_header' ),
	);

	/**
	 * Old names of hooks whose name is completed at the call site.
	 *
	 * Keyed by the canonical prefix; whatever follows it (`main_price`,
	 * `loop`) carries over to the old name unchanged, as it did in 5.x.
	 *
	 * @since 6.0.0
	 * @var array<string,string>
	 */
	private static $underscore_prefixes = array(
		'WC_Custom_Installments/Installments/Best_With_Fee_'    => 'woo_custom_installments_best_with_fee_',
		'WC_Custom_Installments/Installments/Best_Without_Fee_' => 'woo_custom_installments_best_no_fee_',
	);


	/**
	 * Resolve the 5.x name of a hook.
	 *
	 * @since 6.0.0
	 * @param string $hook | Canonical hook name
	 * @return string Empty when the hook has no 5.x counterpart.
	 */
	public static function legacy_name( $hook ) {
		if ( isset( self::$overrides[ $hook ] ) ) {
			return self::$overrides[ $hook ];
		}

		if ( 0 !== strpos( $hook, self::PREFIX ) ) {
			return '';
		}

		return self::LEGACY_PREFIX . substr( $hook, strlen( self::PREFIX ) );
	}


	/**
	 * Resolve the pre-5.4 names of a hook.
	 *
	 * @since 6.0.0
	 * @param string $hook | Canonical hook name
	 * @return array<int,string> Empty when the hook never had one.
	 */
	public static function underscore_names( $hook ) {
		if ( isset( self::$underscore_aliases[ $hook ] ) ) {
			return self::$underscore_aliases[ $hook ];
		}

		foreach ( self::$underscore_prefixes as $prefix => $old_prefix ) {
			if ( 0 === strpos( $hook, $prefix ) ) {
				return array( $old_prefix . substr( $hook, strlen( $prefix ) ) );
			}
		}

		return array();
	}


	/**
	 * Every earlier name of a hook, in the order they fire.
	 *
	 * The 5.x name first, then the names 5.x had itself inherited.
	 *
	 * @since 6.0.0
	 * @param string $hook | Canonical hook name
	 * @return array<int,string>
	 */
	public static function legacy_names( $hook ) {
		$names = array();
		$legacy = self::legacy_name( $hook );

		if ( '' !== $legacy ) {
			$names[] = $legacy;
		}

		return array_merge( $names, self::underscore_names( $hook ) );
	}


	/**
	 * Apply a filter under every name it has had.
	 *
	 * @since 6.0.0
	 * @param string $hook | Canonical hook name
	 * @param mixed $value | Value being filtered
	 * @param mixed ...$args | Additional arguments passed to the listeners
	 * @return mixed
	 */
	public static function apply_filters( $hook, $value, ...$args ) {
		$value = apply_filters( $hook, $value, ...$args );

		foreach ( self::legacy_names( $hook ) as $legacy ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Preserved earlier name; the canonical name ran above.
			$value = apply_filters( $legacy, $value, ...$args );
		}

		return $value;
	}


	/**
	 * Fire an action under every name it has had.
	 *
	 * @since 6.0.0
	 * @param string $hook | Canonical hook name
	 * @param mixed ...$args | Arguments passed to the listeners
	 * @return void
	 */
	public static function do_action( $hook, ...$args ) {
		do_action( $hook, ...$args );

		foreach ( self::legacy_names( $hook ) as $legacy ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Preserved earlier name; the canonical name ran above.
			do_action( $legacy, ...$args );
		}
	}


	/**
	 * Whether anything is listening on any name of a hook.
	 *
	 * Useful where building the filtered value is itself expensive.
	 *
	 * @since 6.0.0
	 * @param string $hook | Canonical hook name
	 * @return bool
	 */
	public static function has_listeners( $hook ) {
		if ( has_filter( $hook ) ) {
			return true;
		}

		foreach ( self::legacy_names( $hook ) as $legacy ) {
			if ( has_filter( $legacy ) ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Register an additional alias at runtime.
	 *
	 * An add-on that publishes a hook of its own can map it here so the same
	 * guarantee covers it.
	 *
	 * @since 6.0.0
	 * @param string $hook | Canonical hook name
	 * @param string $legacy | Name to keep firing alongside it
	 * @return void
	 */
	public static function register_alias( $hook, $legacy ) {
		if ( '' === (string) $hook || '' === (string) $legacy ) {
			return;
		}

		self::$overrides[ $hook ] = $legacy;
	}


	/**
	 * Get the full alias map, for documentation generation.
	 *
	 * @since 6.0.0
	 * @return array<string,string>
	 */
	public static function get_overrides() {
		return self::$overrides;
	}


	/**
	 * Get the pre-5.4 names, for documentation generation.
	 *
	 * @since 6.0.0
	 * @return array{aliases: array<string,array<int,string>>, prefixes: array<string,string>}
	 */
	public static function get_underscore_aliases() {
		return array(
			'aliases' => self::$underscore_aliases,
			'prefixes' => self::$underscore_prefixes,
		);
	}
}
