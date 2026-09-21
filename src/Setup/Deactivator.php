<?php
/**
 * Runs on plugin deactivation.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Setup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Releases scheduled work. Site data is kept until uninstall.
 */
final class Deactivator {

	/**
	 * Deactivation entry point.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'yuniq_ai_scheduled_crawl' );
	}
}
