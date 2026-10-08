<?php
/**
 * Outbound notifications for live-support escalations and captured leads.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Notifications;

use Yuniq\Ai\Settings;
use Yuniq\Ai\Support\Logger;
use Yuniq\Ai\Support\Text;

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

		if ( ! empty( $lead['form_title'] ) ) {
			$lines[] = sprintf( 'فرم: %s', $lead['form_title'] );
		}

		foreach ( (array) $lead['fields'] as $name => $value ) {
			$lines[] = sprintf( '%s: %s', $name, $value );
		}
		$lines[] = admin_url( 'admin.php?page=yuniq-ai-live-support' );

		$this->dispatch( $subject, implode( "\n", $lines ), $lead );
	}

	/**
	 * Send a test message through every enabled channel.
	 *
	 * @return array<string,true|string> Channel => true, or why it failed.
	 */
	public function send_test() {
		return $this->dispatch(
			__( 'پیام آزمایشی دستیار هوشمند', 'yuniq-ai' ),
			sprintf( "سایت: %s\nاگر این پیام را می‌بینید، اعلان‌ها درست کار می‌کنند.", home_url() ),
			array( 'test' => true )
		);
	}

	/**
	 * Fan a notification out to every enabled channel.
	 *
	 * @param string $subject Short subject line.
	 * @param string $message Plain-text body.
	 * @param array  $context The conversation or lead that triggered it.
	 * @return array<string,true|string> Channel => true, or why it failed.
	 */
	private function dispatch( $subject, $message, array $context ) {
		$default_channels = array(
			'email'    => array( $this, 'send_email' ),
			'telegram' => array( $this, 'send_telegram' ),
		);

		/**
		 * Filters the available notification channels.
		 *
		 * @param array<string,callable> $channels Channel key => sender.
		 * @param array                  $context  What triggered the notification.
		 */
		$channels = apply_filters( 'yuniq_ai_notify_channels', $default_channels, $context );
		$enabled  = (array) $this->settings->get( 'notify_channels', array() );
		$results  = array();

		foreach ( $enabled as $key ) {
			if ( ! isset( $channels[ $key ] ) || ! is_callable( $channels[ $key ] ) ) {
				continue;
			}

			$result          = call_user_func( $channels[ $key ], $subject, $message );
			$results[ $key ] = ( true === $result || null === $result ) ? true : (string) $result;

			if ( true !== $results[ $key ] ) {
				Logger::error( 'notify', sprintf( 'اعلان از کانال «%s» ارسال نشد: %s', $key, $results[ $key ] ) );
			}
		}

		return $results;
	}

	/**
	 * Email channel.
	 *
	 * @param string $subject Subject line.
	 * @param string $message Plain-text body.
	 * @return true|string True, or the reason it was not sent.
	 */
	private function send_email( $subject, $message ) {
		$to = sanitize_email( (string) $this->settings->get( 'notify_email' ) );

		if ( ! $to ) {
			return __( 'ایمیل دریافت اعلان تنظیم نشده است.', 'yuniq-ai' );
		}

		// Visitor-supplied text is in the body: one line break style, no headers.
		return wp_mail( $to, wp_strip_all_tags( $subject ), $message )
			? true
			: __( 'وردپرس نتوانست ایمیل را ارسال کند. تنظیمات ایمیل هاست یا افزونه SMTP را بررسی کنید.', 'yuniq-ai' );
	}

	/**
	 * Telegram channel.
	 *
	 * @param string $subject Subject line.
	 * @param string $message Plain-text body.
	 * @return true|string True, or the reason it was not sent.
	 */
	private function send_telegram( $subject, $message ) {
		$token   = (string) $this->settings->get( 'telegram_bot_token' );
		$chat_id = (string) $this->settings->get( 'telegram_chat_id' );

		if ( '' === $token || '' === $chat_id ) {
			return __( 'توکن ربات یا شناسه چت تلگرام وارد نشده است.', 'yuniq-ai' );
		}

		$response = wp_remote_post(
			'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage',
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'body'        => array(
					'chat_id' => $chat_id,
					'text'    => Text::truncate( $subject . "\n\n" . $message, 3500, '…' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return __( 'اتصال به تلگرام برقرار نشد (از هاست ایران معمولاً مسدود است):', 'yuniq-ai' ) . ' ' . $response->get_error_message();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			return sprintf( 'تلگرام خطای %d داد: %s', $code, isset( $body['description'] ) ? $body['description'] : '' );
		}

		return true;
	}
}
