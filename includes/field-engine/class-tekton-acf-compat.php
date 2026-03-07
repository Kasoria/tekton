<?php
declare(strict_types=1);
/**
 * ACF Compatibility Layer — read-only access to ACF field values.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_ACF_Compat {

	/**
	 * Check if ACF is available.
	 */
	public static function is_available(): bool {
		return function_exists( 'get_field' );
	}

	/**
	 * Get an ACF field value.
	 */
	public static function get_field( string $field_name, int $post_id = 0 ): mixed {
		if ( ! self::is_available() ) {
			return '';
		}

		if ( $post_id > 0 ) {
			return get_field( $field_name, $post_id );
		}

		return get_field( $field_name );
	}

	/**
	 * Get an ACF option field value.
	 */
	public static function get_option_field( string $field_name ): mixed {
		if ( ! self::is_available() ) {
			return '';
		}

		return get_field( $field_name, 'option' );
	}
}
