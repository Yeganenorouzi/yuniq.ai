<?php
/**
 * PSR-4 compatible autoloader.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps the plugin namespace onto the src/ directory.
 */
final class Autoloader {

	/**
	 * Namespace prefix handled by this loader.
	 */
	const PREFIX = __NAMESPACE__ . '\\';

	/**
	 * Directory the namespace maps onto.
	 *
	 * @var string
	 */
	private $base_dir;

	/**
	 * Constructor.
	 *
	 * @param string $base_dir Absolute path to the src/ directory.
	 */
	public function __construct( $base_dir ) {
		$this->base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;
	}

	/**
	 * Build a loader and register it with the SPL stack.
	 *
	 * @param string $base_dir Absolute path to the src/ directory.
	 * @return self
	 */
	public static function register( $base_dir ) {
		$loader = new self( $base_dir );
		spl_autoload_register( array( $loader, 'load' ) );

		return $loader;
	}

	/**
	 * Resolve a class name to a file and require it.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public function load( $class_name ) {
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( self::PREFIX ) );
		$path     = $this->base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
