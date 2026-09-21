<?php
/**
 * Knowledge base storage and retrieval.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Kb;

use Yuniq\Ai\Setup\Schema;
use Yuniq\Ai\Support\Text;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes indexed website content.
 */
final class Repository {

	/**
	 * Option holding a counter that invalidates cached search results.
	 */
	const CACHE_VERSION_OPTION = 'yuniq_ai_kb_cache_version';

	/**
	 * How long a built context block stays cached, in seconds.
	 */
	const CACHE_TTL = 3600;

	/**
	 * Characters of content kept per document in the model context.
	 */
	const CONTEXT_CHARS = 1100;

	/**
	 * Fully prefixed table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->table = Schema::table( Schema::KNOWLEDGE );
	}

	/**
	 * Insert a document, or update the one already stored for this post.
	 *
	 * @param array $data Document fields.
	 * @return int|false Row id, or false on failure.
	 */
	public function upsert( array $data ) {
		global $wpdb;

		$defaults = array(
			'post_id'        => null,
			'post_type'      => '',
			'title'          => '',
			'content'        => '',
			'excerpt'        => '',
			'url'            => '',
			'slug'           => '',
			'categories'     => '',
			'tags'           => '',
			'metadata'       => '',
			'embedding_hash' => '',
		);

		$data = wp_parse_args( $data, $defaults );

		$data['title']      = sanitize_text_field( $data['title'] );
		$data['content']    = wp_kses_post( $data['content'] );
		$data['excerpt']    = sanitize_textarea_field( $data['excerpt'] );
		$data['url']        = esc_url_raw( $data['url'] );
		$data['slug']       = sanitize_title( $data['slug'] );
		$data['post_type']  = sanitize_key( $data['post_type'] );
		$data['categories'] = $this->flatten_terms( $data['categories'] );
		$data['tags']       = $this->flatten_terms( $data['tags'] );
		$data['metadata']   = is_array( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : (string) $data['metadata'];

		// The normalized copy is what every search actually matches against.
		$data['search_text'] = Text::normalize(
			$data['title'] . ' ' . $data['categories'] . ' ' . $data['tags'] . ' ' . $data['excerpt'] . ' ' . $data['content']
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( ! empty( $data['post_id'] ) ) {
			$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( "SELECT id FROM {$this->table} WHERE post_id = %d", $data['post_id'] )
			);

			if ( $existing ) {
				$wpdb->update( $this->table, $data, array( 'id' => $existing ), $formats, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

				return (int) $existing;
			}
		}

		$wpdb->insert( $this->table, $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Find documents relevant to a query.
	 *
	 * FULLTEXT runs first because it is index-backed. The fallback is a
	 * single OR'd LIKE over a capped set of tokens — deliberately one
	 * query, since the per-token loop it replaces could trigger a full
	 * table scan for every word in the question.
	 *
	 * @param string $query Search terms.
	 * @param int    $limit Maximum rows.
	 * @return array
	 */
	public function search( $query, $limit = 5 ) {
		global $wpdb;

		$normalized = Text::normalize( $query );

		if ( '' === $normalized ) {
			return array();
		}

		$columns = 'id, post_id, post_type, title, content, excerpt, url, slug, categories, tags, metadata';

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT {$columns},
					MATCH(search_text) AGAINST(%s IN NATURAL LANGUAGE MODE) AS relevance
				FROM {$this->table}
				WHERE MATCH(search_text) AGAINST(%s IN NATURAL LANGUAGE MODE)
				ORDER BY relevance DESC
				LIMIT %d",
				$normalized,
				$normalized,
				$limit
			),
			ARRAY_A
		);

		if ( ! empty( $results ) ) {
			return $results;
		}

		$tokens = Text::tokens( $query, 4 );

		if ( empty( $tokens ) ) {
			// Nothing but stop words: match the phrase as typed.
			$tokens = array( $normalized );
		}

		$clauses = array();
		$values  = array();

		foreach ( $tokens as $token ) {
			$clauses[] = 'search_text LIKE %s';
			$values[]  = '%' . $wpdb->esc_like( $token ) . '%';
		}

		$values[] = $limit;
		$where    = implode( ' OR ', $clauses );

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT {$columns}, 0 AS relevance
				FROM {$this->table}
				WHERE {$where}
				ORDER BY id DESC
				LIMIT %d",
				$values
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Build the context block handed to the model for a question.
	 *
	 * Results are cached per normalized question, so a repeated or common
	 * question skips the database entirely.
	 *
	 * @param string $query User question.
	 * @param int    $limit Maximum documents.
	 * @return string Empty when nothing matched.
	 */
	public function get_context_for_query( $query, $limit = 5 ) {
		$normalized = Text::normalize( $query );

		if ( '' === $normalized ) {
			return '';
		}

		$cache_key = 'yuniq_ai_ctx_' . md5( $this->cache_version() . '|' . $limit . '|' . $normalized );
		$cached    = get_transient( $cache_key );

		if ( is_string( $cached ) ) {
			return $cached;
		}

		$context = $this->build_context( $this->search( $query, $limit ) );

		set_transient( $cache_key, $context, self::CACHE_TTL );

		return $context;
	}

	/**
	 * Render search results into the block the model reads.
	 *
	 * @param array $results Rows from {@see self::search()}.
	 * @return string
	 */
	private function build_context( array $results ) {
		if ( empty( $results ) ) {
			return '';
		}

		$parts = array();
		$index = 1;

		foreach ( $results as $item ) {
			$part  = "[Document {$index}]\n";
			$part .= 'Title: ' . ( isset( $item['title'] ) ? $item['title'] : '' ) . "\n";
			$part .= 'Type: ' . ( isset( $item['post_type'] ) ? $item['post_type'] : '' ) . "\n";
			$part .= 'Link: ' . ( isset( $item['url'] ) ? $item['url'] : '' ) . "\n";

			// Only products are ever referenced by [[PRODUCT:ID]], so the id
			// is surfaced just for them rather than cluttering every result.
			if ( isset( $item['post_type'] ) && 'product' === $item['post_type'] && ! empty( $item['post_id'] ) ) {
				$part .= 'Product ID: ' . (int) $item['post_id'] . "\n";
			}

			if ( ! empty( $item['categories'] ) ) {
				$part .= 'Categories: ' . $item['categories'] . "\n";
			}

			$content = wp_strip_all_tags( isset( $item['content'] ) ? $item['content'] : '' );

			$part   .= 'Content: ' . Text::truncate( $content, self::CONTEXT_CHARS ) . "\n";
			$parts[] = $part;
			$index++;
		}

		return implode( "\n-----\n", $parts );
	}

	/**
	 * Fetch one document by its source post id — used to ground a
	 * `[[PRODUCT:id]]` directive in the actual indexed price/stock rather
	 * than whatever the model claims.
	 *
	 * @param int $post_id Source post id.
	 * @return array|null
	 */
	public function get_by_post_id( $post_id ) {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE post_id = %d", $post_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Total indexed documents.
	 *
	 * @return int
	 */
	public function get_count() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Document counts grouped by post type.
	 *
	 * @return array<string,int>
	 */
	public function get_counts_by_type() {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			"SELECT post_type, COUNT(*) AS cnt FROM {$this->table} GROUP BY post_type",
			ARRAY_A
		);

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ $row['post_type'] ] = (int) $row['cnt'];
		}

		return $counts;
	}

	/**
	 * Most recently indexed documents.
	 *
	 * @param int $limit Maximum rows.
	 * @return array
	 */
	public function get_recent( $limit = 20 ) {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, post_id, post_type, title, url, indexed_at
				FROM {$this->table}
				ORDER BY indexed_at DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $rows ? $rows : array();
	}

	/**
	 * Remove every document.
	 *
	 * @return bool
	 */
	public function clear_all() {
		global $wpdb;

		$done = (bool) $wpdb->query( "TRUNCATE TABLE {$this->table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		$this->flush_cache();

		return $done;
	}

	/**
	 * Remove every document of one post type.
	 *
	 * @param string $post_type Post type.
	 * @return int|false Rows deleted.
	 */
	public function delete_by_post_type( $post_type ) {
		global $wpdb;

		$deleted = $wpdb->delete( $this->table, array( 'post_type' => sanitize_key( $post_type ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$this->flush_cache();

		return $deleted;
	}

	/**
	 * Invalidate every cached context block.
	 *
	 * Bumping a counter is used instead of deleting transients so this
	 * stays O(1) whether or not a persistent object cache is in play.
	 *
	 * @return void
	 */
	public function flush_cache() {
		update_option( self::CACHE_VERSION_OPTION, (int) $this->cache_version() + 1, false );
	}

	/**
	 * Current cache generation.
	 *
	 * @return int
	 */
	private function cache_version() {
		return (int) get_option( self::CACHE_VERSION_OPTION, 1 );
	}

	/**
	 * Normalize a term list into a comma separated string.
	 *
	 * @param mixed $terms Array of names or a plain string.
	 * @return string
	 */
	private function flatten_terms( $terms ) {
		if ( is_array( $terms ) ) {
			return implode( ',', array_map( 'sanitize_text_field', $terms ) );
		}

		return sanitize_text_field( (string) $terms );
	}
}
