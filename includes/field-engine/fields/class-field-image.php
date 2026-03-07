<?php
declare(strict_types=1);
/**
 * Image field type — uses WordPress media library.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Image extends Tekton_Field_Type {

	public function get_type(): string {
		return 'image';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$attachment_id = (int) $value;
		$preview_size  = $field_config['previewSize'] ?? 'medium';
		$preview_url   = $attachment_id ? wp_get_attachment_image_url( $attachment_id, $preview_size ) : '';

		$html  = '<div class="tekton-image-field" data-field="' . esc_attr( $field_name ) . '">';
		$html .= '<input type="hidden" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '"'
			. ' value="' . esc_attr( (string) $attachment_id ?: '' ) . '" />';

		$html .= '<div class="tekton-image-preview"' . ( $preview_url ? '' : ' style="display:none"' ) . '>';
		if ( $preview_url ) {
			$html .= '<img src="' . esc_url( $preview_url ) . '" alt="" />';
		}
		$html .= '</div>';

		$html .= '<button type="button" class="button tekton-image-select">'
			. esc_html__( 'Select Image', 'tekton' ) . '</button>';
		$html .= ' <button type="button" class="button tekton-image-remove"'
			. ( $attachment_id ? '' : ' style="display:none"' ) . '>'
			. esc_html__( 'Remove', 'tekton' ) . '</button>';
		$html .= '</div>';

		return $this->wrap( $field_name, $field_config, $html );
	}

	public function sanitize( mixed $value, array $field_config ): string {
		$id = absint( $value );
		if ( $id && ! wp_get_attachment_url( $id ) ) {
			return '';
		}
		return $id ? (string) $id : '';
	}

	public function format_value( mixed $value, int $post_id, array $field_config ): mixed {
		$return_format = $field_config['returnFormat'] ?? 'url';
		$attachment_id = (int) $value;

		if ( ! $attachment_id ) {
			return '';
		}

		if ( $return_format === 'id' ) {
			return $attachment_id;
		}

		$size = $field_config['previewSize'] ?? 'large';
		return (string) wp_get_attachment_image_url( $attachment_id, $size );
	}

	public function enqueue_admin_assets(): void {
		wp_enqueue_media();
	}
}
