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

		if ( ! empty( $json['operations'] ) ) {
			/** @var Tekton_Storage $storage */
			$storage   = $this->core->get_module( 'storage' );
			$existing  = $storage->get_structure( $template_key );

			if ( ! $existing || empty( $existing['components'] ) ) {
				return null;
			}

			$patched = Tekton_Structure_Patcher::apply( $existing, $json['operations'] );
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
