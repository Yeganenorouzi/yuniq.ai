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
		$sku                = is_array( $data['metadata'] ) ? self::searchable_meta( $data['metadata'] ) : '';
		$data['metadata']   = is_array( $data['metadata'] ) ? wp_json_encode( $data['metadata'], JSON_UNESCAPED_UNICODE ) : (string) $data['metadata'];

		// The normalized copy is what every search actually matches against.
		$data['search_text'] = Text::normalize(
			$data['title'] . ' ' . $data['categories'] . ' ' . $data['tags'] . ' ' . $sku . ' ' . $data['excerpt'] . ' ' . $data['content']
		);

		// Stamped on every pass (not only on insert), so a finished crawl can
		// tell which rows it did not touch and prune them.
		$data['indexed_at'] = current_time( 'mysql' );

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( ! empty( $data['post_id'] ) ) {
			$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( "SELECT id FROM {$this->table} WHERE post_id = %d", $data['post_id'] ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		} else {
			// Taxonomy terms carry no post id; without this lookup every
			// re-crawl stored each term a second time.
			$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( "SELECT id FROM {$this->table} WHERE post_type = %s AND slug = %s AND ( post_id IS NULL OR post_id = 0 ) LIMIT 1", $data['post_type'], $data['slug'] ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}

		if ( $existing ) {
			$wpdb->update( $this->table, $data, array( 'id' => $existing ), $formats, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			return (int) $existing;
		}

		$wpdb->insert( $this->table, $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $wpdb->insert_id ) {
			\Yuniq\Ai\Support\Logger::db( sprintf( 'ایندکس «%s» در پایگاه دانش ذخیره نشد.', $data['title'] ) );
		}

		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Find documents relevant to a question.
	 *
	 * Every meaningful word is scored against each document: a hit in the
	 * title area counts three times a hit in the body, and words from the
	 * question itself count twice the words borrowed from the conversation
	 * so far. That last part is what lets a follow-up such as «چه رنگ‌هایی
	 * داری؟» still find the product the visitor was just talking about.
	 *
	 * MySQL FULLTEXT is not used for ranking: it ignores short tokens such
	 * as "16", and it happily ranked documents on filler words.
	 *
	 * @param string $query    The visitor's question.
	 * @param int    $limit    Maximum rows.
	 * @param string $context  Earlier turns of the conversation, optional.
	 * @param array  $synonyms Extra synonym groups from the settings.
	 * @return array
	 */
	public function search( $query, $limit = 5, $context = '', array $synonyms = array() ) {
		global $wpdb;

		$weights = Text::weighted_tokens( $query, $context, $synonyms );

		if ( ! $weights ) {
			$normalized = Text::normalize( $query );

			if ( '' === $normalized ) {
				return array();
			}

			// Nothing but stop words: match the phrase as typed.
			$weights = array( $normalized => 2 );
		}

		$score  = array();
		$where  = array();
		$values = array();
		$likes  = array();

		foreach ( $weights as $token => $weight ) {
			$like     = '%' . $wpdb->esc_like( $token ) . '%';
			$score[]  = '(CASE WHEN LEFT(search_text, 220) LIKE %s THEN %d ELSE 0 END + CASE WHEN search_text LIKE %s THEN %d ELSE 0 END)';
			$values[] = $like;
			$values[] = 3 * (int) $weight;
			$values[] = $like;
			$values[] = (int) $weight;
			$where[]  = 'search_text LIKE %s';
			$likes[]  = $like;
		}

		$values   = array_merge( $values, $likes );
		$values[] = max( 1, (int) $limit ) * 2;

		$ranked = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT id, (' . implode( ' + ', $score ) . ") AS relevance FROM {$this->table} WHERE " . implode( ' OR ', $where ) . ' ORDER BY relevance DESC, id DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders are built above, one per value.
				$values
			),
			ARRAY_A
		);

		if ( empty( $ranked ) ) {
			return array();
		}

		// Weak tail matches (one borrowed word somewhere in the body) only
		// dilute the context, so anything far below the best hit is dropped.
		$best = (int) $ranked[0]['relevance'];
		$ids  = array();

		foreach ( $ranked as $row ) {
			if ( (int) $row['relevance'] * 3 >= $best && count( $ids ) < $limit ) {
				$ids[] = (int) $row['id'];
			}
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id, post_id, post_type, title, content, excerpt, url, slug, categories, tags, metadata FROM {$this->table} WHERE id IN (" . implode( ',', $ids ) . ')', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integer ids from the query above.
			ARRAY_A
		);

		$by_id = array();
		foreach ( (array) $rows as $row ) {
			$by_id[ (int) $row['id'] ] = $row;
		}

		$results = array();
		foreach ( $ids as $id ) {
			if ( isset( $by_id[ $id ] ) ) {
				$results[] = $by_id[ $id ];
			}
		}

		return $results;
	}

	/**
	 * Build the context block handed to the model for a question.
	 *
	 * Results are cached per normalized question, so a repeated or common
	 * question skips the database entirely.
	 *
	 * @param string $query    User question.
	 * @param int    $limit    Maximum documents.
	 * @param string $context  Earlier turns of the conversation, optional.
	 * @param int    $chars    Characters of body text kept per document.
	 * @param array  $synonyms Extra synonym groups from the settings.
	 * @return string Empty when nothing matched.
	 */
	public function get_context_for_query( $query, $limit = 5, $context = '', $chars = self::CONTEXT_CHARS, array $synonyms = array() ) {
		$normalized = Text::normalize( $query . ' | ' . $context );

		if ( '' === trim( $normalized, ' |' ) ) {
			return '';
		}

		$cache_key = 'yuniq_ai_ctx_' . md5( $this->cache_version() . '|' . $limit . '|' . $chars . '|' . wp_json_encode( $synonyms ) . '|' . $normalized );
		$cached    = get_transient( $cache_key );

		if ( is_string( $cached ) ) {
			return $cached;
		}

		$built = $this->build_context( $this->search( $query, $limit, $context, $synonyms ), max( 200, (int) $chars ) );

		set_transient( $cache_key, $built, self::CACHE_TTL );

		return $built;
	}

	/**
	 * Render search results into the block the model reads.
	 *
	 * @param array $results Rows from {@see self::search()}.
	 * @param int   $chars   Characters of body text kept per document.
	 * @return string
	 */
	private function build_context( array $results, $chars = self::CONTEXT_CHARS ) {
		if ( empty( $results ) ) {
			return '';
		}

		$parts = array();
		$index = 1;

		foreach ( $results as $item ) {
			$is_product = isset( $item['post_type'] ) && 'product' === $item['post_type'];

			$part  = "[Document {$index}]\n";
			$part .= 'Title: ' . ( isset( $item['title'] ) ? $item['title'] : '' ) . "\n";
			$part .= 'Type: ' . ( isset( $item['post_type'] ) ? $item['post_type'] : '' ) . "\n";
			$part .= 'Link: ' . ( isset( $item['url'] ) ? $item['url'] : '' ) . "\n";

			// Only products are ever referenced by [[PRODUCT:ID]], so the id
			// is surfaced just for them rather than cluttering every result.
			if ( $is_product && ! empty( $item['post_id'] ) ) {
				$part .= 'Product ID: ' . (int) $item['post_id'] . "\n";
			}

			if ( ! empty( $item['categories'] ) ) {
				$part .= 'Categories: ' . $item['categories'] . "\n";
			}

			// Facts first: they are short, exact, and must never be the part
			// that truncation cuts off.
			$part .= $this->facts( $item );

			if ( $is_product && ! empty( $item['excerpt'] ) ) {
				$part .= 'Summary: ' . Text::truncate( $item['excerpt'], 400 ) . "\n";
			}

			$content = wp_strip_all_tags( isset( $item['content'] ) ? $item['content'] : '' );

			$part   .= 'Content: ' . Text::truncate( $content, $chars ) . "\n";
			$parts[] = $part;
			$index++;
		}

		return implode( "\n-----\n", $parts );
	}

	/**
	 * The structured facts stored with a document: price, stock, SKU,
	 * attributes, variations, dimensions, reviews and custom fields —
	 * whichever of them the crawler settings chose to index.
	 *
	 * @param array $item Knowledge base row.
	 * @return string
	 */
	private function facts( array $item ) {
		$meta = empty( $item['metadata'] ) ? null : json_decode( (string) $item['metadata'], true );

		if ( ! is_array( $meta ) ) {
			return '';
		}

		$stock_labels = array(
			'instock'     => 'in stock',
			'outofstock'  => 'out of stock',
			'onbackorder' => 'available on backorder',
		);
		$show_stock   = ! isset( $meta['show_stock'] ) || $meta['show_stock'];
		$facts        = '';

		if ( ! empty( $meta['price_min'] ) && ! empty( $meta['price_max'] ) && (string) $meta['price_min'] !== (string) $meta['price_max'] ) {
			$facts .= 'Price: from ' . self::format_price( $meta['price_min'] ) . ' to ' . self::format_price( $meta['price_max'] ) . " (depends on the variation)\n";
		} else {
			$price = self::format_price( isset( $meta['price'] ) ? $meta['price'] : '' );
			$sale  = isset( $meta['sale_price'] ) ? (string) $meta['sale_price'] : '';
			$full  = isset( $meta['regular_price'] ) ? (string) $meta['regular_price'] : '';

			if ( '' !== $price ) {
				$facts .= 'Price: ' . $price . ( '' !== $sale && '' !== $full && $sale !== $full ? ' (on sale, was ' . self::format_price( $full ) . ')' : '' ) . "\n";
			}
		}

		if ( $show_stock && ! empty( $meta['stock_status'] ) ) {
			$facts .= 'Stock: ' . ( isset( $stock_labels[ $meta['stock_status'] ] ) ? $stock_labels[ $meta['stock_status'] ] : $meta['stock_status'] )
				. ( isset( $meta['stock_qty'] ) ? ' (' . (int) $meta['stock_qty'] . ' left)' : '' ) . "\n";
		}

		if ( ! empty( $meta['sku'] ) ) {
			$facts .= 'SKU: ' . $meta['sku'] . "\n";
		}

		foreach ( array( 'weight' => 'Weight', 'dimensions' => 'Dimensions', 'rating' => 'Customer rating' ) as $key => $label ) {
			if ( ! empty( $meta[ $key ] ) ) {
				$facts .= $label . ': ' . $meta[ $key ] . "\n";
			}
		}

		if ( ! empty( $meta['attributes'] ) && is_array( $meta['attributes'] ) ) {
			$facts .= "Attributes (all available options):\n";
			foreach ( $meta['attributes'] as $name => $values ) {
				$facts .= '- ' . $name . ': ' . $values . "\n";
			}
		}

		if ( ! empty( $meta['variations'] ) && is_array( $meta['variations'] ) ) {
			$facts .= "Variations (each is a separately purchasable option):\n";
			foreach ( $meta['variations'] as $variation ) {
				$line = '- ' . ( isset( $variation['label'] ) ? $variation['label'] : '' );

				if ( ! empty( $variation['price'] ) ) {
					$line .= ' | ' . self::format_price( $variation['price'] );
				}
				if ( ! empty( $variation['stock'] ) ) {
					$line .= ' | ' . ( isset( $stock_labels[ $variation['stock'] ] ) ? $stock_labels[ $variation['stock'] ] : $variation['stock'] );
				}
				if ( ! empty( $variation['sku'] ) ) {
					$line .= ' | SKU ' . $variation['sku'];
				}

				$facts .= $line . "\n";
			}
		}

		if ( ! empty( $meta['fields'] ) && is_array( $meta['fields'] ) ) {
			$facts .= "Extra fields:\n";
			foreach ( $meta['fields'] as $name => $value ) {
				$facts .= '- ' . $name . ': ' . $value . "\n";
			}
		}

		if ( ! empty( $meta['reviews'] ) && is_array( $meta['reviews'] ) ) {
			$facts .= "Customer reviews:\n";
			foreach ( $meta['reviews'] as $review ) {
				$facts .= '- ' . $review . "\n";
			}
		}

		return $facts;
	}

	/**
	 * The most populated indexed terms of one taxonomy.
	 *
	 * @param string $post_type Stored type, e.g. `tax_product_cat`.
	 * @param int    $limit     How many to return.
	 * @return array<int,array{title:string,count:int,url:string}>
	 */
	public function top_terms( $post_type, $limit = 3 ) {
		global $wpdb;

		$cache_key = 'yuniq_ai_top_' . md5( $this->cache_version() . '|' . $post_type . '|' . $limit );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT title, slug, url, metadata FROM {$this->table} WHERE post_type = %s LIMIT 300", $post_type ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$terms = array();

		foreach ( (array) $rows as $row ) {
			$meta  = json_decode( (string) $row['metadata'], true );
			$count = is_array( $meta ) && isset( $meta['count'] ) ? (int) $meta['count'] : 0;

			// Empty categories and WordPress's catch-all one are not a
			// useful thing to offer a visitor.
			if ( $count < 1 || in_array( $row['slug'], array( 'uncategorized', 'bdon-dsth-bndy' ), true ) || false !== strpos( $row['title'], 'دسته‌بندی نشده' ) || false !== strpos( $row['title'], 'بدون دسته' ) ) {
				continue;
			}

			$terms[] = array(
				'title' => wp_strip_all_tags( $row['title'] ),
				'count' => $count,
				'url'   => $row['url'],
			);
		}

		usort(
			$terms,
			function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);

		$terms = array_slice( $terms, 0, max( 1, (int) $limit ) );

		set_transient( $cache_key, $terms, DAY_IN_SECONDS );

		return $terms;
	}

	/**
	 * A short map of the whole site: how much of each kind of content is
	 * indexed, the shop's categories and the main pages.
	 *
	 * Sent with every question, so the assistant can answer "what do you
	 * sell?" or point to the right section even when the search for that
	 * particular wording found nothing.
	 *
	 * @return string
	 */
	public function site_overview() {
		global $wpdb;

		$cache_key = 'yuniq_ai_ovw_' . $this->cache_version();
		$cached    = get_transient( $cache_key );

		if ( is_string( $cached ) ) {
			return $cached;
		}

		$labels   = array(
			'product' => 'products',
			'post'    => 'articles',
			'page'    => 'pages',
		);
		$counts   = $this->get_counts_by_type();
		$overview = '';
		$summary  = array();

		foreach ( $labels as $type => $label ) {
			if ( ! empty( $counts[ $type ] ) ) {
				$summary[] = (int) $counts[ $type ] . ' ' . $label;
			}
		}

		if ( $summary ) {
			$overview .= 'Indexed content: ' . implode( ', ', $summary ) . ".\n";
		}

		$lists = array(
			'tax_product_cat' => array( 'Product categories', 40 ),
			'tax_category'    => array( 'Article categories', 20 ),
			'page'            => array( 'Main pages', 25 ),
		);

		foreach ( $lists as $type => $list ) {
			$titles = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( "SELECT title FROM {$this->table} WHERE post_type = %s ORDER BY id ASC LIMIT %d", $type, $list[1] ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);

			if ( $titles ) {
				$overview .= $list[0] . ': ' . implode( '، ', array_map( 'wp_strip_all_tags', $titles ) ) . "\n";
			}
		}

		set_transient( $cache_key, $overview, DAY_IN_SECONDS );

		return $overview;
	}

	/**
	 * The words in a document's structured facts that a visitor might
	 * search by: SKU, attribute values and variation names.
	 *
	 * @param array $meta Document metadata.
	 * @return string
	 */
	private static function searchable_meta( array $meta ) {
		$words = array();

		if ( ! empty( $meta['sku'] ) ) {
			$words[] = $meta['sku'];
		}

		foreach ( array( 'attributes', 'fields' ) as $group ) {
			if ( ! empty( $meta[ $group ] ) && is_array( $meta[ $group ] ) ) {
				foreach ( $meta[ $group ] as $name => $value ) {
					$words[] = $name . ' ' . $value;
				}
			}
		}

		if ( ! empty( $meta['variations'] ) && is_array( $meta['variations'] ) ) {
			foreach ( $meta['variations'] as $variation ) {
				$words[] = ( isset( $variation['label'] ) ? $variation['label'] : '' ) . ' ' . ( isset( $variation['sku'] ) ? $variation['sku'] : '' );
			}
		}

		return Text::truncate( implode( ' ', array_map( 'strval', $words ) ), 3000, '' );
	}

	/**
	 * A raw price as the shop would display it, e.g. `250,000 تومان`.
	 *
	 * @param mixed $amount Raw numeric price from the product.
	 * @return string Empty when there is no price.
	 */
	public static function format_price( $amount ) {
		if ( '' === $amount || null === $amount || ! is_numeric( $amount ) ) {
			return '';
		}

		if ( function_exists( 'wc_price' ) ) {
			return trim( html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount ) ), ENT_QUOTES, 'UTF-8' ) );
		}

		return number_format_i18n( (float) $amount );
	}

	/**
	 * Remove the document stored for one post.
	 *
	 * @param int $post_id Source post id.
	 * @return void
	 */
	public function delete_by_post_id( $post_id ) {
		global $wpdb;

		$post_id = (int) $post_id;

		if ( $post_id <= 0 ) {
			return;
		}

		if ( $wpdb->delete( $this->table, array( 'post_id' => $post_id ), array( '%d' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->flush_cache();
		}
	}

	/**
	 * Remove documents a completed crawl did not touch: deleted or
	 * unpublished content, and anything the filters now exclude.
	 *
	 * @param string $before MySQL datetime the crawl started at.
	 * @return int Rows removed.
	 */
	public function delete_stale( $before ) {
		global $wpdb;

		return (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "DELETE FROM {$this->table} WHERE indexed_at < %s", $before ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
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
