<?php
declare(strict_types=1);
/**
 * Plugin activation and deactivation logic.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Activator {

	/** Global template keys that exist on every install. */
	public const GLOBAL_TEMPLATES = [ 'header', 'footer' ];

	public static function activate(): void {
		self::create_tables();
		self::install_bridge_theme();
		self::set_default_options();
		self::create_global_templates();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	private static function create_tables(): void {
		require_once TEKTON_DIR . 'includes/class-tekton-storage.php';
		Tekton_Storage::create_tables();
	}

	private static function install_bridge_theme(): void {
		$source = TEKTON_DIR . 'bridge-theme/';
		$dest   = get_theme_root() . '/tekton-bridge/';

		if ( ! is_dir( $source ) ) {
			return;
		}

		if ( ! is_dir( $dest ) ) {
			wp_mkdir_p( $dest );
		}

		$files = [ 'style.css', 'index.php', 'functions.php' ];
		foreach ( $files as $file ) {
			if ( file_exists( $source . $file ) ) {
				copy( $source . $file, $dest . $file );
			}
		}

		$screenshot_src = $source . 'screenshot.png';
		if ( file_exists( $screenshot_src ) ) {
			copy( $screenshot_src, $dest . 'screenshot.png' );
		}

		switch_theme( 'tekton-bridge' );
	}

	private static function set_default_options(): void {
		$defaults = [
			'tekton_ai_provider'   => 'anthropic',
			'tekton_ai_model'      => 'claude-sonnet-4-20250514',
			'tekton_ai_max_tokens' => 8192,
			'tekton_override_theme'=> true,
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	private static function create_global_templates(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'tekton_structures';

		$templates = [
			'header' => 'Header',
			'footer' => 'Footer',
		];

		foreach ( $templates as $key => $title ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$table} WHERE template_key = %s", $key )
			);
			if ( ! $exists ) {
				$wpdb->insert( $table, [
					'template_key' => $key,
					'title'        => $title,
					'components'   => '[]',
					'styles'       => '{}',
					'status'       => 'draft',
				] );
			}
		}
	}

}
