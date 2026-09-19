<?php
/**
 * Crawls WordPress content into the knowledge base, one batch per request.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Kb;

use Yuniq\Ai\Settings;
use Yuniq\Ai\Setup\Schema;
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walks the selected post types and taxonomies and indexes them.
 *
 * A full crawl is split across many short AJAX requests: each call
 * processes one batch, advances a cursor stored on the crawl log row and
 * reports real progress. A site with thousands of posts therefore never
 * depends on a single request surviving long enough to finish.
 */
final class Indexer {

	/**
	 * Items handled per batch when the caller does not say.
	 */
	const DEFAULT_BATCH_SIZE = 20;

	/**
	 * Crawl phases, in order.
	 */
	const PHASE_POSTS = 'posts';
	const PHASE_TERMS = 'terms';
	const PHASE_DONE  = 'done';

	/**
	 * Knowledge base storage.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Crawl log table.
	 *
	 * @var string
	 */
	private $log_table;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Knowledge base storage.
	 * @param Settings   $settings   Plugin settings.
	 */
	public function __construct( Repository $repository, Settings $settings ) {
		$this->repository = $repository;
		$this->settings   = $settings;
		$this->log_table  = Schema::table( Schema::CRAWL_LOG );
	}

	/**
	 * Open a crawl and count the work ahead.
	 *
	 * @return array{log_id:int, total:int, processed:int, phase:string, done:bool}
	 */
	public function start() {
		global $wpdb;

		$total = $this->count_posts() + $this->count_terms();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->log_table,
			array(
				'status'          => 'running',
				'phase'           => self::PHASE_POSTS,
				'cursor_offset'   => 0,
				'total_items'     => $total,
				'processed_items' => 0,
				'started_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		return array(
			'log_id'    => (int) $wpdb->insert_id,
			'total'     => $total,
			'processed' => 0,
			'phase'     => self::PHASE_POSTS,
			'done'      => 0 === $total,
		);
	}

	/**
	 * Index the next batch of a running crawl.
	 *
	 * @param int $log_id     Crawl log row id.
	 * @param int $batch_size Items to handle in this call.
	 * @return array{log_id:int, total:int, processed:int, phase:string, done:bool, message:string}
	 */
	public function process_batch( $log_id, $batch_size = self::DEFAULT_BATCH_SIZE ) {
		$log_id     = (int) $log_id;
		$batch_size = max( 1, min( 100, (int) $batch_size ) );
		$log        = $this->get_log( $log_id );

		if ( ! $log ) {
			return $this->progress( $log_id, 0, 0, self::PHASE_DONE, true, __( 'این عملیات یافت نشد.', 'yuniq-ai' ) );
		}

		if ( 'stopped' === $log['status'] ) {
			return $this->progress(
				$log_id,
				(int) $log['total_items'],
				(int) $log['processed_items'],
				self::PHASE_DONE,
				true,
				__( 'ایندکس‌گذاری متوقف شد.', 'yuniq-ai' )
			);
		}

		$phase     = isset( $log['phase'] ) ? $log['phase'] : self::PHASE_POSTS;
		$offset    = (int) $log['cursor_offset'];
		$processed = (int) $log['processed_items'];
		$total     = (int) $log['total_items'];

		$include = $this->parse_slug_list( $this->settings->get( 'include_slugs', '' ) );
		$exclude = $this->parse_slug_list( $this->settings->get( 'exclude_slugs', '' ) );

		if ( self::PHASE_POSTS === $phase ) {
			$handled = $this->index_post_batch( $offset, $batch_size, $include, $exclude );

			if ( 0 === $handled['scanned'] ) {
				// Posts exhausted; move on to taxonomy terms.
				$this->update_log( $log_id, array( 'phase' => self::PHASE_TERMS, 'cursor_offset' => 0 ) );

				return $this->progress( $log_id, $total, $processed, self::PHASE_TERMS, false );
			}

			$processed += $handled['indexed'];
			$offset    += $handled['scanned'];

			$this->update_log( $log_id, array( 'cursor_offset' => $offset, 'processed_items' => $processed ) );

			return $this->progress( $log_id, $total, $processed, self::PHASE_POSTS, false );
		}

		if ( self::PHASE_TERMS === $phase ) {
			$handled = $this->index_term_batch( $offset, $batch_size, $include, $exclude );

			if ( 0 === $handled['scanned'] ) {
				return $this->finish( $log_id, $processed, $total );
			}

			$processed += $handled['indexed'];
			$offset    += $handled['scanned'];

			$this->update_log( $log_id, array( 'cursor_offset' => $offset, 'processed_items' => $processed ) );

			return $this->progress( $log_id, $total, $processed, self::PHASE_TERMS, false );
		}

		return $this->finish( $log_id, $processed, $total );
	}

