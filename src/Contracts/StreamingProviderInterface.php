<?php
/**
 * Contract for providers that can stream a reply token by token.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional companion to AiProviderInterface.
 *
 * Kept separate so a custom provider that only supports a single blocking
 * request still works: the plugin checks for this interface and falls back
 * to {@see AiProviderInterface::generate()} when it is absent.
 */
interface StreamingProviderInterface {

	/**
	 * Send a completion request and report each delta as it arrives.
	 *
	 * @param array    $messages Chat messages, each with `role` and `content`.
	 * @param array    $options  Provider options: model, temperature, max_tokens.
	 * @param callable $on_chunk Receives each text delta as a string.
	 * @return array{
	 *     success: bool,
	 *     content?: string,
	 *     tokens?: int,
	 *     model?: string,
	 *     error?: string,
	 *     code?: int
	 * } The accumulated result once the stream closes.
	 */
	public function stream( array $messages, array $options, callable $on_chunk );

	/**
	 * Whether streaming can actually run in this environment.
	 *
	 * @return bool
	 */
	public function supports_streaming();
}
