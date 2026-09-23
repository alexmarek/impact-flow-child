<?php
/**
 * Portfolio homepage.
 *
 * The approved design source (2026-09-20) remains the single source of truth
 * for the homepage CSS, the shared header/footer chrome and the style-guide
 * dialog. The <main> content now comes from the WordPress page assigned as the
 * front page, so the homepage copy is editable in the block editor.
 *
 * @package ImpactFlowPortfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$design_path = get_stylesheet_directory() . '/assets/design/2026-09-20-impact-flow-homepage-v2-1.html';
$source      = is_readable( $design_path ) ? file_get_contents( $design_path ) : '';
$css         = '';
$header      = '';
$footer      = '';
$dialog      = '';
$fallback    = '';

if ( is_string( $source ) && preg_match_all( '~<style(?:\s[^>]*)?>(.*?)</style>~s', $source, $matches ) ) {
	$style_blocks = array_filter(
		$matches[1],
		static fn( $style_block ) => '' !== trim( $style_block )
	);
	$css          = str_replace(
		'../impact-flow-theme/',
		trailingslashit( get_template_directory_uri() ),
		implode( "\n", $style_blocks )
	);
}

if ( is_string( $source ) ) {
	if ( preg_match( '~<header\b[^>]*>.*?</header>~s', $source, $matches ) ) {
		$header = preg_replace( '~href="#"~', 'href="' . esc_url( home_url( '/' ) ) . '"', $matches[0], 1 );
	}
	if ( preg_match( '~<main\b[^>]*>(.*?)</main>~s', $source, $matches ) ) {
		$fallback = $matches[1];
	}
	if ( preg_match( '~<footer\b[^>]*>.*?</footer>~s', $source, $matches ) ) {
		$footer = preg_replace( '~href="#"~', 'href="' . esc_url( home_url( '/' ) ) . '"', $matches[0], 1 );
	}
	if ( preg_match( '~<dialog\b[^>]*>.*?</dialog>~s', $source, $matches ) ) {
		$dialog = $matches[0];
	}
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="Distinctive websites, useful features and reliable ongoing care. Impact Flow takes care of your business online.">
	<?php wp_head(); ?>
	<style><?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted, versioned design source. ?></style>
</head>
<body <?php body_class( 'impact-flow-portfolio' ); ?>>
<?php wp_body_open(); ?>
<a class="skip" href="#main">Skip to content</a>
<?php echo $header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted, versioned design source. ?>
<main id="main">
	<?php
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();
			if ( '' !== trim( get_the_content() ) ) {
				the_content();
			} else {
				echo $fallback; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted, versioned design source.
			}
		endwhile;
	endif;
	?>
</main>
<?php echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted, versioned design source. ?>
<?php echo $dialog; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted, versioned design source. ?>
<script src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/js/portfolio.js' ); ?>"></script>
<?php wp_footer(); ?>
</body>
</html>