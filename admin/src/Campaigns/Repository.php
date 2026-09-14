<?php

namespace MeuMouse\WC_Custom_Installments\Campaigns;

defined('ABSPATH') || exit;

/**
 * Read and write campaigns, and keep the storefront's index in step.
 *
 * The storefront never queries campaigns: it reads one autoloaded option, the
 * index, holding every active campaign that has not ended, already compiled
 * (Rules::compile()) and sorted. Every write here rebuilds it, so a price is
 * never decided from a stale copy.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Campaigns
 * @author MeuMouse.com
 */
class Repository {

	/**
	 * Option holding the compiled index. It cannot change name (AGENTS.md §5).
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const INDEX_OPTION = 'wc_custom_installments_campaigns_index';

	/**
	 * The statuses the campaigns screen lists.
	 *
	 * @since 6.0.0
	 * @var array<int,string>
	 */
	const STATUSES = array( 'publish', 'draft', 'trash' );


	/**
	 * Every campaign, trashed ones included, newest change first.
	 *
	 * @since 6.0.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function all() {
		$posts = get_posts( array(
			'post_type' => Post_Type::NAME,
			'post_status' => self::STATUSES,
			'numberposts' => -1,
			'orderby' => 'modified',
			'order' => 'DESC',
			'suppress_filters' => true,
		) );

		return array_map( array( __CLASS__, 'payload' ), $posts );
	}


	/**
	 * One campaign, as the screen reads it.
	 *
	 * @since 6.0.0
	 * @param int $id | Campaign ID
	 * @return array<string,mixed>|null
	 */
	public static function get( $id ) {
		$post = get_post( (int) $id );

		if ( ! $post || Post_Type::NAME !== $post->post_type ) {
			return null;
		}

		return self::payload( $post );
	}


	/**
	 * The stored rules of a campaign, filled out with the defaults.
	 *
	 * @since 6.0.0
	 * @param int $id | Campaign ID
	 * @return array<string,mixed>
	 */
	public static function rules( $id ) {
		$stored = get_post_meta( (int) $id, Post_Type::META_RULES, true );

		return Rules::sanitize( is_array( $stored ) ? $stored : array() )['rules'];
	}


	/**
	 * Create or update a campaign.
	 *
	 * Nothing is written when the rules have errors: a campaign that cannot
	 * work would otherwise sit among the active ones doing nothing, or worse.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $data | `id` (0 to create), `title`, `status` and `rules`
	 * @return array{id?:int,errors?:array<string,string>}
	 */
	public static function save( $data ) {
		$id = (int) ( $data['id'] ?? 0 );
		$title = sanitize_text_field( (string) ( $data['title'] ?? '' ) );
		$result = Rules::sanitize( $data['rules'] ?? array() );
		$errors = $result['errors'];

		if ( '' === $title ) {
			$errors = array( 'title' => __( 'Dê um nome à campanha.', 'wc-custom-installments' ) ) + $errors;
		}

		if ( $id && null === self::get( $id ) ) {
			return array( 'errors' => array( 'id' => __( 'Esta campanha não existe mais.', 'wc-custom-installments' ) ) );
		}

		if ( ! empty( $errors ) ) {
			return array( 'errors' => $errors );
		}

		$status = in_array( $data['status'] ?? '', array( 'publish', 'draft' ), true ) ? $data['status'] : null;

		$post = array(
			'post_type' => Post_Type::NAME,
			'post_title' => $title,
		);

		if ( $id ) {
			$post['ID'] = $id;

			if ( $status ) {
				$post['post_status'] = $status;
			}

			$saved = wp_update_post( wp_slash( $post ), true );
		} else {
			$post['post_status'] = $status ?: 'draft';
			$saved = wp_insert_post( wp_slash( $post ), true );
		}

		if ( is_wp_error( $saved ) || ! $saved ) {
			return array( 'errors' => array( 'id' => __( 'Não foi possível salvar a campanha. Tente novamente.', 'wc-custom-installments' ) ) );
		}

		update_post_meta( (int) $saved, Post_Type::META_RULES, wp_slash( $result['rules'] ) );

		self::changed( array( (int) $saved ) );

		return array( 'id' => (int) $saved );
	}


	/**
	 * Apply a status action to campaigns.
	 *
	 * `activate` publishes, `deactivate` returns to draft, `trash` and
	 * `restore` move in and out of the trash (a restored campaign comes back
	 * inactive, so it never goes live unannounced), and `delete` removes it for
	 * good.
	 *
	 * @since 6.0.0
	 * @param array<int,int> $ids | Campaign IDs
	 * @param string $action | One of activate, deactivate, trash, restore, delete
	 * @return int How many campaigns changed
	 */
	public static function apply_action( $ids, $action ) {
		$changed = array();

		foreach ( array_unique( array_map( 'intval', (array) $ids ) ) as $id ) {
			if ( null === self::get( $id ) ) {
				continue;
			}

			switch ( $action ) {
				case 'activate':
					$ok = ! is_wp_error( wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true ) );
					break;

				case 'deactivate':
					$ok = ! is_wp_error( wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ), true ) );
					break;

				case 'trash':
					$ok = (bool) wp_trash_post( $id );
					break;

