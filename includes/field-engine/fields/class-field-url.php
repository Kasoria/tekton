<?php
declare(strict_types=1);
/**
 * URL field type.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Url extends Tekton_Field_Type {

	public function get_type(): string {
		return 'url';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$placeholder = esc_attr( $field_config['placeholder'] ?? 'https://' );
		$val         = esc_attr( (string) $value );

		$input = '<input type="url" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '"'
			. ' value="' . $val . '" placeholder="' . $placeholder . '"'
			. ' class="tekton-input widefat" />';

		return $this->wrap( $field_name, $field_config, $input );
	}

	public function sanitize( mixed $value, array $field_config ): string {
		return esc_url_raw( (string) $value );
	}
}
