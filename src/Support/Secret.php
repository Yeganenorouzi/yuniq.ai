<?php
/**
 * Encryption at rest for stored credentials.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encrypts the API key and the Telegram token before they are written to
 * the options table.
 *
 * The key is derived from the site's secret salts in wp-config.php, which
 * are not in the database. A leaked database dump, a backup file or an SQL
 * injection in some other plugin therefore no longer hands over a usable
 * key. Values saved by older versions are plain text and are still read.
 */
final class Secret {

	/**
	 * Marks a stored value as encrypted by this class.
	 */
	const PREFIX = 'yq1:';

	/**
	 * Cipher used.
	 */
	const CIPHER = 'aes-256-gcm';

	/**
	 * Whether this server can encrypt at all.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'openssl_encrypt' )
			&& function_exists( 'random_bytes' )
			&& in_array( self::CIPHER, openssl_get_cipher_methods(), true );
	}

	/**
	 * Whether a stored value is already encrypted.
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	public static function is_encrypted( $value ) {
		return is_string( $value ) && 0 === strpos( $value, self::PREFIX );
	}

	/**
	 * Encrypt a credential for storage.
	 *
	 * @param string $plain Credential.
	 * @return string Encrypted value, or the input when it is empty,
	 *                already encrypted, or encryption is unavailable.
	 */
	public static function encrypt( $plain ) {
		$plain = (string) $plain;

		if ( '' === $plain || self::is_encrypted( $plain ) || ! self::available() ) {
			return $plain;
		}

		try {
			$iv = random_bytes( 12 );
		} catch ( \Exception $e ) {
			return $plain;
		}

		$tag    = '';
		$cipher = openssl_encrypt( $plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $cipher ) {
			return $plain;
		}

		return self::PREFIX . base64_encode( $iv . $tag . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- binary-safe storage, not obfuscation.
	}

	/**
	 * Read a stored credential.
	 *
	 * @param mixed $stored Stored value.
	 * @return string Empty when it cannot be decrypted, e.g. the salts in
	 *                wp-config.php changed since it was saved.
	 */
	public static function decrypt( $stored ) {
		if ( ! self::is_encrypted( $stored ) ) {
			return is_string( $stored ) ? $stored : '';
		}

		if ( ! self::available() ) {
			return '';
		}

		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions

		if ( false === $raw || strlen( $raw ) < 29 ) {
			return '';
		}

		$plain = openssl_decrypt( substr( $raw, 28 ), self::CIPHER, self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );

		return false === $plain ? '' : $plain;
	}

	/**
	 * 256-bit key derived from the site's salts.
	 *
	 * @return string Raw binary key.
	 */
	private static function key() {
		return hash( 'sha256', 'yuniq-ai|' . wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ), true );
	}
}
