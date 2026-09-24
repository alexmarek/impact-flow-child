<?php
/**
 * Build the portfolio homepage as editable child-pattern variants.
 *
 * The approved v2.1 HTML remains the visual source of truth. This builder
 * converts its sections to core blocks, adds the matching ImpactFlow parent
 * component classes, writes individual child patterns and can install the
 * resulting block tree on the configured static front page.
 *
 * Usage from the child theme directory:
 *   php tools/2026-09-23-build-homepage.php
 *   php tools/2026-09-23-build-homepage.php --apply
 *
 * @package ImpactFlowPortfolio
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	exit( 'CLI only.' );
}

$theme_dir  = dirname( __DIR__ );
$design     = $theme_dir . '/assets/design/2026-09-20-impact-flow-homepage-v2-1.html';
$apply      = in_array( '--apply', $argv, true );
$source     = file_get_contents( $design );

if ( false === $source ) {
	fwrite( STDERR, "Unable to read {$design}.\n" );
	exit( 1 );
}

/** Encode block attributes while keeping copy readable in source. */
function ifp_attrs( array $attributes ): string {
	return json_encode( $attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ?: '{}';
}

/** Return an element's HTML contents. */
function ifp_inner_html( DOMElement $element ): string {
	$html = '';
	foreach ( $element->childNodes as $child ) {
		$html .= $element->ownerDocument->saveHTML( $child );
	}
	return trim( $html );
}

/** Return escaped HTML attributes used when preserving a link. */
function ifp_link_html( DOMElement $element ): string {
	$attributes = array();
	foreach ( array( 'href', 'class', 'target', 'rel', 'aria-label' ) as $name ) {
		if ( $element->hasAttribute( $name ) ) {
			$attributes[] = sprintf(
				'%s="%s"',
				$name,
				htmlspecialchars( $element->getAttribute( $name ), ENT_QUOTES | ENT_HTML5 )
			);
		}
	}
	return '<a ' . implode( ' ', $attributes ) . '>' . ifp_inner_html( $element ) . '</a>';
}

/** Add one or more classes without duplicating existing values. */
function ifp_add_classes( DOMElement $element, string $classes ): void {
	$current = preg_split( '/\s+/', trim( $element->getAttribute( 'class' ) ) ) ?: array();
	$add     = preg_split( '/\s+/', trim( $classes ) ) ?: array();
	$all     = array_values( array_unique( array_filter( array_merge( $current, $add ) ) ) );
	$element->setAttribute( 'class', implode( ' ', $all ) );
}

/** Add classes to every element matching an XPath query. */
function ifp_add_xpath_classes( DOMXPath $xpath, string $query, string $classes ): void {
	$nodes = $xpath->query( $query );
	if ( ! $nodes ) {
		return;
	}
	foreach ( $nodes as $node ) {
		if ( $node instanceof DOMElement ) {
			ifp_add_classes( $node, $classes );
		}
	}
}

/** XPath fragment matching a whole class token. */
function ifp_has_class( string $class ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
}

/** Core paragraph block, retaining inline markup. */
function ifp_paragraph_block( string $html, string $class = '' ): string {
	$attrs      = '' !== $class ? array( 'className' => $class ) : array();
	$attr_text  = $attrs ? ' ' . ifp_attrs( $attrs ) : '';
	$class_text = '' !== $class ? ' class="' . htmlspecialchars( $class, ENT_QUOTES | ENT_HTML5 ) . '"' : '';
	return "<!-- wp:paragraph{$attr_text} -->\n<p{$class_text}>{$html}</p>\n<!-- /wp:paragraph -->";
}

/** Convert one design element to core block markup. */
function ifp_element_to_blocks( DOMElement $element, string $section_name = '' ): string {
	$tag   = strtolower( $element->tagName );
	$class = trim( $element->getAttribute( 'class' ) );
	$id    = trim( $element->getAttribute( 'id' ) );

	if ( 'p' === $tag ) {
		return ifp_paragraph_block( ifp_inner_html( $element ), $class );
	}

	if ( preg_match( '/^h([1-6])$/', $tag, $matches ) ) {
		$level      = (int) $matches[1];
		$attributes = array();
		if ( 2 !== $level ) {
			$attributes['level'] = $level;
		}
		if ( '' !== $class ) {
			$attributes['className'] = $class;
		}
		$attr_text  = $attributes ? ' ' . ifp_attrs( $attributes ) : '';
		$class_text = ' class="' . htmlspecialchars( trim( 'wp-block-heading ' . $class ), ENT_QUOTES | ENT_HTML5 ) . '"';
		return "<!-- wp:heading{$attr_text} -->\n<{$tag}{$class_text}>" . ifp_inner_html( $element ) . "</{$tag}>\n<!-- /wp:heading -->";
	}

	if ( in_array( $tag, array( 'ul', 'ol' ), true ) ) {
		$attributes = array();
		if ( 'ol' === $tag ) {
			$attributes['ordered'] = true;
		}
		if ( '' !== $class ) {
			$attributes['className'] = $class;
		}
		$attr_text  = $attributes ? ' ' . ifp_attrs( $attributes ) : '';
		$class_text = trim( 'wp-block-list ' . $class );
		$items      = array();
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof DOMElement && 'li' === strtolower( $child->tagName ) ) {
				$items[] = "<!-- wp:list-item -->\n<li>" . ifp_inner_html( $child ) . "</li>\n<!-- /wp:list-item -->";
			}
		}
		return "<!-- wp:list{$attr_text} -->\n<{$tag} class=\"{$class_text}\">\n" . implode( "\n", $items ) . "\n</{$tag}>\n<!-- /wp:list -->";
	}

	if ( 'form' === $tag ) {
		return "<!-- wp:group {\"className\":\"if-form\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group if-form\">\n<!-- wp:shortcode -->\n[fluentform id=\"1\"]\n<!-- /wp:shortcode -->\n</div>\n<!-- /wp:group -->";
	}

	if ( 'a' === $tag ) {
		return ifp_paragraph_block( ifp_link_html( $element ), 'if-portfolio-link' );
	}

	if ( 'button' === $tag ) {
		if ( false !== strpos( ' ' . $class . ' ', ' style-sample ' ) ) {
			return ifp_paragraph_block( ifp_inner_html( $element ), $class );
		}
		$label = trim( strip_tags( ifp_inner_html( $element ) ) );
		return "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button {\"className\":\"is-style-outline {$class}\"} -->\n<div class=\"wp-block-button is-style-outline {$class}\"><a class=\"wp-block-button__link wp-element-button {$class}\" href=\"#contact\">" . htmlspecialchars( $label, ENT_QUOTES | ENT_HTML5 ) . "</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->";
	}

	if ( 'details' === $tag ) {
		$summary = 'More';
		$children = array();
		foreach ( $element->childNodes as $child ) {
			if ( ! $child instanceof DOMElement ) {
				continue;
			}
			if ( 'summary' === strtolower( $child->tagName ) ) {
				$summary = trim( $child->textContent );
				continue;
			}
			$children[] = ifp_element_to_blocks( $child );
		}
		$attrs      = '' !== $class ? ' ' . ifp_attrs( array( 'className' => $class ) ) : '';
		$class_text = trim( 'wp-block-details ' . $class );
		return "<!-- wp:details{$attrs} -->\n<details class=\"{$class_text}\"><summary>" . htmlspecialchars( $summary, ENT_QUOTES | ENT_HTML5 ) . "</summary>\n" . implode( "\n\n", $children ) . "\n</details>\n<!-- /wp:details -->";
	}

	if ( in_array( $tag, array( 'span', 'b', 'small' ), true ) ) {
		$parent_class = $element->parentNode instanceof DOMElement ? $element->parentNode->getAttribute( 'class' ) : '';
		$helper       = '';
		if ( false !== strpos( $parent_class, 'service-promises' ) || ( $element->parentNode instanceof DOMElement && 'article' === strtolower( $element->parentNode->tagName ) && $element->parentNode->parentNode instanceof DOMElement && false !== strpos( $element->parentNode->parentNode->getAttribute( 'class' ), 'service-promises' ) ) ) {
			$helper = 'service-index';
		} elseif ( false !== strpos( $parent_class, 'cap-row' ) ) {
			$helper = 'cap-index if-services-editorial__number';
		} elseif ( false !== strpos( $parent_class, 'step' ) ) {
			$helper = 'step-index if-steps__num';
		} elseif ( false !== strpos( $parent_class, 'hero-strip' ) || ( $element->parentNode instanceof DOMElement && $element->parentNode->parentNode instanceof DOMElement && false !== strpos( $element->parentNode->parentNode->getAttribute( 'class' ), 'hero-strip' ) ) ) {
			$helper = 'hero-strip-label';
		} elseif ( false !== strpos( $parent_class, 'experience-line' ) ) {
			$helper = 'experience-value';
		} elseif ( false !== strpos( $parent_class, 'work-count' ) ) {
			$helper = 'b' === $tag ? 'work-count-value if-proof-facts__value' : 'work-count-label if-proof-facts__label';
		}
		return ifp_paragraph_block( ifp_inner_html( $element ), trim( $class . ' ' . $helper ) );
	}

	if ( 'div' === $tag && ( false !== strpos( ' ' . $class . ' ', ' amount ' ) || false !== strpos( ' ' . $class . ' ', ' term ' ) ) ) {
		return ifp_paragraph_block( ifp_inner_html( $element ), $class );
	}

	if ( in_array( $tag, array( 'section', 'div', 'article', 'aside' ), true ) ) {
		$attributes = array();
		if ( 'div' !== $tag ) {
			$attributes['tagName'] = $tag;
		}
		if ( '' !== $id ) {
			$attributes['anchor'] = $id;
		}
		if ( '' !== $class ) {
			$attributes['className'] = $class;
		}
		if ( '' !== $section_name ) {
			$attributes['metadata'] = array( 'name' => $section_name );
		}
		$children = array();
		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				$children[] = ifp_element_to_blocks( $child );
			}
		}
		$attr_text  = $attributes ? ' ' . ifp_attrs( $attributes ) : '';
		$html_attrs = '' !== $id ? ' id="' . htmlspecialchars( $id, ENT_QUOTES | ENT_HTML5 ) . '"' : '';
		$html_attrs .= ' class="' . htmlspecialchars( trim( 'wp-block-group ' . $class ), ENT_QUOTES | ENT_HTML5 ) . '"';
		return "<!-- wp:group{$attr_text} -->\n<{$tag}{$html_attrs}>\n" . implode( "\n\n", array_filter( $children ) ) . "\n</{$tag}>\n<!-- /wp:group -->";
	}

	return '';
}

