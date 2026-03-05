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
		$messages[] = [ 'role' => 'user', 'content' => $prompt ];

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

		// Try to extract JSON from code fences.
		if ( preg_match( '/```(?:json)?\s*\n(.*?)\n```/s', $response, $matches ) ) {
			$decoded = json_decode( $matches[1], true );
			if ( is_array( $decoded ) ) {
				$json = $decoded;
				// Extract the natural language part (everything before the code fence).
				$before = trim( substr( $response, 0, strpos( $response, '```' ) ) );
				$message = $before !== '' ? $before : 'Changes applied to the preview.';
			}
		}

		// If no code fence, try direct JSON parse (backwards compat).
		if ( ! $json ) {
			$decoded = json_decode( $response, true );
			if ( is_array( $decoded ) && ( isset( $decoded['components'] ) || isset( $decoded['type'] ) || isset( $decoded['structure'] ) ) ) {
				$json    = $decoded;
				$message = 'Changes applied to the preview.';
			}
		}

		return [
			'message' => $message,
			'json'    => $json,
		];
	}
}
