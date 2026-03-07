<?php
declare(strict_types=1);
/**
 * Group field type — a single set of sub-fields rendered together.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Group extends Tekton_Field_Type {

	public function get_type(): string {
		return 'group';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$sub_fields = $field_config['subFields'] ?? [];
		$data       = is_array( $value ) ? $value : ( is_string( $value ) ? ( json_decode( $value, true ) ?: [] ) : [] );

		$registry = Tekton_Core::instance()->get_module( 'field_registry' );

		$html = '<div class="tekton-group" data-field="' . esc_attr( $field_name ) . '">';

		foreach ( $sub_fields as $sub ) {
			$sub_name  = $field_name . '[' . ( $sub['name'] ?? '' ) . ']';
			$sub_value = $data[ $sub['name'] ?? '' ] ?? '';
			$type      = $registry ? $registry->get_type( $sub['type'] ?? 'text' ) : null;

			if ( $type ) {
				$html .= $type->render( $sub, $sub_value, $sub_name );
			}
		}

		$html .= '</div>';

		return $this->wrap( $field_name, $field_config, $html );
	}

	public function sanitize( mixed $value, array $field_config ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$sub_fields = $field_config['subFields'] ?? [];
		$registry   = Tekton_Core::instance()->get_module( 'field_registry' );
		$result     = [];

		foreach ( $sub_fields as $sub ) {
			$sub_name  = $sub['name'] ?? '';
			$sub_value = $value[ $sub_name ] ?? '';
			$type      = $registry ? $registry->get_type( $sub['type'] ?? 'text' ) : null;
			$result[ $sub_name ] = $type ? $type->sanitize( $sub_value, $sub ) : sanitize_text_field( (string) $sub_value );
		}

		return $result;
	}

	public function format_value( mixed $value, int $post_id, array $field_config ): mixed {
		if ( is_string( $value ) ) {
			return json_decode( $value, true ) ?: [];
		}
		return is_array( $value ) ? $value : [];
	}
}
