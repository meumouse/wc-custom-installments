<?php

namespace MeuMouse\WC_Custom_Installments\Integrations;

defined('ABSPATH') || exit;

/**
 * Every plugin this one knows how to work alongside, and what the store owner
 * may decide about each.
 *
 * An integration describes itself: its name, what the integration does, whether
 * the other plugin is installed, and the settings that change its behaviour.
 * The settings screen asks here rather than carrying a list of its own, so a
 * new integration is one file and the Pro add-on can append its own.
 *
 * A described field is a plain array - type, key, label, description, and the
 * feature gating it when one does. `Settings\Registry` turns it into a schema
 * field, which is where defaults, locks and tiers are resolved for every field
 * in the plugin alike.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations
 * @author MeuMouse.com
 */
class Catalog {

	/**
	 * The integration classes, in the order the screen lists them.
	 *
	 * Page builders and SEO first, because almost every store has one; the
	 * pricing plugins after, because what they decide is the price itself.
	 *
	 * @since 6.0.0
	 * @var array<int,string>
	 */
	const CLASSES = array(
		Elementor::class,
		Rank_Math::class,
		Tiered_Pricing_Table::class,
		Dynamic_Pricing_Discounts::class,
		Role_Based_Pricing::class,
	);


	/**
	 * Every integration, described.
	 *
	 * @since 6.0.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function all() {
		/**
		 * Filter the integration classes the catalogue describes.
		 *
		 * An add-on appends its own class here. The class needs a static
		 * `describe()` returning the shape this catalogue documents.
		 *
		 * @since 6.0.0
		 * @param array<int,string> $classes | Fully qualified class names
		 * @return array<int,string>
		 */
		$classes = (array) apply_filters( 'WC_Custom_Installments/Integrations/Catalog', self::CLASSES );
		$integrations = array();

		foreach ( $classes as $class ) {
			$described = self::describe( $class );

			if ( $described ) {
				$integrations[] = $described;
			}
		}

		return $integrations;
	}


	/**
	 * Every field every integration declares, flattened.
	 *
	 * What `Default_Options` reads, so a setting cannot be declared on an
	 * integration and then have no default.
	 *
	 * @since 6.0.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function fields() {
		$fields = array();

		foreach ( self::all() as $integration ) {
			foreach ( (array) ( $integration['fields'] ?? array() ) as $field ) {
				if ( ! empty( $field['key'] ) ) {
					$fields[] = $field;
				}
			}
		}

		return $fields;
	}


	/**
	 * Whether one integration's other plugin is installed.
	 *
	 * @since 6.0.0
	 * @param string $id | Integration ID
	 * @return bool
	 */
	public static function is_detected( $id ) {
		foreach ( self::all() as $integration ) {
			if ( $id === ( $integration['id'] ?? '' ) ) {
				return ! empty( $integration['active'] );
			}
		}

		return false;
	}


	/**
	 * Describe one integration class.
	 *
	 * A class with no `describe()` is skipped rather than fataled: the
	 * catalogue is extensible, so a malformed entry must not take the settings
	 * screen down with it.
	 *
	 * @since 6.0.0
	 * @param string $class | Fully qualified class name
	 * @return array<string,mixed>|null Null when the class cannot describe itself.
	 */
	private static function describe( $class ) {
		if ( ! is_string( $class ) || ! class_exists( $class ) || ! method_exists( $class, 'describe' ) ) {
			return null;
		}

		$described = (array) $class::describe();

		if ( empty( $described['id'] ) ) {
			return null;
		}

		return array_merge( array(
			'title' => '',
			'description' => '',
			'url' => '',
			'active' => false,
			'fields' => array(),
		), $described );
	}
}
