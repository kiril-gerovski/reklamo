<?php
/**
 * Search engines and link previews.
 *
 * WooCommerce already prints Product structured data on product pages; this adds what
 * nothing else does: a description, Open Graph and Twitter tags, and the site-wide
 * Organization / WebSite / BreadcrumbList / FAQPage graph.
 *
 * @package Reklamo
 */

const REKLAMO_FAQ_SLUG = 'chesto-zadavani-vaprosi';

/** Readable one-line text from stored HTML; list items keep a separator. */
function reklamo_seo_text( string $html, int $limit = 0 ): string {
	$html = preg_replace( '#</li>#i', ', ', $html );
	$text = trim( preg_replace( '#\s+#u', ' ', wp_strip_all_tags( (string) $html ) ) );
	$text = trim( $text, ' ,;·' );
	return $limit > 0 ? rtrim( wp_html_excerpt( $text, $limit, '' ) ) : $text;
}

/** What this request is about: title, description, image and Open Graph type. */
function reklamo_seo_context(): array {
	$ctx = array(
		'title'       => wp_get_document_title(),
		'description' => '',
		'type'        => 'website',
		'url'         => home_url( add_query_arg( array() ) ),
		'image'       => 0,
		'product'     => null,
	);

	if ( is_front_page() ) {
		// The site tagline is short because it also ends up in the <title>; the company tagline
		// from the settings is a full sentence and reads better as a search result.
		$ctx['description'] = reklamo_setting( 'tagline', (string) get_bloginfo( 'description' ) );
		$ctx['url']         = home_url( '/' );
	} elseif ( is_singular( 'product' ) && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( get_queried_object_id() );
		if ( $product ) {
			$ctx['type']        = 'product';
			$ctx['product']     = $product;
			$ctx['description'] = reklamo_seo_text( $product->get_short_description(), 160 );
			$ctx['image']       = (int) $product->get_image_id();
			$ctx['url']         = (string) get_permalink( $product->get_id() );
		}
	} elseif ( is_singular() ) {
		$post               = get_post();
		$ctx['type']        = is_page() ? 'website' : 'article';
		$ctx['description'] = reklamo_seo_text( $post->post_excerpt ? $post->post_excerpt : $post->post_content, 160 );
		$ctx['image']       = (int) get_post_thumbnail_id( $post );
		$ctx['url']         = (string) get_permalink( $post );
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$term               = get_queried_object();
		$ctx['description'] = reklamo_seo_text( (string) ( $term->description ?? '' ), 160 );
		$ctx['url']         = (string) get_term_link( $term );
	}

	if ( '' === $ctx['description'] ) {
		$ctx['description'] = reklamo_seo_text( reklamo_setting( 'tagline', (string) get_bloginfo( 'description' ) ), 160 );
	}
	$ctx['title']       = html_entity_decode( $ctx['title'], ENT_QUOTES, 'UTF-8' );
	$ctx['description'] = html_entity_decode( $ctx['description'], ENT_QUOTES, 'UTF-8' );
	return $ctx;
}

/** Description, Open Graph and Twitter card. */
function reklamo_seo_meta(): void {
	if ( is_404() || is_search() ) {
		return;
	}
	$ctx  = reklamo_seo_context();
	$tags = array(
		array( 'name', 'description', $ctx['description'] ),
		array( 'property', 'og:site_name', get_bloginfo( 'name' ) ),
		array( 'property', 'og:locale', str_replace( '-', '_', get_bloginfo( 'language' ) ) ),
		array( 'property', 'og:type', $ctx['type'] ),
		array( 'property', 'og:title', $ctx['title'] ),
		array( 'property', 'og:description', $ctx['description'] ),
		array( 'property', 'og:url', $ctx['url'] ),
	);

	// A page with a picture of its own shows it; everything else shows the brand card, which is
	// the header lockup at the 1.91:1 the networks crop to.
	$src = $ctx['image'] ? wp_get_attachment_image_src( $ctx['image'], 'large' ) : false;
	if ( ! $src ) {
		$src = array( get_theme_file_uri( 'assets/img/share.jpg' ), 1200, 630 );
	}
	$alt = $ctx['image'] ? (string) get_post_meta( $ctx['image'], '_wp_attachment_image_alt', true ) : get_bloginfo( 'name' );

	$tags[] = array( 'property', 'og:image', $src[0] );
	$tags[] = array( 'property', 'og:image:width', (string) $src[1] );
	$tags[] = array( 'property', 'og:image:height', (string) $src[2] );
	$tags[] = array( 'property', 'og:image:alt', $alt );
	$tags[] = array( 'name', 'twitter:card', 'summary_large_image' );

	if ( $ctx['product'] ) {
		$tags[] = array( 'property', 'product:price:amount', wc_get_price_to_display( $ctx['product'] ) );
		$tags[] = array( 'property', 'product:price:currency', get_woocommerce_currency() );
		$tags[] = array( 'property', 'product:availability', $ctx['product']->is_in_stock() ? 'in stock' : 'out of stock' );
	}

	foreach ( $tags as $tag ) {
		if ( '' === (string) $tag[2] ) {
			continue;
		}
		printf(
			"<meta %s=\"%s\" content=\"%s\">\n",
			esc_attr( $tag[0] ),
			esc_attr( $tag[1] ),
			'og:image' === $tag[1] || 'og:url' === $tag[1] ? esc_url( $tag[2] ) : esc_attr( $tag[2] )
		);
	}
}
add_action( 'wp_head', 'reklamo_seo_meta', 4 );

