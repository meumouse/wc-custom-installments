<?php

/**
 * The extension helpers.
 *
 * Each one is sugar over a single filter. They exist because a filter name is
 * something you have to know to look up, and a function is something your
 * editor offers you — the discoverability is the whole point, not the saved
 * keystrokes. Everything here can be done with `add_filter` instead, and
 * DEVELOPERS.md documents both.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Core
 * @author MeuMouse.com
 */

use MeuMouse\WC_Custom_Installments\Core\Features;

defined('ABSPATH') || exit;

if ( ! function_exists('wcci_register_feature') ) {
	/**
	 * Register a gated capability.
	 *
	 * @since 1.0.0
	 * @param string $id | Feature identifier
	 * @param array<string,mixed> $args | label, description, tier
	 * @return void
	 */
	function wcci_register_feature( $id, $args = array() ) {
		Features::register( $id, $args );
	}
}

if ( ! function_exists('wcci_is_feature_available') ) {
	/**
	 * Whether a feature may run on this site.
	 *
	 * @since 1.0.0
	 * @param string $id | Feature identifier
	 * @return bool
	 */
	function wcci_is_feature_available( $id ) {
		return Features::is_available( $id );
	}
}

if ( ! function_exists('wcci_register_settings_section') ) {
	/**
	 * Add a tab to the settings screen.
	 *
	 * @since 1.0.0
	 * @param array<string,mixed> $section | id, title, description, cards
	 * @param int $priority | Filter priority
	 * @return void
	 */
	function wcci_register_settings_section( $section, $priority = 10 ) {
		if ( empty( $section['id'] ) ) {
			return;
		}

		add_filter( 'WC_Custom_Installments/Admin/Settings_Schema', static function( $schema ) use ( $section ) {
			// Registering the same id twice would give the screen two tabs
			// that look identical and disagree about what they hold.
			foreach ( (array) $schema as $existing ) {
				if ( ( $existing['id'] ?? '' ) === $section['id'] ) {
					return $schema;
				}
			}

			$schema[] = $section;

			return $schema;
		}, $priority );
	}
}

if ( ! function_exists('wcci_register_settings_card') ) {
	/**
	 * Add a card to an existing settings tab.
	 *
	 * @since 1.0.0
	 * @param string $section_id | The tab to add it to
	 * @param array<string,mixed> $card | id, title, description, fields
	 * @param int $priority | Filter priority
	 * @return void
	 */
	function wcci_register_settings_card( $section_id, $card, $priority = 10 ) {
		add_filter( 'WC_Custom_Installments/Admin/Settings_Schema', static function( $schema ) use ( $section_id, $card ) {
			foreach ( (array) $schema as $index => $section ) {
				if ( ( $section['id'] ?? '' ) !== $section_id ) {
					continue;
				}

				$schema[ $index ]['cards'][] = $card;

				break;
			}

			return $schema;
		}, $priority );
	}
}

if ( ! function_exists('wcci_register_settings_field') ) {
	/**
	 * Add a field to an existing card.
	 *
	 * @since 1.0.0
	 * @param string $card_id | The card to add it to
	 * @param array<string,mixed> $field | type, key, label, description, default
	 * @param int $priority | Filter priority
	 * @return void
	 */
	function wcci_register_settings_field( $card_id, $field, $priority = 10 ) {
		add_filter( 'WC_Custom_Installments/Admin/Settings_Schema', static function( $schema ) use ( $card_id, $field ) {
			foreach ( (array) $schema as $section_index => $section ) {
				foreach ( (array) ( $section['cards'] ?? array() ) as $card_index => $card ) {
					if ( ( $card['id'] ?? '' ) !== $card_id ) {
						continue;
					}

					$schema[ $section_index ]['cards'][ $card_index ]['fields'][] = $field;

					return $schema;
				}
			}

			return $schema;
		}, $priority );

		// A field with no default reads as an empty string on a fresh install,
		// wherever it is used, so registering one implies registering the other.
		if ( isset( $field['key'], $field['default'] ) ) {
			wcci_register_default_options( array( $field['key'] => $field['default'] ) );
		}
	}
}

if ( ! function_exists('wcci_register_default_options') ) {
	/**
	 * Declare the default value of one or more settings.
	 *
	 * @since 1.0.0
	 * @param array<string,mixed> $defaults | Setting name => default value
	 * @param int $priority | Filter priority
	 * @return void
	 */
	function wcci_register_default_options( $defaults, $priority = 10 ) {
		add_filter( 'WC_Custom_Installments/Admin/Default_Options', static function( $existing ) use ( $defaults ) {
			return array_merge( (array) $existing, (array) $defaults );
		}, $priority );
	}
}

if ( ! function_exists('wcci_register_product_setting') ) {
	/**
	 * Add a field to the product data panel.
	 *
	 * @since 1.0.0
	 * @param array<string,mixed> $field | key, type, label, description, feature
	 * @param int $priority | Filter priority
	 * @return void
	 */
	function wcci_register_product_setting( $field, $priority = 10 ) {
		if ( empty( $field['key'] ) ) {
			return;
		}

		add_filter( 'WC_Custom_Installments/Admin/Product_Fields', static function( $fields ) use ( $field ) {
			$fields[] = $field;

			return $fields;
		}, $priority );
	}
}

if ( ! function_exists('wcci_register_price_element') ) {
	/**
	 * Add an element to the storefront price group.
	 *
	 * @since 1.0.0
	 * @param string $key | Element key
	 * @param array<string,mixed> $element | id, selector, order, icon, styles
	 * @param int $priority | Filter priority
	 * @return void
	 */
	function wcci_register_price_element( $key, $element, $priority = 10 ) {
		add_filter( 'WC_Custom_Installments/Frontend/Elements', static function( $elements ) use ( $key, $element ) {
			$elements[ $key ] = $element;

			return $elements;
		}, $priority );
	}
}

if ( ! function_exists('wcci_register_elementor_widget') ) {
	/**
	 * Add a widget to the plugin's Elementor category.
	 *
	 * @since 1.0.0
	 * @param string $class | Fully qualified widget class name
	 * @param int $priority | Filter priority
	 * @return void
	 */
	function wcci_register_elementor_widget( $class, $priority = 10 ) {
		add_filter( 'WC_Custom_Installments/Elementor/Widgets', static function( $widgets ) use ( $class ) {
			$widgets[] = $class;

			return $widgets;
		}, $priority );
	}
}
