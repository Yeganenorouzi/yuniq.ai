<?php
/**
 * Setup progress and system checks for the admin screens.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Admin;

use Yuniq\Ai\Ai\Client;
use Yuniq\Ai\Kb\Repository as KnowledgeBase;
use Yuniq\Ai\Settings;
use Yuniq\Ai\Setup\Schema;
use Yuniq\Ai\Support\Logger;
use Yuniq\Ai\Support\Secret;
use Yuniq\Ai\Support\Text;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Answers two questions for the site owner: "what is left to set up?"
 * and "is anything wrong right now?".
 */
final class Health {

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Knowledge base storage.
	 *
	 * @var KnowledgeBase
	 */
	private $knowledge_base;

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings       Plugin settings.
	 * @param KnowledgeBase $knowledge_base Knowledge base storage.
	 */
	public function __construct( Settings $settings, KnowledgeBase $knowledge_base ) {
		$this->settings       = $settings;
		$this->knowledge_base = $knowledge_base;
	}

	/**
	 * The four steps from a fresh install to a working assistant.
	 *
	 * @return array<int,array{done:bool,title:string,text:string,url:string,action:string}>
	 */
	public function setup_steps() {
		$has_key   = '' !== (string) $this->settings->get( 'api_key', '' );
		$last_ok   = (int) get_option( Client::LAST_SUCCESS_OPTION, 0 );
		$documents = $this->knowledge_base->get_count();
		$base      = admin_url( 'admin.php?page=' );

		return array(
			array(
				'done'   => $has_key,
				'title'  => 'کلید API را وارد کنید',
				'text'   => 'از یکی از سرویس‌های هوش مصنوعی کلید بگیرید و در تب «هوش مصنوعی و پاسخ‌ها» وارد کنید.',
				'url'    => $base . AdminPages::GUIDE_SLUG,
				'action' => 'راهنمای دریافت کلید',
			),
			array(
				'done'   => $has_key && $last_ok > 0,
				'title'  => 'اتصال را تست کنید',
				'text'   => 'دکمه «تست اتصال» را بزنید تا مطمئن شوید کلید و مدل درست کار می‌کنند.',
				'url'    => $base . AdminPages::MENU_SLUG . '#ai',
				'action' => 'رفتن به تست اتصال',
			),
			array(
				'done'   => $documents > 0,
				'title'  => 'محتوای سایت را ایندکس کنید',
				'text'   => 'تا دستیار محصولات، خدمات و مقالات شما را بشناسد.',
				'url'    => $base . AdminPages::KNOWLEDGE_SLUG,
				'action' => 'شروع ایندکس',
			),
			array(
				'done'   => (bool) $this->settings->get( 'enabled' ),
				'title'  => 'دستیار را روشن کنید',
				'text'   => 'کلید «فعال‌سازی دستیار» در تب «عمومی».',
				'url'    => $base . AdminPages::MENU_SLUG . '#general',
				'action' => 'رفتن به تنظیمات',
			),
		);
	}

