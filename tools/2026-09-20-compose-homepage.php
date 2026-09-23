<?php
/**
 * Compose the Home page from registered ImpactFlow patterns.
 *
 * Parent patterns are pulled from the pattern registry, structurally trimmed
 * (e.g. the fourth service, the annual price lines) and re-worded with the
 * Impact Flow copy. Two portfolio-specific sections come from child patterns.
 * The result is written to the front page as ordinary, editable blocks.
 *
 * Usage (from the child theme directory):
 *   php tools/2026-09-20-compose-homepage.php           # dry run
 *   php tools/2026-09-20-compose-homepage.php --apply   # write the page
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

/**
 * Fetch a registered pattern's block markup.
 *
 * @param string $slug Pattern name.
 * @return string
 */
function compose_pattern( string $slug ): string {
	$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( $slug );
	if ( ! $pattern ) {
		fwrite( STDERR, "Missing pattern: {$slug}\n" );
		exit( 1 );
	}
	return $pattern['content'];
}

/**
 * Replace the inner HTML of a block's opening/closing tag pair.
 *
 * @param string $html  Block innerHTML.
 * @param string $inner New inner content (may contain inline HTML).
 * @return string
 */
function compose_retag( string $html, string $inner ): string {
	if ( preg_match( '~^(\s*)(<[^>]+>)(.*)(</[^>]+>)(\s*)$~s', $html, $m ) ) {
		return $m[1] . $m[2] . $inner . $m[4] . $m[5];
	}
	return $inner;
}

/**
 * Recursively remove blocks matching a predicate.
 *
 * @param array    $blocks Parsed blocks.
 * @param callable $pred   Predicate receiving a block.
 * @return array
 */
function compose_remove( array $blocks, callable $pred ): array {
	$out = array();
	foreach ( $blocks as $block ) {
		if ( $pred( $block ) ) {
			continue;
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = compose_remove( $block['innerBlocks'], $pred );
		}
		$out[] = $block;
	}
	return $out;
}

/**
 * Keep only the first N children of the block matching a class.
 *
 * @param array  $blocks    Parsed blocks.
 * @param string $className Container class to find.
 * @param int    $keep      Number of children to keep.
 * @return array
 */
