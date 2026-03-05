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
		$model = $options['model'] ?: 'gemini-2.0-flash';
		$url   = self::API_BASE . $model . ':streamGenerateContent?alt=sse&key=' . $this->api_key;

		$contents = [];
		foreach ( $messages as $msg ) {
			$role = 'assistant' === $msg['role'] ? 'model' : 'user';
			$contents[] = [
				'role'  => $role,
				'parts' => [ [ 'text' => $msg['content'] ] ],
			];
		}

		$body = wp_json_encode( [
			'contents'          => $contents,
			'systemInstruction' => [ 'parts' => [ [ 'text' => $system_prompt ] ] ],
			'generationConfig'  => [
				'maxOutputTokens' => $options['max_tokens'] ?? 8192,
			],
		] );

		$ch     = curl_init( $url );
		$chunks = [];

		curl_setopt_array( $ch, [
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_HTTPHEADER     => [ 'Content-Type: application/json' ],
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
			$full  = implode( '', $chunks );
			$error = json_decode( $full, true );
			$message = $error['error']['message'] ?? "Google API error (HTTP {$http_code})";
			throw new \RuntimeException( $message );
		}

		$buffer = '';
		foreach ( $chunks as $chunk ) {
			$buffer .= $chunk;
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

				$text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
				if ( '' !== $text ) {
					yield $text;
				}
			}
		}
	}

	public function get_models(): array {
		return [
			[ 'id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash' ],
			[ 'id' => 'gemini-2.0-pro',   'name' => 'Gemini 2.0 Pro' ],
			[ 'id' => 'gemini-1.5-pro',   'name' => 'Gemini 1.5 Pro' ],
			[ 'id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash' ],
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
