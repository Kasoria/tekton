<?php
declare(strict_types=1);
/**
 * Google Gemini AI provider.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Provider_Google implements Tekton_AI_Provider_Interface {

	private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';
	private string $api_key;

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	public function get_slug(): string {
		return 'google';
	}

	public function get_name(): string {
		return 'Google Gemini';
	}

	public function send_streaming( string $system_prompt, array $messages, array $options = [] ): \Generator {
		$model      = $options['model'] ?: 'gemini-2.5-flash';
		$max_tokens = $options['max_tokens'] ?? 8192;
		$url        = self::API_BASE . $model . ':streamGenerateContent?alt=sse&key=' . $this->api_key;

		$contents = $this->build_contents( $messages );

		$accumulated       = '';
		$max_continuations = 4;

		for ( $attempt = 0; $attempt <= $max_continuations; $attempt++ ) {
			$body = wp_json_encode( [
				'contents'          => $contents,
				'systemInstruction' => [ 'parts' => [ [ 'text' => $system_prompt ] ] ],
				'generationConfig'  => [ 'maxOutputTokens' => $max_tokens ],
			] );

			$result = yield from $this->stream_request( $url, $body );
			$accumulated .= $result['text'];

			if ( $result['error'] ) {
				throw new \RuntimeException( $result['error'] );
			}

			if ( 'MAX_TOKENS' !== $result['finish_reason'] ) {
				break;
			}

			// Hit token limit — ask for continuation.
			$contents[] = [ 'role' => 'model', 'parts' => [ [ 'text' => $accumulated ] ] ];
			$contents[] = [ 'role' => 'user', 'parts' => [ [ 'text' => 'Continue exactly from where you left off. Do not repeat any content. Do not add any preamble — resume the JSON output immediately.' ] ] ];
		}
	}

	/**
	 * Build Gemini contents array from internal message format.
	 */
	private function build_contents( array $messages ): array {
		$contents = [];
		foreach ( $messages as $msg ) {
			$role  = 'assistant' === $msg['role'] ? 'model' : 'user';
			$parts = [];
			if ( ! empty( $msg['images'] ) && 'user' === $msg['role'] ) {
				foreach ( $msg['images'] as $img ) {
					$parts[] = [
						'inline_data' => [
							'mime_type' => $img['media_type'] ?? 'image/png',
							'data'      => $img['data'],
						],
					];
				}
			}
			$parts[] = [ 'text' => $msg['content'] ];
			$contents[] = [ 'role' => $role, 'parts' => $parts ];
		}
		return $contents;
	}

	/**
	 * Execute a single streaming request and yield text chunks.
	 *
	 * @return array{text: string, finish_reason: ?string, error: ?string}
	 */
	private function stream_request( string $url, string $body ): \Generator {
		$queue         = new \SplQueue();
		$error         = null;
		$buffer        = '';
		$error_body    = '';
		$finish_reason = null;
		$chunk_text    = '';

		$fiber = new \Fiber( function () use ( $url, $body, &$queue, &$error, &$buffer, &$error_body, &$finish_reason, &$chunk_text ) {
			$ch = curl_init( $url );
			curl_setopt_array( $ch, [
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $body,
				CURLOPT_RETURNTRANSFER => false,
				CURLOPT_HTTPHEADER     => [ 'Content-Type: application/json' ],
				CURLOPT_TIMEOUT        => 300,
				CURLOPT_WRITEFUNCTION  => function ( $ch, string $data ) use ( &$queue, &$buffer, &$error_body, &$finish_reason, &$chunk_text ): int {
					$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
					if ( $http_code >= 400 ) {
						$error_body .= $data;
						return strlen( $data );
					}

					$buffer .= $data;
					$lines   = explode( "\n", $buffer );
					$buffer  = array_pop( $lines );

					foreach ( $lines as $line ) {
						$line = trim( $line );
						if ( ! str_starts_with( $line, 'data: ' ) ) {
							continue;
						}

						$json = json_decode( substr( $line, 6 ), true );
						if ( ! $json ) {
							continue;
						}

						$fr = $json['candidates'][0]['finishReason'] ?? null;
						if ( $fr ) {
							$finish_reason = $fr;
						}

						$text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
						if ( '' !== $text ) {
							$chunk_text .= $text;
							$queue->enqueue( $text );
							\Fiber::suspend();
						}
					}

					return strlen( $data );
				},
			] );

			curl_exec( $ch );

			$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			curl_close( $ch );

			if ( $http_code >= 400 ) {
				$detail = '';
				$err_json = json_decode( $error_body, true );
				if ( ! empty( $err_json['error']['message'] ) ) {
					$detail = ': ' . $err_json['error']['message'];
				}
				$error = "Google API error (HTTP {$http_code}){$detail}";
			}
		} );

		$fiber->start();

		while ( ! $fiber->isTerminated() ) {
			while ( ! $queue->isEmpty() ) {
				yield $queue->dequeue();
			}
			if ( ! $fiber->isTerminated() ) {
				$fiber->resume();
			}
		}

		while ( ! $queue->isEmpty() ) {
			yield $queue->dequeue();
		}

		return [ 'text' => $chunk_text, 'finish_reason' => $finish_reason, 'error' => $error ];
	}

	public function get_models(): array {
		return [
			[ 'id' => 'gemini-3.1-pro-preview',        'name' => 'Gemini 3.1 Pro' ],
			[ 'id' => 'gemini-3.1-flash-lite-preview',  'name' => 'Gemini 3.1 Flash Lite' ],
			[ 'id' => 'gemini-3-flash-preview',         'name' => 'Gemini 3 Flash' ],
			[ 'id' => 'gemini-2.5-pro',                 'name' => 'Gemini 2.5 Pro' ],
			[ 'id' => 'gemini-2.5-flash',               'name' => 'Gemini 2.5 Flash' ],
		];
	}

	public function validate_api_key( string $api_key ): bool {
		$url      = self::API_BASE . '?key=' . $api_key;
		$response = wp_remote_get( $url, [ 'timeout' => 15 ] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return 200 === wp_remote_retrieve_response_code( $response );
	}
}
