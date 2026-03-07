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

	private const TEXT_TAGS  = [ 'p', 'div', 'span', 'blockquote', 'pre', 'address', 'figcaption', 'li', 'dd', 'dt' ];
	private const TITLE_TAGS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span' ];
	private const FIELD_TAGS = [ 'span', 'div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'pre', 'code', 'em', 'strong', 'small', 'time' ];

	/** @var string[] Collected styles from all components. */
	private array $collected_styles = [];
	/** @var array<string, \WP_Post[]> Cached CPT post queries per request. */
	private array $cpt_post_cache = [];
	private Tekton_Security $security;

	public function __construct( Tekton_Security $security ) {
		$this->security = $security;
	}

	public function render_page( array $structure, int $post_id = 0, string $wrapper_tag = 'div' ): string {
		$this->collected_styles = [];

		$components = $structure['components'] ?? [];
		$html       = '';

		foreach ( $components as $component ) {
			$html .= $this->render_component( $component, $post_id );
		}

		$template_key  = esc_attr( $structure['template_key'] ?? '' );
		$keyframes_css = $this->render_keyframes( $structure['keyframes'] ?? [] );

		// Wrapper-level styles (e.g. position: sticky on <header>).
		$wrapper_styles = $structure['wrapper_styles'] ?? [];
		$wrapper_css    = '';
		$wrapper_id     = '';
		if ( ! empty( $wrapper_styles ) ) {
			$wrapper_id  = 'tekton-' . $template_key;
			$wrapper_css = $this->render_styles( $wrapper_styles, $wrapper_id );
		}

		$all_css = $keyframes_css . $wrapper_css . implode( "\n", $this->collected_styles );
		$styles_block = '';
		if ( '' !== $all_css ) {
			$styles_block = '<style class="tekton-scoped">' . "\n" . $all_css . "\n</style>\n";
		}

		$scripts_block = '';
		$scripts = $structure['scripts'] ?? [];
		if ( ! empty( $scripts ) && is_array( $scripts ) ) {
			$validated = [];
			foreach ( $scripts as $script ) {
				$script_str = (string) $script;
				$result     = $this->security->validate_generated_code( $script_str );
				if ( $result['valid'] ) {
					$validated[] = $script_str;
				}
			}
			if ( ! empty( $validated ) ) {
				$scripts_js     = implode( "\n", $validated );
				$scripts_block  = "\n" . '<script class="tekton-scripts">' . "\n"
					. '(function(){' . "\n"
					. 'function _init(){' . "\n" . $scripts_js . "\n" . '}' . "\n"
					. 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",_init);}else{_init();}' . "\n"
					. '})();'
					. "\n</script>\n";
			}
		}

		$tag      = in_array( $wrapper_tag, [ 'header', 'main', 'footer', 'div' ], true ) ? $wrapper_tag : 'div';
		$id_attr  = $wrapper_id ? ' id="' . esc_attr( $wrapper_id ) . '"' : '';

		return $styles_block
			. '<' . $tag . $id_attr . ' class="tekton-page" data-template="' . $template_key . '">'
			. "\n" . $html
			. "\n</" . $tag . '>'
			. $scripts_block;
	}

	public function render_component( array $component, int $post_id = 0 ): string {
		if ( empty( $component['type'] ) || empty( $component['id'] ) ) {
			return '';
		}

		if ( ! empty( $component['styles'] ) ) {
			$html_id = $component['props']['id'] ?? $component['id'];
			$this->collected_styles[] = $this->render_styles( $component['styles'], $html_id );
		}

		$type = $component['type'];

		return match ( $type ) {
			'section'        => $this->render_section( $component, $post_id ),
			'container'      => $this->render_container( $component, $post_id ),
			'div'            => $this->render_div( $component, $post_id ),
			'heading'        => $this->render_heading( $component, $post_id ),
			'text'           => $this->render_text( $component, $post_id ),
			'image'          => $this->render_image( $component, $post_id ),
			'button'         => $this->render_button( $component, $post_id ),
			'grid'           => $this->render_grid( $component, $post_id ),
			'flex-row'       => $this->render_flex( $component, $post_id, 'row' ),
			'flex-column'    => $this->render_flex( $component, $post_id, 'column' ),
			'link'           => $this->render_link( $component, $post_id ),
			'list'           => $this->render_list( $component, $post_id ),
			'spacer'         => $this->render_spacer( $component ),
			'divider'        => $this->render_divider( $component ),
			'video'          => $this->render_video( $component, $post_id ),
			'icon'           => $this->render_icon( $component ),
			'post-loop'      => $this->render_post_loop( $component, $post_id ),
			'post-title'     => $this->render_post_title( $component, $post_id ),
			'post-content'   => $this->render_post_content( $component, $post_id ),
			'post-meta'      => $this->render_post_meta( $component, $post_id ),
			'featured-image' => $this->render_featured_image( $component, $post_id ),
			'menu'           => $this->render_menu_component( $component ),
			'tekton-field'   => $this->render_tekton_field( $component, $post_id ),
			'search-form'    => $this->render_search_form( $component ),
			default          => '',
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
		$raw     = $c['props']['level'] ?? 2;
		$level   = (int) preg_replace( '/[^0-9]/', '', (string) $raw );
		$level   = max( 1, min( 6, $level ?: 2 ) );
		$tag     = 'h' . $level;
		$attrs   = $this->build_attributes( $c, 'tekton-heading' );
		$edit    = $this->build_editable_attrs( $c, 'content' );
		$content = $this->resolve_prop_content( $c, 'content', $post_id );

		return "<{$tag} {$attrs}{$edit}>" . esc_html( $content ) . "</{$tag}>\n";
	}

	private function render_text( array $c, int $post_id ): string {
		$tag     = $this->validate_tag_name( $c['props']['tagName'] ?? 'p', self::TEXT_TAGS, 'p' );
		$attrs   = $this->build_attributes( $c, 'tekton-text' );
		$edit    = $this->build_editable_attrs( $c, 'content' );
		$content = $this->resolve_prop_content( $c, 'content', $post_id );

		return "<{$tag} {$attrs}{$edit}>" . wp_kses_post( $content ) . "</{$tag}>\n";
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
		$href   = $this->resolve_prop_content( $c, 'href', $post_id );
		$target = esc_attr( $c['props']['target'] ?? '_self' );
		$rel    = $this->build_link_rel( $c['props'] ?? [] );
		$attrs  = $this->build_attributes( $c, 'tekton-button' );
		$edit   = empty( $c['children'] ) ? $this->build_editable_attrs( $c, 'text' ) : '';

		// If the button has children (e.g. icon + label), render them instead of text prop.
		if ( ! empty( $c['children'] ) ) {
			$inner = $this->render_children( $c, $post_id );
		} else {
			$inner = esc_html( $this->resolve_prop_content( $c, 'text', $post_id ) );
		}

		// Render as <a> when there's a real href, otherwise as <button>.
		if ( '' !== $href ) {
			return '<a ' . $attrs . $edit . ' href="' . esc_url( $href ) . '" target="' . $target . '"' . $rel . '>'
				. $inner . "</a>\n";
		}

		$btn_type = esc_attr( $c['props']['type'] ?? 'button' );
		return '<button ' . $attrs . $edit . ' type="' . $btn_type . '">'
			. $inner . "</button>\n";
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
		$href   = $this->resolve_prop_content( $c, 'href', $post_id );
		$target = esc_attr( $c['props']['target'] ?? '_self' );
		$rel    = $this->build_link_rel( $c['props'] ?? [] );
		$attrs  = $this->build_attributes( $c, 'tekton-link' );
		$edit   = empty( $c['children'] ) ? $this->build_editable_attrs( $c, 'text' ) : '';

		// If the link has children (e.g. logo with icon + text), render them instead of text prop.
		if ( ! empty( $c['children'] ) ) {
			$inner = $this->render_children( $c, $post_id );
		} else {
			$inner = esc_html( $this->resolve_prop_content( $c, 'text', $post_id ) );
		}

		return '<a ' . $attrs . $edit . ' href="' . esc_url( $href ) . '" target="' . $target . '"' . $rel . '>'
			. $inner . "</a>\n";
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
			'static'   => (string) ( $source['value'] ?? '' ),
			'post'     => $this->resolve_post_source( $source, $post_id ) ?: $fallback,
			'option'   => (string) ( get_option( $source['key'] ?? '', $fallback ) ),
			'field'    => $this->resolve_field_source( $source, $post_id ) ?: $fallback,
			'acf'      => $this->resolve_acf_source( $source, $post_id ) ?: $fallback,
			'menu'     => $this->resolve_menu_source( $source ) ?: $fallback,
			'computed' => $this->resolve_computed_source( $source, $post_id ) ?: $fallback,
			default    => $fallback,
		};
	}

	private function resolve_post_source( array $source, int $post_id ): string {
		// Query a specific CPT by index when post_type is specified.
		if ( ! empty( $source['post_type'] ) ) {
			return $this->resolve_cpt_post_source( $source );
		}

		if ( $post_id <= 0 ) {
			return '';
		}

		return $this->resolve_post_field( $source['field'] ?? '', $post_id, $source );
	}

	/**
	 * Resolve a post field (core or custom meta) for a known post ID.
	 */
	private function resolve_post_field( string $field, int $post_id, array $source ): string {
		return match ( $field ) {
			'post_title'     => get_the_title( $post_id ),
			'post_content'   => (string) get_post_field( 'post_content', $post_id ),
			'post_excerpt'   => (string) get_the_excerpt( $post_id ),
			'post_date'      => (string) get_the_date( '', $post_id ),
			'featured_image' => (string) get_the_post_thumbnail_url( $post_id, $source['size'] ?? 'large' ),
			default          => $this->resolve_post_meta_field( $field, $post_id, $source ),
		};
	}

	/**
	 * Query a CPT by post_type + post_index and resolve a field from the matching post.
	 */
	private function resolve_cpt_post_source( array $source ): string {
		$post_type  = sanitize_key( $source['post_type'] );
		$post_index = max( 0, (int) ( $source['post_index'] ?? 0 ) );

		if ( ! isset( $this->cpt_post_cache[ $post_type ] ) ) {
			$this->cpt_post_cache[ $post_type ] = get_posts( [
				'post_type'      => $post_type,
				'posts_per_page' => 20,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			] );
		}

		$post = $this->cpt_post_cache[ $post_type ][ $post_index ] ?? null;
		if ( ! $post ) {
			return '';
		}

		return $this->resolve_post_field( $source['field'] ?? '', $post->ID, $source );
	}

	/**
	 * Resolve a custom meta field. Tries Tekton field engine, then plain post meta.
	 */
	private function resolve_post_meta_field( string $field, int $post_id, array $source ): string {
		if ( '' === $field ) {
			return '';
		}

		// Explicit group provided — direct lookup.
		if ( ! empty( $source['group'] ) ) {
			$value = tekton_get_field( $field, $post_id, $source['group'] );
			return is_scalar( $value ) ? (string) $value : '';
		}

		// Auto-detect: search Tekton field groups for this field name.
		$storage = Tekton_Core::instance()->get_module( 'storage' );
		$groups  = $storage->list_field_groups();

		foreach ( $groups as $group ) {
			foreach ( $group['fields'] as $f ) {
				if ( ( $f['name'] ?? '' ) === $field ) {
					$value = tekton_get_field( $field, $post_id, $group['slug'] );
					if ( is_scalar( $value ) && '' !== (string) $value ) {
						return (string) $value;
					}
				}
			}
		}

		// Last resort: plain post meta.
		$value = get_post_meta( $post_id, $field, true );
		return is_scalar( $value ) ? (string) $value : '';
	}

	// ─── Content source resolvers ─────────────────────────────────────

	private function resolve_field_source( array $source, int $post_id ): string {
		$group = $source['group'] ?? '';
		$field = $source['field'] ?? '';
		if ( ! $group || ! $field || $post_id <= 0 ) {
			return '';
		}
		$value = tekton_get_field( $field, $post_id, $group );
		return is_scalar( $value ) ? (string) $value : '';
	}

	private function resolve_acf_source( array $source, int $post_id ): string {
		$field = $source['field'] ?? '';
		if ( ! $field ) {
			return '';
		}
		$value = Tekton_ACF_Compat::get_field( $field, $post_id );
		return is_scalar( $value ) ? (string) $value : '';
	}

	private function resolve_menu_source( array $source ): string {
		$location = $source['location'] ?? '';
		if ( ! $location ) {
			return '';
		}
		return (string) wp_nav_menu( [
			'theme_location' => $location,
			'echo'           => false,
			'fallback_cb'    => '__return_empty_string',
		] );
	}

	private function resolve_computed_source( array $source, int $post_id ): string {
		$expr = $source['expression'] ?? '';
		$args = $source['args'] ?? [];

		return match ( $expr ) {
			'current_year' => (string) gmdate( 'Y' ),
			'site_name'    => get_bloginfo( 'name' ),
			'site_url'     => home_url(),
			'post_count'   => (string) wp_count_posts( $args['post_type'] ?? 'post' )->publish,
			default        => '',
		};
	}

	// ─── WordPress components ─────────────────────────────────────────

	private function render_post_loop( array $c, int $post_id ): string {
		$query_args = $c['props']['query'] ?? [];
		$sanitized  = $this->sanitize_query_args( $query_args );
		$query      = new \WP_Query( $sanitized );
		$attrs      = $this->build_attributes( $c, 'tekton-post-loop' );

		$html = "<div {$attrs}>\n";
		while ( $query->have_posts() ) {
			$query->the_post();
			$html .= $this->render_children( $c, get_the_ID() );
		}
		wp_reset_postdata();
		$html .= "</div>\n";

		return $html;
	}

	private function render_post_title( array $c, int $post_id ): string {
		$tag   = $this->validate_tag_name( $c['props']['tagName'] ?? 'h2', self::TITLE_TAGS, 'h2' );
		$attrs = $this->build_attributes( $c, 'tekton-post-title' );
		$link  = ! empty( $c['props']['link'] );
		$title = esc_html( get_the_title( $post_id ) );

		if ( $link ) {
			$url   = esc_url( (string) get_permalink( $post_id ) );
			$title = '<a href="' . $url . '">' . $title . '</a>';
		}

		return "<{$tag} {$attrs}>{$title}</{$tag}>\n";
	}

	private function render_post_content( array $c, int $post_id ): string {
		$attrs   = $this->build_attributes( $c, 'tekton-post-content' );
		$content = apply_filters( 'the_content', (string) get_post_field( 'post_content', $post_id ) );
		return "<div {$attrs}>" . wp_kses_post( $content ) . "</div>\n";
	}

	private function render_post_meta( array $c, int $post_id ): string {
		$attrs = $this->build_attributes( $c, 'tekton-post-meta' );
		$items = [];

		if ( ! empty( $c['props']['showDate'] ) ) {
			$items[] = '<span class="tekton-post-date">' . esc_html( (string) get_the_date( '', $post_id ) ) . '</span>';
		}
		if ( ! empty( $c['props']['showAuthor'] ) ) {
			$author_id = (int) get_post_field( 'post_author', $post_id );
			$items[]   = '<span class="tekton-post-author">' . esc_html( get_the_author_meta( 'display_name', $author_id ) ) . '</span>';
		}
		if ( ! empty( $c['props']['showCategories'] ) ) {
			$cats = get_the_category_list( ', ', '', $post_id );
			if ( $cats ) {
				$items[] = '<span class="tekton-post-cats">' . wp_kses_post( $cats ) . '</span>';
			}
		}

		return "<div {$attrs}>" . implode( ' ', $items ) . "</div>\n";
	}

	private function render_featured_image( array $c, int $post_id ): string {
		$size  = $c['props']['size'] ?? 'large';
		$attrs = $this->build_attributes( $c, 'tekton-featured-image' );
		$link  = ! empty( $c['props']['link'] );

		$img = get_the_post_thumbnail( $post_id, $size, [ 'loading' => 'lazy' ] );
		if ( ! $img ) {
			return '';
		}

		if ( $link ) {
			$url = esc_url( (string) get_permalink( $post_id ) );
			$img = '<a href="' . $url . '">' . $img . '</a>';
		}

		return "<div {$attrs}>{$img}</div>\n";
	}

	private function render_menu_component( array $c ): string {
		$location = $c['props']['location'] ?? 'primary';
		$attrs    = $this->build_attributes( $c, 'tekton-menu' );

		$menu = wp_nav_menu( [
			'theme_location' => $location,
			'echo'           => false,
			'fallback_cb'    => '__return_empty_string',
			'container'      => false,
		] );

		return "<nav {$attrs}>{$menu}</nav>\n";
	}

	private function render_tekton_field( array $c, int $post_id ): string {
		$group = $c['props']['group'] ?? '';
		$field = $c['props']['field'] ?? '';
		$tag   = $this->validate_tag_name( $c['props']['tagName'] ?? 'span', self::FIELD_TAGS, 'span' );
		$attrs = $this->build_attributes( $c, 'tekton-field' );

		$value = tekton_get_field( $field, $post_id, $group );
		if ( is_array( $value ) ) {
			$value = wp_json_encode( $value );
		}

		return "<{$tag} {$attrs}>" . esc_html( (string) $value ) . "</{$tag}>\n";
	}

	private function render_search_form( array $c ): string {
		$attrs = $this->build_attributes( $c, 'tekton-search-form' );
		$form  = get_search_form( [ 'echo' => false ] );
		return "<div {$attrs}>{$form}</div>\n";
	}

	/**
	 * Whitelist WP_Query args to prevent injection.
	 */
	private function sanitize_query_args( array $args ): array {
		$allowed = [
			'post_type', 'posts_per_page', 'orderby', 'order', 'post_status',
			'tax_query', 'meta_query', 'paged', 'offset', 'category_name',
			'tag', 'author', 's',
		];

		$sanitized = [];
		foreach ( $allowed as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$sanitized[ $key ] = $args[ $key ];
			}
		}

		// Force published posts only
		$sanitized['post_status'] = 'publish';

		// Sensible default
		if ( empty( $sanitized['posts_per_page'] ) ) {
			$sanitized['posts_per_page'] = 10;
		}

		return $sanitized;
	}

	// ─── Styles ───────────────────────────────────────────────────────

	private function render_styles( array $styles, string $component_id ): string {
		$css = '';
		$id  = esc_attr( $component_id );

		foreach ( [ 'desktop', 'tablet', 'mobile' ] as $breakpoint ) {
			if ( empty( $styles[ $breakpoint ] ) ) {
				continue;
			}

			$rules = '';
			foreach ( $styles[ $breakpoint ] as $prop => $value ) {
				$prop = $this->camel_to_kebab( $prop );
				$prop = $this->security->sanitize_css_property( $prop );
				if ( null === $prop ) {
					continue;
				}
				$value = $this->security->sanitize_css_value( (string) $value );
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
		$id        = esc_attr( $component['props']['id'] ?? $component['id'] ?? '' );
		$extra_cls = esc_attr( $component['props']['className'] ?? '' );
		$classes   = trim( $base_class . ( $extra_cls ? ' ' . $extra_cls : '' ) );

		$attrs = 'id="' . $id . '"';
		if ( $classes ) {
			$attrs .= ' class="' . esc_attr( $classes ) . '"';
		}

		$attrs .= ' data-component-type="' . esc_attr( $component['type'] ?? '' ) . '"';

		// Output aria-*, role, and data-* attributes from props.
		$props = $component['props'] ?? [];
		if ( ! empty( $props['role'] ) ) {
			$attrs .= ' role="' . esc_attr( $props['role'] ) . '"';
		}
		foreach ( $props as $key => $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}
			if ( str_starts_with( $key, 'aria-' ) || str_starts_with( $key, 'data-' ) ) {
				$safe_key = preg_replace( '/[^a-z0-9-]/', '', strtolower( $key ) );
				$attrs .= ' ' . $safe_key . '="' . esc_attr( $value ) . '"';
			}
		}

		return $attrs;
	}

	/**
	 * Build the rel attribute for links/buttons.
	 * Auto-adds noopener noreferrer for target="_blank". Supports explicit rel prop.
	 */
	private function build_link_rel( array $props ): string {
		if ( empty( $props['rel'] ) ) {
			return '';
		}

		$allowed = [ 'nofollow', 'noopener', 'noreferrer', 'sponsored', 'ugc', 'external' ];
		$explicit = is_array( $props['rel'] ) ? $props['rel'] : explode( ' ', (string) $props['rel'] );
		$rels     = [];

		foreach ( $explicit as $r ) {
			$r = strtolower( trim( $r ) );
			if ( in_array( $r, $allowed, true ) && ! in_array( $r, $rels, true ) ) {
				$rels[] = $r;
			}
		}

		if ( empty( $rels ) ) {
			return '';
		}

		return ' rel="' . esc_attr( implode( ' ', $rels ) ) . '"';
	}

	private function render_keyframes( array $keyframes ): string {
		$css = '';
		foreach ( $keyframes as $name => $steps ) {
			if ( ! preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]*$/', $name ) ) {
				continue;
			}
			$css .= '@keyframes ' . $name . " {\n";
			foreach ( $steps as $stop => $props ) {
				$stop = (string) $stop;
				if ( ! preg_match( '/^(\d{1,3}%|from|to)$/', $stop ) ) {
					continue;
				}
				$css .= "  {$stop} {\n";
				foreach ( $props as $prop => $value ) {
					$prop = $this->camel_to_kebab( $prop );
					$prop = $this->security->sanitize_css_property( $prop );
					if ( null === $prop ) {
						continue;
					}
					$value = $this->security->sanitize_css_value( (string) $value );
					$css .= "    {$prop}: {$value};\n";
				}
				$css .= "  }\n";
			}
			$css .= "}\n";
		}
		return $css;
	}

	private function validate_tag_name( string $tag, array $allowed, string $default ): string {
		return in_array( $tag, $allowed, true ) ? $tag : $default;
	}

	private function camel_to_kebab( string $str ): string {
		$kebab = strtolower( (string) preg_replace( '/([a-z])([A-Z])/', '$1-$2', $str ) );

		// Vendor-prefixed properties: WebkitTextStroke → -webkit-text-stroke.
		if ( preg_match( '/^(webkit|moz|ms|o)-/', $kebab ) ) {
			$kebab = '-' . $kebab;
		}

		return $kebab;
	}

	/**
	 * Build data attributes for inline-editable elements.
	 *
	 * @param array  $c    Component data.
	 * @param string $prop The prop name that holds the editable content.
	 * @return string HTML attribute string (leading space included).
	 */
	private function build_editable_attrs( array $c, string $prop ): string {
		$attrs = ' data-tekton-editable="true" data-tekton-prop="' . esc_attr( $prop ) . '"';

		$value = $c['props'][ $prop ] ?? null;
		if ( is_array( $value ) && isset( $value['source'] ) ) {
			$source_data = [
				'source' => $value['source'],
			];
			if ( ! empty( $value['group'] ) ) {
				$source_data['group'] = $value['group'];
			}
			if ( ! empty( $value['field'] ) ) {
				$source_data['field'] = $value['field'];
			}
			if ( ! empty( $value['expression'] ) ) {
				$source_data['expression'] = $value['expression'];
			}
			if ( ! empty( $value['key'] ) ) {
				$source_data['key'] = $value['key'];
			}
			$attrs .= ' data-tekton-source="' . esc_attr( wp_json_encode( $source_data ) ) . '"';
		}

		return $attrs;
	}
}
