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
		$this->maybe_upgrade_db();
		$this->disable_gutenberg();
		$this->init_modules();
		$this->register_hooks();
	}

	private const DB_VERSION = '1.2.0';

	private function maybe_upgrade_db(): void {
		$db_version = get_option( 'tekton_db_version', '0' );
		if ( version_compare( $db_version, self::DB_VERSION, '<' ) ) {
			Tekton_Storage::create_tables();
			update_option( 'tekton_db_version', self::DB_VERSION );
		}
	}

	private function load_dependencies(): void {
		$dir = TEKTON_DIR . 'includes/';

		require_once $dir . 'class-tekton-activator.php';
		require_once $dir . 'class-tekton-security.php';
		require_once $dir . 'class-tekton-storage.php';
		require_once $dir . 'class-tekton-schema.php';
		require_once $dir . 'class-tekton-renderer.php';
		require_once $dir . 'class-tekton-context-builder.php';
		require_once $dir . 'class-tekton-theme-bridge.php';
		require_once $dir . 'class-tekton-assets.php';
		require_once $dir . 'class-tekton-rest-api.php';
		require_once $dir . 'class-tekton-structure-patcher.php';
		require_once $dir . 'class-tekton-publisher.php';

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
		$this->modules['publisher']      = new Tekton_Publisher();
		$this->modules['rest_api']       = new Tekton_REST_API( $this );

		$this->ensure_global_templates();
	}

	/**
	 * Ensure global templates (header, footer) exist. Runs on every init
	 * so they are created even without reactivation.
	 */
	private function ensure_global_templates(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'tekton_structures';

		foreach ( Tekton_Activator::GLOBAL_TEMPLATES as $key ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$table} WHERE template_key = %s", $key )
			);
			if ( ! $exists ) {
				$wpdb->insert( $table, [
					'template_key' => $key,
					'title'        => ucfirst( $key ),
					'components'   => '[]',
					'styles'       => '{}',
					'status'       => 'draft',
				] );
			}
		}
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
		echo '<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,700;12..96,800&family=Outfit:wght@300;400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet" />';
		echo '<style>#wpcontent { padding-left: 0; } #wpbody-content { padding-bottom: 0; } .wrap { margin: 0; max-width: none; } .notice, .updated, .update-nag, .error { display: none; }</style>';
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
			$entry         = $manifest_data['admin/src/main.js'] ?? $manifest_data['src/main.js'] ?? null;

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

		$tekton_locale = get_option( 'tekton_locale', '' );
		$switched      = false;
		if ( $tekton_locale && $tekton_locale !== determine_locale() ) {
			switch_to_locale( $tekton_locale );
			$switched = true;
		}

		wp_localize_script( 'tekton-admin', 'tektonData', [
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'restUrl'      => esc_url_raw( rest_url() ),
			'siteUrl'      => esc_url_raw( site_url() ),
			'adminUrl'     => esc_url_raw( admin_url() ),
			'version'      => TEKTON_VERSION,
			'locale'       => $tekton_locale ?: determine_locale(),
			'translations' => self::get_translations(),
		] );

		if ( $switched ) {
			restore_previous_locale();
		}
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
			'tekton_locale'             => '',
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

	/**
	 * Get all translatable UI strings for the admin frontend.
	 *
	 * @return array<string, string>
	 */
	public static function get_translations(): array {
		return [
			// General
			'settings'                  => __( 'Settings', 'tekton' ),
			'save'                      => __( 'Save', 'tekton' ),
			'cancel'                    => __( 'Cancel', 'tekton' ),
			'edit'                      => __( 'Edit', 'tekton' ),
			'delete'                    => __( 'Delete', 'tekton' ),
			'loading'                   => __( 'Loading...', 'tekton' ),
			'saving'                    => __( 'Saving...', 'tekton' ),
			'copy'                      => __( 'Copy', 'tekton' ),
			'discard'                   => __( 'Discard', 'tekton' ),
			'add'                       => __( 'Add', 'tekton' ),
			'create'                    => __( 'Create', 'tekton' ),

			// Dashboard tabs
			'overview'                  => __( 'Overview', 'tekton' ),
			'templates'                 => __( 'Templates', 'tekton' ),
			'fields'                    => __( 'Fields', 'tekton' ),
			'post_types'                => __( 'Post Types', 'tekton' ),

			// Dashboard stats
			'live'                      => __( 'live', 'tekton' ),
			'field_groups'              => __( 'Field Groups', 'tekton' ),
			'entries'                   => __( 'entries', 'tekton' ),
			'plugins'                   => __( 'Plugins', 'tekton' ),
			'active'                    => __( 'active', 'tekton' ),

			// Dashboard overview
			'view_all'                  => __( 'View all →', 'tekton' ),
			'no_templates_yet'          => __( 'No templates yet. Open the builder to create one.', 'tekton' ),
			'comp'                      => __( 'comp', 'tekton' ),
			'global'                    => __( 'Global', 'tekton' ),
			'status_live'               => __( 'LIVE', 'tekton' ),
			'status_draft'              => __( 'DRAFT', 'tekton' ),
			'activity'                  => __( 'Activity', 'tekton' ),
			'no_activity_yet'           => __( 'No activity yet.', 'tekton' ),
			'quick_actions'             => __( 'Quick Actions', 'tekton' ),
			'build_a_page'              => __( 'Build a Page', 'tekton' ),
			'build_a_page_desc'         => __( 'Start from a natural language prompt', 'tekton' ),
			'fullstack_generate'        => __( 'Full-Stack Generate', 'tekton' ),
			'fullstack_generate_desc'   => __( 'CPT + Fields + Template in one shot', 'tekton' ),
			'create_plugin'             => __( 'Create Plugin', 'tekton' ),
			'create_plugin_desc'        => __( 'Generate a server-side feature', 'tekton' ),
			'new_template'              => __( 'New Template', 'tekton' ),
			'components'                => __( 'components', 'tekton' ),

			// Templates tab
			'templates_heading'         => __( 'Templates', 'tekton' ),
			'templates_desc'            => __( 'Page and archive templates managed by Tekton', 'tekton' ),
			'new_template_btn'          => __( '+ New Template', 'tekton' ),

			// Fields tab
			'field_groups_heading'      => __( 'Field Groups', 'tekton' ),
			'field_groups_desc'         => __( 'Content structure — Tekton\'s built-in field engine', 'tekton' ),
			'no_field_groups_yet'       => __( 'No field groups yet. They\'ll be created automatically when the AI generates templates that need custom fields.', 'tekton' ),

			// Post types tab
			'custom_post_types'         => __( 'Custom Post Types', 'tekton' ),
			'no_post_types_yet'         => __( 'No custom post types yet. They\'ll be created when the AI generates full-stack features.', 'tekton' ),

			// Settings
			'api_keys'                  => __( 'API Keys', 'tekton' ),
			'ai'                        => __( 'AI', 'tekton' ),
			'provider'                  => __( 'Provider', 'tekton' ),
			'model'                     => __( 'Model', 'tekton' ),
			'custom_model'              => __( 'Custom model...', 'tekton' ),
			'custom_model_id'           => __( 'Custom model ID', 'tekton' ),
			'max_tokens'                => __( 'Max tokens', 'tekton' ),
			'not_set'                   => __( 'Not set', 'tekton' ),
			'enter_api_key'             => __( 'Enter API key...', 'tekton' ),
			'rendering'                 => __( 'Rendering', 'tekton' ),
			'optional'                  => __( 'Optional', 'tekton' ),
			'override_theme'            => __( 'Override theme', 'tekton' ),
			'disable_gutenberg'         => __( 'Disable Gutenberg', 'tekton' ),
			'cache_html'                => __( 'Cache HTML', 'tekton' ),
			'minify_output'             => __( 'Minify output', 'tekton' ),
			'acf_compatibility'         => __( 'ACF compatibility', 'tekton' ),
			'plugin_mode'               => __( 'Plugin Mode', 'tekton' ),
			'debug_mode'                => __( 'Debug mode', 'tekton' ),

			// Header
			'api_connected'             => __( 'API Connected', 'tekton' ),
			'no_api_key'                => __( 'No API Key', 'tekton' ),
			'open_builder'              => __( 'Open Builder', 'tekton' ),

			// Delete dialog
			'delete_template'           => __( 'Delete template', 'tekton' ),
			'delete_template_desc'      => __( 'This will permanently delete the template and all its versions. This cannot be undone.', 'tekton' ),

			// Builder
			'editing'                   => __( 'editing', 'tekton' ),
			'select_page'               => __( 'Select page', 'tekton' ),
			'template_name'             => __( 'Template name...', 'tekton' ),
			'preview'                   => __( 'Preview', 'tekton' ),
			'code'                      => __( 'Code', 'tekton' ),
			'tree'                      => __( 'Tree', 'tekton' ),
			'history'                   => __( 'History', 'tekton' ),
			'component_tree'            => __( 'Component Tree', 'tekton' ),
			'version_history'           => __( 'Version History', 'tekton' ),
			'generated_plugins'         => __( 'Generated Plugins', 'tekton' ),
			'preview_link'              => __( 'Preview ↗', 'tekton' ),
			'unpublish'                 => __( 'Unpublish', 'tekton' ),
			'view_link'                 => __( 'View ↗', 'tekton' ),
			'publish'                   => __( 'Publish', 'tekton' ),
			'you'                       => __( 'You', 'tekton' ),
			'tekton'                    => __( 'Tekton', 'tekton' ),
			'summary_of_previous'       => __( 'summary of previous session', 'tekton' ),
			'preview_updated'           => __( 'Preview updated', 'tekton' ),
			'generating_structure'      => __( 'Generating structure…', 'tekton' ),
			'thinking'                  => __( 'Thinking...', 'tekton' ),
			'describe_prompt'           => __( 'Describe what to build or change...', 'tekton' ),
			'clear_chat'                => __( 'Clear chat', 'tekton' ),
			'clearing'                  => __( 'Clearing...', 'tekton' ),
			'clear_with_summary'        => __( 'Clear with summary', 'tekton' ),
			'clear_with_summary_desc'   => __( 'AI summarizes the conversation, then clears', 'tekton' ),
			'clear_all'                 => __( 'Clear all', 'tekton' ),
			'clear_all_desc'            => __( 'Remove entire chat history', 'tekton' ),
			'shift_enter_newline'       => __( 'shift+enter for newline', 'tekton' ),
			'structure_json'            => __( 'Structure JSON', 'tekton' ),
			'rendered_html'             => __( 'Rendered HTML', 'tekton' ),
			'no_preview_html'           => __( 'No preview HTML generated yet.', 'tekton' ),
			'no_template_selected'      => __( 'No template selected.', 'tekton' ),
			'loading_preview'           => __( 'Loading preview...', 'tekton' ),
			'no_template_hint'          => __( 'Use the chat to generate a page, or select a template from the dropdown above.', 'tekton' ),
			'no_components_yet'         => __( 'No components yet.', 'tekton' ),
			'no_versions_yet'           => __( 'No versions yet.', 'tekton' ),
			'no_field_groups_sidebar'   => __( 'No field groups yet.', 'tekton' ),
			'plugins_hint'              => __( 'Generated plugins will appear here.', 'tekton' ),
			'plugins_hint_cmd'          => __( 'Use /plugin in the chat to generate one.', 'tekton' ),
			'current'                   => __( 'CURRENT', 'tekton' ),
			'restore'                   => __( 'Restore', 'tekton' ),
			'rename'                    => __( 'Rename', 'tekton' ),
			'label'                     => __( 'Label', 'tekton' ),
			'version_label'             => __( 'Version label...', 'tekton' ),
			'ai_generate'               => __( 'AI generated', 'tekton' ),
			'manual_edit'               => __( 'Manual edit', 'tekton' ),
			'restored'                  => __( 'Restored', 'tekton' ),
			'published'                 => __( 'Published', 'tekton' ),
			'just_now'                  => __( 'just now', 'tekton' ),
			'attach_image'              => __( 'Attach image', 'tekton' ),
			'send_message'              => __( 'Send message', 'tekton' ),

			// ChatPanel
			'tekton_builder'            => __( 'Tekton Builder', 'tekton' ),
			'chat_empty_hint'           => __( 'Describe the page you want to build.', 'tekton' ),
			'chat_empty_example'        => __( 'Try: "Create a landing page with a hero section, features grid, and call to action"', 'tekton' ),
			'chat_placeholder'          => __( 'Describe what you want to build...', 'tekton' ),

			// PageSelector
			'select_template'           => __( 'Select template...', 'tekton' ),
			'front_page'                => __( 'Front Page', 'tekton' ),
			'template_key'              => __( 'template-key', 'tekton' ),

			// PreviewPanel
			'no_preview_hint'           => __( 'No preview yet. Start by describing a page in the chat.', 'tekton' ),
			'preview_error'             => __( 'Preview error:', 'tekton' ),
			'page_preview'              => __( 'Page Preview', 'tekton' ),

			// SettingsPanel
			'ai_provider'               => __( 'AI Provider', 'tekton' ),
			'api_key_for'               => __( 'API Key', 'tekton' ),
			'model_hint'                => __( 'Select provider and enter API key to load models', 'tekton' ),
			'settings_saved'            => __( 'Settings saved.', 'tekton' ),
			'save_settings'             => __( 'Save Settings', 'tekton' ),
			'api_keys_encrypted'        => __( 'API keys are encrypted and stored in your WordPress database. They are never sent to the frontend.', 'tekton' ),
			'salt_warning'              => __( 'If your WordPress security salts are regenerated, you will need to re-enter your API keys.', 'tekton' ),

			// Language
			'language'                  => __( 'Language', 'tekton' ),
			'language_auto'             => __( 'Auto (WordPress default)', 'tekton' ),
		];
	}
}
