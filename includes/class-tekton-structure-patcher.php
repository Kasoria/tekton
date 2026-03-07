<?php
declare(strict_types=1);
/**
 * Applies granular operations (patches) to an existing component tree.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Structure_Patcher {

	/**
	 * Apply a list of operations to a structure, returning the modified structure.
	 *
	 * @param array $structure   The existing structure with 'components'.
	 * @param array $operations  List of operation objects from the AI.
	 * @return array Modified structure.
	 */
	public static function apply( array $structure, array $operations ): array {
		$components = $structure['components'] ?? [];

		foreach ( $operations as $op ) {
			$type = $op['op'] ?? '';

			switch ( $type ) {
				case 'update_styles':
					$components = self::walk( $components, $op['target'], function ( &$comp ) use ( $op ) {
						$styles = $comp['styles'] ?? [];
						foreach ( ( $op['styles'] ?? [] ) as $breakpoint => $props ) {
							if ( ! isset( $styles[ $breakpoint ] ) ) {
								$styles[ $breakpoint ] = [];
							}
							$styles[ $breakpoint ] = array_merge( $styles[ $breakpoint ], $props );
						}
						$comp['styles'] = $styles;
					} );
					break;

				case 'update_props':
					$components = self::walk( $components, $op['target'], function ( &$comp ) use ( $op ) {
						$comp['props'] = array_merge( $comp['props'] ?? [], $op['props'] ?? [] );
					} );
					break;

				case 'update_content':
					$components = self::walk( $components, $op['target'], function ( &$comp ) use ( $op ) {
						if ( isset( $op['content'] ) ) {
							$comp['props']['content'] = $op['content'];
						}
					} );
					break;

				case 'add_component':
					$components = self::insert_child(
						$components,
						$op['parent'] ?? null,
						$op['position'] ?? -1,
						$op['component'] ?? []
					);
					break;

				case 'remove_component':
					$components = self::remove( $components, $op['target'] );
					break;

				case 'replace_component':
					$components = self::walk( $components, $op['target'], function ( &$comp ) use ( $op ) {
						$new = $op['component'] ?? [];
						// Preserve ID from target.
						$new['id'] = $comp['id'];
						foreach ( $new as $k => $v ) {
							$comp[ $k ] = $v;
						}
					} );
					break;

				case 'set_keyframes':
					$existing = $structure['keyframes'] ?? [];
					$structure['keyframes'] = array_merge( $existing, $op['keyframes'] ?? [] );
					break;

				case 'set_scripts':
					$new_scripts = $op['scripts'] ?? [];
					if ( ! empty( $op['replace'] ) ) {
						$structure['scripts'] = $new_scripts;
					} else {
						$existing_scripts = $structure['scripts'] ?? [];
						foreach ( $new_scripts as $script ) {
							if ( ! in_array( $script, $existing_scripts, true ) ) {
								$existing_scripts[] = $script;
							}
						}
						$structure['scripts'] = $existing_scripts;
					}
					break;

				case 'set_meta':
					$existing_meta = $structure['meta'] ?? [];
					$structure['meta'] = array_merge( $existing_meta, $op['meta'] ?? [] );
					break;

				case 'set_wrapper_styles':
					$existing_ws = $structure['wrapper_styles'] ?? [];
					foreach ( ( $op['wrapper_styles'] ?? [] ) as $breakpoint => $props ) {
						if ( ! isset( $existing_ws[ $breakpoint ] ) ) {
							$existing_ws[ $breakpoint ] = [];
						}
						$existing_ws[ $breakpoint ] = array_merge( $existing_ws[ $breakpoint ], $props );
					}
					$structure['wrapper_styles'] = $existing_ws;
					break;

				case 'move_component':
					// Extract the component, then insert at new position.
					$extracted = null;
					$components = self::extract( $components, $op['target'], $extracted );
					if ( $extracted ) {
						$components = self::insert_child(
							$components,
							$op['parent'] ?? null,
							$op['position'] ?? -1,
							$extracted
						);
					}
					break;
			}
		}

		$structure['components'] = $components;
		return $structure;
	}

	/**
	 * Walk the component tree and apply a callback to the component matching $target_id.
	 */
	private static function walk( array $components, string $target_id, callable $callback ): array {
		foreach ( $components as &$comp ) {
			if ( ( $comp['id'] ?? '' ) === $target_id ) {
				$callback( $comp );
			}
			if ( ! empty( $comp['children'] ) ) {
				$comp['children'] = self::walk( $comp['children'], $target_id, $callback );
			}
		}
		return $components;
	}

	/**
	 * Remove a component by ID from the tree.
	 */
	private static function remove( array $components, string $target_id ): array {
		$result = [];
		foreach ( $components as $comp ) {
			if ( ( $comp['id'] ?? '' ) === $target_id ) {
				continue;
			}
			if ( ! empty( $comp['children'] ) ) {
				$comp['children'] = self::remove( $comp['children'], $target_id );
			}
			$result[] = $comp;
		}
		return $result;
	}

	/**
	 * Extract (remove and return) a component by ID.
	 */
	private static function extract( array $components, string $target_id, ?array &$extracted ): array {
		$result = [];
		foreach ( $components as $comp ) {
			if ( ( $comp['id'] ?? '' ) === $target_id ) {
				$extracted = $comp;
				continue;
			}
			if ( ! empty( $comp['children'] ) ) {
				$comp['children'] = self::extract( $comp['children'], $target_id, $extracted );
			}
			$result[] = $comp;
		}
		return $result;
	}

	/**
	 * Insert a component as a child of $parent_id at $position.
	 * If $parent_id is null, insert at root level.
	 */
	private static function insert_child( array $components, ?string $parent_id, int $position, array $component ): array {
		if ( null === $parent_id || '' === $parent_id ) {
			if ( $position < 0 || $position >= count( $components ) ) {
				$components[] = $component;
			} else {
				array_splice( $components, $position, 0, [ $component ] );
			}
			return $components;
		}

		foreach ( $components as &$comp ) {
			if ( ( $comp['id'] ?? '' ) === $parent_id ) {
				if ( ! isset( $comp['children'] ) ) {
					$comp['children'] = [];
				}
				if ( $position < 0 || $position >= count( $comp['children'] ) ) {
					$comp['children'][] = $component;
				} else {
					array_splice( $comp['children'], $position, 0, [ $component ] );
				}
				return $components;
			}
			if ( ! empty( $comp['children'] ) ) {
				$comp['children'] = self::insert_child( $comp['children'], $parent_id, $position, $component );
			}
		}

		return $components;
	}
}
