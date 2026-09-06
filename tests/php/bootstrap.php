<?php
/**
 * Minimal bootstrap: the classes under test are pure PHP and only need the
 * ABSPATH guard satisfied. Anything needing WordPress belongs in the E2E suite.
 */
define( 'ABSPATH', '/nonexistent/' );
$plugin = dirname( __DIR__, 2 ) . '/wp-content/plugins/reklamo-core/includes/';
require $plugin . 'class-reklamo-token.php';
require $plugin . 'class-reklamo-money.php';
require $plugin . 'class-reklamo-filetypes.php';
require $plugin . 'class-reklamo-svg.php';
require $plugin . 'class-reklamo-statuses.php';
require $plugin . 'class-reklamo-progress.php'; // can_transition() and TRANSITIONS are pure; nothing runs at load.
