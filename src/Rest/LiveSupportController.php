<?php
/**
 * Public REST endpoints for human handoff and in-chat lead capture.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Rest;

use Yuniq\Ai\Contracts\HookableInterface;
use Yuniq\Ai\LiveSupport\LeadRepository;
use Yuniq\Ai\LiveSupport\Repository as LiveSupport;
use Yuniq\Ai\Notifications\Dispatcher;
use Yuniq\Ai\Settings;
use Yuniq\Ai\Support\RateLimiter;
use Yuniq\Ai\Support\Text;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets a visitor escalate a conversation to a human, poll for the agent's
 * replies while escalated, and submit an in-chat lead-capture form.
 */
final class LiveSupportController implements HookableInterface {

	/**
	 * Longest accepted message, in characters.
	 */
	const MAX_MESSAGE_LENGTH = 2000;

	/**
	 * Escalation requests allowed per session inside the window.
	 */
	const ESCALATE_LIMIT = 5;

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
	 * Conversations and their transcript.
	 *
	 * @var LiveSupport
	 */
	private $live_support;

	/**
	 * Captured leads.
	 *
	 * @var LeadRepository
	 */
	private $leads;

	/**
	 * Outbound notifications.
	 *
	 * @var Dispatcher
	 */
	private $notifier;

	/**
	 * Request throttling.
	 *
	 * @var RateLimiter
	 */
	private $rate_limiter;