				case 'restore':
					$ok = (bool) wp_untrash_post( $id ) && ! is_wp_error( wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ), true ) );
					break;

				case 'delete':
					$ok = (bool) wp_delete_post( $id, true );
					break;

				default:
					$ok = false;
			}

			if ( $ok ) {
				$changed[] = $id;
			}
		}

		if ( ! empty( $changed ) ) {
			self::changed( $changed );
		}

		return count( $changed );
	}


	/**
	 * Copy a campaign, as an inactive one.
	 *
	 * @since 6.0.0
	 * @param int $id | Campaign ID
	 * @return int The copy's ID, or 0
	 */
	public static function duplicate( $id ) {
		$source = self::get( $id );

		if ( null === $source ) {
			return 0;
		}

		$result = self::save( array(
			'id' => 0,
			/* translators: %s: campaign name */
			'title' => sprintf( __( '%s (cópia)', 'wc-custom-installments' ), $source['title'] ),
			'status' => 'draft',
			'rules' => $source['rules'],
		) );

		return (int) ( $result['id'] ?? 0 );
	}


	/**
	 * The compiled index the storefront matches against.
	 *
	 * @since 6.0.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function index() {
		$index = get_option( self::INDEX_OPTION, array() );

		return is_array( $index ) ? $index : array();
	}


	/**
	 * Recompile the index from the active campaigns.
	 *
	 * Ended campaigns are left out: they can never match again unless edited,
	 * and editing rebuilds this.
	 *
	 * @since 6.0.0
	 * @return array<int,array<string,mixed>> The new index
	 */
	public static function rebuild_index() {
		$posts = get_posts( array(
			'post_type' => Post_Type::NAME,
			'post_status' => 'publish',
			'numberposts' => -1,
			'suppress_filters' => true,
		) );

		$now = Resolver::now();
		$entries = array();

		foreach ( $posts as $post ) {
			$entry = Rules::compile( $post->ID, $post->post_title, self::rules( $post->ID ) );

			if ( 'ended' !== Rules::phase( $entry, $now ) ) {
				$entries[] = $entry;
			}
		}

		$entries = Rules::sort( $entries );

		update_option( self::INDEX_OPTION, $entries, true );
		Resolver::flush();

		/**
		 * Fires when the campaign index has been rebuilt.
		 *
		 * @since 6.0.0
		 * @param array<int,array<string,mixed>> $entries | Compiled active campaigns
		 */
		do_action( 'WC_Custom_Installments/Campaigns/Index_Rebuilt', $entries );

		return $entries;
	}


	/**
	 * Rebuild the index and announce which campaigns changed.
	 *
	 * @since 6.0.0
	 * @param array<int,int> $ids | Campaign IDs
	 * @return void
	 */
	public static function changed( $ids ) {
		self::rebuild_index();

		/**
		 * Fires when campaigns change, or start or end.
		 *
		 * Prices on the products they target may have moved: a page cache or a
		 * shopping feed holding them can refresh here.
		 *
		 * @since 6.0.0
		 * @param array<int,int> $ids | Campaign IDs
		 */
		do_action( 'WC_Custom_Installments/Campaigns/Changed', array_values( array_map( 'intval', $ids ) ) );
	}


	/**
	 * A campaign as the screen reads it.
	 *
	 * `state` is where it stands now: running, scheduled or ended when active,
	 * otherwise inactive or trash. `labels` names the products and terms it
	 * lists, so the editor can show them before searching for anything.
	 *
	 * @since 6.0.0
	 * @param \WP_Post $post | Campaign post
	 * @return array<string,mixed>
	 */
	public static function payload( $post ) {
		$rules = self::rules( $post->ID );

		if ( 'publish' === $post->post_status ) {
			$state = Rules::phase( Rules::compile( $post->ID, $post->post_title, $rules ), Resolver::now() );
		} else {
			$state = 'trash' === $post->post_status ? 'trash' : 'inactive';
		}

		return array(
			'id' => (int) $post->ID,
			'title' => (string) $post->post_title,
			'status' => (string) $post->post_status,
			'state' => $state,
			'modified' => (string) get_post_modified_time( 'Y-m-d\TH:i', false, $post ),
			'rules' => $rules,
			'labels' => self::labels( $rules ),
		);
	}


	/**
	 * The names of the products and terms a campaign lists.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $rules | Campaign rules
	 * @return array{products:array<int,string>,terms:array<int,string>}
	 */
	private static function labels( $rules ) {
		$target = $rules['target'];
		$product_ids = $target['exclude_products'];
		$term_ids = $target['exclude_categories'];

		if ( 'products' === $target['scope'] ) {
			$product_ids = array_merge( $product_ids, $target['ids'] );
		} elseif ( 'all' !== $target['scope'] ) {
			$term_ids = array_merge( $term_ids, $target['ids'] );
		}

		$labels = array(
			'products' => array(),
			'terms' => array(),
		);

		foreach ( array_unique( $product_ids ) as $id ) {
			$labels['products'][ $id ] = html_entity_decode( (string) get_the_title( $id ), ENT_QUOTES, 'UTF-8' );
		}

		foreach ( array_unique( $term_ids ) as $id ) {
			$term = get_term( $id );
			$labels['terms'][ $id ] = ( $term && ! is_wp_error( $term ) ) ? html_entity_decode( (string) $term->name, ENT_QUOTES, 'UTF-8' ) : '';
		}

		return $labels;
	}
}
