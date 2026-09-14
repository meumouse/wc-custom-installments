<?php

namespace MeuMouse\WC_Custom_Installments\Campaigns;

defined('ABSPATH') || exit;

/**
 * The shape of a campaign, and the rules it has to follow.
 *
 * Pure functions over arrays: what the editor sends is cleaned and checked
 * here, and a stored campaign is compiled here into the entry the storefront
 * matches against (Resolver). Nothing in this class touches the database, so
 * the harness can exercise all of it.
 *
 * A campaign has a target (which products), a period, and up to two effects:
 * a discount that stands in for the store's upfront discount, the way a
 * product's own discount does, and an instalment plan that overrides the
 * store's for those products.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Campaigns
 * @author MeuMouse.com
 */
class Rules {

	/**
	 * Version of the stored shape.
	 *
	 * @since 6.0.0
	 * @var int
	 */
	const VERSION = 1;

	/**
	 * What a campaign can target, and the taxonomy each scope reads.
	 *
	 * @since 6.0.0
	 * @var array<string,string>
	 */
	const SCOPES = array(
		'all' => '',
		'products' => '',
		'categories' => 'product_cat',
		'tags' => 'product_tag',
		'brands' => 'product_brand',
	);

	/**
	 * Where a campaign's instalment interest comes from.
	 *
	 * @since 6.0.0
	 * @var array<int,string>
	 */
	const INTEREST_MODES = array( 'store', 'single', 'table' );


	/**
	 * A blank campaign, as the editor starts one.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'version' => self::VERSION,
			'priority' => 10,
			'starts_at' => '',
			'ends_at' => '',
			'target' => array(
				'scope' => 'categories',
				'ids' => array(),
				'exclude_products' => array(),
				'exclude_categories' => array(),
				'on_sale' => 'any',
			),
			'discount' => array(
				'enabled' => false,
				'method' => 'percentage',
				'amount' => '',
				'gateway' => '',
			),
			'installments' => array(
				'enabled' => false,
				'max' => '',
				'without_fee' => '',
				'min_value' => '',
				'interest' => 'store',
				'fee' => '',
				'fees' => array(),
			),
		);
	}


	/**
	 * Clean what the editor sent, and say what is wrong with it.
	 *
	 * Every value is coerced to its type; nothing is rejected for its shape. What
	 * cannot work - an end before the start, a campaign that changes nothing -
	 * comes back as a message for the reader, keyed by the part it concerns.
	 *
	 * @since 6.0.0
	 * @param mixed $input | Campaign rules, as sent
	 * @return array{rules:array<string,mixed>,errors:array<string,string>}
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$target = is_array( $input['target'] ?? null ) ? $input['target'] : array();
		$discount = is_array( $input['discount'] ?? null ) ? $input['discount'] : array();
		$installments = is_array( $input['installments'] ?? null ) ? $input['installments'] : array();

		$scope = (string) ( $target['scope'] ?? '' );
		$method = (string) ( $discount['method'] ?? '' );
		$interest = (string) ( $installments['interest'] ?? '' );

		$rules = array(
			'version' => self::VERSION,
			'priority' => (int) ( $input['priority'] ?? $defaults['priority'] ),
			'starts_at' => self::sanitize_local_datetime( $input['starts_at'] ?? '' ),
			'ends_at' => self::sanitize_local_datetime( $input['ends_at'] ?? '' ),
			'target' => array(
				'scope' => array_key_exists( $scope, self::SCOPES ) ? $scope : $defaults['target']['scope'],
				'ids' => self::sanitize_ids( $target['ids'] ?? array() ),
				'exclude_products' => self::sanitize_ids( $target['exclude_products'] ?? array() ),
				'exclude_categories' => self::sanitize_ids( $target['exclude_categories'] ?? array() ),
				'on_sale' => in_array( $target['on_sale'] ?? '', array( 'any', 'only', 'exclude' ), true ) ? $target['on_sale'] : 'any',
			),
			'discount' => array(
				'enabled' => self::is_on( $discount['enabled'] ?? false ),
				'method' => in_array( $method, array( 'percentage', 'fixed' ), true ) ? $method : 'percentage',
				'amount' => self::sanitize_decimal( $discount['amount'] ?? '' ),
				'gateway' => sanitize_key( (string) ( $discount['gateway'] ?? '' ) ),
			),
			'installments' => array(
				'enabled' => self::is_on( $installments['enabled'] ?? false ),
				'max' => self::sanitize_count( $installments['max'] ?? '' ),
				'without_fee' => self::sanitize_count( $installments['without_fee'] ?? '' ),
				'min_value' => self::sanitize_decimal( $installments['min_value'] ?? '' ),
				'interest' => in_array( $interest, self::INTEREST_MODES, true ) ? $interest : 'store',
				'fee' => self::sanitize_decimal( $installments['fee'] ?? '' ),
				'fees' => self::sanitize_fee_table( $installments['fees'] ?? array() ),
			),
		);

		// Only the scope's own list means anything; a list left over from
		// another scope would otherwise come back when the scope does.
		if ( 'all' === $rules['target']['scope'] ) {
			$rules['target']['ids'] = array();
		}

		return array(
			'rules' => $rules,
			'errors' => self::validate( $rules ),
		);
	}


	/**
	 * What would keep a campaign from working, keyed by the part it concerns.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $rules | Sanitized rules
	 * @return array<string,string>
	 */
	public static function validate( $rules ) {
		$errors = array();
		$starts = self::to_timestamp( $rules['starts_at'] );
		$ends = self::to_timestamp( $rules['ends_at'], true );

		if ( $starts && $ends && $ends < $starts ) {
			$errors['ends_at'] = __( 'A campanha termina antes de começar.', 'wc-custom-installments' );
		}

		if ( 'all' !== $rules['target']['scope'] && empty( $rules['target']['ids'] ) ) {
			$errors['target'] = __( 'Escolha ao menos um produto, categoria, tag ou marca.', 'wc-custom-installments' );
		}

		$discount = $rules['discount'];
		$installments = $rules['installments'];

		if ( ! $discount['enabled'] && ! $installments['enabled'] ) {
			$errors['effects'] = __( 'Ative o desconto, o parcelamento ou os dois.', 'wc-custom-installments' );
		}

		if ( $discount['enabled'] ) {
			if ( '' === $discount['amount'] || (float) $discount['amount'] <= 0 ) {
				$errors['discount'] = __( 'Informe o valor do desconto.', 'wc-custom-installments' );
			} elseif ( 'percentage' === $discount['method'] && (float) $discount['amount'] > 100 ) {
				$errors['discount'] = __( 'Um desconto percentual não pode passar de 100%.', 'wc-custom-installments' );
			}
		}

		if ( $installments['enabled'] ) {
			$changes_something = '' !== $installments['max']
				|| '' !== $installments['without_fee']
				|| '' !== $installments['min_value']
				|| 'store' !== $installments['interest'];

			if ( ! $changes_something ) {
				$errors['installments'] = __( 'Defina ao menos uma regra de parcelamento.', 'wc-custom-installments' );
			} elseif ( '' !== $installments['max'] && '' !== $installments['without_fee'] && (int) $installments['without_fee'] > (int) $installments['max'] ) {
				$errors['installments'] = __( 'As parcelas sem juros não podem passar do máximo de parcelas.', 'wc-custom-installments' );
			} elseif ( 'single' === $installments['interest'] && '' === $installments['fee'] ) {
				$errors['installments'] = __( 'Informe a taxa de juros mensal.', 'wc-custom-installments' );
			}
		}

		return $errors;
	}


