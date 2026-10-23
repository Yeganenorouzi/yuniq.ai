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
 * Email, Telegram and Bale ship today. Third parties can add more without
 * touching this class via the `yuniq_ai_notify_channels` filter.
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
	 * Whether each built-in channel is actually configured.
	 *
	 * Shared with Admin\Health so the "وضعیت و خطاها" screen reports the
	 * exact same thing dispatch() is about to do, instead of a separate
	 * guess that can drift out of sync with it.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return array<string,bool>
	 */
	public static function channel_readiness( Settings $settings ) {
		return array(
			'email'    => '' !== (string) $settings->get( 'notify_email' ),
			'telegram' => '' !== (string) $settings->get( 'telegram_bot_token' ) && '' !== (string) $settings->get( 'telegram_chat_id' ),
			'bale'     => '' !== (string) $settings->get( 'bale_bot_token' ) && '' !== (string) $settings->get( 'bale_chat_id' ),
		);
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
	 * Send a test message through every known channel.
	 *
	 * Unlike a real escalation or lead, a test also reports a channel the
	 * owner never configured (e.g. Telegram, on a site that only wants
	 * email) — but tagged as merely unconfigured rather than failed, so
	 * the status screen does not read as a red "something is broken" when
	 * nothing actually is.
	 *
	 * @return array<string,array{ok:bool,configured:bool,message:string}>
	 */
	public function send_test() {
		$ready   = self::channel_readiness( $this->settings );
		$results = $this->dispatch(
			__( 'پیام آزمایشی دستیار هوشمند', 'yuniq-ai' ),
			sprintf( "سایت: %s\nاگر این پیام را می‌بینید، اعلان‌ها درست کار می‌کنند.", home_url() ),
			array( 'test' => true )
		);

		$report = array();

		foreach ( $results as $channel => $result ) {
			$report[ $channel ] = array(
				'ok'         => true === $result,
				// A channel this plugin has no readiness rule for (added by
				// the yuniq_ai_notify_channels filter) is assumed configured
				// — it is the filter's job to report its own setup state.
				'configured' => isset( $ready[ $channel ] ) ? $ready[ $channel ] : true,
				'message'    => true === $result ? __( 'ارسال شد', 'yuniq-ai' ) : (string) $result,
			);
		}

		return $report;
	}

	/**
	 * Fan a notification out to every configured channel.
	 *
	 * A channel used to need a separate "enable" checkbox, ticked in
	 * addition to filling in its address/token — easy to fill in the
	 * fields and still miss the checkbox, which then silently dropped
	 * every notification with nothing in the UI to explain why. A channel
	 * now fires as soon as it is actually configured: email always has a
	 * destination (`notify_email` falls back to the site's admin email, so
	 * it is never really empty), and Telegram is ready the moment both its
	 * bot token and chat id are filled in. An explicit test still tries
	 * every channel the filter below knows about, so a half-finished one
	 * (e.g. a chat id with no bot token yet) still gets a clear answer
	 * instead of being silently skipped.
	 *
	 * @param string $subject Short subject line.
	 * @param string $message Plain-text body.
	 * @param array  $context The conversation, lead, or `['test' => true]`.
	 * @return array<string,true|string> Channel => true, or why it failed.
	 */
	private function dispatch( $subject, $message, array $context ) {
		$default_channels = array(
			'email'    => array( $this, 'send_email' ),
			'telegram' => array( $this, 'send_telegram' ),
			'bale'     => array( $this, 'send_bale' ),
		);

		/**
		 * Filters the available notification channels.
		 *
		 * @param array<string,callable> $channels Channel key => sender.
		 * @param array                  $context  What triggered the notification.
		 */
		$channels = apply_filters( 'yuniq_ai_notify_channels', $default_channels, $context );
		$ready    = self::channel_readiness( $this->settings );
		$results  = array();

		foreach ( $channels as $key => $callback ) {
			if ( ! is_callable( $callback ) ) {
				continue;
			}

			// A real escalation or lead skips a channel nobody set up,
			// rather than writing "not configured" to the error log every
			// single time; an explicit test still reports it, so the owner
			// sees exactly what is missing.
			if ( empty( $context['test'] ) && isset( $ready[ $key ] ) && ! $ready[ $key ] ) {
				continue;
			}

			$result          = call_user_func( $callback, $subject, $message );
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

	/**
	 * Bale channel.
	 *
	 * Bale (پیام‌رسان بله) runs a bot API that deliberately mirrors
	 * Telegram's — same request shape, same `sendMessage` method — just on
	 * its own host, so this is Bale's equivalent of send_telegram() above.
	 *
	 * @param string $subject Subject line.
	 * @param string $message Plain-text body.
	 * @return true|string True, or the reason it was not sent.
	 */
	private function send_bale( $subject, $message ) {
		$token   = (string) $this->settings->get( 'bale_bot_token' );
		$chat_id = (string) $this->settings->get( 'bale_chat_id' );

		if ( '' === $token || '' === $chat_id ) {
			return __( 'توکن ربات یا شناسه چت بله وارد نشده است.', 'yuniq-ai' );
		}

		$response = wp_remote_post(
			'https://tapi.bale.ai/bot' . rawurlencode( $token ) . '/sendMessage',
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
			return __( 'اتصال به بله برقرار نشد:', 'yuniq-ai' ) . ' ' . $response->get_error_message();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			return sprintf( 'بله خطای %d داد: %s', $code, isset( $body['description'] ) ? $body['description'] : '' );
		}

		return true;
	}
}