function compose_trim_list( array $blocks, string $className, int $keep ): array {
	foreach ( $blocks as &$block ) {
		$cn = $block['attrs']['className'] ?? '';
		if ( false !== strpos( $cn, $className ) ) {
			$block['innerBlocks'] = array_slice( $block['innerBlocks'], 0, $keep );
		} elseif ( ! empty( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = compose_trim_list( $block['innerBlocks'], $className, $keep );
		}
	}
	return $blocks;
}

/**
 * Set block inner content in document order, keyed by exact className.
 *
 * @param array $blocks Parsed blocks (by reference).
 * @param array $map    className => list of inner HTML strings.
 */
function compose_set_text( array &$blocks, array $map ): void {
	$counters = array();
	$walk     = function ( array &$bs ) use ( &$walk, &$map, &$counters ) {
		foreach ( $bs as &$block ) {
			$cn = $block['attrs']['className'] ?? '';
			if ( isset( $map[ $cn ] ) ) {
				$index = $counters[ $cn ] ?? 0;
				if ( isset( $map[ $cn ][ $index ] ) ) {
					$block['innerHTML']    = compose_retag( $block['innerHTML'], $map[ $cn ][ $index ] );
					$block['innerContent'] = array( $block['innerHTML'] );
				}
				$counters[ $cn ] = $index + 1;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$walk( $block['innerBlocks'] );
			}
		}
	};
	$walk( $blocks );
}

/**
 * Rebuild every block's innerContent so it matches its innerBlocks.
 *
 * Structural edits (removing/trimming inner blocks) leave innerContent stale,
 * which serialize_blocks cannot reconcile. This re-derives it.
 *
 * @param array $blocks Parsed blocks.
 * @return array
 */
function compose_normalize( array $blocks ): array {
	foreach ( $blocks as &$block ) {
		if ( ! empty( $block['innerBlocks'] ) ) {
			$content = is_array( $block['innerContent'] ) ? $block['innerContent'] : array();
			$open    = isset( $content[0] ) ? $content[0] : '';
			$close   = ! empty( $content ) ? $content[ count( $content ) - 1 ] : '';
			$rebuilt = array( $open );
			foreach ( $block['innerBlocks'] as $unused ) {
				$rebuilt[] = null;
			}
			$rebuilt[]                = $close;
			$block['innerContent']    = $rebuilt;
			$block['innerBlocks']     = compose_normalize( $block['innerBlocks'] );
		} else {
			$block['innerContent'] = array( $block['innerHTML'] );
		}
	}
	return $blocks;
}

/**
 * Apply a map of string replacements and report any misses.
 *
 * @param string $content Block markup.
 * @param array  $map     from => to.
 * @return string
 */
function compose_replace( string $content, array $map ): string {
	foreach ( $map as $from => $to ) {
		if ( false === strpos( $content, $from ) ) {
			echo "  ! not found: " . substr( $from, 0, 70 ) . "\n";
			continue;
		}
		$content = str_replace( $from, $to, $content );
	}
	return $content;
}

$sections = array();

/* 1. Hero ---------------------------------------------------------------- */
$hero = compose_pattern( 'impact-flow/hero-contained' );
$hero = compose_replace(
	$hero,
	array(
		'<p class="if-eyebrow has-accent-color has-text-color has-small-font-size">Now booking</p>' => '<p class="if-eyebrow has-accent-color has-text-color has-small-font-size">Your business, confidently online</p>',
		'<h1 class="wp-block-heading has-x-large-font-size">A clinic that takes the time to listen</h1>' => '<h1 class="wp-block-heading has-x-large-font-size">Looks great. Works properly. Stays looked after.</h1>',
		'<p class="has-large-font-size"><strong>Sixty-minute first assessments, never fifteen.</strong></p>' => '<p class="has-large-font-size"><strong>Design, build and ongoing care from one team.</strong></p>',
		'<p>Your first visit is a full hour so we can assess how you move, explain what we find and agree a plan before any treatment begins.</p>' => '<p>A website that feels right for your business and easier for your customers to use. We plan it, build it and keep it running, so you can stay focused on the business itself.</p>',
		'<a class="wp-block-button__link wp-element-button">Book an assessment</a>' => '<a class="wp-block-button__link wp-element-button" href="#contact">Tell us what you need</a>',
		'<a class="wp-block-button__link wp-element-button">Our services</a>' => '<a class="wp-block-button__link wp-element-button" href="#styles">Explore the possibilities</a>',
	)
);
$sections[] = $hero;

/* 2. Services / promises ------------------------------------------------ */
$services = compose_pattern( 'impact-flow/services-editorial' );
$services = compose_replace(
	$services,
	array(
		'<p class="if-eyebrow has-primary-color has-text-color has-small-font-size">How we help</p>' => '<p class="if-eyebrow has-primary-color has-text-color has-small-font-size">The whole website, taken care of</p>',
		'<h2 class="wp-block-heading has-xx-large-font-size">Treatment built around how you move</h2>' => '<h2 class="wp-block-heading has-xx-large-font-size">You run your business. We take care of your website.</h2>',
		'<p>Start with what is getting in your way. We will assess it, explain it clearly and build a plan around your goals.</p>' => '<p>You don’t need to know which software to choose or what keeps a website online. We explain the choices, organise the work and keep everything current — so nothing depends on you chasing it.</p>',
		'Sports injury recovery' => 'A look you can be proud of',
		'Progressive rehabilitation built around your sport and your return to training.' => 'Your website is reviewed and refined from the first layout to the last detail, so it feels like your business and works properly on phones, too.',
		'Long-term pain support' => 'Built around your customers',
		'A practical plan that adapts as symptoms, movement and confidence improve.' => 'Make it easy to understand what you offer, find the right information and get in touch, book or buy.',
		'Post-operative rehabilitation' => 'Care beyond launch',
		'Structured support from early recovery through strength and return to activity.' => 'Updates, backups and security checks run on a schedule, with monitoring that catches most problems before anyone notices.',
	)
);
$services_blocks = parse_blocks( $services );
$services_blocks = compose_trim_list( $services_blocks, 'if-services-editorial__list', 3 );
$sections[]      = serialize_blocks( compose_normalize( $services_blocks ) );

/* 3. Website styles (child pattern) ------------------------------------ */
$sections[] = compose_pattern( 'impact-flow-portfolio/homepage-styles' );

/* 4. Live work (child pattern) ----------------------------------------- */
$sections[] = compose_pattern( 'impact-flow-portfolio/homepage-work' );

/* 5. Proof facts ------------------------------------------------------- */
$proof = compose_pattern( 'impact-flow/proof-facts' );
$proof_blocks = parse_blocks( $proof );
compose_set_text(
	$proof_blocks,
	array(
		'if-proof-facts__value' => array( '2014', '3', '2', '99.9%' ),
		'if-proof-facts__label' => array( 'Longest-running site', 'Countries served', 'Bilingual builds', 'Uptime under care' ),
	)
);
$sections[] = serialize_blocks( compose_normalize( $proof_blocks ) );

/* 6. Capabilities (feature grid) --------------------------------------- */
$features = compose_pattern( 'impact-flow/feature-grid' );
$features = compose_replace(
	$features,
	array(
		'<p class="has-text-align-center if-eyebrow has-primary-color has-text-color has-small-font-size">Why Impact Flow</p>' => '<p class="has-text-align-center if-eyebrow has-primary-color has-text-color has-small-font-size">Room to do more</p>',
		'<h2 class="wp-block-heading has-text-align-center has-x-large-font-size">Everything the site needs, in one place</h2>' => '<h2 class="wp-block-heading has-text-align-center has-x-large-font-size">Simple when it can be. Powerful when it needs to be.</h2>',
	)
);
$feature_blocks = parse_blocks( $features );
compose_set_text(
	$feature_blocks,
	array(
		'if-feature__title' => array( 'Speak your customers’ language', 'Sell online', 'Take bookings', 'Give people a private space', 'Connect the tools you use', 'Keep your content fresh' ),
		'if-feature__text'  => array(
			'Make your website available in several languages, so more people can understand your offer.',
			'Let customers browse products, place orders and pay through your website.',
			'Help people choose an appointment, reserve a table or book a place at an event.',
			'Create a place where staff or customers can sign in to find documents, information and useful tools.',
			'Share information with your existing business systems, so you spend less time copying it by hand.',
			'Change text, photos and everyday details yourself, with help available when you need it.',
		),
	)
);
$sections[] = serialize_blocks( compose_normalize( $feature_blocks ) );

/* 7. Consulting (CTA split) -------------------------------------------- */
$consulting = compose_pattern( 'impact-flow/cta-split' );
$consulting = compose_replace(
	$consulting,
	array(
		'<h2 class="wp-block-heading has-large-font-size">Grow your practice with a site that does the work</h2>' => '<h2 class="wp-block-heading has-large-font-size">Expert help with a bigger challenge</h2>',
		'<p>Strategy, build, hosting and ongoing care — set up in days, not months.</p>' => '<p>Already have a team? Impact Flow can work alongside them to plan a complex website, review an existing setup or help a difficult project move forward.</p>',
		'<a class="wp-block-button__link wp-element-button">Become a client</a>' => '<a class="wp-block-button__link wp-element-button" href="#contact">Talk through your project</a>',
	)
);
$sections[] = $consulting;

/* 8. Process (steps cards) --------------------------------------------- */
$steps = compose_pattern( 'impact-flow/steps-cards' );
$steps = compose_replace(
	$steps,
	array(
		'<p class="if-eyebrow has-primary-color has-text-color has-small-font-size">Getting started</p>' => '<p class="if-eyebrow has-primary-color has-text-color has-small-font-size">A clear way forward</p>',
		'<h2 class="wp-block-heading has-x-large-font-size">Your site, live in three steps</h2>' => '<h2 class="wp-block-heading has-x-large-font-size">It starts with a conversation</h2>',
	)
);
$step_blocks = parse_blocks( $steps );
compose_set_text(
	$step_blocks,
	array(
		'if-steps__title' => array( 'Tell us what you need', 'Agree the plan and price', 'Build it. Look after it.' ),
		'if-steps__text'  => array(
			'Your business, your customers and what you want the website to help with.',
			'You’ll know what’s included, what it costs and the expected timing before work begins.',
			'We review the design together, check that everything works and put it online.',
		),
	)
);
$sections[] = serialize_blocks( compose_normalize( $step_blocks ) );

/* 9. Pricing ----------------------------------------------------------- */
$pricing = compose_pattern( 'impact-flow/pricing-tiers' );
$pricing = compose_replace(
	$pricing,
	array(
		'<p class="if-eyebrow if-pricing-head__eyebrow has-accent-color has-text-color has-small-font-size">Treatment plans</p>' => '<p class="if-eyebrow if-pricing-head__eyebrow has-accent-color has-text-color has-small-font-size">What it costs</p>',
		'<h2 class="wp-block-heading has-text-align-center has-x-large-font-size">Clear pricing, agreed before we start</h2>' => '<h2 class="wp-block-heading has-text-align-center has-x-large-font-size">Clear numbers, before you ask</h2>',
		'<p class="has-text-align-center if-pricing-head__sub">Block bookings work out cheaper than paying per session, and unused sessions carry over for twelve months.</p>' => '<p class="has-text-align-center if-pricing-head__sub">Every project is quoted properly after one conversation. These are starting points, not estimates.</p>',
		'<a class="wp-block-button__link wp-element-button">Book a session</a>' => '<a class="wp-block-button__link wp-element-button" href="#contact">Get a quote</a>',
		'<a class="wp-block-button__link wp-element-button">Book a block</a>' => '<a class="wp-block-button__link wp-element-button" href="#contact">Start care</a>',
		'<a class="wp-block-button__link wp-element-button">Enquire</a>' => '<a class="wp-block-button__link wp-element-button" href="#contact">Enquire</a>',
	)
);
$pricing_blocks = parse_blocks( $pricing );
$pricing_blocks = compose_remove(
	$pricing_blocks,
	static fn( $b ) => false !== strpos( $b['attrs']['className'] ?? '', 'if-price-tier__price--annual' )
);
compose_set_text(
	$pricing_blocks,
	array(
		'if-price-tier__name'             => array( 'A new website', 'Ongoing care', 'Consulting' ),
		'if-price-tier__price if-price-tier__price--monthly' => array(
			'<strong>from [ €X,XXX ]</strong> 4–8 weeks',
			'<strong>from [ €XX ]</strong> per month',
			'<strong>[ €XXX ]</strong> per day',
		),
		'if-price-tier__feature'          => array(
			'Structure and content planning',
			'A design direction shaped around your business',
			'Built, tested and put online',
			'You own your domain, content and website',
			'Hosting, backups and security updates',
			'Small changes and content help',
			'A reply within two working days',
			'Monitoring that runs on a schedule',
			'Direct access to Alex',
			'Planning a complex website',
			'Reviewing an existing setup',
			'Connecting business systems',
			'A practical way through',
		),
	)
);
$sections[] = serialize_blocks( compose_normalize( $pricing_blocks ) );

/* 10. Contact ---------------------------------------------------------- */
$contact = compose_pattern( 'impact-flow/contact-split' );
$contact = compose_replace(
	$contact,
	array(
		'<p class="if-eyebrow has-primary-color has-text-color has-small-font-size">Contact</p>' => '<p class="if-eyebrow has-primary-color has-text-color has-small-font-size">Pull up a chair</p>',
		'<h2 class="wp-block-heading has-x-large-font-size">Tell us what you need</h2>' => '<h2 class="wp-block-heading has-x-large-font-size">What should your website do next?</h2>',
		'<p style="line-height:1.6">Send a few lines about your project and we will reply within one working day. Prefer to talk it through? The phone line is open in office hours.</p>' => '<p style="line-height:1.6">A new site, a fresh start or an idea that still needs a shape. Tell us where you are. You don’t need a polished brief or technical answers — start with the business problem and we’ll work out the rest together.</p>',
	)
);
$contact_blocks = parse_blocks( $contact );
compose_set_text(
	$contact_blocks,
	array(
		'if-contact__label' => array( 'Email', 'Based in', 'Response', 'Availability' ),
		'if-contact__value' => array( 'hello@impactflow.com', 'Bayreuth, Germany and Luby, Czech Republic', 'Within one working day', 'Taking on new projects' ),
	)
);
$sections[] = serialize_blocks( compose_normalize( $contact_blocks ) );

$content = implode( "\n\n", $sections );

$parsed = parse_blocks( $content );
echo 'Sections: ' . count( $sections ) . ', top-level blocks: ' . count( $parsed ) . "\n";
$freeform = 0;
$check    = function ( array $blocks ) use ( &$check, &$freeform ) {
	foreach ( $blocks as $b ) {
		if ( null === $b['blockName'] && '' !== trim( (string) $b['innerHTML'] ) ) {
			++$freeform;
		}
		if ( ! empty( $b['innerBlocks'] ) ) {
			$check( $b['innerBlocks'] );
		}
	}
};
$check( $parsed );
echo 'Freeform fragments: ' . $freeform . "\n";

if ( $freeform > 0 ) {
	fwrite( STDERR, "Refusing to write: unexpected freeform content.\n" );
	exit( 1 );
}

$front_id = (int) get_option( 'page_on_front' );

if ( ! $apply ) {
	echo "Dry run only (front page ID {$front_id}). Re-run with --apply to write.\n";
	exit( 0 );
}

kses_remove_filters();
$result = wp_update_post(
	array(
		'ID'           => $front_id,
		'post_content' => $content,
	),
	true
);
kses_init_filters();

if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'Update failed: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}

echo "Wrote {$front_id}. Edit: " . admin_url( "post.php?post={$front_id}&action=edit" ) . "\n";