	/**
	 * Compile a stored campaign into the entry the storefront matches against.
	 *
	 * Dates become timestamps, and each chosen category brings its
	 * subcategories along, so a campaign on "Roupas" reaches a product filed
	 * under "Roupas › Camisetas" without a query at render time.
	 *
	 * @since 6.0.0
	 * @param int $id | Campaign ID
	 * @param string $title | Campaign name
	 * @param array<string,mixed> $rules | Sanitized rules
	 * @return array<string,mixed>
	 */
	public static function compile( $id, $title, $rules ) {
		$target = $rules['target'];
		$taxonomy = self::SCOPES[ $target['scope'] ] ?? '';
		$ids = $target['ids'];

		if ( 'product_cat' === $taxonomy ) {
			$ids = self::with_descendants( $ids, 'product_cat' );
		}

		$discount = $rules['discount']['enabled']
			? array(
				'method' => $rules['discount']['method'],
				'amount' => $rules['discount']['amount'],
				'gateway' => $rules['discount']['gateway'],
			)
			: null;

		$installments = $rules['installments']['enabled']
			? array(
				'max' => $rules['installments']['max'],
				'without_fee' => $rules['installments']['without_fee'],
				'min_value' => $rules['installments']['min_value'],
				'interest' => $rules['installments']['interest'],
				'fee' => $rules['installments']['fee'],
				'fees' => $rules['installments']['fees'],
			)
			: null;

		return array(
			'id' => (int) $id,
			'title' => (string) $title,
			'priority' => (int) $rules['priority'],
			'starts' => self::to_timestamp( $rules['starts_at'] ),
			'ends' => self::to_timestamp( $rules['ends_at'], true ),
			'target' => array(
				'scope' => $target['scope'],
				'taxonomy' => $taxonomy,
				'ids' => $ids,
				'exclude_products' => $target['exclude_products'],
				'exclude_categories' => self::with_descendants( $target['exclude_categories'], 'product_cat' ),
				'on_sale' => $target['on_sale'],
			),
			'discount' => $discount,
			'installments' => $installments,
		);
	}


	/**
	 * The order campaigns are tried in: the first match wins, per effect.
	 *
	 * Higher priority first; among equals, the one that started last - the
	 * most recent decision - and then the newest.
	 *
	 * @since 6.0.0
	 * @param array<int,array<string,mixed>> $entries | Compiled campaigns
	 * @return array<int,array<string,mixed>>
	 */
	public static function sort( $entries ) {
		usort( $entries, static function( $a, $b ) {
			return array( $b['priority'], $b['starts'], $b['id'] ) <=> array( $a['priority'], $a['starts'], $a['id'] );
		} );

		return $entries;
	}


