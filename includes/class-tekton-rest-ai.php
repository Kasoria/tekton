<?php
declare(strict_types=1);
/**
 * REST API controller — AI generation and models.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_REST_AI {

	private Tekton_Core $core;
	private ?array $updated_design_tokens = null;

	public function __construct( Tekton_Core $core ) {
		$this->core = $core;
	}

	public function register_routes( string $ns ): void {
		register_rest_route( $ns, '/ai/generate', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_ai_generate' ],
			'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
			'args'                => [
				'images' => [
					'type'              => 'array',
					'default'           => [],
					'validate_callback' => function ( $val ): bool { return is_array( $val ); },
					'sanitize_callback' => function ( $val ): array {
						return is_array( $val ) ? array_values( array_filter( $val, 'is_array' ) ) : [];
					},
				],
			],
		] );

		register_rest_route( $ns, '/ai/models', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_get_models' ],
			'permission_callback' => [ Tekton_REST_API::class, 'check_permission' ],
		] );
	}

	public function handle_ai_generate( \WP_REST_Request $request ): void {
		$this->updated_design_tokens = null;
		$prompt       = sanitize_text_field( $request->get_param( 'prompt' ) ?? '' );
		$template_key = sanitize_key( $request->get_param( 'template_key' ) ?? 'front-page' );
		$type         = sanitize_key( $request->get_param( 'type' ) ?? 'generate_page' );
		$raw_images   = $request->get_param( 'images' ) ?? [];

		if ( '' === $prompt ) {
			$this->send_sse_error( 'Prompt is required.' );
			return;
		}

		// Allow long-running streaming requests.
		set_time_limit( 0 );
		@ini_set( 'max_execution_time', '0' );

		// Set up SSE.
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'X-Accel-Buffering: no' );
		header( 'Connection: keep-alive' );
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

		// Sanitize images: validate base64, restrict MIME types, verify magic bytes.
		$images        = [];
		$allowed_mimes = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
		$magic_bytes   = [
			'image/jpeg' => [ "\xFF\xD8\xFF" ],
			'image/png'  => [ "\x89\x50\x4E\x47" ],
			'image/gif'  => [ "GIF87a", "GIF89a" ],
			'image/webp' => [ "RIFF" ],
		];
		foreach ( $raw_images as $img ) {
			if ( empty( $img['data'] ) ) {
				continue;
			}
			$mime = $img['media_type'] ?? 'image/png';
			if ( ! in_array( $mime, $allowed_mimes, true ) ) {
				continue;
			}
			// Decode once and validate.
			$bytes = base64_decode( $img['data'], true );
			if ( false === $bytes ) {
				continue;
			}
			// Verify magic bytes match claimed MIME type.
			$valid_magic = false;
			if ( isset( $magic_bytes[ $mime ] ) ) {
				foreach ( $magic_bytes[ $mime ] as $magic ) {
					if ( str_starts_with( $bytes, $magic ) ) {
						$valid_magic = true;
						break;
					}
				}
				// WebP needs additional WEBP signature at offset 8.
				if ( 'image/webp' === $mime && $valid_magic ) {
					$valid_magic = strlen( $bytes ) >= 12 && 'WEBP' === substr( $bytes, 8, 4 );
				}
			}
			if ( ! $valid_magic ) {
				continue;
			}
			$images[] = [
				'media_type' => $mime,
				'data'       => $img['data'],
				'bytes'      => $bytes,
			];
		}

		// Save images to uploads and store URLs in chat metadata.
		$image_urls = [];
		if ( ! empty( $images ) ) {
			$upload_dir = wp_upload_dir();
			$chat_dir   = $upload_dir['basedir'] . '/tekton/chat';
			if ( ! is_dir( $chat_dir ) ) {
				wp_mkdir_p( $chat_dir );
			}

			$mime_ext = [
				'image/jpeg' => 'jpg',
				'image/png'  => 'png',
				'image/gif'  => 'gif',
				'image/webp' => 'webp',
			];

			foreach ( $images as $img ) {
				$ext      = $mime_ext[ $img['media_type'] ] ?? 'png';
				$filename = wp_unique_filename( $chat_dir, 'img-' . wp_generate_password( 8, false ) . '.' . $ext );
				$filepath = $chat_dir . '/' . $filename;

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				if ( file_put_contents( $filepath, $img['bytes'] ) ) {
					$image_urls[] = $upload_dir['baseurl'] . '/tekton/chat/' . $filename;
				}
			}
		}

		// Strip decoded bytes before passing to AI engine (only media_type + data needed).
		foreach ( $images as &$img_ref ) {
			unset( $img_ref['bytes'] );
		}
		unset( $img_ref );

		$chat_metadata = ! empty( $image_urls ) ? [ 'images' => $image_urls ] : null;
		$storage->add_chat_message( $template_key, 'user', $prompt, $chat_metadata );

		$full_response    = '';
		$max_continuations = 4;

		try {
			// Initial generation.
			$generator = $ai->send_message( $prompt, $chat_history, [
				'type'    => $type,
				'context' => $site_context,
				'images'  => $images,
			] );

			foreach ( $generator as $chunk ) {
				$full_response .= $chunk;
				$this->send_sse_event( 'chunk', $chunk );
			}

			// Attempt continuations if the response looks like truncated JSON.
			for ( $cont = 0; $cont < $max_continuations; $cont++ ) {
				$parsed = Tekton_AI_Engine::parse_response( $full_response );
				if ( $parsed['json'] ) {
					break; // Valid JSON found — done.
				}
				if ( ! $this->looks_like_truncated_json( $full_response ) ) {
					break; // Not truncated JSON — it's just a text response.
				}

				// Send a continuation request.
				$continuation_history   = $chat_history;
				$continuation_history[] = [ 'role' => 'user', 'content' => $prompt ];
				$continuation_history[] = [ 'role' => 'assistant', 'content' => $full_response ];

				$cont_generator = $ai->send_message(
					'Your previous response was cut off mid-JSON. Continue EXACTLY from where you stopped. Do not repeat any content. Do not add any preamble or explanation — output ONLY the remaining JSON to complete the structure.',
					$continuation_history,
					[ 'type' => $type, 'context' => $site_context ]
				);

				foreach ( $cont_generator as $chunk ) {
					$full_response .= $chunk;
					$this->send_sse_event( 'chunk', $chunk );
				}
			}

			// Parse the final accumulated response.
			$parsed_response = Tekton_AI_Engine::parse_response( $full_response );
			$message         = $parsed_response['message'];
			$json_data       = $parsed_response['json'];

			// Extract structure from JSON.
			$structure = null;
			if ( $json_data ) {
				$structure = $this->extract_structure( $json_data, $template_key );

				// If data model was created (posts, CPTs, fields) but no template
				// change, reload the current structure so the preview re-renders
				// with the new post data resolved.
				if ( ! $structure && $current_structure ) {
					$has_data_keys = ! empty( $json_data['posts'] )
						|| ! empty( $json_data['postTypes'] )
						|| ! empty( $json_data['fieldGroups'] )
						|| ! empty( $json_data['optionsPages'] );
					if ( $has_data_keys ) {
						$structure = $storage->get_structure( $template_key );
					}
				}
			}

			// Store the natural language message in chat history (not the raw JSON).
			$assistant_meta = $structure ? [ 'has_structure' => true ] : null;
			$storage->add_chat_message( $template_key, 'assistant', $message, $assistant_meta );

			if ( $structure ) {
				if ( ! empty( $json_data['operations'] ) || ! empty( $json_data['components'] ) || ! empty( $json_data['structure'] ) ) {
					$storage->save_structure( $template_key, $structure );
				}
				$complete_data = [
					'structure' => $structure,
					'message'   => $message,
				];
				if ( $this->updated_design_tokens ) {
					$complete_data['design_tokens'] = $this->updated_design_tokens;
				}
				$this->send_sse_event( 'complete', null, $complete_data );
			} else {
				$complete_data = [ 'message' => $message ];
				if ( $this->updated_design_tokens ) {
					$complete_data['design_tokens'] = $this->updated_design_tokens;
				}
				$this->send_sse_event( 'complete', null, $complete_data );
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

	// ─── SSE Helpers ────────────────────────────────────────────────────

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
	 * Detect if a response looks like truncated JSON.
	 */
	private function looks_like_truncated_json( string $response ): bool {
		$trimmed = trim( $response );

		$fence_count = substr_count( $response, '```' );
		if ( $fence_count % 2 !== 0 ) {
			$last_fence = strrpos( $response, '```' );
			$after      = trim( substr( $response, $last_fence + 3 ) );
			if ( str_contains( $after, '"operations"' ) || str_contains( $after, '"components"' ) || str_contains( $after, '"op"' ) ) {
				return true;
			}
		}

		if ( str_contains( $trimmed, '"operations"' ) || str_contains( $trimmed, '"components"' ) || str_contains( $trimmed, '"op"' ) ) {
			$opens  = substr_count( $trimmed, '{' ) + substr_count( $trimmed, '[' );
			$closes = substr_count( $trimmed, '}' ) + substr_count( $trimmed, ']' );
			if ( $opens > $closes ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract a renderable structure from parsed JSON data.
	 */
	private function extract_structure( array $json, string $template_key ): ?array {
		// Always process data model (CPTs, field groups, posts) regardless of response mode.
		$data_created = $this->process_fullstack_data( $json );

		// Process design token updates from operations or top-level key.
		$this->extract_and_apply_design_tokens( $json );

		if ( ! empty( $json['operations'] ) ) {
			// Filter out set_design_tokens ops (already handled above).
			$structure_ops = array_values( array_filter(
				$json['operations'],
				fn( $op ) => ( $op['op'] ?? '' ) !== 'set_design_tokens'
			) );

			// If only design token ops remain, no structure change needed.
			if ( empty( $structure_ops ) ) {
				return null;
			}

			/** @var Tekton_Storage $storage */
			$storage   = $this->core->get_module( 'storage' );
			$existing  = $storage->get_structure( $template_key );

			if ( ! $existing || empty( $existing['components'] ) ) {
				return null;
			}

			$patched = Tekton_Structure_Patcher::apply( $existing, $structure_ops );
			$patched['template_key'] = $template_key;

			if ( ! empty( $json['title'] ) ) {
				$patched['title'] = $json['title'];
			}

			return $patched;
		}

		if ( ! empty( $json['components'] ) ) {
			$json['template_key'] = $template_key;
			return $this->merge_existing_metadata( $json, $template_key );
		}

		if ( ! empty( $json['structure']['components'] ) ) {
			$json['structure']['template_key'] = $template_key;
			return $this->merge_existing_metadata( $json['structure'], $template_key );
		}

		return null;
	}

	/**
	 * Process post types, field groups, and posts from a fullstack AI response.
	 */
	/**
	 * @return bool Whether any data model changes were made.
	 */
	private function process_fullstack_data( array $json ): bool {
		$has_data = ! empty( $json['postTypes'] ) || ! empty( $json['fieldGroups'] ) || ! empty( $json['posts'] ) || ! empty( $json['optionsPages'] );
		if ( ! $has_data ) {
			return false;
		}

		/** @var Tekton_Storage $storage */
		$storage = $this->core->get_module( 'storage' );

		if ( ! empty( $json['postTypes'] ) && is_array( $json['postTypes'] ) ) {
			foreach ( $json['postTypes'] as $pt ) {
				if ( ! empty( $pt['slug'] ) ) {
					$storage->save_post_type( $pt );
				}
			}
			delete_transient( 'tekton_cpt_hash' );

			// Register CPTs immediately so wp_insert_post works for them in this request.
			$cpt_manager = $this->core->get_module( 'cpt_manager' );
			if ( $cpt_manager instanceof Tekton_CPT_Manager ) {
				$cpt_manager->register_all();
			}
		}

		if ( ! empty( $json['fieldGroups'] ) && is_array( $json['fieldGroups'] ) ) {
			foreach ( $json['fieldGroups'] as $fg ) {
				if ( ! empty( $fg['slug'] ) ) {
					$storage->save_field_group( $fg );
				}
			}
		}

		if ( ! empty( $json['optionsPages'] ) && is_array( $json['optionsPages'] ) ) {
			foreach ( $json['optionsPages'] as $op ) {
				if ( ! empty( $op['slug'] ) ) {
					$storage->save_options_page( $op );
				}
			}
		}

		if ( ! empty( $json['posts'] ) && is_array( $json['posts'] ) ) {
			$this->process_posts_data( $json['posts'] );
		}

		$this->core->get_module( 'context' )->flush_cache();
		return true;
	}

	/**
	 * Extract design token updates from AI response and apply them.
	 *
	 * Supports both:
	 *   - Operations: {"operations": [{"op": "set_design_tokens", "design_tokens": {...}}]}
	 *   - Top-level: {"design_tokens": {...}}
	 */
	private function extract_and_apply_design_tokens( array $json ): void {
		$token_updates = null;

		// Check for set_design_tokens inside operations array.
		if ( ! empty( $json['operations'] ) && is_array( $json['operations'] ) ) {
			foreach ( $json['operations'] as $op ) {
				if ( ( $op['op'] ?? '' ) === 'set_design_tokens' && ! empty( $op['design_tokens'] ) ) {
					$token_updates = $op['design_tokens'];
					break;
				}
			}
		}

		// Also check top-level design_tokens key (fallback).
		if ( ! $token_updates && ! empty( $json['design_tokens'] ) ) {
			$token_updates = $json['design_tokens'];
		}

		if ( ! $token_updates || ! is_array( $token_updates ) ) {
			return;
		}

		$this->apply_design_token_update( $token_updates );
	}

	/**
	 * Merge design token changes into the existing theme, derive CSS tokens, and persist.
	 */
	private function apply_design_token_update( array $updates ): void {
		$theme_raw = get_option( 'tekton_theme', null );
		if ( is_string( $theme_raw ) ) {
			$theme = json_decode( $theme_raw, true ) ?? [];
		} else {
			$theme = is_array( $theme_raw ) ? $theme_raw : [];
		}

		// Top-level string fields.
		$top_level_keys = [ 'name', 'description', 'style_notes' ];
		foreach ( $top_level_keys as $key ) {
			if ( isset( $updates[ $key ] ) && is_string( $updates[ $key ] ) ) {
				$theme[ $key ] = sanitize_text_field( $updates[ $key ] );
			}
		}

		// Category-level merge (colors, fonts, typography, spacing, radii, shadows).
		$categories = [ 'colors', 'fonts', 'typography', 'spacing', 'radii', 'shadows' ];
		foreach ( $categories as $category ) {
			if ( ! empty( $updates[ $category ] ) && is_array( $updates[ $category ] ) ) {
				if ( ! isset( $theme[ $category ] ) || ! is_array( $theme[ $category ] ) ) {
					$theme[ $category ] = [];
				}
				foreach ( $updates[ $category ] as $key => $value ) {
					$theme[ $category ][ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
				}
			}
		}

		// Save updated theme.
		update_option( 'tekton_theme', wp_json_encode( $theme ) );

		// Derive CSS tokens and save.
		$tokens = Tekton_REST_Settings::derive_design_tokens( $theme );
		update_option( 'tekton_design_tokens', wp_json_encode( $tokens ) );

		// Flush context cache so future AI calls see the updated theme.
		$this->core->get_module( 'context' )->flush_cache();

		// Store for inclusion in SSE response.
		$this->updated_design_tokens = $tokens;
	}

	/**
	 * Insert posts with custom field values from a fullstack AI response.
	 */
	private function process_posts_data( array $posts ): void {
		foreach ( $posts as $post_data ) {
			$post_type = sanitize_key( $post_data['post_type'] ?? 'post' );
			$title     = sanitize_text_field( $post_data['title'] ?? '' );

			if ( '' === $title ) {
				continue;
			}

			// Skip if a post with this title already exists in this post type.
			$existing = get_posts( [
				'post_type'   => $post_type,
				'title'       => $title,
				'post_status' => 'any',
				'numberposts' => 1,
			] );

			if ( ! empty( $existing ) ) {
				$post_id = $existing[0]->ID;
			} else {
				$post_id = wp_insert_post( [
					'post_title'   => $title,
					'post_content' => wp_kses_post( $post_data['content'] ?? '' ),
					'post_status'  => 'publish',
					'post_type'    => $post_type,
				] );

				if ( is_wp_error( $post_id ) || 0 === $post_id ) {
					continue;
				}
			}

			// Set Tekton custom field values.
			if ( ! empty( $post_data['meta'] ) && is_array( $post_data['meta'] ) ) {
				foreach ( $post_data['meta'] as $group_slug => $fields ) {
					if ( ! is_array( $fields ) ) {
						continue;
					}
					$safe_group = sanitize_key( $group_slug );
					foreach ( $fields as $field_name => $value ) {
						$meta_key = '_tekton_' . $safe_group . '_' . sanitize_key( $field_name );
						update_post_meta( $post_id, $meta_key, sanitize_text_field( (string) $value ) );
					}
				}
			}
		}
	}

	/**
	 * Merge scripts, keyframes, wrapper_styles, and meta from the existing
	 * structure with a new AI response so nothing is silently wiped.
	 */
	private function merge_existing_metadata( array $new, string $template_key ): array {
		/** @var Tekton_Storage $storage */
		$storage  = $this->core->get_module( 'storage' );
		$existing = $storage->get_structure( $template_key );

		if ( ! $existing ) {
			return $new;
		}

		// Preserve if completely absent in new response.
		$preserve_if_empty = [ 'wrapper_styles', 'meta' ];
		foreach ( $preserve_if_empty as $key ) {
			if ( empty( $new[ $key ] ) && ! empty( $existing[ $key ] ) ) {
				$new[ $key ] = $existing[ $key ];
			}
		}

		// Scripts: merge arrays, skip exact duplicates.
		if ( ! empty( $existing['scripts'] ) ) {
			$merged_scripts = $existing['scripts'];
			foreach ( $new['scripts'] ?? [] as $script ) {
				if ( ! in_array( $script, $merged_scripts, true ) ) {
					$merged_scripts[] = $script;
				}
			}
			$new['scripts'] = $merged_scripts;
		}

		// Keyframes: merge by name (new definitions override existing for same name).
		if ( ! empty( $existing['keyframes'] ) ) {
			$new['keyframes'] = array_merge( $existing['keyframes'], $new['keyframes'] ?? [] );
		}

		return $new;
	}
}
