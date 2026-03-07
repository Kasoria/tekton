<?php
declare(strict_types=1);
/**
 * Email field type.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Email extends Tekton_Field_Type {

	public function get_type(): string {
		return 'email';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$placeholder = esc_attr( $field_config['placeholder'] ?? '' );
		$val         = esc_attr( (string) $value );

		$input = '<input type="email" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '"'
			. ' value="' . $val . '" placeholder="' . $placeholder . '"'
			. ' class="tekton-input widefat" />';

		return $this->wrap( $field_name, $field_config, $input );
	}

	public function sanitize( mixed $value, array $field_config ): string {
		return sanitize_email( (string) $value );
	}

	public function validate( mixed $value, array $field_config ): true|string {
		$parent = parent::validate( $value, $field_config );
		if ( $parent !== true ) {
			return $parent;
		}
		if ( $value !== '' && $value !== null && ! is_email( (string) $value ) ) {
			return sprintf( '%s must be a valid email address.', $field_config['label'] ?? 'Email' );
		}
		return true;
	}
}
