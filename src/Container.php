<?php
/**
 * Minimal service container.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lazily builds and caches the plugin's services.
 */
final class Container {

	/**
	 * Registered service factories, keyed by service id.
	 *
	 * @var callable[]
	 */
	private $factories = array();

	/**
	 * Already-built services, keyed by service id.
	 *
	 * @var array
	 */
	private $instances = array();

	/**
	 * Register a service factory.
	 *
	 * @param string   $id      Service identifier, normally a class name.
	 * @param callable $factory Receives the container, returns the service.
	 * @return self
	 */
	public function set( $id, callable $factory ) {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );

		return $this;
	}

	/**
	 * Whether a service is registered.
	 *
	 * @param string $id Service identifier.
	 * @return bool
	 */
	public function has( $id ) {
		return isset( $this->factories[ $id ] ) || isset( $this->instances[ $id ] );
	}

	/**
	 * Resolve a service, building it on first use.
	 *
	 * @param string $id Service identifier.
	 * @return mixed
	 * @throws \InvalidArgumentException When the service was never registered.
	 */
	public function get( $id ) {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Service "%s" is not registered in the container.', esc_html( $id ) )
			);
		}

		$this->instances[ $id ] = call_user_func( $this->factories[ $id ], $this );

		return $this->instances[ $id ];
	}
}
