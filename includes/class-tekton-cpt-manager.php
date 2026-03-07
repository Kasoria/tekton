<?php
declare(strict_types=1);
/**
 * CPT Manager — registers custom post types and taxonomies from DB.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_CPT_Manager {

	private Tekton_Storage $storage;

	public function __construct( Tekton_Storage $storage ) {
		$this->storage = $storage;
	}

	public function init(): void {
		add_action( 'init', [ $this, 'register_all' ], 5 );
	}

	/**
	 * Register all Tekton post types and taxonomies from DB.
	 */
	public function register_all(): void {
		$post_types = $this->storage->list_post_types();

		foreach ( $post_types as $pt ) {
			$this->register_post_type( $pt );
		}

		$this->maybe_flush_rewrite_rules( $post_types );
	}

	private function register_post_type( array $pt ): void {
		$slug   = sanitize_key( $pt['slug'] ?? '' );
		$config = $pt['config'] ?? [];

		if ( ! $slug || post_type_exists( $slug ) ) {
			return;
		}

		// Ensure show_in_rest for REST API access
		$config['show_in_rest'] = $config['show_in_rest'] ?? true;

		// Never include editor in supports — Tekton replaces it
		if ( isset( $config['supports'] ) && is_array( $config['supports'] ) ) {
			$config['supports'] = array_values( array_diff( $config['supports'], [ 'editor' ] ) );
		}

		register_post_type( $slug, $config );

		// Register associated taxonomies
		$taxonomies = $pt['taxonomies'] ?? [];
		foreach ( $taxonomies as $tax ) {
			$this->register_taxonomy( $tax, $slug );
		}
	}

	private function register_taxonomy( array $tax, string $post_type ): void {
		$slug   = sanitize_key( $tax['slug'] ?? '' );
		$config = $tax['config'] ?? [];

		if ( ! $slug || taxonomy_exists( $slug ) ) {
			return;
		}

		$config['show_in_rest'] = $config['show_in_rest'] ?? true;

		register_taxonomy( $slug, $post_type, $config );
	}

	/**
	 * Flush rewrite rules only when CPT configuration changes.
	 */
	private function maybe_flush_rewrite_rules( array $post_types ): void {
		$hash     = md5( wp_json_encode( $post_types ) );
		$previous = get_transient( 'tekton_cpt_hash' );

		if ( $hash !== $previous ) {
			flush_rewrite_rules( false );
			set_transient( 'tekton_cpt_hash', $hash, DAY_IN_SECONDS );
		}
	}
}
