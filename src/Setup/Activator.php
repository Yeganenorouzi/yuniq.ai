<?php
/**
 * Runs on plugin activation.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Setup;

use Yuniq\Ai\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the schema and seeds default settings.
 */
final class Activator {

	/**
	 * Activation entry point.
	 *
	 * @return void
	 */
	public static function activate() {
		// Also records the schema revision, so maybe_upgrade() stays quiet.
		Schema::create();

		// Never overwrite an existing configuration on re-activation.
		if ( false === get_option( Settings::OPTION_KEY, false ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults() );
		}
	}
}
