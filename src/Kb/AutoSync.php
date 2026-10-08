<?php
/**
 * Keeps the knowledge base in step with content edits.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Kb;

use Yuniq\Ai\Contracts\HookableInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Re-indexes a single post when it is saved and forgets it when it goes
 * away, so the assistant never quotes a stale price or a deleted page
 * while waiting for the next full crawl.
 */
final class AutoSync implements HookableInterface {

	/**
	 * Content indexer.
	 *
	 * @var Indexer
	 */
	private $indexer;

	/**
	 * Knowledge base storage.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Post ids waiting to be synced at the end of the request.
	 *
	 * @var array<int,bool>
	 */
	private $queue = array();

	/**
	 * Constructor.
	 *
	 * @param Indexer    $indexer    Content indexer.
	 * @param Repository $repository Knowledge base storage.
	 */
	public function __construct( Indexer $indexer, Repository $repository ) {
		$this->indexer    = $indexer;
		$this->repository = $repository;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks() {
		add_action( 'wp_after_insert_post', array( $this, 'enqueue' ), 20 );
		add_action( 'woocommerce_update_product', array( $this, 'enqueue' ), 20 );
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'enqueue' ), 20 );
		add_action( 'trashed_post', array( $this->indexer, 'forget_post' ) );
		add_action( 'deleted_post', array( $this->indexer, 'forget_post' ) );
		add_action( 'shutdown', array( $this, 'flush' ) );
	}

	/**
	 * Remember a post to sync once the request has finished its own work.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public function enqueue( $post_id ) {
		$post_id = (int) $post_id;

		if ( $post_id <= 0 || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$this->queue[ $post_id ] = true;
	}

	/**
	 * Sync every queued post. Runs on `shutdown`, after the save is
	 * complete, and only once the site has an index to keep up to date.
	 *
	 * @return void
	 */
	public function flush() {
		if ( ! $this->queue ) {
			return;
		}

		$ids         = array_keys( $this->queue );
		$this->queue = array();

		if ( 0 === $this->repository->get_count() ) {
			return;
		}

		foreach ( array_slice( $ids, 0, 20 ) as $post_id ) {
			$this->indexer->sync_post( $post_id );
		}
	}
}
