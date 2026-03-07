<?php
declare(strict_types=1);
/**
 * REST API coordinator — delegates to domain-specific controllers.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_REST_API {

	private Tekton_Core $core;

	/** @var object[] */
	private array $controllers = [];

	public function __construct( Tekton_Core $core ) {
		$this->core = $core;
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		$ns = 'tekton/v1';

		$this->controllers['ai']         = new Tekton_REST_AI( $this->core );
		$this->controllers['structures'] = new Tekton_REST_Structures( $this->core );
		$this->controllers['settings']   = new Tekton_REST_Settings( $this->core );
		$this->controllers['content']    = new Tekton_REST_Content( $this->core );

		foreach ( $this->controllers as $controller ) {
			$controller->register_routes( $ns );
		}
	}

	public static function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}
}
