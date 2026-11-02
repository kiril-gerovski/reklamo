<?php
/**
 * Visits page (Reklamo_Stats::render).
 *
 * @var int    $days
 * @var object $totals  views, visits
 * @var array  $series  key => [label, visits, views]
 * @var int    $peak
 * @var array  $sources source, visits
 * @var array  $pages   path, views
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

// Included by Reklamo_Stats::render(): these are locals, not globals.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$period_names = array(
	7   => __( '7 days', 'reklamo-core' ),
	30  => __( '30 days', 'reklamo-core' ),
	90  => __( '90 days', 'reklamo-core' ),
	365 => __( '12 months', 'reklamo-core' ),
);
$total_visits = max( 1, (int) $totals->visits );
?>
<div class="wrap reklamo-stats">
	<h1><?php esc_html_e( 'Visits', 'reklamo-core' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Counted without cookies and without storing anything about the visitor. Your own visits while logged in and known robots are not counted.', 'reklamo-core' ); ?></p>

	<nav class="reklamo-stats__periods" aria-label="<?php esc_attr_e( 'Period', 'reklamo-core' ); ?>">
		<?php foreach ( $period_names as $n => $name ) : ?>
			<a class="button<?php echo $n === $days ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'period', $n, admin_url( 'admin.php?page=' . self::SLUG ) ) ); ?>"<?php echo $n === $days ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $name ); ?></a>
		<?php endforeach; ?>
	</nav>

	<div class="reklamo-stats__tiles">
		<div class="reklamo-stats__tile" data-rk-stat="visits">
			<span><?php esc_html_e( 'Visits', 'reklamo-core' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( (int) $totals->visits ) ); ?></strong>
			<small><?php esc_html_e( 'arrivals from outside the site', 'reklamo-core' ); ?></small>
		</div>
		<div class="reklamo-stats__tile" data-rk-stat="views">
			<span><?php esc_html_e( 'Pages viewed', 'reklamo-core' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( (int) $totals->views ) ); ?></strong>
			<small><?php esc_html_e( 'including moves between pages', 'reklamo-core' ); ?></small>
		</div>
	</div>

	<?php if ( ! (int) $totals->views ) : ?>
		<p class="reklamo-stats__empty"><?php esc_html_e( 'No visits counted in this period yet.', 'reklamo-core' ); ?></p>
	<?php else : ?>
		<h2><?php echo esc_html( 365 === $days ? __( 'Visits per month', 'reklamo-core' ) : __( 'Visits per day', 'reklamo-core' ) ); ?></h2>
		<div class="reklamo-stats__chart" role="img" aria-label="<?php esc_attr_e( 'Visits over the period; the table below has the numbers.', 'reklamo-core' ); ?>">
			<?php foreach ( $series as $point ) : ?>
				<?php /* translators: 1: date, 2: visits, 3: pages viewed */ $tip = sprintf( __( '%1$s: %2$d visits, %3$d pages', 'reklamo-core' ), $point[0], $point[1], $point[2] ); ?>
				<div class="reklamo-stats__col" data-tip="<?php echo esc_attr( $tip ); ?>" tabindex="0">
					<span class="reklamo-stats__bar" style="height: <?php echo esc_attr( (string) round( 100 * $point[1] / $peak, 1 ) ); ?>%"></span>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="reklamo-stats__axis">
			<span><?php echo esc_html( reset( $series )[0] ); ?></span>
			<span><?php echo esc_html( sprintf( /* translators: %d: highest value on the chart */ __( 'highest: %d', 'reklamo-core' ), $peak ) ); ?></span>
			<span><?php echo esc_html( end( $series )[0] ); ?></span>
		</div>
		<details class="reklamo-stats__table">
			<summary><?php esc_html_e( 'As a table', 'reklamo-core' ); ?></summary>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Date', 'reklamo-core' ); ?></th><th class="num"><?php esc_html_e( 'Visits', 'reklamo-core' ); ?></th><th class="num"><?php esc_html_e( 'Pages viewed', 'reklamo-core' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( array_reverse( $series ) as $point ) : ?>
					<tr><td><?php echo esc_html( $point[0] ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( $point[1] ) ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( $point[2] ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</details>

		<div class="reklamo-stats__lists">
			<section>
				<h2><?php esc_html_e( 'Where visitors came from', 'reklamo-core' ); ?></h2>
				<table class="widefat striped" data-rk-sources>
					<thead><tr><th><?php esc_html_e( 'Source', 'reklamo-core' ); ?></th><th class="num"><?php esc_html_e( 'Visits', 'reklamo-core' ); ?></th><th class="num">%</th></tr></thead>
					<tbody>
					<?php foreach ( $sources as $row ) : ?>
						<tr><td><?php echo esc_html( self::label( $row->source ) ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( (int) $row->visits ) ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( 100 * $row->visits / $total_visits ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</section>
			<section>
				<h2><?php esc_html_e( 'Most viewed pages', 'reklamo-core' ); ?></h2>
				<table class="widefat striped" data-rk-pages>
					<thead><tr><th><?php esc_html_e( 'Page', 'reklamo-core' ); ?></th><th class="num"><?php esc_html_e( 'Views', 'reklamo-core' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $pages as $row ) : ?>
						<tr><td><a href="<?php echo esc_url( home_url( $row->path ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( self::page_name( $row->path ) ); ?></a></td><td class="num"><?php echo esc_html( number_format_i18n( (int) $row->views ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		</div>
	<?php endif; ?>
</div>
