<?php
/**
 * Contract for services that bind themselves to WordPress hooks.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implemented by every service that registers actions or filters.
 */
interface HookableInterface {

	/**
	 * Attach the service's callbacks to WordPress.
	 *
	 * @return void
	 */
	public function register_hooks();
}
