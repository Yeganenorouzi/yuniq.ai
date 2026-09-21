<?php
/**
 * Admin-ajax handlers for the Live Support inbox.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Admin\Ajax;

use Yuniq\Ai\Admin\AdminPages;
use Yuniq\Ai\Contracts\HookableInterface;
use Yuniq\Ai\LiveSupport\LeadRepository;
use Yuniq\Ai\LiveSupport\Repository as LiveSupport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists conversations/leads and lets an admin claim, reply to or resolve
 * an escalated conversation.
 *
 * Every handler is registered on `wp_ajax_` only — never `nopriv` — and
 * checks both the nonce and the capability before doing any work.
 */
final class LiveSupportController implements HookableInterface {

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
	 * Constructor.
	 *
	 * @param LiveSupport    $live_support Conversations and transcript.
	 * @param LeadRepository $leads        Captured leads.
	 */
	public function __construct( LiveSupport $live_support, LeadRepository $leads ) {
		$this->live_support = $live_support;
		$this->leads        = $leads;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_yuniq_ai_ls_list', array( $this, 'list_conversations' ) );
		add_action( 'wp_ajax_yuniq_ai_ls_messages', array( $this, 'get_messages' ) );
		add_action( 'wp_ajax_yuniq_ai_ls_reply', array( $this, 'reply' ) );
		add_action( 'wp_ajax_yuniq_ai_ls_claim', array( $this, 'claim' ) );
		add_action( 'wp_ajax_yuniq_ai_ls_resolve', array( $this, 'resolve' ) );
		add_action( 'wp_ajax_yuniq_ai_ls_leads', array( $this, 'list_leads' ) );
	}

	/**
	 * List conversations, optionally filtered by status.
	 *
	 * @return void
	 */
	public function list_conversations() {
		$this->authorize();

		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		wp_send_json_success(
			array( 'conversations' => $this->live_support->get_conversations( $status ? $status : null ) )
		);
	}

	/**
	 * Fetch the transcript for one conversation and mark it read.
	 *
	 * @return void
	 */
	public function get_messages() {
		$this->authorize();

		$session_id = $this->required_session_id();

		$this->live_support->mark_read_admin( $session_id );

		wp_send_json_success(
			array(
				'conversation' => $this->live_support->get_conversation( $session_id ),
				'messages'     => $this->live_support->get_messages( $session_id ),
			)
		);
	}

	/**
	 * Send an agent reply. Auto-claims the conversation if it is still
	 * unclaimed, so the first admin to answer doesn't need an extra click.
	 *
	 * @return void
	 */
	public function reply() {
		$this->authorize();

		$session_id = $this->required_session_id();
		$message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( '' === trim( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'پیام نمی‌تواند خالی باشد.', 'yuniq-ai' ) ), 400 );
		}

		$conversation = $this->live_support->get_conversation( $session_id );

		if ( $conversation && 'pending' === $conversation['status'] ) {
			$this->live_support->claim( $session_id, get_current_user_id() );
		}

		$this->live_support->add_message( $session_id, 'agent', $message );

		wp_send_json_success( array( 'message' => __( 'پیام ارسال شد.', 'yuniq-ai' ) ) );
	}

	/**
	 * Claim a conversation for the current admin.
	 *
	 * @return void
	 */
	public function claim() {
		$this->authorize();

		$this->live_support->claim( $this->required_session_id(), get_current_user_id() );

		wp_send_json_success( array( 'message' => __( 'گفتگو به شما اختصاص یافت.', 'yuniq-ai' ) ) );
	}

	/**
	 * Resolve a conversation and hand it back to the AI.
	 *
	 * @return void
	 */
	public function resolve() {
		$this->authorize();

		$this->live_support->resolve( $this->required_session_id() );

		wp_send_json_success( array( 'message' => __( 'گفتگو بسته شد.', 'yuniq-ai' ) ) );
	}

	/**
	 * List recently captured leads.
	 *
	 * @return void
	 */
	public function list_leads() {
		$this->authorize();

		wp_send_json_success( array( 'leads' => $this->leads->get_recent() ) );
	}

	/**
	 * Read and validate the `session_id` POST field, or stop the request.
	 *
	 * @return string
	 */
	private function required_session_id() {
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

		if ( '' === $session_id ) {
			wp_send_json_error( array( 'message' => __( 'شناسه گفتگو نامعتبر است.', 'yuniq-ai' ) ), 400 );
		}

		return $session_id;
	}

	/**
	 * Verify the nonce and capability, or stop the request.
	 *
	 * @return void
	 */
	private function authorize() {
		check_ajax_referer( CrawlController::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( AdminPages::CAPABILITY ) ) {
			wp_send_json_error(
				array( 'message' => __( 'شما اجازه انجام این کار را ندارید.', 'yuniq-ai' ) ),
				403
			);
		}
	}
}