	/**
	 * Everything worth checking, each with a verdict and what to do.
	 *
	 * @return array<int,array{status:string,title:string,text:string}>
	 */
	public function checks() {
		global $wpdb;

		$checks = array();
		$stored = get_option( Settings::OPTION_KEY, array() );
		$stored = is_array( $stored ) ? $stored : array();

		// --- AI connection -----------------------------------------------------
		$has_key  = '' !== (string) $this->settings->get( 'api_key', '' );
		$endpoint = (string) $this->settings->get( 'api_endpoint', '' );
		$host     = (string) wp_parse_url( $endpoint, PHP_URL_HOST );
		$last_ok  = (int) get_option( Client::LAST_SUCCESS_OPTION, 0 );

		if ( ! $has_key && ! empty( $stored['api_key'] ) ) {
			$checks[] = self::check( 'fail', 'کلید API قابل خواندن نیست', 'کلیدهای امنیتی وردپرس (salt) در wp-config.php عوض شده‌اند و کلید رمزنگاری‌شده دیگر باز نمی‌شود. کلید API را دوباره در تنظیمات وارد و ذخیره کنید.' );
		} elseif ( ! $has_key ) {
			$checks[] = self::check( 'fail', 'کلید API وارد نشده است', 'بدون کلید، دستیار نمی‌تواند پاسخ بدهد. از صفحه «راهنما» ببینید چطور کلید بگیرید.' );
		} else {
			$checks[] = self::check( 'ok', 'کلید API ذخیره شده است', Secret::is_encrypted( isset( $stored['api_key'] ) ? $stored['api_key'] : '' ) ? 'کلید به‌صورت رمزنگاری‌شده در پایگاه‌داده نگه‌داری می‌شود.' : 'یک بار تنظیمات را ذخیره کنید تا کلید رمزنگاری شود.' );
		}

		if ( $has_key && $last_ok ) {
			$checks[] = self::check( 'ok', 'سرویس هوش مصنوعی پاسخ می‌دهد', 'آخرین پاسخ موفق: ' . Text::human_date( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $last_ok ) ) ) . ' — مدل ' . $this->settings->get( 'model' ) );
		} elseif ( $has_key ) {
			$checks[] = self::check( 'warn', 'هنوز پاسخ موفقی از سرویس ثبت نشده', 'در تنظیمات دکمه «تست اتصال» را بزنید.' );
		}

		if ( $endpoint && 0 === stripos( $endpoint, 'http://' ) && ! in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
			$checks[] = self::check( 'fail', 'آدرس API رمزنگاری‌نشده است (http)', 'کلید API و پیام بازدیدکنندگان بدون رمز روی شبکه فرستاده می‌شود. آدرس را به https تغییر دهید.' );
		}

		// --- Server ------------------------------------------------------------
		$checks[] = version_compare( PHP_VERSION, '7.4', '>=' )
			? self::check( 'ok', 'نسخه PHP مناسب است', 'PHP ' . PHP_VERSION )
			: self::check( 'fail', 'نسخه PHP قدیمی است', 'افزونه به PHP 7.4 یا بالاتر نیاز دارد. نسخه فعلی: ' . PHP_VERSION );

		$checks[] = function_exists( 'curl_init' )
			? self::check( 'ok', 'پاسخ تدریجی (کلمه‌به‌کلمه) فعال است', 'افزونه cURL روی هاست نصب است.' )
			: self::check( 'warn', 'پاسخ‌ها یکجا نمایش داده می‌شوند', 'برای نمایش کلمه‌به‌کلمه از پشتیبانی هاست بخواهید افزونه cURL را در PHP فعال کند. دستیار بدون آن هم کار می‌کند.' );

		$checks[] = Secret::available()
			? self::check( 'ok', 'رمزنگاری کلیدها فعال است', 'کلید API و توکن تلگرام رمزنگاری‌شده ذخیره می‌شوند.' )
			: self::check( 'warn', 'رمزنگاری کلیدها در دسترس نیست', 'افزونه OpenSSL روی هاست فعال نیست، بنابراین کلید API به‌صورت متن ساده ذخیره می‌شود.' );

		if ( ! is_ssl() ) {
			$checks[] = self::check( 'warn', 'سایت بدون HTTPS باز شده است', 'گفتگوی بازدیدکنندگان بدون رمز منتقل می‌شود. برای سایت گواهی SSL فعال کنید.' );
		}

		// --- Database ----------------------------------------------------------
		$missing = array();
		foreach ( Schema::tables() as $name ) {
			$table = Schema::table( $name );
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$missing[] = $table;
			}
		}

		$checks[] = $missing
			? self::check( 'fail', 'بعضی جدول‌های پایگاه‌داده ساخته نشده‌اند', 'افزونه را یک بار غیرفعال و دوباره فعال کنید. جدول‌های ناموجود: ' . implode( '، ', $missing ) )
			: self::check( 'ok', 'جدول‌های پایگاه‌داده سالم هستند', 'هر شش جدول افزونه موجود است.' );

		// --- Knowledge base ----------------------------------------------------
		$documents = $this->knowledge_base->get_count();

		$checks[] = $documents
			? self::check( 'ok', 'پایگاه دانش آماده است', number_format_i18n( $documents ) . ' صفحه ایندکس شده است.' )
			: self::check( 'warn', 'پایگاه دانش خالی است', 'دستیار هنوز چیزی از سایت شما نمی‌داند. از صفحه «پایگاه دانش» ایندکس را اجرا کنید.' );

		// --- Widget ------------------------------------------------------------
		$checks[] = $this->settings->get( 'enabled' )
			? self::check( 'ok', 'دستیار در سایت فعال است', 'بازدیدکنندگان دکمه گفتگو را می‌بینند.' )
			: self::check( 'warn', 'دستیار خاموش است', 'تا آن را در تب «عمومی» روشن نکنید، در سایت نمایش داده نمی‌شود.' );

		// --- Live support ------------------------------------------------------
		if ( $this->settings->get( 'live_support_enabled' ) ) {
			$channels = (array) $this->settings->get( 'notify_channels', array() );

			if ( ! $channels ) {
				$checks[] = self::check( 'warn', 'هیچ کانال اعلانی انتخاب نشده', 'پشتیبانی زنده روشن است ولی از درخواست‌های جدید باخبر نمی‌شوید. ایمیل یا تلگرام را در تب «پشتیبانی زنده» فعال کنید.' );
			} elseif ( in_array( 'telegram', $channels, true ) && ( ! $this->settings->get( 'telegram_bot_token' ) || ! $this->settings->get( 'telegram_chat_id' ) ) ) {
				$checks[] = self::check( 'fail', 'اعلان تلگرام ناقص است', 'توکن ربات یا شناسه چت وارد نشده است.' );
			} else {
				$checks[] = self::check( 'ok', 'اعلان پشتیبانی زنده تنظیم شده', 'با دکمه «ارسال اعلان آزمایشی» در همین صفحه امتحانش کنید.' );
			}
		}

		// --- Protection --------------------------------------------------------
		$checks[] = (int) $this->settings->get( 'daily_limit', 0 ) > 0
			? self::check( 'ok', 'سقف روزانه پیام فعال است', 'حداکثر ' . number_format_i18n( (int) $this->settings->get( 'daily_limit' ) ) . ' پیام در روز پاسخ داده می‌شود.' )
			: self::check( 'warn', 'سقف روزانه پیام تعیین نشده', 'برای کنترل هزینه API در برابر سوءاستفاده، یک سقف روزانه در تب «امنیت» انتخاب کنید.' );

		if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && 'cloudflare' !== $this->settings->get( 'ip_source' ) ) {
			$checks[] = self::check( 'warn', 'سایت پشت Cloudflare است', 'در تب «امنیت»، «تشخیص IP بازدیدکننده» را روی «کلودفلر» بگذارید؛ وگرنه همه بازدیدکنندگان یک نفر حساب می‌شوند و زود به سقف پیام می‌رسند.' );
		}

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON && (int) $this->settings->get( 'data_retention', 0 ) > 0 ) {
			$checks[] = self::check( 'warn', 'زمان‌بند وردپرس خاموش است', 'پاک‌سازی خودکار داده‌های قدیمی فقط وقتی اجرا می‌شود که روی هاست یک cron واقعی برای wp-cron.php تنظیم شده باشد.' );
		}

		return $checks;
	}

	/**
	 * A plain-text summary the owner can paste to support. No secrets.
	 *
	 * @return string
	 */
	public function report() {
		global $wp_version;

		$lines = array(
			'Yuniq.ai ' . YUNIQ_AI_VERSION,
			'WordPress ' . $wp_version . ' | PHP ' . PHP_VERSION . ' | ' . ( is_multisite() ? 'multisite' : 'single site' ),
			'cURL: ' . ( function_exists( 'curl_init' ) ? 'yes' : 'no' ) . ' | OpenSSL: ' . ( Secret::available() ? 'yes' : 'no' ) . ' | WooCommerce: ' . ( class_exists( 'WooCommerce' ) ? 'yes' : 'no' ),
			'Provider: ' . $this->settings->get( 'ai_provider' ) . ' | Model: ' . $this->settings->get( 'model' ) . ' | Host: ' . wp_parse_url( (string) $this->settings->get( 'api_endpoint' ), PHP_URL_HOST ),
			'API key set: ' . ( $this->settings->get( 'api_key' ) ? 'yes' : 'no' ) . ' | Knowledge base: ' . $this->knowledge_base->get_count() . ' docs',
			'',
			'--- Checks ---',
		);

		foreach ( $this->checks() as $check ) {
			$lines[] = '[' . strtoupper( $check['status'] ) . '] ' . $check['title'] . ' — ' . $check['text'];
		}

		$lines[] = '';
		$lines[] = '--- Log ---';

		foreach ( array_slice( Logger::all(), 0, 30 ) as $entry ) {
			$context = array();
			foreach ( (array) $entry['context'] as $key => $value ) {
				$context[] = $key . '=' . $value;
			}

			$lines[] = $entry['time'] . ' [' . $entry['level'] . '/' . $entry['source'] . '] ' . $entry['message']
				. ( (int) $entry['count'] > 1 ? ' (x' . (int) $entry['count'] . ')' : '' )
				. ( $context ? ' {' . implode( ', ', $context ) . '}' : '' );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Shape one check.
	 *
	 * @param string $status ok, warn or fail.
	 * @param string $title  Verdict.
	 * @param string $text   Detail and what to do.
	 * @return array{status:string,title:string,text:string}
	 */
	private static function check( $status, $title, $text ) {
		return array(
			'status' => $status,
			'title'  => $title,
			'text'   => $text,
		);
	}
}
