<?php
/**
 * Public REST endpoints.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Rest;

use Yuniq\Ai\Ai\Client;
use Yuniq\Ai\Analytics\Repository as Analytics;
use Yuniq\Ai\Contracts\HookableInterface;
use Yuniq\Ai\Settings;
use Yuniq\Ai\Support\RateLimiter;
use Yuniq\Ai\Support\Text;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the widget's chat, streaming chat and configuration routes.
 */
final class ChatController implements HookableInterface {

	/**
	 * REST namespace.
	 */
	const NAMESPACE_V1 = 'yuniq-ai/v1';

	/**
	 * Longest accepted message, in characters.
	 */
	const MAX_MESSAGE_LENGTH = 2000;

	/**
	 * Requests allowed per client IP inside the window.
	 */
	const IP_LIMIT = 30;

	/**
	 * Requests allowed per session id inside the window.
	 */
	const SESSION_LIMIT = 20;

	/**
	 * Rate limit window, in seconds.
	 */
	const RATE_WINDOW = 600;

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * AI client.
	 *
	 * @var Client
	 */
	private $client;

	/**
	 * Conversation log.
	 *
	 * @var Analytics
	 */
	private $analytics;

	/**
	 * Request throttling.
	 *
	 * @var RateLimiter
	 */
	private $rate_limiter;

	/**
	 * Constructor.
	 *
	 * @param Settings    $settings     Plugin settings.
	 * @param Client      $client       AI client.
	 * @param Analytics   $analytics    Conversation log.
	 * @param RateLimiter $rate_limiter Request throttling.
	 */
	public function __construct( Settings $settings, Client $client, Analytics $analytics, RateLimiter $rate_limiter ) {
		$this->settings     = $settings;
		$this->client       = $client;
		$this->analytics    = $analytics;
		$this->rate_limiter = $rate_limiter;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the plugin's routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$chat_args = array(
			'message'    => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'session_id' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'history'    => array(
				'required' => false,
				'type'     => 'array',
			),
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat' ),
				'permission_callback' => '__return_true',
				'args'                => $chat_args,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/chat/stream',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat_stream' ),
				'permission_callback' => '__return_true',
				'args'                => $chat_args,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/config',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_public_config' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Answer a chat message in one response.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function handle_chat( WP_REST_Request $request ) {
		$guard = $this->guard( $request );

		if ( $guard instanceof WP_REST_Response ) {
			return $guard;
		}

		list( $message, $session_id, $history ) = $guard;

		$started  = microtime( true );
		$response = $this->client->generate_response( $message, $history );

		$this->log_turn( $session_id, $message, $response, microtime( true ) - $started );

		if ( empty( $response['success'] ) ) {
			return $this->error(
				isset( $response['error'] ) ? $response['error'] : __( 'پاسخی دریافت نشد.', 'yuniq-ai' ),
				500,
				$session_id,
				array( 'needs_human' => true )
			);
		}

		return new WP_REST_Response(
			array(
				'success'     => true,
				'content'     => $response['content'],
				'session_id'  => $session_id,
				'tokens'      => isset( $response['tokens'] ) ? $response['tokens'] : 0,
				'needs_human' => ! empty( $response['needs_human'] ),
				'product'     => isset( $response['product'] ) ? $response['product'] : null,
				'form_key'    => isset( $response['form_key'] ) ? $response['form_key'] : null,
			),
			200
		);
	}

	/**
	 * Answer a chat message as a server-sent event stream.
	 *
	 * Writes directly to the client and terminates the request, so the
	 * visitor sees the first words while the model is still generating
	 * instead of waiting for the whole reply.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|void
	 */
	public function handle_chat_stream( WP_REST_Request $request ) {
		$guard = $this->guard( $request );

		if ( $guard instanceof WP_REST_Response ) {
			return $guard;
		}

		list( $message, $session_id, $history ) = $guard;

		$this->open_event_stream();
		$this->send_event( array( 'type' => 'start', 'session_id' => $session_id ) );

		// Client::stream_response() holds back any `[[...]]` directive text
		// itself, so every delta reaching this callback is already safe to
		// show the visitor as-is.
		$started  = microtime( true );
		$response = $this->client->stream_response(
			$message,
			$history,
			function ( $delta ) {
				$this->send_event(
					array(
						'type'  => 'delta',
						'delta' => $delta,
					)
				);
			}
		);

		$this->log_turn( $session_id, $message, $response, microtime( true ) - $started );

		if ( empty( $response['success'] ) ) {
			$this->send_event(
				array(
					'type'  => 'error',
					'error' => isset( $response['error'] ) ? $response['error'] : __( 'پاسخی دریافت نشد.', 'yuniq-ai' ),
				)
			);
		} else {
			if ( ! empty( $response['product'] ) ) {
				$this->send_event( array( 'type' => 'product_card', 'product' => $response['product'] ) );
			}

			if ( ! empty( $response['form_key'] ) ) {
				$this->send_event( array( 'type' => 'form', 'form_key' => $response['form_key'] ) );
			}
		}

		if ( ! empty( $response['needs_human'] ) ) {
			$this->send_event( array( 'type' => 'needs_human' ) );
		}

		if ( ! empty( $response['success'] ) ) {
			$this->send_event(
				array(
					'type'   => 'done',
					'tokens' => isset( $response['tokens'] ) ? $response['tokens'] : 0,
				)
			);
		}

		exit;
	}