$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->formatOutput       = false;
libxml_use_internal_errors( true );
$dom->loadHTML( $source, LIBXML_NOERROR | LIBXML_NOWARNING );
libxml_clear_errors();
$xpath = new DOMXPath( $dom );

// The static design fills the hero preview with JavaScript. Copy the same
// Clinical sample into the block tree so both editor and frontend show it.
$hero_sample = $dom->getElementById( 'hero-sample' );
$clinical    = $dom->getElementById( 'clinical' );
if ( $hero_sample instanceof DOMElement && $clinical instanceof DOMElement ) {
	$preview = $xpath->query( ".//button[" . ifp_has_class( 'style-sample' ) . ']/*[1]', $clinical );
	if ( $preview && $preview->item( 0 ) instanceof DOMElement ) {
		$hero_sample->appendChild( $preview->item( 0 )->cloneNode( true ) );
		ifp_add_classes( $hero_sample, 'style-sample' );
	}
}

// Declare each section as a child presentation variant of an existing parent
// component. The canonical design classes remain alongside the parent API.
$section_classes = array(
	'hero'          => 'if-anim if-hero-contained if-portfolio-hero',
	'partner'       => 'if-anim if-services-editorial if-portfolio-partner',
	'styles'        => 'if-cards-section if-portfolio-styles',
	'work'          => 'if-cards-section if-portfolio-work',
	'possibilities' => 'if-anim if-services-editorial if-portfolio-capabilities',
	'consulting'    => 'if-anim if-cta if-cta-split if-portfolio-consulting',
	'process'       => 'if-anim if-steps if-steps-horizontal if-portfolio-process',
	'pricing'       => 'if-anim if-pricing-section if-pricing-tiers if-portfolio-pricing',
	'contact'       => 'if-anim if-contact if-contact-split if-portfolio-contact',
);

