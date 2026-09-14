<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Campaigns\Post_Type;
use MeuMouse\WC_Custom_Installments\Campaigns\Repository;
use MeuMouse\WC_Custom_Installments\Campaigns\Rules;
use WP_Query;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * What a campaign being edited would reach, before it is saved.
 *
 * The editor's summary asks this as the reader changes the target: how many
 * published products fall under it, and which active campaigns overlap it in
 * time, effect and target - where only one of them can apply.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Campaigns_Preview extends Abstract_Campaign_Route {

	/**
	 * Route path.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $route = '/admin/campaigns/preview';

	/**
	 * Allowed HTTP methods.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $methods = 'POST';


	/**
	 * Handle the request.
	 *
	 * @since 6.0.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$campaign = ( isset( $payload['campaign'] ) && is_array( $payload['campaign'] ) ) ? $payload['campaign'] : array();
		$id = (int) ( $campaign['id'] ?? 0 );
		$rules = Rules::sanitize( $campaign['rules'] ?? array() )['rules'];

		return $this->success_response( array(
			'products' => self::count_products( $rules['target'] ),
			'conflicts' => self::conflicts( $id, $rules ),
		) );
	}


	/**
	 * How many published products a target reaches.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $target | Sanitized target
	 * @return int
	 */
	private static function count_products( $target ) {
		$taxonomy = Rules::SCOPES[ $target['scope'] ] ?? '';

		if ( 'all' !== $target['scope'] && empty( $target['ids'] ) ) {
			return 0;
		}

		$args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'fields' => 'ids',
			'posts_per_page' => 1,
			'no_found_rows' => false,
			'tax_query' => array(),
			'post__in' => array(),
			'post__not_in' => $target['exclude_products'],
		);

		if ( 'products' === $target['scope'] ) {
			$args['post__in'] = $target['ids'];
		} elseif ( '' !== $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				return 0;
			}

			$args['tax_query'][] = array(
				'taxonomy' => $taxonomy,
				'field' => 'term_id',
				'terms' => $target['ids'],
				'include_children' => 'product_cat' === $taxonomy,
			);
		}

		if ( ! empty( $target['exclude_categories'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field' => 'term_id',
				'terms' => $target['exclude_categories'],
				'operator' => 'NOT IN',
				'include_children' => true,
			);
		}

		if ( 'any' !== $target['on_sale'] && function_exists('wc_get_product_ids_on_sale') ) {
			$on_sale = array_map( 'intval', wc_get_product_ids_on_sale() );

			if ( 'only' === $target['on_sale'] ) {
				$args['post__in'] = empty( $args['post__in'] ) ? $on_sale : array_intersect( $args['post__in'], $on_sale );

				if ( empty( $args['post__in'] ) ) {
					return 0;
				}
			} else {
				$args['post__not_in'] = array_merge( $args['post__not_in'], $on_sale );
			}
		}

		if ( count( $args['tax_query'] ) > 1 ) {
			$args['tax_query']['relation'] = 'AND';
		}

		return (int) ( new WP_Query( $args ) )->found_posts;
	}


	/**
	 * The active campaigns that overlap this one, where only one can apply.
	 *
	 * Two campaigns overlap when their periods meet, they change the same thing
	 * (the discount or the plan), and their targets can share a product: one of
	 * them reaches every product, or both pick from the same list and share an
	 * entry. Targets of different kinds are not compared - that would take a
	 * query per product.
	 *
	 * @since 6.0.0
	 * @param int $id | The campaign being edited, 0 when new
	 * @param array<string,mixed> $rules | Its sanitized rules
	 * @return array<int,array{id:int,title:string,priority:int}>
	 */
	private static function conflicts( $id, $rules ) {
		$mine = Rules::compile( $id, '', $rules );
		$conflicts = array();

		$posts = get_posts( array(
			'post_type' => Post_Type::NAME,
			'post_status' => 'publish',
			'numberposts' => -1,
			'exclude' => $id ? array( $id ) : array(),
			'suppress_filters' => true,
		) );

		foreach ( $posts as $post ) {
			$other = Rules::compile( $post->ID, $post->post_title, Repository::rules( $post->ID ) );

			$same_effect = ( $mine['discount'] && $other['discount'] ) || ( $mine['installments'] && $other['installments'] );

			if ( ! $same_effect || ! self::periods_meet( $mine, $other ) || ! self::targets_meet( $mine['target'], $other['target'] ) ) {
				continue;
			}

			$conflicts[] = array(
				'id' => (int) $post->ID,
				'title' => (string) $post->post_title,
				'priority' => (int) $other['priority'],
			);
		}

		return $conflicts;
	}


	/**
	 * Whether two campaigns' periods share a moment. No date is an open end.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $a | Compiled campaign
	 * @param array<string,mixed> $b | Compiled campaign
	 * @return bool
	 */
	private static function periods_meet( $a, $b ) {
		$a_ends = $a['ends'] ?: PHP_INT_MAX;
		$b_ends = $b['ends'] ?: PHP_INT_MAX;

		return $a['starts'] <= $b_ends && $b['starts'] <= $a_ends;
	}


	/**
	 * Whether two targets can share a product.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $a | Compiled target
	 * @param array<string,mixed> $b | Compiled target
	 * @return bool
	 */
	private static function targets_meet( $a, $b ) {
		if ( 'all' === $a['scope'] || 'all' === $b['scope'] ) {
			return true;
		}

		return $a['scope'] === $b['scope'] && (bool) array_intersect( $a['ids'], $b['ids'] );
	}
}
