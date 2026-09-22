<?php
/**
 * Ready-made connection settings for common OpenAI-compatible services.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every service here speaks the chat-completions protocol, so the one
 * OpenAiProvider handles all of them — a preset only fills in the
 * endpoint and suggests model names; the site owner can still edit both.
 */
final class Presets {

	/**
	 * Preset id used when the site owner types everything by hand.
	 */
	const CUSTOM = 'custom';

	/**
	 * All presets, keyed by id.
	 *
	 * @return array<string,array{label:string,endpoint:string,models:string[],key_url:string,note:string}>
	 */
	public static function all() {
		$presets = array(
			'openai'     => array(
				'label'    => 'OpenAI',
				'endpoint' => 'https://api.openai.com/v1/chat/completions',
				'models'   => array( 'gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'gpt-4.1' ),
				'key_url'  => 'https://platform.openai.com/api-keys',
				'note'     => __( 'سرورهای OpenAI درخواست‌هایی را که از IP ایران ارسال شوند رد می‌کنند. اگر هاست سایت در ایران است، یکی از درگاه‌های واسط (اول‌ای‌آی، گپ‌جی‌پی‌تی، متیس یا OpenRouter) را انتخاب کنید.', 'yuniq-ai' ),
			),
			'avalai'     => array(
				'label'    => __( 'اول‌ای‌آی (AvalAI)', 'yuniq-ai' ),
				'endpoint' => 'https://api.avalai.ir/v1/chat/completions',
				'models'   => array( 'gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini' ),
				'key_url'  => 'https://avalai.ir',
				'note'     => __( 'درگاه ایرانی با پرداخت ریالی؛ روی هاست ایرانی بدون مشکل کار می‌کند. مدل‌های OpenAI، Claude و Gemini را از یک کلید در اختیار می‌گذارد.', 'yuniq-ai' ),
			),
			'gapgpt'     => array(
				'label'    => __( 'گپ‌جی‌پی‌تی (GapGPT)', 'yuniq-ai' ),
				'endpoint' => 'https://api.gapgpt.app/v1/chat/completions',
				'models'   => array( 'gpt-4o-mini', 'gpt-4o' ),
				'key_url'  => 'https://gapgpt.app',
				'note'     => __( 'درگاه ایرانی با پرداخت ریالی و سازگار با OpenAI.', 'yuniq-ai' ),
			),
			'metis'      => array(
				'label'    => __( 'متیس (Metis AI)', 'yuniq-ai' ),
				'endpoint' => 'https://api.metisai.ir/openai/v1/chat/completions',
				'models'   => array( 'gpt-4o-mini', 'gpt-4o' ),
				'key_url'  => 'https://metisai.ir',
				'note'     => __( 'درگاه ایرانی با پرداخت ریالی و سازگار با OpenAI.', 'yuniq-ai' ),
			),
			'openrouter' => array(
				'label'    => 'OpenRouter',
				'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
				'models'   => array( 'openai/gpt-4o-mini', 'anthropic/claude-haiku-4-5', 'google/gemini-2.5-flash', 'deepseek/deepseek-chat' ),
				'key_url'  => 'https://openrouter.ai/keys',
				'note'     => __( 'صدها مدل از شرکت‌های مختلف با یک کلید. نام مدل به شکل «شرکت/مدل» نوشته می‌شود.', 'yuniq-ai' ),
			),
			'anthropic'  => array(
				'label'    => 'Anthropic (Claude)',
				'endpoint' => 'https://api.anthropic.com/v1/chat/completions',
				'models'   => array( 'claude-haiku-4-5', 'claude-sonnet-5', 'claude-opus-5' ),
				'key_url'  => 'https://console.anthropic.com/settings/keys',
				'note'     => __( 'از لایه سازگار با OpenAI در API رسمی Claude استفاده می‌شود. برای پشتیبانی سایت، مدل Haiku سریع و کم‌هزینه است.', 'yuniq-ai' ),
			),
			'gemini'     => array(
				'label'    => 'Google Gemini',
				'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
				'models'   => array( 'gemini-2.5-flash', 'gemini-2.5-pro' ),
				'key_url'  => 'https://aistudio.google.com/apikey',
				'note'     => __( 'کلید را از Google AI Studio بگیرید. این سرویس هم IP ایران را محدود می‌کند.', 'yuniq-ai' ),
			),
			'deepseek'   => array(
				'label'    => 'DeepSeek',
				'endpoint' => 'https://api.deepseek.com/v1/chat/completions',
				'models'   => array( 'deepseek-chat', 'deepseek-reasoner' ),
				'key_url'  => 'https://platform.deepseek.com/api_keys',
				'note'     => __( 'ارزان و مناسب زبان فارسی.', 'yuniq-ai' ),
			),
			'groq'       => array(
				'label'    => 'Groq',
				'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
				'models'   => array( 'llama-3.3-70b-versatile', 'llama-3.1-8b-instant' ),
				'key_url'  => 'https://console.groq.com/keys',
				'note'     => __( 'پاسخ‌دهی بسیار سریع با مدل‌های متن‌باز.', 'yuniq-ai' ),
			),
			'ollama'     => array(
				'label'    => __( 'Ollama (سرور شخصی)', 'yuniq-ai' ),
				'endpoint' => 'http://localhost:11434/v1/chat/completions',
				'models'   => array( 'llama3.1', 'qwen2.5', 'gemma2' ),
				'key_url'  => 'https://ollama.com/download',
				'note'     => __( 'مدل روی سرور خودتان اجرا می‌شود و کلید واقعی لازم ندارد؛ در فیلد کلید هر متنی (مثلاً ollama) بنویسید. Ollama باید روی همان سروری باشد که وردپرس از آن قابل دسترسی است.', 'yuniq-ai' ),
			),
			self::CUSTOM => array(
				'label'    => __( 'سفارشی (هر API سازگار با OpenAI)', 'yuniq-ai' ),
				'endpoint' => '',
				'models'   => array(),
				'key_url'  => '',
				'note'     => __( 'آدرس و نام مدل را از مستندات سرویس خود بردارید. کافی است سرویس از قالب chat/completions پشتیبانی کند؛ آدرس پایه (مثلاً https://example.com/v1) هم پذیرفته می‌شود.', 'yuniq-ai' ),
			),
		);

		/**
		 * Filters the connection presets shown on the settings screen.
		 *
		 * @param array $presets Presets keyed by id.
		 */
		return (array) apply_filters( 'yuniq_ai_provider_presets', $presets );
	}

	/**
	 * Whether a preset id exists.
	 *
	 * @param string $id Preset id.
	 * @return bool
	 */
	public static function exists( $id ) {
		return array_key_exists( (string) $id, self::all() );
	}
}
