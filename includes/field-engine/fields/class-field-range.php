<?php
declare(strict_types=1);
/**
 * Range (slider) field type.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Range extends Tekton_Field_Type {

	public function get_type(): string {
		return 'range';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$min  = isset( $field_config['min'] ) ? ' min="' . esc_attr( (string) $field_config['min'] ) . '"' : ' min="0"';
		$max  = isset( $field_config['max'] ) ? ' max="' . esc_attr( (string) $field_config['max'] ) . '"' : ' max="100"';
		$step = isset( $field_config['step'] ) ? ' step="' . esc_attr( (string) $field_config['step'] ) . '"' : '';
		$val  = esc_attr( (string) $value );

		$input = '<input type="range" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '"'
			. ' value="' . $val . '"' . $min . $max . $step
			. ' class="tekton-range tekton-range-input" />'
			. ' <span class="tekton-range-value">' . $val . '</span>';

		return $this->wrap( $field_name, $field_config, $input );
	}

	public function sanitize( mixed $value, array $field_config ): string {
		if ( $value === '' || $value === null ) {
			return '';
		}
		return (string) floatval( $value );
	}

	public function validate( mixed $value, array $field_config ): true|string {
		$parent = parent::validate( $value, $field_config );
		if ( $parent !== true ) {
			return $parent;
		}
		if ( $value === '' || $value === null ) {
			return true;
		}
		$num = floatval( $value );
		if ( isset( $field_config['min'] ) && $num < (float) $field_config['min'] ) {
			return sprintf( '%s must be at least %s.', $field_config['label'] ?? 'Value', $field_config['min'] );
		}
		if ( isset( $field_config['max'] ) && $num > (float) $field_config['max'] ) {
			return sprintf( '%s must be at most %s.', $field_config['label'] ?? 'Value', $field_config['max'] );
		}
		return true;
	}
}
