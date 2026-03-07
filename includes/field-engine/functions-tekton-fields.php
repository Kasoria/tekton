<?php
declare(strict_types=1);
/**
 * Public API functions for Tekton Field Engine.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Get a single Tekton field value.
 *
 * @param string   $field_name Field key within the group.
 * @param int|null $post_id    Post ID (defaults to current post).
 * @param string   $group      Field group slug.
 */
function tekton_get_field( string $field_name, ?int $post_id = null, string $group = '' ): mixed {
	if ( ! $post_id ) {
		$post_id = get_the_ID() ?: 0;
	}

	$engine = Tekton_Core::instance()->get_module( 'field_engine' );
	if ( ! $engine instanceof Tekton_Field_Engine ) {
		return '';
	}

	return $engine->get_field_value( $group, $field_name, $post_id );
}

/**
 * Get all field values for a group.
 *
 * @param string   $group   Field group slug.
 * @param int|null $post_id Post ID (defaults to current post).
 * @return array<string, mixed>
 */
function tekton_get_fields( string $group, ?int $post_id = null ): array {
	if ( ! $post_id ) {
		$post_id = get_the_ID() ?: 0;
	}

	$engine = Tekton_Core::instance()->get_module( 'field_engine' );
	if ( ! $engine instanceof Tekton_Field_Engine ) {
		return [];
	}

	return $engine->get_group_values( $group, $post_id );
}

/**
 * Get a Tekton option value.
 *
 * @param string $field_name Field key.
 * @param string $page       Options page slug.
 */
function tekton_get_option( string $field_name, string $page = '' ): mixed {
	$engine = Tekton_Core::instance()->get_module( 'field_engine' );
	if ( ! $engine instanceof Tekton_Field_Engine ) {
		return '';
	}

	return $engine->get_option_value( $page, $field_name );
}
