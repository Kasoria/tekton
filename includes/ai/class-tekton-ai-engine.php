<?php
declare(strict_types=1);
/**
 * AI engine orchestrator — provider-agnostic interface.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_AI_Engine {

	private Tekton_Security              $security;
	private ?Tekton_AI_Provider_Interface $provider = null;

	public function __construct( Tekton_Security $security ) {
		$this->security = $security;
	}

	/**
	 * Send a message through the current provider. Yields streamed text chunks.
	 *
	 * @return \Generator<string>
	 */
	public function send_message( string $prompt, array $conversation_history = [], array $options = [] ): \Generator {
		$provider = $this->get_provider();

		$system_prompt = $this->build_system_prompt(
			$options['type'] ?? 'generate_page',
			$options['context'] ?? []
		);

		$messages = [];
		foreach ( $conversation_history as $msg ) {
			$messages[] = [
				'role'    => $msg['role'] ?? 'user',
				'content' => $msg['content'] ?? '',
			];
		}

		// Build the current user message, optionally with images.
		$images = $options['images'] ?? [];
		if ( ! empty( $images ) ) {
			$messages[] = [
				'role'    => 'user',
				'content' => $prompt,
				'images'  => $images,
			];
		} else {
			$messages[] = [ 'role' => 'user', 'content' => $prompt ];
		}

		yield from $provider->send_streaming( $system_prompt, $messages, [
			'model'      => $options['model'] ?? get_option( 'tekton_ai_model', '' ),
			'max_tokens' => (int) get_option( 'tekton_ai_max_tokens', 8192 ),
		] );
	}

	public function get_provider(): Tekton_AI_Provider_Interface {
		if ( null === $this->provider ) {
			$slug = get_option( 'tekton_ai_provider', 'anthropic' );
			$this->set_provider( $slug );
		}
		return $this->provider;
	}

	public function set_provider( string $slug ): void {
		$api_key_encrypted = get_option( "tekton_api_key_{$slug}", '' );
		$api_key           = $this->security->decrypt_api_key( $api_key_encrypted );

		$this->provider = match ( $slug ) {
			'anthropic'  => new Tekton_Provider_Anthropic( $api_key ),
			'openai'     => new Tekton_Provider_OpenAI( $api_key ),
			'google'     => new Tekton_Provider_Google( $api_key ),
			'openrouter' => new Tekton_Provider_OpenRouter( $api_key ),
			default      => new Tekton_Provider_Anthropic( $api_key ),
		};
	}

	/**
	 * @return array<string, array{name: string}>
	 */
	public static function get_available_providers(): array {
		return [
			'anthropic'  => [ 'name' => 'Anthropic' ],
			'openai'     => [ 'name' => 'OpenAI' ],
			'google'     => [ 'name' => 'Google Gemini' ],
			'openrouter' => [ 'name' => 'OpenRouter' ],
		];
	}

	public function get_models_for_provider( string $slug ): array {
		$api_key_encrypted = get_option( "tekton_api_key_{$slug}", '' );
		$api_key           = $this->security->decrypt_api_key( $api_key_encrypted );

		$provider = match ( $slug ) {
			'anthropic'  => new Tekton_Provider_Anthropic( $api_key ),
			'openai'     => new Tekton_Provider_OpenAI( $api_key ),
			'google'     => new Tekton_Provider_Google( $api_key ),
			'openrouter' => new Tekton_Provider_OpenRouter( $api_key ),
			default      => new Tekton_Provider_Anthropic( $api_key ),
		};

		return $provider->get_models();
	}

	/**
	 * Build the system prompt from template files.
	 */
	public function build_system_prompt( string $type, array $context ): string {
		$templates_dir = TEKTON_DIR . 'templates/';

		// Load base prompt.
		$prompt = $this->load_template( $templates_dir . 'system-prompt-base.md' );

		// Load type-specific prompt.
		$type_map = [
			'generate_page'      => 'system-prompt-page.md',
			'generate_fullstack' => 'system-prompt-fullstack.md',
			'fullstack'          => 'system-prompt-fullstack.md',
			'generate_plugin'    => 'system-prompt-plugin.md',
			'generate_component' => 'system-prompt-component.md',
			'modify_page'        => 'system-prompt-page.md',
			'generate_theme'     => 'system-prompt-theme.md',
		];

		$type_file = $type_map[ $type ] ?? 'system-prompt-page.md';
		$prompt   .= "\n\n" . $this->load_template( $templates_dir . $type_file );

		// Append site context.
		if ( ! empty( $context ) ) {
			$context_template = $this->load_template( $templates_dir . 'context-template.md' );
			$context_json     = wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			$prompt          .= "\n\n" . str_replace( '{{context_json}}', $context_json, $context_template );
		}

		return $prompt;
	}

	/**
	 * Load a template file, returning empty string if not found.
	 */
	private function load_template( string $path ): string {
		if ( ! file_exists( $path ) ) {
			return '';
		}
		return (string) file_get_contents( $path );
	}

	/**
	 * Parse an AI response into natural language message and structured data.
	 *
	 * The AI is instructed to respond with natural language first,
	 * then JSON inside a ```json code fence.
	 *
	 * @return array{message: string, json: ?array}
	 */
	public static function parse_response( string $response ): array {
		$message = $response;
		$json    = null;

		// Try to extract JSON from code fences (greedy — captures across continuations).
		if ( preg_match( '/```(?:json)?\s*\n(.+)\n```/s', $response, $matches ) ) {
			// If continuations produced multiple fences, strip inner fence markers.
			$raw_json = preg_replace( '/\n```\s*\n*```(?:json)?\s*\n/', "\n", $matches[1] );
			$decoded  = json_decode( $raw_json, true );

			// If decode fails, try repairing common AI JSON issues.
			if ( null === $decoded ) {
				$decoded = self::try_repair_json( $raw_json );
			}

			if ( is_array( $decoded ) ) {
				$json = $decoded;
				$before = trim( substr( $response, 0, strpos( $response, '```' ) ) );
				$message = $before !== '' ? $before : 'Changes applied to the preview.';
			}
		}

		// If no code fence, try direct JSON parse (backwards compat).
		if ( ! $json ) {
			$decoded = json_decode( $response, true );
			if ( is_array( $decoded ) && self::is_tekton_json( $decoded ) ) {
				$json    = $decoded;
				$message = 'Changes applied to the preview.';
			}
		}

		// Last resort: if the response contains JSON that starts with { but wasn't
		// in a code fence (e.g. from a continuation that dropped the fence), try
		// to extract the outermost JSON object.
		if ( ! $json ) {
			$first_brace = strpos( $response, '{' );
			if ( false !== $first_brace ) {
				$candidate = substr( $response, $first_brace );
				$decoded   = json_decode( $candidate, true );
				if ( null === $decoded ) {
					$decoded = self::try_repair_json( $candidate );
				}
				if ( is_array( $decoded ) && self::is_tekton_json( $decoded ) ) {
					$json    = $decoded;
					$before  = trim( substr( $response, 0, $first_brace ) );
					$message = $before !== '' ? $before : 'Changes applied to the preview.';
				}
			}
		}

		return [
			'message' => $message,
			'json'    => $json,
		];
	}

	/**
	 * Check if a decoded JSON array contains Tekton-specific keys.
	 */
	private static function is_tekton_json( array $data ): bool {
		return isset( $data['components'] )
			|| isset( $data['operations'] )
			|| isset( $data['structure'] )
			|| isset( $data['type'] )
			|| isset( $data['posts'] )
			|| isset( $data['postTypes'] )
			|| isset( $data['fieldGroups'] );
	}

	/**
	 * Attempt to repair common AI-generated JSON errors.
	 *
	 * @return ?array Decoded JSON or null if repair failed.
	 */
	private static function try_repair_json( string $raw ): ?array {
		// Remove trailing commas before } or ] (common AI mistake).
		$repaired = preg_replace( '/,\s*([}\]])/', '$1', $raw );

		// Try decoding after trailing comma fix.
		$decoded = json_decode( $repaired, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		// Fix unescaped control characters (newlines/tabs inside strings).
		$repaired = preg_replace_callback(
			'/"((?:[^"\\\\]|\\\\.)*)"/s',
			function ( $m ) {
				$inner = str_replace( [ "\n", "\r", "\t" ], [ '\\n', '\\r', '\\t' ], $m[1] );
				return '"' . $inner . '"';
			},
			$repaired
		);

		$decoded = json_decode( $repaired, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		return null;
	}
}
