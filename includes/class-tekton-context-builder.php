<?php
declare(strict_types=1);
/**
 * Builds WordPress site context snapshot for AI prompts.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Context_Builder {

	private Tekton_Storage $storage;

	public function __construct( Tekton_Storage $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Build full site context. Cached as transient.
	 *
	 * @return array<string, mixed>
	 */
	public function build( array $options = [] ): array {
		$cache_key = 'tekton_site_context';

		if ( empty( $options['force'] ) ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$context = [
			'site'            => [
				'name'  => get_bloginfo( 'name' ),
				'url'   => site_url(),
				'admin' => admin_url(),
			],
			'post_types'      => $this->get_post_types(),
			'taxonomies'      => $this->get_taxonomies(),
			'menus'           => $this->get_menus(),
			'field_groups'    => $this->get_tekton_field_groups(),
			'options_pages'   => $this->get_options_pages(),
			'templates'       => $this->get_existing_templates(),
			'design_tokens'   => $this->get_design_tokens(),
			'active_plugins'  => $this->get_active_plugins(),
			'woocommerce'     => $this->get_woocommerce_status(),
			'theme'           => $this->get_theme(),
		];

		set_transient( $cache_key, $context, HOUR_IN_SECONDS );

		return $context;
	}

	public function flush_cache(): void {
		delete_transient( 'tekton_site_context' );
	}

	/**
	 * Format context as condensed string for AI prompt.
	 */
	public function to_string( array $context ): string {
		$lines = [];

		$lines[] = "Site: {$context['site']['name']} ({$context['site']['url']})";

		if ( ! empty( $context['post_types'] ) ) {
			$types = array_map( fn( $pt ) => $pt['slug'] . ' (' . $pt['source'] . ')', $context['post_types'] );
			$lines[] = 'Post types: ' . implode( ', ', $types );
		}

		if ( ! empty( $context['taxonomies'] ) ) {
			$taxes = array_column( $context['taxonomies'], 'slug' );
			$lines[] = 'Taxonomies: ' . implode( ', ', $taxes );
		}

		if ( ! empty( $context['menus'] ) ) {
			$menus = [];
			foreach ( $context['menus'] as $loc => $name ) {
				$menus[] = "{$loc}: {$name}";
			}
			$lines[] = 'Menus: ' . implode( ', ', $menus );
		}

		if ( ! empty( $context['field_groups'] ) ) {
			$groups = array_map( fn( $g ) => $g['title'] . ' (' . $g['slug'] . ')', $context['field_groups'] );
			$lines[] = 'Field groups: ' . implode( ', ', $groups );
		}

		if ( ! empty( $context['options_pages'] ) ) {
			$pages = array_map( fn( $p ) => $p['title'] . ' (' . $p['slug'] . ')', $context['options_pages'] );
			$lines[] = 'Options pages: ' . implode( ', ', $pages );
		}

		if ( ! empty( $context['templates'] ) ) {
			$templates = array_column( $context['templates'], 'template_key' );
			$lines[] = 'Existing templates: ' . implode( ', ', $templates );
		}

		if ( ! empty( $context['woocommerce']['active'] ) ) {
			$lines[] = 'WooCommerce: active';
		}

		if ( ! empty( $context['theme'] ) ) {
			$theme = $context['theme'];
			$lines[] = "Theme: {$theme['name']} — {$theme['description']}";
			if ( ! empty( $theme['style_notes'] ) ) {
				$lines[] = "Style: {$theme['style_notes']}";
			}
			if ( ! empty( $theme['colors'] ) ) {
				$c = $theme['colors'];
				$lines[] = "Colors: primary={$c['primary']}, secondary={$c['secondary']}, accent={$c['accent']}, bg={$c['background']}";
			}
			if ( ! empty( $theme['fonts'] ) ) {
				$f = $theme['fonts'];
				$lines[] = "Fonts: heading={$f['heading']}, body={$f['body']}";
			}
		}

		// Include available CSS variable names so the AI uses exact token names.
		if ( ! empty( $context['design_tokens'] ) ) {
			$vars = [];
			$prefixes = [
				'colors'     => '--tekton-',
				'fonts'      => '--tekton-font-',
				'typography' => '--tekton-',
				'spacing'    => '--tekton-spacing-',
				'radii'      => '--tekton-radius-',
				'shadows'    => '--tekton-shadow-',
			];
			foreach ( $prefixes as $category => $prefix ) {
				if ( ! empty( $context['design_tokens'][ $category ] ) ) {
					foreach ( array_keys( $context['design_tokens'][ $category ] ) as $key ) {
						$vars[] = "var({$prefix}{$key})";
					}
				}
			}
			if ( $vars ) {
				$lines[] = 'Available design tokens: ' . implode( ', ', $vars );
			}
		}

		return implode( "\n", $lines );
	}

	private function get_post_types(): array {
		$types  = get_post_types( [ 'public' => true ], 'objects' );
		$result = [];

		foreach ( $types as $pt ) {
			$source = 'core';
			if ( in_array( $pt->name, [ 'product', 'shop_order', 'shop_coupon' ], true ) ) {
				$source = 'woocommerce';
			}

			$result[] = [
				'slug'     => $pt->name,
				'label'    => $pt->label,
				'source'   => $source,
				'has_archive' => (bool) $pt->has_archive,
				'supports' => get_all_post_type_supports( $pt->name ),
			];
		}

		return $result;
	}

	private function get_taxonomies(): array {
		$taxes  = get_taxonomies( [ 'public' => true ], 'objects' );
		$result = [];

		foreach ( $taxes as $tax ) {
			$result[] = [
				'slug'         => $tax->name,
				'label'        => $tax->label,
				'hierarchical' => $tax->hierarchical,
				'post_types'   => $tax->object_type,
			];
		}

		return $result;
	}

	private function get_menus(): array {
		$locations = get_registered_nav_menus();
		$assigned  = get_nav_menu_locations();
		$result    = [];

		foreach ( $locations as $slug => $label ) {
			$menu_name = '';
			if ( ! empty( $assigned[ $slug ] ) ) {
				$menu = wp_get_nav_menu_object( $assigned[ $slug ] );
				if ( $menu ) {
					$menu_name = $menu->name;
				}
			}
			$result[ $slug ] = $menu_name ?: '(unassigned)';
		}

		return $result;
	}

	private function get_tekton_field_groups(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'tekton_field_groups';

		// Table may not exist yet during activation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		if ( ! $exists ) {
			return [];
		}

		$rows = $wpdb->get_results(
			"SELECT title, slug, fields, location_rules FROM {$table} WHERE is_active = 1",
			ARRAY_A
		);

		return array_map( function ( $row ) {
			return [
				'title'          => $row['title'],
				'slug'           => $row['slug'],
				'fields'         => json_decode( $row['fields'], true ) ?? [],
				'location_rules' => json_decode( $row['location_rules'], true ) ?? [],
			];
		}, $rows ?: [] );
	}

	private function get_options_pages(): array {
		return $this->storage->list_options_pages();
	}

	private function get_existing_templates(): array {
		return $this->storage->list_structures();
	}

	private function get_design_tokens(): array {
		$tokens = get_option( 'tekton_design_tokens', '' );
		if ( is_string( $tokens ) ) {
			return json_decode( $tokens, true ) ?? [];
		}
		return is_array( $tokens ) ? $tokens : [];
	}

	private function get_active_plugins(): array {
		$plugins = get_option( 'active_plugins', [] );
		return array_map( fn( $p ) => explode( '/', $p )[0], $plugins );
	}

	private function get_theme(): ?array {
		$theme = get_option( 'tekton_theme', null );
		if ( is_string( $theme ) ) {
			$theme = json_decode( $theme, true );
		}
		return is_array( $theme ) ? $theme : null;
	}

	private function get_woocommerce_status(): array {
		$active = class_exists( 'WooCommerce' );
		return [
			'active'  => $active,
			'version' => $active && defined( 'WC_VERSION' ) ? WC_VERSION : null,
		];
	}
}
