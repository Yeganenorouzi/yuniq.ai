<?php
/**
 * OpenAI-compatible chat completion provider.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Ai\Provider;

use Yuniq\Ai\Contracts\AiProviderInterface;
use Yuniq\Ai\Contracts\StreamingProviderInterface;
use Yuniq\Ai\Support\Text;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Talks to OpenAI and any gateway that mirrors its chat-completions API.
 */
final class OpenAiProvider implements AiProviderInterface, StreamingProviderInterface {

	/**
	 * Default endpoint when none is configured.
	 */
	const DEFAULT_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Default model when none is configured.
	 */
	const DEFAULT_MODEL = 'gpt-4o-mini';

	/**
	 * API credential.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Configured endpoint, before normalization.
	 *
	 * @var string
	 */
	private $endpoint;

	/**
	 * Constructor.
	 *
	 * @param string $api_key  API credential.
	 * @param string $endpoint Configured endpoint.
	 */
	public function __construct( $api_key, $endpoint ) {
		$this->api_key  = trim( (string) $api_key );
		$this->endpoint = trim( (string) $endpoint );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'openai';
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports_streaming() {
		return function_exists( 'curl_init' ) && function_exists( 'curl_setopt_array' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate( array $messages, array $options ) {
		if ( '' === $this->api_key ) {
			return $this->missing_key_error();
		}

		$model = $this->resolve_model( $options );

		$args = array(
			'method'  => 'POST',
			'timeout' => 60,
			'headers' => $this->request_headers(),
			'body'    => wp_json_encode( $this->request_body( $messages, $options, $model, false ) ),
		);

		/**
		 * Filters the HTTP arguments sent to the AI provider.
		 *
		 * @param array  $args        Arguments for wp_remote_post().
		 * @param string $provider_id Provider identifier.
		 */
		$args = apply_filters( 'yuniq_ai_api_request_args', $args, $this->get_id() );

		$response = wp_remote_post( $this->resolve_endpoint(), $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			return array(
				'success' => false,
				'error'   => $this->describe_error( $code, is_array( $data ) ? $data : array() ),
				'code'    => $code,
			);
		}

		$content = $this->extract_content( is_array( $data ) ? $data : array() );

		if ( '' === $content ) {
			return array(
				'success' => false,
				'error'   => $this->empty_response_error( $body ),
				'code'    => $code,
			);
		}

		return array(
			'success' => true,
			'content' => $content,
			'tokens'  => isset( $data['usage']['total_tokens'] ) ? (int) $data['usage']['total_tokens'] : 0,
			'model'   => $model,
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * WordPress's HTTP API buffers the whole body before returning, so
	 * cURL is driven directly here to surface each delta as it lands.
	 */
	public function stream( array $messages, array $options, callable $on_chunk ) {
		if ( '' === $this->api_key ) {
			return $this->missing_key_error();
		}

		if ( ! $this->supports_streaming() ) {
			return $this->generate( $messages, $options );
		}

		$model   = $this->resolve_model( $options );
		$content = '';
		$tokens  = 0;
		$buffer  = '';
		$status  = 0;
		$raw     = '';

		$headers = array();
		foreach ( $this->request_headers() as $name => $value ) {
			$headers[] = $name . ': ' . $value;
		}

		$curl = curl_init(); // phpcs:ignore WordPress.WP.AlternativeFunctions

		curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions
			$curl,
			array(
				CURLOPT_URL            => $this->resolve_endpoint(),
				CURLOPT_POST           => true,
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_POSTFIELDS     => wp_json_encode( $this->request_body( $messages, $options, $model, true ) ),
				CURLOPT_RETURNTRANSFER => false,
				CURLOPT_CONNECTTIMEOUT => 15,
				CURLOPT_TIMEOUT        => 120,
				CURLOPT_WRITEFUNCTION  => function ( $handle, $data ) use ( &$buffer, &$content, &$tokens, &$raw, &$status, $on_chunk ) {
					unset( $handle );

					// A non-2xx reply is JSON, not SSE: collect it for the error path.
					if ( $status >= 400 ) {
						$raw .= $data;

						return strlen( $data );
					}

					$buffer .= $data;

					while ( false !== ( $break = strpos( $buffer, "\n" ) ) ) {
						$line   = trim( substr( $buffer, 0, $break ) );
						$buffer = substr( $buffer, $break + 1 );

						if ( '' === $line || 0 !== strpos( $line, 'data:' ) ) {
							continue;
						}

						$payload = trim( substr( $line, 5 ) );

						if ( '[DONE]' === $payload ) {
							continue;
						}

						$decoded = json_decode( $payload, true );

						if ( ! is_array( $decoded ) ) {
							continue;
						}

						if ( isset( $decoded['usage']['total_tokens'] ) ) {
							$tokens = (int) $decoded['usage']['total_tokens'];
						}

						$delta = $this->extract_delta( $decoded );

						if ( '' !== $delta ) {
							$content .= $delta;
							call_user_func( $on_chunk, $delta );
						}
					}

					return strlen( $data );
				},
				CURLOPT_HEADERFUNCTION => function ( $handle, $header ) use ( &$status ) {
					if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $header, $m ) ) {
						$status = (int) $m[1];
					}

					unset( $handle );

					return strlen( $header );
				},
			)
		);

		$ok    = curl_exec( $curl ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$error = curl_error( $curl ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		curl_close( $curl ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( $status >= 400 ) {
			$decoded = json_decode( $raw, true );

			return array(
				'success' => false,
				'error'   => $this->describe_error( $status, is_array( $decoded ) ? $decoded : array() ),
				'code'    => $status,
			);
		}

		if ( false === $ok && '' === $content ) {
			return array(
				'success' => false,
				'error'   => $error ? $error : __( 'ارتباط با سرویس هوش مصنوعی قطع شد.', 'yuniq-ai' ),
			);
		}

		if ( '' === $content ) {
			return array(
				'success' => false,
				'error'   => $this->empty_response_error( $raw ),
				'code'    => $status,
			);
		}

		return array(
			'success' => true,
			'content' => $content,
			'tokens'  => $tokens,
			'model'   => $model,
		);
	}

	/**
	 * Headers common to both transports.
	 *
	 * @return array<string,string>
	 */
	private function request_headers() {
		return array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $this->api_key,
			'Accept'        => 'text/event-stream, application/json',
		);
	}

	/**
	 * Request payload.
	 *
	 * @param array  $messages Chat messages.
	 * @param array  $options  Provider options.
	 * @param string $model    Resolved model name.
	 * @param bool   $stream   Whether to ask for a streamed reply.
	 * @return array
	 */
	private function request_body( array $messages, array $options, $model, $stream ) {
		$body = array(
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.7,
			'max_tokens'  => isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 1024,
		);

		if ( $stream ) {
			$body['stream'] = true;
		}

		return $body;
	}

	/**
	 * Model name, falling back to the default.
	 *
	 * @param array $options Provider options.
	 * @return string
	 */
	private function resolve_model( array $options ) {
		$model = isset( $options['model'] ) ? trim( (string) $options['model'] ) : '';

		return $model ? $model : self::DEFAULT_MODEL;
	}

	/**
	 * Normalize the configured endpoint to a chat-completions URL.
	 *
	 * Gateways are commonly pasted as a bare host or as a `/v1` base, so
	 * the missing path is filled in rather than failing with a 404.
	 *
	 * @return string
	 */
	private function resolve_endpoint() {
		$endpoint = $this->endpoint ? $this->endpoint : self::DEFAULT_ENDPOINT;
		$endpoint = untrailingslashit( $endpoint );

		if ( preg_match( '#/v1$#', $endpoint ) ) {
			return $endpoint . '/chat/completions';
		}

		if ( preg_match( '#/(chat/)?completions$#', $endpoint ) ) {
			return $endpoint;
		}

		// A bare host such as https://example.com with no API path at all.
		if ( ! preg_match( '#/v1/#', $endpoint ) && substr_count( $endpoint, '/' ) <= 3 ) {
			return $endpoint . '/v1/chat/completions';
		}

		return $endpoint;
	}

	/**
	 * Error returned when no credential is configured.
	 *
	 * @return array
	 */
	private function missing_key_error() {
		return array(
			'success' => false,
			'error'   => __( 'کلید API تنظیم نشده است. لطفاً آن را در تنظیمات افزونه وارد کنید.', 'yuniq-ai' ),
		);
	}

	/**
	 * Error returned when the reply carried no usable text.
	 *
	 * @param string $body Raw response body.
	 * @return string
	 */
	private function empty_response_error( $body ) {
		$snippet = is_string( $body ) ? Text::truncate( wp_strip_all_tags( $body ), 180, '' ) : '';

		return __( 'پاسخ API خالی یا در قالب ناشناخته بود.', 'yuniq-ai' ) . ( $snippet ? ' ' . $snippet : '' );
	}

	/**
	 * Turn an error response into a message an admin can act on.
	 *
	 * @param int   $code HTTP status code.
	 * @param array $data Decoded response body.
	 * @return string
	 */
	private function describe_error( $code, array $data ) {
		$detail = '';

		if ( isset( $data['error']['message'] ) && is_string( $data['error']['message'] ) ) {
			$detail = $data['error']['message'];
		} elseif ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
			$detail = $data['error'];
		} elseif ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
			$detail = $data['message'];
		}

		switch ( $code ) {
			case 401:
			case 403:
				$hint = __( 'دسترسی رد شد. کلید API نامعتبر است یا منقضی شده.', 'yuniq-ai' );
				break;
			case 404:
				$hint = __( 'آدرس endpoint یافت نشد. آدرس API را در تنظیمات بررسی کنید.', 'yuniq-ai' );
				break;
			case 429:
				$hint = __( 'سقف درخواست API پر شده است. کمی بعد دوباره تلاش کنید.', 'yuniq-ai' );
				break;
			default:
				$hint = sprintf(
					/* translators: %d: HTTP status code. */
					__( 'خطای API (کد %d). کلید API، مدل و آدرس endpoint را در تنظیمات بررسی کنید.', 'yuniq-ai' ),
					$code
				);
		}

		return $detail ? $hint . ' ' . $detail : $hint;
	}

	/**
	 * Pull the incremental text out of one streamed chunk.
	 *
	 * @param array $chunk Decoded SSE payload.
	 * @return string
	 */
	private function extract_delta( array $chunk ) {
		if ( isset( $chunk['choices'][0]['delta']['content'] ) && is_string( $chunk['choices'][0]['delta']['content'] ) ) {
			return $chunk['choices'][0]['delta']['content'];
		}

		// Some gateways stream the non-delta shape instead.
		if ( isset( $chunk['choices'][0]['message']['content'] ) && is_string( $chunk['choices'][0]['message']['content'] ) ) {
			return $chunk['choices'][0]['message']['content'];
		}

		if ( isset( $chunk['choices'][0]['text'] ) && is_string( $chunk['choices'][0]['text'] ) ) {
			return $chunk['choices'][0]['text'];
		}

		return '';
	}

	/**
	 * Pull the reply text out of either non-streamed response shape.
	 *
	 * @param array $data Decoded response body.
	 * @return string Empty when the shape is unrecognized.
	 */
	private function extract_content( array $data ) {
		if ( isset( $data['choices'][0]['message']['content'] ) && is_string( $data['choices'][0]['message']['content'] ) ) {
			return $data['choices'][0]['message']['content'];
		}

		if ( isset( $data['choices'][0]['text'] ) && is_string( $data['choices'][0]['text'] ) ) {
			return $data['choices'][0]['text'];
		}

		return '';
	}
}
