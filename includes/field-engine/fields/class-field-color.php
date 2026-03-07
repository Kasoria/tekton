<?php
declare(strict_types=1);
/**
 * Color picker field type.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Color extends Tekton_Field_Type {

	public function get_type(): string {
		return 'color';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$val     = esc_attr( (string) $value );
		$default = esc_attr( $field_config['default'] ?? '#000000' );

		$input = '<input type="color" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '"'
			. ' value="' . ( $val ?: $default ) . '" class="tekton-color-input" />'
			. '<input type="text" class="tekton-color-text" value="' . ( $val ?: $default ) . '"'
			. ' data-target="' . esc_attr( $field_name ) . '" size="8" />';

		return $this->wrap( $field_name, $field_config, $input );
	}

	public function sanitize( mixed $value, array $field_config ): string {
		$val = sanitize_hex_color( (string) $value );
		return $val ?: '';
	}
}
