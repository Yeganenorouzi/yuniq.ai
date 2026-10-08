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
use Yuniq\Ai\Support\Logger;
use Yuniq\Ai\Support\Text;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assembles the system prompt, knowledge base context and history.
 */
final class Client {

	/**
	 * Default number of conversation turns replayed to the model.
	 */
	const HISTORY_LIMIT = 6;

	/**
	 * Default number of knowledge base documents attached to a question.
	 *
	 * Every document costs input tokens, which the visitor pays for in
	 * waiting time before the first word appears.
	 */
	const CONTEXT_DOCUMENTS = 5;

	/**
	 * Longest replayed history turn, in characters.
	 */
	const HISTORY_TURN_CHARS = 2000;

	/**
	 * Most follow-up options shown under one reply.
	 */
	const MAX_OPTIONS = 4;

	/**
	 * Most product cards shown under one reply.
	 */
	const MAX_PRODUCTS = 3;

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

		$this->resolve_directives( $result, $user_message );
		$this->report( $result );

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

			// A delta can end on the first bracket of a directive.
			if ( '' !== $safe && '[' === substr( $safe, -1 ) ) {
				$safe    = substr( $safe, 0, -1 );
				$pending = '[' . $pending;
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

		$this->resolve_directives( $result, $user_message );
		$this->report( $result );

		return $result;
	}

	/**
	 * Option holding the time of the last answer the provider gave.
	 */
	const LAST_SUCCESS_OPTION = 'yuniq_ai_last_success';

