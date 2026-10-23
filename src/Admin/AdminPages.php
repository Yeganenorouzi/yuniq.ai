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
use Yuniq\Ai\Setup\Activator;
use Yuniq\Ai\Support\Assets;
use Yuniq\Ai\Support\Logger;

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
	 * Status and error log screen slug.
	 */
	const STATUS_SLUG = 'yuniq-ai-status';

	/**
	 * Setup guide screen slug.
	 */
	const GUIDE_SLUG = 'yuniq-ai-guide';

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
	 * Setup progress and system checks.
	 *
	 * @var Health
	 */
	private $health;

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings       Plugin settings.
	 * @param KnowledgeBase $knowledge_base Knowledge base storage.
	 * @param Indexer       $indexer        Content indexer.
	 * @param Analytics     $analytics      Conversation log.
	 * @param LiveSupport   $live_support   Live-support conversations.
	 * @param Health        $health         Setup progress and system checks.
	 */
	public function __construct( Settings $settings, KnowledgeBase $knowledge_base, Indexer $indexer, Analytics $analytics, LiveSupport $live_support, Health $health ) {
		$this->health         = $health;
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
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_wizard' ) );
		add_action( 'admin_init', array( $this, 'maybe_skip_index_step' ) );
		add_action( 'admin_init', array( $this, 'maybe_lock_other_screens' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . YUNIQ_AI_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Send a fresh install straight to the setup wizard on its first admin
	 * request after activation, so a new user is walked through it instead
	 * of landing on the plain plugins list and having to find it themselves.
	 *
	 * @return void
	 */
	public function maybe_redirect_to_wizard() {
		if ( ! get_option( Activator::REDIRECT_OPTION ) ) {
			return;
		}

		delete_option( Activator::REDIRECT_OPTION );

		// Never redirect out of a bulk/multi-plugin activation, an AJAX
		// request, or a user who cannot even see the plugin's screens.
		if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) || ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
		exit;
	}

	/**
	 * While the wizard is not finished, every other plugin screen bounces
	 * back to it — the owner should not be able to poke around the
	 * knowledge base, analytics or live-support inbox of an assistant that
	 * is not even connected to an AI service yet.
	 *
	 * The guide stays open: it is reference material for finishing step
	 * one, not a feature of its own.
	 *
	 * @return void
	 */
	public function maybe_lock_other_screens() {
		if ( wp_doing_ajax() || ! current_user_can( self::CAPABILITY ) || ! $this->health->is_setup_locked() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $page || in_array( $page, array( self::MENU_SLUG, self::GUIDE_SLUG ), true ) || 0 !== strpos( $page, 'yuniq-ai' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
		exit;
	}

	/**
	 * The wizard's "index" step offers a plain link (not a form, since
	 * there is nothing else to submit) to skip it outright, for a brand
	 * new site with nothing to index yet — without it, an empty site could
	 * never satisfy that step and would stay locked out forever.
	 *
	 * @return void
	 */
	public function maybe_skip_index_step() {
		if ( ! isset( $_GET['yuniq_ai_skip_index'] ) || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		check_admin_referer( 'yuniq_ai_skip_index' );

		update_option( Health::INDEX_SKIPPED_OPTION, true );

		wp_safe_redirect( remove_query_arg( array( 'yuniq_ai_skip_index', '_wpnonce' ) ) );
		exit;
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
			$this->health->is_setup_locked() ? __( 'راه‌اندازی', 'yuniq-ai' ) : __( 'تنظیمات', 'yuniq-ai' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_settings_page' )
		);

		// Hidden while the wizard is unfinished: maybe_lock_other_screens()
		// bounces a direct visit back to it anyway, so a menu entry that
		// only leads to a bounce is worse than no entry at all.
		if ( ! $this->health->is_setup_locked() ) {
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

			add_submenu_page(
				self::MENU_SLUG,
				__( 'وضعیت و خطاها', 'yuniq-ai' ),
				__( 'وضعیت و خطاها', 'yuniq-ai' ) . $this->count_badge( Logger::unseen() ),
				self::CAPABILITY,
				self::STATUS_SLUG,
				array( $this, 'render_status_page' )
			);
		}

		add_submenu_page(
			self::MENU_SLUG,
			__( 'راهنما', 'yuniq-ai' ),
			__( 'راهنما', 'yuniq-ai' ),
			self::CAPABILITY,
			self::GUIDE_SLUG,
			array( $this, 'render_guide_page' )
		);
	}

	/**
	 * A WP-core-style count bubble for a menu label.
	 *
	 * @param int $count Number to show.
	 * @return string Empty for zero.
	 */
	private function count_badge( $count ) {
		$count = (int) $count;

		return $count ? sprintf( ' <span class="update-plugins count-%1$d"><span class="update-count">%1$d</span></span>', $count ) : '';
	}

	/**
	 * Render the status and error log screen.
	 *
	 * @return void
	 */
	public function render_status_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$checks  = $this->health->checks();
		$report  = $this->health->report();
		$entries = Logger::all();

		// Opening the screen is what "seeing" the errors means.
		Logger::mark_seen();

		require YUNIQ_AI_PATH . 'admin/views/status-page.php';
	}

	/**
	 * Render the setup guide.
	 *
	 * @return void
	 */
	public function render_guide_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$presets = Presets::all();

		require YUNIQ_AI_PATH . 'admin/views/guide-page.php';
	}

	/**
	 * A WP-core-style unread count bubble for conversations awaiting an
	 * agent, appended to the menu label.
	 *
	 * @return string Empty when there is nothing pending.
	 */
	private function pending_badge() {
		// No handoff, no pending conversations — and no query on every admin page.
		if ( ! $this->settings->get( 'live_support_enabled' ) ) {
			return '';
		}

		$count = $this->live_support->count_open();

		if ( ! $count ) {
			return '';
		}

		return $this->count_badge( $count );
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

		// The colour picker and the media library are only used on the
		// settings screen; the other three screens skip both.
		$is_settings = isset( $_GET['page'] ) && self::MENU_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( $is_settings ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_media();
		}

		wp_enqueue_script(
			'yuniq-ai-admin',
			YUNIQ_AI_URL . 'assets/js/admin.js',
			$is_settings ? array( 'jquery', 'wp-color-picker' ) : array( 'jquery' ),
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
				'restUrl' => esc_url_raw( rest_url( 'yuniq-ai/v1/config' ) ),
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
					'confirmSkipIndex' => __( 'بدون ایندکس، دستیار فقط از «اطلاعات کسب‌وکار» و توضیحاتی که بعداً می‌نویسید استفاده می‌کند، نه از محتوای واقعی سایت. بعداً هر وقت خواستید از صفحه «پایگاه دانش» ایندکس را اجرا کنید. رد شدن از این مرحله؟', 'yuniq-ai' ),
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
					'lsAnonymous'       => __( 'بازدیدکننده ناشناس', 'yuniq-ai' ),
					'lsNoLeads'         => __( 'هنوز سرنخی ثبت نشده است.', 'yuniq-ai' ),
					'lsRoleUser'        => __( 'بازدیدکننده', 'yuniq-ai' ),
					'lsRoleBot'         => __( 'دستیار هوشمند', 'yuniq-ai' ),
					'lsRoleAgent'       => __( 'کارشناس', 'yuniq-ai' ),
					'unsaved'           => __( 'تغییرات ذخیره‌نشده دارید', 'yuniq-ai' ),
					'confirmClearLog'   => __( 'همه موارد گزارش خطا پاک شود؟', 'yuniq-ai' ),
					'copied'            => __( 'کپی شد', 'yuniq-ai' ),
					'sending'           => __( 'در حال ارسال...', 'yuniq-ai' ),
					'restOk'            => __( 'ارتباط ویجت با سایت برقرار است', 'yuniq-ai' ),
					'restOkText'        => __( 'مسیر REST افزونه از مرورگر در دسترس است.', 'yuniq-ai' ),
					'restFail'          => __( 'ویجت نمی‌تواند با سایت ارتباط بگیرد', 'yuniq-ai' ),
					'restFailText'      => __( 'مسیر REST افزونه مسدود است. معمولاً یک افزونه امنیتی یا فایروال، REST API را برای کاربران مهمان بسته است؛ مسیر /wp-json/yuniq-ai/ را در آن مجاز کنید.', 'yuniq-ai' ),
					'insecureEndpoint'  => __( 'این آدرس با http شروع می‌شود؛ کلید API بدون رمز فرستاده می‌شود. تا حد امکان از https استفاده کنید.', 'yuniq-ai' ),
					'saving'            => __( 'در حال ذخیره...', 'yuniq-ai' ),
					'confirmEnableNoKey' => __( 'هنوز کلید API وارد نکرده‌اید؛ با فعال‌سازی، بازدیدکنندگان دکمه گفتگو را می‌بینند ولی پاسخی نمی‌گیرند. همین الان فعال شود؟', 'yuniq-ai' ),
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

		$settings    = $this->settings->all();
		$setup_steps = $this->health->setup_steps();

		if ( $this->health->is_setup_locked() ) {
			$this->render_wizard( $setup_steps, $settings );

			return;
		}

		require YUNIQ_AI_PATH . 'admin/views/settings-page.php';
	}

	/**
	 * The gated setup wizard shown instead of the regular settings screen
	 * until every step is done.
	 *
	 * @param array $setup_steps From Health::setup_steps().
	 * @param array $settings    Current settings.
	 * @return void
	 */
	private function render_wizard( array $setup_steps, array $settings ) {
		$first_undone = count( $setup_steps );

		foreach ( $setup_steps as $index => $step ) {
			if ( ! $step['done'] ) {
				$first_undone = $index + 1;
				break;
			}
		}

		// A step already done can be revisited (e.g. to fix a wrong key),
		// but the URL cannot be used to jump ahead of what is actually done.
		$requested    = isset( $_GET['yuniq_step'] ) ? absint( wp_unslash( $_GET['yuniq_step'] ) ) : $first_undone; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_step = ( $requested >= 1 && $requested <= $first_undone ) ? $requested : $first_undone;
		$presets      = Presets::all();
		$skip_index_url = wp_nonce_url(
			add_query_arg( 'yuniq_ai_skip_index', '1' ),
			'yuniq_ai_skip_index'
		);

		require YUNIQ_AI_PATH . 'admin/views/wizard-page.php';
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
