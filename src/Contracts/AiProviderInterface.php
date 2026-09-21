<?php
/**
 * Contract for AI chat-completion providers.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Any backend able to turn a message list into an assistant reply.
 *
 * Implement this interface to add support for a provider that is not
 * OpenAI-compatible, then swap it in via the
 * `yuniq_ai_provider` filter.
 */
interface AiProviderInterface {

	/**
	 * Send a completion request.
	 *
	 * @param array $messages Chat messages, each with `role` and `content`.
	 * @param array $options  Provider options: model, temperature, max_tokens.
	 * @return array{
	 *     success: bool,
	 *     content?: string,
	 *     tokens?: int,
	 *     model?: string,
	 *     error?: string,
	 *     code?: int
	 * }
	 */
	public function generate( array $messages, array $options );

	/**
	 * Short identifier for this provider, e.g. `openai`.
	 *
	 * @return string
	 */
	public function get_id();
}
