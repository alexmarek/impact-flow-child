<?php
/**
 * Clean up WooCommerce demo content and configure the store for digital
 * products in the Czech Republic.
 *
 * Usage (from the child theme directory):
 *   php tools/2026-09-20-woocommerce-cleanup.php           # dry run
 *   php tools/2026-09-20-woocommerce-cleanup.php --apply   # make changes
 *
 * The demo products and sample posts are moved to Trash (recoverable). Demo
 * product categories are left in place and reported, not deleted.
 *
 * @package ImpactFlowPortfolio
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 'CLI only.' );
}

$_SERVER['HTTP_HOST']   = 'impact-flow.local';
$_SERVER['REQUEST_URI'] = '/';

require dirname( __DIR__, 4 ) . '/wp-load.php';

$apply = in_array( '--apply', $argv, true );

$products = get_posts(
	array(
		'post_type'      => 'product',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

$posts = get_posts(
	array(
		'post_type'      => 'post',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

$options = array(
	'woocommerce_currency'                     => 'EUR',
	'woocommerce_default_country'              => 'CZ',
	'woocommerce_currency_pos'                 => 'right_space',
	'woocommerce_price_decimal_sep'            => ',',
	'woocommerce_price_thousand_sep'           => ' ',
	'woocommerce_weight_unit'                  => 'kg',
	'woocommerce_dimension_unit'               => 'cm',
	'woocommerce_file_download_method'         => 'force',
	'woocommerce_downloads_require_login'      => 'no',
	'woocommerce_downloads_grant_access_after_payment' => 'yes',
);

echo "== Products to trash (" . count( $products ) . ") ==\n";
foreach ( $products as $id ) {
	echo "  #{$id} {$id}: " . get_the_title( $id ) . "\n";
}

echo "\n== Posts to trash (" . count( $posts ) . ") ==\n";
foreach ( $posts as $id ) {
	echo "  #{$id}: " . get_the_title( $id ) . "\n";
}

echo "\n== Option changes ==\n";
foreach ( $options as $key => $value ) {
	$old = get_option( $key );
	echo "  {$key}: '{$old}' -> '{$value}'\n";
}

$demo_cats = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	)
);
echo "\n== Demo product categories (left in place) ==\n";
if ( ! is_wp_error( $demo_cats ) ) {
	foreach ( $demo_cats as $term ) {
		echo "  #{$term->term_id} {$term->slug} ({$term->count})\n";
	}
}

if ( ! $apply ) {
	echo "\nDry run only. Re-run with --apply to make changes.\n";
	exit( 0 );
}

foreach ( $products as $id ) {
	wp_trash_post( $id );
}
foreach ( $posts as $id ) {
	wp_trash_post( $id );
}
foreach ( $options as $key => $value ) {
	update_option( $key, $value );
}

echo "\nDone: " . count( $products ) . " products and " . count( $posts ) . " posts trashed; " . count( $options ) . " options updated.\n";