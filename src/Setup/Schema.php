<?php
/**
 * Database schema.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Setup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the plugin's custom table names and their definitions.
 */
final class Schema {

	/**
	 * Unprefixed name of the knowledge base table.
	 */
	const KNOWLEDGE = 'yuniq_ai_knowledge';

	/**
	 * Unprefixed name of the conversation log table.
	 */
	const ANALYTICS = 'yuniq_ai_analytics';

	/**
	 * Unprefixed name of the crawl log table.
	 */
	const CRAWL_LOG = 'yuniq_ai_crawl_log';

	/**
	 * Schema revision. Bumped whenever a table definition changes.
	 */
	const DB_VERSION = '2';

	/**
	 * Option storing the revision the tables were last built at.
	 */
	const DB_VERSION_OPTION = 'yuniq_ai_db_version';

	/**
	 * Every table the plugin owns.
	 *
	 * @return string[] Unprefixed table names.
	 */
	public static function tables() {
		return array( self::KNOWLEDGE, self::ANALYTICS, self::CRAWL_LOG );
	}

	/**
	 * Prefix a table name for the current site.
	 *
	 * @param string $name One of the class constants.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;

		return $wpdb->prefix . $name;
	}

	/**
	 * Run dbDelta only when the stored revision is behind.
	 *
	 * Called on every request, so it must stay a single option read in the
	 * common case.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		self::create();
	}

	/**
	 * Create or upgrade every table.
	 *
	 * @return void
	 */
	public static function create() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$knowledge       = self::table( self::KNOWLEDGE );
		$analytics       = self::table( self::ANALYTICS );
		$crawl_log       = self::table( self::CRAWL_LOG );

		/*
		 * `search_text` holds the Persian-normalized copy of title, excerpt
		 * and content. Queries are normalized the same way before matching,
		 * so ی/ي, ک/ك, ZWNJ and digit-script differences all collapse.
		 */
		$sql_knowledge = "CREATE TABLE {$knowledge} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned DEFAULT NULL,
			post_type varchar(50) DEFAULT '',
			title text NOT NULL,
			content longtext NOT NULL,
			excerpt text DEFAULT '',
			search_text longtext DEFAULT '',
			url varchar(500) DEFAULT '',
			slug varchar(255) DEFAULT '',
			categories text DEFAULT '',
			tags text DEFAULT '',
			metadata longtext DEFAULT '',
			embedding_hash varchar(64) DEFAULT '',
			indexed_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY post_type (post_type),
			KEY slug (slug(191)),
			FULLTEXT KEY search_normalized (search_text)
		) {$charset_collate};";

		$sql_analytics = "CREATE TABLE {$analytics} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(64) NOT NULL,
			user_question text NOT NULL,
			ai_response longtext DEFAULT '',
			topics text DEFAULT '',
			response_time float DEFAULT 0,
			tokens_used int DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		/*
		 * `phase` and `cursor_offset` let a crawl resume where the previous
		 * AJAX batch stopped, so a large site is indexed across many short
		 * requests instead of one that times out.
		 */
		$sql_crawl_log = "CREATE TABLE {$crawl_log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			status varchar(20) DEFAULT 'pending',
			phase varchar(20) DEFAULT 'posts',
			cursor_offset int DEFAULT 0,
			total_items int DEFAULT 0,
			processed_items int DEFAULT 0,
			message text DEFAULT '',
			started_at datetime DEFAULT NULL,
			finished_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql_knowledge );
		dbDelta( $sql_analytics );
		dbDelta( $sql_crawl_log );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Permanently remove every table. Used only by uninstall.php.
	 *
	 * @return void
	 */
	public static function drop() {
		global $wpdb;

		foreach ( self::tables() as $name ) {
			$table = self::table( $name );
			// Table name comes from a class constant, never from user input.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		}
	}
}