	/**
	 * Constructor.
	 *
	 * @param Settings       $settings     Plugin settings.
	 * @param LiveSupport    $live_support Conversations and transcript.
	 * @param LeadRepository $leads        Captured leads.
	 * @param Dispatcher     $notifier     Outbound notifications.
	 * @param RateLimiter    $rate_limiter Request throttling.
	 */
	public function __construct( Settings $settings, LiveSupport $live_support, LeadRepository $leads, Dispatcher $notifier, RateLimiter $rate_limiter ) {
		$this->settings     = $settings;
		$this->live_support = $live_support;
		$this->leads        = $leads;
		$this->notifier     = $notifier;
		$this->rate_limiter = $rate_limiter;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the plugin's live-support routes, under the same namespace
	 * as the chat routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			ChatController::NAMESPACE_V1,
			'/escalate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_escalate' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'session_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'name'       => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'contact'    => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			ChatController::NAMESPACE_V1,
			'/lead',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_lead' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'session_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'form_key'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'fields'     => array(
						'required' => false,
						'type'     => 'object',
					),
				),
			)
		);

		register_rest_route(
			ChatController::NAMESPACE_V1,
			'/conversation/(?P<session_id>[A-Za-z0-9\-]+)/messages',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'handle_get_messages' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'after_id' => array(
							'required' => false,
							'type'     => 'integer',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_post_message' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'message' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);
	}

	/**
	 * A visitor asked to talk to a human (button click, or the AI signaled
	 * `needs_human`).
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function handle_escalate( WP_REST_Request $request ) {
		if ( ! $this->settings->get( 'live_support_enabled' ) ) {
			return $this->error( __( 'پشتیبانی زنده در حال حاضر فعال نیست.', 'yuniq-ai' ), 403 );
		}

		$session_id = (string) $request->get_param( 'session_id' );

		if ( '' === $session_id ) {
			return $this->error( __( 'شناسه گفتگو نامعتبر است.', 'yuniq-ai' ), 400 );
		}

		if ( $this->rate_limiter->hit( 'handoff:' . $session_id, self::ESCALATE_LIMIT, self::RATE_WINDOW ) ) {
			return $this->error( __( 'تعداد درخواست‌ها زیاد است. لطفاً کمی صبر کنید.', 'yuniq-ai' ), 429 );
		}

		$conversation = $this->live_support->escalate(
			$session_id,
			(string) $request->get_param( 'name' ),
			(string) $request->get_param( 'contact' )
		);

		$this->notifier->notify_new_escalation( $conversation );

		return new WP_REST_Response(
			array(
				'success'       => true,
				'status'        => $conversation['status'],
				'agent_name'    => $this->settings->get( 'agent_display_name' ),
			),
			200
		);
	}

	/**
	 * A visitor submitted one of the admin-configured in-chat forms.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function handle_lead( WP_REST_Request $request ) {
		$session_id = (string) $request->get_param( 'session_id' );
		$form_key   = (string) $request->get_param( 'form_key' );
		$submitted  = (array) $request->get_param( 'fields' );

		$form = $this->find_form( $form_key );

		if ( ! $form ) {
			return $this->error( __( 'فرم موردنظر یافت نشد.', 'yuniq-ai' ), 404 );
		}

		$fields = array();

		foreach ( $form['fields'] as $field ) {
			$value = isset( $submitted[ $field['name'] ] ) ? trim( (string) $submitted[ $field['name'] ] ) : '';

			if ( ! empty( $field['required'] ) && '' === $value ) {
				return $this->error(
					/* translators: %s: field label. */
					sprintf( __( 'وارد کردن «%s» الزامی است.', 'yuniq-ai' ), $field['label'] ),
					400
				);
			}

			$fields[ $field['name'] ] = 'email' === $field['type'] ? sanitize_email( $value ) : sanitize_text_field( $value );
		}

		$this->leads->insert( $session_id, $form_key, $fields );
		$this->notifier->notify_new_lead(
			array(
				'session_id' => $session_id,
				'form_key'   => $form_key,
				'fields'     => $fields,
			)
		);

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Poll for new messages (agent replies) on an escalated conversation.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function handle_get_messages( WP_REST_Request $request ) {
		$session_id = (string) $request->get_param( 'session_id' );
		$after_id   = absint( $request->get_param( 'after_id' ) );

		$conversation = $this->live_support->get_conversation( $session_id );
		$status       = $conversation ? $conversation['status'] : 'bot';

		if ( $conversation && 'active' === $status ) {
			$this->live_support->mark_read_visitor( $session_id );
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'status'   => $status,
				'messages' => $this->live_support->get_messages( $session_id, $after_id ),
			),
			200
		);
	}

	/**
	 * A visitor sent a message while escalated — routed to the agent, not
	 * back through the AI.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function handle_post_message( WP_REST_Request $request ) {
		$session_id = (string) $request->get_param( 'session_id' );
		$message    = (string) $request->get_param( 'message' );

		if ( '' === trim( $message ) || Text::length( $message ) > self::MAX_MESSAGE_LENGTH ) {
			return $this->error( __( 'پیام نامعتبر است.', 'yuniq-ai' ), 400 );
		}

		$conversation = $this->live_support->get_conversation( $session_id );

		if ( ! $conversation || ! in_array( $conversation['status'], array( 'pending', 'active' ), true ) ) {
			return $this->error( __( 'این گفتگو دیگر با کارشناس باز نیست.', 'yuniq-ai' ), 409 );
		}

		if ( $this->rate_limiter->hit( 'handoff-msg:' . $session_id, 40, self::RATE_WINDOW ) ) {
			return $this->error( __( 'تعداد پیام‌ها زیاد است. لطفاً کمی صبر کنید.', 'yuniq-ai' ), 429 );
		}

		$this->live_support->add_message( $session_id, 'user', $message );

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Find a configured lead form by key.
	 *
	 * @param string $form_key Form key.
	 * @return array|null
	 */
	private function find_form( $form_key ) {
		foreach ( (array) $this->settings->get( 'lead_forms', array() ) as $form ) {
			if ( isset( $form['key'] ) && $form['key'] === $form_key ) {
				return $form;
			}
		}

		return null;
	}

	/**
	 * Build a failure response.
	 *
	 * @param string $message Message shown to the visitor.
	 * @param int    $status  HTTP status code.
	 * @return WP_REST_Response
	 */
	private function error( $message, $status ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'error'   => $message,
			),
			$status
		);
	}
}
