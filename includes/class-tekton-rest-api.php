<?php
declare(strict_types=1);
/**
 * REST API endpoints for the builder UI.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_REST_API {

	private Tekton_Core $core;

	public function __construct( Tekton_Core $core ) {
		$this->core = $core;
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		$ns = 'tekton/v1';

		// AI.
		register_rest_route( $ns, '/ai/generate', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_ai_generate' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		register_rest_route( $ns, '/ai/models', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_get_models' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		// Structures.
		register_rest_route( $ns, '/structures', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_list_structures' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_save_structure' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
		] );

		register_rest_route( $ns, '/structures/(?P<template_key>[a-zA-Z0-9_-]+)', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_get_structure' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'handle_delete_structure' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
		] );

		register_rest_route( $ns, '/structures/(?P<template_key>[a-zA-Z0-9_-]+)/versions', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_get_versions' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		register_rest_route( $ns, '/structures/(?P<template_key>[a-zA-Z0-9_-]+)/rollback', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_rollback' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		// Chat.
		register_rest_route( $ns, '/chat/(?P<template_key>[a-zA-Z0-9_-]+)', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_get_chat' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'handle_clear_chat' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
		] );

		// Context.
		register_rest_route( $ns, '/context', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_get_context' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		register_rest_route( $ns, '/context/refresh', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_refresh_context' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		// Settings.
		register_rest_route( $ns, '/settings', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_get_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_save_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
		] );

		// Dashboard.
		register_rest_route( $ns, '/dashboard', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_get_dashboard' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		// Field Groups.
		register_rest_route( $ns, '/field-groups', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_list_field_groups' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		// Post Types.
		register_rest_route( $ns, '/post-types', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_list_post_types' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		// Activity.
		register_rest_route( $ns, '/activity', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_get_activity' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		// Preview.
		register_rest_route( $ns, '/preview', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_preview' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );
	}

	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	// ─── AI ──────────────────────────────────────────────────────────────

	public function handle_ai_generate( \WP_REST_Request $request ): void {
		$prompt       = sanitize_text_field( $request->get_param( 'prompt' ) ?? '' );
		$template_key = sanitize_key( $request->get_param( 'template_key' ) ?? 'front-page' );
		$type         = sanitize_key( $request->get_param( 'type' ) ?? 'generate_page' );

		if ( '' === $prompt ) {
			$this->send_sse_error( 'Prompt is required.' );
			return;
		}

		// Set up SSE.
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'X-Accel-Buffering: no' );
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		/** @var Tekton_AI_Engine $ai */
		$ai = $this->core->get_module( 'ai_engine' );
		/** @var Tekton_Context_Builder $context_builder */
		$context_builder = $this->core->get_module( 'context' );

		$chat_history = $storage->get_chat_history( $template_key );
		$site_context = $context_builder->build();

		// Include the current template structure so the AI can modify it.
		$current_structure = $storage->get_structure( $template_key );
		if ( $current_structure && ! empty( $current_structure['components'] ) ) {
			$site_context['current_template'] = [
				'template_key' => $template_key,
				'components'   => $current_structure['components'],
				'styles'       => $current_structure['styles'] ?? [],
			];
		}

		$storage->add_chat_message( $template_key, 'user', $prompt );

		$full_response = '';

		try {
			$generator = $ai->send_message( $prompt, $chat_history, [
				'type'    => $type,
				'context' => $site_context,
			] );

			foreach ( $generator as $chunk ) {
				$full_response .= $chunk;
				$this->send_sse_event( 'chunk', $chunk );
			}

			// Parse the response into natural language message + structured JSON.
			$parsed_response = Tekton_AI_Engine::parse_response( $full_response );
			$message         = $parsed_response['message'];
			$json_data       = $parsed_response['json'];

			// Extract structure from JSON.
			$structure = null;
			if ( $json_data ) {
				$structure = $this->extract_structure( $json_data, $template_key );
			}

			// Store the natural language message in chat history (not the raw JSON).
			$storage->add_chat_message( $template_key, 'assistant', $message );

			if ( $structure ) {
				$storage->save_structure( $template_key, $structure );
				$this->send_sse_event( 'complete', null, [
					'structure' => $structure,
					'message'   => $message,
				] );
			} else {
				$this->send_sse_event( 'complete', null, [
					'message' => $message,
				] );
			}
		} catch ( \Throwable $e ) {
			$this->send_sse_error( $e->getMessage() );
		}

		exit;
	}

	public function handle_get_models( \WP_REST_Request $request ): \WP_REST_Response {
		$provider = sanitize_key( $request->get_param( 'provider' ) ?? '' );

		if ( '' === $provider ) {
			return new \WP_REST_Response( [ 'message' => 'Provider is required.' ], 400 );
		}

		/** @var Tekton_AI_Engine $ai */
		$ai     = $this->core->get_module( 'ai_engine' );
		$models = $ai->get_models_for_provider( $provider );

		return new \WP_REST_Response( $models );
	}

	// ─── Structures ─────────────────────────────────────────────────────

	public function handle_list_structures(): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		return new \WP_REST_Response( $storage->list_structures() );
	}

	public function handle_get_structure( \WP_REST_Request $request ): \WP_REST_Response {
		$key = sanitize_key( $request['template_key'] );
		/** @var Tekton_Storage $storage */
		$storage   = $this->core->get_module( 'storage' );
		$structure = $storage->get_structure( $key );

		if ( ! $structure ) {
			return new \WP_REST_Response( [ 'message' => 'Not found.' ], 404 );
		}

		return new \WP_REST_Response( $structure );
	}

	public function handle_save_structure( \WP_REST_Request $request ): \WP_REST_Response {
		$key = sanitize_key( $request->get_param( 'template_key' ) ?? '' );
		if ( '' === $key ) {
			return new \WP_REST_Response( [ 'message' => 'template_key is required.' ], 400 );
		}

		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );

		$data = [
			'title'      => sanitize_text_field( $request->get_param( 'title' ) ?? '' ),
			'components' => $request->get_param( 'components' ) ?? [],
			'styles'     => $request->get_param( 'styles' ) ?? [],
			'status'     => sanitize_key( $request->get_param( 'status' ) ?? 'draft' ),
		];

		$id = $storage->save_structure( $key, $data );

		return new \WP_REST_Response( [ 'id' => $id, 'template_key' => $key ] );
	}

	public function handle_delete_structure( \WP_REST_Request $request ): \WP_REST_Response {
		$key = sanitize_key( $request['template_key'] );

		// Prevent deletion of global templates.
		if ( in_array( $key, Tekton_Activator::GLOBAL_TEMPLATES, true ) ) {
			return new \WP_REST_Response( [ 'message' => 'Global templates cannot be deleted.' ], 403 );
		}

		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );

		if ( $storage->delete_structure( $key ) ) {
			return new \WP_REST_Response( [ 'deleted' => true ] );
		}

		return new \WP_REST_Response( [ 'message' => 'Not found.' ], 404 );
	}

	public function handle_get_versions( \WP_REST_Request $request ): \WP_REST_Response {
		$key = sanitize_key( $request['template_key'] );
		/** @var Tekton_Storage $storage */
		$storage   = $this->core->get_module( 'storage' );
		$structure = $storage->get_structure( $key );

		if ( ! $structure ) {
			return new \WP_REST_Response( [ 'message' => 'Not found.' ], 404 );
		}

		return new \WP_REST_Response( $storage->get_versions( (int) $structure['id'] ) );
	}

	public function handle_rollback( \WP_REST_Request $request ): \WP_REST_Response {
		$key     = sanitize_key( $request['template_key'] );
		$version = (int) ( $request->get_param( 'version_number' ) ?? 0 );
		/** @var Tekton_Storage $storage */
		$storage   = $this->core->get_module( 'storage' );
		$structure = $storage->get_structure( $key );

		if ( ! $structure ) {
			return new \WP_REST_Response( [ 'message' => 'Not found.' ], 404 );
		}

		if ( $storage->rollback( (int) $structure['id'], $version ) ) {
			return new \WP_REST_Response( [ 'success' => true ] );
		}

		return new \WP_REST_Response( [ 'message' => 'Version not found.' ], 404 );
	}

	// ─── Chat ───────────────────────────────────────────────────────────

	public function handle_get_chat( \WP_REST_Request $request ): \WP_REST_Response {
		$key = sanitize_key( $request['template_key'] );
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		return new \WP_REST_Response( $storage->get_chat_history( $key ) );
	}

	public function handle_clear_chat( \WP_REST_Request $request ): \WP_REST_Response {
		$key = sanitize_key( $request['template_key'] );
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		$storage->clear_chat_history( $key );
		return new \WP_REST_Response( [ 'cleared' => true ] );
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

		// Add masked API keys.
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

	// ─── Preview ────────────────────────────────────────────────────────

	public function handle_preview( \WP_REST_Request $request ): \WP_REST_Response {
		$components   = $request->get_param( 'components' ) ?? [];
		$template_key = sanitize_key( $request->get_param( 'template_key' ) ?? 'preview' );

		/** @var Tekton_Renderer $renderer */
		$renderer = $this->core->get_module( 'renderer' );
		/** @var Tekton_Assets $assets */
		$assets = $this->core->get_module( 'assets' );
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );

		$structure = [
			'template_key' => $template_key,
			'components'   => $components,
		];

		$tokens_css = $assets->get_design_tokens_css();
		$reset_url  = esc_url( TEKTON_URL . 'assets/css/tekton-frontend-reset.css?v=' . TEKTON_VERSION );

		$html = '<!DOCTYPE html><html><head>';
		$html .= '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		$html .= '<link rel="stylesheet" href="' . $reset_url . '">';
		$html .= '<style>:root{' . "\n" . $tokens_css . '}</style>';
		$html .= '</head><body class="tekton-preview">';

		// Render header (unless we're previewing the header itself).
		if ( 'header' !== $template_key ) {
			$header_html = $this->render_global_template( $storage, $renderer, 'header' );
			if ( $header_html ) {
				$html .= $header_html;
			}
		}

		$html .= $renderer->render_page( $structure );

		// Render footer (unless we're previewing the footer itself).
		if ( 'footer' !== $template_key ) {
			$footer_html = $this->render_global_template( $storage, $renderer, 'footer' );
			if ( $footer_html ) {
				$html .= $footer_html;
			}
		}

		$html .= '</body></html>';

		return new \WP_REST_Response( [ 'html' => $html ] );
	}

	/**
	 * Render a global template (header/footer) if it has components.
	 */
	private function render_global_template( Tekton_Storage $storage, Tekton_Renderer $renderer, string $key ): string {
		$structure = $storage->get_structure( $key );
		if ( ! $structure || empty( $structure['components'] ) ) {
			return '';
		}
		return $renderer->render_page( $structure );
	}

	// ─── Dashboard ─────────────────────────────────────────────────────

	public function handle_get_dashboard(): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );

		$structures   = $storage->list_structures();
		$field_groups = $storage->list_field_groups();
		$post_types   = $storage->list_post_types();
		$activity     = $storage->get_recent_activity( 10 );

		// Count post type entries.
		foreach ( $post_types as &$cpt ) {
			$slug  = $cpt['slug'];
			$count = wp_count_posts( $slug );
			$cpt['entry_count'] = $count ? (int) $count->publish + (int) $count->draft : 0;
		}

		// Count generated micro-plugins.
		$plugins      = get_option( 'active_plugins', [] );
		$tekton_plugins = array_filter( $plugins, fn( $p ) => str_starts_with( $p, 'tekton-' ) );

		// Add component counts and version info to structures.
		foreach ( $structures as &$s ) {
			$full = $storage->get_structure( $s['template_key'] );
			$s['component_count'] = $full ? count( $full['components'] ?? [] ) : 0;
			$versions = $full ? $storage->get_versions( (int) $full['id'], 1 ) : [];
			$s['version']         = ! empty( $versions ) ? (int) $versions[0]['version_number'] : 1;
		}

		// Format activity times as relative.
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

	public function handle_list_field_groups(): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		return new \WP_REST_Response( $storage->list_field_groups() );
	}

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

	public function handle_get_activity(): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage  = $this->core->get_module( 'storage' );
		$activity = $storage->get_recent_activity( 10 );

		foreach ( $activity as &$a ) {
			$a['time'] = human_time_diff( strtotime( $a['time'] ), current_time( 'timestamp' ) ) . ' ago';
		}

		return new \WP_REST_Response( $activity );
	}

	// ─── Helpers ────────────────────────────────────────────────────────

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

	/**
	 * Extract a renderable structure from parsed JSON data.
	 * Supports both full component trees and granular operations.
	 */
	private function extract_structure( array $json, string $template_key ): ?array {
		// Operations mode — apply patches to existing structure.
		if ( ! empty( $json['operations'] ) ) {
			/** @var Tekton_Storage $storage */
			$storage   = $this->core->get_module( 'storage' );
			$existing  = $storage->get_structure( $template_key );

			if ( ! $existing || empty( $existing['components'] ) ) {
				return null;
			}

			$patched = Tekton_Structure_Patcher::apply( $existing, $json['operations'] );
			$patched['template_key'] = $template_key;

			// Allow the AI to update the title via operations.
			if ( ! empty( $json['title'] ) ) {
				$patched['title'] = $json['title'];
			}

			return $patched;
		}

		// Standard page response — full component tree.
		if ( ! empty( $json['components'] ) ) {
			$json['template_key'] = $template_key;
			return $json;
		}

		// Fullstack response.
		if ( ! empty( $json['structure']['components'] ) ) {
			$json['structure']['template_key'] = $template_key;
			return $json['structure'];
		}

		return null;
	}
}
