<?php
/**
 * Admin-ajax handlers for the crawler.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Admin\Ajax;

use Yuniq\Ai\Admin\AdminPages;
use Yuniq\Ai\Contracts\HookableInterface;
use Yuniq\Ai\Kb\Indexer;
use Yuniq\Ai\Kb\Repository as KnowledgeBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drives the crawl in batches and clears the knowledge base.
 *
 * Every handler is registered on `wp_ajax_` only — never `nopriv` — and
 * checks both the nonce and the capability before doing any work.
 */
final class CrawlController implements HookableInterface {

	/**
	 * Nonce action shared by all crawler requests.
	 */
	const NONCE_ACTION = 'yuniq_ai_admin_nonce';

	/**
	 * Content indexer.
	 *
	 * @var Indexer
	 */
	private $indexer;

	/**
	 * Knowledge base storage.
	 *
	 * @var KnowledgeBase
	 */
	private $knowledge_base;

	/**
	 * Constructor.
	 *
	 * @param Indexer       $indexer        Content indexer.
	 * @param KnowledgeBase $knowledge_base Knowledge base storage.
	 */
	public function __construct( Indexer $indexer, KnowledgeBase $knowledge_base ) {
		$this->indexer        = $indexer;
		$this->knowledge_base = $knowledge_base;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_yuniq_ai_start_crawl', array( $this, 'start_crawl' ) );
		add_action( 'wp_ajax_yuniq_ai_crawl_batch', array( $this, 'crawl_batch' ) );
		add_action( 'wp_ajax_yuniq_ai_stop_crawl', array( $this, 'stop_crawl' ) );
		add_action( 'wp_ajax_yuniq_ai_get_crawl_status', array( $this, 'get_crawl_status' ) );
		add_action( 'wp_ajax_yuniq_ai_clear_knowledge_base', array( $this, 'clear_knowledge_base' ) );
	}

	/**
	 * Open a crawl and report how much work it found.
	 *
	 * @return void
	 */
	public function start_crawl() {
		$this->authorize();

		wp_send_json_success( $this->indexer->start() );
	}

	/**
	 * Index the next batch of an open crawl.
	 *
	 * @return void
	 */
	public function crawl_batch() {
		$this->authorize();

		$log_id = isset( $_POST['log_id'] ) ? absint( wp_unslash( $_POST['log_id'] ) ) : 0;

		if ( ! $log_id ) {
			wp_send_json_error( array( 'message' => __( 'شناسه عملیات نامعتبر است.', 'yuniq-ai' ) ), 400 );
		}

		wp_send_json_success( $this->indexer->process_batch( $log_id ) );
	}

	/**
	 * Stop an open crawl.
	 *
	 * @return void
	 */
	public function stop_crawl() {
		$this->authorize();

		$log_id = isset( $_POST['log_id'] ) ? absint( wp_unslash( $_POST['log_id'] ) ) : 0;

		if ( $log_id ) {
			$this->indexer->stop( $log_id );
		}

		wp_send_json_success( array( 'message' => __( 'ایندکس‌گذاری متوقف شد.', 'yuniq-ai' ) ) );
	}

	/**
	 * Report the latest crawl status and document count.
	 *
	 * @return void
	 */
	public function get_crawl_status() {
		$this->authorize();

		wp_send_json_success(
			array(
				'status' => $this->indexer->get_latest_status(),
				'count'  => $this->knowledge_base->get_count(),
			)
		);
	}

	/**
	 * Empty the knowledge base.
	 *
	 * @return void
	 */
	public function clear_knowledge_base() {
		$this->authorize();

		$this->knowledge_base->clear_all();

		wp_send_json_success(
			array( 'message' => __( 'پایگاه دانش پاک شد.', 'yuniq-ai' ) )
		);
	}

	/**
	 * Verify the nonce and capability, or stop the request.
	 *
	 * @return void
	 */
	private function authorize() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( AdminPages::CAPABILITY ) ) {
			wp_send_json_error(
				array( 'message' => __( 'شما اجازه انجام این کار را ندارید.', 'yuniq-ai' ) ),
				403
			);
		}
	}
}
