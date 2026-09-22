<?php
/**
 * Admin menu, screens and asset loading.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Admin;

use Yuniq\Ai\Ai\Presets;
use Yuniq\Ai\Analytics\Repository as Analytics;
use Yuniq\Ai\Contracts\HookableInterface;
use Yuniq\Ai\Kb\Indexer;
use Yuniq\Ai\Kb\Repository as KnowledgeBase;
use Yuniq\Ai\LiveSupport\Repository as LiveSupport;
use Yuniq\Ai\Settings;
use Yuniq\Ai\Support\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the settings, knowledge base and analytics screens.
 */
final class AdminPages implements HookableInterface {

	/**
	 * Top level menu slug.
	 */
	const MENU_SLUG = 'yuniq-ai';

	/**
	 * Knowledge base screen slug.
	 */
	const KNOWLEDGE_SLUG = 'yuniq-ai-knowledge';

	/**
	 * Analytics screen slug.
	 */
	const ANALYTICS_SLUG = 'yuniq-ai-analytics';

	/**
	 * Live support inbox screen slug.
	 */
	const LIVE_SUPPORT_SLUG = 'yuniq-ai-live-support';

	/**
	 * Capability required for every screen and action.
	 */
	const CAPABILITY = 'manage_options';

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
	 * Content indexer.
	 *
	 * @var Indexer
	 */
	private $indexer;

	/**
	 * Conversation log.
	 *
	 * @var Analytics
	 */
	private $analytics;

