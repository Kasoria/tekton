<?php
declare(strict_types=1);
/**
 * Field Engine — orchestrates field groups, meta box rendering, and value storage.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Engine {

	private Tekton_Storage        $storage;
	private Tekton_Field_Registry $registry;

	/** @var array<string, array>|null Cached field groups */
	private ?array $groups_cache = null;

	public function __construct( Tekton_Storage $storage, Tekton_Field_Registry $registry ) {
		$this->storage  = $storage;
		$this->registry = $registry;
	}

	public function init(): void {
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_meta_boxes' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Enqueue field UI styles on post edit screens.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		wp_enqueue_style(
			'tekton-field-ui',
			TEKTON_URL . 'assets/css/tekton-field-ui.css',
			[],
			TEKTON_VERSION
		);

		// Inline JS for image field media picker and repeater add/remove.
		wp_add_inline_script( 'jquery', $this->get_field_admin_js() );
	}

	/**
	 * Inline JS for image picker and repeater interactions.
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
  // Gallery field: add
  $(document).on('click','.tekton-gallery-add',function(e){
    e.preventDefault();
    var $wrap=$(this).closest('.tekton-gallery-field');
    var frame=wp.media({title:'Add to Gallery',multiple:true,library:{type:'image'}});
    frame.on('select',function(){
      var sel=frame.state().get('selection').toJSON();
      var $input=$wrap.find('input[type=hidden]');
      var cur=$input.val();
      var ids=cur?cur.split(','):[];
      var $preview=$wrap.find('.tekton-gallery-preview');
      $.each(sel,function(i,a){
        ids.push(a.id);
        var url=a.sizes&&a.sizes.thumbnail?a.sizes.thumbnail.url:a.url;
        $preview.append('<div class="tekton-gallery-thumb" data-id="'+a.id+'"><img src="'+url+'" alt=""></div>');
      });
      $input.val(ids.join(','));
      $wrap.find('.tekton-gallery-clear').show();
    });
    frame.open();
  });
  // Gallery field: clear
  $(document).on('click','.tekton-gallery-clear',function(e){
    e.preventDefault();
    var $wrap=$(this).closest('.tekton-gallery-field');
    $wrap.find('input[type=hidden]').val('');
    $wrap.find('.tekton-gallery-preview').html('');
    $(this).hide();
  });
  // File field: select
  $(document).on('click','.tekton-file-select',function(e){
    e.preventDefault();
    var $wrap=$(this).closest('.tekton-file-field');
    var frame=wp.media({title:'Select File',multiple:false});
    frame.on('select',function(){
      var a=frame.state().get('selection').first().toJSON();
      $wrap.find('input[type=hidden]').val(a.id);
      $wrap.find('.tekton-file-info').html('<span class="tekton-file-name">'+a.filename+'</span>').show();
      $wrap.find('.tekton-file-remove').show();
    });
    frame.open();
  });
  // File field: remove
  $(document).on('click','.tekton-file-remove',function(e){
    e.preventDefault();
    var $wrap=$(this).closest('.tekton-file-field');
    $wrap.find('input[type=hidden]').val('');
    $wrap.find('.tekton-file-info').hide().html('');
    $(this).hide();
  });
  // Flexible Content: toggle layout picker
  $(document).on('click','.tekton-fc-add-btn',function(){
    $(this).siblings('.tekton-fc-layout-picker').toggle();
  });
  // Flexible Content: pick layout
  $(document).on('click','.tekton-fc-pick-layout',function(){
    var $fc=$(this).closest('.tekton-flexible-content');
    var layout=$(this).data('layout');
    var $rows=$fc.find('.tekton-fc-rows');
    var $count=$fc.find('.tekton-fc-count');
    var count=parseInt($count.val())||0;
    var tmpl=$fc.find('template.tekton-fc-template[data-layout="'+layout+'"]').html();
    tmpl=tmpl.replace(/\{\{INDEX\}\}/g,count);
    $rows.append(tmpl);
    $count.val(count+1);
    $(this).closest('.tekton-fc-layout-picker').hide();
  });
  // Flexible Content: remove row
  $(document).on('click','.tekton-fc-remove',function(){
    var $fc=$(this).closest('.tekton-flexible-content');
    $(this).closest('.tekton-fc-row').remove();
    var $count=$fc.find('.tekton-fc-count');
    var c=parseInt($count.val())||1;
    $count.val(Math.max(0,c-1));
  });
  // Range field: update display
  $(document).on('input','.tekton-range-input',function(){
    $(this).siblings('.tekton-range-value').text($(this).val());
  });
});
JS;
	}

	/**
	 * Register meta boxes for all matching field groups on the current screen.
	 */
	public function register_meta_boxes( string $post_type ): void {
		$groups = $this->get_active_groups();

		foreach ( $groups as $group ) {
			if ( ! $this->matches_location_rules( $group['location_rules'], $post_type ) ) {
				continue;
			}

			$position = $group['position'] ?? 'normal';
			$priority = $group['priority'] ?? 'high';

			add_meta_box(
				'tekton_fg_' . $group['slug'],
				esc_html( $group['title'] ),
				[ $this, 'render_meta_box' ],
				$post_type,
				$position,
				$priority,
				[ 'group' => $group ]
			);

			// Enqueue assets for field types in this group
			foreach ( $group['fields'] as $field ) {
				$type = $this->registry->get_type( $field['type'] ?? 'text' );
				if ( $type ) {
					$type->enqueue_admin_assets();
				}
			}
		}
	}

	/**
	 * Render a meta box for a field group.
	 */
	public function render_meta_box( \WP_Post $post, array $args ): void {
		$group = $args['args']['group'];

		wp_nonce_field( 'tekton_fg_' . $group['slug'], 'tekton_fg_nonce_' . $group['slug'] );

		echo '<div class="tekton-field-group">';

		foreach ( $group['fields'] as $field ) {
			$type = $this->registry->get_type( $field['type'] ?? 'text' );
			if ( ! $type ) {
				continue;
			}

			$meta_key = $this->meta_key( $group['slug'], $field['name'] );
			$value    = $this->get_raw_value( $meta_key, $post->ID, $field );

			echo $type->render( $field, $value, $meta_key ); // phpcs:ignore WordPress.Security.EscapeOutput -- field types handle escaping
		}

		echo '</div>';
	}

	/**
	 * Save meta box values on post save.
	 */
	public function save_meta_boxes( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$groups = $this->get_active_groups();

		foreach ( $groups as $group ) {
			if ( ! $this->matches_location_rules( $group['location_rules'], $post->post_type ) ) {
				continue;
			}

			$nonce_key = 'tekton_fg_nonce_' . $group['slug'];
			if ( ! isset( $_POST[ $nonce_key ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ), 'tekton_fg_' . $group['slug'] ) ) {
				continue;
			}

			foreach ( $group['fields'] as $field ) {
				$type     = $this->registry->get_type( $field['type'] ?? 'text' );
				if ( ! $type ) {
					continue;
				}

				$meta_key  = $this->meta_key( $group['slug'], $field['name'] );
				$raw_value = $_POST[ $meta_key ] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized by field type below
				$value     = $type->sanitize( $raw_value, $field );

				// Validate
				$valid = $type->validate( $value, $field );
				if ( $valid !== true ) {
					continue; // Skip invalid values silently
				}

				// Store arrays/objects as JSON
				if ( is_array( $value ) ) {
					$value = wp_json_encode( $value );
				}

				update_post_meta( $post_id, $meta_key, $value );
			}
		}
	}

	/**
	 * Get a single field value (formatted for frontend use).
	 */
	public function get_field_value( string $group_slug, string $field_name, int $post_id ): mixed {
		$meta_key = $this->meta_key( $group_slug, $field_name );
		$group    = $this->get_group_by_slug( $group_slug );
		$field    = $this->find_field_in_group( $group, $field_name );
		$type     = $field ? $this->registry->get_type( $field['type'] ?? 'text' ) : null;
		$raw      = get_post_meta( $post_id, $meta_key, true );

		if ( $type && $field ) {
			return $type->format_value( $raw, $post_id, $field );
		}

		return $raw;
	}

	/**
	 * Get all field values for a group.
	 *
	 * @return array<string, mixed>
	 */
	public function get_group_values( string $group_slug, int $post_id ): array {
		$group  = $this->get_group_by_slug( $group_slug );
		$values = [];

		if ( ! $group ) {
			return $values;
		}

		foreach ( $group['fields'] as $field ) {
			$values[ $field['name'] ] = $this->get_field_value( $group_slug, $field['name'], $post_id );
		}

		return $values;
	}

	/**
	 * Get an option value from an options page.
	 */
	public function get_option_value( string $page_slug, string $field_name ): mixed {
		$key = '_tekton_opt_' . $page_slug . '_' . $field_name;
		return get_option( $key, '' );
	}

	/**
	 * Build the meta key for a field.
	 */
	public function meta_key( string $group_slug, string $field_name ): string {
		return '_tekton_' . $group_slug . '_' . $field_name;
	}

	// ─── Private helpers ──────────────────────────────────────────────

	private function get_active_groups(): array {
		if ( $this->groups_cache === null ) {
			$this->groups_cache = $this->storage->list_field_groups();
		}
		return $this->groups_cache;
	}

	private function get_group_by_slug( string $slug ): ?array {
		foreach ( $this->get_active_groups() as $group ) {
			if ( $group['slug'] === $slug ) {
				return $group;
			}
		}
		return null;
	}

	private function find_field_in_group( ?array $group, string $field_name ): ?array {
		if ( ! $group ) {
			return null;
		}
		foreach ( $group['fields'] as $field ) {
			if ( ( $field['name'] ?? '' ) === $field_name ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * Get the raw meta value, handling JSON-encoded arrays.
	 */
	private function get_raw_value( string $meta_key, int $post_id, array $field ): mixed {
		$raw = get_post_meta( $post_id, $meta_key, true );

		// Decode JSON for array field types
		$array_types = [ 'repeater', 'checkbox', 'relationship', 'gallery', 'group', 'flexible_content', 'taxonomy' ];
		if ( in_array( $field['type'] ?? '', $array_types, true ) && is_string( $raw ) && $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return $raw;
	}

	/**
	 * Check if a field group's location rules match the current post type.
	 *
	 * Rules use OR logic between rule groups, AND logic within a group.
	 */
	private function matches_location_rules( array $rules, string $post_type ): bool {
		if ( empty( $rules ) ) {
			return true;
		}

		// Outer array = OR groups
		foreach ( $rules as $rule_group ) {
			if ( ! is_array( $rule_group ) ) {
				continue;
			}

			$group_match = true;

			// Inner array = AND conditions
			foreach ( $rule_group as $rule ) {
				if ( ! is_array( $rule ) ) {
					$group_match = false;
					break;
				}

				$param    = $rule['param'] ?? '';
				$operator = $rule['operator'] ?? '==';
				$value    = $rule['value'] ?? '';

				$result = match ( $param ) {
					'post_type' => $post_type === $value,
					default     => true,
				};

				if ( $operator === '!=' ) {
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
}
