<?php
/**
 * Finds which custom fields actually exist on a post type, and which
 * plugin they most likely come from, so the crawler tab can let the
 * owner pick fields one by one instead of an all-or-nothing switch.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Kb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only helper for the settings screen. Never called from the crawl
 * itself — Indexer::custom_fields() reads post meta directly, the same
 * as it always has, and only consults the owner's field choices here.
 */
final class FieldDiscovery {

	/**
	 * Posts sampled per post type to find which meta keys are in use.
	 */
	const SAMPLE_POSTS = 25;

	/**
	 * Fields listed per post type, most recently used first.
	 */
	const MAX_FIELDS = 60;

	/**
	 * Custom fields in use on one post type, each with a human label and
	 * a best guess at the plugin it comes from.
	 *
	 * @param string $post_type Post type to inspect.
	 * @return array<int,array{key:string,label:string,source:string,sample:string}>
	 */
	public static function for_post_type( $post_type ) {
		global $wpdb;

		$post_type = (string) $post_type;

		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			return array();
		}

		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','draft','private') ORDER BY ID DESC LIMIT %d", // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
				$post_type,
				self::SAMPLE_POSTS
			)
		);

		if ( ! $post_ids ) {
			return array();
		}

		$acf_labels = self::acf_labels( $post_ids );

		// The underscore-prefix exclusion is done in PHP below rather than
		// with a `NOT LIKE '\_%'` clause: escaping `_` (a LIKE wildcard)
		// is sensitive to the server's SQL mode, and Indexer::custom_fields()
		// already filters the same way, so this stays exactly in step with
		// what actually gets indexed.
		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ({$placeholders}) ORDER BY meta_id DESC", // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- placeholders are %d per sampled id, bound below.
				$post_ids
			),
			ARRAY_A
		);

		$seen   = array();
		$fields = array();

		foreach ( (array) $rows as $row ) {
			$key = isset( $row['meta_key'] ) ? (string) $row['meta_key'] : '';

			if ( '' === $key || '_' === $key[0] || isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;

			$value = maybe_unserialize( isset( $row['meta_value'] ) ? $row['meta_value'] : '' );

			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$fields[] = array(
				'key'    => $key,
				'label'  => isset( $acf_labels[ $key ] ) ? $acf_labels[ $key ] : self::prettify( $key ),
				'source' => self::source_of( $key, $acf_labels ),
				'sample' => \Yuniq\Ai\Support\Text::truncate( trim( wp_strip_all_tags( (string) $value ) ), 60, '…' ),
			);

			if ( count( $fields ) >= self::MAX_FIELDS ) {
				break;
			}
		}

		return $fields;
	}

	/**
	 * ACF field labels keyed by meta key, gathered across the sampled
	 * posts (one post rarely carries every field a group defines).
	 *
	 * @param int[] $post_ids Sampled post ids.
	 * @return array<string,string>
	 */
	private static function acf_labels( array $post_ids ) {
		if ( ! function_exists( 'get_field_objects' ) ) {
			return array();
		}

		$labels = array();

		foreach ( $post_ids as $post_id ) {
			$objects = get_field_objects( $post_id, false, false );

			if ( ! is_array( $objects ) ) {
				continue;
			}

			foreach ( $objects as $name => $field ) {
				if ( is_array( $field ) && ! empty( $field['label'] ) && ! isset( $labels[ $name ] ) ) {
					$labels[ $name ] = (string) $field['label'];
				}
			}
		}

		return $labels;
	}

	/**
	 * Best guess at which plugin a meta key belongs to.
	 *
	 * Exact attribution is only possible for ACF, which exposes a real
	 * field registry. JetEngine (and most custom theme code) stores
	 * values as plain post meta with no registry this plugin can read
	 * independently of it, so those are labelled as a likely source
	 * rather than a confirmed one.
	 *
	 * @param string               $key        Meta key.
	 * @param array<string,string> $acf_labels Meta keys ACF confirmed.
	 * @return string
	 */
	private static function source_of( $key, array $acf_labels ) {
		if ( isset( $acf_labels[ $key ] ) ) {
			return 'ACF';
		}

		if ( class_exists( 'Jet_Engine' ) || defined( 'JET_ENGINE_VERSION' ) ) {
			return __( 'احتمالاً JetEngine', 'yuniq-ai' );
		}

		if ( class_exists( 'WooCommerce' ) && 0 === strpos( $key, '_' ) ) {
			// Dead code in practice — underscore-prefixed keys are already
			// filtered out by the SQL above — kept only so a future change
			// to that filter does not silently mislabel a WooCommerce
			// internal field as a generic one.
			return 'WooCommerce';
		}

		return __( 'فیلد سفارشی (متا)', 'yuniq-ai' );
	}

	/**
	 * Turn a raw meta key into something readable when no plugin supplied
	 * a real label.
	 *
	 * @param string $key Meta key.
	 * @return string
	 */
	private static function prettify( $key ) {
		$label = trim( str_replace( array( '_', '-' ), ' ', $key ) );
		$label = preg_replace( '/\s+/u', ' ', $label );

		return '' !== $label ? $label : $key;
	}
}
