<?php
declare(strict_types=1);
/**
 * Radio field type (single choice).
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Radio extends Tekton_Field_Type {

	public function get_type(): string {
		return 'radio';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$choices = $field_config['choices'] ?? [];

		$html = '<div class="tekton-radio-group">';
		foreach ( $choices as $val => $label ) {
			$checked = ( (string) $val === (string) $value ) ? ' checked' : '';
			$id      = esc_attr( $field_name . '_' . $val );
			$html   .= '<label class="tekton-radio-item" for="' . $id . '">'
				. '<input type="radio" id="' . $id . '" name="' . esc_attr( $field_name ) . '"'
				. ' value="' . esc_attr( (string) $val ) . '"' . $checked . ' />'
				. ' ' . esc_html( (string) $label )
				. '</label>';
		}
		$html .= '</div>';

		return $this->wrap( $field_name, $field_config, $html );
	}

	public function sanitize( mixed $value, array $field_config ): string {
		$choices = array_map( 'strval', array_keys( $field_config['choices'] ?? [] ) );
		$val     = sanitize_text_field( (string) $value );
		return in_array( $val, $choices, true ) ? $val : '';
	}
}