	/**
	 * Put a failed turn in the error log, and note a successful one so the
	 * status screen can say when the assistant last worked.
	 *
	 * @param array $result Provider result.
	 * @return void
	 */
	private function report( array $result ) {
		if ( ! empty( $result['success'] ) ) {
			// At most one write every ten minutes, not one per message.
			if ( time() - (int) get_option( self::LAST_SUCCESS_OPTION, 0 ) > 600 ) {
				update_option( self::LAST_SUCCESS_OPTION, time(), false );
			}

			return;
		}

		Logger::error(
			'ai',
			'سرویس هوش مصنوعی پاسخ نداد: ' . ( isset( $result['error'] ) ? $result['error'] : 'خطای ناشناخته' ),
			array(
				'model' => (string) $this->settings->get( 'model' ),
				'host'  => (string) wp_parse_url( (string) $this->settings->get( 'api_endpoint' ), PHP_URL_HOST ),
				'code'  => isset( $result['code'] ) ? (int) $result['code'] : '',
			)
		);
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
			'timeout'     => (int) $this->settings->get( 'request_timeout', 60 ),
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
				'content' => $this->build_system_prompt( $user_message, $history ),
			),
		);

		$limit = (int) $this->settings->get( 'history_limit', self::HISTORY_LIMIT );

		foreach ( $limit > 0 ? array_slice( $history, -$limit ) : array() as $turn ) {
			// The history comes from the browser: only the two conversational
			// roles are accepted, so a visitor cannot smuggle in a `system`
			// message, and each turn is capped like a live message is.
			if ( ! is_array( $turn ) || ! isset( $turn['role'], $turn['content'] ) || ! is_string( $turn['content'] ) ) {
				continue;
			}

			if ( ! in_array( $turn['role'], array( 'user', 'assistant' ), true ) ) {
				continue;
			}

			$content = Text::truncate( sanitize_textarea_field( $turn['content'] ), self::HISTORY_TURN_CHARS, '' );

			if ( '' !== $content ) {
				$messages[] = array(
					'role'    => $turn['role'],
					'content' => $content,
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
	 * @param array  $history      Prior turns, used to keep follow-up questions on topic.
	 * @return string
	 */
	private function build_system_prompt( $user_message, array $history = array() ) {
		$prompt = (string) $this->settings->get( 'system_prompt', '' );

		$prompt .= "\n\nYou are the official AI assistant of this website. Rules:\n"
			. "1) ALWAYS prefer the Website Knowledge Base below over general knowledge.\n"
			. "2) When answering, use facts from the knowledge base (titles, content, categories).\n"
			. '3) ' . $this->links_rule() . "\n"
			. '4) ' . $this->language_rule() . "\n"
			. '5) ' . $this->style_rule() . "\n"
			. "6) If knowledge base has no match, say so briefly and still try to help. Never invent prices, stock, colours, variations or policies that are not in the knowledge base. When a product lists Attributes or Variations, those lists are the complete set of available options: answer questions about colours, sizes, storage and the like from them.\n"
			. "7) HANDOFF: if you genuinely cannot help (the knowledge base has nothing relevant and the question needs a human, or the visitor is frustrated/asks for a human), write your best short reply and then add a new line containing exactly [[NEED_HUMAN]] and nothing else on that line. Never mention this token to the user, never explain it — it is stripped before they see your message.\n"
			. "8) PRODUCT CARD: when your reply recommends or lists specific products from the knowledge base, add one line per product at the very end containing exactly [[PRODUCT:ID]] (ID is the numeric Product ID shown for it below). Up to 3 products, most relevant first. The visitor then sees each as a card with its photo, price and buy button, so do not repeat links for them.\n"
			. "9) LEAD FORM: if one of the \"Available forms\" listed below clearly matches what the visitor wants (e.g. they ask for a consultation/quote/callback), add a new line at the end containing exactly [[FORM:key]] (replace key with that form's key). Only ever include one per reply.\n";

		$prompt .= $this->options_rule();

		$prompt .= $this->build_forms_hint();

		$prompt .= $this->build_site_profile();

		$detail  = array(
			'compact' => 700,
			'normal'  => 1100,
			'full'    => 2600,
		);
		$chosen  = (string) $this->settings->get( 'context_detail', 'normal' );
		$context = $this->knowledge_base->get_context_for_query(
			$user_message,
			max( 1, (int) $this->settings->get( 'context_documents', self::CONTEXT_DOCUMENTS ) ),
			$this->conversation_subject( $history ),
			isset( $detail[ $chosen ] ) ? $detail[ $chosen ] : $detail['normal'],
			Text::parse_synonyms( (string) $this->settings->get( 'synonyms', '' ) )
		);

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
	 * How (and whether) the model offers tappable next steps under a reply.
	 *
	 * Vague chips ("more info", "another question") are worse than none:
	 * they cost a tap and lead nowhere. The rule therefore ties every
	 * option to something that is actually in the knowledge base.
	 *
	 * @return string
	 */
	private function options_rule() {
		$mode = (string) $this->settings->get( 'suggest_mode', 'smart' );

		if ( 'off' === $mode || ! $this->settings->get( 'suggest_options', true ) ) {
			return '';
		}

		$rule = '10) NEXT-STEP OPTIONS: you may end with one final line containing exactly [[OPTIONS:first|second|third]] (2 or 3 options, max ' . self::MAX_OPTIONS . "). They are buttons the visitor taps instead of typing, so each one must be:\n"
			. "   - written as the visitor's own next message, in their language, at most 5 words;\n"
			. "   - SPECIFIC and taken from the knowledge base documents above: real product names, real variations (a colour, a size, a storage capacity), real categories or services. Good: «رنگ مشکی موجوده؟», «مدل ۲۵۶ گیگ چنده؟», «iPhone 16 Pro Max», «هزینه ارسال به شهرستان». Bad: «اطلاعات بیشتر», «سوال دیگری دارم», «بله», «ممنون», «ادامه بده», «راهنمایی»;\n"
			. "   - answerable from the knowledge base or the About section, never something you would have to refuse;\n"
			. "   - different from what the visitor just asked and from what you just answered.\n";

		if ( 'choices' === $mode ) {
			$rule .= "   Offer them ONLY when the visitor has to pick between concrete alternatives you just listed (products, variations, categories, plans). In every other case add no OPTIONS line.\n";
		} else {
			$rule .= "   Prefer, in this order: (a) the concrete alternatives you just listed, so the visitor can pick one; (b) the natural next detail about the product or service under discussion (its variations, price, stock, delivery, warranty) if the knowledge base has it. If you cannot think of at least two options that meet every rule, add no OPTIONS line at all.\n";
		}

		return $rule . "   Never mention this token.\n";
	}

	/**
	 * Drop the options that would waste the visitor's tap: filler, a
	 * repeat of their own question, or anything too long for a button.
	 *
	 * @param string[] $options      Options parsed from the reply.
	 * @param string   $user_message What the visitor just asked.
	 * @return string[] Empty unless at least two useful options remain.
	 */
	private function useful_options( array $options, $user_message ) {
		$asked  = Text::normalize( $user_message );
		$filler = '/^(اطلاعات|توضیح(ات)?|جزییات|راهنمایی|سوال|پرسش)( ی)? ?(بیشتر|دیگر|دیگری|دیگه)?( دارم| بده| میخوام| میخواهم| بدهید)?$|^(بله|اره|خیر|نه|ممنون|مرسی|تشکر|باشه|اوکی|ادامه( بده)?|بیشتر( بگو)?|سوال دیگر(ی)?( دارم)?|کمک( میخوام)?|شروع|بعدی|more( info(rmation)?)?|yes|no|ok(ay)?|thanks?|continue|next|help|tell me more|other questions?)$/u';
		$kept   = array();

		foreach ( $options as $option ) {
			$normalized = trim( Text::normalize( $option ), " ?!.\t" );

			if ( Text::length( $normalized ) < 2 || Text::length( $option ) > 45 || $normalized === trim( $asked, ' ?!.' ) || preg_match( $filler, $normalized ) ) {
				continue;
			}

			$kept[ $normalized ] = $option;
		}

		return count( $kept ) >= 2 ? array_values( $kept ) : array();
	}

	/**
	 * What the last exchange was about, as extra search words.
	 *
	 * A follow-up like «چه رنگ‌هایی داری؟» names no product. The previous
	 * question and the start of the previous answer usually do.
	 *
	 * @param array $history Prior turns from the browser.
	 * @return string
	 */
	private function conversation_subject( array $history ) {
		$last = array(
			'user'      => '',
			'assistant' => '',
		);

		foreach ( array_slice( $history, -6 ) as $turn ) {
			if ( is_array( $turn ) && isset( $turn['role'], $turn['content'] ) && is_string( $turn['content'] ) && isset( $last[ $turn['role'] ] ) ) {
				$last[ $turn['role'] ] = $turn['content'];
			}
		}

		return Text::truncate( sanitize_textarea_field( $last['user'] ), 200, '' ) . ' ' . Text::truncate( sanitize_textarea_field( $last['assistant'] ), 160, '' );
	}

	/**
	 * Facts about the business that go with every question: who the site
	 * is, what the owner wrote about it, and a map of what is on it.
	 *
	 * @return string
	 */
	private function build_site_profile() {
		$profile = "\n\n=== About this website (always true) ===\n"
			. 'Name: ' . wp_strip_all_tags( get_bloginfo( 'name' ) ) . "\n"
			. 'Address: ' . home_url( '/' ) . "\n";

		$tagline = wp_strip_all_tags( get_bloginfo( 'description' ) );
		if ( '' !== $tagline ) {
			$profile .= 'Tagline: ' . $tagline . "\n";
		}

		$info = trim( (string) $this->settings->get( 'business_info', '' ) );
		if ( '' !== $info ) {
			$profile .= "Information from the site owner (contact, hours, shipping, returns, payment):\n" . $info . "\n";
		}

		if ( $this->settings->get( 'site_profile', true ) ) {
			$profile .= $this->knowledge_base->site_overview();
		}

		return $profile . "=== End of About ===\n";
	}

	/**
	 * Link behaviour chosen on the settings screen.
	 *
	 * @return string
	 */
	private function links_rule() {
		if ( 'helpful' === $this->settings->get( 'links_policy', 'on_request' ) ) {
			return 'LINKS POLICY: when a page from the knowledge base is directly relevant, add its link once as a markdown link [title](url). Only ever use URLs that appear in the knowledge base; never invent one.';
		}

		return 'LINKS POLICY: Do NOT include URLs or links in normal answers. Only when the user explicitly asks for a link (e.g. «لینک بده», «آدرس صفحه», «link», «URL»), then provide the relevant full URL from the knowledge base as a markdown link [title](url). Otherwise answer with text only, no links.';
	}

	/**
	 * Reply language chosen on the settings screen.
	 *
	 * @return string
	 */
	private function language_rule() {
		switch ( $this->settings->get( 'reply_language', 'auto' ) ) {
			case 'fa':
				return 'Always answer in Persian (Farsi), whatever language the user writes in.';
			case 'en':
				return 'Always answer in English, whatever language the user writes in.';
		}

		return 'Answer in the same language as the user (Persian if they write in Persian).';
	}

	/**
	 * Tone and length chosen on the settings screen.
	 *
	 * @return string
	 */
	private function style_rule() {
		$tones = array(
			'friendly' => 'Tone: warm, friendly and conversational, like a helpful colleague.',
			'formal'   => 'Tone: formal, respectful and precise.',
			'sales'    => 'Tone: enthusiastic and persuasive; highlight benefits and gently guide the visitor to buy or request a consultation, without being pushy.',
			'expert'   => 'Tone: knowledgeable and exact, like a specialist explaining to a customer.',
		);
		$lengths = array(
			'short'    => 'Length: very short, 1 to 3 sentences, unless the user asks for detail.',
			'medium'   => 'Length: concise, one short paragraph or a few bullet-like lines.',
			'detailed' => 'Length: complete and well explained, but never padded.',
		);

		$tone   = (string) $this->settings->get( 'reply_tone', 'friendly' );
		$length = (string) $this->settings->get( 'reply_length', 'short' );

		return ( isset( $tones[ $tone ] ) ? $tones[ $tone ] : $tones['friendly'] )
			. ' ' . ( isset( $lengths[ $length ] ) ? $lengths[ $length ] : $lengths['short'] );
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
	private function resolve_directives( array &$result, $user_message = '' ) {
		$content = isset( $result['content'] ) ? $result['content'] : '';
		$flags   = $this->extract_directives( $content );

		if ( isset( $result['content'] ) ) {
			$result['content'] = $content;
		}

		$result['needs_human'] = empty( $result['success'] ) ? true : $flags['needs_human'];
		$result['form_key']    = $flags['form_key'];
		$result['options']     = $this->useful_options( $flags['options'], $user_message );

		$products = array();
		foreach ( array_slice( $flags['product_ids'], 0, self::MAX_PRODUCTS ) as $product_id ) {
			$payload = $this->build_product_payload( $product_id );

			if ( $payload ) {
				$products[] = $payload;
			}
		}

		$result['products'] = $products;
		// Kept for anything still reading the single-product field.
		$result['product']  = $products ? $products[0] : null;
	}

	/**
	 * Find and remove every `[[NEED_HUMAN]]`, `[[PRODUCT:id]]`,
	 * `[[FORM:key]]` and `[[OPTIONS:a|b]]` directive in a piece of text.
	 *
	 * @param string $content Text to scan, mutated in place with directives removed.
	 * @return array{needs_human:bool, product_ids:int[], form_key:string|null, options:string[]}
	 */
	private function extract_directives( &$content ) {
		$flags = array(
			'needs_human' => false,
			'product_ids' => array(),
			'form_key'    => null,
			'options'     => array(),
		);

		$content = (string) preg_replace_callback(
			'/\n?\[\[(NEED_HUMAN|PRODUCT:(\d+)|FORM:([a-z0-9_-]+)|OPTIONS:([^\[\]]+))\]\]/iu',
			function ( $m ) use ( &$flags ) {
				if ( 0 === stripos( $m[1], 'PRODUCT:' ) ) {
					if ( isset( $m[2] ) && ! in_array( (int) $m[2], $flags['product_ids'], true ) ) {
						$flags['product_ids'][] = (int) $m[2];
					}
				} elseif ( 0 === stripos( $m[1], 'FORM:' ) ) {
					$flags['form_key'] = isset( $m[3] ) ? $m[3] : null;
				} elseif ( 0 === stripos( $m[1], 'OPTIONS:' ) ) {
					$flags['options'] = $this->parse_options( isset( $m[4] ) ? $m[4] : '' );
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
	 * Turn the body of an `[[OPTIONS:a|b|c]]` directive into clean labels.
	 *
	 * @param string $raw Pipe separated choices as written by the model.
	 * @return string[]
	 */
	private function parse_options( $raw ) {
		$options = array();

		foreach ( explode( '|', (string) $raw ) as $option ) {
			$option = Text::truncate( trim( sanitize_text_field( $option ) ), 60, '…' );

			if ( '' !== $option && ! in_array( $option, $options, true ) ) {
				$options[] = $option;
			}

			if ( count( $options ) >= self::MAX_OPTIONS ) {
				break;
			}
		}

		return $options;
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

		$stock = isset( $metadata['stock_status'] ) ? (string) $metadata['stock_status'] : '';
		$type  = isset( $metadata['type'] ) ? (string) $metadata['type'] : 'simple';

		return array(
			'id'            => (int) $product_id,
			'title'         => $row['title'],
			'url'           => $row['url'],
			'image'         => isset( $metadata['image'] ) ? $metadata['image'] : '',
			'price'         => KnowledgeBase::format_price( isset( $metadata['price'] ) ? $metadata['price'] : '' ),
			'regular_price' => KnowledgeBase::format_price( isset( $metadata['regular_price'] ) ? $metadata['regular_price'] : '' ),
			'sale_price'    => KnowledgeBase::format_price( isset( $metadata['sale_price'] ) ? $metadata['sale_price'] : '' ),
			'stock_status'  => $stock,
			// Only a simple, in-stock product can go straight into the cart;
			// a variable one needs its options chosen on the product page.
			'purchasable'   => 'simple' === $type && 'outofstock' !== $stock,
		);
	}
}
