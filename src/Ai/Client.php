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

		$this->resolve_directives( $result );

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

		// A `[[...]]` directive could be split across several deltas, so raw
		// text is held back here until it is either confirmed to not be the
		// start of one, or resolved and stripped — the caller never sees a
		// partial or literal control token.
		$pending      = '';
		$wrapped_chunk = function ( $delta ) use ( &$pending, $on_chunk ) {
			$pending .= $delta;
			$this->extract_directives( $pending );

			$cut = strrpos( $pending, '[[' );

			if ( false !== $cut && false === strpos( substr( $pending, $cut ), ']]' ) ) {
				$safe    = substr( $pending, 0, $cut );
				$pending = substr( $pending, $cut );
			} else {
				$safe    = $pending;
				$pending = '';
			}

			if ( '' !== $safe ) {
				call_user_func( $on_chunk, $safe );
			}
		};

		if ( $provider instanceof StreamingProviderInterface && $provider->supports_streaming() ) {
			$result = $provider->stream( $messages, $options, $wrapped_chunk );
		} else {
			$result = $provider->generate( $messages, $options );

			if ( ! empty( $result['success'] ) && ! empty( $result['content'] ) ) {
				call_user_func( $wrapped_chunk, $result['content'] );
			}
		}

		// A stray, never-closed "[[" was not a real directive — release it
		// as ordinary text so nothing the model wrote is silently dropped.
		if ( '' !== $pending ) {
			call_user_func( $on_chunk, $pending );
		}

		$result['provider'] = $provider->get_id();

		$this->resolve_directives( $result );

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
			. "6) If knowledge base has no match, say so briefly and still try to help.\n"
			. "7) HANDOFF: if you genuinely cannot help (the knowledge base has nothing relevant and the question needs a human, or the visitor is frustrated/asks for a human), write your best short reply and then add a new line containing exactly [[NEED_HUMAN]] and nothing else on that line. Never mention this token to the user, never explain it — it is stripped before they see your message.\n"
			. "8) PRODUCT CARD: when you recommend one specific product from the knowledge base, add a new line at the end containing exactly [[PRODUCT:ID]] (replace ID with the numeric id shown for that product below). Only ever include one per reply, and only when a specific product is clearly the right recommendation.\n"
			. "9) LEAD FORM: if one of the \"Available forms\" listed below clearly matches what the visitor wants (e.g. they ask for a consultation/quote/callback), add a new line at the end containing exactly [[FORM:key]] (replace key with that form's key). Only ever include one per reply.\n";

		$prompt .= $this->build_forms_hint();

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

	/**
	 * List the admin-configured lead-capture forms, so the model knows
	 * which `[[FORM:key]]` values it is allowed to emit.
	 *
	 * @return string Empty string when no forms are configured.
	 */
	private function build_forms_hint() {
		$forms = (array) $this->settings->get( 'lead_forms', array() );

		if ( ! $forms ) {
			return '';
		}

		$lines = array();

		foreach ( $forms as $form ) {
			if ( empty( $form['key'] ) || empty( $form['title'] ) ) {
				continue;
			}

			$lines[] = '- ' . $form['key'] . ': ' . $form['title'];
		}

		if ( ! $lines ) {
			return '';
		}

		return "\n\nAvailable forms (use the key, not the title, in [[FORM:key]]):\n" . implode( "\n", $lines ) . "\n";
	}

	/**
	 * Strip `[[...]]` control directives out of the final reply and attach
	 * what they asked for to the result. A provider failure always implies
	 * a human is needed, regardless of what (if anything) the model wrote.
	 *
	 * @param array $result Provider result, mutated in place.
	 * @return void
	 */
	private function resolve_directives( array &$result ) {
		$content = isset( $result['content'] ) ? $result['content'] : '';
		$flags   = $this->extract_directives( $content );

		if ( isset( $result['content'] ) ) {
			$result['content'] = $content;
		}

		$result['needs_human'] = empty( $result['success'] ) ? true : $flags['needs_human'];
		$result['form_key']    = $flags['form_key'];
		$result['product']     = $flags['product_id'] ? $this->build_product_payload( $flags['product_id'] ) : null;
	}

	/**
	 * Find and remove every `[[NEED_HUMAN]]`, `[[PRODUCT:id]]` and
	 * `[[FORM:key]]` directive in a piece of text.
	 *
	 * @param string $content Text to scan, mutated in place with directives removed.
	 * @return array{needs_human:bool, product_id:int|null, form_key:string|null}
	 */
	private function extract_directives( &$content ) {
		$flags = array(
			'needs_human' => false,
			'product_id'  => null,
			'form_key'    => null,
		);

		$content = (string) preg_replace_callback(
			'/\n?\[\[(NEED_HUMAN|PRODUCT:(\d+)|FORM:([a-z0-9_-]+))\]\]/i',
			function ( $m ) use ( &$flags ) {
				if ( 0 === stripos( $m[1], 'PRODUCT:' ) ) {
					$flags['product_id'] = isset( $m[2] ) ? (int) $m[2] : null;
				} elseif ( 0 === stripos( $m[1], 'FORM:' ) ) {
					$flags['form_key'] = isset( $m[3] ) ? $m[3] : null;
				} else {
					$flags['needs_human'] = true;
				}

				return '';
			},
			$content
		);

		return $flags;
	}

	/**
	 * Look up a recommended product's live price/stock from the knowledge
	 * base, so the widget renders a card grounded in indexed data rather
	 * than whatever the model claims.
	 *
	 * @param int $product_id WooCommerce product (post) id.
	 * @return array|null Null when the id isn't an indexed product.
	 */
	private function build_product_payload( $product_id ) {
		// post_id 0 identifies a synthetic taxonomy-term document (see
		// Kb\Indexer::index_term_batch()), never a real product.
		if ( $product_id <= 0 ) {
			return null;
		}

		$row = $this->knowledge_base->get_by_post_id( $product_id );

		if ( ! $row || 'product' !== $row['post_type'] ) {
			return null;
		}

		$metadata = json_decode( (string) $row['metadata'], true );
		$metadata = is_array( $metadata ) ? $metadata : array();

		return array(
			'id'            => (int) $product_id,
			'title'         => $row['title'],
			'url'           => $row['url'],
			'image'         => isset( $metadata['image'] ) ? $metadata['image'] : '',
			'price'         => isset( $metadata['price'] ) ? $metadata['price'] : '',
			'regular_price' => isset( $metadata['regular_price'] ) ? $metadata['regular_price'] : '',
			'sale_price'    => isset( $metadata['sale_price'] ) ? $metadata['sale_price'] : '',
			'stock_status'  => isset( $metadata['stock_status'] ) ? $metadata['stock_status'] : '',
		);
	}
}
