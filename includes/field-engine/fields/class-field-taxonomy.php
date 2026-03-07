<?php
declare(strict_types=1);
/**
 * Taxonomy field type — select taxonomy terms.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Taxonomy extends Tekton_Field_Type {

	public function get_type(): string {
		return 'taxonomy';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$taxonomy = $field_config['taxonomy'] ?? 'category';
		$multiple = ! empty( $field_config['multiple'] );
		$name     = $multiple ? esc_attr( $field_name ) . '[]' : esc_attr( $field_name );
		$multi    = $multiple ? ' multiple' : '';
		$selected = $multiple ? array_map( 'intval', (array) $value ) : [ (int) $value ];

		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $terms ) ) {
			$terms = [];
		}

		$html = '<select id="' . esc_attr( $field_name ) . '" name="' . $name . '"' . $multi . ' class="tekton-select widefat">';
		if ( ! $multiple ) {
			$html .= '<option value="">' . esc_html( $field_config['placeholder'] ?? __( '— Select —', 'tekton' ) ) . '</option>';
		}

		$html .= $this->render_terms_hierarchy( $terms, $selected, 0 );

		$html .= '</select>';

		return $this->wrap( $field_name, $field_config, $html );
	}

	/**
	 * Render terms with hierarchy (indented children).
	 */
	private function render_terms_hierarchy( array $terms, array $selected, int $parent, int $depth = 0 ): string {
		$html   = '';
		$prefix = str_repeat( '— ', $depth );

		foreach ( $terms as $term ) {
			if ( (int) $term->parent !== $parent ) {
				continue;
			}

			$sel   = in_array( (int) $term->term_id, $selected, true ) ? ' selected' : '';
			$html .= '<option value="' . esc_attr( (string) $term->term_id ) . '"' . $sel . '>'
				. esc_html( $prefix . $term->name )
				. '</option>';

			$html .= $this->render_terms_hierarchy( $terms, $selected, (int) $term->term_id, $depth + 1 );
		}

		return $html;
	}

	public function sanitize( mixed $value, array $field_config ): string|array {
		$taxonomy = $field_config['taxonomy'] ?? 'category';

		if ( ! empty( $field_config['multiple'] ) ) {
			$values = is_array( $value ) ? $value : [ $value ];
			$ids    = array_filter( array_map( 'absint', $values ) );
			$valid  = [];

			foreach ( $ids as $term_id ) {
				$term = get_term( $term_id, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$valid[] = $term_id;
				}
			}

			return $valid;
		}

		$term_id = absint( $value );
		if ( $term_id ) {
			$term = get_term( $term_id, $taxonomy );
			if ( ! $term || is_wp_error( $term ) ) {
				return '';
			}
		}

		return $term_id ? (string) $term_id : '';
	}

	public function format_value( mixed $value, int $post_id, array $field_config ): mixed {
		$return_format = $field_config['returnFormat'] ?? 'id';
		$taxonomy      = $field_config['taxonomy'] ?? 'category';

		if ( ! empty( $field_config['multiple'] ) ) {
			$ids = is_array( $value ) ? array_map( 'intval', $value ) : [];

			if ( is_string( $value ) ) {
				$decoded = json_decode( $value, true );
				$ids     = is_array( $decoded ) ? array_map( 'intval', $decoded ) : [];
			}

			if ( $return_format === 'object' ) {
				$terms = [];
				foreach ( $ids as $term_id ) {
					$term = get_term( $term_id, $taxonomy );
					if ( $term && ! is_wp_error( $term ) ) {
						$terms[] = $term;
					}
				}
				return $terms;
			}

			return $ids;
		}

		$term_id = (int) $value;
		if ( ! $term_id ) {
			return null;
		}

		if ( $return_format === 'object' ) {
			$term = get_term( $term_id, $taxonomy );
			return ( $term && ! is_wp_error( $term ) ) ? $term : null;
		}

		return $term_id;
	}
}
