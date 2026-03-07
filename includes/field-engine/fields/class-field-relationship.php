<?php
declare(strict_types=1);
/**
 * Relationship field type — select related posts.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Relationship extends Tekton_Field_Type {

	public function get_type(): string {
		return 'relationship';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$post_types = $field_config['postTypes'] ?? [ 'post' ];
		$max        = (int) ( $field_config['max'] ?? 0 );
		$selected   = is_array( $value ) ? array_map( 'intval', $value ) : ( $value ? [ (int) $value ] : [] );

		$html  = '<div class="tekton-relationship" data-field="' . esc_attr( $field_name ) . '"'
			. ' data-post-types="' . esc_attr( wp_json_encode( $post_types ) ) . '"'
			. ' data-max="' . $max . '">';

		// Selected items
		$html .= '<div class="tekton-relationship-selected">';
		foreach ( $selected as $post_id ) {
			$title = get_the_title( $post_id );
			if ( ! $title ) {
				continue;
			}
			$html .= '<div class="tekton-relationship-item" data-id="' . $post_id . '">'
				. '<input type="hidden" name="' . esc_attr( $field_name ) . '[]" value="' . $post_id . '" />'
				. '<span>' . esc_html( $title ) . '</span>'
				. '<button type="button" class="button-link tekton-relationship-remove">&times;</button>'
				. '</div>';
		}
		$html .= '</div>';

		// Search
		$html .= '<div class="tekton-relationship-search">';
		$html .= '<input type="text" class="tekton-relationship-search-input widefat"'
			. ' placeholder="' . esc_attr__( 'Search posts...', 'tekton' ) . '" />';
		$html .= '<div class="tekton-relationship-results"></div>';
		$html .= '</div>';

		$html .= '</div>';

		return $this->wrap( $field_name, $field_config, $html );
	}

	public function sanitize( mixed $value, array $field_config ): array {
		if ( ! is_array( $value ) ) {
			return $value ? [ absint( $value ) ] : [];
		}
		$ids = array_filter( array_map( 'absint', $value ) );
		$max = (int) ( $field_config['max'] ?? 0 );
		if ( $max > 0 ) {
			$ids = array_slice( $ids, 0, $max );
		}
		return array_values( $ids );
	}

	public function format_value( mixed $value, int $post_id, array $field_config ): mixed {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			return is_array( $decoded ) ? $decoded : [];
		}
		return is_array( $value ) ? $value : [];
	}
}
