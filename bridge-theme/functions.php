<?php
declare(strict_types=1);
/**
 * Tekton Bridge theme functions.
 * Intentionally minimal — all logic lives in the Tekton plugin.
 *
 * @package Tekton_Bridge
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'after_setup_theme', function (): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	] );
	register_nav_menus( [
		'primary' => esc_html__( 'Primary Menu', 'tekton-bridge' ),
		'footer'  => esc_html__( 'Footer Menu', 'tekton-bridge' ),
	] );
} );
