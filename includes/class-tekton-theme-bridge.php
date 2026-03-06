<?php
declare(strict_types=1);
/**
 * Theme hijacking — intercepts template rendering for Tekton-managed pages.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Theme_Bridge {

	private Tekton_Storage  $storage;
	private Tekton_Renderer $renderer;
	private ?string         $matched_key       = null;
	private ?array          $matched_structure  = null;

	public function __construct( Tekton_Storage $storage, Tekton_Renderer $renderer ) {
		$this->storage  = $storage;
		$this->renderer = $renderer;

		add_filter( 'template_include', [ $this, 'intercept_template' ], 999 );
		add_action( 'wp_head', [ $this, 'output_seo_meta' ], 1 );
	}

	public function intercept_template( string $template ): string {
		if ( ! get_option( 'tekton_override_theme', true ) ) {
			return $template;
		}

		$key = $this->resolve_template_key();
		if ( null === $key ) {
			return $template;
		}

		$structure = $this->storage->get_structure( $key );
		if ( ! $structure || empty( $structure['components'] ) ) {
			return $template;
		}

		$this->matched_key       = $key;
		$this->matched_structure = $structure;

		return TEKTON_DIR . 'includes/template-canvas.php';
	}

	/**
	 * Output SEO meta tags from the structure's meta property.
	 */
	public function output_seo_meta(): void {
		if ( ! $this->matched_structure ) {
			return;
		}

		$meta = $this->matched_structure['meta'] ?? [];
		if ( empty( $meta ) ) {
			return;
		}

		if ( ! empty( $meta['description'] ) ) {
			echo '<meta name="description" content="' . esc_attr( $meta['description'] ) . '">' . "\n";
		}

		// Open Graph tags.
		$og_map = [
			'og_title'       => 'og:title',
			'og_description' => 'og:description',
			'og_image'       => 'og:image',
			'og_type'        => 'og:type',
		];
		foreach ( $og_map as $key => $property ) {
			if ( ! empty( $meta[ $key ] ) ) {
				echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $meta[ $key ] ) . '">' . "\n";
			}
		}

		// Twitter card tags.
		$twitter_map = [
			'twitter_card'  => 'twitter:card',
			'twitter_title' => 'twitter:title',
			'twitter_desc'  => 'twitter:description',
			'twitter_image' => 'twitter:image',
		];
		foreach ( $twitter_map as $key => $name ) {
			if ( ! empty( $meta[ $key ] ) ) {
				echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $meta[ $key ] ) . '">' . "\n";
			}
		}

		// Canonical URL (overrides WP default).
		if ( ! empty( $meta['canonical'] ) ) {
			remove_action( 'wp_head', 'rel_canonical' );
			echo '<link rel="canonical" href="' . esc_url( $meta['canonical'] ) . '">' . "\n";
		}

		// Robots directives.
		if ( ! empty( $meta['robots'] ) ) {
			echo '<meta name="robots" content="' . esc_attr( $meta['robots'] ) . '">' . "\n";
		}
	}

	public function get_current_template_key(): ?string {
		return $this->matched_key;
	}

	public function get_structure_for_current(): ?array {
		return $this->matched_structure;
	}

	/**
	 * Try specific keys first, then generic fallbacks.
	 */
	private function resolve_template_key(): ?string {
		// 1. Check post meta first (any singular page with _tekton_template_key).
		if ( is_singular() ) {
			$post_id = get_the_ID();
			if ( $post_id ) {
				$meta_key = get_post_meta( $post_id, '_tekton_template_key', true );
				if ( $meta_key ) {
					$structure = $this->storage->get_structure( $meta_key );
					if ( $structure && ! empty( $structure['components'] ) ) {
						return $meta_key;
					}
				}
			}
		}

		// 2. Fall back to conditional-based template matching.
		$candidates = $this->get_template_candidates();

		foreach ( $candidates as $key ) {
			$structure = $this->storage->get_structure( $key );
			if ( $structure && ! empty( $structure['components'] ) ) {
				return $key;
			}
		}

		return null;
	}

	/**
	 * Build ordered list of template keys to check.
	 *
	 * @return string[]
	 */
	private function get_template_candidates(): array {
		$candidates = [];

		if ( is_front_page() ) {
			$candidates[] = 'front-page';
		}

		if ( is_home() ) {
			$candidates[] = 'home';
		}

		if ( is_singular() ) {
			$post_id   = get_the_ID();
			$post_type = get_post_type();

			if ( $post_id ) {
				$candidates[] = 'post-' . $post_id;
			}
			if ( $post_type ) {
				$candidates[] = 'single-' . $post_type;
			}
		}

		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			if ( is_array( $post_type ) ) {
				$post_type = reset( $post_type );
			}
			if ( $post_type ) {
				$candidates[] = 'archive-' . $post_type;
			}
		}

		if ( is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$candidates[] = 'taxonomy-' . $term->taxonomy . '-' . $term->slug;
				$candidates[] = 'taxonomy-' . $term->taxonomy;
			}
		}

		if ( is_category() ) {
			$candidates[] = 'category';
		}

		if ( is_tag() ) {
			$candidates[] = 'tag';
		}

		if ( is_author() ) {
			$candidates[] = 'author';
		}

		if ( is_search() ) {
			$candidates[] = 'search';
		}

		if ( is_404() ) {
			$candidates[] = '404';
		}

		if ( is_archive() ) {
			$candidates[] = 'archive';
		}

		return $candidates;
	}
}
