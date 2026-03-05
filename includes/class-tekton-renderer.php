<?php
declare(strict_types=1);
/**
 * Component JSON to HTML renderer.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Renderer {

	/** @var string[] Collected styles from all components. */
	private array $collected_styles = [];

	public function render_page( array $structure, int $post_id = 0 ): string {
		$this->collected_styles = [];

		$components = $structure['components'] ?? [];
		$html       = '';

		foreach ( $components as $component ) {
			$html .= $this->render_component( $component, $post_id );
		}

		$template_key = esc_attr( $structure['template_key'] ?? '' );
		$styles_block = '';
		if ( ! empty( $this->collected_styles ) ) {
			$styles_block = '<style class="tekton-scoped">' . "\n" . implode( "\n", $this->collected_styles ) . "\n</style>\n";
		}

		return $styles_block
			. '<div class="tekton-page" data-template="' . $template_key . '">'
			. "\n" . $html
			. "\n</div>";
	}

	public function render_component( array $component, int $post_id = 0 ): string {
		if ( empty( $component['type'] ) || empty( $component['id'] ) ) {
			return '';
		}

		if ( ! empty( $component['styles'] ) ) {
			$this->collected_styles[] = $this->render_styles( $component['styles'], $component['id'] );
		}

		$type = $component['type'];

		return match ( $type ) {
			'section'     => $this->render_section( $component, $post_id ),
			'container'   => $this->render_container( $component, $post_id ),
			'div'         => $this->render_div( $component, $post_id ),
			'heading'     => $this->render_heading( $component, $post_id ),
			'text'        => $this->render_text( $component, $post_id ),
			'image'       => $this->render_image( $component, $post_id ),
			'button'      => $this->render_button( $component, $post_id ),
			'grid'        => $this->render_grid( $component, $post_id ),
			'flex-row'    => $this->render_flex( $component, $post_id, 'row' ),
			'flex-column' => $this->render_flex( $component, $post_id, 'column' ),
			'link'        => $this->render_link( $component, $post_id ),
			'list'        => $this->render_list( $component, $post_id ),
			'spacer'      => $this->render_spacer( $component ),
			'divider'     => $this->render_divider( $component ),
			'video'       => $this->render_video( $component, $post_id ),
			'icon'        => $this->render_icon( $component ),
			default       => '',
		};
	}

	private function render_section( array $c, int $post_id ): string {
		$attrs    = $this->build_attributes( $c, 'tekton-section' );
		$children = $this->render_children( $c, $post_id );
		return "<section {$attrs}>\n{$children}</section>\n";
	}

	private function render_container( array $c, int $post_id ): string {
		// If the container has absolute/fixed positioning, use plain div (no max-width constraint).
		$position = $c['styles']['desktop']['position'] ?? '';
		$class    = in_array( $position, [ 'absolute', 'fixed' ], true ) ? 'tekton-div' : 'tekton-container';
		$attrs    = $this->build_attributes( $c, $class );
		$children = $this->render_children( $c, $post_id );
		return "<div {$attrs}>\n{$children}</div>\n";
	}

	private function render_div( array $c, int $post_id ): string {
		$attrs    = $this->build_attributes( $c, 'tekton-div' );
		$children = $this->render_children( $c, $post_id );
		return "<div {$attrs}>\n{$children}</div>\n";
	}

	private function render_heading( array $c, int $post_id ): string {
		$level   = (int) ( $c['props']['level'] ?? 2 );
		$level   = max( 1, min( 6, $level ) );
		$tag     = 'h' . $level;
		$attrs   = $this->build_attributes( $c, 'tekton-heading' );
		$content = $this->resolve_prop_content( $c, 'content', $post_id );

		return "<{$tag} {$attrs}>" . esc_html( $content ) . "</{$tag}>\n";
	}

	private function render_text( array $c, int $post_id ): string {
		$tag     = esc_attr( $c['props']['tagName'] ?? 'p' );
		$attrs   = $this->build_attributes( $c, 'tekton-text' );
		$content = $this->resolve_prop_content( $c, 'content', $post_id );

		return "<{$tag} {$attrs}>" . wp_kses_post( $content ) . "</{$tag}>\n";
	}

	private function render_image( array $c, int $post_id ): string {
		$src     = $this->resolve_prop_content( $c, 'src', $post_id );
		$alt     = $this->resolve_prop_content( $c, 'alt', $post_id );
		$caption = $this->resolve_prop_content( $c, 'caption', $post_id );
		$attrs   = $this->build_attributes( $c, 'tekton-image' );

		$img = '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy">';

		if ( $caption ) {
			return "<figure {$attrs}>{$img}<figcaption>" . esc_html( $caption ) . "</figcaption></figure>\n";
		}

		return "<div {$attrs}>{$img}</div>\n";
	}

	private function render_button( array $c, int $post_id ): string {
		$text   = $this->resolve_prop_content( $c, 'text', $post_id );
		$href   = $this->resolve_prop_content( $c, 'href', $post_id );
		$target = esc_attr( $c['props']['target'] ?? '_self' );
		$attrs  = $this->build_attributes( $c, 'tekton-button' );

		return '<a ' . $attrs . ' href="' . esc_url( $href ) . '" target="' . $target . '">'
			. esc_html( $text ) . "</a>\n";
	}

	private function render_grid( array $c, int $post_id ): string {
		$attrs    = $this->build_attributes( $c, 'tekton-grid' );
		$children = $this->render_children( $c, $post_id );
		return "<div {$attrs}>\n{$children}</div>\n";
	}

	private function render_flex( array $c, int $post_id, string $direction ): string {
		$class   = 'row' === $direction ? 'tekton-flex-row' : 'tekton-flex-column';
		$attrs    = $this->build_attributes( $c, $class );
		$children = $this->render_children( $c, $post_id );
		return "<div {$attrs}>\n{$children}</div>\n";
	}

	private function render_link( array $c, int $post_id ): string {
		$text   = $this->resolve_prop_content( $c, 'text', $post_id );
		$href   = $this->resolve_prop_content( $c, 'href', $post_id );
		$target = esc_attr( $c['props']['target'] ?? '_self' );
		$attrs  = $this->build_attributes( $c, 'tekton-link' );

		return '<a ' . $attrs . ' href="' . esc_url( $href ) . '" target="' . $target . '">'
			. esc_html( $text ) . "</a>\n";
	}

	private function render_list( array $c, int $post_id ): string {
		$ordered = ! empty( $c['props']['ordered'] );
		$tag     = $ordered ? 'ol' : 'ul';
		$attrs   = $this->build_attributes( $c, 'tekton-list' );
		$items   = $c['props']['items'] ?? [];

		$html = "<{$tag} {$attrs}>\n";
		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item['source'] ) ) {
				$html .= '<li>' . esc_html( $this->resolve_content_source( $item, $post_id ) ) . "</li>\n";
			} else {
				$html .= '<li>' . esc_html( (string) $item ) . "</li>\n";
			}
		}
		$html .= "</{$tag}>\n";

		return $html;
	}

	private function render_spacer( array $c ): string {
		$height = esc_attr( $c['props']['height'] ?? '2rem' );
		$id     = esc_attr( $c['id'] );
		return '<div class="tekton-spacer" id="' . $id . '" style="height:' . $height . '" aria-hidden="true"></div>' . "\n";
	}

	private function render_divider( array $c ): string {
		$attrs = $this->build_attributes( $c, 'tekton-divider' );
		return "<hr {$attrs}>\n";
	}

	private function render_video( array $c, int $post_id ): string {
		$src   = $this->resolve_prop_content( $c, 'src', $post_id );
		$attrs = $this->build_attributes( $c, 'tekton-video' );
		$type  = $c['props']['type'] ?? 'embed';

		if ( 'embed' === $type ) {
			return '<div ' . $attrs . '><iframe src="' . esc_url( $src ) . '" frameborder="0" allowfullscreen loading="lazy" style="width:100%;aspect-ratio:16/9"></iframe></div>' . "\n";
		}

		return '<div ' . $attrs . '><video src="' . esc_url( $src ) . '" controls preload="metadata" style="width:100%"></video></div>' . "\n";
	}

	private function render_icon( array $c ): string {
		$name = esc_attr( $c['props']['name'] ?? '' );
		$size = esc_attr( $c['props']['size'] ?? '24px' );
		$attrs = $this->build_attributes( $c, 'tekton-icon' );

		return '<span ' . $attrs . ' style="font-size:' . $size . '" aria-hidden="true">' . esc_html( $name ) . '</span>' . "\n";
	}

	private function resolve_prop_content( array $c, string $prop, int $post_id ): string {
		$value = $c['props'][ $prop ] ?? null;

		if ( null === $value ) {
			return '';
		}

		if ( is_array( $value ) && isset( $value['source'] ) ) {
			return $this->resolve_content_source( $value, $post_id );
		}

		return (string) $value;
	}

	private function resolve_content_source( array $source, int $post_id ): string {
		$fallback = (string) ( $source['fallback'] ?? '' );

		return match ( $source['source'] ) {
			'static'  => (string) ( $source['value'] ?? '' ),
			'post'    => $this->resolve_post_source( $source, $post_id ) ?: $fallback,
			'option'  => (string) ( get_option( $source['key'] ?? '', $fallback ) ),
			'field'   => $fallback, // Phase 2: Field Engine
			'acf'     => $fallback, // Phase 2: ACF compat
			'menu'    => $fallback, // Phase 2
			'computed'=> $fallback, // Phase 2
			default   => $fallback,
		};
	}

	private function resolve_post_source( array $source, int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}

		$field = $source['field'] ?? '';

		return match ( $field ) {
			'post_title'     => get_the_title( $post_id ),
			'post_content'   => (string) get_post_field( 'post_content', $post_id ),
			'post_excerpt'   => (string) get_the_excerpt( $post_id ),
			'post_date'      => (string) get_the_date( '', $post_id ),
			'featured_image' => (string) get_the_post_thumbnail_url( $post_id, $source['size'] ?? 'large' ),
			default          => '',
		};
	}

	private function render_styles( array $styles, string $component_id ): string {
		$css = '';
		$id  = esc_attr( $component_id );

		foreach ( [ 'desktop', 'tablet', 'mobile' ] as $breakpoint ) {
			if ( empty( $styles[ $breakpoint ] ) ) {
				continue;
			}

			$rules = '';
			foreach ( $styles[ $breakpoint ] as $prop => $value ) {
				$prop  = $this->camel_to_kebab( $prop );
				$rules .= "  {$prop}: {$value};\n";
			}

			if ( '' === $rules ) {
				continue;
			}

			$selector = "#{$id}";

			if ( 'desktop' === $breakpoint ) {
				$css .= "{$selector} {\n{$rules}}\n";
			} elseif ( 'tablet' === $breakpoint ) {
				$css .= "@media (max-width: 1024px) {\n  {$selector} {\n  {$rules}  }\n}\n";
			} elseif ( 'mobile' === $breakpoint ) {
				$css .= "@media (max-width: 767px) {\n  {$selector} {\n  {$rules}  }\n}\n";
			}
		}

		return $css;
	}

	private function render_children( array $component, int $post_id ): string {
		if ( empty( $component['children'] ) ) {
			return '';
		}

		$html = '';
		foreach ( $component['children'] as $child ) {
			$html .= $this->render_component( $child, $post_id );
		}
		return $html;
	}

	private function build_attributes( array $component, string $base_class = '' ): string {
		$id        = esc_attr( $component['id'] ?? '' );
		$extra_cls = esc_attr( $component['props']['className'] ?? '' );
		$classes   = trim( $base_class . ( $extra_cls ? ' ' . $extra_cls : '' ) );

		$attrs = 'id="' . $id . '"';
		if ( $classes ) {
			$attrs .= ' class="' . esc_attr( $classes ) . '"';
		}

		$attrs .= ' data-component-type="' . esc_attr( $component['type'] ?? '' ) . '"';

		return $attrs;
	}

	private function camel_to_kebab( string $str ): string {
		return strtolower( (string) preg_replace( '/([a-z])([A-Z])/', '$1-$2', $str ) );
	}
}
