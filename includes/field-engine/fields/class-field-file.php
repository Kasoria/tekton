<?php
declare(strict_types=1);
/**
 * File field type — file selection from media library.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_File extends Tekton_Field_Type {

	public function get_type(): string {
		return 'file';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$attachment_id = (int) $value;
		$filename      = '';

		if ( $attachment_id ) {
			$filepath = get_attached_file( $attachment_id );
			$filename = $filepath ? basename( $filepath ) : '';
		}

		$html  = '<div class="tekton-file-field" data-field="' . esc_attr( $field_name ) . '">';
		$html .= '<input type="hidden" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '"'
			. ' value="' . esc_attr( (string) $attachment_id ?: '' ) . '" />';

		$html .= '<div class="tekton-file-info"' . ( $filename ? '' : ' style="display:none"' ) . '>';
		if ( $filename ) {
			$html .= '<span class="tekton-file-name">' . esc_html( $filename ) . '</span>';
		}
		$html .= '</div>';

		$html .= '<button type="button" class="button tekton-file-select">'
			. esc_html__( 'Select File', 'tekton' ) . '</button>';
		$html .= ' <button type="button" class="button tekton-file-remove"'
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
		$return_format = $field_config['returnFormat'] ?? 'id';
		$attachment_id = (int) $value;

		if ( ! $attachment_id ) {
			return '';
		}

		if ( $return_format === 'id' ) {
			return $attachment_id;
		}

		$url = (string) wp_get_attachment_url( $attachment_id );

		if ( $return_format === 'url' ) {
			return $url;
		}

		// 'array' format
		$filepath = get_attached_file( $attachment_id );
		return [
			'id'       => $attachment_id,
			'url'      => $url,
			'filename' => $filepath ? basename( $filepath ) : '',
		];
	}

	public function enqueue_admin_assets(): void {
		wp_enqueue_media();
	}
}
