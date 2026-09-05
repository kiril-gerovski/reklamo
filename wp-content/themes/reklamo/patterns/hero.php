<?php
/**
 * Title: Hero — choose, send, we do the rest
 * Slug: reklamo/hero
 * Categories: reklamo
 * Block Types: core/post-content
 * Description: Homepage hero with headline, lead, two buttons, four features and the product image with a price badge.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

$reklamo_hero_img = get_template_directory_uri() . '/assets/img/hero-placeholder.svg';
?>
<!-- wp:group {"templateLock":"contentOnly","lock":{"move":true,"remove":true},"className":"hero","layout":{"type":"constrained","contentSize":"1200px"}} -->
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
<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="<?php echo esc_url( $reklamo_hero_img ); ?>" alt="Тефтер и химикалка с лого"/></figure>
<!-- /wp:image -->
<!-- wp:group {"className":"hero__badge","layout":{"type":"constrained"}} -->
<div class="wp-block-group hero__badge">
<!-- wp:paragraph {"className":"eyebrow"} -->
<p class="eyebrow">Red Business Pack</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><small>20 тефтера<br>20 химикалки</small></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><strong>100 €</strong></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->
</div>
<!-- /wp:group -->
