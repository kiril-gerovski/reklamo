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
<!-- wp:html -->
<span class="brand__mark"><svg viewBox="0 0 40 40" width="64" height="64" aria-hidden="true"><circle cx="20" cy="20" r="18" fill="none" stroke="#b8892b" stroke-width="2"/><path d="M14 29V11h6.5a5.5 5.5 0 0 1 0 11H14m6.5 0L27 29" fill="none" stroke="#b8892b" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
<!-- wp:shortcode -->
[reklamo_request_form compact="1"]
<!-- /wp:shortcode -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