	/**
	 * Widget configuration safe to expose publicly.
	 *
	 * Deliberately excludes every credential and crawler setting.
	 *
	 * @return WP_REST_Response
	 */
	public function get_public_config() {
		return new WP_REST_Response(
			array(
				'enabled'           => (bool) $this->settings->get( 'enabled' ),
				'assistant_name'    => $this->settings->get( 'assistant_name' ),
				'welcome_message'   => $this->settings->get( 'welcome_message' ),
				'avatar_url'        => $this->settings->get( 'avatar_url' ),
				'logo_url'          => $this->settings->get( 'logo_url' ),
				'primary_color'     => $this->settings->get( 'primary_color' ),
				'secondary_color'   => $this->settings->get( 'secondary_color' ),
				'launcher_icon'       => $this->settings->get( 'launcher_icon' ),
				'widget_position'   => $this->settings->get( 'widget_position' ),
				'custom_position_x' => (int) $this->settings->get( 'custom_position_x' ),
				'custom_position_y' => (int) $this->settings->get( 'custom_position_y' ),
				'chat_width'        => (int) $this->settings->get( 'chat_width' ),
				'chat_height'       => (int) $this->settings->get( 'chat_height' ),
				'enable_animations' => (bool) $this->settings->get( 'enable_animations' ),
				'header_style'      => $this->settings->get( 'header_style' ),
				'streaming'         => $this->client->can_stream(),
				'quick_actions'     => (array) $this->settings->get( 'quick_actions', array() ),
			),
			200
		);
	}

	/**
	 * Shared validation for both chat routes.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return array{0:string,1:string,2:array}|WP_REST_Response Validated input, or the failure to return.
	 */
	private function guard( WP_REST_Request $request ) {
		if ( ! $this->settings->get( 'enabled' ) ) {
			return $this->error( __( 'دستیار هوشمند در حال حاضر غیرفعال است.', 'yuniq-ai' ), 403 );
		}

		$message    = (string) $request->get_param( 'message' );
		$session_id = (string) $request->get_param( 'session_id' );
		$history    = $request->get_param( 'history' );

		if ( '' === $session_id ) {
			$session_id = wp_generate_uuid4();
		}

		// Counted in characters: a byte limit would reject valid Persian
		// at roughly half the length the input field allows.
		if ( '' === trim( $message ) || Text::length( $message ) > self::MAX_MESSAGE_LENGTH ) {
			return $this->error( __( 'پیام نامعتبر است.', 'yuniq-ai' ), 400, $session_id );
		}

		if ( $this->is_rate_limited( $session_id ) ) {
			return $this->error( __( 'تعداد درخواست‌ها زیاد است. لطفاً کمی صبر کنید.', 'yuniq-ai' ), 429, $session_id );
		}

		return array( $message, $session_id, is_array( $history ) ? $history : array() );
	}

	/**
	 * Record one conversation turn.
	 *
	 * @param string $session_id Session identifier.
	 * @param string $message    The visitor's question.
	 * @param array  $response   Provider result.
	 * @param float  $elapsed    Seconds spent answering.
	 * @return void
	 */
	private function log_turn( $session_id, $message, array $response, $elapsed ) {
		$this->analytics->log(
			$session_id,
			$message,
			isset( $response['content'] ) ? $response['content'] : '',
			array(
				'response_time' => $elapsed,
				'tokens_used'   => isset( $response['tokens'] ) ? $response['tokens'] : 0,
			)
		);
	}

	/**
	 * Switch the response over to an unbuffered event stream.
	 *
	 * @return void
	 */
	private function open_event_stream() {
		// Discard anything WordPress or another plugin has buffered, or the
		// stream would not reach the browser until the request ended.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		if ( function_exists( 'ini_set' ) ) {
			@ini_set( 'zlib.output_compression', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.IniSet
			@ini_set( 'output_buffering', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.IniSet
			@ini_set( 'implicit_flush', '1' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.IniSet
		}

		ob_implicit_flush( true );

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/event-stream; charset=utf-8' );
			header( 'Cache-Control: no-cache, no-store, must-revalidate' );
			header( 'Connection: keep-alive' );
			// Stops nginx and other reverse proxies from buffering the stream.
			header( 'X-Accel-Buffering: no' );
		}

		// Keep generating even if the visitor navigates away mid-answer, so
		// the turn is still logged and the provider call completes cleanly.
		ignore_user_abort( true );
	}

	/**
	 * Write one SSE frame and push it to the client.
	 *
	 * @param array $payload Event data, serialized as JSON.
	 * @return void
	 */
	private function send_event( array $payload ) {
		echo 'data: ' . wp_json_encode( $payload ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput

		if ( ob_get_level() > 0 ) {
			@ob_flush(); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		flush();
	}

	/**
	 * Throttle on both the client IP and the supplied session id.
	 *
	 * The session id travels with the request and can be rotated freely,
	 * so the IP bucket is what actually caps what one client can spend.
	 *
	 * @param string $session_id Session identifier.
	 * @return bool
	 */
	private function is_rate_limited( $session_id ) {
		$ip = RateLimiter::client_ip();

		if ( '' !== $ip && $this->rate_limiter->hit( 'ip:' . $ip, self::IP_LIMIT, self::RATE_WINDOW ) ) {
			return true;
		}

		return $this->rate_limiter->hit( 'session:' . $session_id, self::SESSION_LIMIT, self::RATE_WINDOW );
	}

	/**
	 * Build a failure response.
	 *
	 * @param string $message    Message shown to the visitor.
	 * @param int    $status     HTTP status code.
	 * @param string $session_id Session identifier, when known.
	 * @param array  $extra      Additional fields to merge in, e.g. `needs_human`.
	 * @return WP_REST_Response
	 */
	private function error( $message, $status, $session_id = '', array $extra = array() ) {
		$payload = array(
			'success' => false,
			'error'   => $message,
		);

		if ( '' !== $session_id ) {
			$payload['session_id'] = $session_id;
		}

		return new WP_REST_Response( array_merge( $payload, $extra ), $status );
	}
}
