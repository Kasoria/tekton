<?php
declare(strict_types=1);
/**
 * OpenAI (GPT) AI provider.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Provider_OpenAI implements Tekton_AI_Provider_Interface {

	private const API_URL = 'https://api.openai.com/v1/chat/completions';
	private string $api_key;

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	public function get_slug(): string {
		return 'openai';
	}

	public function get_name(): string {
		return 'OpenAI';
	}

	/** Models that require max_completion_tokens instead of max_tokens. */
	private const COMPLETION_TOKEN_MODELS = [ 'o1', 'o1-mini', 'o1-pro', 'o3', 'o3-mini', 'o4-mini' ];

	/** Models that do not support system role messages. */
	private const NO_SYSTEM_ROLE_MODELS = [ 'o1', 'o1-mini', 'o1-pro' ];

	public function send_streaming( string $system_prompt, array $messages, array $options = [] ): \Generator {
		$model      = $options['model'] ?: 'gpt-4o';
		$max_tokens = $options['max_tokens'] ?? 8192;

		$is_reasoning = in_array( $model, self::COMPLETION_TOKEN_MODELS, true )
		             || str_starts_with( $model, 'o1' )
		             || str_starts_with( $model, 'o3' )
		             || str_starts_with( $model, 'o4' )
		             || str_starts_with( $model, 'gpt-5' );
		$no_system   = in_array( $model, self::NO_SYSTEM_ROLE_MODELS, true );

		$api_messages = [];

		// System prompt: use developer role for reasoning models, system for others.
		if ( $no_system ) {
			$api_messages[] = [ 'role' => 'developer', 'content' => $system_prompt ];
		} else {
			$api_messages[] = [ 'role' => 'system', 'content' => $system_prompt ];
		}

		foreach ( $messages as $msg ) {
			if ( ! empty( $msg['images'] ) && 'user' === $msg['role'] ) {
				$content = [ [ 'type' => 'text', 'text' => $msg['content'] ] ];
				foreach ( $msg['images'] as $img ) {
					$mime = $img['media_type'] ?? 'image/png';
					$content[] = [
						'type'      => 'image_url',
						'image_url' => [
							'url' => 'data:' . $mime . ';base64,' . $img['data'],
						],
					];
				}
				$api_messages[] = [ 'role' => 'user', 'content' => $content ];
			} else {
				$api_messages[] = [ 'role' => $msg['role'], 'content' => $msg['content'] ];
			}
		}

		$request = [
			'model'    => $model,
			'messages' => $api_messages,
			'stream'   => true,
		];

		if ( $is_reasoning ) {
			$request['max_completion_tokens'] = $max_tokens;
		} else {
			$request['max_tokens'] = $max_tokens;
		}

		$body = wp_json_encode( $request );

		$queue      = new \SplQueue();
		$error      = null;
		$buffer     = '';
		$error_body = '';

		$fiber = new \Fiber( function () use ( $body, &$queue, &$error, &$buffer, &$error_body ) {
			$ch = curl_init( self::API_URL );
			curl_setopt_array( $ch, [
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $body,
				CURLOPT_RETURNTRANSFER => false,
				CURLOPT_HTTPHEADER     => [
					'Content-Type: application/json',
					'Authorization: Bearer ' . $this->api_key,
				],
				CURLOPT_TIMEOUT        => 120,
				CURLOPT_WRITEFUNCTION  => function ( $ch, string $data ) use ( &$queue, &$buffer, &$error_body ): int {
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
						if ( '' === $line || ! str_starts_with( $line, 'data: ' ) ) {
							continue;
						}

						$payload = substr( $line, 6 );
						if ( '[DONE]' === $payload ) {
							continue;
						}

						$json = json_decode( $payload, true );
						if ( ! $json ) {
							continue;
						}

						$text = $json['choices'][0]['delta']['content'] ?? '';
						if ( '' !== $text ) {
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
				$error = "OpenAI API error (HTTP {$http_code}){$detail}";
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

		if ( $error ) {
			throw new \RuntimeException( $error );
		}
	}

	public function get_models(): array {
		return [
			[ 'id' => 'gpt-4o',       'name' => 'GPT-4o' ],
			[ 'id' => 'gpt-4o-mini',  'name' => 'GPT-4o Mini' ],
			[ 'id' => 'gpt-4-turbo',  'name' => 'GPT-4 Turbo' ],
			[ 'id' => 'o1',           'name' => 'o1' ],
			[ 'id' => 'o1-mini',      'name' => 'o1 Mini' ],
		];
	}

	public function validate_api_key( string $api_key ): bool {
		$response = wp_remote_get( 'https://api.openai.com/v1/models', [
			'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return 200 === wp_remote_retrieve_response_code( $response );
	}
}
