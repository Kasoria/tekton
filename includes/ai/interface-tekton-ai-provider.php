<?php
declare(strict_types=1);
/**
 * AI provider interface — all providers must implement this.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

interface Tekton_AI_Provider_Interface {

	public function get_slug(): string;

	public function get_name(): string;

	/**
	 * Send a streaming request. Yields text chunks as they arrive.
	 *
	 * @param string $system_prompt System instructions.
	 * @param array  $messages      Conversation messages [{role, content}].
	 * @param array  $options       Optional: model, max_tokens, etc.
	 * @return Generator<string>
	 */
	public function send_streaming( string $system_prompt, array $messages, array $options = [] ): \Generator;

	/**
	 * Get available models for this provider.
	 *
	 * @return array<array{id: string, name: string}>
	 */
	public function get_models(): array;

	/**
	 * Validate that an API key works.
	 */
	public function validate_api_key( string $api_key ): bool;
}