$main = $dom->getElementById( 'main' );
if ( ! $main instanceof DOMElement ) {
	fwrite( STDERR, "Approved design has no #main element.\n" );
	exit( 1 );
}

foreach ( $section_classes as $key => $classes ) {
	$section = $dom->getElementById( $key );
	if ( ! $section instanceof DOMElement ) {
		$section = $xpath->query( './/section[' . ifp_has_class( $key ) . ']', $main )->item( 0 );
	}
	if ( $section instanceof DOMElement ) {
		ifp_add_classes( $section, $classes );
	}
}

// The approved HTML originally used the styles section itself as the width
// wrapper. WordPress pattern backgrounds belong on the full-width section
// shell, with a separate inner wrapper constraining the content.
$styles_section = $dom->getElementById( 'styles' );
if ( $styles_section instanceof DOMElement ) {
	$classes = preg_split( '/\s+/', trim( $styles_section->getAttribute( 'class' ) ) ) ?: array();
	$classes = array_values( array_filter( $classes, static fn( string $class ): bool => 'wrap' !== $class ) );
	$styles_section->setAttribute( 'class', implode( ' ', $classes ) );

	$styles_wrap = $dom->createElement( 'div' );
	$styles_wrap->setAttribute( 'class', 'wrap' );
	while ( $styles_section->firstChild ) {
		$styles_wrap->appendChild( $styles_section->firstChild );
	}
	$styles_section->appendChild( $styles_wrap );
}

