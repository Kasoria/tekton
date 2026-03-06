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

		$this->enqueue_google_fonts();
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

		$prefixes = [
			'colors'     => '--tekton-',
			'fonts'      => '--tekton-font-',
			'typography' => '--tekton-',
			'spacing'    => '--tekton-spacing-',
			'radii'      => '--tekton-radius-',
			'shadows'    => '--tekton-shadow-',
		];

		foreach ( $prefixes as $category => $prefix ) {
			if ( empty( $tokens[ $category ] ) ) {
				continue;
			}
			foreach ( $tokens[ $category ] as $name => $value ) {
				$name  = sanitize_key( $name );
				$value = sanitize_text_field( $value );
				$css  .= "  {$prefix}{$name}: {$value};\n";
			}
		}

		return $css;
	}

	public function get_google_fonts_url(): string {
		$theme = get_option( 'tekton_theme', '' );
		if ( is_string( $theme ) ) {
			$theme = json_decode( $theme, true );
		}
		if ( ! is_array( $theme ) || empty( $theme['fonts'] ) ) {
			return '';
		}

		$families = [];
		foreach ( [ 'heading', 'body' ] as $key ) {
			if ( ! empty( $theme['fonts'][ $key ] ) ) {
				$family = sanitize_text_field( $theme['fonts'][ $key ] );
				if ( preg_match( '/^(system-ui|inherit|sans-serif|serif|monospace|cursive|fantasy)/i', $family ) ) {
					continue;
				}
				$families[] = str_replace( ' ', '+', $family ) . ':wght@300;400;500;600;700;800;900';
			}
		}

		if ( empty( $families ) ) {
			return '';
		}

		$families = array_unique( $families );
		return 'https://fonts.googleapis.com/css2?' . implode( '&', array_map( fn( $f ) => 'family=' . $f, $families ) ) . '&display=swap';
	}

	private function enqueue_google_fonts(): void {
		$url = $this->get_google_fonts_url();
		if ( $url ) {
			wp_enqueue_style( 'tekton-google-fonts', esc_url( $url ), [], null );
		}
	}

	private function is_tekton_rendered_page(): bool {
		$bridge = Tekton_Core::instance()->get_module( 'theme_bridge' );
		return $bridge instanceof Tekton_Theme_Bridge && null !== $bridge->get_current_template_key();
	}
}
