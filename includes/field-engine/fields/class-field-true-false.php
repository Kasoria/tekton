<?php
declare(strict_types=1);
/**
 * True/False (toggle) field type.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_True_False extends Tekton_Field_Type {

	public function get_type(): string {
		return 'true_false';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$checked = ( $value === '1' || $value === 1 || $value === true ) ? ' checked' : '';
		$label   = esc_html( $field_config['toggleLabel'] ?? $field_config['label'] ?? '' );

		$input = '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="0" />'
			. '<label class="tekton-toggle" for="' . esc_attr( $field_name ) . '">'
			. '<input type="checkbox" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '"'
			. ' value="1"' . $checked . ' />'
			. ' ' . $label
			. '</label>';

		return $this->wrap( $field_name, $field_config, $input );
	}

	public function sanitize( mixed $value, array $field_config ): string {
		return $value ? '1' : '0';
	}

	public function format_value( mixed $value, int $post_id, array $field_config ): bool {
		return $value === '1' || $value === 1 || $value === true;
	}
}
