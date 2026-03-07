<?php
declare(strict_types=1);
/**
 * Field type registry — registers and retrieves field type instances.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Field_Registry {

	/** @var array<string, Tekton_Field_Type> */
	private array $types = [];

	/**
	 * Register a field type instance.
	 */
	public function register( Tekton_Field_Type $type ): void {
		$this->types[ $type->get_type() ] = $type;
	}

	/**
	 * Get a field type by its type key.
	 */
	public function get_type( string $type ): ?Tekton_Field_Type {
		return $this->types[ $type ] ?? null;
	}

	/**
	 * Get all registered type keys.
	 *
	 * @return string[]
	 */
	public function get_registered_types(): array {
		return array_keys( $this->types );
	}

	/**
	 * Register all built-in field types.
	 */
	public function register_defaults(): void {
		$dir = TEKTON_DIR . 'includes/field-engine/fields/';

		$defaults = [
			'text'         => 'Tekton_Field_Text',
			'textarea'     => 'Tekton_Field_Textarea',
			'number'       => 'Tekton_Field_Number',
			'email'        => 'Tekton_Field_Email',
			'url'          => 'Tekton_Field_Url',
			'select'       => 'Tekton_Field_Select',
			'checkbox'     => 'Tekton_Field_Checkbox',
			'radio'        => 'Tekton_Field_Radio',
			'true_false'   => 'Tekton_Field_True_False',
			'image'        => 'Tekton_Field_Image',
			'date'         => 'Tekton_Field_Date',
			'color'        => 'Tekton_Field_Color',
			'repeater'     => 'Tekton_Field_Repeater',
			'relationship' => 'Tekton_Field_Relationship',
		];

		foreach ( $defaults as $type => $class ) {
			$file = $dir . 'class-field-' . str_replace( '_', '-', $type ) . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
				if ( class_exists( $class ) ) {
					$this->register( new $class() );
				}
			}
		}
	}
}
