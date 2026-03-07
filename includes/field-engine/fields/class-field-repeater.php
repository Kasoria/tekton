<?php
declare(strict_types=1);
/**
 * Repeater field type — rows of sub-fields.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Repeater extends Tekton_Field_Type {

	public function get_type(): string {
		return 'repeater';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$sub_fields  = $field_config['subFields'] ?? [];
		$rows        = is_array( $value ) ? $value : [];
		$min_rows    = (int) ( $field_config['minRows'] ?? 0 );
		$max_rows    = (int) ( $field_config['maxRows'] ?? 0 );
		$button_label = esc_html( $field_config['buttonLabel'] ?? __( 'Add Row', 'tekton' ) );

		$registry = Tekton_Core::instance()->get_module( 'field_registry' );

		$html  = '<div class="tekton-repeater" data-field="' . esc_attr( $field_name ) . '"'
			. ' data-min="' . $min_rows . '" data-max="' . $max_rows . '">';

		// Hidden count
		$html .= '<input type="hidden" name="' . esc_attr( $field_name ) . '[_count]" value="' . count( $rows ) . '" class="tekton-repeater-count" />';

		// Rows
		$html .= '<div class="tekton-repeater-rows">';
		foreach ( $rows as $i => $row_data ) {
			$html .= $this->render_row( $i, $sub_fields, $row_data, $field_name, $registry );
		}
		$html .= '</div>';

		// Template row (hidden, for JS cloning)
		$html .= '<template class="tekton-repeater-template">';
		$html .= $this->render_row( '{{INDEX}}', $sub_fields, [], $field_name, $registry );
		$html .= '</template>';

		$html .= '<button type="button" class="button tekton-repeater-add">' . $button_label . '</button>';
		$html .= '</div>';

		return $this->wrap( $field_name, $field_config, $html );
	}

	private function render_row( int|string $index, array $sub_fields, array $row_data, string $field_name, ?object $registry ): string {
		$html  = '<div class="tekton-repeater-row" data-index="' . esc_attr( (string) $index ) . '">';
		$html .= '<div class="tekton-repeater-row-header">';
		$html .= '<span class="tekton-repeater-row-num">' . ( is_int( $index ) ? $index + 1 : '' ) . '</span>';
		$html .= '<button type="button" class="button-link tekton-repeater-remove">&times;</button>';
		$html .= '</div>';
		$html .= '<div class="tekton-repeater-row-fields">';

		foreach ( $sub_fields as $sub ) {
			$sub_name  = $field_name . '[' . $index . '][' . ( $sub['name'] ?? '' ) . ']';
			$sub_value = $row_data[ $sub['name'] ?? '' ] ?? '';
			$type      = $registry ? $registry->get_type( $sub['type'] ?? 'text' ) : null;

			if ( $type ) {
				$html .= $type->render( $sub, $sub_value, $sub_name );
			}
		}

		$html .= '</div></div>';
		return $html;
	}

	public function sanitize( mixed $value, array $field_config ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$sub_fields = $field_config['subFields'] ?? [];
		$count      = (int) ( $value['_count'] ?? 0 );
		$registry   = Tekton_Core::instance()->get_module( 'field_registry' );
		$rows       = [];

		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! isset( $value[ $i ] ) || ! is_array( $value[ $i ] ) ) {
				continue;
			}
			$row = [];
			foreach ( $sub_fields as $sub ) {
				$sub_name  = $sub['name'] ?? '';
				$sub_value = $value[ $i ][ $sub_name ] ?? '';
				$type      = $registry ? $registry->get_type( $sub['type'] ?? 'text' ) : null;
				$row[ $sub_name ] = $type ? $type->sanitize( $sub_value, $sub ) : sanitize_text_field( (string) $sub_value );
			}
			$rows[] = $row;
		}

		$max = (int) ( $field_config['maxRows'] ?? 0 );
		if ( $max > 0 ) {
			$rows = array_slice( $rows, 0, $max );
		}

		return $rows;
	}

	public function format_value( mixed $value, int $post_id, array $field_config ): mixed {
		if ( is_string( $value ) ) {
			return json_decode( $value, true ) ?: [];
		}
		return is_array( $value ) ? $value : [];
	}
}
