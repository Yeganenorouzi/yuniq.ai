<?php
/**
 * AJAX actions of the "status and errors" screen.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Admin\Ajax;

use Yuniq\Ai\Admin\AdminPages;
use Yuniq\Ai\Contracts\HookableInterface;
use Yuniq\Ai\Notifications\Dispatcher;
use Yuniq\Ai\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clears the error log and sends a test notification.
 */
final class StatusController implements HookableInterface {

	/**
	 * Outbound notifications.
	 *
	 * @var Dispatcher
	 */
	private $notifier;

	/**
	 * Constructor.
	 *
	 * @param Dispatcher $notifier Outbound notifications.
	 */
	public function __construct( Dispatcher $notifier ) {
		$this->notifier = $notifier;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_yuniq_ai_clear_log', array( $this, 'clear_log' ) );
		add_action( 'wp_ajax_yuniq_ai_test_notify', array( $this, 'test_notify' ) );
	}

	/**
	 * Empty the error log.
	 *
	 * @return void
	 */
	public function clear_log() {
		$this->authorize();

		Logger::clear();

		wp_send_json_success( array( 'message' => __( 'گزارش خطاها پاک شد.', 'yuniq-ai' ) ) );
	}

	/**
	 * Send a test message through each enabled notification channel.
	 *
	 * @return void
	 */
	public function test_notify() {
		$this->authorize();

		$results = $this->notifier->send_test();

		if ( ! $results ) {
			wp_send_json_error( array( 'message' => __( 'هیچ کانال اعلانی فعال نیست. ایمیل یا تلگرام را در تب «پشتیبانی زنده» انتخاب و ذخیره کنید.', 'yuniq-ai' ) ) );
		}

		$labels = array(
			'email'    => __( 'ایمیل', 'yuniq-ai' ),
			'telegram' => __( 'تلگرام', 'yuniq-ai' ),
		);
		$lines  = array();
		$failed = false;

		foreach ( $results as $channel => $result ) {
			$label = isset( $labels[ $channel ] ) ? $labels[ $channel ] : $channel;

			if ( true === $result ) {
				$lines[] = '✓ ' . $label . ': ' . __( 'ارسال شد', 'yuniq-ai' );
			} else {
				$failed  = true;
				$lines[] = '✕ ' . $label . ': ' . Logger::redact( $result );
			}
		}

		$payload = array( 'message' => implode( "\n", $lines ) );

		if ( $failed ) {
			wp_send_json_error( $payload );
		}

		wp_send_json_success( $payload );
	}

	/**
	 * Reject requests without a valid nonce or capability.
	 *
	 * @return void
	 */
	private function authorize() {
		check_ajax_referer( CrawlController::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( AdminPages::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'شما اجازه انجام این کار را ندارید.', 'yuniq-ai' ) ), 403 );
		}
	}
}
