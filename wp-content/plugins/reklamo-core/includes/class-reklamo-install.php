<?php
/**
 * Tables, private directory, one-off settings. Runs on activation and again
 * whenever the plugin version changes, so developing never needs a re-activation.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Install {

	const DB_VERSION_OPTION = 'reklamo_db_version';
	const FLUSH_OPTION      = 'reklamo_flush_rewrite';

	public static function activate(): void {
		self::install();
		update_option( self::FLUSH_OPTION, 1 );
	}

	public static function maybe_upgrade(): void {
		if ( get_option( self::DB_VERSION_OPTION ) !== REKLAMO_VERSION ) {
			self::install();
			update_option( self::FLUSH_OPTION, 1 );
		}
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite' ), 99 );
	}

	/** Rewrite rules can only be flushed after every rule is registered (init). */
	public static function maybe_flush_rewrite(): void {
		if ( get_option( self::FLUSH_OPTION ) ) {
			flush_rewrite_rules();
			delete_option( self::FLUSH_OPTION );
		}
	}

	public static function install(): void {
		self::create_tables();
		Reklamo_Storage::ensure_base_dir();
		Reklamo_Statuses::ensure_analytics_settings();
		update_option( self::DB_VERSION_OPTION, REKLAMO_VERSION );
	}

	private static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		// Customer logos and designer mockups. Order meta alone cannot express
		// revision history or support garbage collection of unclaimed uploads.
		$files = $wpdb->prefix . 'reklamo_files';
		dbDelta(
			"CREATE TABLE {$files} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  token char(32) NOT NULL,
  order_id bigint(20) unsigned DEFAULT NULL,
  order_item_id bigint(20) unsigned DEFAULT NULL,
  kind varchar(16) NOT NULL DEFAULT 'logo',
  revision smallint(5) unsigned NOT NULL DEFAULT 0,
  orig_name varchar(255) NOT NULL,
  path varchar(500) NOT NULL,
  ext varchar(8) NOT NULL,
  mime varchar(100) NOT NULL DEFAULT '',
  bytes bigint(20) unsigned NOT NULL DEFAULT 0,
  sha256 char(64) NOT NULL DEFAULT '',
  created_ip varchar(45) DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY token (token),
  KEY order_id (order_id),
  KEY created_at (created_at)
) {$charset};"
		);

		// Approval links: selector is public, only the HASH of the secret is stored.
		$tokens = $wpdb->prefix . 'reklamo_tokens';
		dbDelta(
			"CREATE TABLE {$tokens} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  selector char(16) NOT NULL,
  order_id bigint(20) unsigned NOT NULL,
  file_id bigint(20) unsigned NOT NULL,
  revision smallint(5) unsigned NOT NULL DEFAULT 1,
  hash char(64) NOT NULL,
  expires_at datetime NOT NULL,
  used_at datetime DEFAULT NULL,
  used_action varchar(16) DEFAULT NULL,
  attempts smallint(5) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY selector (selector),
  KEY order_id (order_id)
) {$charset};"
		);
	}
}
