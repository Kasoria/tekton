<?php
declare(strict_types=1);
/**
 * REST API controller — settings, theme, dashboard, context, activity.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_REST_Settings {

	private Tekton_Core $core;

	public function __construct( Tekton_Core $core ) {
		$this->core = $core;
	}

	public function register_routes( string $ns ): void {
		// Context.
		register_rest_route( $ns, '/context', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_get_context' ],
			'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
		] );

		register_rest_route( $ns, '/context/refresh', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_refresh_context' ],
			'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
		] );

		// Settings.
		register_rest_route( $ns, '/settings', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_get_settings' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_save_settings' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
		] );

		// Dashboard.
		register_rest_route( $ns, '/dashboard', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_get_dashboard' ],
			'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
		] );

		// Activity.
		register_rest_route( $ns, '/activity', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_get_activity' ],
			'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
		] );

		// Theme.
		register_rest_route( $ns, '/theme', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_get_theme' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_save_theme' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
		] );

		register_rest_route( $ns, '/theme/generate', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_generate_theme' ],
			'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
		] );
	}

	// ─── Context ────────────────────────────────────────────────────────

	public function handle_get_context(): \WP_REST_Response {
		/** @var Tekton_Context_Builder $ctx */
		$ctx = $this->core->get_module( 'context' );
		return new \WP_REST_Response( $ctx->build() );
	}

	public function handle_refresh_context(): \WP_REST_Response {
		/** @var Tekton_Context_Builder $ctx */
		$ctx = $this->core->get_module( 'context' );
		$ctx->flush_cache();
		return new \WP_REST_Response( $ctx->build( [ 'force' => true ] ) );
	}

	// ─── Settings ───────────────────────────────────────────────────────

	public function handle_get_settings(): \WP_REST_Response {
		/** @var Tekton_Security $security */
		$security = $this->core->get_module( 'security' );
		$settings = Tekton_Core::get_settings();

		$providers = Tekton_AI_Engine::get_available_providers();
		foreach ( array_keys( $providers ) as $slug ) {
			$encrypted = get_option( "tekton_api_key_{$slug}", '' );
			$decrypted = $security->decrypt_api_key( $encrypted );
			$settings[ "tekton_api_key_{$slug}" ] = $security->mask_api_key( $decrypted );
		}

		$settings['tekton_available_providers'] = $providers;

		return new \WP_REST_Response( $settings );
	}

	public function handle_save_settings( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var Tekton_Security $security */
		$security = $this->core->get_module( 'security' );
		$params   = $request->get_json_params();

		$allowed_keys = [
			'tekton_ai_provider',
			'tekton_ai_model',
			'tekton_ai_max_tokens',
			'tekton_context_token_budget',
			'tekton_override_theme',
			'tekton_fallback_behavior',
			'tekton_cache_enabled',
			'tekton_cache_ttl',
			'tekton_minify_output',
			'tekton_inline_editing',
			'tekton_max_versions',
			'tekton_disable_gutenberg',
			'tekton_acf_compat',
			'tekton_plugin_mode_enabled',
			'tekton_design_tokens',
			'tekton_debug_mode',
			'tekton_locale',
		];

		foreach ( $allowed_keys as $key ) {
			if ( array_key_exists( $key, $params ) ) {
				$value = $params[ $key ];
				if ( 'tekton_design_tokens' === $key && is_array( $value ) ) {
					$value = wp_json_encode( $value );
				}
				update_option( $key, $value );
			}
		}

		// Handle API keys separately — encrypt before storing.
		$providers = Tekton_AI_Engine::get_available_providers();
		foreach ( array_keys( $providers ) as $slug ) {
			$param_key = "tekton_api_key_{$slug}";
			if ( ! empty( $params[ $param_key ] ) && ! str_contains( $params[ $param_key ], '...' ) ) {
				$encrypted = $security->encrypt_api_key( sanitize_text_field( $params[ $param_key ] ) );
				update_option( $param_key, $encrypted );
			}
		}

		return new \WP_REST_Response( [ 'saved' => true ] );
	}

	// ─── Dashboard ─────────────────────────────────────────────────────

	public function handle_get_dashboard(): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );

		$structures   = $storage->list_structures();
		$field_groups = $storage->list_field_groups();
		$post_types   = $storage->list_post_types();
		$activity     = $storage->get_recent_activity( 10 );

		foreach ( $post_types as &$cpt ) {
			$slug  = $cpt['slug'];
			$count = wp_count_posts( $slug );
			$cpt['entry_count'] = $count ? (int) $count->publish + (int) $count->draft : 0;
		}

		$plugins      = get_option( 'active_plugins', [] );
		$tekton_plugins = array_filter( $plugins, fn( $p ) => str_starts_with( $p, 'tekton-' ) );

		foreach ( $structures as &$s ) {
			$full = $storage->get_structure( $s['template_key'] );
			$s['component_count'] = $full ? count( $full['components'] ?? [] ) : 0;
			$versions = $full ? $storage->get_versions( (int) $full['id'], 1 ) : [];
			$s['version']         = ! empty( $versions ) ? (int) $versions[0]['version_number'] : 1;
		}

		foreach ( $activity as &$a ) {
			$a['time'] = human_time_diff( strtotime( $a['time'] ), current_time( 'timestamp' ) ) . ' ago';
		}

		return new \WP_REST_Response( [
			'templates'    => $structures,
			'field_groups' => $field_groups,
			'post_types'   => $post_types,
			'activity'     => $activity,
			'plugins'      => [
				'count'  => count( $tekton_plugins ),
				'active' => count( $tekton_plugins ),
			],
		] );
	}

	public function handle_get_activity(): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage  = $this->core->get_module( 'storage' );
		$activity = $storage->get_recent_activity( 10 );

		foreach ( $activity as &$a ) {
			$a['time'] = human_time_diff( strtotime( $a['time'] ), current_time( 'timestamp' ) ) . ' ago';
		}

		return new \WP_REST_Response( $activity );
	}

	// ─── Theme ─────────────────────────────────────────────────────────

	public function handle_get_theme(): \WP_REST_Response {
		$theme = get_option( 'tekton_theme', null );
		if ( is_string( $theme ) ) {
			$theme = json_decode( $theme, true );
		}
		$onboarding_complete = (bool) get_option( 'tekton_onboarding_complete', false );

		return new \WP_REST_Response( [
			'theme'                => $theme,
			'onboarding_complete'  => $onboarding_complete,
		] );
	}

	public function handle_save_theme( \WP_REST_Request $request ): \WP_REST_Response {
		$theme = $request->get_json_params();

		if ( empty( $theme ) || ! is_array( $theme ) ) {
			return new \WP_REST_Response( [ 'message' => 'Invalid theme data.' ], 400 );
		}

		update_option( 'tekton_theme', wp_json_encode( $theme ) );

		$tokens = $this->derive_design_tokens( $theme );
		update_option( 'tekton_design_tokens', wp_json_encode( $tokens ) );

		update_option( 'tekton_onboarding_complete', true );

		/** @var Tekton_Context_Builder $context_builder */
		$context_builder = $this->core->get_module( 'context' );
		$context_builder->flush_cache();

		return new \WP_REST_Response( [
			'saved'         => true,
			'design_tokens' => $tokens,
		] );
	}

	public function handle_generate_theme( \WP_REST_Request $request ): void {
		$description = sanitize_text_field( $request->get_param( 'description' ) ?? '' );

		if ( '' === $description ) {
			$this->send_sse_error( 'Business description is required.' );
			return;
		}

		set_time_limit( 0 );
		@ini_set( 'max_execution_time', '0' );

		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'X-Accel-Buffering: no' );
		header( 'Connection: keep-alive' );
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		/** @var Tekton_AI_Engine $ai */
		$ai = $this->core->get_module( 'ai_engine' );

		$full_response = '';

		try {
			$generator = $ai->send_message( $description, [], [
				'type'    => 'generate_theme',
				'context' => [],
			] );

			foreach ( $generator as $chunk ) {
				$full_response .= $chunk;
				$this->send_sse_event( 'chunk', $chunk );
			}

			$parsed  = Tekton_AI_Engine::parse_response( $full_response );
			$message = $parsed['message'];
			$json    = $parsed['json'];

			if ( $json ) {
				$this->send_sse_event( 'complete', null, [
					'theme'   => $json,
					'message' => $message,
				] );
			} else {
				$this->send_sse_event( 'complete', null, [
					'message' => $message ?: 'Failed to generate theme. Please try again.',
				] );
			}
		} catch ( \Throwable $e ) {
			$this->send_sse_error( $e->getMessage() );
		}

		exit;
	}

	// ─── Helpers ────────────────────────────────────────────────────────

	/**
	 * Derive CSS custom property design tokens from a theme array.
	 *
	 * @param  array<string, mixed> $theme
	 * @return array<string, string>
	 */
	private function derive_design_tokens( array $theme ): array {
		$tokens = [];

		$categories = [ 'colors', 'fonts', 'typography', 'spacing', 'radii', 'shadows' ];

		foreach ( $categories as $category ) {
			if ( empty( $theme[ $category ] ) || ! is_array( $theme[ $category ] ) ) {
				continue;
			}
			$tokens[ $category ] = [];
			foreach ( $theme[ $category ] as $key => $value ) {
				$key = sanitize_key( str_replace( '_', '-', $key ) );
				if ( 'colors' === $category ) {
					$value = sanitize_hex_color( $value ) ?: sanitize_text_field( $value );
				} elseif ( 'fonts' === $category ) {
					$value = sanitize_text_field( $value );
					$fallback = ( 'mono' === $key ) ? 'monospace' : 'sans-serif';
					if ( ! str_contains( strtolower( $value ), $fallback ) ) {
						$value .= ', ' . $fallback;
					}
				} else {
					$value = sanitize_text_field( $value );
				}
				$tokens[ $category ][ $key ] = $value;
			}
		}

		if ( ! empty( $theme['colors']['primary'] ) ) {
			$tokens['colors']['primary-hover'] = $this->darken_hex_color( $theme['colors']['primary'], 15 );
		}

		return $tokens;
	}

	private function darken_hex_color( string $hex, int $percent ): string {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return "#{$hex}";
		}

		$r = max( 0, (int) round( hexdec( substr( $hex, 0, 2 ) ) * ( 1 - $percent / 100 ) ) );
		$g = max( 0, (int) round( hexdec( substr( $hex, 2, 2 ) ) * ( 1 - $percent / 100 ) ) );
		$b = max( 0, (int) round( hexdec( substr( $hex, 4, 2 ) ) * ( 1 - $percent / 100 ) ) );

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	private function send_sse_event( string $type, ?string $content = null, array $extra = [] ): void {
		$data = array_merge( [ 'type' => $type ], $extra );
		if ( null !== $content ) {
			$data['content'] = $content;
		}
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";
		if ( ob_get_level() ) {
			ob_flush();
		}
		flush();
	}

	private function send_sse_error( string $message ): void {
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		$this->send_sse_event( 'error', null, [ 'message' => $message ] );
		exit;
	}
}
