<?php
/**
 * Outbound notifications for live-support escalations and captured leads.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Notifications;

use Yuniq\Ai\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fans a short admin-facing message out to whichever channels are enabled.
 *
 * Only email and Telegram ship today. Third parties (or a future built-in
 * channel such as Bale) can add more without touching this class via the
 * `yuniq_ai_notify_channels` filter.
 */
final class Dispatcher {

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
	 * A visitor asked to talk to a human, or the AI could not help.
	 *
	 * @param array $conversation Row from LiveSupport\Repository::escalate().
	 * @return void
	 */
	public function notify_new_escalation( array $conversation ) {
		if ( ! $this->settings->get( 'live_support_enabled' ) ) {
			return;
		}

		$subject = __( 'یک بازدیدکننده درخواست صحبت با کارشناس را دارد', 'yuniq-ai' );

		$lines   = array( sprintf( 'سایت: %s', home_url() ) );
		if ( ! empty( $conversation['visitor_name'] ) ) {
			$lines[] = sprintf( 'نام: %s', $conversation['visitor_name'] );
		}
		if ( ! empty( $conversation['visitor_contact'] ) ) {
			$lines[] = sprintf( 'تماس: %s', $conversation['visitor_contact'] );
		}
		$lines[] = sprintf( 'شناسه گفتگو: %s', $conversation['session_id'] );
		$lines[] = admin_url( 'admin.php?page=yuniq-ai-live-support' );

		$this->dispatch( $subject, implode( "\n", $lines ), $conversation );
	}

	/**
	 * A visitor submitted an in-chat lead-capture form.
	 *
	 * @param array $lead Row shape: session_id, form_key, fields (assoc array).
	 * @return void
	 */
	public function notify_new_lead( array $lead ) {
		$subject = __( 'یک سرنخ جدید از دستیار هوشمند ثبت شد', 'yuniq-ai' );

		$lines = array( sprintf( 'سایت: %s', home_url() ) );
		foreach ( (array) $lead['fields'] as $name => $value ) {
			$lines[] = sprintf( '%s: %s', $name, $value );
		}
		$lines[] = admin_url( 'admin.php?page=yuniq-ai-live-support' );

		$this->dispatch( $subject, implode( "\n", $lines ), $lead );
	}

	/**
	 * Send through every channel the admin has enabled.
	 *
	 * @param string $subject Short summary line.
	 * @param string $message Full body.
	 * @param array  $context Passed through to the `yuniq_ai_notify_channels` filter.
	 * @return void
	 */
	private function dispatch( $subject, $message, array $context ) {
		$default_channels = array(
			'email'    => array( $this, 'send_email' ),
			'telegram' => array( $this, 'send_telegram' ),
		);

		/**
		 * Filters the available notification channels.
		 *
		 * @param array<string,callable> $channels Channel key => callable( $subject, $message ).
		 * @param array                  $context  The escalation or lead being notified about.
		 */
		$channels = apply_filters( 'yuniq_ai_notify_channels', $default_channels, $context );
		$enabled  = (array) $this->settings->get( 'notify_channels', array() );

		foreach ( $enabled as $key ) {
			if ( isset( $channels[ $key ] ) && is_callable( $channels[ $key ] ) ) {
				call_user_func( $channels[ $key ], $subject, $message );
			}
		}
	}

	/**
	 * @param string $subject Email subject.
	 * @param string $message Email body.
	 * @return void
	 */
	private function send_email( $subject, $message ) {
		$to = $this->settings->get( 'notify_email' );

		if ( ! $to ) {
			return;
		}

		wp_mail( $to, $subject, $message );
	}

	/**
	 * @param string $subject Prefixed onto the Telegram message as a heading.
	 * @param string $message Message body.
	 * @return void
	 */
	private function send_telegram( $subject, $message ) {
		$token   = $this->settings->get( 'telegram_bot_token' );
		$chat_id = $this->settings->get( 'telegram_chat_id' );

		if ( ! $token || ! $chat_id ) {
			return;
		}

		wp_remote_post(
			'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage',
			array(
				'timeout' => 10,
				'body'    => array(
					'chat_id' => $chat_id,
					'text'    => $subject . "\n\n" . $message,
				),
			)
		);
	}
}
