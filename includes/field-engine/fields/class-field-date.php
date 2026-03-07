<?php
declare(strict_types=1);
/**
 * Date field type.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Date extends Tekton_Field_Type {

	public function get_type(): string {
		return 'date';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$val = esc_attr( (string) $value );

		$input = '<input type="date" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '"'
			. ' value="' . $val . '" class="tekton-input" />';

		return $this->wrap( $field_name, $field_config, $input );
	}

	public function sanitize( mixed $value, array $field_config ): string {
		$val = sanitize_text_field( (string) $value );
		if ( $val && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $val ) ) {
			return '';
		}
		return $val;
	}
}