	/**
	 * Live-support conversations, for the inbox screen and menu badge.
	 *
	 * @var LiveSupport
	 */
	private $live_support;

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings       Plugin settings.
	 * @param KnowledgeBase $knowledge_base Knowledge base storage.
	 * @param Indexer       $indexer        Content indexer.
	 * @param Analytics     $analytics      Conversation log.
	 * @param LiveSupport   $live_support   Live-support conversations.
	 */
	public function __construct( Settings $settings, KnowledgeBase $knowledge_base, Indexer $indexer, Analytics $analytics, LiveSupport $live_support ) {
		$this->settings       = $settings;
		$this->knowledge_base = $knowledge_base;
		$this->indexer        = $indexer;
		$this->analytics      = $analytics;
		$this->live_support   = $live_support;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . YUNIQ_AI_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add the plugin's menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'دستیار هوشمند Yuniq.ai', 'yuniq-ai' ),
			__( 'Yuniq.ai', 'yuniq-ai' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-format-chat',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'تنظیمات', 'yuniq-ai' ),
			__( 'تنظیمات', 'yuniq-ai' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'پایگاه دانش', 'yuniq-ai' ),
			__( 'پایگاه دانش', 'yuniq-ai' ),
			self::CAPABILITY,
			self::KNOWLEDGE_SLUG,
			array( $this, 'render_knowledge_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'آمار و تحلیل', 'yuniq-ai' ),
			__( 'آمار و تحلیل', 'yuniq-ai' ),
			self::CAPABILITY,
			self::ANALYTICS_SLUG,
			array( $this, 'render_analytics_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'پشتیبانی زنده', 'yuniq-ai' ),
			__( 'پشتیبانی زنده', 'yuniq-ai' ) . $this->pending_badge(),
			self::CAPABILITY,
			self::LIVE_SUPPORT_SLUG,
			array( $this, 'render_live_support_page' )
		);
	}

	/**
	 * A WP-core-style unread count bubble for conversations awaiting an
	 * agent, appended to the menu label.
	 *
	 * @return string Empty when there is nothing pending.
	 */
	private function pending_badge() {
		$count = $this->live_support->count_open();

		if ( ! $count ) {
			return '';
		}

		return sprintf( ' <span class="update-plugins count-%1$d"><span class="update-count">%1$d</span></span>', $count );
	}

	/**
	 * Register the option with the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			Settings::OPTION_GROUP,
			Settings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->settings, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * Add a Settings shortcut on the plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$url = admin_url( 'admin.php?page=' . self::MENU_SLUG );

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'تنظیمات', 'yuniq-ai' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Load admin CSS and JS on the plugin's own screens only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'yuniq-ai' ) ) {
			return;
		}

		wp_enqueue_style(
			'yuniq-ai-admin',
			YUNIQ_AI_URL . 'assets/css/admin.css',
			array(),
			Assets::version( 'assets/css/admin.css' )
		);

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();

		wp_enqueue_script(
			'yuniq-ai-admin',
			YUNIQ_AI_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			Assets::version( 'assets/js/admin.js' ),
			true
		);

		wp_localize_script(
			'yuniq-ai-admin',
			'yuniqAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Ajax\CrawlController::NONCE_ACTION ),
				'presets' => Presets::all(),
				'i18n'    => array(
					'testing'           => __( 'در حال ارسال یک پیام آزمایشی...', 'yuniq-ai' ),
					'loadingModels'     => __( 'در حال دریافت فهرست مدل‌ها...', 'yuniq-ai' ),
					'modelsLoaded'      => __( 'مدل در دسترس است. روی هر کدام بزنید تا انتخاب شود.', 'yuniq-ai' ),
					'noModels'          => __( 'سرویس فهرستی برنگرداند؛ نام مدل را از مستندات سرویس بردارید.', 'yuniq-ai' ),
					'requestFailed'     => __( 'درخواست به سرور وردپرس انجام نشد.', 'yuniq-ai' ),
					'suggestedModels'   => __( 'مدل‌های پیشنهادی:', 'yuniq-ai' ),
					'getKey'            => __( 'دریافت کلید API', 'yuniq-ai' ),
					'replaceEndpoint'   => __( 'آدرس فعلی با آدرس این سرویس جایگزین شود؟', 'yuniq-ai' ),
					'replacePrompt'     => __( 'متن فعلی پرامپت سیستم با این قالب جایگزین شود؟', 'yuniq-ai' ),
					'confirmResetDesign' => __( 'همه تنظیمات طراحی به حالت اولیه برگردد؟ (تا ذخیره نکنید در سایت اعمال نمی‌شود)', 'yuniq-ai' ),
					'crawling'        => __( 'در حال ایندکس‌گذاری...', 'yuniq-ai' ),
					'completed'       => __( 'ایندکس‌گذاری با موفقیت انجام شد!', 'yuniq-ai' ),
					'completedShort'  => __( 'تکمیل‌شده', 'yuniq-ai' ),
					'error'           => __( 'خطایی رخ داد.', 'yuniq-ai' ),
					'confirmClear'    => __( 'آیا مطمئن هستید که می‌خواهید کل پایگاه دانش را پاک کنید؟', 'yuniq-ai' ),
					'confirmRebuild'  => __( 'ابتدا داده‌های قبلی پاک و سپس ایندکس جدید اجرا می‌شود. ادامه می‌دهید؟', 'yuniq-ai' ),
					'startIndex'      => __( 'شروع ایندکس‌گذاری', 'yuniq-ai' ),
					'indexed'         => __( 'ایندکس‌شده:', 'yuniq-ai' ),
					'starting'        => __( 'در حال شروع...', 'yuniq-ai' ),
					'stopping'        => __( 'در حال توقف...', 'yuniq-ai' ),
					'stopped'         => __( 'ایندکس‌گذاری متوقف شد.', 'yuniq-ai' ),
					'nothingToIndex'  => __( 'محتوایی برای ایندکس پیدا نشد. نوع محتوا را در تنظیمات انتخاب کنید.', 'yuniq-ai' ),
					'useThisImage'    => __( 'استفاده از این تصویر', 'yuniq-ai' ),
					'chooseAvatar'    => __( 'انتخاب آواتار / لوگو', 'yuniq-ai' ),
					'chooseLogo'      => __( 'انتخاب لوگو', 'yuniq-ai' ),
					'lsNoConversations' => __( 'گفتگویی در این بخش وجود ندارد.', 'yuniq-ai' ),
					'lsSelectPrompt'    => __( 'یک گفتگو را از فهرست انتخاب کنید.', 'yuniq-ai' ),
					'lsConfirmResolve'  => __( 'این گفتگو بسته و به دستیار هوشمند بازگردانده شود؟', 'yuniq-ai' ),
					'lsSent'            => __( 'ارسال شد', 'yuniq-ai' ),
					'lsStatusPending'   => __( 'در انتظار', 'yuniq-ai' ),
					'lsStatusActive'    => __( 'در حال گفتگو', 'yuniq-ai' ),
					'lsStatusResolved'  => __( 'بسته‌شده', 'yuniq-ai' ),
				),
			)
		);
	}

	/**
	 * Render the settings screen.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$settings = $this->settings->all();

		require YUNIQ_AI_PATH . 'admin/views/settings-page.php';
	}

	/**
	 * Render the knowledge base screen.
	 *
	 * @return void
	 */
	public function render_knowledge_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$count          = $this->knowledge_base->get_count();
		$counts_by_type = $this->knowledge_base->get_counts_by_type();
		$recent         = $this->knowledge_base->get_recent( 100 );
		$status         = $this->indexer->get_latest_status();

		require YUNIQ_AI_PATH . 'admin/views/knowledge-page.php';
	}

	/**
	 * Render the analytics screen.
	 *
	 * @return void
	 */
	public function render_analytics_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$summary = $this->analytics->get_summary();
		$recent  = $this->analytics->get_recent( 25 );

		require YUNIQ_AI_PATH . 'admin/views/analytics-page.php';
	}

	/**
	 * Render the live-support inbox screen.
	 *
	 * @return void
	 */
	public function render_live_support_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$conversations = $this->live_support->get_conversations();

		require YUNIQ_AI_PATH . 'admin/views/live-support-page.php';
	}
}
