<?php
declare(strict_types=1);
/**
 * Code editor field type — monospace textarea for code input.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Code extends Tekton_Field_Type {

	public function get_type(): string {
		return 'code';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$rows     = (int) ( $field_config['rows'] ?? 10 );
		$language = esc_attr( $field_config['language'] ?? '' );
		$val      = esc_textarea( (string) $value );

		$input = '<textarea id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '"'
			. ' rows="' . $rows . '"'
			. ( $language ? ' data-language="' . $language . '"' : '' )
			. ' class="tekton-code widefat" style="font-family:monospace;tab-size:4;">' . $val . '</textarea>';

		return $this->wrap( $field_name, $field_config, $input );
	}

	public function sanitize( mixed $value, array $field_config ): string {
		return sanitize_textarea_field( (string) $value );
	}
}