// Parent pattern anatomy on the matching inner containers.
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'style-grid' ) . ']', 'if-cards-grid is-style-if-card-text' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'style-card' ) . ']', 'if-card' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'style-copy' ) . ']', 'if-card__body' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'work-list' ) . ']', 'if-cards-list is-style-if-card-horizontal' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'work-row' ) . ']', 'if-card' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'work-counts' ) . ']', 'if-proof-facts__grid' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'possibility-grid' ) . ']', 'if-services-editorial__layout' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'cap-list' ) . ']', 'if-services-editorial__list' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'cap-row' ) . ']', 'if-services-editorial__item' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'steps' ) . ']', 'if-steps__row' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'step' ) . ']', 'if-steps__item' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'price-grid' ) . ']', 'if-pricing-grid' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'price-card' ) . ']', 'if-price-tier' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'amount' ) . ']', 'if-price-tier__price' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'term' ) . ']', 'if-price-tier__term' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'contact-grid' ) . ']', 'if-contact__grid' );
ifp_add_xpath_classes( $xpath, '//*[' . ifp_has_class( 'contact-copy' ) . ']', 'if-contact__details' );

$names = array(
	'hero'          => 'Hero — Portfolio',
	'partner'       => 'Services — Portfolio promise',
	'styles'        => 'Cards — Website styles',
	'work'          => 'Cards — Live work',
	'possibilities' => 'Services — Capabilities',
	'consulting'    => 'CTA — Enterprise consulting',
	'process'       => 'Steps — Portfolio process',
	'pricing'       => 'Pricing — Portfolio services',
	'contact'       => 'Contact — Portfolio enquiry',
);

$sections = array();
foreach ( $main->childNodes as $child ) {
	if ( ! $child instanceof DOMElement || 'section' !== strtolower( $child->tagName ) ) {
		continue;
	}
	$key = trim( $child->getAttribute( 'id' ) );
	if ( '' === $key ) {
		$tokens = ' ' . $child->getAttribute( 'class' ) . ' ';
		foreach ( array_keys( $names ) as $candidate ) {
			if ( false !== strpos( $tokens, ' ' . $candidate . ' ' ) ) {
				$key = $candidate;
				break;
			}
		}
	}
	if ( ! isset( $names[ $key ] ) ) {
		continue;
	}
	$sections[ $key ] = ifp_element_to_blocks( $child, $names[ $key ] );

	$header = "<?php\n/**\n * Title: {$names[$key]}\n * Slug: impact-flow-portfolio/homepage-{$key}\n * Categories: impactflow-section\n * Description: Portfolio child variant using the matching ImpactFlow parent component anatomy.\n *\n * @package ImpactFlowPortfolio\n */\n?>\n";
	file_put_contents( $theme_dir . "/patterns/homepage-{$key}.php", $header . $sections[ $key ] . "\n" );
}

