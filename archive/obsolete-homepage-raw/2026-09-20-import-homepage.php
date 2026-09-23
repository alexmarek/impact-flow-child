<?php
/**
 * Import the assembled homepage block markup into the Home page.
 *
 * Usage (from the child theme directory):
 *   php tools/2026-09-20-import-homepage.php           # validate only
 *   php tools/2026-09-20-import-homepage.php --apply   # write to the page
 *
 * @package ImpactFlowPortfolio
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 'CLI only.' );
}

$_SERVER['HTTP_HOST']   = 'impact-flow.local';
$_SERVER['REQUEST_URI'] = '/';

require dirname( __DIR__, 4 ) . '/wp-load.php';

$apply  = in_array( '--apply', $argv, true );
$source = get_stylesheet_directory() . '/assets/content/2026-09-20-homepage-blocks.html';

if ( ! is_readable( $source ) ) {
	fwrite( STDERR, "Missing block source: {$source}\n" );
	exit( 1 );
}

$markup = file_get_contents( $source );
$blocks = parse_blocks( $markup );

/**
 * Recursively report unparsed freeform content.
 *
 * @param array  $blocks  Parsed blocks.
 * @param string $prefix  Indent label.
 * @param int    $depth   Current depth.
 * @return int Number of problems found.
 */
function impactflow_report_freeform( array $blocks, string $prefix = '', int $depth = 0 ): int {
	$problems = 0;
	foreach ( $blocks as $index => $block ) {
		$name = $block['blockName'] ?? null;
		$pos  = str_repeat( '  ', $depth ) . ( $name ? $name : 'freeform' );
		if ( null === $name ) {
			if ( '' !== trim( (string) $block['innerHTML'] ) ) {
				echo "[freeform HTML @ {$prefix}{$index}] " . substr( trim( wp_strip_all_tags( $block['innerHTML'] ) ), 0, 60 ) . "\n";
				++$problems;
			}
		} else {
			echo "- {$pos}\n";
			if ( ! empty( $block['innerBlocks'] ) ) {
				$problems += impactflow_report_freeform( $block['innerBlocks'], $prefix . $index . '.', $depth + 1 );
			}
		}
	}
	return $problems;
}

echo "Parsed " . count( $blocks ) . " top-level blocks:\n";
$problems = impactflow_report_freeform( $blocks );

$front_id = (int) get_option( 'page_on_front' );
echo "\nFront page ID: {$front_id}\n";

if ( $problems > 0 ) {
	echo "\nValidation problems: {$problems}\n";
	exit( 1 );
}
echo "Validation clean.\n";

if ( ! $apply ) {
	echo "\nDry run only. Re-run with --apply to update the page.\n";
	exit( 0 );
}

if ( ! $front_id ) {
	fwrite( STDERR, "No static front page configured.\n" );
	exit( 1 );
}

// The homepage keeps the approved design's form and data attributes verbatim.
// This is a controlled admin import, so skip KSES for the write only.
kses_remove_filters();

$result = wp_update_post(
	array(
		'ID'           => $front_id,
		'post_content' => $markup,
	),
	true
);

kses_init_filters();

if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'Update failed: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}

echo "Updated page {$front_id}. Edit link: " . admin_url( "post.php?post={$front_id}&action=edit" ) . "\n";