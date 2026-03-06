<?php
declare(strict_types=1);
/**
 * Publishes Tekton templates as real WordPress pages.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Publisher {

	/**
	 * Create or update a WordPress page for a template.
	 * Returns the page URL or null for global templates.
	 */
	public function publish( string $template_key, string $title, string $status = 'published' ): ?string {
		// Global templates (header/footer) don't get their own pages.
		if ( in_array( $template_key, Tekton_Activator::GLOBAL_TEMPLATES, true ) ) {
			return null;
		}

		$wp_status = 'published' === $status ? 'publish' : 'draft';
		$post_id   = $this->get_page_for_template( $template_key );

		if ( $post_id ) {
			wp_update_post( [
				'ID'          => $post_id,
				'post_title'  => $title ?: $template_key,
				'post_status' => $wp_status,
			] );
		} else {
			$post_id = wp_insert_post( [
				'post_title'  => $title ?: ucwords( str_replace( '-', ' ', $template_key ) ),
				'post_name'   => $template_key,
				'post_status' => $wp_status,
				'post_type'   => 'page',
				'post_content' => '', // Content lives in Tekton structures, not the post.
			] );

			if ( is_wp_error( $post_id ) ) {
				return null;
			}

			update_post_meta( $post_id, '_tekton_template_key', $template_key );
		}

		// Handle front page assignment.
		if ( 'front-page' === $template_key && 'publish' === $wp_status ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $post_id );
		}

		return get_permalink( $post_id ) ?: null;
	}

	/**
	 * Unpublish (draft) a template's WordPress page.
	 */
	public function unpublish( string $template_key ): void {
		$post_id = $this->get_page_for_template( $template_key );
		if ( $post_id ) {
			wp_update_post( [
				'ID'          => $post_id,
				'post_status' => 'draft',
			] );
		}
	}

	/**
	 * Get the WP page ID for a template key, or 0 if none.
	 */
	public function get_page_for_template( string $template_key ): int {
		global $wpdb;

		$post_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = '_tekton_template_key' AND meta_value = %s
				 LIMIT 1",
				$template_key
			)
		);

		// Verify the post still exists and is a page.
		if ( $post_id && get_post_type( $post_id ) === 'page' ) {
			return $post_id;
		}

		return 0;
	}

	/**
	 * Get the frontend URL for a template, or null.
	 */
	public function get_url( string $template_key ): ?string {
		if ( in_array( $template_key, Tekton_Activator::GLOBAL_TEMPLATES, true ) ) {
			return null;
		}

		$post_id = $this->get_page_for_template( $template_key );
		if ( ! $post_id ) {
			return null;
		}

		return get_permalink( $post_id ) ?: null;
	}

	/**
	 * Get a preview URL for a template (works for drafts too).
	 */
	public function get_preview_url( string $template_key ): ?string {
		if ( in_array( $template_key, Tekton_Activator::GLOBAL_TEMPLATES, true ) ) {
			return null;
		}

		$post_id = $this->get_page_for_template( $template_key );
		if ( ! $post_id ) {
			return null;
		}

		$post_status = get_post_status( $post_id );
		if ( 'publish' === $post_status ) {
			return get_permalink( $post_id ) ?: null;
		}

		// For drafts, use WordPress preview link.
		return get_preview_post_link( $post_id ) ?: null;
	}

	/**
	 * Get the WP post status for a template's page.
	 */
	public function get_post_status( string $template_key ): ?string {
		$post_id = $this->get_page_for_template( $template_key );
		if ( ! $post_id ) {
			return null;
		}
		return get_post_status( $post_id ) ?: null;
	}
}
