<?php
declare(strict_types=1);
/**
 * Component schema definitions and validation.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Schema {

	/** @var array<string, array> */
	private array $types = [];

	public function __construct() {
		$this->register_core_types();
	}

	/**
	 * @return array{valid: bool, errors: string[]}
	 */
	public function validate_component( array $component, int $depth = 0 ): array {
		$errors = [];

		if ( $depth > 20 ) {
			return [ 'valid' => false, 'errors' => [ 'Maximum nesting depth (20) exceeded.' ] ];
		}

		if ( empty( $component['id'] ) ) {
			$errors[] = 'Component missing "id".';
		}

		if ( empty( $component['type'] ) ) {
			$errors[] = 'Component missing "type".';
			return [ 'valid' => false, 'errors' => $errors ];
		}

		if ( ! isset( $this->types[ $component['type'] ] ) ) {
			$errors[] = sprintf( 'Unknown component type: %s', $component['type'] );
		}

		if ( isset( $component['props']['content'] ) && is_array( $component['props']['content'] ) ) {
			if ( ! $this->validate_content_source( $component['props']['content'] ) ) {
				$errors[] = sprintf( 'Invalid content source in component %s.', $component['id'] ?? '?' );
			}
		}

		if ( ! empty( $component['children'] ) && is_array( $component['children'] ) ) {
			foreach ( $component['children'] as $i => $child ) {
				$child_result = $this->validate_component( $child, $depth + 1 );
				foreach ( $child_result['errors'] as $err ) {
					$errors[] = sprintf( 'Child %d: %s', $i, $err );
				}
			}
		}

		return [ 'valid' => empty( $errors ), 'errors' => $errors ];
	}

	/**
	 * @return array{valid: bool, errors: string[]}
	 */
	public function validate_structure( array $structure ): array {
		$errors = [];

		if ( empty( $structure['components'] ) || ! is_array( $structure['components'] ) ) {
			return [ 'valid' => false, 'errors' => [ 'Structure must have a components array.' ] ];
		}

		foreach ( $structure['components'] as $i => $component ) {
			$result = $this->validate_component( $component );
			foreach ( $result['errors'] as $err ) {
				$errors[] = sprintf( 'Component %d: %s', $i, $err );
			}
		}

		return [ 'valid' => empty( $errors ), 'errors' => $errors ];
	}

	public function get_component_defaults( string $type ): array {
		return $this->types[ $type ] ?? [];
	}

	/** @return string[] */
	public function get_registered_types(): array {
		return array_keys( $this->types );
	}

	private function validate_content_source( array $source ): bool {
		if ( empty( $source['source'] ) ) {
			return false;
		}

		$valid_sources = [ 'field', 'post', 'option', 'acf', 'static', 'computed', 'menu' ];
		if ( ! in_array( $source['source'], $valid_sources, true ) ) {
			return false;
		}

		return match ( $source['source'] ) {
			'field'    => ! empty( $source['group'] ) && ! empty( $source['field'] ),
			'post'     => ! empty( $source['field'] ),
			'option'   => ! empty( $source['key'] ),
			'acf'      => ! empty( $source['field'] ),
			'static'   => array_key_exists( 'value', $source ),
			'computed' => ! empty( $source['expression'] ),
			'menu'     => ! empty( $source['location'] ),
			default    => false,
		};
	}

	private function register_core_types(): void {
		$this->types = [
			'section' => [
				'type'            => 'section',
				'label'           => 'Section',
				'allowedChildren' => '*',
				'defaultProps'    => [ 'tagName' => 'section', 'className' => '' ],
				'editableProps'   => [ 'className', 'backgroundColor', 'padding' ],
			],
			'container' => [
				'type'            => 'container',
				'label'           => 'Container',
				'allowedChildren' => '*',
				'defaultProps'    => [ 'maxWidth' => '1200px', 'className' => '' ],
				'editableProps'   => [ 'maxWidth', 'className', 'padding' ],
			],
			'heading' => [
				'type'            => 'heading',
				'label'           => 'Heading',
				'allowedChildren' => [],
				'defaultProps'    => [ 'level' => 2, 'content' => null ],
				'editableProps'   => [ 'content', 'level', 'className' ],
			],
			'text' => [
				'type'            => 'text',
				'label'           => 'Text',
				'allowedChildren' => [],
				'defaultProps'    => [ 'content' => null, 'tagName' => 'p' ],
				'editableProps'   => [ 'content', 'className' ],
			],
			'image' => [
				'type'            => 'image',
				'label'           => 'Image',
				'allowedChildren' => [],
				'defaultProps'    => [ 'src' => null, 'alt' => null, 'caption' => null ],
				'editableProps'   => [ 'src', 'alt' ],
			],
			'button' => [
				'type'            => 'button',
				'label'           => 'Button',
				'allowedChildren' => [],
				'defaultProps'    => [ 'text' => null, 'href' => null, 'target' => '_self' ],
				'editableProps'   => [ 'text', 'href', 'className' ],
			],
			'grid' => [
				'type'            => 'grid',
				'label'           => 'Grid',
				'allowedChildren' => '*',
				'defaultProps'    => [ 'columns' => 3, 'gap' => '1rem' ],
				'editableProps'   => [ 'columns', 'gap' ],
			],
			'flex-row' => [
				'type'            => 'flex-row',
				'label'           => 'Flex Row',
				'allowedChildren' => '*',
				'defaultProps'    => [ 'gap' => '1rem', 'alignItems' => 'center', 'justifyContent' => 'flex-start' ],
				'editableProps'   => [ 'gap', 'alignItems', 'justifyContent' ],
			],
			'flex-column' => [
				'type'            => 'flex-column',
				'label'           => 'Flex Column',
				'allowedChildren' => '*',
				'defaultProps'    => [ 'gap' => '1rem', 'alignItems' => 'stretch' ],
				'editableProps'   => [ 'gap', 'alignItems' ],
			],
			'link' => [
				'type'            => 'link',
				'label'           => 'Link',
				'allowedChildren' => [],
				'defaultProps'    => [ 'text' => null, 'href' => null, 'target' => '_self' ],
				'editableProps'   => [ 'text', 'href' ],
			],
			'list' => [
				'type'            => 'list',
				'label'           => 'List',
				'allowedChildren' => [],
				'defaultProps'    => [ 'ordered' => false, 'items' => [] ],
				'editableProps'   => [ 'ordered', 'items' ],
			],
			'spacer' => [
				'type'            => 'spacer',
				'label'           => 'Spacer',
				'allowedChildren' => [],
				'defaultProps'    => [ 'height' => '2rem' ],
				'editableProps'   => [ 'height' ],
			],
			'divider' => [
				'type'            => 'divider',
				'label'           => 'Divider',
				'allowedChildren' => [],
				'defaultProps'    => [ 'color' => 'var(--tekton-border)', 'thickness' => '1px' ],
				'editableProps'   => [ 'color', 'thickness' ],
			],
			'video' => [
				'type'            => 'video',
				'label'           => 'Video',
				'allowedChildren' => [],
				'defaultProps'    => [ 'src' => null, 'type' => 'embed' ],
				'editableProps'   => [ 'src' ],
			],
			'icon' => [
				'type'            => 'icon',
				'label'           => 'Icon',
				'allowedChildren' => [],
				'defaultProps'    => [ 'name' => '', 'size' => '24px' ],
				'editableProps'   => [ 'name', 'size' ],
			],
			'post-loop' => [
				'type'            => 'post-loop',
				'label'           => 'Post Loop',
				'allowedChildren' => '*',
				'defaultProps'    => [ 'query' => [ 'post_type' => 'post', 'posts_per_page' => 10 ] ],
				'editableProps'   => [ 'query' ],
			],
			'post-title' => [
				'type'            => 'post-title',
				'label'           => 'Post Title',
				'allowedChildren' => [],
				'defaultProps'    => [ 'tagName' => 'h2', 'link' => true ],
				'editableProps'   => [ 'tagName', 'link' ],
			],
			'post-content' => [
				'type'            => 'post-content',
				'label'           => 'Post Content',
				'allowedChildren' => [],
				'defaultProps'    => [],
				'editableProps'   => [],
			],
			'post-meta' => [
				'type'            => 'post-meta',
				'label'           => 'Post Meta',
				'allowedChildren' => [],
				'defaultProps'    => [ 'showDate' => true, 'showAuthor' => true, 'showCategories' => false ],
				'editableProps'   => [ 'showDate', 'showAuthor', 'showCategories' ],
			],
			'featured-image' => [
				'type'            => 'featured-image',
				'label'           => 'Featured Image',
				'allowedChildren' => [],
				'defaultProps'    => [ 'size' => 'large', 'link' => false ],
				'editableProps'   => [ 'size', 'link' ],
			],
			'menu' => [
				'type'            => 'menu',
				'label'           => 'Menu',
				'allowedChildren' => [],
				'defaultProps'    => [ 'location' => 'primary' ],
				'editableProps'   => [ 'location' ],
			],
			'tekton-field' => [
				'type'            => 'tekton-field',
				'label'           => 'Tekton Field',
				'allowedChildren' => [],
				'defaultProps'    => [ 'group' => '', 'field' => '', 'tagName' => 'span' ],
				'editableProps'   => [ 'group', 'field', 'tagName' ],
			],
			'search-form' => [
				'type'            => 'search-form',
				'label'           => 'Search Form',
				'allowedChildren' => [],
				'defaultProps'    => [],
				'editableProps'   => [],
			],
		];
	}
}