$inner   = implode( "\n\n", $sections );
$content = "<!-- wp:group {\"className\":\"if-portfolio-home\",\"metadata\":{\"name\":\"Impact Flow homepage\"},\"templateLock\":\"insert\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group if-portfolio-home\">\n{$inner}\n</div>\n<!-- /wp:group -->";

$homepage_header = "<?php\n/**\n * Title: Impact Flow portfolio homepage\n * Slug: impact-flow-portfolio/homepage\n * Categories: featured\n * Inserter: no\n * Description: Complete editable homepage assembled from ImpactFlow child variants.\n *\n * @package ImpactFlowPortfolio\n */\n?>\n";
file_put_contents( $theme_dir . '/patterns/homepage.php', $homepage_header . $content . "\n" );

preg_match_all( '~<style(?:\\s[^>]*)?>(.*?)</style>~s', $source, $style_matches );
$styles = array_values(
	array_filter(
		$style_matches[1] ?? array(),
		static fn( string $css ): bool => '' !== trim( $css )
	)
);

$bridge = <<<'CSS'


/* WordPress block bridge: the design and editor share this presentation. */
.if-portfolio-home {
	--wp--style--block-gap: 0;
}

body.home .wp-site-blocks,
body.home main,
body.home .wp-block-post-content,
.editor-styles-wrapper,
.editor-styles-wrapper .if-portfolio-home {
	margin: 0;
	max-width: none !important;
	padding: 0;
}

body.home main > *,
body.home .wp-block-post-content > *,
.if-portfolio-home > * {
	margin-block-start: 0;
	margin-block-end: 0;
}

.if-portfolio-home .if-hero-contained,
.if-portfolio-home .if-services-editorial,
.if-portfolio-home .if-cards-section,
.if-portfolio-home .if-cta-split,
.if-portfolio-home .if-steps-horizontal,
.if-portfolio-home .if-pricing-tiers,
.if-portfolio-home .if-contact-split {
	border-radius: 0;
	margin-block: 0;
	max-width: none;
	width: 100%;
}

.if-portfolio-home .hero.if-hero-contained {
	background: var(--deep) !important;
	color: var(--cream) !important;
	padding: 0 !important;
}

/* Homepage grounds and rhythm use only the approved design palette. Explicit
 * variant rules prevent parent brand surfaces and section gaps leaking in. */
.if-portfolio-home .partner.if-services-editorial {
	background: var(--cream) !important;
	color: var(--ink) !important;
	padding: 80px 0 !important;
}

.if-portfolio-home .styles.if-cards-section {
	background: var(--paper) !important;
	color: var(--ink) !important;
	padding: 92px 0 115px !important;
}

.if-portfolio-home .work.if-cards-section {
	background: var(--cream) !important;
	color: var(--ink) !important;
	padding: 96px 0 104px !important;
}

.if-portfolio-home .possibilities.if-services-editorial {
	background: var(--deep) !important;
	color: var(--cream) !important;
	padding: 88px 0 !important;
}

.if-portfolio-home .consulting.if-cta-split {
	background: var(--pale) !important;
	color: var(--ink) !important;
	padding: 64px 0 !important;
}

.if-portfolio-home .process.if-steps-horizontal {
	background: var(--cream) !important;
	color: var(--ink) !important;
	padding: 82px 0 92px !important;
}

.if-portfolio-home .pricing.if-pricing-tiers {
	background: var(--sage) !important;
	color: var(--ink) !important;
	padding: 92px 0 96px !important;
}

