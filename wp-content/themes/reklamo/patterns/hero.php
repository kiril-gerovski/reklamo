<?php
/**
 * Title: Hero — choose, send, we do the rest
 * Slug: reklamo/hero
 * Categories: reklamo
 * Block Types: core/post-content
 * Description: Homepage hero with headline, lead, two buttons, four features and a product's photo with its price badge.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"templateLock":"contentOnly","lock":{"move":true,"remove":true},"className":"hero","layout":{"type":"constrained","contentSize":"1320px"}} -->
<div class="wp-block-group hero">
<!-- wp:columns {"verticalAlignment":"center"} -->
<div class="wp-block-columns are-vertically-aligned-center">
<!-- wp:column {"verticalAlignment":"center","width":"50%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:50%">
<!-- wp:paragraph {"className":"eyebrow"} -->
<p class="eyebrow">Промо пакети с вашето лого</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Избери пакет.<br>Изпрати логото.<br><span class="gold">Ние правим останалото.</span></h1>
<!-- /wp:heading -->
<!-- wp:html -->
<div class="hero__rule"></div>
<!-- /wp:html -->
<!-- wp:paragraph {"className":"lead"} -->
<p class="lead">Фиксирани количества, ясни цени и професионално брандиране. Получаваш визуализация за одобрение преди да започнем производството.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/promo-paketi/">Разгледай пакетите</a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/kak-raboti/">Как работи?</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
<!-- wp:html -->
<ul class="hero__features">
	<li><?php echo reklamo_icon( 'monitor', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span>Визуализация преди поръчка</span></li>
	<li><?php echo reklamo_icon( 'diamond', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span>Качествени материали</span></li>
	<li><?php echo reklamo_icon( 'pen', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span>Брандиране по твой избор</span></li>
	<li><?php echo reklamo_icon( 'truck', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span>Бърза и сигурна доставка</span></li>
</ul>
<!-- /wp:html -->
</div>
<!-- /wp:column -->
<!-- wp:column {"verticalAlignment":"center","width":"50%","className":"hero__media"} -->
<div class="wp-block-column is-vertically-aligned-center hero__media" style="flex-basis:50%">
<!-- wp:shortcode -->
[reklamo_hero_product sku="PKG-MAGNET" title="Metal Business Pack" items="50 тефтера|50 химикалки"]
<!-- /wp:shortcode -->
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->
</div>
<!-- /wp:group -->
