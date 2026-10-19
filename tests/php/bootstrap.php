<?php
/**
 * Minimal bootstrap: the classes under test are pure PHP and only need the
 * ABSPATH guard satisfied. Anything needing WordPress belongs in the E2E suite.
 */
define( 'ABSPATH', '/nonexistent/' );
$plugin = dirname( __DIR__, 2 ) . '/wp-content/plugins/reklamo-core/includes/';
require $plugin . 'class-reklamo-token.php';
require $plugin . 'class-reklamo-money.php';
require $plugin . 'class-reklamo-product.php'; // promo_price() is pure; the rest needs WooCommerce.
require $plugin . 'class-reklamo-filetypes.php';
require $plugin . 'class-reklamo-svg.php';
require $plugin . 'class-reklamo-statuses.php';
require $plugin . 'class-reklamo-progress.php';
require $plugin . 'class-reklamo-items.php'; // order_status_for() is pure; the rest needs WooCommerce.
require $plugin . 'class-reklamo-email-check.php';
require $plugin . 'class-reklamo-payment-deadline.php'; // times() is pure.
require $plugin . 'class-reklamo-dispatch.php'; // clean_waybill() and tracking_url() are pure.
require $plugin . 'class-reklamo-privacy.php'; // mask_ips() and the constants are pure; init() is never called here. // domain() is pure; has_mail_host() needs WordPress and is not unit-tested. // can_transition() and TRANSITIONS are pure; nothing runs at load.
