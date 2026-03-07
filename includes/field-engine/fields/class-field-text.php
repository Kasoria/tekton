<?php
declare(strict_types=1);
/**
 * Text field type.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Text extends Tekton_Field_Type {

	public function get_type(): string {
		return 'text';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$placeholder = esc_attr( $field_config['placeholder'] ?? '' );
		$maxlength   = ! empty( $field_config['maxLength'] ) ? ' maxlength="' . (int) $field_config['maxLength'] . '"' : '';
		$val         = esc_attr( (string) $value );

		$input = '<input type="text" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '"'
			. ' value="' . $val . '" placeholder="' . $placeholder . '"' . $maxlength
			. ' class="tekton-input widefat" />';

		return $this->wrap( $field_name, $field_config, $input );
	}

	public function sanitize( mixed $value, array $field_config ): string {
		$val = sanitize_text_field( (string) $value );
		if ( ! empty( $field_config['maxLength'] ) ) {
			$val = mb_substr( $val, 0, (int) $field_config['maxLength'] );
		}
		return $val;
	}
}
