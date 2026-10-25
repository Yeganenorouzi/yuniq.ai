<?php
/**
 * Plugin settings: defaults, reads and sanitization.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for the plugin's option array.
 */
final class Settings {

	/**
	 * Option name holding every plugin setting.
	 */
	const OPTION_KEY = 'yuniq_ai_settings';

	/**
	 * Settings group used by the Settings API.
	 */
	const OPTION_GROUP = 'yuniq_ai_settings_group';

	/**
	 * Placeholder rendered in place of a stored API key.
	 *
	 * The real key is never written back into the HTML. When this exact
	 * value is submitted, the stored key is kept unchanged.
	 */
	const SECRET_MASK = '__YUNIQ_AI_UNCHANGED__';

	/**
	 * Settings stored encrypted (see {@see Support\Secret}).
	 */
	const SECRET_KEYS = array( 'api_key', 'telegram_bot_token', 'bale_bot_token' );

	/**
	 * Hidden field marking a form that carries only some settings.
	 */
	const PARTIAL_FLAG = '_partial';

	/**
	 * Cached option array for this request.
	 *
	 * @var array|null
	 */
	private $cache;

	/**
	 * Default values for a fresh install.
	 *
	 * No credentials are shipped with the plugin: `api_key` is empty and
	 * the endpoint points at OpenAI, which the site owner overrides in
	 * the settings screen.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// AI configuration.
			'ai_provider'           => 'openai',
			'api_key'               => '',
			'api_endpoint'          => 'https://api.openai.com/v1/chat/completions',
			'model'                 => 'gpt-4o-mini',
			'temperature'           => 0.7,
			'max_tokens'            => 1024,
			'system_prompt'         => 'تو دستیار هوشمند رسمی این وب‌سایت هستی. همیشه اول از پایگاه دانش وب‌سایت استفاده کن و مؤدب و دقیق پاسخ بده.',
			'reply_tone'            => 'friendly',
			'reply_length'          => 'short',
			'reply_language'        => 'auto',
			'links_policy'          => 'on_request',
			'suggest_options'       => true,
			'suggest_mode'          => 'smart',
			'starter_mode'          => 'auto',
			'history_limit'         => 6,
			'context_documents'     => 5,
			'request_timeout'       => 60,

			// Crawler.
			'content_types'         => array( 'post', 'page' ),
			'include_slugs'         => '',
			'exclude_slugs'         => "/cart/\n/checkout/\n/account/\n/my-account/\n/wp-admin/",
			'crawl_status'          => 'idle',

			// Crawler sources.
			'wc_products'           => true,
			'wc_product_categories' => true,
			'wc_product_tags'       => true,
			'wc_attributes'         => true,
			'wc_variations'         => true,
			'wc_short_desc'         => true,
			'wc_price'              => true,
			'wc_stock'              => true,
			'wc_sku'                => true,
			'wc_dimensions'         => false,
			'wc_reviews'            => false,
			'wc_skip_outofstock'    => false,
			'wc_skip_hidden'        => true,
			'index_custom_fields'   => false,
			// Per post type, the custom field keys (ACF, JetEngine, or any
			// other plugin that stores values as plain post meta) left
			// unchecked on the crawler tab. Fields not listed here yet are
			// still indexed — only an explicit uncheck excludes one — so a
			// brand-new field a plugin adds later is included by default.
			'excluded_fields'       => array(),
			'site_profile'          => true,
			'context_detail'        => 'normal',
			'business_info'         => '',
			'synonyms'              => '',
			'wp_pages'              => true,
			'wp_posts'              => true,
			'wp_categories'         => true,
			'wp_tags'               => false,
			'wp_custom_post_types'  => false,
			'custom_post_types'     => array(),

			// Presentation.
			'assistant_name'        => 'دستیار هوشمند',
			'welcome_message'       => 'سلام! چطور می‌تونم کمکتون کنم؟',
			'avatar_url'            => '',
			'logo_url'              => '',
			'primary_color'         => '#263DFF',
			'secondary_color'       => '#111B55',
			'header_style'          => 'gradient',
			'theme'                 => 'auto',
			'widget_position'       => 'bottom-right',
			'custom_position_x'     => 24,
			'custom_position_y'     => 24,
			'chat_width'            => 420,
			'chat_height'           => 720,
			'border_radius'         => 20,
			'enable_animations'     => true,
			'header_subtitle'       => 'پاسخ سریع، دقیق و حرفه‌ای',
			'help_title'            => 'چطور می‌توانم کمک کنم؟',
			'help_subtitle'         => 'سوال خود را مطرح کنید یا از گزینه‌های زیر استفاده کنید.',
			'suggestion_text'       => 'می‌توانید درباره خدمات، محصولات و قیمت‌ها سوال کنید.',
			'quick_actions'         => array(
				array(
					'label'  => 'سوالات متداول',
					'prompt' => 'سوالات متداول را بگو',
					'desc'   => 'پاسخ سریع',
					'link'   => '',
				),
				array(
					'label'  => 'خدمات ما',
					'prompt' => 'خدمات شما چیست؟',
					'desc'   => 'آشنایی با خدمات',
					'link'   => '',
				),
				array(
					'label'  => 'قیمت‌ها',
					'prompt' => 'گزینه‌های قیمت‌گذاری چیست؟',
					'desc'   => 'دریافت اطلاعات',
					'link'   => '',
				),
				array(
					'label'  => 'مشاوره رایگان',
					'prompt' => 'چطور می‌توانم مشاوره رایگان بگیرم؟',
					'desc'   => 'همین حالا شروع کنید',
					'link'   => '',
				),
			),

			// Launcher button.
			'launcher_icon'         => 'bot',
			'launcher_shape'        => 'squircle',
			'launcher_size'         => 62,
			'launcher_label'        => 'گفتگو با ما',
			'launcher_bg'           => 'gradient',
			'show_online_badge'     => true,
			'greeting_enabled'      => true,
			'greeting_title'        => 'سلام! 👋',
			'greeting_text'         => 'سوالی دارید؟ من اینجام.',
			'greeting_delay'        => 2,

			// Typography and chat window.
			'font_family'           => 'vazirmatn',
			'custom_font'           => '',
			'font_size'             => 14,
			'user_bubble_color'     => '',
			'bot_bubble_color'      => '',
			'input_placeholder'     => 'سوال خود را اینجا بنویسید...',
			'show_theme_toggle'     => true,
			'show_powered_by'       => true,
			'powered_by_text'       => 'قدرت‌گرفته با هوش مصنوعی | Yuniq.ai',

			// Where the widget shows up.
			'display_rule'          => 'all',
			'display_paths'         => '',
			'hide_on_mobile'        => false,
			'hide_on_desktop'       => false,
			'custom_css'            => '',

			// Live support (human handoff).
			'live_support_enabled'  => false,
			'live_support_mode'     => 'both',
			'agent_display_name'    => 'کارشناس پشتیبانی',
			'notify_channels'       => array( 'email' ),
			'notify_email'          => get_option( 'admin_email' ),
			'telegram_bot_token'    => '',
			'telegram_chat_id'      => '',
			'bale_bot_token'        => '',
			'bale_chat_id'          => '',
			'lead_forms'            => array(
				array(
					'key'           => 'consult',
					'title'         => 'درخواست مشاوره',
					'trigger_label' => 'درخواست مشاوره رایگان',
					'fields'        => array(
						array(
							'name'     => 'name',
							'label'    => 'نام شما',
							'type'     => 'text',
							'required' => true,
						),
						array(
							'name'     => 'phone',
							'label'    => 'شماره تماس',
							'type'     => 'tel',
							'required' => true,
						),
					),
				),
			),
			'product_cards_enabled' => class_exists( 'WooCommerce' ),
			'full_page_enabled'     => false,
			'full_page_slug'        => 'ai-assistant',

			// Security and privacy.
			'ip_source'             => 'remote_addr',
			'rate_profile'          => 'balanced',
			'daily_limit'           => '0',
			'data_retention'        => '0',

			// General. Off until the owner explicitly turns it on (the 4th
			// setup step): a freshly activated site has no API key yet, so
			// a widget visible by default would just show every visitor a
			// chat bubble that answers with an apology. Once the settings
			// screen is saved even once, this is always the submitted
			// checkbox state from then on — this default only covers the
			// window between activation and that first save.
			'enabled'               => false,
		);
	}

	/**
	 * The full settings array, merged over the defaults.
	 *
	 * @return array
	 */
	public function all() {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION_KEY, array() );
			$this->cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );

			// Credentials are encrypted at rest; everything that reads the
			// settings gets the usable value.
			foreach ( self::SECRET_KEYS as $secret ) {
				$this->cache[ $secret ] = Support\Secret::decrypt( $this->cache[ $secret ] );
			}
		}

		return $this->cache;
	}

	/**
	 * Read a single setting.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default Returned when the key is missing.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$all = $this->all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Forget the per-request cache, so the next read hits the database.
	 *
	 * @return void
	 */
	public function flush() {
		$this->cache = null;
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * Runs as the `sanitize_callback` for `register_setting()`; the values
	 * arrive already unslashed.
	 *
	 * @param mixed $input Raw submitted values.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		// The setup wizard posts small forms (step one: the AI connection;
		// step four: the on/off switch). Run through the full sanitizer they
		// would reset every field they do not carry to its default — step
		// four used to put an AvalAI owner back on OpenAI's endpoint, so the
		// key "vanished" right after the wizard said everything worked. A
		// partial form therefore only changes the fields it actually sent.
		if ( ! empty( $input[ self::PARTIAL_FLAG ] ) ) {
			unset( $input[ self::PARTIAL_FLAG ] );

			$sanitized = $this->sanitize_all( $input );
			$existing  = get_option( self::OPTION_KEY, array() );
			$output    = wp_parse_args( is_array( $existing ) ? $existing : array(), self::defaults() );

			foreach ( array_keys( $input ) as $key ) {
				if ( array_key_exists( $key, $sanitized ) ) {
					$output[ $key ] = $sanitized[ $key ];
				}
			}

			foreach ( self::SECRET_KEYS as $secret ) {
				$output[ $secret ] = Support\Secret::encrypt( isset( $output[ $secret ] ) ? (string) $output[ $secret ] : '' );
			}

			$this->flush();

			return $output;
		}

		return $this->sanitize_all( $input );
	}

	/**
	 * Sanitize a complete settings submission (the main settings screen).
	 *
	 * @param array $input Raw submitted values.
	 * @return array
	 */
	private function sanitize_all( array $input ) {
		// options.php has already unslashed the submitted values. Unslashing
		// again here ate every backslash in the custom CSS and the prompt
		// (`content: "\201C"` came back as `content: "201C"`).
		$input    = is_array( $input ) ? $input : array();
		$existing = get_option( self::OPTION_KEY, array() );
		$output   = wp_parse_args( is_array( $existing ) ? $existing : array(), self::defaults() );

		// --- AI configuration -------------------------------------------------
		$provider              = isset( $input['ai_provider'] ) ? sanitize_key( $input['ai_provider'] ) : 'openai';
		$output['ai_provider'] = Ai\Presets::exists( $provider ) ? $provider : Ai\Presets::CUSTOM;

		// The key is only replaced when the form actually carried a new one.
		$output['api_key'] = $this->submitted_secret( $input, $output, 'api_key' );

		$endpoint                = isset( $input['api_endpoint'] ) ? esc_url_raw( trim( $input['api_endpoint'] ) ) : '';
		$output['api_endpoint']  = $endpoint ? $endpoint : 'https://api.openai.com/v1/chat/completions';
		$model                   = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : '';
		$output['model']         = $model ? $model : 'gpt-4o-mini';
		$output['temperature']   = isset( $input['temperature'] ) ? min( 2.0, max( 0.0, (float) $input['temperature'] ) ) : 0.7;
		$output['max_tokens']    = isset( $input['max_tokens'] ) ? min( 8192, max( 64, absint( $input['max_tokens'] ) ) ) : 1024;
		$output['system_prompt'] = isset( $input['system_prompt'] ) ? sanitize_textarea_field( $input['system_prompt'] ) : '';

		$output['reply_tone']      = self::pick( $input, 'reply_tone', array_keys( self::choices( 'reply_tone' ) ), 'friendly' );
		$output['reply_length']    = self::pick( $input, 'reply_length', array_keys( self::choices( 'reply_length' ) ), 'short' );
		$output['reply_language']  = self::pick( $input, 'reply_language', array_keys( self::choices( 'reply_language' ) ), 'auto' );
		$output['links_policy']    = self::pick( $input, 'links_policy', array_keys( self::choices( 'links_policy' ) ), 'on_request' );
		$output['starter_mode']    = self::pick( $input, 'starter_mode', array_keys( self::choices( 'starter_mode' ) ), 'auto' );
		$output['suggest_mode']    = self::pick( $input, 'suggest_mode', array_keys( self::choices( 'suggest_mode' ) ), 'smart' );
		$output['suggest_options'] = 'off' !== $output['suggest_mode'];

		$output['history_limit']     = self::clamp_int( $input, 'history_limit', 0, 20, 6 );
		$output['context_documents'] = self::clamp_int( $input, 'context_documents', 1, 12, 5 );
		$output['request_timeout']   = self::clamp_int( $input, 'request_timeout', 10, 300, 60 );

		// --- Crawler ----------------------------------------------------------
		$output['include_slugs'] = isset( $input['include_slugs'] ) ? sanitize_textarea_field( $input['include_slugs'] ) : '';
		$output['exclude_slugs'] = isset( $input['exclude_slugs'] ) ? sanitize_textarea_field( $input['exclude_slugs'] ) : '';

		$bool_keys = array(
			'wc_product_categories',
			'wc_products',
			'wc_product_tags',
			'wc_attributes',
			'wc_variations',
			'wc_short_desc',
			'wc_price',
			'wc_stock',
			'wc_sku',
			'wc_dimensions',
			'wc_reviews',
			'wc_skip_outofstock',
			'wc_skip_hidden',
			'index_custom_fields',
			'site_profile',
			'wp_pages',
			'wp_posts',
			'wp_categories',
			'wp_tags',
			'wp_custom_post_types',
		);
		foreach ( $bool_keys as $bool_key ) {
			$output[ $bool_key ] = ! empty( $input[ $bool_key ] );
		}

		$output['custom_post_types'] = ( isset( $input['custom_post_types'] ) && is_array( $input['custom_post_types'] ) )
			? array_values( array_map( 'sanitize_key', $input['custom_post_types'] ) )
			: array();

		$output['excluded_fields'] = $this->derive_excluded_fields( $input, (array) $output['excluded_fields'] );

		$output['content_types'] = $this->derive_content_types( $output );

		$output['context_detail'] = self::pick( $input, 'context_detail', array_keys( self::choices( 'context_detail' ) ), 'normal' );
		$output['business_info']  = isset( $input['business_info'] ) ? Support\Text::truncate( sanitize_textarea_field( $input['business_info'] ), 4000, '' ) : '';
		$output['synonyms']       = isset( $input['synonyms'] ) ? Support\Text::truncate( sanitize_textarea_field( $input['synonyms'] ), 4000, '' ) : '';

		// --- Presentation -----------------------------------------------------
		$text_fields = array(
			'assistant_name'  => 'دستیار هوشمند',
			'header_subtitle' => '',
			'help_title'      => '',
			'help_subtitle'   => '',
			'suggestion_text' => '',
		);
		foreach ( $text_fields as $field => $fallback ) {
			$output[ $field ] = isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : $fallback;
		}

		$output['welcome_message']   = isset( $input['welcome_message'] ) ? sanitize_textarea_field( $input['welcome_message'] ) : '';
		$output['avatar_url']        = isset( $input['avatar_url'] ) ? esc_url_raw( $input['avatar_url'] ) : '';
		$output['logo_url']          = isset( $input['logo_url'] ) ? esc_url_raw( $input['logo_url'] ) : '';
		$output['primary_color']     = isset( $input['primary_color'] ) ? (string) sanitize_hex_color( $input['primary_color'] ) : '#263DFF';
		$output['secondary_color']   = isset( $input['secondary_color'] ) ? (string) sanitize_hex_color( $input['secondary_color'] ) : '#111B55';

		$output['header_style']    = self::pick( $input, 'header_style', array( 'gradient', 'brand', 'solid' ), 'gradient' );
		$output['theme']           = self::pick( $input, 'theme', array( 'auto', 'light', 'dark' ), 'auto' );
		$output['widget_position'] = self::pick( $input, 'widget_position', array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' ), 'bottom-right' );

		// Distance from the chosen screen corner, in px.
		$output['custom_position_x'] = self::clamp_int( $input, 'custom_position_x', 0, 400, 24 );
		$output['custom_position_y'] = self::clamp_int( $input, 'custom_position_y', 0, 400, 24 );
		$output['chat_width']        = self::clamp_int( $input, 'chat_width', 320, 560, 420 );
		$output['chat_height']       = self::clamp_int( $input, 'chat_height', 400, 900, 720 );
		$output['border_radius']     = self::clamp_int( $input, 'border_radius', 0, 32, 20 );
		$output['enable_animations'] = ! empty( $input['enable_animations'] );
		$output['enabled']           = ! empty( $input['enabled'] );

		// Launcher button.
		$output['launcher_icon']       = self::pick( $input, 'launcher_icon', array( 'bot', 'chat', 'sparkle', 'headset', 'avatar' ), 'bot' );
		$output['launcher_shape']    = self::pick( $input, 'launcher_shape', array( 'squircle', 'circle', 'pill' ), 'squircle' );
		$output['launcher_bg']       = self::pick( $input, 'launcher_bg', array( 'gradient', 'solid' ), 'gradient' );
		$output['launcher_size']     = self::clamp_int( $input, 'launcher_size', 44, 88, 62 );
		$output['launcher_label']    = isset( $input['launcher_label'] ) ? sanitize_text_field( $input['launcher_label'] ) : '';
		$output['show_online_badge'] = ! empty( $input['show_online_badge'] );
		$output['greeting_enabled']  = ! empty( $input['greeting_enabled'] );
		$output['greeting_title']    = isset( $input['greeting_title'] ) ? sanitize_text_field( $input['greeting_title'] ) : '';
		$output['greeting_text']     = isset( $input['greeting_text'] ) ? sanitize_text_field( $input['greeting_text'] ) : '';
		$output['greeting_delay']    = self::clamp_int( $input, 'greeting_delay', 0, 60, 2 );

		// Typography and chat window.
		$output['font_family']       = self::pick( $input, 'font_family', array( 'vazirmatn', 'inherit', 'custom' ), 'vazirmatn' );
		// Ends up inside a CSS declaration: anything that could close it is dropped.
		$output['custom_font']       = isset( $input['custom_font'] ) ? trim( preg_replace( '/[;{}<>()\\\\]/', '', sanitize_text_field( $input['custom_font'] ) ) ) : '';
		$output['font_size']         = self::clamp_int( $input, 'font_size', 12, 18, 14 );
		$output['user_bubble_color'] = isset( $input['user_bubble_color'] ) ? (string) sanitize_hex_color( $input['user_bubble_color'] ) : '';
		$output['bot_bubble_color']  = isset( $input['bot_bubble_color'] ) ? (string) sanitize_hex_color( $input['bot_bubble_color'] ) : '';
		$output['input_placeholder'] = isset( $input['input_placeholder'] ) ? sanitize_text_field( $input['input_placeholder'] ) : '';
		$output['show_theme_toggle'] = ! empty( $input['show_theme_toggle'] );
		$output['show_powered_by']   = ! empty( $input['show_powered_by'] );
		$output['powered_by_text']   = isset( $input['powered_by_text'] ) ? sanitize_text_field( $input['powered_by_text'] ) : '';

		// Where the widget shows up.
		$output['display_rule']    = self::pick( $input, 'display_rule', array( 'all', 'include', 'exclude' ), 'all' );
		$output['display_paths']   = isset( $input['display_paths'] ) ? sanitize_textarea_field( $input['display_paths'] ) : '';
		$output['hide_on_mobile']  = ! empty( $input['hide_on_mobile'] );
		$output['hide_on_desktop'] = ! empty( $input['hide_on_desktop'] );

		// Raw CSS is only accepted from users trusted with unfiltered HTML
		// (not the case for site admins on multisite); others keep the old value.
		if ( isset( $input['custom_css'] ) && current_user_can( 'unfiltered_html' ) ) {
			$css                  = wp_strip_all_tags( (string) $input['custom_css'] );
			$output['custom_css'] = str_ireplace( '</style', '', $css );
		}

		// The form always sends `quick_actions_present`, so removing every
		// card is saved as "no cards" instead of silently keeping the old ones.
		if ( isset( $input['quick_actions'] ) && is_array( $input['quick_actions'] ) ) {
			$output['quick_actions'] = $this->sanitize_quick_actions( $input['quick_actions'] );
		} elseif ( ! empty( $input['quick_actions_present'] ) ) {
			$output['quick_actions'] = array();
		}

		// --- Live support (human handoff) --------------------------------------
		$output['live_support_enabled'] = ! empty( $input['live_support_enabled'] );

		$ls_mode                    = isset( $input['live_support_mode'] ) ? sanitize_key( $input['live_support_mode'] ) : 'both';
		$output['live_support_mode'] = in_array( $ls_mode, array( 'manual', 'auto_suggest', 'both' ), true ) ? $ls_mode : 'both';

		$output['agent_display_name'] = isset( $input['agent_display_name'] ) && '' !== trim( (string) $input['agent_display_name'] )
			? sanitize_text_field( $input['agent_display_name'] )
			: 'کارشناس پشتیبانی';

		// Not used to gate sending any more (see Notifications\Dispatcher::
		// channel_readiness()) — kept only so a `yuniq_ai_notify_channels`
		// filter adding a custom channel still has somewhere to read an
		// owner's on/off choice for it from, same as before.
		$output['notify_channels'] = ( isset( $input['notify_channels'] ) && is_array( $input['notify_channels'] ) )
			? array_values( array_intersect( array_map( 'sanitize_key', $input['notify_channels'] ), array( 'email', 'telegram', 'bale' ) ) )
			: array();

		$notify_email           = isset( $input['notify_email'] ) ? sanitize_email( $input['notify_email'] ) : '';
		$output['notify_email'] = $notify_email ? $notify_email : get_option( 'admin_email' );

		foreach ( array( 'telegram_bot_token', 'bale_bot_token' ) as $secret_key ) {
			$output[ $secret_key ] = $this->submitted_secret( $input, $output, $secret_key );
		}

		$output['telegram_chat_id'] = isset( $input['telegram_chat_id'] ) ? sanitize_text_field( $input['telegram_chat_id'] ) : '';
		$output['bale_chat_id']     = isset( $input['bale_chat_id'] ) ? sanitize_text_field( $input['bale_chat_id'] ) : '';

		if ( isset( $input['lead_forms'] ) && is_array( $input['lead_forms'] ) ) {
			$output['lead_forms'] = $this->sanitize_lead_forms( $input['lead_forms'] );
		}

		$output['product_cards_enabled'] = ! empty( $input['product_cards_enabled'] );
		$output['full_page_enabled']     = ! empty( $input['full_page_enabled'] );

		$full_page_slug              = isset( $input['full_page_slug'] ) ? sanitize_title( $input['full_page_slug'] ) : '';
		$output['full_page_slug']    = $full_page_slug ? $full_page_slug : 'ai-assistant';

		// --- Security and privacy ---------------------------------------------
		foreach ( array( 'ip_source' => 'remote_addr', 'rate_profile' => 'balanced', 'daily_limit' => '0', 'data_retention' => '0' ) as $choice_key => $choice_default ) {
			$submitted             = isset( $input[ $choice_key ] ) && is_scalar( $input[ $choice_key ] ) ? (string) $input[ $choice_key ] : '';
			$output[ $choice_key ] = array_key_exists( $submitted, self::choices( $choice_key ) ) ? $submitted : $choice_default;
		}

		// Whatever path a credential took to get here (new value, kept value,
		// or a plain-text one saved by an older version), it leaves encrypted.
		foreach ( self::SECRET_KEYS as $secret ) {
			$output[ $secret ] = Support\Secret::encrypt( isset( $output[ $secret ] ) ? (string) $output[ $secret ] : '' );
		}

		// Empty colors fall back rather than being stored as ''.
		if ( ! $output['primary_color'] ) {
			$output['primary_color'] = '#263DFF';
		}
		if ( ! $output['secondary_color'] ) {
			$output['secondary_color'] = '#111B55';
		}

		$this->flush();

		return $output;
	}

	/**
	 * Decide what a credential field keeps after a save.
	 *
	 * Three cases, and the third is the reason this is not inline any more:
	 *
	 * 1. Nothing submitted (the field is not on this form) — keep the stored
	 *    value.
	 * 2. The {@see self::SECRET_MASK} placeholder, which is all the form ever
	 *    renders for a credential already on the server — keep it too; the
	 *    owner did not touch the field.
	 * 3. A value this very method already encrypted — keep it verbatim.
	 *
	 * That third case looks impossible from the form's side, and is not:
	 * WordPress runs a `register_setting()` sanitize callback TWICE whenever
	 * the stored option still equals the registered default. update_option()
	 * sees `default_option_… === $old_value`, hands the save off to
	 * add_option(), and add_option() sanitizes the already-sanitized array a
	 * second time. On a fresh install that is exactly the state of the very
	 * first save — the activator writes self::defaults() and nothing has
	 * changed it yet — so the second pass was handed this method's own
	 * encrypted output, rejected it as "not a new key", and fell back to the
	 * empty stored one. The owner typed a key into step one of the wizard,
	 * pressed "ذخیره و ادامه", and got step one back with an empty field and
	 * "هنوز کلیدی ذخیره نشده".
	 *
	 * A decrypt check, not just the `yq1:` prefix, decides whether a value
	 * really is ours: an arbitrary string starting with the prefix is not,
	 * and would be stored as an unreadable credential.
	 *
	 * @param array  $input  Submitted values.
	 * @param array  $output Values being built, carrying the stored value.
	 * @param string $key    Setting name.
	 * @return string Plain text, except when it is our own encrypted value
	 *                coming back around; sanitize() encrypts either way.
	 */
	private function submitted_secret( array $input, array $output, $key ) {
		$current = isset( $output[ $key ] ) ? (string) $output[ $key ] : '';

		if ( ! isset( $input[ $key ] ) || ! is_scalar( $input[ $key ] ) ) {
			return $current;
		}

		$submitted = trim( sanitize_text_field( (string) $input[ $key ] ) );

		if ( Support\Secret::is_encrypted( $submitted ) ) {
			return '' !== Support\Secret::decrypt( $submitted ) ? $submitted : $current;
		}

		if ( self::SECRET_MASK === $submitted ) {
			return $current;
		}

		return $submitted;
	}

	/**
	 * The fixed-choice settings and their labels: one list shared by the
	 * settings screen (which draws the option cards from it) and the
	 * sanitizer (which accepts nothing else).
	 *
	 * @param string $key Setting name.
	 * @return array<string,array{0:string,1:string}> Value => [label, hint].
	 */
	public static function choices( $key ) {
		$choices = array(
			'reply_tone'     => array(
				'friendly' => array( 'دوستانه', 'گرم و صمیمی، مثل یک همکار خوش‌برخورد' ),
				'formal'   => array( 'رسمی', 'محترمانه و دقیق، مناسب سازمان‌ها' ),
				'sales'    => array( 'فروشنده', 'پرانرژی؛ مزیت‌ها را می‌گوید و به خرید راهنمایی می‌کند' ),
				'expert'   => array( 'کارشناس', 'تخصصی و دقیق، مثل یک مشاور فنی' ),
			),
			'reply_length'   => array(
				'short'    => array( 'کوتاه', '۱ تا ۳ جمله؛ سریع‌ترین و کم‌هزینه‌ترین' ),
				'medium'   => array( 'متوسط', 'یک پاراگراف کوتاه' ),
				'detailed' => array( 'کامل', 'توضیح مفصل با جزئیات' ),
			),
			'reply_language' => array(
				'auto' => array( 'زبان بازدیدکننده', 'به هر زبانی بپرسد، همان‌طور پاسخ می‌دهد' ),
				'fa'   => array( 'همیشه فارسی', 'حتی اگر به زبان دیگری بپرسد' ),
				'en'   => array( 'همیشه انگلیسی', 'برای سایت‌های انگلیسی‌زبان' ),
			),
			'links_policy'   => array(
				'on_request' => array( 'فقط وقتی بخواهد', 'لینک فقط با درخواست صریح بازدیدکننده' ),
				'helpful'    => array( 'هر جا مفید بود', 'لینک صفحه مرتبط را خودش پیشنهاد می‌دهد' ),
			),
			'starter_mode'   => array(
				'auto'   => array( 'خودکار از سایت', 'دسته‌های اصلی محصولات، ارسال و مرجوعی، پیگیری سفارش، مشاوره و کارشناس (پیشنهادی)' ),
				'manual' => array( 'دستی', 'فقط گزینه‌هایی که خودتان در پایین تعریف می‌کنید' ),
				'off'    => array( 'بدون گزینه', 'فقط کادر پیام نمایش داده شود' ),
			),
			'suggest_mode'   => array(
				'smart'   => array( 'هوشمند', 'انتخاب بین گزینه‌ها + قدم بعدی مرتبط (رنگ، قیمت، ارسال…)' ),
				'choices' => array( 'فقط هنگام انتخاب', 'فقط وقتی چند محصول یا مدل معرفی شده و باید یکی انتخاب شود' ),
				'off'     => array( 'خاموش', 'هیچ دکمه‌ای زیر پاسخ‌ها نمایش داده نشود' ),
			),
			'context_detail' => array(
				'compact' => array( 'خلاصه', 'کم‌هزینه‌ترین؛ برای صفحات کوتاه' ),
				'normal'  => array( 'معمولی', 'تعادل دقت و هزینه (پیشنهادی)' ),
				'full'    => array( 'کامل', 'متن بلند هر صفحه؛ دقیق‌تر ولی گران‌تر' ),
			),
			'rate_profile'   => array(
				'strict'   => array( 'سخت‌گیرانه', 'هر بازدیدکننده ۱۰ پیام در ۱۰ دقیقه؛ کمترین هزینه' ),
				'balanced' => array( 'متعادل', 'هر بازدیدکننده ۲۰ پیام در ۱۰ دقیقه (پیشنهادی)' ),
				'relaxed'  => array( 'آزاد', 'هر بازدیدکننده ۴۰ پیام در ۱۰ دقیقه' ),
			),
			'daily_limit'    => array(
				'0'     => array( 'بدون سقف', 'فقط محدودیت هر بازدیدکننده اعمال می‌شود' ),
				'200'   => array( '۲۰۰ پیام در روز', 'سایت کوچک یا دوره آزمایشی' ),
				'1000'  => array( '۱٬۰۰۰ پیام در روز', 'سایت متوسط' ),
				'5000'  => array( '۵٬۰۰۰ پیام در روز', 'سایت پربازدید' ),
			),
			'ip_source'      => array(
				'remote_addr' => array( 'اتصال مستقیم', 'سایت پشت CDN نیست (پیش‌فرض و امن‌ترین)' ),
				'cloudflare'  => array( 'کلودفلر', 'سایت از Cloudflare عبور می‌کند' ),
				'forwarded'   => array( 'CDN / پراکسی دیگر', 'آروان‌کلاود و مشابه (X-Forwarded-For)' ),
				'real_ip'     => array( 'Nginx پراکسی', 'هدر X-Real-IP' ),
			),
			'data_retention' => array(
				'0'   => array( 'همیشه نگه دار', 'چیزی خودکار پاک نمی‌شود' ),
				'30'  => array( '۳۰ روز', 'بیشترین حفظ حریم خصوصی' ),
				'90'  => array( '۹۰ روز', 'متعادل' ),
				'365' => array( 'یک سال', 'برای تحلیل بلندمدت' ),
			),
		);

		return isset( $choices[ $key ] ) ? $choices[ $key ] : array();
	}

	/**
	 * Read an integer from the submitted input, clamped to a range.
	 *
	 * @param array  $input    Submitted values.
	 * @param string $key      Field name.
	 * @param int    $min      Lower bound.
	 * @param int    $max      Upper bound.
	 * @param int    $fallback Used when the field is missing.
	 * @return int
	 */
	private static function clamp_int( array $input, $key, $min, $max, $fallback ) {
		if ( ! isset( $input[ $key ] ) || '' === $input[ $key ] ) {
			return $fallback;
		}

		return min( $max, max( $min, absint( $input[ $key ] ) ) );
	}

	/**
	 * Read one of a fixed set of values from the submitted input.
	 *
	 * @param array    $input    Submitted values.
	 * @param string   $key      Field name.
	 * @param string[] $allowed  Accepted values.
	 * @param string   $fallback Used when the value is missing or unknown.
	 * @return string
	 */
	private static function pick( array $input, $key, array $allowed, $fallback ) {
		$value = isset( $input[ $key ] ) ? sanitize_key( $input[ $key ] ) : '';

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Keep a submitted post-meta key comparable to the real column value:
	 * same character set `sanitize_key()` allows, but case is preserved.
	 *
	 * @param mixed $key Raw value.
	 * @return string
	 */
	private static function clean_meta_key( $key ) {
		return is_scalar( $key ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $key ) : '';
	}

	/**
	 * Translate the source checkboxes into a post-type list for WP_Query.
	 *
	 * @param array $output Settings being built.
	 * @return array
	 */
	private function derive_content_types( array $output ) {
		$types = array();

		if ( ! empty( $output['wp_posts'] ) ) {
			$types[] = 'post';
		}
		if ( ! empty( $output['wp_pages'] ) ) {
			$types[] = 'page';
		}
		if ( ! empty( $output['wc_products'] ) ) {
			$types[] = 'product';
		}
		if ( ! empty( $output['custom_post_types'] ) ) {
			$types = array_merge( $types, $output['custom_post_types'] );
		}

		$types = array_values( array_unique( array_filter( $types ) ) );

		return $types ? $types : array( 'post', 'page' );
	}

	/**
	 * Translate the crawler tab's per-post-type field checkboxes into the
	 * stored exclude list.
	 *
	 * The form submits every field it knows about for a post type as
	 * `all_fields[post_type]` (one hidden, comma-separated value, built
	 * from the same live lookup the checkboxes were drawn from) alongside
	 * `included_fields[post_type][]` for the ones left checked; a field
	 * present in the first but not the second was unchecked. A post type
	 * whose `all_fields` entry is missing — its checklist was never
	 * rendered this submission, e.g. the type is not selected right now —
	 * keeps whatever was stored for it before, instead of being read as
	 * "everything excluded".
	 *
	 * @param array $input    Submitted values.
	 * @param array $existing Previously stored exclude list.
	 * @return array<string,string[]>
	 */
	private function derive_excluded_fields( array $input, array $existing ) {
		$all      = isset( $input['all_fields'] ) && is_array( $input['all_fields'] ) ? $input['all_fields'] : array();
		$included = isset( $input['included_fields'] ) && is_array( $input['included_fields'] ) ? $input['included_fields'] : array();

		foreach ( $all as $post_type => $csv ) {
			$post_type = sanitize_key( $post_type );

			if ( '' === $post_type ) {
				continue;
			}

			// Not sanitize_key(): it lowercases, and a meta key with any
			// uppercase character would then never match the real column
			// value Indexer::custom_fields() compares it against.
			$known = array_values( array_filter( array_map( array( __CLASS__, 'clean_meta_key' ), explode( ',', (string) $csv ) ) ) );
			$kept  = isset( $included[ $post_type ] ) && is_array( $included[ $post_type ] )
				? array_map( array( __CLASS__, 'clean_meta_key' ), $included[ $post_type ] )
				: array();

			$excluded = array_values( array_diff( $known, $kept ) );

			if ( $excluded ) {
				$existing[ $post_type ] = $excluded;
			} else {
				unset( $existing[ $post_type ] );
			}
		}

		return $existing;
	}

	/**
	 * Sanitize the quick-action cards, dropping rows without a label.
	 *
	 * @param array $rows Submitted rows.
	 * @return array
	 */
	private function sanitize_quick_actions( array $rows ) {
		$actions = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['label'] ) ) {
				continue;
			}

			$actions[] = array(
				'label'  => sanitize_text_field( $row['label'] ),
				'prompt' => isset( $row['prompt'] ) ? sanitize_text_field( $row['prompt'] ) : '',
				'desc'   => isset( $row['desc'] ) ? sanitize_text_field( $row['desc'] ) : '',
				'link'   => isset( $row['link'] ) ? esc_url_raw( $row['link'] ) : '',
			);
		}

		return $actions;
	}

	/**
	 * Sanitize the in-chat lead-capture form definitions.
	 *
	 * @param array $rows Submitted form rows, each with a `fields` sub-array.
	 * @return array
	 */
	private function sanitize_lead_forms( array $rows ) {
		$forms = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['title'] ) ) {
				continue;
			}

			$key = isset( $row['key'] ) ? sanitize_key( $row['key'] ) : '';

			$fields = array();
			if ( isset( $row['fields'] ) && is_array( $row['fields'] ) ) {
				foreach ( $row['fields'] as $field ) {
					if ( ! is_array( $field ) || empty( $field['label'] ) ) {
						continue;
					}

					// The admin UI only asks for a label; the field's
					// internal name is derived from it automatically.
					$name = ! empty( $field['name'] ) ? sanitize_key( $field['name'] ) : sanitize_key( sanitize_title( $field['label'] ) );

					if ( ! $name ) {
						continue;
					}

					$type = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';

					$fields[] = array(
						'name'     => $name,
						'label'    => sanitize_text_field( $field['label'] ),
						'type'     => in_array( $type, array( 'text', 'tel', 'email', 'textarea' ), true ) ? $type : 'text',
						'required' => ! empty( $field['required'] ),
					);
				}
			}

			if ( ! $fields ) {
				continue;
			}

			$forms[] = array(
				'key'           => $key ? $key : sanitize_key( sanitize_title( $row['title'] ) ),
				'title'         => sanitize_text_field( $row['title'] ),
				'trigger_label' => isset( $row['trigger_label'] ) ? sanitize_text_field( $row['trigger_label'] ) : sanitize_text_field( $row['title'] ),
				'fields'        => $fields,
			);
		}

		return $forms;
	}
}
