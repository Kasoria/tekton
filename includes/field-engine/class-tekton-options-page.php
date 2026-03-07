<?php
declare(strict_types=1);
/**
 * Options pages — registers admin pages for global settings fields.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Options_Page {

	private Tekton_Storage        $storage;
	private Tekton_Field_Registry $registry;

	public function __construct( Tekton_Storage $storage, Tekton_Field_Registry $registry ) {
		$this->storage  = $storage;
		$this->registry = $registry;
	}

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_pages' ], 20 );
		add_action( 'admin_init', [ $this, 'handle_save' ] );
	}

	/**
	 * Register all options pages from storage.
	 */
	public function register_pages(): void {
		$pages = $this->storage->list_options_pages();

		foreach ( $pages as $page ) {
			$slug        = sanitize_key( $page['slug'] ?? '' );
			$title       = $page['title'] ?? $slug;
			$menu_title  = $page['menu_title'] ?? $title;
			$capability  = $page['capability'] ?? 'manage_options';
			$parent_slug = $page['parent_slug'] ?? 'tekton';
			$icon        = $page['icon'] ?? '';
			$position    = $page['position'] ?? null;

			if ( '' === $slug ) {
				continue;
			}

			$menu_slug = 'tekton-opt-' . $slug;

			if ( '' !== $parent_slug ) {
				add_submenu_page(
					$parent_slug,
					esc_html( $title ),
					esc_html( $menu_title ),
					$capability,
					$menu_slug,
					function () use ( $slug ): void {
						$this->render_page( $slug );
					},
					$position
				);
			} else {
				add_menu_page(
					esc_html( $title ),
					esc_html( $menu_title ),
					$capability,
					$menu_slug,
					function () use ( $slug ): void {
						$this->render_page( $slug );
					},
					$icon ?: 'dashicons-admin-generic',
					$position
				);
			}
		}
	}

	/**
	 * Render an options page with all matching field groups.
	 */
	public function render_page( string $slug ): void {
		$page_config = $this->storage->get_options_page( $slug );
		if ( ! $page_config ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Page not found.', 'tekton' ) . '</h1></div>';
			return;
		}

		$title       = $page_config['title'] ?? $slug;
		$description = $page_config['description'] ?? '';

		// Find all field groups targeting this options page.
		$matching_groups = $this->get_matching_groups( $slug );

		// Enqueue field UI styles.
		wp_enqueue_style(
			'tekton-field-ui',
			TEKTON_URL . 'assets/css/tekton-field-ui.css',
			[],
			TEKTON_VERSION
		);

		// Enqueue media for image fields.
		wp_enqueue_media();

		// Inline JS for image picker and repeater interactions.
		wp_add_inline_script( 'jquery', $this->get_field_admin_js() );

		// Enqueue assets for field types in matching groups.
		foreach ( $matching_groups as $group ) {
			foreach ( $group['fields'] as $field ) {
				$type = $this->registry->get_type( $field['type'] ?? 'text' );
				if ( $type ) {
					$type->enqueue_admin_assets();
				}
			}
		}

		// Check for saved notice.
		$saved = isset( $_GET['tekton-saved'] ) && '1' === $_GET['tekton-saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $title ) . '</h1>';

		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}

		if ( $saved ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'tekton' ) . '</p></div>';
		}

		if ( empty( $matching_groups ) ) {
			echo '<p>' . esc_html__( 'No field groups assigned to this page.', 'tekton' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<form method="post" action="">';
		wp_nonce_field( 'tekton_options_page_' . $slug, 'tekton_options_nonce' );
		echo '<input type="hidden" name="tekton_options_page_slug" value="' . esc_attr( $slug ) . '" />';

		foreach ( $matching_groups as $group ) {
			echo '<div class="tekton-options-section">';
			echo '<h2>' . esc_html( $group['title'] ) . '</h2>';
			echo '<div class="tekton-field-group">';

			foreach ( $group['fields'] as $field ) {
				$type = $this->registry->get_type( $field['type'] ?? 'text' );
				if ( ! $type ) {
					continue;
				}

				$option_key = '_tekton_opt_' . $slug . '_' . $field['name'];
				$value      = $this->get_option_value( $slug, $field['name'] );

				// Decode JSON for array field types.
				$array_types = [ 'repeater', 'checkbox', 'relationship', 'gallery', 'group', 'flexible_content', 'taxonomy' ];
				if ( in_array( $field['type'] ?? '', $array_types, true ) && is_string( $value ) && '' !== $value ) {
					$decoded = json_decode( $value, true );
					if ( is_array( $decoded ) ) {
						$value = $decoded;
					}
				}

				echo $type->render( $field, $value, $option_key ); // phpcs:ignore WordPress.Security.EscapeOutput -- field types handle escaping
			}

			echo '</div>';
			echo '</div>';
		}

		submit_button( __( 'Save Settings', 'tekton' ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Handle form submission for options page saves.
	 */
	public function handle_save(): void {
		if ( ! isset( $_POST['tekton_options_page_slug'] ) ) {
			return;
		}

		$slug = sanitize_key( wp_unslash( $_POST['tekton_options_page_slug'] ) );
		if ( '' === $slug ) {
			return;
		}

		if ( ! isset( $_POST['tekton_options_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tekton_options_nonce'] ) ), 'tekton_options_page_' . $slug ) ) {
			return;
		}

		// Check capability from page config.
		$page_config = $this->storage->get_options_page( $slug );
		$capability  = $page_config['capability'] ?? 'manage_options';
		if ( ! current_user_can( $capability ) ) {
			return;
		}

		$this->save_page( $slug );

		// Redirect to avoid resubmission.
		$redirect_url = add_query_arg( 'tekton-saved', '1' );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Save all field values for an options page.
	 */
	public function save_page( string $slug ): void {
		$matching_groups = $this->get_matching_groups( $slug );

		foreach ( $matching_groups as $group ) {
			foreach ( $group['fields'] as $field ) {
				$type = $this->registry->get_type( $field['type'] ?? 'text' );
				if ( ! $type ) {
					continue;
				}

				$option_key = '_tekton_opt_' . $slug . '_' . $field['name'];
				$raw_value  = $_POST[ $option_key ] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized by field type below
				$value      = $type->sanitize( $raw_value, $field );

				// Validate.
				$valid = $type->validate( $value, $field );
				if ( $valid !== true ) {
					continue; // Skip invalid values silently.
				}

				// Store arrays/objects as JSON.
				if ( is_array( $value ) ) {
					$value = wp_json_encode( $value );
				}

				update_option( $option_key, $value );
			}
		}
	}

	/**
	 * Get a single option value.
	 */
	public function get_option_value( string $page_slug, string $field_name ): mixed {
		$key = '_tekton_opt_' . $page_slug . '_' . $field_name;
		return get_option( $key, '' );
	}

	/**
	 * Find all field groups whose location rules target the given options page.
	 *
	 * @return array<int, array>
	 */
	private function get_matching_groups( string $page_slug ): array {
		$all_groups = $this->storage->list_field_groups();
		$matching   = [];

		foreach ( $all_groups as $group ) {
			$rules = $group['location_rules'] ?? [];
			if ( $this->matches_options_page( $rules, $page_slug ) ) {
				$matching[] = $group;
			}
		}

		return $matching;
	}

	/**
	 * Check if location rules target a specific options page.
	 *
	 * Rules use OR logic between rule groups, AND logic within a group.
	 */
	private function matches_options_page( array $rules, string $page_slug ): bool {
		if ( empty( $rules ) ) {
			return false; // Empty rules should not match options pages.
		}

		foreach ( $rules as $rule_group ) {
			if ( ! is_array( $rule_group ) ) {
				continue;
			}

			$group_match = true;

			foreach ( $rule_group as $rule ) {
				if ( ! is_array( $rule ) ) {
					$group_match = false;
					break;
				}

				$param    = $rule['param'] ?? '';
				$operator = $rule['operator'] ?? '==';
				$value    = $rule['value'] ?? '';

				if ( 'options_page' !== $param ) {
					$group_match = false;
					break;
				}

				$result = ( $value === $page_slug );

				if ( '!=' === $operator ) {
					$result = ! $result;
				}

				if ( ! $result ) {
					$group_match = false;
					break;
				}
			}

			if ( $group_match ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Inline JS for image picker and repeater interactions on options pages.
	 */
	private function get_field_admin_js(): string {
		return <<<'JS'
jQuery(function($){
  // Image field: select
  $(document).on('click','.tekton-image-select',function(e){
    e.preventDefault();
    var $wrap=$(this).closest('.tekton-image-field');
    var frame=wp.media({title:'Select Image',multiple:false,library:{type:'image'}});
    frame.on('select',function(){
      var a=frame.state().get('selection').first().toJSON();
      $wrap.find('input[type=hidden]').val(a.id);
      var url=a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url;
      $wrap.find('.tekton-image-preview').html('<img src="'+url+'" alt="">').show();
      $wrap.find('.tekton-image-remove').show();
    });
    frame.open();
  });
  // Image field: remove
  $(document).on('click','.tekton-image-remove',function(e){
    e.preventDefault();
    var $wrap=$(this).closest('.tekton-image-field');
    $wrap.find('input[type=hidden]').val('');
    $wrap.find('.tekton-image-preview').hide().html('');
    $(this).hide();
  });
  // Repeater: add row
  $(document).on('click','.tekton-repeater-add',function(){
    var $rep=$(this).closest('.tekton-repeater');
    var $rows=$rep.find('.tekton-repeater-rows');
    var $count=$rep.find('.tekton-repeater-count');
    var max=parseInt($rep.data('max'))||0;
    var count=parseInt($count.val())||0;
    if(max>0&&count>=max)return;
    var tmpl=$rep.find('template.tekton-repeater-template').html();
    tmpl=tmpl.replace(/\{\{INDEX\}\}/g,count);
    $rows.append(tmpl);
    $count.val(count+1);
    $rows.find('.tekton-repeater-row').last().find('.tekton-repeater-row-num').text(count+1);
  });
  // Repeater: remove row
  $(document).on('click','.tekton-repeater-remove',function(){
    var $rep=$(this).closest('.tekton-repeater');
    $(this).closest('.tekton-repeater-row').remove();
    var $count=$rep.find('.tekton-repeater-count');
    var c=parseInt($count.val())||1;
    $count.val(Math.max(0,c-1));
    $rep.find('.tekton-repeater-row').each(function(i){
      $(this).find('.tekton-repeater-row-num').text(i+1);
    });
  });
});
JS;
	}
}