	/**
	 * Where a compiled campaign stands at a moment.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $entry | Compiled campaign
	 * @param int $now | Unix timestamp
	 * @return string One of `scheduled`, `running` or `ended`
	 */
	public static function phase( $entry, $now ) {
		if ( ! empty( $entry['starts'] ) && $now < $entry['starts'] ) {
			return 'scheduled';
		}

		if ( ! empty( $entry['ends'] ) && $now > $entry['ends'] ) {
			return 'ended';
		}

		return 'running';
	}


	/**
	 * Turn a date the reader typed, in the site's timezone, into a timestamp.
	 *
	 * An end is inclusive to the minute: a campaign ending at 23:59 runs until
	 * 23:59:59.
	 *
	 * @since 6.0.0
	 * @param string $local | `Y-m-d\TH:i`, or empty
	 * @param bool $end | Whether this is the end of a period
	 * @return int Unix timestamp; 0 when there is no date
	 */
	public static function to_timestamp( $local, $end = false ) {
		if ( '' === (string) $local ) {
			return 0;
		}

		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', (string) $local, wp_timezone() );

		if ( ! $date ) {
			return 0;
		}

		return $date->getTimestamp() + ( $end ? 59 : 0 );
	}


	/**
	 * Keep a `Y-m-d\TH:i` date, the format the browser's date and time field sends.
	 *
	 * @since 6.0.0
	 * @param mixed $value | Incoming value
	 * @return string The date, or empty
	 */
	private static function sanitize_local_datetime( $value ) {
		$value = substr( trim( (string) $value ), 0, 16 );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ) {
			return '';
		}

		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value );

		return $date && $date->format('Y-m-d\TH:i') === $value ? $value : '';
	}


	/**
	 * A list of positive, unique IDs.
	 *
	 * @since 6.0.0
	 * @param mixed $value | Incoming list
	 * @return array<int,int>
	 */
	private static function sanitize_ids( $value ) {
		$ids = array_filter( array_map( 'intval', (array) $value ), static function( $id ) {
			return $id > 0;
		} );

		return array_values( array_unique( $ids ) );
	}


	/**
	 * A decimal written with either separator, or empty.
	 *
	 * @since 6.0.0
	 * @param mixed $value | Incoming value
	 * @return string
	 */
	private static function sanitize_decimal( $value ) {
		$value = str_replace( ',', '.', trim( (string) $value ) );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}

		return (string) max( 0, (float) $value );
	}


	/**
	 * An instalment count between 1 and 48, or empty.
	 *
	 * @since 6.0.0
	 * @param mixed $value | Incoming value
	 * @return string
	 */
	private static function sanitize_count( $value ) {
		if ( '' === trim( (string) $value ) || ! is_numeric( $value ) ) {
			return '';
		}

		return (string) max( 0, min( 48, (int) $value ) );
	}


	/**
	 * A per-instalment interest table, in the store setting's shape.
	 *
	 * @since 6.0.0
	 * @param mixed $value | Incoming table
	 * @return array<int,array<string,string>>
	 */
	private static function sanitize_fee_table( $value ) {
		$clean = array();

		foreach ( (array) $value as $installment => $row ) {
			$installment = (int) $installment;
			$amount = self::sanitize_decimal( is_array( $row ) ? ( $row['amount'] ?? '' ) : $row );
			$fixed = self::sanitize_decimal( is_array( $row ) ? ( $row['fixed'] ?? '' ) : '' );

			if ( $installment < 1 || $installment > 48 || ( '' === $amount && '' === $fixed ) ) {
				continue;
			}

			$clean[ $installment ] = array( 'amount' => '' === $amount ? '0' : $amount );

			if ( '' !== $fixed && (float) $fixed > 0 ) {
				$clean[ $installment ]['fixed'] = $fixed;
			}
		}

		ksort( $clean );

		return $clean;
	}


	/**
	 * Whether a flag the browser sent is on.
	 *
	 * @since 6.0.0
	 * @param mixed $value | Incoming value
	 * @return bool
	 */
	private static function is_on( $value ) {
		return true === $value || 1 === $value || in_array( $value, array( '1', 'yes', 'true' ), true );
	}


	/**
	 * A list of terms with every subterm beneath them.
	 *
	 * @since 6.0.0
	 * @param array<int,int> $ids | Term IDs
	 * @param string $taxonomy | Taxonomy
	 * @return array<int,int>
	 */
	private static function with_descendants( $ids, $taxonomy ) {
		$all = $ids;

		foreach ( $ids as $id ) {
			$children = get_term_children( $id, $taxonomy );

			if ( is_array( $children ) ) {
				$all = array_merge( $all, array_map( 'intval', $children ) );
			}
		}

		return array_values( array_unique( $all ) );
	}
}