	/**
	 * Mark a running crawl as stopped.
	 *
	 * @param int $log_id Crawl log row id.
	 * @return void
	 */
	public function stop( $log_id ) {
		$this->update_log(
			(int) $log_id,
			array(
				'status'      => 'stopped',
				'phase'       => self::PHASE_DONE,
				'finished_at' => current_time( 'mysql' ),
				'message'     => __( 'ایندکس‌گذاری توسط کاربر متوقف شد.', 'yuniq-ai' ),
			)
		);

		$this->repository->flush_cache();
	}

	/**
	 * The most recent crawl log row.
	 *
	 * @return array|null
	 */
	public function get_latest_status() {
		global $wpdb;

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			"SELECT * FROM {$this->log_table} ORDER BY id DESC LIMIT 1",
			ARRAY_A
		);
	}

	/**
	 * Index one page of posts.
	 *
	 * @param int   $offset  Rows already scanned.
	 * @param int   $limit   Rows to scan now.
	 * @param array $include Paths that must match.
	 * @param array $exclude Paths that must not match.
	 * @return array{scanned:int, indexed:int}
	 */
	private function index_post_batch( $offset, $limit, array $include, array $exclude ) {
		$query = new WP_Query(
			array(
				'post_type'              => $this->resolve_content_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$indexed = 0;

		foreach ( $query->posts as $post ) {
			if ( ! $this->passes_slug_filters( get_permalink( $post ), $include, $exclude ) ) {
				continue;
			}

			$this->index_post( $post );
			$indexed++;
		}

		return array(
			'scanned' => count( $query->posts ),
			'indexed' => $indexed,
		);
	}

	/**
	 * Index one page of taxonomy terms.
	 *
	 * @param int   $offset  Terms already scanned.
	 * @param int   $limit   Terms to scan now.
	 * @param array $include Paths that must match.
	 * @param array $exclude Paths that must not match.
	 * @return array{scanned:int, indexed:int}
	 */
	private function index_term_batch( $offset, $limit, array $include, array $exclude ) {
		$taxonomies = $this->resolve_taxonomies();

		if ( empty( $taxonomies ) ) {
			return array(
				'scanned' => 0,
				'indexed' => 0,
			);
		}

		$terms = get_terms(
			array(
				'taxonomy'   => array_keys( $taxonomies ),
				'hide_empty' => false,
				'number'     => $limit,
				'offset'     => $offset,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array(
				'scanned' => 0,
				'indexed' => 0,
			);
		}

		$indexed = 0;

		foreach ( $terms as $term ) {
			$url = get_term_link( $term );

			if ( is_wp_error( $url ) || ! $this->passes_slug_filters( $url, $include, $exclude ) ) {
				continue;
			}

			$label   = isset( $taxonomies[ $term->taxonomy ] ) ? $taxonomies[ $term->taxonomy ] : $term->taxonomy;
			$content = $term->description ? wp_strip_all_tags( $term->description ) : '';
			$content = trim( $content . ' ' . $label . ': ' . $term->name );

			$this->repository->upsert(
				array(
					'post_id'    => 0,
					'post_type'  => 'tax_' . $term->taxonomy,
					'title'      => $term->name,
					'content'    => $content,
					'excerpt'    => wp_trim_words( $content, 30, '...' ),
					'url'        => $url,
					'slug'       => $term->slug,
					'categories' => array( $label ),
					'tags'       => array(),
					'metadata'   => array(
						'term_id'  => $term->term_id,
						'taxonomy' => $term->taxonomy,
						'count'    => $term->count,
					),
				)
			);

			$indexed++;
		}

		return array(
			'scanned' => count( $terms ),
			'indexed' => $indexed,
		);
	}

	/**
	 * Store one post as a knowledge base document.
	 *
	 * @param WP_Post $post Post to index.
	 * @return void
	 */
	private function index_post( WP_Post $post ) {
		$content = apply_filters( 'the_content', $post->post_content );
		$content = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $content ) ) );

		$excerpt = $post->post_excerpt;
		if ( '' === $excerpt ) {
			$excerpt = wp_trim_words( $content, 40, '...' );
		}

		$is_product = ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) );

		if ( $is_product ) {
			$categories = $this->term_names( $post->ID, 'product_cat' );
			$tags       = $this->term_names( $post->ID, 'product_tag' );
		} else {
			$cats       = get_the_category( $post->ID );
			$categories = $cats ? wp_list_pluck( $cats, 'name' ) : array();
			$post_tags  = get_the_tags( $post->ID );
			$tags       = $post_tags ? wp_list_pluck( $post_tags, 'name' ) : array();
		}

		$this->repository->upsert(
			array(
				'post_id'    => $post->ID,
				'post_type'  => $post->post_type,
				'title'      => $post->post_title,
				'content'    => $content,
				'excerpt'    => $excerpt,
				'url'        => get_permalink( $post ),
				'slug'       => $post->post_name,
				'categories' => $categories,
				'tags'       => $tags,
				'metadata'   => $is_product ? $this->product_metadata( $post->ID ) : array(),
			)
		);
	}

	/**
	 * How many posts a full crawl would scan.
	 *
	 * @return int
	 */
	private function count_posts() {
		$query = new WP_Query(
			array(
				'post_type'              => $this->resolve_content_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * How many taxonomy terms a full crawl would scan.
	 *
	 * @return int
	 */
	private function count_terms() {
		$taxonomies = $this->resolve_taxonomies();

		if ( empty( $taxonomies ) ) {
			return 0;
		}

		$count = wp_count_terms(
			array(
				'taxonomy'   => array_keys( $taxonomies ),
				'hide_empty' => false,
			)
		);

		return is_wp_error( $count ) ? 0 : (int) $count;
	}

	/**
	 * Post types to crawl, with unavailable ones removed.
	 *
	 * @return string[]
	 */
	private function resolve_content_types() {
		$types = (array) $this->settings->get( 'content_types', array( 'post', 'page' ) );

		if ( in_array( 'everything', $types, true ) ) {
			$types = get_post_types( array( 'public' => true ), 'names' );
			unset( $types['attachment'] );
			$types = array_values( $types );
		}

		if ( in_array( 'product', $types, true ) && ! class_exists( 'WooCommerce' ) ) {
			$types = array_values( array_diff( $types, array( 'product' ) ) );
		}

		return $types ? $types : array( 'post', 'page' );
	}

	/**
	 * Taxonomies selected for indexing, mapped to their display label.
	 *
	 * @return array<string,string>
	 */
	private function resolve_taxonomies() {
		$map = array();

		if ( $this->settings->get( 'wp_categories' ) ) {
			$map['category'] = __( 'دسته مقاله', 'yuniq-ai' );
		}
		if ( $this->settings->get( 'wp_tags' ) ) {
			$map['post_tag'] = __( 'برچسب مقاله', 'yuniq-ai' );
		}
		if ( $this->settings->get( 'wc_product_categories' ) && taxonomy_exists( 'product_cat' ) ) {
			$map['product_cat'] = __( 'دسته محصول', 'yuniq-ai' );
		}
		if ( $this->settings->get( 'wc_product_tags' ) && taxonomy_exists( 'product_tag' ) ) {
			$map['product_tag'] = __( 'برچسب محصول', 'yuniq-ai' );
		}

		return $map;
	}

	/**
	 * Extra fields worth indexing for a WooCommerce product.
	 *
	 * @param int $post_id Product id.
	 * @return array
	 */
	private function product_metadata( $post_id ) {
		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return array();
		}

		return array(
			'price'         => $product->get_price(),
			'regular_price' => $product->get_regular_price(),
			'sale_price'    => $product->get_sale_price(),
			'sku'           => $product->get_sku(),
			'stock_status'  => $product->get_stock_status(),
			'type'          => $product->get_type(),
		);
	}

	/**
	 * Apply the include/exclude path filters to a URL.
	 *
	 * @param string $url     Permalink or term link.
	 * @param array  $include Paths that must match.
	 * @param array  $exclude Paths that must not match.
	 * @return bool
	 */
	private function passes_slug_filters( $url, array $include, array $exclude ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$path = trailingslashit( $path ? $path : '/' );

		if ( $include && ! $this->matches_any_slug( $path, $include ) ) {
			return false;
		}

		if ( $exclude && $this->matches_any_slug( $path, $exclude ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether a path matches any pattern in the list.
	 *
	 * @param string $path  URL path.
	 * @param array  $slugs Patterns.
	 * @return bool
	 */
	private function matches_any_slug( $path, array $slugs ) {
		foreach ( $slugs as $slug ) {
			if ( $path === $slug || false !== strpos( $path, $slug ) ) {
				return true;
			}

			if ( false !== strpos( $path, rtrim( $slug, '/' ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Turn a textarea of paths into normalized patterns.
	 *
	 * @param string $list Raw textarea value.
	 * @return string[]
	 */
	private function parse_slug_list( $list ) {
		if ( empty( $list ) ) {
			return array();
		}

		$slugs = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $list ) as $line ) {
			$line = trim( $line );

			if ( '' !== $line ) {
				$slugs[] = '/' . trim( $line, '/' ) . '/';
			}
		}

		return $slugs;
	}

	/**
	 * Term names attached to a post.
	 *
	 * @param int    $post_id  Post id.
	 * @param string $taxonomy Taxonomy name.
	 * @return string[]
	 */
	private function term_names( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );

		return ( $terms && ! is_wp_error( $terms ) ) ? wp_list_pluck( $terms, 'name' ) : array();
	}

	/**
	 * Close out a completed crawl.
	 *
	 * @param int $log_id    Crawl log row id.
	 * @param int $processed Documents indexed.
	 * @param int $total     Items scanned.
	 * @return array
	 */
	private function finish( $log_id, $processed, $total ) {
		$message = sprintf(
			/* translators: %d: number of indexed items. */
			__( 'تعداد %d آیتم با موفقیت ایندکس شد.', 'yuniq-ai' ),
			$processed
		);

		$this->update_log(
			$log_id,
			array(
				'status'          => 'completed',
				'phase'           => self::PHASE_DONE,
				'processed_items' => $processed,
				'finished_at'     => current_time( 'mysql' ),
				'message'         => $message,
			)
		);

		$stored                 = get_option( Settings::OPTION_KEY, array() );
		$stored                 = is_array( $stored ) ? $stored : array();
		$stored['crawl_status'] = 'completed';
		$stored['last_crawl']   = current_time( 'mysql' );
		update_option( Settings::OPTION_KEY, $stored );

		$this->settings->flush();

		// Answers built from the old index are no longer correct.
		$this->repository->flush_cache();

		return $this->progress( $log_id, $total, $processed, self::PHASE_DONE, true, $message );
	}

	/**
	 * Shape a progress report for the browser.
	 *
	 * @param int    $log_id    Crawl log row id.
	 * @param int    $total     Items to scan in total.
	 * @param int    $processed Documents indexed so far.
	 * @param string $phase     Current phase.
	 * @param bool   $done      Whether the crawl has finished.
	 * @param string $message   Optional status line.
	 * @return array
	 */
	private function progress( $log_id, $total, $processed, $phase, $done, $message = '' ) {
		return array(
			'log_id'    => (int) $log_id,
			'total'     => (int) $total,
			'processed' => (int) $processed,
			'phase'     => $phase,
			'done'      => (bool) $done,
			'message'   => $message,
		);
	}

	/**
	 * Read one crawl log row.
	 *
	 * @param int $log_id Crawl log row id.
	 * @return array|null
	 */
	private function get_log( $log_id ) {
		global $wpdb;

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM {$this->log_table} WHERE id = %d", $log_id ),
			ARRAY_A
		);
	}

	/**
	 * Patch fields on a crawl log row.
	 *
	 * @param int   $log_id Crawl log row id.
	 * @param array $fields Column/value pairs.
	 * @return void
	 */
	private function update_log( $log_id, array $fields ) {
		global $wpdb;

		$formats = array();

		foreach ( $fields as $value ) {
			$formats[] = is_int( $value ) ? '%d' : '%s';
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->log_table,
			$fields,
			array( 'id' => (int) $log_id ),
			$formats,
			array( '%d' )
		);
	}
}
