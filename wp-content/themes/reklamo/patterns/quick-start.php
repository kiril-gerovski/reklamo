<?php
/**
 * Title: Quick start — send your logo
 * Slug: reklamo/quick-start
 * Categories: reklamo
 * Description: Compact request form (name, email, note, logo, package) with intro.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"templateLock":"contentOnly","lock":{"move":true,"remove":true},"className":"section","layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group section">
<!-- wp:group {"className":"quick-start","layout":{"type":"default"}} -->
<div class="wp-block-group quick-start">
<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group">
<!-- wp:heading -->
<h2 class="wp-block-heading">Започни своята поръчка</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Изпрати ни логото си и ще се свържем с теб с визуализация.</p>
<!-- /wp:paragraph -->
<!-- wp:shortcode -->
[reklamo_mark size="76"]
<!-- /wp:shortcode -->
</div>
<!-- /wp:group -->
<!-- wp:shortcode -->
[reklamo_request_form compact="1"]
<!-- /wp:shortcode -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
