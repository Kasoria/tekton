<?php
declare(strict_types=1);
/**
 * Select field type.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Select extends Tekton_Field_Type {

	public function get_type(): string {
		return 'select';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$choices  = $field_config['choices'] ?? [];
		$multiple = ! empty( $field_config['multiple'] );
		$name     = $multiple ? esc_attr( $field_name ) . '[]' : esc_attr( $field_name );
		$multi    = $multiple ? ' multiple' : '';
		$selected = $multiple ? (array) $value : [ (string) $value ];

		$html = '<select id="' . esc_attr( $field_name ) . '" name="' . $name . '"' . $multi . ' class="tekton-select widefat">';
		if ( ! $multiple ) {
			$html .= '<option value="">' . esc_html( $field_config['placeholder'] ?? '— Select —' ) . '</option>';
		}
		foreach ( $choices as $val => $label ) {
			$sel   = in_array( (string) $val, $selected, true ) ? ' selected' : '';
			$html .= '<option value="' . esc_attr( (string) $val ) . '"' . $sel . '>' . esc_html( (string) $label ) . '</option>';
		}
		$html .= '</select>';

		return $this->wrap( $field_name, $field_config, $html );
	}

	public function sanitize( mixed $value, array $field_config ): string|array {
		$choices = array_keys( $field_config['choices'] ?? [] );

		if ( ! empty( $field_config['multiple'] ) ) {
			$values = is_array( $value ) ? $value : [ $value ];
			return array_values( array_filter( array_map( 'sanitize_text_field', $values ), function ( $v ) use ( $choices ) {
				return in_array( $v, array_map( 'strval', $choices ), true );
			} ) );
		}

		$val = sanitize_text_field( (string) $value );
		return in_array( $val, array_map( 'strval', $choices ), true ) ? $val : '';
	}
}
