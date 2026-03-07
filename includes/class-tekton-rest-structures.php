<?php
declare(strict_types=1);
/**
 * REST API controller — structures, versions, chat, preview.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_REST_Structures {

	private Tekton_Core $core;

	public function __construct( Tekton_Core $core ) {
		$this->core = $core;
	}

	public function register_routes( string $ns ): void {
		register_rest_route( $ns, '/structures', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_list_structures' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_save_structure' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
				'args'                => [
					'template_key' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
					'title' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'components' => [
						'type'              => 'array',
						'default'           => [],
						'validate_callback' => function ( $val ): bool { return is_array( $val ); },
						'sanitize_callback' => function ( $val ) { return $val; },
					],
					'styles' => [
						'type'    => 'array',
						'default' => [],
						'validate_callback' => function ( $val ): bool { return is_array( $val ); },
						'sanitize_callback' => function ( $val ) { return $val; },
					],
					'keyframes' => [
						'type'    => 'array',
						'default' => [],
						'validate_callback' => function ( $val ): bool { return is_array( $val ); },
						'sanitize_callback' => function ( $val ) { return $val; },
					],
					'scripts' => [
						'type'    => 'array',
						'default' => [],
						'validate_callback' => function ( $val ): bool { return is_array( $val ); },
						'sanitize_callback' => function ( $val ) { return $val; },
					],
					'status' => [
						'type'              => 'string',
						'default'           => 'draft',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => function ( $val ): bool {
							return in_array( $val, [ 'draft', 'publish' ], true );
						},
					],
					'wrapper_styles' => [
						'type'    => 'array',
						'default' => [],
						'validate_callback' => function ( $val ): bool { return is_array( $val ); },
						'sanitize_callback' => function ( $val ) { return $val; },
					],
					'change_type' => [
						'type'              => 'string',
						'default'           => 'ai_generate',
						'sanitize_callback' => 'sanitize_key',
					],
					'change_summary' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
		] );

		register_rest_route( $ns, '/structures/(?P<template_key>[a-zA-Z0-9_-]+)', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_get_structure' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'handle_delete_structure' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
		] );

		register_rest_route( $ns, '/structures/(?P<template_key>[a-zA-Z0-9_-]+)/versions', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_get_versions' ],
			'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
		] );

		register_rest_route( $ns, '/structures/(?P<template_key>[a-zA-Z0-9_-]+)/rollback', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_rollback' ],
			'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
		] );

		register_rest_route( $ns, '/structures/(?P<template_key>[a-zA-Z0-9_-]+)/versions/(?P<version_number>\d+)/rename', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_rename_version' ],
			'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
		] );

		// Chat.
		register_rest_route( $ns, '/chat/(?P<template_key>[a-zA-Z0-9_-]+)', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_get_chat' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'handle_clear_chat' ],
				'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			],
		] );

		register_rest_route( $ns, '/chat/(?P<template_key>[a-zA-Z0-9_-]+)/summarize-clear', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_summarize_and_clear_chat' ],
			'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
		] );

		// Preview.
		register_rest_route( $ns, '/preview', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_preview' ],
			'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
		] );
	}

	// ─── Structures ─────────────────────────────────────────────────────

	public function handle_list_structures(): \WP_REST_Response {
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		/** @var Tekton_Publisher $publisher */
		$publisher = $this->core->get_module( 'publisher' );

		$structures = $storage->list_structures();
		foreach ( $structures as &$s ) {
			$key = $s['template_key'];
			$s['url']         = $publisher->get_url( $key );
			$s['preview_url'] = $publisher->get_preview_url( $key );
			$s['wp_status']   = $publisher->get_post_status( $key );
		}

		return new \WP_REST_Response( $structures );
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
		/** @var Tekton_Publisher $publisher */
		$publisher = $this->core->get_module( 'publisher' );

		$title  = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
		$status = sanitize_key( $request->get_param( 'status' ) ?? 'draft' );

		$data = [
			'title'          => $title,
			'components'     => $request->get_param( 'components' ) ?? [],
			'styles'         => $request->get_param( 'styles' ) ?? [],
			'keyframes'      => $request->get_param( 'keyframes' ) ?? [],
			'scripts'        => $request->get_param( 'scripts' ) ?? [],
			'wrapper_styles' => $request->get_param( 'wrapper_styles' ) ?? [],
			'status'         => $status,
			'change_type'    => sanitize_key( $request->get_param( 'change_type' ) ?? 'ai_generate' ),
			'change_summary' => sanitize_text_field( $request->get_param( 'change_summary' ) ?? '' ),
		];

		// Validate structure before saving.
		/** @var Tekton_Schema $schema */
		$schema     = $this->core->get_module( 'schema' );
		$validation = $schema->validate_structure( $data );
		if ( ! $validation['valid'] ) {
			return new \WP_REST_Response( [
				'message' => 'Structure validation failed.',
				'errors'  => $validation['errors'],
			], 400 );
		}

		$id = $storage->save_structure( $key, $data );

		// Create/update the corresponding WordPress page.
		$page_url = $publisher->publish( $key, $title, $status );

		$response = [ 'id' => $id, 'template_key' => $key, 'status' => $status ];
		if ( $page_url ) {
			$response['url'] = $page_url;
		}
		$preview_url = $publisher->get_preview_url( $key );
		if ( $preview_url ) {
			$response['preview_url'] = $preview_url;
		}

		return new \WP_REST_Response( $response );
	}

	public function handle_delete_structure( \WP_REST_Request $request ): \WP_REST_Response {
		$key = sanitize_key( $request['template_key'] );

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

	public function handle_rename_version( \WP_REST_Request $request ): \WP_REST_Response {
		$key     = sanitize_key( $request['template_key'] );
		$version = (int) $request['version_number'];
		$label   = sanitize_text_field( $request->get_param( 'label' ) ?? '' );

		/** @var Tekton_Storage $storage */
		$storage   = $this->core->get_module( 'storage' );
		$structure = $storage->get_structure( $key );

		if ( ! $structure ) {
			return new \WP_REST_Response( [ 'message' => 'Not found.' ], 404 );
		}

		$storage->rename_version( (int) $structure['id'], $version, $label );

		return new \WP_REST_Response( [ 'success' => true ] );
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

	public function handle_summarize_and_clear_chat( \WP_REST_Request $request ): \WP_REST_Response {
		$key = sanitize_key( $request['template_key'] );

		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );
		/** @var Tekton_AI_Engine $ai */
		$ai = $this->core->get_module( 'ai_engine' );

		$history = $storage->get_chat_history( $key );

		if ( empty( $history ) ) {
			return new \WP_REST_Response( [ 'cleared' => true ] );
		}

		$transcript = '';
		foreach ( $history as $msg ) {
			$role = 'user' === $msg['role'] ? 'User' : 'AI';
			$transcript .= "{$role}: {$msg['content']}\n";
		}

		$summary = '';
		try {
			$provider = $ai->get_provider();
			$generator = $provider->send_streaming(
				'You are a concise summarizer. Summarize the following conversation in 2-3 short sentences. Focus on what was built, changed, or decided. Do not use markdown. Just plain text.',
				[
					[
						'role'    => 'user',
						'content' => "Summarize this conversation:\n\n{$transcript}",
					],
				],
				[
					'model'      => get_option( 'tekton_ai_model', '' ),
					'max_tokens' => 200,
				]
			);

			foreach ( $generator as $chunk ) {
				$summary .= $chunk;
			}
		} catch ( \Throwable $e ) {
			$summary = 'Previous conversation cleared. (Summary generation failed.)';
		}

		$storage->clear_chat_history( $key );
		$storage->add_chat_message( $key, 'assistant', trim( $summary ), [ 'is_summary' => true ] );

		return new \WP_REST_Response( [ 'cleared' => true, 'summary' => trim( $summary ) ] );
	}

	// ─── Preview ────────────────────────────────────────────────────────

	public function handle_preview( \WP_REST_Request $request ): \WP_REST_Response {
		$components   = $request->get_param( 'components' ) ?? [];
		$keyframes    = $request->get_param( 'keyframes' ) ?? [];
		$scripts      = $request->get_param( 'scripts' ) ?? [];
		$template_key = sanitize_key( $request->get_param( 'template_key' ) ?? 'preview' );

		/** @var Tekton_Renderer $renderer */
		$renderer = $this->core->get_module( 'renderer' );
		/** @var Tekton_Assets $assets */
		$assets = $this->core->get_module( 'assets' );
		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );

		$wrapper_styles = $request->get_param( 'wrapper_styles' ) ?? [];

		$structure = [
			'template_key'   => $template_key,
			'components'     => $components,
			'keyframes'      => $keyframes,
			'scripts'        => $scripts,
			'wrapper_styles' => $wrapper_styles,
		];

		$tokens_css = $assets->get_design_tokens_css();
		$reset_url  = esc_url( TEKTON_URL . 'assets/css/tekton-frontend-reset.css?v=' . TEKTON_VERSION );

		$google_fonts_url = $assets->get_google_fonts_url();

		$html = '<!DOCTYPE html><html><head>';
		$html .= '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		if ( $google_fonts_url ) {
			$html .= '<link rel="preconnect" href="https://fonts.googleapis.com">';
			$html .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
			$html .= '<link rel="stylesheet" href="' . esc_url( $google_fonts_url ) . '">';
		}
		$html .= '<link rel="stylesheet" href="' . $reset_url . '">';
		$html .= '<style>:root{' . "\n" . $tokens_css . '}</style>';
		$html .= '</head><body class="tekton-preview">';
		$html .= '<div id="tekton-site">';

		if ( 'header' !== $template_key ) {
			$header_html = $this->render_global_template( $storage, $renderer, 'header' );
			if ( $header_html ) {
				$html .= $header_html;
			}
		}

		$main_tag = ( 'header' === $template_key || 'footer' === $template_key ) ? $template_key : 'main';
		$html .= $renderer->render_page( $structure, 0, $main_tag );

		if ( 'footer' !== $template_key ) {
			$footer_html = $this->render_global_template( $storage, $renderer, 'footer' );
			if ( $footer_html ) {
				$html .= $footer_html;
			}
		}

		$html .= '</div>';

		// Inject inline editor bridge script for live preview interaction.
		$bridge_url = esc_url( TEKTON_URL . 'assets/js/tekton-preview-bridge.js?v=' . TEKTON_VERSION );
		$html .= '<script src="' . $bridge_url . '"></script>';

		$html .= '</body></html>';

		return new \WP_REST_Response( [ 'html' => $html ] );
	}

	private function render_global_template( Tekton_Storage $storage, Tekton_Renderer $renderer, string $key ): string {
		$structure = $storage->get_structure( $key );
		if ( ! $structure || empty( $structure['components'] ) ) {
			return '';
		}
		$tag = in_array( $key, [ 'header', 'footer' ], true ) ? $key : 'div';
		return $renderer->render_page( $structure, 0, $tag );
	}
}
