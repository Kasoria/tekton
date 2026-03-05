<?php
declare(strict_types=1);
/**
 * Tekton template canvas — renders component structures as full HTML pages.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$bridge    = Tekton_Core::instance()->get_module( 'theme_bridge' );
$renderer  = Tekton_Core::instance()->get_module( 'renderer' );
$structure = $bridge->get_structure_for_current();

if ( ! $structure ) {
	wp_safe_redirect( home_url() );
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'tekton-rendered' ); ?>>
<?php wp_body_open(); ?>

<?php
// Renderer handles all escaping internally.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $renderer->render_page( $structure, get_the_ID() ?: 0 );
?>

<?php wp_footer(); ?>
</body>
</html>
