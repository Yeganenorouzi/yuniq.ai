<?php
/**
 * Transient-backed request throttling.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts requests per bucket inside a rolling window.
 *
 * The chat endpoint is public and spends the site owner's API credit, so
 * it is throttled on the client IP as well as the session id. The session
 * id is supplied by the browser and can be rotated at will; the IP bucket
 * is what actually bounds the cost of an abusive client.
 */
final class RateLimiter {

	/**
	 * Transient key prefix.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Constructor.
	 *
	 * @param string $prefix Transient key prefix.
	 */
	public function __construct( $prefix = 'yuniq_ai_rl_' ) {
		$this->prefix = $prefix;
	}

	/**
	 * Record a hit and report whether the bucket is now over its limit.
	 *
	 * @param string $bucket Bucket identifier, e.g. an IP or session id.
	 * @param int    $limit  Maximum hits allowed inside the window.
	 * @param int    $window Window length in seconds.
	 * @return bool True when the caller should be refused.
	 */
	public function hit( $bucket, $limit, $window ) {
		$key   = $this->prefix . md5( (string) $bucket );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return true;
		}

		set_transient( $key, $count + 1, $window );

		return false;
	}

	/**
	 * Best-effort client IP.
	 *
	 * Only `REMOTE_ADDR` is trusted by default: forwarded headers are
	 * attacker-controlled unless a known proxy sits in front of the site.
	 * Sites behind Cloudflare or a load balancer can opt in with the
	 * `yuniq_ai_client_ip` filter.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? (string) rest_is_ip_address( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		/**
		 * Filters the IP used for rate limiting.
		 *
		 * @param string $ip Resolved client IP, empty when undetermined.
		 */
		return (string) apply_filters( 'yuniq_ai_client_ip', $ip );
	}
}
