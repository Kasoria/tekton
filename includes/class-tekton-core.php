<?php
declare(strict_types=1);
/**
 * Main plugin class — singleton orchestrator.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Tekton_Core {

	private static ?self $instance = null;

	/** @var array<string, object> */
	private array $modules = [];

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->disable_gutenberg();
		$this->init_modules();
		$this->register_hooks();
	}

	private function load_dependencies(): void {
		$dir = TEKTON_DIR . 'includes/';

		require_once $dir . 'class-tekton-security.php';
		require_once $dir . 'class-tekton-storage.php';
		require_once $dir . 'class-tekton-schema.php';
		require_once $dir . 'class-tekton-renderer.php';
		require_once $dir . 'class-tekton-context-builder.php';
		require_once $dir . 'class-tekton-theme-bridge.php';
		require_once $dir . 'class-tekton-assets.php';
		require_once $dir . 'class-tekton-rest-api.php';

		require_once $dir . 'ai/interface-tekton-ai-provider.php';
		require_once $dir . 'ai/class-tekton-ai-engine.php';
		require_once $dir . 'ai/class-tekton-provider-anthropic.php';
		require_once $dir . 'ai/class-tekton-provider-openai.php';
		require_once $dir . 'ai/class-tekton-provider-google.php';
		require_once $dir . 'ai/class-tekton-provider-openrouter.php';
	}

	private function disable_gutenberg(): void {
		add_filter( 'use_block_editor_for_post', '__return_false' );
		add_filter( 'use_block_editor_for_post_type', '__return_false' );
		add_action( 'wp_enqueue_scripts', function (): void {
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'wp-block-library-theme' );
			wp_dequeue_style( 'global-styles' );
		}, 100 );
		add_action( 'admin_enqueue_scripts', function (): void {
			wp_dequeue_style( 'wp-block-library' );
		}, 100 );
	}

	private function init_modules(): void {
		$this->modules['security']       = new Tekton_Security();
		$this->modules['storage']        = new Tekton_Storage();
		$this->modules['schema']         = new Tekton_Schema();
		$this->modules['renderer']       = new Tekton_Renderer();
		$this->modules['context']        = new Tekton_Context_Builder( $this->modules['storage'] );
		$this->modules['theme_bridge']   = new Tekton_Theme_Bridge( $this->modules['storage'], $this->modules['renderer'] );
		$this->modules['assets']         = new Tekton_Assets();
		$this->modules['ai_engine']      = new Tekton_AI_Engine( $this->modules['security'] );
		$this->modules['rest_api']       = new Tekton_REST_API( $this );
	}

	private function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	public function register_admin_menu(): void {
		add_menu_page(
			__( 'Tekton', 'tekton' ),
			__( 'Tekton', 'tekton' ),
			'manage_options',
			'tekton',
			[ $this, 'render_admin_page' ],
			'dashicons-layout',
			3
		);
	}

	public function render_admin_page(): void {
		echo '<div id="tekton-app"></div>';
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_tekton' !== $hook ) {
			return;
		}

		$dist_dir  = TEKTON_DIR . 'admin/dist/';
		$dist_url  = TEKTON_URL . 'admin/dist/';
		$manifest  = $dist_dir . '.vite/manifest.json';

		if ( file_exists( $manifest ) ) {
			$manifest_data = json_decode( (string) file_get_contents( $manifest ), true );
			$entry         = $manifest_data['src/main.js'] ?? null;

			if ( $entry ) {
				if ( ! empty( $entry['css'] ) ) {
					foreach ( $entry['css'] as $i => $css_file ) {
						wp_enqueue_style(
							'tekton-admin-' . $i,
							$dist_url . $css_file,
							[],
							TEKTON_VERSION
						);
					}
				}
				wp_enqueue_script(
					'tekton-admin',
					$dist_url . $entry['file'],
					[],
					TEKTON_VERSION,
					true
				);
			}
		}

		wp_localize_script( 'tekton-admin', 'tektonData', [
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'restUrl'  => esc_url_raw( rest_url() ),
			'siteUrl'  => esc_url_raw( site_url() ),
			'adminUrl' => esc_url_raw( admin_url() ),
			'version'  => TEKTON_VERSION,
		] );
	}

	public function get_module( string $name ): ?object {
		return $this->modules[ $name ] ?? null;
	}

	/**
	 * Get all settings with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$defaults = [
			'tekton_ai_provider'        => 'anthropic',
			'tekton_ai_model'           => 'claude-sonnet-4-20250514',
			'tekton_ai_max_tokens'      => 8192,
			'tekton_context_token_budget'=> 4000,
			'tekton_override_theme'     => true,
			'tekton_fallback_behavior'  => 'theme',
			'tekton_cache_enabled'      => true,
			'tekton_cache_ttl'          => 3600,
			'tekton_minify_output'      => false,
			'tekton_inline_editing'     => true,
			'tekton_max_versions'       => 50,
			'tekton_disable_gutenberg'  => true,
			'tekton_acf_compat'         => true,
			'tekton_plugin_mode_enabled'=> true,
			'tekton_debug_mode'         => false,
		];

		$settings = [];
		foreach ( $defaults as $key => $default ) {
			$settings[ $key ] = get_option( $key, $default );
		}

		return $settings;
	}

	/**
	 * Get a single setting.
	 */
	public static function get_setting( string $key, mixed $default = null ): mixed {
		$settings = self::get_settings();
		return $settings[ $key ] ?? $default;
	}
}
