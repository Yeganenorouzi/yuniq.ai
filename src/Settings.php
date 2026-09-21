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

			// Video.
			'video_enabled'         => false,
			'video_url'             => '',
			'video_title'           => '',
			'video_autoplay'        => true,
			'video_mute'            => true,
			'video_controls'        => false,

			// Presentation.
			'assistant_name'        => 'دستیار هوشمند',
			'welcome_message'       => 'سلام! چطور می‌تونم کمکتون کنم؟',
			'avatar_url'            => '',
			'logo_url'              => '',
			'primary_color'         => '#263DFF',
			'secondary_color'       => '#111B55',
			'button_icon'           => 'sparkle',
			'theme'                 => 'auto',
			'widget_position'       => 'bottom-right',
			'custom_position_x'     => 20,
			'custom_position_y'     => 20,
			'chat_width'            => 480,
			'chat_height'           => 860,
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
		$output['ai_provider'] = isset( $input['ai_provider'] ) ? sanitize_key( $input['ai_provider'] ) : 'openai';

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

		// --- Video ------------------------------------------------------------
		$output['video_enabled']  = ! empty( $input['video_enabled'] );
		$output['video_url']      = isset( $input['video_url'] ) ? esc_url_raw( $input['video_url'] ) : '';
		$output['video_title']    = isset( $input['video_title'] ) ? sanitize_text_field( $input['video_title'] ) : '';
		$output['video_autoplay'] = ! empty( $input['video_autoplay'] );
		$output['video_mute']     = ! empty( $input['video_mute'] );
		$output['video_controls'] = ! empty( $input['video_controls'] );

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
		$output['button_icon']       = isset( $input['button_icon'] ) ? sanitize_key( $input['button_icon'] ) : 'sparkle';

		$theme            = isset( $input['theme'] ) ? sanitize_key( $input['theme'] ) : 'auto';
		$output['theme']  = in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ? $theme : 'auto';

		$output['widget_position']   = isset( $input['widget_position'] ) ? sanitize_key( $input['widget_position'] ) : 'bottom-right';
		$output['custom_position_x'] = isset( $input['custom_position_x'] ) ? absint( $input['custom_position_x'] ) : 20;
		$output['custom_position_y'] = isset( $input['custom_position_y'] ) ? absint( $input['custom_position_y'] ) : 20;
		$output['chat_width']        = isset( $input['chat_width'] ) ? min( 560, max( 320, absint( $input['chat_width'] ) ) ) : 480;
		$output['chat_height']       = isset( $input['chat_height'] ) ? min( 900, max( 400, absint( $input['chat_height'] ) ) ) : 860;
		$output['border_radius']     = isset( $input['border_radius'] ) ? min( 32, absint( $input['border_radius'] ) ) : 20;
		$output['enable_animations'] = ! empty( $input['enable_animations'] );
		$output['enabled']           = ! empty( $input['enabled'] );

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
