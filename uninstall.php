<?php
/**
 * Removes every trace of the plugin when it is deleted.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

// Only WordPress may run this file, and only during an uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete this site's options, tables and cached transients.
 *
 * @return void
 */
function yuniq_ai_uninstall_site() {
	global $wpdb;

	$options = array(
		'yuniq_ai_settings',
		'yuniq_ai_db_version',
		'yuniq_ai_kb_cache_version',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	$tables = array(
		'yuniq_ai_knowledge',
		'yuniq_ai_analytics',
		'yuniq_ai_crawl_log',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	// Rate limiter buckets and cached context blocks, which would otherwise
	// linger in the options table until their timeouts were read again.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_yuniq_ai_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_yuniq_ai_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	$yuniq_ai_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $yuniq_ai_site_ids as $yuniq_ai_site_id ) {
		switch_to_blog( $yuniq_ai_site_id );
		yuniq_ai_uninstall_site();
		restore_current_blog();
	}

	unset( $yuniq_ai_site_ids, $yuniq_ai_site_id );
} else {
	yuniq_ai_uninstall_site();
}
