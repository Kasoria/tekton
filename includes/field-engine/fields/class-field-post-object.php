<?php
declare(strict_types=1);
/**
 * Post Object field type — single post selector.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Post_Object extends Tekton_Field_Type {

	public function get_type(): string {
		return 'post_object';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$post_types  = $field_config['postTypes'] ?? [ 'post' ];
		$selected_id = (int) $value;

		$posts = get_posts( [
			'post_type'      => $post_types,
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		] );

		$html = '<select id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '" class="tekton-select widefat">';
		$html .= '<option value="">' . esc_html( $field_config['placeholder'] ?? __( '— Select —', 'tekton' ) ) . '</option>';

		foreach ( $posts as $post_item ) {
			$sel   = ( $post_item->ID === $selected_id ) ? ' selected' : '';
			$label = $post_item->post_title ?: __( '(no title)', 'tekton' );
			$html .= '<option value="' . esc_attr( (string) $post_item->ID ) . '"' . $sel . '>'
				. esc_html( $label )
				. '</option>';
		}

		$html .= '</select>';

		return $this->wrap( $field_name, $field_config, $html );
	}

	public function sanitize( mixed $value, array $field_config ): string {
		$id = absint( $value );
		return $id ? (string) $id : '';
	}

	public function format_value( mixed $value, int $post_id, array $field_config ): mixed {
		$return_format = $field_config['returnFormat'] ?? 'id';
		$object_id     = (int) $value;

		if ( ! $object_id ) {
			return null;
		}

		if ( $return_format === 'object' ) {
			return get_post( $object_id );
		}

		return $object_id;
	}
}
