<?php
/**
 * Storage for in-chat lead-capture form submissions.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\LiveSupport;

use Yuniq\Ai\Setup\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One row per submitted form, with the field values kept as JSON since the
 * field set is admin-defined per form (see `Settings::defaults()['lead_forms']`).
 */
final class LeadRepository {

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
		$this->table = Schema::table( Schema::LEADS );
	}

	/**
	 * Store a submission.
	 *
	 * @param string $session_id Session identifier.
	 * @param string $form_key   Which configured form this came from.
	 * @param array  $fields     Field name => submitted value.
	 * @return int|false Row id.
	 */
	public function insert( $session_id, $form_key, array $fields ) {
		global $wpdb;

		$clean = array();
		foreach ( $fields as $name => $value ) {
			$clean[ sanitize_key( $name ) ] = sanitize_text_field( (string) $value );
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table,
			array(
				'session_id' => sanitize_text_field( $session_id ),
				'form_key'   => sanitize_key( $form_key ),
				'fields'     => wp_json_encode( $clean ),
				'status'     => 'new',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Most recent leads, with `fields` decoded back into an array.
	 *
	 * @param int $limit Maximum rows.
	 * @return array
	 */
	public function get_recent( $limit = 50 ) {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT %d", $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$rows = $rows ? $rows : array();

		foreach ( $rows as &$row ) {
			$decoded        = json_decode( (string) $row['fields'], true );
			$row['fields']  = is_array( $decoded ) ? $decoded : array();
		}
		unset( $row );

		return $rows;
	}
}
