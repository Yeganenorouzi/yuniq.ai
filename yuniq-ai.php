<?php
/**
 * Plugin Name:       Yuniq.ai
 * Plugin URI:        https://github.com/Yeganenorouzi/yuniq-ai
 * Description:       دستیار هوشمند وب‌سایت شما: ویجت شناور گفتگو با هوش مصنوعی، پایگاه دانش خودکار از محتوای سایت، پشتیبانی کامل فارسی و RTL. — Turn any WordPress site into an AI assistant that answers from your own content.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Yegane Norouzi (یگانه نوروزی)
 * Author URI:        https://github.com/Yeganenorouzi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       yuniq-ai
 * Domain Path:       /languages
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

use Yuniq\Ai\Autoloader;
use Yuniq\Ai\Plugin;
use Yuniq\Ai\Setup\Activator;
use Yuniq\Ai\Setup\Deactivator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version. Kept in step with the header above and readme.txt.
 */
define( 'YUNIQ_AI_VERSION', '1.0.0' );
define( 'YUNIQ_AI_FILE', __FILE__ );
define( 'YUNIQ_AI_PATH', plugin_dir_path( __FILE__ ) );
define( 'YUNIQ_AI_URL', plugin_dir_url( __FILE__ ) );
define( 'YUNIQ_AI_BASENAME', plugin_basename( __FILE__ ) );

require_once YUNIQ_AI_PATH . 'src/Autoloader.php';
Autoloader::register( YUNIQ_AI_PATH . 'src' );

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Deactivator::class, 'deactivate' ) );

// Bind services once all plugins are available, so integrations can hook in.
add_action(
	'plugins_loaded',
	static function () {
		Plugin::instance()->boot();
	}
);
