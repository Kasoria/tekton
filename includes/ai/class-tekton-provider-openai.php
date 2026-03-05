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

	public function send_streaming( string $system_prompt, array $messages, array $options = [] ): \Generator {
		$model      = $options['model'] ?: 'gpt-4o';
		$max_tokens = $options['max_tokens'] ?? 8192;

		$api_messages   = [];
		$api_messages[] = [ 'role' => 'system', 'content' => $system_prompt ];
		foreach ( $messages as $msg ) {
			$api_messages[] = [
				'role'    => $msg['role'],
				'content' => $msg['content'],
			];
		}

		$body = wp_json_encode( [
			'model'      => $model,
			'messages'   => $api_messages,
			'stream'     => true,
			'max_tokens' => $max_tokens,
		] );

		$ch = curl_init( self::API_URL );
		$chunks = [];

		curl_setopt_array( $ch, [
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_HTTPHEADER     => [
				'Content-Type: application/json',
				'Authorization: Bearer ' . $this->api_key,
			],
			CURLOPT_TIMEOUT        => 120,
			CURLOPT_WRITEFUNCTION  => function ( $ch, string $data ) use ( &$chunks ): int {
				$chunks[] = $data;
				return strlen( $data );
			},
		] );

		curl_exec( $ch );

		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		if ( $http_code >= 400 ) {
			$full = implode( '', $chunks );
			$error = json_decode( $full, true );
			$message = $error['error']['message'] ?? "OpenAI API error (HTTP {$http_code})";
			throw new \RuntimeException( $message );
		}

		yield from $this->parse_sse_chunks( $chunks );
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

	/**
	 * @param string[] $chunks
	 * @return \Generator<string>
	 */
	private function parse_sse_chunks( array $chunks ): \Generator {
		$buffer = '';

		foreach ( $chunks as $chunk ) {
			$buffer .= $chunk;
			$lines   = explode( "\n", $buffer );
			$buffer  = array_pop( $lines );

			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line || ! str_starts_with( $line, 'data: ' ) ) {
					continue;
				}

				$data = substr( $line, 6 );
				if ( '[DONE]' === $data ) {
					return;
				}

				$json = json_decode( $data, true );
				if ( ! $json ) {
					continue;
				}

				$text = $json['choices'][0]['delta']['content'] ?? '';
				if ( '' !== $text ) {
					yield $text;
				}
			}
		}
	}
}
