<?php
declare(strict_types=1);
/**
 * REST API controller — field groups and post types.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_REST_Content {

	private Tekton_Core $core;

	public function __construct( Tekton_Core $core ) {
		$this->core = $core;
	}

	public function register_routes( string $ns ): void {
		// Field Groups.
		register_rest_route( $ns, '/field-groups', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_list_field_groups' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_save_field_group' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
		] );

		register_rest_route( $ns, '/field-groups/(?P<id>\d+)', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_get_field_group' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'handle_save_field_group' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'handle_delete_field_group' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
		] );

		// All registered WP post types (for dropdowns).
		register_rest_route( $ns, '/wp-post-types', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_list_wp_post_types' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
		] );

		// Post Types.
		register_rest_route( $ns, '/post-types', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_list_post_types' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_save_post_type' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
		] );

		register_rest_route( $ns, '/post-types/(?P<id>\d+)', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_get_post_type' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'handle_save_post_type' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'handle_delete_post_type' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
		] );

		// Options Pages.
		register_rest_route( $ns, '/options-pages', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_list_options_pages' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_save_options_page' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
		] );

		register_rest_route( $ns, '/options-pages/(?P<slug>[a-z0-9_]+)', [
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'handle_delete_options_page' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
		] );
	}

	// ─── Field Groups ───────────────────────────────────────────────────

	public function handle_list_field_groups(): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		return new \WP_REST_Response( $storage->list_field_groups() );
	}

	public function handle_get_field_group( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		$group   = $storage->get_field_group( (int) $request->get_param( 'id' ) );
		if ( ! $group ) {
			return new \WP_REST_Response( [ 'message' => 'Field group not found.' ], 404 );
		}
		return new \WP_REST_Response( $group );
	}

	public function handle_save_field_group( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		$data    = $request->get_json_params();

		if ( empty( $data['slug'] ) || empty( $data['title'] ) ) {
			return new \WP_REST_Response( [ 'message' => 'Slug and title are required.' ], 400 );
		}

		if ( ! preg_match( '/^[a-z0-9_-]+$/', $data['slug'] ) ) {
			return new \WP_REST_Response( [ 'message' => 'Invalid slug format.' ], 400 );
		}

		if ( isset( $data['fields'] ) ) {
			if ( ! is_array( $data['fields'] ) ) {
				return new \WP_REST_Response( [ 'message' => 'Fields must be an array.' ], 400 );
			}
			if ( count( $data['fields'] ) > 100 ) {
				return new \WP_REST_Response( [ 'message' => 'Too many fields (max 100).' ], 400 );
			}
			$valid_field_types = [
				'text', 'textarea', 'number', 'email', 'url', 'select',
				'checkbox', 'radio', 'true_false', 'image', 'date',
				'color', 'repeater', 'relationship',
			];
			foreach ( $data['fields'] as $i => $field ) {
				if ( ! is_array( $field ) ) {
					return new \WP_REST_Response( [ 'message' => sprintf( 'Field %d must be an object.', $i ) ], 400 );
				}
				if ( empty( $field['name'] ) || empty( $field['type'] ) ) {
					return new \WP_REST_Response( [ 'message' => sprintf( 'Field %d missing name or type.', $i ) ], 400 );
				}
				if ( ! in_array( $field['type'], $valid_field_types, true ) ) {
					return new \WP_REST_Response( [ 'message' => sprintf( 'Field %d has invalid type: %s', $i, sanitize_text_field( $field['type'] ) ) ], 400 );
				}
			}
		}

		if ( isset( $data['location_rules'] ) && ! is_array( $data['location_rules'] ) ) {
			return new \WP_REST_Response( [ 'message' => 'location_rules must be an array.' ], 400 );
		}

		$id = $storage->save_field_group( $data );

		$this->core->get_module( 'context' )->flush_cache();

		return new \WP_REST_Response( [ 'id' => $id, 'saved' => true ] );
	}

	public function handle_delete_field_group( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		$deleted = $storage->delete_field_group( (int) $request->get_param( 'id' ) );

		$this->core->get_module( 'context' )->flush_cache();

		return new \WP_REST_Response( [ 'deleted' => $deleted ] );
	}

	// ─── WP Post Types (all registered) ─────────────────────────────────

	public function handle_list_wp_post_types(): \WP_REST_Response {
		$types  = get_post_types( [ 'show_ui' => true ], 'objects' );
		$result = [];
		foreach ( $types as $pt ) {
			$result[] = [
				'value' => $pt->name,
				'label' => $pt->labels->singular_name ?: $pt->label,
			];
		}
		usort( $result, fn( $a, $b ) => strcasecmp( $a['label'], $b['label'] ) );
		return new \WP_REST_Response( $result );
	}

	// ─── Post Types ─────────────────────────────────────────────────────

	public function handle_list_post_types(): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );

		$post_types = $storage->list_post_types();
		foreach ( $post_types as &$cpt ) {
			$count = wp_count_posts( $cpt['slug'] );
			$cpt['entry_count'] = $count ? (int) $count->publish + (int) $count->draft : 0;
		}

		return new \WP_REST_Response( $post_types );
	}

	public function handle_get_post_type( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		$pt      = $storage->get_post_type_entry( (int) $request->get_param( 'id' ) );
		if ( ! $pt ) {
			return new \WP_REST_Response( [ 'message' => 'Post type not found.' ], 404 );
		}
		return new \WP_REST_Response( $pt );
	}

	public function handle_save_post_type( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		$data    = $request->get_json_params();

		if ( empty( $data['slug'] ) ) {
			return new \WP_REST_Response( [ 'message' => 'Slug is required.' ], 400 );
		}

		if ( ! preg_match( '/^[a-z0-9_-]+$/', $data['slug'] ) || strlen( $data['slug'] ) > 20 ) {
			return new \WP_REST_Response( [ 'message' => 'Invalid slug format (lowercase alphanumeric, max 20 chars).' ], 400 );
		}

		$reserved = [
			'post', 'page', 'attachment', 'revision', 'nav_menu_item',
			'custom_css', 'customize_changeset', 'wp_block',
			'wp_template', 'wp_template_part', 'wp_navigation',
		];
		if ( in_array( $data['slug'], $reserved, true ) ) {
			return new \WP_REST_Response( [ 'message' => 'Cannot use a reserved post type slug.' ], 400 );
		}

		if ( isset( $data['config'] ) && ! is_array( $data['config'] ) ) {
			return new \WP_REST_Response( [ 'message' => 'Config must be an object.' ], 400 );
		}

		if ( isset( $data['taxonomies'] ) && ! is_array( $data['taxonomies'] ) ) {
			return new \WP_REST_Response( [ 'message' => 'Taxonomies must be an array.' ], 400 );
		}

		$id = $storage->save_post_type( $data );

		delete_transient( 'tekton_cpt_hash' );
		$this->core->get_module( 'context' )->flush_cache();

		return new \WP_REST_Response( [ 'id' => $id, 'saved' => true ] );
	}

	public function handle_delete_post_type( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		$deleted = $storage->delete_post_type( (int) $request->get_param( 'id' ) );

		delete_transient( 'tekton_cpt_hash' );
		$this->core->get_module( 'context' )->flush_cache();

		return new \WP_REST_Response( [ 'deleted' => $deleted ] );
	}

	// ─── Options Pages ──────────────────────────────────────────────────

	public function handle_list_options_pages(): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		return new \WP_REST_Response( $storage->list_options_pages() );
	}

	public function handle_save_options_page( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		$data    = $request->get_json_params();

		if ( empty( $data['slug'] ) || empty( $data['title'] ) ) {
			return new \WP_REST_Response( [ 'message' => 'Slug and title are required.' ], 400 );
		}

		if ( ! preg_match( '/^[a-z0-9_]+$/', $data['slug'] ) ) {
			return new \WP_REST_Response( [ 'message' => 'Invalid slug format (lowercase alphanumeric and underscores only).' ], 400 );
		}

		if ( strlen( $data['slug'] ) > 100 ) {
			return new \WP_REST_Response( [ 'message' => 'Slug must be 100 characters or fewer.' ], 400 );
		}

		$valid_capabilities = [ 'manage_options', 'edit_posts', 'edit_pages', 'publish_posts', 'edit_others_posts' ];
		if ( ! empty( $data['capability'] ) && ! in_array( $data['capability'], $valid_capabilities, true ) ) {
			return new \WP_REST_Response( [ 'message' => 'Invalid capability.' ], 400 );
		}

		$saved = $storage->save_options_page( $data );
		$this->core->get_module( 'context' )->flush_cache();

		return new \WP_REST_Response( [ 'saved' => $saved ] );
	}

	public function handle_delete_options_page( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		$slug    = sanitize_key( $request->get_param( 'slug' ) );

		if ( '' === $slug ) {
			return new \WP_REST_Response( [ 'message' => 'Slug is required.' ], 400 );
		}

		$deleted = $storage->delete_options_page( $slug );
		$this->core->get_module( 'context' )->flush_cache();

		return new \WP_REST_Response( [ 'deleted' => $deleted ] );
	}
}
