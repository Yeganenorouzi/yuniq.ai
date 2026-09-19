<?php
/**
 * Builds prompts and dispatches them to the configured provider.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Ai;

use Yuniq\Ai\Ai\Provider\OpenAiProvider;
use Yuniq\Ai\Contracts\AiProviderInterface;
use Yuniq\Ai\Contracts\StreamingProviderInterface;
use Yuniq\Ai\Kb\Repository as KnowledgeBase;
use Yuniq\Ai\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assembles the system prompt, knowledge base context and history.
 */
final class Client {

	/**
	 * How many conversation turns are replayed to the model.
	 */
	const HISTORY_LIMIT = 6;

	/**
	 * How many knowledge base documents are attached to a question.
	 *
	 * Every document costs input tokens, which the visitor pays for in
	 * waiting time before the first word appears.
	 */
	const CONTEXT_DOCUMENTS = 5;

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
	 * Resolved provider, built on first use.
	 *
	 * @var AiProviderInterface|null
	 */
	private $provider;

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings       Plugin settings.
	 * @param KnowledgeBase $knowledge_base Knowledge base storage.
	 */
	public function __construct( Settings $settings, KnowledgeBase $knowledge_base ) {
		$this->settings       = $settings;
		$this->knowledge_base = $knowledge_base;
	}

	/**
	 * Answer a visitor's message in one blocking call.
	 *
	 * @param string $user_message The visitor's question.
	 * @param array  $history      Prior turns, each with `role` and `content`.
	 * @return array{success:bool, content?:string, tokens?:int, error?:string}
	 */
	public function generate_response( $user_message, array $history = array() ) {
		$result             = $this->provider()->generate(
			$this->build_messages( $user_message, $history ),
			$this->build_options()
		);
		$result['provider'] = $this->provider()->get_id();

		return $result;
	}

	/**
	 * Answer a visitor's message, reporting each delta as it arrives.
	 *
	 * Falls back to a single blocking call when the provider cannot
	 * stream, emitting the whole reply as one chunk so the caller's
	 * handling stays identical either way.
	 *
	 * @param string   $user_message The visitor's question.
	 * @param array    $history      Prior turns.
	 * @param callable $on_chunk     Receives each text delta.
	 * @return array
	 */
	public function stream_response( $user_message, array $history, callable $on_chunk ) {
		$provider = $this->provider();
		$messages = $this->build_messages( $user_message, $history );
		$options  = $this->build_options();

		if ( $provider instanceof StreamingProviderInterface && $provider->supports_streaming() ) {
			$result = $provider->stream( $messages, $options, $on_chunk );
		} else {
			$result = $provider->generate( $messages, $options );

			if ( ! empty( $result['success'] ) && ! empty( $result['content'] ) ) {
				call_user_func( $on_chunk, $result['content'] );
			}
		}

		$result['provider'] = $provider->get_id();

		return $result;
	}

	/**
	 * Whether replies can be streamed in this environment.
	 *
	 * @return bool
	 */
	public function can_stream() {
		$provider = $this->provider();

		return $provider instanceof StreamingProviderInterface && $provider->supports_streaming();
	}

	/**
	 * Send a throwaway prompt to confirm the credentials work.
	 *
	 * @return array
	 */
	public function test_connection() {
		return $this->generate_response( 'Say "Connection successful" in one short sentence.' );
	}

	/**
	 * The provider the plugin will talk to.
	 *
	 * @return AiProviderInterface
	 */
	public function provider() {
		if ( null === $this->provider ) {
			$provider = new OpenAiProvider(
				$this->settings->get( 'api_key', '' ),
				$this->settings->get( 'api_endpoint', '' )
			);

			/**
			 * Filters the AI provider used for chat completions.
			 *
			 * Return any AiProviderInterface implementation to support a
			 * backend that is not OpenAI-compatible. Also implement
			 * StreamingProviderInterface to keep streamed replies.
			 *
			 * @param AiProviderInterface $provider Default provider.
			 * @param Settings            $settings Plugin settings.
			 */
			$filtered = apply_filters( 'yuniq_ai_provider', $provider, $this->settings );

			$this->provider = ( $filtered instanceof AiProviderInterface ) ? $filtered : $provider;
		}

		return $this->provider;
	}

	/**
	 * Model options drawn from the settings.
	 *
	 * @return array
	 */
	private function build_options() {
		return array(
			'model'       => $this->settings->get( 'model' ),
			'temperature' => $this->settings->get( 'temperature', 0.7 ),
			'max_tokens'  => $this->settings->get( 'max_tokens', 1024 ),
		);
	}

	/**
	 * Assemble the full message list for a turn.
	 *
	 * @param string $user_message The visitor's question.
	 * @param array  $history      Prior turns.
	 * @return array
	 */
	private function build_messages( $user_message, array $history ) {
		$messages = array(
			array(
				'role'    => 'system',
				'content' => $this->build_system_prompt( $user_message ),
			),
		);

		foreach ( array_slice( $history, -self::HISTORY_LIMIT ) as $turn ) {
			if ( isset( $turn['role'], $turn['content'] ) ) {
				$messages[] = array(
					'role'    => sanitize_text_field( $turn['role'] ),
					'content' => sanitize_textarea_field( $turn['content'] ),
				);
			}
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => sanitize_textarea_field( $user_message ),
		);

		return $messages;
	}

	/**
	 * Compose the system message, including retrieved site content.
	 *
	 * @param string $user_message The visitor's question.
	 * @return string
	 */
	private function build_system_prompt( $user_message ) {
		$prompt = (string) $this->settings->get( 'system_prompt', '' );

		$prompt .= "\n\nYou are the official AI assistant of this website. Rules:\n"
			. "1) ALWAYS prefer the Website Knowledge Base below over general knowledge.\n"
			. "2) When answering, use facts from the knowledge base (titles, content, categories).\n"
			. "3) LINKS POLICY: Do NOT include URLs or links in normal answers. Only when the user explicitly asks for a link (e.g. «لینک بده», «آدرس صفحه», «link», «URL»), then provide the relevant full URL from the knowledge base as a markdown link [title](url). Otherwise answer with text only, no links.\n"
			. "4) Answer in the same language as the user (Persian if they write in Persian).\n"
			. "5) Be concise, helpful, and professional. Keep answers short unless asked for detail.\n"
			. "6) If knowledge base has no match, say so briefly and still try to help.\n";

		$context = $this->knowledge_base->get_context_for_query( $user_message, self::CONTEXT_DOCUMENTS );

		if ( '' !== $context ) {
			$prompt .= "\n\n=== Website Knowledge Base (use this) ===\n" . $context . "\n=== End of Knowledge Base ===\n";
		} else {
			$prompt .= "\n\n(No matching knowledge-base documents for this query.)\n";
		}

		/**
		 * Filters the assembled system prompt.
		 *
		 * @param string $prompt       Full system message.
		 * @param string $user_message The visitor's question.
		 * @param string $context      Retrieved knowledge base context.
		 */
		return (string) apply_filters( 'yuniq_ai_system_prompt', $prompt, $user_message, $context );
	}
}
