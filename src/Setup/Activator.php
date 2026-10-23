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
	 * Option that tells the admin screen to send a first-time activator
	 * straight to the setup guide, instead of leaving them on the plugins
	 * list to find it themselves.
	 */
	const REDIRECT_OPTION = 'yuniq_ai_activation_redirect';

	/**
	 * Activation entry point.
	 *
	 * @param bool $network_wide Whether the plugin is being activated for
	 *                           the whole network. WordPress passes this
	 *                           automatically; there is no single admin
	 *                           screen to redirect to in that case.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		// Also records the schema revision, so maybe_upgrade() stays quiet.
		Schema::create();

		// Never overwrite an existing configuration on re-activation.
		$is_fresh_install = false === get_option( Settings::OPTION_KEY, false );

		if ( $is_fresh_install ) {
			add_option( Settings::OPTION_KEY, Settings::defaults() );
		}

		// Guide a brand-new install to the setup steps on its very next admin
		// request; re-activating an already configured site does not need
		// to interrupt the admin again.
		if ( $is_fresh_install && ! $network_wide ) {
			add_option( self::REDIRECT_OPTION, 1 );
		}
	}
}