.if-portfolio-home .contact.if-contact-split {
	background: var(--deep) !important;
	color: var(--cream) !important;
	padding: 105px 0 90px !important;
}

.if-portfolio-home .style-sample {
	margin: 0;
}

.if-portfolio-home #hero-sample.style-sample {
	display: block;
}

.service-promises article > .service-index,
.cap-row > .cap-index,
.step > .step-index,
.hero-strip .hero-strip-label,
.experience-line .experience-value,
.work-counts .work-count-value,
.work-counts .work-count-label {
	margin: 0;
}

.service-promises article > .service-index {
	font: 28px var(--display);
	color: #486923;
}

.cap-row > .cap-index {
	font: 400 22px/1 var(--display);
	color: var(--lime);
}

.step > .step-index {
	font: 400 38px/1 var(--display);
	color: var(--green);
}

.if-portfolio-home .step-index.if-steps__num {
	background: transparent !important;
	border: 0 !important;
	border-radius: 0 !important;
	color: var(--green) !important;
	display: block;
	font: 400 38px/1 var(--display) !important;
	height: auto !important;
	padding: 0 !important;
	width: auto !important;
}

.experience-line .experience-value {
	font: 36px var(--display);
	white-space: nowrap;
}

.work-counts .work-count-value {
	display: block;
	font: 400 46px/1 var(--display);
	letter-spacing: -.05em;
	color: var(--blue);
}

.work-counts .work-count-label {
	display: block;
	font-size: 15px;
	color: var(--muted);
	margin-top: 12px;
	max-width: 30ch;
}

.if-portfolio-home .if-portfolio-link {
	margin: 0;
}

.if-portfolio-home .wp-block-buttons {
	margin: 0;
}

.if-portfolio-home .wp-block-button.open-guide {
	border: 0;
	padding: 0;
}

.if-portfolio-home .open-guide.wp-block-button__link {
	background: none;
	border: 0;
	border-bottom: 1px solid var(--ink);
	border-radius: 0;
	color: var(--ink);
	font-size: 12px;
	font-weight: 600;
	min-height: 0;
	padding: 0 0 3px;
}

.if-portfolio-home .price-card > .amount {
	font: 400 clamp(38px, 4vw, 52px)/1 var(--display);
	letter-spacing: -.05em;
	color: var(--blue);
	margin-bottom: 6px;
}

.if-portfolio-home .price-card > .term {
	font-size: 13px;
	letter-spacing: .1em;
	text-transform: uppercase;
	color: var(--muted);
	margin-bottom: 18px;
}

.if-portfolio-home .contact .if-form {
	margin: 0;
}

.if-portfolio-home .style-card.if-card {
	background: transparent !important;
	border: 0 !important;
	border-top: 1px solid var(--ink) !important;
	border-radius: 0 !important;
	box-shadow: none !important;
	display: flex;
	overflow: visible !important;
	padding: 14px 0 0 !important;
}

.if-portfolio-home .style-copy.if-card__body {
	display: grid;
	padding: 0 !important;
}

.if-portfolio-home .style-copy.if-card__body::before {
	content: none;
}

.if-portfolio-home .work-row.if-card {
	background: transparent !important;
	border: 0 !important;
	border-bottom: 1px solid var(--line) !important;
	border-radius: 0 !important;
	box-shadow: none !important;
	display: grid !important;
	grid-template-columns: .9fr 1.1fr auto !important;
	gap: 24px 40px !important;
	align-items: baseline;
	padding: 26px 0 !important;
}

.if-portfolio-home .price-grid.if-pricing-grid {
	display: grid !important;
	grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
	gap: 40px !important;
}

.if-portfolio-home .price-card.if-price-tier {
	background: transparent !important;
	border: 0 !important;
	border-top: 1px solid var(--ink) !important;
	border-radius: 0 !important;
	box-shadow: none !important;
	padding: 22px 0 0 !important;
	transform: none !important;
}

.if-portfolio-home .cap-row.if-services-editorial__item::after {
	display: none;
}

@media (hover: hover) {
	.if-portfolio-home .cap-row.if-services-editorial__item:hover {
		background: transparent;
		padding-inline: 0;
	}
}

