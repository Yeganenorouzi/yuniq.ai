<?php
/**
 * Daily housekeeping.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Support;

use Yuniq\Ai\Contracts\HookableInterface;
use Yuniq\Ai\Settings;
use Yuniq\Ai\Setup\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies the data-retention choice from the settings screen: visitor
 * conversations and form submissions older than the chosen age are
 * deleted once a day, so personal data is not kept forever by default.
 */
final class Maintenance implements HookableInterface {

	/**
	 * Cron hook name.
	 */
	const HOOK = 'yuniq_ai_daily_cleanup';

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks() {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'admin_init', array( $this, 'schedule' ) );
	}

	/**
	 * Make sure the daily event exists.
	 *
	 * @return void
	 */
	public function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Delete what is older than the retention period.
	 *
	 * @return void
	 */
	public function run() {
		global $wpdb;

		// The error log is diagnostics, not history: a month is plenty.
		Logger::prune( 30 );

		$days = (int) $this->settings->get( 'data_retention', 0 );

		if ( $days <= 0 ) {
			return;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - $days * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp -- compared against site-time columns.

		$analytics     = Schema::table( Schema::ANALYTICS );
		$messages      = Schema::table( Schema::MESSAGES );
		$conversations = Schema::table( Schema::CONVERSATIONS );
		$leads         = Schema::table( Schema::LEADS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names come from class constants.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$analytics} WHERE created_at < %s", $cutoff ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$messages} WHERE created_at < %s", $cutoff ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$leads} WHERE created_at < %s", $cutoff ) );
		// A conversation still open with an agent is never removed.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$conversations} WHERE status NOT IN ('pending','active') AND COALESCE(last_message_at, created_at) < %s", $cutoff ) );
		// phpcs:enable
	}
}
