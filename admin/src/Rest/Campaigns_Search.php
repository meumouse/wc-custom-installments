<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Find the products and terms a campaign can target.
 *
 * Feeds the editor's pickers as the reader types. `include` names specific
 * IDs instead of searching, for the chips of a campaign opened for editing.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Campaigns_Search extends Abstract_Campaign_Route {

	/**
	 * Route path.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $route = '/admin/campaigns/search';

	/**
	 * Allowed HTTP methods.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $methods = 'GET';

	/**
	 * How many results a search returns.
	 *
	 * @since 6.0.0
	 * @var int
	 */
	const LIMIT = 20;


	/**
	 * Handle the request.
	 *
	 * @since 6.0.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$type = sanitize_key( (string) $request->get_param('type') );
		$search = sanitize_text_field( (string) $request->get_param('search') );
		$include = array_filter( array_map( 'intval', explode( ',', (string) $request->get_param('include') ) ) );

		if ( 'product' === $type ) {
			$results = self::products( $search, $include );
		} elseif ( in_array( $type, array( 'product_cat', 'product_tag', 'product_brand' ), true ) && taxonomy_exists( $type ) ) {
			$results = self::terms( $type, $search, $include );
		} else {
			$results = array();
		}

		return $this->success_response( array( 'results' => $results ) );
	}


	/**
	 * Products by name or SKU, variations left out: choosing a product
	 * chooses its variations.
	 *
	 * @since 6.0.0
	 * @param string $search | What the reader typed
	 * @param array<int,int> $include | Specific IDs to name instead
	 * @return array<int,array{id:int,label:string}>
	 */
	private static function products( $search, $include ) {
		if ( ! empty( $include ) ) {
			$ids = $include;
		} elseif ( '' !== $search && class_exists('WC_Data_Store') ) {
			$ids = \WC_Data_Store::load('product')->search_products( $search, '', false, true, self::LIMIT );
		} else {
			$ids = get_posts( array(
				'post_type' => 'product',
				'post_status' => array( 'publish', 'private' ),
				'numberposts' => self::LIMIT,
				'orderby' => 'title',
				'order' => 'ASC',
				'fields' => 'ids',
			) );
		}

		$results = array();

		foreach ( array_slice( array_filter( array_map( 'intval', (array) $ids ) ), 0, max( self::LIMIT, count( $include ) ) ) as $id ) {
			$product = wc_get_product( $id );

			if ( ! $product || $product->is_type('variation') ) {
				continue;
			}

			$label = html_entity_decode( wp_strip_all_tags( $product->get_name() ), ENT_QUOTES, 'UTF-8' );
			$sku = (string) $product->get_sku();

			$results[] = array(
				'id' => $id,
				'label' => '' !== $sku ? sprintf( '%s (%s)', $label, $sku ) : $label,
			);
		}

		return $results;
	}


	/**
	 * Terms by name, each labelled with its parents ("Roupas › Camisetas").
	 *
	 * @since 6.0.0
	 * @param string $taxonomy | Taxonomy
	 * @param string $search | What the reader typed
	 * @param array<int,int> $include | Specific IDs to name instead
	 * @return array<int,array{id:int,label:string}>
	 */
	private static function terms( $taxonomy, $search, $include ) {
		$args = array(
			'taxonomy' => $taxonomy,
			'hide_empty' => false,
			'number' => empty( $include ) ? self::LIMIT : 0,
			'orderby' => 'name',
		);

		if ( ! empty( $include ) ) {
			$args['include'] = $include;
		} elseif ( '' !== $search ) {
			$args['search'] = $search;
		}

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$results = array();

		foreach ( $terms as $term ) {
			$names = array();

			foreach ( array_reverse( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, $taxonomy );

				if ( $ancestor && ! is_wp_error( $ancestor ) ) {
					$names[] = $ancestor->name;
				}
			}

			$names[] = $term->name;

			$results[] = array(
				'id' => (int) $term->term_id,
				'label' => html_entity_decode( implode( ' › ', $names ), ENT_QUOTES, 'UTF-8' ),
			);
		}

		return $results;
	}
}