/** The trader, as schema.org, from the same settings the footer and the legal pages use. */
function reklamo_seo_organization(): array {
	$org = array(
		'@type' => 'Organization',
		'@id'   => home_url( '/#organization' ),
		'name'  => reklamo_setting( 'company_name', get_bloginfo( 'name' ) ),
		'url'   => home_url( '/' ),
	);
	$map = array(
		'email'     => reklamo_setting( 'email' ),
		'telephone' => reklamo_setting( 'phone' ),
		'vatID'     => reklamo_setting( 'vat' ),
		'taxID'     => reklamo_setting( 'eik' ),
	);
	foreach ( array_filter( $map ) as $key => $value ) {
		$org[ $key ] = $value;
	}
	$street = reklamo_setting( 'legal_address', reklamo_setting( 'address' ) );
	if ( $street ) {
		$org['address'] = array(
			'@type'          => 'PostalAddress',
			'streetAddress'  => $street,
			'addressCountry' => 'BG',
		);
	}
	$social = array_values( array_filter( array_map( 'reklamo_setting', array( 'facebook', 'instagram', 'linkedin' ) ) ) );
	if ( $social ) {
		$org['sameAs'] = $social;
	}
	$icon = get_site_icon_url( 512 );
	if ( $icon ) {
		$org['logo'] = $icon;
	}
	return $org;
}

/** Home → (shop) → this page. */
function reklamo_seo_breadcrumb(): array {
	if ( is_front_page() || ! is_singular() ) {
		return array();
	}
	$items = array(
		array(
			'name' => get_bloginfo( 'name' ),
			'item' => home_url( '/' ),
		),
	);
	if ( is_singular( 'product' ) && function_exists( 'wc_get_page_id' ) ) {
		$shop = wc_get_page_id( 'shop' );
		if ( $shop > 0 ) {
			$items[] = array(
				'name' => get_the_title( $shop ),
				'item' => (string) get_permalink( $shop ),
			);
		}
	}
	$items[] = array(
		'name' => get_the_title(),
		'item' => (string) get_permalink(),
	);

	$list = array();
	foreach ( $items as $i => $item ) {
		$list[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $item['name'],
			'item'     => $item['item'],
		);
	}
	return array(
		'@type'           => 'BreadcrumbList',
		'@id'             => get_permalink() . '#breadcrumb',
		'itemListElement' => $list,
	);
}

/**
 * FAQ page: every <h3> is a question and everything up to the next one is its answer,
 * which is how the seeded page is written. Nothing is emitted until it has real content.
 */
function reklamo_seo_faq(): array {
	if ( ! is_page( REKLAMO_FAQ_SLUG ) ) {
		return array();
	}
	$blocks = preg_split( '#<h3[^>]*>#i', (string) get_post_field( 'post_content', get_queried_object_id() ) );
	array_shift( $blocks );
	$pairs = array();
	foreach ( (array) $blocks as $block ) {
		list( $question, $answer ) = array_pad( preg_split( '#</h3>#i', (string) $block, 2 ), 2, '' );
		$question                  = reklamo_seo_text( $question );
		$answer                    = reklamo_seo_text( $answer );
		if ( '' !== $question && '' !== $answer ) {
			$pairs[] = array(
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer,
				),
			);
		}
	}
	return $pairs ? array(
		'@type'      => 'FAQPage',
		'@id'        => get_permalink() . '#faq',
		'mainEntity' => $pairs,
	) : array();
}

/** One @graph with everything WooCommerce does not already print. */
function reklamo_seo_jsonld(): void {
	if ( is_404() ) {
		return;
	}
	$graph = array(
		reklamo_seo_organization(),
		array(
			'@type'           => 'WebSite',
			'@id'             => home_url( '/#website' ),
			'url'             => home_url( '/' ),
			'name'            => get_bloginfo( 'name' ),
			'inLanguage'      => get_bloginfo( 'language' ),
			'publisher'       => array( '@id' => home_url( '/#organization' ) ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		),
	);
	foreach ( array( reklamo_seo_breadcrumb(), reklamo_seo_faq() ) as $node ) {
		if ( $node ) {
			$graph[] = $node;
		}
	}

	$json = wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);
	echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode escapes for this context.
}
add_action( 'wp_head', 'reklamo_seo_jsonld', 6 );

/**
 * WooCommerce flattens the short description into "20 тефтера20 химикалки" because the
 * list markup is stripped without separators.
 */
function reklamo_seo_product_description( array $markup, $product ): array {
	$short = $product->get_short_description();
	if ( $short ) {
		$markup['description'] = reklamo_seo_text( $short );
	}
	return $markup;
}
add_filter( 'woocommerce_structured_data_product', 'reklamo_seo_product_description', 10, 2 );

/** Cart, checkout and account pages carry nothing worth indexing. */
function reklamo_seo_sitemap_pages( array $args, string $post_type ): array {
	if ( 'page' !== $post_type || ! function_exists( 'wc_get_page_id' ) ) {
		return $args;
	}
	$skip = array_filter( array_map( 'wc_get_page_id', array( 'cart', 'checkout', 'myaccount' ) ), static fn( $id ) => $id > 0 );
	if ( $skip ) {
		$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), $skip );
	}
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'reklamo_seo_sitemap_pages', 10, 2 );

/** No author archives on a shop: keep the users sitemap out of it. */
add_filter( 'wp_sitemaps_add_provider', static fn( $provider, $name ) => 'users' === $name ? false : $provider, 10, 2 );
