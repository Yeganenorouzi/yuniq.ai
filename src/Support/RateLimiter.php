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
		$state = get_transient( $key );
		$now   = time();

		// The window is anchored to the first request. Re-saving the counter
		// with a fresh timeout on every hit used to push the expiry forward
		// forever, so a steady visitor ended up blocked permanently.
		if ( ! is_array( $state ) || empty( $state['start'] ) || $now - (int) $state['start'] >= $window ) {
			$state = array(
				'start' => $now,
				'count' => 0,
			);
		}

		if ( (int) $state['count'] >= $limit ) {
			return true;
		}

		++$state['count'];

		set_transient( $key, $state, max( 1, $window - ( $now - (int) $state['start'] ) ) );

		return false;
	}

	/**
	 * The visitor's IP address.
	 *
	 * Only `REMOTE_ADDR` cannot be forged by the visitor, so it is the
	 * default. A site behind Cloudflare or another reverse proxy sees the
	 * proxy's address there for everyone — all visitors would then share
	 * one rate-limit bucket — so the owner can name the header their
	 * proxy sets. That header is only trustworthy when requests really do
	 * arrive through that proxy, which is why it is opt-in.
	 *
	 * @param string $source remote_addr, cloudflare, forwarded or real_ip.
	 * @return string Empty when no valid address is available.
	 */
	public static function client_ip( $source = 'remote_addr' ) {
		$headers = array(
			'cloudflare' => 'HTTP_CF_CONNECTING_IP',
			'forwarded'  => 'HTTP_X_FORWARDED_FOR',
			'real_ip'    => 'HTTP_X_REAL_IP',
		);

		$ip = '';

		if ( isset( $headers[ $source ], $_SERVER[ $headers[ $source ] ] ) ) {
			// X-Forwarded-For is a list; the first entry is the client.
			$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $headers[ $source ] ] ) ) );
			$ip    = (string) rest_is_ip_address( trim( $parts[0] ) );
		}

		if ( '' === $ip && isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = (string) rest_is_ip_address( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) );
		}

		/**
		 * Filters the client IP used for rate limiting.
		 *
		 * @param string $ip     Detected address.
		 * @param string $source Configured source.
		 */
		return (string) apply_filters( 'yuniq_ai_client_ip', $ip, $source );
	}
}
