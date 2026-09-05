<?php
/**
 * Title: Popular packages
 * Slug: reklamo/packages
 * Categories: reklamo
 * Description: Section heading, "view all" link and the four package cards (WooCommerce products).
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"templateLock":"contentOnly","lock":{"move":true,"remove":true},"className":"section packages","layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group section packages">
<!-- wp:group {"className":"section-head","layout":{"type":"flex","justifyContent":"space-between"}} -->
<div class="wp-block-group section-head">
<!-- wp:heading {"className":"section-title"} -->
<h2 class="wp-block-heading section-title">Популярни промо пакети</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"link-more"} -->
<p class="link-more"><a href="/promo-paketi/">Виж всички пакети →</a></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:shortcode -->
[products limit="4" columns="4" orderby="menu_order" order="ASC"]
<!-- /wp:shortcode -->
</div>
<!-- /wp:group -->
