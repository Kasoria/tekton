<?php
declare(strict_types=1);
/**
 * Plugin Name: Tekton
 * Plugin URI:  https://github.com/kasoria/tekton
 * Description: AI-first WordPress site builder replacing theme + pagebuilder + custom fields.
 * Version:     1.0.0
 * Author:      Kasoria
 * Author URI:  https://kasoria.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tekton
 * Requires PHP: 8.2
 * Requires at least: 6.9
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'TEKTON_VERSION', '1.0.0' );
define( 'TEKTON_FILE', __FILE__ );
define( 'TEKTON_DIR', plugin_dir_path( __FILE__ ) );
define( 'TEKTON_URL', plugin_dir_url( __FILE__ ) );
define( 'TEKTON_BASENAME', plugin_basename( __FILE__ ) );

require_once TEKTON_DIR . 'includes/class-tekton-core.php';

/**
 * Boot the plugin.
 */
function tekton_init(): void {
	load_plugin_textdomain( 'tekton', false, dirname( TEKTON_BASENAME ) . '/languages/' );
	Tekton_Core::instance();
}
add_action( 'plugins_loaded', 'tekton_init' );

/**
 * Activation hook.
 */
function tekton_activate(): void {
	require_once TEKTON_DIR . 'includes/class-tekton-activator.php';
	Tekton_Activator::activate();
}
register_activation_hook( __FILE__, 'tekton_activate' );

/**
 * Deactivation hook.
 */
function tekton_deactivate(): void {
	require_once TEKTON_DIR . 'includes/class-tekton-activator.php';
	Tekton_Activator::deactivate();
}
register_deactivation_hook( __FILE__, 'tekton_deactivate' );
