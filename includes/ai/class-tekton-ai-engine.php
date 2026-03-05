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
	 * @return array<string, string>
	 */
	public static function get_available_providers(): array {
		return [
			'anthropic'  => 'Anthropic',
			'openai'     => 'OpenAI',
			'google'     => 'Google Gemini',
			'openrouter' => 'OpenRouter',
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

	public function build_system_prompt( string $type, array $context ): string {
		$base = "You are Tekton, an AI site builder for WordPress. You generate structured component JSON that renders to HTML pages.\n\n";

		$base .= "RESPONSE FORMAT: You MUST respond with valid JSON only. No markdown, no explanation, no code fences. The JSON must match the Tekton component schema.\n\n";

		$base .= "COMPONENT SCHEMA:\n";
		$base .= "Each component has: id (comp_XXXXXXXX), type, props, styles (desktop/tablet/mobile), children.\n";
		$base .= "Available types: section, container, heading, text, image, button, grid, flex-row, flex-column, link, list, spacer, divider, video, icon.\n\n";

		$base .= "CONTENT SOURCES (never hardcode content):\n";
		$base .= '- Static: {"source":"static","value":"..."} (only for labels/ARIA)' . "\n";
		$base .= '- Post field: {"source":"post","field":"post_title|post_content|featured_image"}' . "\n";
		$base .= '- Option: {"source":"option","key":"blogname"}' . "\n";
		$base .= '- Tekton field: {"source":"field","group":"slug","field":"name","fallback":"..."}' . "\n\n";

		$base .= "DESIGN TOKENS: Use var(--tekton-*) CSS custom properties for all colors, fonts, spacing.\n\n";

		$base .= "STYLES: Each component can have styles.desktop, styles.tablet, styles.mobile with CSS property objects.\n\n";

		if ( 'generate_fullstack' === $type ) {
			$base .= "FULL-STACK MODE: Generate postTypes, fieldGroups, AND structure in a single response.\n";
			$base .= 'Format: {"type":"fullstack","postTypes":[...],"fieldGroups":[...],"structure":{"templateKey":"...","components":[...]}}' . "\n\n";
		} else {
			$base .= 'RESPONSE: {"components":[...],"styles":{},"templateKey":"...","title":"..."}' . "\n\n";
		}

		if ( ! empty( $context ) ) {
			$base .= "SITE CONTEXT:\n" . wp_json_encode( $context, JSON_PRETTY_PRINT ) . "\n";
		}

		return $base;
	}
}
