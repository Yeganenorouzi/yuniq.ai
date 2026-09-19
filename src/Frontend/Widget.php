<?php
/**
 * Public-facing chat widget.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Frontend;

use Yuniq\Ai\Ai\Client;
use Yuniq\Ai\Contracts\HookableInterface;
use Yuniq\Ai\Rest\ChatController;
use Yuniq\Ai\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the widget assets and prints its markup in the footer.
 */
final class Widget implements HookableInterface {

	/**
	 * Handle shared by the widget's stylesheet and script.
	 */
	const HANDLE = 'yuniq-ai-widget';

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * AI client, used only to report whether streaming is available.
	 *
	 * @var Client
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings.
	 * @param Client   $client   AI client.
	 */
	public function __construct( Settings $settings, Client $client ) {
		$this->settings = $settings;
		$this->client   = $client;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_head', array( $this, 'preload_font' ), 1 );
		add_action( 'wp_footer', array( $this, 'render' ) );
	}

	/**
	 * Whether the widget should appear on the current request.
	 *
	 * @return bool
	 */
	private function is_active() {
		/**
		 * Filters whether the assistant renders on this request.
		 *
		 * @param bool $active Whether the widget is enabled.
		 */
		return (bool) apply_filters( 'yuniq_ai_is_active', (bool) $this->settings->get( 'enabled' ) );
	}

