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
$storage   = Tekton_Core::instance()->get_module( 'storage' );
$structure = $bridge->get_structure_for_current();

if ( ! $structure ) {
	wp_safe_redirect( home_url() );
	exit;
}

$template_key = $bridge->get_current_template_key();
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

<div id="tekton-site">
<?php
// Render header (unless this IS the header template).
if ( 'header' !== $template_key ) {
	$header = $storage->get_structure( 'header' );
	if ( $header && ! empty( $header['components'] ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $renderer->render_page( $header, get_the_ID() ?: 0, 'header' );
	}
}

// Render the main page structure.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $renderer->render_page( $structure, get_the_ID() ?: 0, 'main' );

// Render footer (unless this IS the footer template).
if ( 'footer' !== $template_key ) {
	$footer = $storage->get_structure( 'footer' );
	if ( $footer && ! empty( $footer['components'] ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $renderer->render_page( $footer, get_the_ID() ?: 0, 'footer' );
	}
}
?>
</div>

<?php wp_footer(); ?>
</body>
</html>
