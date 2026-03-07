<?php
declare(strict_types=1);
/**
 * Textarea field type.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Textarea extends Tekton_Field_Type {

	public function get_type(): string {
		return 'textarea';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$placeholder = esc_attr( $field_config['placeholder'] ?? '' );
		$rows        = (int) ( $field_config['rows'] ?? 4 );
		$val         = esc_textarea( (string) $value );

		$input = '<textarea id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '"'
			. ' rows="' . $rows . '" placeholder="' . $placeholder . '"'
			. ' class="tekton-textarea widefat">' . $val . '</textarea>';

		return $this->wrap( $field_name, $field_config, $input );
	}

	public function sanitize( mixed $value, array $field_config ): string {
		return sanitize_textarea_field( (string) $value );
	}
}