	/**
	 * Preload the primary font weight.
	 *
	 * The font is served from this plugin rather than Google Fonts, which
	 * is slow or unreachable from Iran and leaks visitor IPs to a third
	 * party. Preloading the one weight used for body text keeps the first
	 * paint from flashing the fallback.
	 *
	 * @return void
	 */
	public function preload_font() {
		if ( ! $this->is_active() ) {
			return;
		}

		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( YUNIQ_AI_URL . 'assets/fonts/vazirmatn-400.woff2' )
		);
	}

	/**
	 * Enqueue the widget stylesheet and script.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_active() ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			YUNIQ_AI_URL . 'assets/css/public.css',
			array(),
			YUNIQ_AI_VERSION
		);

		wp_enqueue_script(
			self::HANDLE,
			YUNIQ_AI_URL . 'assets/js/public.js',
			array(),
			YUNIQ_AI_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			'yuniqAI',
			array(
				'restUrl'  => esc_url_raw( rest_url( ChatController::NAMESPACE_V1 . '/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'settings' => $this->script_settings(),
			)
		);
	}

	/**
	 * Settings handed to the frontend script.
	 *
	 * @return array
	 */
	private function script_settings() {
		return array(
			'assistantName'    => $this->settings->get( 'assistant_name' ),
			'welcomeMessage'   => $this->settings->get( 'welcome_message' ),
			'theme'            => $this->theme(),
			'chatWidth'        => (int) $this->settings->get( 'chat_width' ),
			'chatHeight'       => (int) $this->settings->get( 'chat_height' ),
			'enableAnimations' => (bool) $this->settings->get( 'enable_animations' ),
			'videoEnabled'     => (bool) $this->settings->get( 'video_enabled' ),
			'videoAutoplay'    => (bool) $this->settings->get( 'video_autoplay' ),
			// Honours the admin toggle; it used to be hard-coded to false,
			// which made the "بی‌صدا" switch in the settings screen inert.
			'videoMute'        => (bool) $this->settings->get( 'video_mute' ),
			'videoControls'    => (bool) $this->settings->get( 'video_controls' ),
			'streaming'        => $this->client->can_stream(),
			'quickActions'     => $this->quick_actions(),
			// Both files live in this plugin: nothing is fetched from a CDN,
			// and they load only when the visitor moves to open the panel.
			'motion'           => array(
				'enabled' => (bool) $this->settings->get( 'enable_animations' ),
				'gsap'    => YUNIQ_AI_URL . 'assets/vendor/gsap/gsap.min.js',
				'layer'   => YUNIQ_AI_URL . 'assets/js/motion.js',
			),
			'i18n'             => array(
				'genericError' => __( 'مشکلی پیش آمد. لطفاً دوباره تلاش کنید.', 'yuniq-ai' ),
				'networkError' => __( 'خطای شبکه. اتصال اینترنت خود را بررسی کنید.', 'yuniq-ai' ),
				'newMessage'   => __( 'پیام جدید', 'yuniq-ai' ),
				'lightMode'    => __( 'حالت روشن', 'yuniq-ai' ),
				'darkMode'     => __( 'حالت تاریک', 'yuniq-ai' ),
				'answering'    => __( 'در حال پاسخ‌دادن', 'yuniq-ai' ),
			),
		);
	}

	/**
	 * Configured color scheme: auto, light or dark.
	 *
	 * @return string
	 */
	private function theme() {
		$theme = (string) $this->settings->get( 'theme', 'auto' );

		return in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ? $theme : 'auto';
	}

	/**
	 * Header logo, falling back to the avatar.
	 *
	 * @return string
	 */
	private function logo_url() {
		$logo = (string) $this->settings->get( 'logo_url', '' );

		return $logo ? $logo : (string) $this->settings->get( 'avatar_url', '' );
	}

	/**
	 * Quick action cards with unlabeled rows removed.
	 *
	 * @return array
	 */
	private function quick_actions() {
		$actions = (array) $this->settings->get( 'quick_actions', array() );

		$actions = array_values(
			array_filter(
				$actions,
				function ( $action ) {
					return is_array( $action ) && ! empty( $action['label'] );
				}
			)
		);

		if ( $actions ) {
			return $actions;
		}

		$defaults = Settings::defaults();

		return $defaults['quick_actions'];
	}

	/**
	 * Brand tokens as an inline style attribute.
	 *
	 * Set on the element itself rather than in a `:root` block, because
	 * the stylesheet defines its defaults on `.yuniq-ai-root` — a
	 * `:root` rule would lose to it and the admin's colors would be
	 * silently ignored.
	 *
	 * @return string
	 */
	private function brand_style() {
		return sprintf(
			'--yuniq-ai-primary:%1$s;--yuniq-ai-secondary:%2$s;--yuniq-ai-radius:%3$dpx;',
			(string) $this->settings->get( 'primary_color', '#263DFF' ),
			(string) $this->settings->get( 'secondary_color', '#111B55' ),
			absint( $this->settings->get( 'border_radius', 20 ) )
		);
	}

	/**
	 * Print the widget markup.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! $this->is_active() ) {
			return;
		}

		$assistant_name = (string) $this->settings->get( 'assistant_name' );
		$position_class = 'yuniq-ai-pos-' . sanitize_html_class( (string) $this->settings->get( 'widget_position', 'bottom-right' ) );
		$logo_url       = $this->logo_url();
		$header_sub     = (string) $this->settings->get( 'header_subtitle' );
		$help_title     = (string) $this->settings->get( 'help_title' );
		$help_sub       = (string) $this->settings->get( 'help_subtitle' );
		$video_url      = (string) $this->settings->get( 'video_url' );
		$video_enabled  = $this->settings->get( 'video_enabled' ) && '' !== $video_url;
		$video_muted    = (bool) $this->settings->get( 'video_mute' );
		?>
		<div id="yuniq-ai-root"
			class="yuniq-ai-root <?php echo esc_attr( $position_class ); ?>"
			dir="rtl"
			data-theme="<?php echo esc_attr( $this->theme() ); ?>"
			style="<?php echo esc_attr( $this->brand_style() ); ?>">

			<div class="yuniq-ai-launcher-wrap">
				<button type="button" id="yuniq-ai-launcher" class="yuniq-ai-launcher"
					aria-expanded="false"
					aria-controls="yuniq-ai-panel"
					aria-label="<?php echo esc_attr( sprintf( /* translators: %s: assistant name. */ __( 'باز کردن %s', 'yuniq-ai' ), $assistant_name ) ); ?>">
					<span class="yuniq-ai-online-badge"></span>
					<span class="yuniq-ai-launcher-icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
					</span>
					<span class="yuniq-ai-launcher-close" aria-hidden="true">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" focusable="false"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					</span>
				</button>
				<span class="yuniq-ai-launcher-tooltip" aria-hidden="true"><?php esc_html_e( 'با من صحبت کنید!', 'yuniq-ai' ); ?></span>
			</div>

			<div id="yuniq-ai-panel" class="yuniq-ai-panel"
				role="dialog"
				aria-modal="true"
				aria-labelledby="yuniq-ai-panel-title"
				aria-hidden="true">

				<header class="yuniq-ai-panel-header">
					<div class="yuniq-ai-header-brand">
						<?php if ( $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="yuniq-ai-header-logo" width="36" height="36" />
						<?php else : ?>
							<div class="yuniq-ai-header-logo-fallback" aria-hidden="true">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M12 2a5 5 0 0 1 5 5v1h1a3 3 0 0 1 3 3v3a3 3 0 0 1-3 3h-1v1a3 3 0 0 1-3 3h-4a3 3 0 0 1-3-3v-1H6a3 3 0 0 1-3-3v-3a3 3 0 0 1 3-3h1V7a5 5 0 0 1 5-5z"/><circle cx="9.5" cy="12.5" r="1"/><circle cx="14.5" cy="12.5" r="1"/></svg>
							</div>
						<?php endif; ?>
						<div class="yuniq-ai-header-titles">
							<span class="yuniq-ai-header-name" id="yuniq-ai-panel-title"><?php echo esc_html( $assistant_name ); ?></span>
							<?php if ( $header_sub ) : ?>
								<span class="yuniq-ai-header-sub"><?php echo esc_html( $header_sub ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<div class="yuniq-ai-header-controls">
						<button type="button" class="yuniq-ai-ctrl-btn" id="yuniq-ai-theme-toggle"
							aria-label="<?php esc_attr_e( 'تغییر حالت روشن و تاریک', 'yuniq-ai' ); ?>"
							title="<?php esc_attr_e( 'تغییر حالت روشن و تاریک', 'yuniq-ai' ); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
						</button>
						<button type="button" class="yuniq-ai-ctrl-btn" id="yuniq-ai-panel-close"
							aria-label="<?php esc_attr_e( 'بستن گفتگو', 'yuniq-ai' ); ?>"
							title="<?php esc_attr_e( 'بستن', 'yuniq-ai' ); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
						</button>
					</div>
				</header>

				<?php if ( $video_enabled ) : ?>
					<div class="yuniq-ai-video-section" id="yuniq-ai-video-section">
						<div class="yuniq-ai-video-wrapper">
							<video id="yuniq-ai-video" playsinline webkit-playsinline preload="none" loop
								<?php echo $video_muted ? 'muted' : ''; ?>
								<?php echo $this->settings->get( 'video_controls' ) ? 'controls' : ''; ?>>
								<source src="<?php echo esc_url( $video_url ); ?>" type="video/mp4" />
							</video>
						</div>
					</div>
				<?php endif; ?>

				<div class="yuniq-ai-panel-body" id="yuniq-ai-panel-body">
					<div class="yuniq-ai-intro" id="yuniq-ai-intro">
						<h3 class="yuniq-ai-intro-title"><?php echo esc_html( $help_title ); ?></h3>
						<?php if ( $help_sub ) : ?>
							<p class="yuniq-ai-intro-sub"><?php echo esc_html( $help_sub ); ?></p>
						<?php endif; ?>
					</div>

					<div class="yuniq-ai-messages" id="yuniq-ai-messages" role="log" aria-live="polite" aria-relevant="additions"></div>

					<button type="button" class="yuniq-ai-jump" id="yuniq-ai-jump">
						<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg>
						<?php esc_html_e( 'پیام جدید', 'yuniq-ai' ); ?>
					</button>
				</div>

				<footer class="yuniq-ai-input-area">
					<div class="yuniq-ai-prompt-ticker" id="yuniq-ai-prompt-ticker">
						<div class="yuniq-ai-ticker-track" id="yuniq-ai-ticker-track"></div>
					</div>
					<form id="yuniq-ai-chat-form" autocomplete="off">
						<label class="yuniq-ai-sr-only" for="yuniq-ai-input"><?php esc_html_e( 'پیام شما', 'yuniq-ai' ); ?></label>
						<textarea id="yuniq-ai-input" name="message" rows="1" maxlength="2000"
							placeholder="<?php esc_attr_e( 'سوال خود را اینجا بنویسید...', 'yuniq-ai' ); ?>"></textarea>
						<button type="submit" id="yuniq-ai-send" aria-label="<?php esc_attr_e( 'ارسال پیام', 'yuniq-ai' ); ?>">
							<svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
						</button>
					</form>
					<div class="yuniq-ai-powered">
						<?php
						/**
						 * Filters the credit line under the chat input.
						 *
						 * Return an empty string to remove it.
						 *
						 * @param string $html Credit markup.
						 */
						echo wp_kses_post( apply_filters( 'yuniq_ai_powered_by_html', esc_html__( 'قدرت‌گرفته با هوش مصنوعی | Yuniq.ai', 'yuniq-ai' ) ) );
						?>
					</div>
				</footer>
			</div>
		</div>
		<?php
	}
}
