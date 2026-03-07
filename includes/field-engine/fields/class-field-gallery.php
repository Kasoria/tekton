<?php
declare(strict_types=1);
/**
 * Gallery field type — multiple image selection from media library.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Gallery extends Tekton_Field_Type {

	public function get_type(): string {
		return 'gallery';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$ids          = $this->parse_ids( $value );
		$ids_string   = implode( ',', $ids );
		$preview_size = $field_config['previewSize'] ?? 'thumbnail';

		$html  = '<div class="tekton-gallery-field" data-field="' . esc_attr( $field_name ) . '">';
		$html .= '<input type="hidden" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '"'
			. ' value="' . esc_attr( $ids_string ) . '" />';

		$html .= '<div class="tekton-gallery-preview">';
		foreach ( $ids as $attachment_id ) {
			$thumb_url = wp_get_attachment_image_url( $attachment_id, $preview_size );
			if ( $thumb_url ) {
				$html .= '<div class="tekton-gallery-thumb" data-id="' . $attachment_id . '">'
					. '<img src="' . esc_url( $thumb_url ) . '" alt="" />'
					. '</div>';
			}
		}
		$html .= '</div>';

		$html .= '<button type="button" class="button tekton-gallery-add">'
			. esc_html__( 'Add to Gallery', 'tekton' ) . '</button>';
		$html .= ' <button type="button" class="button tekton-gallery-clear"'
			. ( empty( $ids ) ? ' style="display:none"' : '' ) . '>'
			. esc_html__( 'Clear', 'tekton' ) . '</button>';
		$html .= '</div>';

		return $this->wrap( $field_name, $field_config, $html );
	}

	public function sanitize( mixed $value, array $field_config ): string {
		$ids = $this->parse_ids( $value );
		$valid_ids = [];

		foreach ( $ids as $id ) {
			if ( $id && wp_get_attachment_url( $id ) ) {
				$valid_ids[] = $id;
			}
		}

		return implode( ',', $valid_ids );
	}

	public function format_value( mixed $value, int $post_id, array $field_config ): mixed {
		$return_format = $field_config['returnFormat'] ?? 'ids';
		$ids           = $this->parse_ids( $value );

		if ( empty( $ids ) ) {
			return [];
		}

		if ( $return_format === 'urls' ) {
			$size = $field_config['previewSize'] ?? 'large';
			$urls = [];
			foreach ( $ids as $attachment_id ) {
				$url = wp_get_attachment_image_url( $attachment_id, $size );
				if ( $url ) {
					$urls[] = $url;
				}
			}
			return $urls;
		}

		return $ids;
	}

	public function enqueue_admin_assets(): void {
		wp_enqueue_media();
	}

	/**
	 * Parse attachment IDs from stored value.
	 *
	 * @return int[]
	 */
	private function parse_ids( mixed $value ): array {
		if ( empty( $value ) ) {
			return [];
		}

		if ( is_array( $value ) ) {
			return array_filter( array_map( 'absint', $value ) );
		}

		$str = (string) $value;

		// Try JSON first.
		$decoded = json_decode( $str, true );
		if ( is_array( $decoded ) ) {
			return array_filter( array_map( 'absint', $decoded ) );
		}

		// Comma-separated string.
		return array_filter( array_map( 'absint', explode( ',', $str ) ) );
	}
}
