<?php
/**
 * Plugin bootstrap and service wiring.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai;

use Yuniq\Ai\Admin\AdminPages;
use Yuniq\Ai\Admin\Ajax\CrawlController;
use Yuniq\Ai\Admin\Ajax\LiveSupportController as LiveSupportAjaxController;
use Yuniq\Ai\Ai\Client;
use Yuniq\Ai\Analytics\Repository as Analytics;
use Yuniq\Ai\Contracts\HookableInterface;
use Yuniq\Ai\Frontend\Widget;
use Yuniq\Ai\Kb\Indexer;
use Yuniq\Ai\Kb\Repository as KnowledgeBase;
use Yuniq\Ai\LiveSupport\LeadRepository;
use Yuniq\Ai\LiveSupport\Repository as LiveSupport;
use Yuniq\Ai\Notifications\Dispatcher;
use Yuniq\Ai\Rest\ChatController;
use Yuniq\Ai\Rest\LiveSupportController as LiveSupportRestController;
use Yuniq\Ai\Setup\Schema;
use Yuniq\Ai\Support\RateLimiter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Composition root: builds every service and binds it to WordPress.
 */
final class Plugin {

	/**
	 * The single running instance.
	 *
	 * @var self|null
	 */
	private static $instance;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Whether hooks have already been registered.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Private: use {@see Plugin::instance()}.
	 */
	private function __construct() {
		$this->container = new Container();
		$this->register_services();
	}

	/**
	 * The shared plugin instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register every hookable service with WordPress. Safe to call twice.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		// Applies pending table changes after an update, without the site
		// owner having to deactivate and reactivate the plugin. Run eagerly
		// here — not deferred to `admin_init` — because `admin_menu` (which
		// the live-support badge count relies on) fires before `admin_init`
		// on every admin request, so deferring this left a window where the
		// badge query ran against tables that did not exist yet.
		Schema::maybe_upgrade();

		foreach ( $this->hookable_services() as $id ) {
			$service = $this->container->get( $id );

			if ( $service instanceof HookableInterface ) {
				$service->register_hooks();
			}
		}

		/**
		 * Fires once the plugin's services are bound to WordPress.
		 *
		 * @param Plugin $plugin The running plugin instance.
		 */
		do_action( 'yuniq_ai_booted', $this );
	}

	/**
	 * The service container, for extensions and tests.
	 *
	 * @return Container
	 */
	public function container() {
		return $this->container;
	}

	/**
	 * Services that attach themselves to hooks.
	 *
	 * @return string[]
	 */
	private function hookable_services() {
		$services = array(
			I18n::class,
			ChatController::class,
			LiveSupportRestController::class,
			Widget::class,
		);

		if ( is_admin() ) {
			$services[] = AdminPages::class;
			$services[] = CrawlController::class;
			$services[] = LiveSupportAjaxController::class;
		}

		return $services;
	}

	/**
	 * Describe how each service is built.
	 *
	 * @return void
	 */
	private function register_services() {
		$this->container->set(
			Settings::class,
			function () {
				return new Settings();
			}
		);

		$this->container->set(
			KnowledgeBase::class,
			function () {
				return new KnowledgeBase();
			}
		);

		$this->container->set(
			Analytics::class,
			function () {
				return new Analytics();
			}
		);

		$this->container->set(
			RateLimiter::class,
			function () {
				return new RateLimiter();
			}
		);

		$this->container->set(
			I18n::class,
			function () {
				return new I18n();
			}
		);

		$this->container->set(
			LiveSupport::class,
			function () {
				return new LiveSupport();
			}
		);

		$this->container->set(
			LeadRepository::class,
			function () {
				return new LeadRepository();
			}
		);

		$this->container->set(
			Dispatcher::class,
			function ( Container $c ) {
				return new Dispatcher( $c->get( Settings::class ) );
			}
		);

		$this->container->set(
			Indexer::class,
			function ( Container $c ) {
				return new Indexer( $c->get( KnowledgeBase::class ), $c->get( Settings::class ) );
			}
		);

		$this->container->set(
			Client::class,
			function ( Container $c ) {
				return new Client( $c->get( Settings::class ), $c->get( KnowledgeBase::class ) );
			}
		);

		$this->container->set(
			ChatController::class,
			function ( Container $c ) {
				return new ChatController(
					$c->get( Settings::class ),
					$c->get( Client::class ),
					$c->get( Analytics::class ),
					$c->get( RateLimiter::class )
				);
			}
		);

		$this->container->set(
			Widget::class,
			function ( Container $c ) {
				return new Widget( $c->get( Settings::class ), $c->get( Client::class ) );
			}
		);

		$this->container->set(
			AdminPages::class,
			function ( Container $c ) {
				return new AdminPages(
					$c->get( Settings::class ),
					$c->get( KnowledgeBase::class ),
					$c->get( Indexer::class ),
					$c->get( Analytics::class ),
					$c->get( LiveSupport::class )
				);
			}
		);

		$this->container->set(
			CrawlController::class,
			function ( Container $c ) {
				return new CrawlController( $c->get( Indexer::class ), $c->get( KnowledgeBase::class ) );
			}
		);

		$this->container->set(
			LiveSupportRestController::class,
			function ( Container $c ) {
				return new LiveSupportRestController(
					$c->get( Settings::class ),
					$c->get( LiveSupport::class ),
					$c->get( LeadRepository::class ),
					$c->get( Dispatcher::class ),
					$c->get( RateLimiter::class )
				);
			}
		);

		$this->container->set(
			LiveSupportAjaxController::class,
			function ( Container $c ) {
				return new LiveSupportAjaxController( $c->get( LiveSupport::class ), $c->get( LeadRepository::class ) );
			}
		);
	}

	/**
	 * Singletons are not cloneable.
	 *
	 * @return void
	 */
	private function __clone() {}
}
