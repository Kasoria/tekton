<?php
declare(strict_types=1);
/**
 * Asset management — frontend and admin CSS/JS loading.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Assets {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend' ] );
		add_action( 'wp_head', [ $this, 'inject_design_tokens' ], 5 );
	}

	public function enqueue_frontend(): void {
		if ( ! $this->is_tekton_rendered_page() ) {
			return;
		}

		wp_enqueue_style(
			'tekton-frontend-reset',
			TEKTON_URL . 'assets/css/tekton-frontend-reset.css',
			[],
			TEKTON_VERSION
		);
	}

	public function inject_design_tokens(): void {
		if ( ! $this->is_tekton_rendered_page() ) {
			return;
		}

		$css = $this->get_design_tokens_css();
		if ( $css ) {
			echo '<style id="tekton-design-tokens">' . "\n:root {\n" . $css . "}\n</style>\n";
		}
	}

	public function get_design_tokens_css(): string {
		$tokens = get_option( 'tekton_design_tokens', '' );
		if ( is_string( $tokens ) ) {
			$tokens = json_decode( $tokens, true );
		}
		if ( ! is_array( $tokens ) ) {
			return '';
		}

		$css = '';

		if ( ! empty( $tokens['colors'] ) ) {
			foreach ( $tokens['colors'] as $name => $value ) {
				$name  = sanitize_key( $name );
				$value = sanitize_text_field( $value );
				$css  .= "  --tekton-{$name}: {$value};\n";
			}
		}

		if ( ! empty( $tokens['typography'] ) ) {
			foreach ( $tokens['typography'] as $name => $value ) {
				$name  = sanitize_key( $name );
				$value = sanitize_text_field( $value );
				$css  .= "  --tekton-{$name}: {$value};\n";
			}
		}

		if ( ! empty( $tokens['spacing'] ) ) {
			foreach ( $tokens['spacing'] as $name => $value ) {
				$name  = sanitize_key( $name );
				$value = sanitize_text_field( $value );
				$css  .= "  --tekton-spacing-{$name}: {$value};\n";
			}
		}

		return $css;
	}

	private function is_tekton_rendered_page(): bool {
		$bridge = Tekton_Core::instance()->get_module( 'theme_bridge' );
		return $bridge instanceof Tekton_Theme_Bridge && null !== $bridge->get_current_template_key();
	}
}
