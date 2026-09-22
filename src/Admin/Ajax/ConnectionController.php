<?php
/**
 * Admin-ajax handlers for checking the AI connection from the settings screen.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Admin\Ajax;

use Yuniq\Ai\Admin\AdminPages;
use Yuniq\Ai\Ai\Provider\OpenAiProvider;
use Yuniq\Ai\Contracts\HookableInterface;
use Yuniq\Ai\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tests the credentials typed into the form — before they are saved — so
 * the site owner finds out a key or endpoint is wrong right away, not
 * from a visitor.
 */
final class ConnectionController implements HookableInterface {

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_yuniq_ai_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_yuniq_ai_list_models', array( $this, 'list_models' ) );
	}

	/**
	 * Send one tiny prompt with the submitted credentials.
	 *
	 * @return void
	 */
	public function test_connection() {
		$this->authorize();

		$model    = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked in authorize().
		$started  = microtime( true );
		$provider = $this->provider_from_request();
		$result   = $provider->generate(
			array(
				array(
					'role'    => 'user',
					'content' => 'Reply with exactly: OK',
				),
			),
			array(
				'model'       => $model,
				'temperature' => 0,
				'max_tokens'  => 16,
				'timeout'     => 30,
			)
		);

		if ( empty( $result['success'] ) ) {
			wp_send_json_error(
				array(
					'message' => isset( $result['error'] ) ? $result['error'] : __( 'خطای ناشناخته.', 'yuniq-ai' ),
					'url'     => $provider->base_url(),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: model name, 2: response time in seconds. */
					__( 'اتصال برقرار است. مدل %1$s در %2$s ثانیه پاسخ داد.', 'yuniq-ai' ),
					$result['model'],
					number_format_i18n( microtime( true ) - $started, 1 )
				),
				'reply'   => $result['content'],
			)
		);
	}

	/**
	 * List the models the submitted key can use.
	 *
	 * @return void
	 */
	public function list_models() {
		$this->authorize();

		$result = $this->provider_from_request()->list_models();

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}

		wp_send_json_success( array( 'models' => $result['models'] ) );
	}

	/**
	 * Provider built from the form values, falling back to the stored key
	 * when the field still carries the "unchanged" placeholder.
	 *
	 * @return OpenAiProvider
	 */
	private function provider_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification -- checked in authorize().
		$key      = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';
		$endpoint = isset( $_POST['endpoint'] ) ? esc_url_raw( trim( wp_unslash( $_POST['endpoint'] ) ) ) : '';
		// phpcs:enable

		if ( '' === $key || Settings::SECRET_MASK === $key ) {
			$key = (string) $this->settings->get( 'api_key', '' );
		}

		return new OpenAiProvider( $key, $endpoint ? $endpoint : (string) $this->settings->get( 'api_endpoint', '' ) );
	}

	/**
	 * Reject requests without a valid nonce or the required capability.
	 *
	 * @return void
	 */
	private function authorize() {
		check_ajax_referer( CrawlController::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( AdminPages::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'yuniq-ai' ) ), 403 );
		}
	}
}
