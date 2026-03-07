<?php
declare(strict_types=1);
/**
 * Abstract base class for all field types.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

abstract class Tekton_Field_Type {

	abstract public function get_type(): string;

	/**
	 * Render the field input HTML for the admin meta box.
	 */
	abstract public function render( array $field_config, mixed $value, string $field_name ): string;

	/**
	 * Sanitize the submitted value before saving.
	 */
	abstract public function sanitize( mixed $value, array $field_config ): mixed;

	/**
	 * Validate the value. Return true on success, error string on failure.
	 */
	public function validate( mixed $value, array $field_config ): true|string {
		if ( ! empty( $field_config['required'] ) && ( $value === '' || $value === null ) ) {
			return sprintf( '%s is required.', $field_config['label'] ?? $field_config['name'] ?? 'Field' );
		}
		return true;
	}

	/**
	 * Format the value for frontend output.
	 */
	public function format_value( mixed $value, int $post_id, array $field_config ): mixed {
		return $value;
	}

	/**
	 * Enqueue any admin assets this field type needs.
	 */
	public function enqueue_admin_assets(): void {}

	/**
	 * Helper: render a standard wrapper around a field input.
	 */
	protected function wrap( string $field_name, array $field_config, string $input_html ): string {
		$label    = esc_html( $field_config['label'] ?? $field_config['name'] ?? '' );
		$required = ! empty( $field_config['required'] ) ? ' <span class="tekton-required">*</span>' : '';
		$desc     = ! empty( $field_config['description'] ) ? '<p class="tekton-field-desc">' . esc_html( $field_config['description'] ) . '</p>' : '';

		return '<div class="tekton-field tekton-field--' . esc_attr( $this->get_type() ) . '">'
			. '<label class="tekton-field-label" for="' . esc_attr( $field_name ) . '">' . $label . $required . '</label>'
			. '<div class="tekton-field-input">' . $input_html . '</div>'
			. $desc
			. '</div>';
	}
}
