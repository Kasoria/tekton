<?php
declare(strict_types=1);
/**
 * Checkbox field type (multiple choices).
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Checkbox extends Tekton_Field_Type {

	public function get_type(): string {
		return 'checkbox';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$choices  = $field_config['choices'] ?? [];
		$selected = is_array( $value ) ? $value : ( $value ? [ $value ] : [] );

		$html = '<div class="tekton-checkbox-group">';
		foreach ( $choices as $val => $label ) {
			$checked = in_array( (string) $val, $selected, true ) ? ' checked' : '';
			$id      = esc_attr( $field_name . '_' . $val );
			$html   .= '<label class="tekton-checkbox-item" for="' . $id . '">'
				. '<input type="checkbox" id="' . $id . '" name="' . esc_attr( $field_name ) . '[]"'
				. ' value="' . esc_attr( (string) $val ) . '"' . $checked . ' />'
				. ' ' . esc_html( (string) $label )
				. '</label>';
		}
		$html .= '</div>';

		return $this->wrap( $field_name, $field_config, $html );
	}

	public function sanitize( mixed $value, array $field_config ): array {
		$choices = array_map( 'strval', array_keys( $field_config['choices'] ?? [] ) );
		$values  = is_array( $value ) ? $value : [];
		return array_values( array_filter( array_map( 'sanitize_text_field', $values ), function ( $v ) use ( $choices ) {
			return in_array( $v, $choices, true );
		} ) );
	}

	public function format_value( mixed $value, int $post_id, array $field_config ): mixed {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			return is_array( $decoded ) ? $decoded : [ $value ];
		}
		return is_array( $value ) ? $value : [];
	}
}
