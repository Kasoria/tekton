<?php
declare(strict_types=1);
/**
 * Flexible Content field type — dynamic layouts with sub-fields.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Flexible_Content extends Tekton_Field_Type {

	public function get_type(): string {
		return 'flexible_content';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$layouts      = $field_config['layouts'] ?? [];
		$rows         = is_array( $value ) ? $value : ( is_string( $value ) ? ( json_decode( $value, true ) ?: [] ) : [] );
		$button_label = esc_html( $field_config['buttonLabel'] ?? __( 'Add Layout', 'tekton' ) );

		$registry = Tekton_Core::instance()->get_module( 'field_registry' );

		// Index layouts by name for lookup.
		$layouts_by_name = [];
		foreach ( $layouts as $layout ) {
			$layouts_by_name[ $layout['name'] ?? '' ] = $layout;
		}

		$html  = '<div class="tekton-flexible-content" data-field="' . esc_attr( $field_name ) . '"'
			. ' data-layouts="' . esc_attr( wp_json_encode( $layouts ) ) . '">';

		// Hidden count
		$html .= '<input type="hidden" name="' . esc_attr( $field_name ) . '[_count]" value="' . count( $rows ) . '" class="tekton-fc-count" />';

		// Existing rows
		$html .= '<div class="tekton-fc-rows">';
		foreach ( $rows as $i => $row_data ) {
			$layout_name = $row_data['_layout'] ?? '';
			$layout_def  = $layouts_by_name[ $layout_name ] ?? null;

			if ( ! $layout_def ) {
				continue;
			}

			$html .= $this->render_row( $i, $layout_def, $row_data, $field_name, $registry );
		}
		$html .= '</div>';

		// Templates (one per layout, hidden)
		foreach ( $layouts as $layout ) {
			$html .= '<template class="tekton-fc-template" data-layout="' . esc_attr( $layout['name'] ?? '' ) . '">';
			$html .= $this->render_row( '{{INDEX}}', $layout, [], $field_name, $registry );
			$html .= '</template>';
		}

		// Add button with layout picker
		$html .= '<div class="tekton-fc-add">';
		$html .= '<button type="button" class="button tekton-fc-add-btn">' . $button_label . '</button>';
		$html .= '<div class="tekton-fc-layout-picker" style="display:none">';
		foreach ( $layouts as $layout ) {
			$html .= '<button type="button" class="button tekton-fc-pick-layout" data-layout="' . esc_attr( $layout['name'] ?? '' ) . '">'
				. esc_html( $layout['label'] ?? $layout['name'] ?? '' ) . '</button>';
		}
		$html .= '</div>';
		$html .= '</div>';

		$html .= '</div>';

		return $this->wrap( $field_name, $field_config, $html );
	}

	private function render_row( int|string $index, array $layout, array $row_data, string $field_name, ?object $registry ): string {
		$layout_name = $layout['name'] ?? '';
		$layout_label = $layout['label'] ?? $layout_name;
		$sub_fields  = $layout['subFields'] ?? [];

		$html  = '<div class="tekton-fc-row" data-index="' . esc_attr( (string) $index ) . '" data-layout="' . esc_attr( $layout_name ) . '">';
		$html .= '<div class="tekton-fc-row-header">';
		$html .= '<span class="tekton-fc-row-label">' . esc_html( $layout_label ) . '</span>';
		$html .= '<button type="button" class="button-link tekton-fc-remove">&times;</button>';
		$html .= '</div>';

		// Hidden layout identifier
		$html .= '<input type="hidden" name="' . esc_attr( $field_name ) . '[' . $index . '][_layout]" value="' . esc_attr( $layout_name ) . '" />';

		$html .= '<div class="tekton-fc-row-fields">';
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

		$layouts = $field_config['layouts'] ?? [];
		$count   = (int) ( $value['_count'] ?? 0 );

		// Index layouts by name.
		$layouts_by_name = [];
		foreach ( $layouts as $layout ) {
			$layouts_by_name[ $layout['name'] ?? '' ] = $layout;
		}

		$registry = Tekton_Core::instance()->get_module( 'field_registry' );
		$rows     = [];

		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! isset( $value[ $i ] ) || ! is_array( $value[ $i ] ) ) {
				continue;
			}

			$layout_name = sanitize_text_field( $value[ $i ]['_layout'] ?? '' );
			$layout_def  = $layouts_by_name[ $layout_name ] ?? null;

			if ( ! $layout_def ) {
				continue;
			}

			$row = [ '_layout' => $layout_name ];

			foreach ( $layout_def['subFields'] ?? [] as $sub ) {
				$sub_name  = $sub['name'] ?? '';
				$sub_value = $value[ $i ][ $sub_name ] ?? '';
				$type      = $registry ? $registry->get_type( $sub['type'] ?? 'text' ) : null;
				$row[ $sub_name ] = $type ? $type->sanitize( $sub_value, $sub ) : sanitize_text_field( (string) $sub_value );
			}

			$rows[] = $row;
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
