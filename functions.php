<?php
/**
 * Impact Flow portfolio child theme.
 *
 * @package ImpactFlowPortfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load the child presentation after the shared parent design system.
 */
function impactflow_portfolio_enqueue_assets(): void {
	$theme   = wp_get_theme();
	$version = $theme->get( 'Version' );
	$css     = get_stylesheet_directory() . '/assets/css/portfolio-home.css';

	wp_enqueue_style(
		'impact-flow-portfolio',
		get_stylesheet_uri(),
		array( 'impact-flow' ),
		$version
	);

	wp_enqueue_style(
		'impact-flow-portfolio-home',
		get_stylesheet_directory_uri() . '/assets/css/portfolio-home.css',
		array( 'impact-flow-portfolio' ),
		file_exists( $css ) ? (string) filemtime( $css ) : $version
	);
}
add_action( 'wp_enqueue_scripts', 'impactflow_portfolio_enqueue_assets' );

/**
 * Use the same child presentation inside the block and site editors.
 */
function impactflow_portfolio_editor_styles(): void {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/portfolio-home.css' );
}
add_action( 'after_setup_theme', 'impactflow_portfolio_editor_styles', 20 );

/**
 * Stable hook for homepage-only integration and visual tests.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function impactflow_portfolio_body_class( array $classes ): array {
	if ( is_front_page() ) {
		$classes[] = 'impact-flow-portfolio';
	}
	return $classes;
}
add_filter( 'body_class', 'impactflow_portfolio_body_class' );
