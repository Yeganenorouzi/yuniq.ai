<?php
/**
 * Cache-busting versions for the plugin's static files.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the `ver` query argument for enqueued files.
 */
final class Assets {

	/**
	 * Version string for one plugin file.
	 *
	 * The plugin version alone is not enough: a CSS/JS change that ships
	 * without a version bump keeps the same URL, and browsers, caching
	 * plugins and CDNs keep serving the old file against the new markup.
	 * The file's modification time changes whenever the file does.
	 *
	 * @param string $relative Path relative to the plugin root, e.g. `assets/css/public.css`.
	 * @return string
	 */
	public static function version( $relative ) {
		$mtime = @filemtime( YUNIQ_AI_PATH . ltrim( $relative, '/' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a missing file just falls back to the plugin version.

		return $mtime ? YUNIQ_AI_VERSION . '.' . $mtime : YUNIQ_AI_VERSION;
	}
}
