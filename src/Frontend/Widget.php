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
use Yuniq\Ai\Support\Assets;

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
	 * Whether the `[yuniq_ai_page]` shortcode already printed the widget
	 * markup earlier in this request, so the floating footer copy can skip
	 * itself — otherwise the page would end up with two elements sharing
	 * `id="yuniq-ai-root"`.
	 *
	 * @var bool
	 */
	private $rendered_full_page = false;

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
		add_shortcode( 'yuniq_ai_page', array( $this, 'render_full_page' ) );
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
		if ( ! $this->is_active() || ! $this->is_shown_here() || 'vazirmatn' !== $this->settings->get( 'font_family', 'vazirmatn' ) ) {
			return;
		}

		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( YUNIQ_AI_URL . 'assets/fonts/vazirmatn-400.woff2' )
		);
	}

	/**
	 * Whether the floating widget belongs on the current URL, according to
	 * the page rules on the settings screen.
	 *
	 * Each line is a path fragment (`/shop/`), an exact home match (`/`),
	 * or a pattern with `*` wildcards (`/blog/*`).
	 *
	 * @return bool
	 */
	private function is_shown_here() {
		$rule = (string) $this->settings->get( 'display_rule', 'all' );

		if ( 'include' !== $rule && 'exclude' !== $rule ) {
			return true;
		}

		$request = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path    = (string) wp_parse_url( $request, PHP_URL_PATH );
		$path    = '' === $path ? '/' : $path;
		$lines   = array_filter( array_map( 'trim', explode( "\n", (string) $this->settings->get( 'display_paths', '' ) ) ) );
		$matched = false;

		foreach ( $lines as $line ) {
			if ( false !== strpos( $line, '*' ) ) {
				$regex   = '#^' . str_replace( '\*', '.*', preg_quote( $line, '#' ) ) . '$#i';
				$matched = (bool) preg_match( $regex, $path );
			} elseif ( '/' === $line ) {
				$matched = '/' === $path;
			} else {
				$matched = false !== stripos( $path, $line );
			}

			if ( $matched ) {
				break;
			}
		}

		$shown = 'include' === $rule ? $matched : ! $matched;

		/**
		 * Filters whether the floating widget shows on the current URL.
		 *
		 * @param bool   $shown Result of the page rules.
		 * @param string $path  Current request path.
		 */
		return (bool) apply_filters( 'yuniq_ai_is_shown_here', $shown, $path );
	}

	/**
	 * Enqueue the widget stylesheet and script.
	 *
	 * @param bool|string $force True to skip the page rules (the shortcode
	 *                           page); WordPress passes '' from the hook.
	 * @return void
	 */
	public function enqueue_assets( $force = false ) {
		if ( ! $this->is_active() || ( true !== $force && ! $this->is_shown_here() ) ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			YUNIQ_AI_URL . 'assets/css/public.css',
			array(),
			Assets::version( 'assets/css/public.css' )
		);

		$custom_css = trim( (string) $this->settings->get( 'custom_css', '' ) );
		if ( '' !== $custom_css ) {
			wp_add_inline_style( self::HANDLE, $custom_css );
		}

		wp_enqueue_script(
			self::HANDLE,
			YUNIQ_AI_URL . 'assets/js/public.js',
			array(),
			Assets::version( 'assets/js/public.js' ),
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
			'streaming'        => $this->client->can_stream(),
			'quickActions'     => $this->quick_actions(),
			'liveSupport'      => array(
				'enabled'   => (bool) $this->settings->get( 'live_support_enabled' ),
				'mode'      => (string) $this->settings->get( 'live_support_mode', 'both' ),
				'agentName' => (string) $this->settings->get( 'agent_display_name' ),
			),
			'productCards'     => (bool) $this->settings->get( 'product_cards_enabled' ),
			'leadForms'        => (array) $this->settings->get( 'lead_forms', array() ),
			// Both files live in this plugin: nothing is fetched from a CDN,
			// and they load only when the visitor moves to open the panel.
			'motion'           => array(
				'enabled' => (bool) $this->settings->get( 'enable_animations' ),
				'gsap'    => YUNIQ_AI_URL . 'assets/vendor/gsap/gsap.min.js',
				'layer'   => YUNIQ_AI_URL . 'assets/js/motion.js',
			),
			'i18n'             => array(
				'genericError'     => __( 'مشکلی پیش آمد. لطفاً دوباره تلاش کنید.', 'yuniq-ai' ),
				'networkError'     => __( 'خطای شبکه. اتصال اینترنت خود را بررسی کنید.', 'yuniq-ai' ),
				'newMessage'       => __( 'پیام جدید', 'yuniq-ai' ),
				'lightMode'        => __( 'حالت روشن', 'yuniq-ai' ),
				'darkMode'         => __( 'حالت تاریک', 'yuniq-ai' ),
				'answering'        => __( 'در حال پاسخ‌دادن', 'yuniq-ai' ),
				'talkToHuman'      => __( 'صحبت با کارشناس', 'yuniq-ai' ),
				'needHumanCta'     => __( 'این پاسخ کافی نبود؟ صحبت با کارشناس', 'yuniq-ai' ),
				'escalating'       => __( 'در حال اتصال به کارشناس...', 'yuniq-ai' ),
				'connectedToAgent' => __( 'به کارشناس پشتیبانی متصل شدید', 'yuniq-ai' ),
				'resolvedByAgent'  => __( 'گفتگو با کارشناس پایان یافت. دستیار هوشمند دوباره در خدمت شماست.', 'yuniq-ai' ),
				'agentLabel'       => (string) $this->settings->get( 'agent_display_name' ),
				'formRequired'     => __( 'این فیلد الزامی است.', 'yuniq-ai' ),
				'formSubmitted'    => __( 'با تشکر! به‌زودی با شما تماس گرفته می‌شود.', 'yuniq-ai' ),
				'viewProduct'      => __( 'مشاهده محصول', 'yuniq-ai' ),
				'addToCart'        => __( 'افزودن به سبد خرید', 'yuniq-ai' ),
				'inStock'          => __( 'موجود', 'yuniq-ai' ),
				'outOfStock'       => __( 'ناموجود', 'yuniq-ai' ),
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
		$s    = $this->settings;
		$vars = array(
			'--yuniq-ai-primary'       => (string) $s->get( 'primary_color', '#263DFF' ),
			'--yuniq-ai-secondary'     => (string) $s->get( 'secondary_color', '#111B55' ),
			'--yuniq-ai-radius'        => absint( $s->get( 'border_radius', 20 ) ) . 'px',
			'--yuniq-ai-panel-w'       => absint( $s->get( 'chat_width', 420 ) ) . 'px',
			'--yuniq-ai-panel-h'       => absint( $s->get( 'chat_height', 720 ) ) . 'px',
			'--yuniq-ai-off-x'         => absint( $s->get( 'custom_position_x', 24 ) ) . 'px',
			'--yuniq-ai-off-y'         => absint( $s->get( 'custom_position_y', 24 ) ) . 'px',
			'--yuniq-ai-launcher-size' => absint( $s->get( 'launcher_size', 62 ) ) . 'px',
			'--yuniq-ai-fs'            => absint( $s->get( 'font_size', 14 ) ) . 'px',
			'--yuniq-ai-greet-delay'   => absint( $s->get( 'greeting_delay', 2 ) ) . 's',
		);

		$custom_font = (string) $s->get( 'custom_font', '' );
		if ( 'custom' === $s->get( 'font_family' ) && '' !== $custom_font ) {
			$vars['--yuniq-ai-font'] = $custom_font . ', Tahoma, sans-serif';
		}

		// Bubble colors are optional; left unset, the stylesheet's theme-aware
		// defaults apply. Text color is picked for contrast automatically.
		foreach ( array( 'user' => 'user_bubble_color', 'bot' => 'bot_bubble_color' ) as $who => $key ) {
			$color = (string) $s->get( $key, '' );
			if ( $color ) {
				$vars[ '--yuniq-ai-' . $who . '-bg' ]   = $color;
				$vars[ '--yuniq-ai-' . $who . '-text' ] = self::is_light( $color ) ? '#14161c' : '#ffffff';
			}
		}

		$style = '';
		foreach ( $vars as $name => $value ) {
			$style .= $name . ':' . $value . ';';
		}

		return $style;
	}

	/**
	 * Whether a hex color is light enough to need dark text on top.
	 *
	 * @param string $hex `#rgb` or `#rrggbb`.
	 * @return bool
	 */
	private static function is_light( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		return ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) > 160;
	}

	/**
	 * Root element classes derived from the settings.
	 *
	 * @param string $extra Space-prefixed extra class, or ''.
	 * @return string
	 */
	private function root_classes( $extra ) {
		$position = (string) $this->settings->get( 'widget_position', 'bottom-right' );
		if ( ! in_array( $position, array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' ), true ) ) {
			$position = 'bottom-right';
		}

		$classes = array( 'yuniq-ai-root', 'yuniq-ai-pos-' . $position );

		if ( 'inherit' === $this->settings->get( 'font_family' ) ) {
			$classes[] = 'yuniq-ai-font-inherit';
		}
		if ( $this->settings->get( 'hide_on_mobile' ) ) {
			$classes[] = 'yuniq-ai-hide-mobile';
		}
		if ( $this->settings->get( 'hide_on_desktop' ) ) {
			$classes[] = 'yuniq-ai-hide-desktop';
		}

		return implode( ' ', $classes ) . $extra;
	}

	/**
	 * Icon markup for the launcher button.
	 *
	 * @return string
	 */
	private function launcher_icon() {
		return self::icon_markup(
			(string) $this->settings->get( 'launcher_icon', 'bot' ),
			(string) $this->settings->get( 'avatar_url', '' )
		);
	}

	/**
	 * Launcher icon markup, shared with the settings-screen preview.
	 *
	 * @param string $icon   One of bot, chat, sparkle, headset, avatar.
	 * @param string $avatar Avatar image URL, used by the `avatar` icon.
	 * @return string
	 */
	public static function icon_markup( $icon, $avatar = '' ) {
		if ( 'avatar' === $icon && $avatar ) {
			return '<img src="' . esc_url( $avatar ) . '" alt="" class="yuniq-ai-launcher-avatar" width="62" height="62" />';
		}

		switch ( $icon ) {
			case 'chat':
				return '<svg class="yuniq-ai-glyph" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';
			case 'sparkle':
				return '<svg class="yuniq-ai-glyph" width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M11 3l1.9 5.1L18 10l-5.1 1.9L11 17l-1.9-5.1L4 10l5.1-1.9z"/><path d="M18.5 14.5l.9 2.1 2.1.9-2.1.9-.9 2.1-.9-2.1-2.1-.9 2.1-.9z" opacity="0.75"/></svg>';
			case 'headset':
				return '<svg class="yuniq-ai-glyph" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 15v-3a8 8 0 0 1 16 0v3"/><path d="M20 16a2 2 0 0 1-2 2h-1v-6h1a2 2 0 0 1 2 2zM4 16a2 2 0 0 0 2 2h1v-6H6a2 2 0 0 0-2 2z"/><path d="M18 18v.5a2.5 2.5 0 0 1-2.5 2.5H13"/></svg>';
		}

		return '<svg class="yuniq-ai-bot" width="42" height="42" viewBox="0 0 48 48" aria-hidden="true" focusable="false">'
			. '<line x1="24" y1="5" x2="24" y2="11" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>'
			. '<circle class="yuniq-ai-bot-antenna" cx="24" cy="5" r="3"/>'
			. '<rect x="4" y="21" width="4.5" height="10" rx="2.25" fill="currentColor" opacity="0.75"/>'
			. '<rect x="39.5" y="21" width="4.5" height="10" rx="2.25" fill="currentColor" opacity="0.75"/>'
			. '<rect x="8" y="11" width="32" height="30" rx="11" fill="currentColor"/>'
			. '<rect class="yuniq-ai-bot-visor" x="12" y="16.5" width="24" height="15" rx="7.5"/>'
			. '<g class="yuniq-ai-bot-eyes"><rect x="16.5" y="20.5" width="4.5" height="7" rx="2.25"/><rect x="27" y="20.5" width="4.5" height="7" rx="2.25"/></g>'
			. '<path class="yuniq-ai-bot-mouth" d="M20.5 35.5q3.5 2.2 7 0" fill="none" stroke-width="2" stroke-linecap="round"/>'
			. '</svg>';
	}

	/**
	 * Print the floating widget markup in `wp_footer`.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! $this->is_active() || $this->rendered_full_page || ! $this->is_shown_here() ) {
			return;
		}

		$this->render_markup( '' );
	}

	/**
	 * `[yuniq_ai_page]` shortcode: the same assistant as a full-page
	 * experience instead of a floating widget, for sites that want a
	 * dedicated "help" page rather than (or in addition to) the bubble.
	 *
	 * @param array $atts Shortcode attributes (none defined).
	 * @return string
	 */
	public function render_full_page( $atts = array() ) {
		unset( $atts );

		if ( ! $this->is_active() || ! $this->settings->get( 'full_page_enabled' ) ) {
			return '';
		}

		// Stops the wp_footer copy from also printing on this page, which
		// would otherwise duplicate `id="yuniq-ai-root"`.
		$this->rendered_full_page = true;

		// Idempotent: registers/enqueues the same handle enqueue_assets()
		// already queues on wp_enqueue_scripts, in case this page somehow
		// missed that hook.
		$this->enqueue_assets( true );

		ob_start();
		$this->render_markup( ' yuniq-ai-page-mode' );

		return (string) ob_get_clean();
	}

	/**
	 * Print the widget markup shared by the floating widget and the
	 * full-page shortcode.
	 *
	 * @param string $extra_root_class Space-prefixed extra class for the root element, or ''.
	 * @return void
	 */
	private function render_markup( $extra_root_class ) {
		$assistant_name = (string) $this->settings->get( 'assistant_name' );
		$header_style   = (string) $this->settings->get( 'header_style', 'gradient' );
		$header_style   = in_array( $header_style, array( 'gradient', 'brand', 'solid' ), true ) ? $header_style : 'gradient';
		$logo_url       = $this->logo_url();
		$header_sub     = (string) $this->settings->get( 'header_subtitle' );
		$help_title     = (string) $this->settings->get( 'help_title' );
		$help_sub       = (string) $this->settings->get( 'help_subtitle' );
		$shape          = (string) $this->settings->get( 'launcher_shape', 'squircle' );
		$launcher_label = (string) $this->settings->get( 'launcher_label', '' );
		$is_pill        = 'pill' === $shape && '' !== $launcher_label;
		$greet_title    = (string) $this->settings->get( 'greeting_title', '' );
		$greet_text     = (string) $this->settings->get( 'greeting_text', '' );
		$show_greeting  = $this->settings->get( 'greeting_enabled' ) && ( '' !== $greet_title || '' !== $greet_text );
		$launcher_class = sprintf(
			'yuniq-ai-launcher yuniq-ai-shape-%1$s yuniq-ai-launcher-%2$s',
			$is_pill ? 'pill' : ( 'circle' === $shape ? 'circle' : 'squircle' ),
			'solid' === $this->settings->get( 'launcher_bg' ) ? 'solid' : 'gradient'
		);
		$wrap_class     = 'yuniq-ai-launcher-wrap' . ( $show_greeting && $this->settings->get( 'greeting_delay' ) ? ' yuniq-ai-greet-auto' : '' );
		$placeholder    = (string) $this->settings->get( 'input_placeholder', '' );
		?>
		<div id="yuniq-ai-root"
			class="<?php echo esc_attr( $this->root_classes( $extra_root_class ) ); ?>"
			dir="rtl"
			data-theme="<?php echo esc_attr( $this->theme() ); ?>"
			style="<?php echo esc_attr( $this->brand_style() ); ?>">

			<div class="<?php echo esc_attr( $wrap_class ); ?>">
				<button type="button" id="yuniq-ai-launcher" class="<?php echo esc_attr( $launcher_class ); ?>"
					aria-expanded="false"
					aria-controls="yuniq-ai-panel"
					aria-label="<?php echo esc_attr( sprintf( /* translators: %s: assistant name. */ __( 'باز کردن %s', 'yuniq-ai' ), $assistant_name ) ); ?>">
					<?php if ( $this->settings->get( 'show_online_badge' ) ) : ?>
						<span class="yuniq-ai-online-badge"></span>
					<?php endif; ?>
					<span class="yuniq-ai-launcher-icon">
						<?php echo $this->launcher_icon(); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup; the only dynamic part (avatar URL) is escaped inside. ?>
					</span>
					<?php if ( $is_pill ) : ?>
						<span class="yuniq-ai-launcher-label"><?php echo esc_html( $launcher_label ); ?></span>
					<?php endif; ?>
					<span class="yuniq-ai-launcher-close" aria-hidden="true">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" focusable="false"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					</span>
				</button>
				<?php if ( $show_greeting ) : ?>
					<span class="yuniq-ai-launcher-tooltip" aria-hidden="true">
						<?php if ( '' !== $greet_title ) : ?>
							<strong><?php echo esc_html( $greet_title ); ?></strong>
						<?php endif; ?>
						<?php if ( '' !== $greet_text ) : ?>
							<span><?php echo esc_html( $greet_text ); ?></span>
						<?php endif; ?>
					</span>
				<?php endif; ?>
			</div>

			<div id="yuniq-ai-panel" class="yuniq-ai-panel"
				role="dialog"
				aria-modal="true"
				aria-labelledby="yuniq-ai-panel-title"
				aria-hidden="true">

				<?php // "brand" is a flat primary-color fill: it shares every white-on-color rule with the gradient header. ?>
				<header class="yuniq-ai-panel-header <?php echo esc_attr( 'brand' === $header_style ? 'yuniq-ai-header-gradient yuniq-ai-header-flat' : 'yuniq-ai-header-' . $header_style ); ?>">
					<span class="yuniq-ai-header-glow" aria-hidden="true"></span>
					<div class="yuniq-ai-header-brand">
						<?php if ( $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="yuniq-ai-header-logo" width="40" height="40" />
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
						<?php if ( $this->settings->get( 'show_theme_toggle' ) ) : ?>
							<button type="button" class="yuniq-ai-ctrl-btn" id="yuniq-ai-theme-toggle"
								aria-label="<?php esc_attr_e( 'تغییر حالت روشن و تاریک', 'yuniq-ai' ); ?>"
								title="<?php esc_attr_e( 'تغییر حالت روشن و تاریک', 'yuniq-ai' ); ?>">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
							</button>
						<?php endif; ?>
						<button type="button" class="yuniq-ai-ctrl-btn" id="yuniq-ai-panel-close"
							aria-label="<?php esc_attr_e( 'بستن گفتگو', 'yuniq-ai' ); ?>"
							title="<?php esc_attr_e( 'بستن', 'yuniq-ai' ); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
						</button>
					</div>
				</header>

				<div class="yuniq-ai-panel-body" id="yuniq-ai-panel-body">
					<div class="yuniq-ai-intro" id="yuniq-ai-intro">
						<div class="yuniq-ai-intro-icon" aria-hidden="true">
							<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M12 2a5 5 0 0 1 5 5v1h1a3 3 0 0 1 3 3v3a3 3 0 0 1-3 3h-1v1a3 3 0 0 1-3 3h-4a3 3 0 0 1-3-3v-1H6a3 3 0 0 1-3-3v-3a3 3 0 0 1 3-3h1V7a5 5 0 0 1 5-5z"/><circle cx="9.5" cy="12.5" r="1"/><circle cx="14.5" cy="12.5" r="1"/></svg>
						</div>
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
					<?php if ( $this->settings->get( 'live_support_enabled' ) ) : ?>
						<button type="button" class="yuniq-ai-human-btn" id="yuniq-ai-talk-human">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
							<?php esc_html_e( 'صحبت با کارشناس', 'yuniq-ai' ); ?>
						</button>
					<?php endif; ?>
					<div class="yuniq-ai-prompt-ticker" id="yuniq-ai-prompt-ticker">
						<div class="yuniq-ai-ticker-track" id="yuniq-ai-ticker-track"></div>
					</div>
					<form id="yuniq-ai-chat-form" autocomplete="off">
						<label class="yuniq-ai-sr-only" for="yuniq-ai-input"><?php esc_html_e( 'پیام شما', 'yuniq-ai' ); ?></label>
						<textarea id="yuniq-ai-input" name="message" rows="1" maxlength="2000"
							placeholder="<?php echo esc_attr( '' !== $placeholder ? $placeholder : __( 'سوال خود را اینجا بنویسید...', 'yuniq-ai' ) ); ?>"></textarea>
						<button type="submit" id="yuniq-ai-send" aria-label="<?php esc_attr_e( 'ارسال پیام', 'yuniq-ai' ); ?>">
							<svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
						</button>
					</form>
					<?php
					/**
					 * Filters the credit line under the chat input.
					 *
					 * Return an empty string to remove it.
					 *
					 * @param string $html Credit markup.
					 */
					$powered = $this->settings->get( 'show_powered_by' )
						? (string) apply_filters( 'yuniq_ai_powered_by_html', esc_html( (string) $this->settings->get( 'powered_by_text', '' ) ) )
						: '';
					?>
					<?php if ( '' !== trim( $powered ) ) : ?>
						<div class="yuniq-ai-powered"><?php echo wp_kses_post( $powered ); ?></div>
					<?php endif; ?>
				</footer>
			</div>
		</div>
		<?php
	}
}
