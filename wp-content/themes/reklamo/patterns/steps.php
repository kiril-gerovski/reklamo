<?php
/**
 * Title: How the order works — 6 steps
 * Slug: reklamo/steps
 * Categories: reklamo
 * Description: Six numbered steps with icons.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

$reklamo_icons = array( 'cube', 'upload', 'nodes', 'check', 'card', 'truck' );
$reklamo_steps = array(
	array( 'Избираш пакет', 'Виждаш точна цена и количество.' ),
	array( 'Качваш логото', 'AI, EPS, PDF, SVG или качествен PNG.' ),
	array( 'Получаваш визуализация', 'Наш дизайнер подготвя mockup на продуктите.' ),
	array( 'Одобряваш визията', 'Можеш да поискаш промени до пълно одобрение.' ),
	array( 'Плащаш аванс', 'След одобрение плащаш 50% аванс и стартираме поръчката.' ),
	array( 'Доплащаш и получаваш', 'Плащаш остатъка преди изпращане и получаваш поръчката си.' ),
);
?>
<!-- wp:group {"templateLock":"contentOnly","lock":{"move":true,"remove":true},"className":"section steps","layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group section steps">
<!-- wp:heading {"textAlign":"center","className":"section-title"} -->
<h2 class="wp-block-heading has-text-align-center section-title">Как става поръчката?</h2>
<!-- /wp:heading -->
<!-- wp:html -->
<ol class="steps__grid">
<?php foreach ( $reklamo_steps as $reklamo_i => $reklamo_s ) : ?>
	<li class="steps__item">
		<span class="steps__num"><?php echo esc_html( sprintf( '%02d', $reklamo_i + 1 ) ); ?></span>
		<span class="steps__icon"><?php echo reklamo_icon( $reklamo_icons[ $reklamo_i ], 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<strong><?php echo esc_html( $reklamo_s[0] ); ?></strong>
		<p><?php echo esc_html( $reklamo_s[1] ); ?></p>
	</li>
<?php endforeach; ?>
</ol>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
