<?php
/**
 * Error and event log shown on the "status and errors" screen.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the most recent problems the plugin ran into, so a site owner can
 * see why something failed without reading server logs.
 *
 * Entries live in one non-autoloaded option, capped at {@see self::MAX}.
 * Secrets never reach the log: every message passes through
 * {@see self::redact()} first.
 */
final class Logger {

	/**
	 * Option holding the log entries.
	 */
	const OPTION = 'yuniq_ai_error_log';

	/**
	 * Small autoloaded counter behind the admin menu badge.
	 */
	const UNSEEN_OPTION = 'yuniq_ai_error_unseen';

	/**
	 * Entries kept.
	 */
	const MAX = 100;

	/**
	 * Seconds inside which an identical entry is counted, not repeated.
	 */
	const DEDUPE_WINDOW = 900;

	/**
	 * Something failed and a feature did not work.
	 *
	 * @param string $source  Area: ai, notify, crawl, db, security, system.
	 * @param string $message What happened, in Persian, for the site owner.
	 * @param array  $context Extra scalar details.
	 * @return void
	 */
	public static function error( $source, $message, array $context = array() ) {
		self::record( 'error', $source, $message, $context );
	}

	/**
	 * Something worth knowing that did not break anything.
	 *
	 * @param string $source  Area.
	 * @param string $message What happened.
	 * @param array  $context Extra scalar details.
	 * @return void
	 */
	public static function warning( $source, $message, array $context = array() ) {
		self::record( 'warning', $source, $message, $context );
	}

	/**
	 * Record a failed database write, if the last query actually failed.
	 *
	 * @param string $what Human description of the write.
	 * @return void
	 */
	public static function db( $what ) {
		global $wpdb;

		if ( ! empty( $wpdb->last_error ) ) {
			self::error( 'db', $what, array( 'db' => $wpdb->last_error ) );
		}
	}

	/**
	 * Append one entry.
	 *
	 * @param string $level   error or warning.
	 * @param string $source  Area.
	 * @param string $message What happened.
	 * @param array  $context Extra scalar details.
	 * @return void
	 */
	public static function record( $level, $source, $message, array $context = array() ) {
		$message = Text::truncate( self::redact( wp_strip_all_tags( (string) $message ) ), 400, '…' );
		$details = array();

		foreach ( $context as $key => $value ) {
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				$details[ sanitize_key( $key ) ] = Text::truncate( self::redact( wp_strip_all_tags( (string) $value ) ), 300, '…' );
			}
		}

		$entries = self::all();
		$now     = time();
		$last    = $entries ? $entries[0] : null;

		// A provider that is down fails on every chat turn; one row with a
		// counter says that better than a hundred identical rows.
		if ( $last && $last['message'] === $message && $last['source'] === $source && $now - (int) $last['ts'] < self::DEDUPE_WINDOW ) {
			$entries[0]['count'] = (int) $last['count'] + 1;
			$entries[0]['ts']    = $now;
			update_option( self::OPTION, $entries, false );

			return;
		}

		array_unshift(
			$entries,
			array(
				'ts'      => $now,
				'time'    => current_time( 'mysql' ),
				'level'   => 'warning' === $level ? 'warning' : 'error',
				'source'  => sanitize_key( $source ),
				'message' => $message,
				'context' => $details,
				'count'   => 1,
			)
		);

		update_option( self::OPTION, array_slice( $entries, 0, self::MAX ), false );

		if ( 'error' === $level ) {
			update_option( self::UNSEEN_OPTION, min( 99, self::unseen() + 1 ), true );
		}
	}

	/**
	 * Every stored entry, newest first.
	 *
	 * @return array
	 */
	public static function all() {
		$entries = get_option( self::OPTION, array() );

		return is_array( $entries ) ? array_values( array_filter( $entries, 'is_array' ) ) : array();
	}

	/**
	 * Empty the log.
	 *
	 * @return void
	 */
	public static function clear() {
		update_option( self::OPTION, array(), false );
		self::mark_seen();
	}

	/**
	 * Errors recorded since the admin last opened the status screen.
	 *
	 * @return int
	 */
	public static function unseen() {
		return (int) get_option( self::UNSEEN_OPTION, 0 );
	}

	/**
	 * Reset the menu badge.
	 *
	 * @return void
	 */
	public static function mark_seen() {
		if ( self::unseen() ) {
			update_option( self::UNSEEN_OPTION, 0, true );
		}
	}

	/**
	 * Drop entries older than a number of days.
	 *
	 * @param int $days Age limit.
	 * @return void
	 */
	public static function prune( $days ) {
		$cutoff  = time() - max( 1, (int) $days ) * DAY_IN_SECONDS;
		$entries = self::all();
		$kept    = array();

		foreach ( $entries as $entry ) {
			if ( (int) $entry['ts'] >= $cutoff ) {
				$kept[] = $entry;
			}
		}

		if ( count( $kept ) !== count( $entries ) ) {
			update_option( self::OPTION, $kept, false );
		}
	}

	/**
	 * Remove anything that looks like a credential from a string.
	 *
	 * Provider error messages routinely echo part of the key back
	 * ("Incorrect API key provided: sk-ab…"), and a Telegram URL carries
	 * the bot token in its path.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function redact( $text ) {
		$text = (string) $text;

		$patterns = array(
			'/bot\d{5,}:[A-Za-z0-9_\-]{20,}/'                               => 'bot***',
			'/Bearer\s+[A-Za-z0-9._\-]{8,}/i'                               => 'Bearer ***',
			'/\b(sk|pk|rk|gsk|aa|xai|key|api)[-_][A-Za-z0-9_\-*.]{6,}/i'     => '$1-***',
			'/\b[A-Za-z0-9_\-]{32,}\b/'                                     => '***',
		);

		return (string) preg_replace( array_keys( $patterns ), array_values( $patterns ), $text );
	}

	/**
	 * A client IP with its last part hidden, for security entries.
	 *
	 * @param string $ip IPv4 or IPv6 address.
	 * @return string
	 */
	public static function mask_ip( $ip ) {
		$ip = (string) $ip;

		if ( false !== strpos( $ip, ':' ) ) {
			return implode( ':', array_slice( explode( ':', $ip ), 0, 3 ) ) . ':…';
		}

		return (string) preg_replace( '/\.\d+$/', '.•••', $ip );
	}

	/**
	 * Log fatal PHP errors that originate inside this plugin, so even a
	 * crash leaves a trace the owner can read and send to support.
	 *
	 * @return void
	 */
	public static function register_fatal_handler() {
		register_shutdown_function(
			static function () {
				$error = error_get_last();

				if ( ! $error || ! in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
					return;
				}

				$root = wp_normalize_path( YUNIQ_AI_PATH );
				$file = wp_normalize_path( (string) $error['file'] );

				if ( 0 !== strpos( $file, $root ) || ! function_exists( 'update_option' ) ) {
					return;
				}

				self::error(
					'system',
					'خطای PHP در افزونه: ' . $error['message'],
					array(
						'file' => substr( $file, strlen( $root ) ) . ':' . (int) $error['line'],
					)
				);
			}
		);
	}
}
