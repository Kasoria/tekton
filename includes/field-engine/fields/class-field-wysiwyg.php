<?php
declare(strict_types=1);
/**
 * WYSIWYG (rich text editor) field type.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Wysiwyg extends Tekton_Field_Type {

	public function get_type(): string {
		return 'wysiwyg';
	}

	public function render( array $field_config, mixed $value, string $field_name ): string {
		$media_buttons = $field_config['mediaButtons'] ?? true;
		$rows          = (int) ( $field_config['rows'] ?? 10 );
		$teeny         = ! empty( $field_config['teeny'] );

		$settings = [
			'textarea_name' => $field_name,
			'media_buttons' => (bool) $media_buttons,
			'textarea_rows' => $rows,
			'teeny'         => $teeny,
			'quicktags'     => true,
		];

		ob_start();
		wp_editor( (string) $value, esc_attr( $field_name ), $settings );
		$editor_html = ob_get_clean();

		return $this->wrap( $field_name, $field_config, $editor_html );
	}

	public function sanitize( mixed $value, array $field_config ): string {
		return wp_kses_post( (string) $value );
	}
}
