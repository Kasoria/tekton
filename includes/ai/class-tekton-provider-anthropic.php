<?php
declare(strict_types=1);
/**
 * Anthropic (Claude) AI provider.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Provider_Anthropic implements Tekton_AI_Provider_Interface {

	private const API_URL = 'https://api.anthropic.com/v1/messages';
	private string $api_key;

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	public function get_slug(): string {
		return 'anthropic';
	}

	public function get_name(): string {
		return 'Anthropic';
	}

	public function send_streaming( string $system_prompt, array $messages, array $options = [] ): \Generator {
		$model      = $options['model'] ?: 'claude-sonnet-4-20250514';
		$max_tokens = $options['max_tokens'] ?? 8192;

		$body = wp_json_encode( [
			'model'      => $model,
			'max_tokens' => $max_tokens,
			'system'     => $system_prompt,
			'messages'   => $messages,
			'stream'     => true,
		] );

		$ch = curl_init( self::API_URL );
		curl_setopt_array( $ch, [
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_HTTPHEADER     => [
				'Content-Type: application/json',
				'x-api-key: ' . $this->api_key,
				'anthropic-version: 2023-06-01',
			],
			CURLOPT_TIMEOUT        => 120,
		] );

		$buffer = '';

		curl_setopt( $ch, CURLOPT_WRITEFUNCTION, function ( $ch, string $data ) use ( &$buffer ): int {
			$buffer .= $data;
			return strlen( $data );
		} );

		$chunks = [];
		curl_setopt( $ch, CURLOPT_WRITEFUNCTION, function ( $ch, string $data ) use ( &$chunks ): int {
			$chunks[] = $data;
			return strlen( $data );
		} );

		curl_exec( $ch );

		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		if ( $http_code >= 400 ) {
			$full = implode( '', $chunks );
			$error = json_decode( $full, true );
			$message = $error['error']['message'] ?? "Anthropic API error (HTTP {$http_code})";
			throw new \RuntimeException( $message );
		}

		$buffer = '';
		foreach ( $chunks as $chunk ) {
			$buffer .= $chunk;
			$lines   = explode( "\n", $buffer );
			$buffer  = array_pop( $lines );

			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line || str_starts_with( $line, 'event:' ) ) {
					continue;
				}
				if ( ! str_starts_with( $line, 'data: ' ) ) {
					continue;
				}

				$json = json_decode( substr( $line, 6 ), true );
				if ( ! $json ) {
					continue;
				}

				if ( 'message_stop' === ( $json['type'] ?? '' ) ) {
					return;
				}

				if ( 'content_block_delta' === ( $json['type'] ?? '' ) ) {
					$text = $json['delta']['text'] ?? '';
					if ( '' !== $text ) {
						yield $text;
					}
				}
			}
		}
	}

	public function get_models(): array {
		return [
			[ 'id' => 'claude-sonnet-4-20250514', 'name' => 'Claude Sonnet 4' ],
			[ 'id' => 'claude-haiku-4-20250414',  'name' => 'Claude Haiku 4' ],
			[ 'id' => 'claude-opus-4-20250514',   'name' => 'Claude Opus 4' ],
		];
	}

	public function validate_api_key( string $api_key ): bool {
		$response = wp_remote_post( self::API_URL, [
			'headers' => [
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			],
			'body'    => wp_json_encode( [
				'model'      => 'claude-haiku-4-20250414',
				'max_tokens' => 1,
				'messages'   => [ [ 'role' => 'user', 'content' => 'hi' ] ],
			] ),
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}
}
