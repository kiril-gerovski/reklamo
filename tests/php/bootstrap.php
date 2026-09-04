<?php
/**
 * Minimal bootstrap: the classes under test are pure PHP and only need the
 * ABSPATH guard satisfied. Anything needing WordPress belongs in the E2E suite.
 */
define( 'ABSPATH', '/nonexistent/' );
require dirname( __DIR__, 2 ) . '/wp-content/plugins/reklamo-core/includes/class-reklamo-token.php';
