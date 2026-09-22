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
			'system_prompt'         => 'تو دستیار هوشمند رسمی این وب‌سایت هستی. همیشه اول از پایگاه دانش وب‌سایت استفاده کن. لینک صفحه را فقط وقتی بده که کاربر صریحاً درخواست لینک کرده باشد (مثلاً «لینک بده»). در پاسخ‌های عادی لینک نفرست. پاسخ‌ها را کوتاه، مؤدب و به زبان کاربر بنویس.',
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
			'wc_attributes'         => false,
			'wc_reviews'            => false,
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

			// General.
			'enabled'               => true,
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
	 * Runs as the `sanitize_callback` for `register_setting()`, so the
	 * input is unslashed here before any sanitizer touches it.
	 *
	 * @param mixed $input Raw submitted values.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? wp_unslash( $input ) : array();
		$existing = get_option( self::OPTION_KEY, array() );
		$output   = wp_parse_args( is_array( $existing ) ? $existing : array(), self::defaults() );

		// --- AI configuration -------------------------------------------------
		$provider              = isset( $input['ai_provider'] ) ? sanitize_key( $input['ai_provider'] ) : 'openai';
		$output['ai_provider'] = Ai\Presets::exists( $provider ) ? $provider : Ai\Presets::CUSTOM;

		// The key is only replaced when the form actually carried a new one.
		if ( isset( $input['api_key'] ) ) {
			$submitted_key = trim( sanitize_text_field( $input['api_key'] ) );
			if ( self::SECRET_MASK !== $submitted_key ) {
				$output['api_key'] = $submitted_key;
			}
		}

		$endpoint                = isset( $input['api_endpoint'] ) ? esc_url_raw( trim( $input['api_endpoint'] ) ) : '';
		$output['api_endpoint']  = $endpoint ? $endpoint : 'https://api.openai.com/v1/chat/completions';
		$model                   = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : '';
		$output['model']         = $model ? $model : 'gpt-4o-mini';
		$output['temperature']   = isset( $input['temperature'] ) ? min( 2.0, max( 0.0, (float) $input['temperature'] ) ) : 0.7;
		$output['max_tokens']    = isset( $input['max_tokens'] ) ? min( 8192, max( 64, absint( $input['max_tokens'] ) ) ) : 1024;
		$output['system_prompt'] = isset( $input['system_prompt'] ) ? sanitize_textarea_field( $input['system_prompt'] ) : '';

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
			'wc_reviews',
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

		$output['content_types'] = $this->derive_content_types( $output );

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

		if ( isset( $input['quick_actions'] ) && is_array( $input['quick_actions'] ) ) {
			$output['quick_actions'] = $this->sanitize_quick_actions( $input['quick_actions'] );
		}

		// --- Live support (human handoff) --------------------------------------
		$output['live_support_enabled'] = ! empty( $input['live_support_enabled'] );

		$ls_mode                    = isset( $input['live_support_mode'] ) ? sanitize_key( $input['live_support_mode'] ) : 'both';
		$output['live_support_mode'] = in_array( $ls_mode, array( 'manual', 'auto_suggest', 'both' ), true ) ? $ls_mode : 'both';

		$output['agent_display_name'] = isset( $input['agent_display_name'] ) && '' !== trim( (string) $input['agent_display_name'] )
			? sanitize_text_field( $input['agent_display_name'] )
			: 'کارشناس پشتیبانی';

		$output['notify_channels'] = ( isset( $input['notify_channels'] ) && is_array( $input['notify_channels'] ) )
			? array_values( array_intersect( array_map( 'sanitize_key', $input['notify_channels'] ), array( 'email', 'telegram' ) ) )
			: array();

		$output['notify_email'] = isset( $input['notify_email'] ) && $input['notify_email']
			? sanitize_email( $input['notify_email'] )
			: get_option( 'admin_email' );

		if ( isset( $input['telegram_bot_token'] ) ) {
			$submitted_token = trim( sanitize_text_field( $input['telegram_bot_token'] ) );
			if ( self::SECRET_MASK !== $submitted_token ) {
				$output['telegram_bot_token'] = $submitted_token;
			}
		}

		$output['telegram_chat_id'] = isset( $input['telegram_chat_id'] ) ? sanitize_text_field( $input['telegram_chat_id'] ) : '';

		if ( isset( $input['lead_forms'] ) && is_array( $input['lead_forms'] ) ) {
			$output['lead_forms'] = $this->sanitize_lead_forms( $input['lead_forms'] );
		}

		$output['product_cards_enabled'] = ! empty( $input['product_cards_enabled'] );
		$output['full_page_enabled']     = ! empty( $input['full_page_enabled'] );

		$full_page_slug              = isset( $input['full_page_slug'] ) ? sanitize_title( $input['full_page_slug'] ) : '';
		$output['full_page_slug']    = $full_page_slug ? $full_page_slug : 'ai-assistant';

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
