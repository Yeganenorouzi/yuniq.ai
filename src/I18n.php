<?php
/**
 * Translation loading.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai;

use Yuniq\Ai\Contracts\HookableInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the plugin text domain.
 */
final class I18n implements HookableInterface {

	/**
	 * Text domain shared by every translatable string in the plugin.
	 */
	const TEXT_DOMAIN = 'yuniq-ai';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks() {
		// WordPress 6.7+ expects translations to load on `init`, not earlier.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load the .mo file for the active locale.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			self::TEXT_DOMAIN,
			false,
			dirname( YUNIQ_AI_BASENAME ) . '/languages'
		);
	}
}