.if-portfolio-home .step.if-steps__item::before {
	content: none;
}

.editor-styles-wrapper .if-portfolio-home {
	font-family: var(--body);
}

.editor-styles-wrapper .if-portfolio-home .wrap {
	max-width: 1240px !important;
}

.editor-styles-wrapper .if-portfolio-home [data-block] {
	max-width: none;
}

/* The parent header utilities remain available site-wide; the portfolio home
 * uses the approved dark mast and its intentionally spare navigation. */
body.home .if-portfolio-header.mast {
	background: var(--deep) !important;
	color: var(--cream) !important;
	padding: 0 !important;
}

body.home .if-portfolio-header .wp-block-site-title,
body.home .if-portfolio-header .wp-block-site-title a,
body.home .if-portfolio-header .wp-block-navigation-item__content {
	color: inherit;
}

body.home .if-portfolio-header .wp-block-woocommerce-customer-account,
body.home .if-portfolio-header .wp-block-woocommerce-mini-cart {
	display: none !important;
}

@media (max-width: 1000px) {
	.if-portfolio-home .work-row.if-card,
	.if-portfolio-home .price-grid.if-pricing-grid {
		grid-template-columns: 1fr !important;
	}
}

@media (max-width: 720px) {
	.if-portfolio-home .partner.if-services-editorial {
		padding-block: 56px !important;
	}

	.if-portfolio-home .styles.if-cards-section,
	.if-portfolio-home .possibilities.if-services-editorial,
	.if-portfolio-home .process.if-steps-horizontal,
	.if-portfolio-home .contact.if-contact-split {
		padding-block: 72px !important;
	}

	.if-portfolio-home .work.if-cards-section {
		padding-block: 68px 72px !important;
	}

	.if-portfolio-home .consulting.if-cta-split {
		padding-block: 52px !important;
	}

	.if-portfolio-home .pricing.if-pricing-tiers {
		padding-block: 64px 68px !important;
	}
}
CSS;

file_put_contents(
	$theme_dir . '/assets/css/portfolio-home.css',
	"/* Generated from the approved v2.1 homepage design. */\n" . trim( implode( "\n", $styles ) ) . $bridge . "\n"
);

echo 'Built ' . count( $sections ) . " section variants, homepage pattern and shared editor/frontend CSS.\n";

if ( ! $apply ) {
	echo "Dry run only. Re-run with --apply to update the static front page.\n";
	exit( 0 );
}

$_SERVER['HTTP_HOST']   = 'impact-flow.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 4 ) . '/wp-load.php';

$parsed = parse_blocks( $content );
$freeform = 0;
$walk = static function ( array $blocks ) use ( &$walk, &$freeform ): void {
	foreach ( $blocks as $block ) {
		if ( null === $block['blockName'] && '' !== trim( (string) $block['innerHTML'] ) ) {
			++$freeform;
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$walk( $block['innerBlocks'] );
		}
	}
};
$walk( $parsed );
if ( 0 !== $freeform ) {
	fwrite( STDERR, "Refusing to apply: {$freeform} freeform fragments found.\n" );
	exit( 1 );
}

$front_id = (int) get_option( 'page_on_front' );
if ( $front_id < 1 ) {
	fwrite( STDERR, "No static front page is configured.\n" );
	exit( 1 );
}

$backup_dir = $theme_dir . '/archive';
if ( ! is_dir( $backup_dir ) ) {
	mkdir( $backup_dir, 0775, true );
}
$existing    = get_post_field( 'post_content', $front_id );
$backup_file = $backup_dir . '/2026-09-23-rejected-pattern-homepage.html';
if ( ! file_exists( $backup_file ) ) {
	file_put_contents( $backup_file, (string) $existing );
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
	fwrite( STDERR, 'Homepage update failed: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}

// Let WordPress use the child theme's explicit front-page.html hierarchy
// instead of the development-era Page (no title) assignment.
delete_post_meta( $front_id, '_wp_page_template' );

echo "Updated page {$front_id}: " . admin_url( "post.php?post={$front_id}&action=edit" ) . "\n";
