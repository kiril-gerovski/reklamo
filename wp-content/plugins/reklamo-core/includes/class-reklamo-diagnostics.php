<?php
/**
 * WooCommerce → Reklamo diagnostics: what this server really allows. Shared hosting
 * limits are invisible from wp-admin and differ from what php.ini claims (Apache
 * LimitRequestBody, mod_security); the active probe posts real bodies to find out.
 * Also verifies the private storage directory is not web-reachable.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Diagnostics {

	const SLUG = 'reklamo-diagnostics';

	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_reklamo_mail_test', array( __CLASS__, 'handle_mail_test' ) );
	}

	/** Sends one test message to the current user and reports the transport's verdict. */
	public static function handle_mail_test(): void {
		check_admin_referer( 'reklamo_mail_test' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'reklamo-core' ), 403 );
		}
		$to     = wp_get_current_user()->user_email;
		$result = Reklamo_Mail::send_test( $to );
		set_transient( 'reklamo_mail_test_' . get_current_user_id(), true === $result ? array( 'success', sprintf( /* translators: %s: email address */ __( 'Test email accepted by the mail server. Check the inbox of %s (and its spam folder).', 'reklamo-core' ), $to ) ) : array( 'error', sprintf( /* translators: %s: error message */ __( 'Test email NOT sent: %s', 'reklamo-core' ), $result ) ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	public static function menu(): void {
		add_submenu_page( 'woocommerce', __( 'Reklamo diagnostics', 'reklamo-core' ), __( 'Reklamo diagnostics', 'reklamo-core' ), 'manage_woocommerce', self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function assets( string $hook ): void {
		if ( ! str_ends_with( $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_script( 'reklamo-diagnostics', REKLAMO_URL . 'assets/js/diagnostics.js', array(), REKLAMO_VERSION, true );
		wp_localize_script(
			'reklamo-diagnostics',
			'reklamoDiag',
			array(
				'probeUrl' => rest_url( Reklamo_Upload::NS . '/probe' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'sizesMb'  => array( 1, 2, 4, 8, 16, 32, 64, 128 ),
				'i18n'     => array(
					'ok'      => __( 'accepted', 'reklamo-core' ),
					'fail'    => __( 'rejected', 'reklamo-core' ),
					'running' => __( 'testing…', 'reklamo-core' ),
					/* translators: %s: size */
					'summary' => __( 'Largest single request accepted: %s. Chunks are 2 MB, so uploads of any size work when 2 MB passes.', 'reklamo-core' ),
				),
			)
		);
	}

	private static function bytes( string $ini ): int {
		return (int) wp_convert_hr_to_bytes( $ini );
	}

	/** Is the private dir inside the web root? If so, try fetching a canary. */
	private static function exposure(): array {
		$base = realpath( Reklamo_Storage::base_dir() );
		$root = realpath( ABSPATH );
		if ( ! $base || ! $root || ! str_starts_with( $base, $root ) ) {
			return array( 'outside', __( 'Outside the web root — not reachable by URL.', 'reklamo-core' ) );
		}
		$rel  = ltrim( substr( $base, strlen( $root ) ), '/\\' );
		$name = 'canary-' . wp_generate_password( 8, false, false ) . '.txt';
		file_put_contents( $base . '/' . $name, 'reklamo-canary' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents -- tiny canary in our own directory.
		$resp = wp_remote_get( home_url( '/' . str_replace( '\\', '/', $rel ) . '/' . $name ), array( 'timeout' => 8 ) );
		wp_delete_file( $base . '/' . $name );
		if ( ! is_wp_error( $resp ) && 200 === wp_remote_retrieve_response_code( $resp ) && str_contains( wp_remote_retrieve_body( $resp ), 'reklamo-canary' ) ) {
			return array( 'exposed', __( 'PUBLICLY READABLE — the .htaccess deny rule is not in effect on this server. Move REKLAMO_PRIVATE_DIR outside the web root or add a deny rule for the host.', 'reklamo-core' ) );
		}
		/* translators: %s: HTTP status */
		return array( 'protected', sprintf( __( 'Inside the web root but not readable (HTTP %s).', 'reklamo-core' ), is_wp_error( $resp ) ? $resp->get_error_message() : wp_remote_retrieve_response_code( $resp ) ) );
	}

	public static function render(): void {
		global $wpdb;
		$dir      = Reklamo_Storage::base_dir();
		$free     = @disk_free_space( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$stats    = $wpdb->get_row( "SELECT COUNT(*) AS n, COALESCE(SUM(bytes),0) AS b FROM {$wpdb->prefix}reklamo_files WHERE path <> ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$tmp_dirs = glob( $dir . '/tmp/*', GLOB_ONLYDIR );
		$tmp      = is_array( $tmp_dirs ) ? count( $tmp_dirs ) : 0;
		$exposure = self::exposure();
		$next_gc  = function_exists( 'as_next_scheduled_action' ) ? as_next_scheduled_action( Reklamo_Cleanup::HOOK, array(), 'reklamo' ) : false;
		$rows     = array(
			__( 'PHP version', 'reklamo-core' )      => PHP_VERSION,
			'upload_max_filesize'                    => ini_get( 'upload_max_filesize' ),
			'post_max_size'                          => ini_get( 'post_max_size' ),
			'memory_limit'                           => ini_get( 'memory_limit' ),
			'max_execution_time'                     => ini_get( 'max_execution_time' ) . ' s',
			'max_input_time'                         => ini_get( 'max_input_time' ) . ' s',
			__( 'Effective single-POST limit (PHP)', 'reklamo-core' ) => size_format( min( self::bytes( ini_get( 'upload_max_filesize' ) ), self::bytes( ini_get( 'post_max_size' ) ) ) ),
			__( 'Chunk size used by the uploader', 'reklamo-core' ) => size_format( Reklamo_Upload::CHUNK ),
			__( 'Configured maximum file size', 'reklamo-core' ) => size_format( Reklamo_Storage::max_bytes() ),
			__( 'Private storage directory', 'reklamo-core' ) => $dir . ( is_writable( $dir ) ? '' : ' — ' . __( 'NOT WRITABLE', 'reklamo-core' ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- diagnostics of our own directory.
			__( 'Free disk space', 'reklamo-core' )  => false !== $free ? size_format( $free ) : '?',
			__( 'Stored files', 'reklamo-core' )     => sprintf( '%d (%s)', (int) $stats->n, size_format( (int) $stats->b ) ),
			__( 'Unfinished chunk sessions', 'reklamo-core' ) => (string) $tmp,
			__( 'Next cleanup run', 'reklamo-core' ) => $next_gc ? wp_date( 'd.m.Y H:i', $next_gc ) : __( 'not scheduled (is WooCommerce Action Scheduler running?)', 'reklamo-core' ),
			__( 'WP-Cron', 'reklamo-core' )          => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? __( 'disabled — a real cron job must call wp-cron.php', 'reklamo-core' ) : __( 'enabled (visitor-triggered; fine locally, use a real cron in production)', 'reklamo-core' ),
			__( 'finfo extension', 'reklamo-core' )  => function_exists( 'finfo_open' ) ? __( 'available', 'reklamo-core' ) : __( 'missing', 'reklamo-core' ),
			__( 'DOM extension (SVG sanitiser)', 'reklamo-core' ) => class_exists( 'DOMDocument' ) ? __( 'available', 'reklamo-core' ) : __( 'MISSING — SVG uploads will be refused', 'reklamo-core' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Reklamo diagnostics', 'reklamo-core' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Run this on the live host after deployment and again after any hosting change. The probe below posts real request bodies to find the true upload ceiling; php.ini values alone are not the whole story on shared hosting.', 'reklamo-core' ); ?></p>

			<h2><?php esc_html_e( 'Storage exposure', 'reklamo-core' ); ?></h2>
			<p class="notice notice-<?php echo 'exposed' === $exposure[0] ? 'error' : 'success'; ?> inline" style="padding:.6em 1em"><strong><?php echo esc_html( $exposure[1] ); ?></strong></p>

			<h2><?php esc_html_e( 'Email', 'reklamo-core' ); ?></h2>
			<?php
			$mail_notice = get_transient( 'reklamo_mail_test_' . get_current_user_id() );
			if ( $mail_notice ) {
				delete_transient( 'reklamo_mail_test_' . get_current_user_id() );
				printf( '<p class="notice notice-%s inline" style="padding:.6em 1em"><strong>%s</strong></p>', esc_attr( $mail_notice[0] ), esc_html( $mail_notice[1] ) );
			}
			?>
			<table class="widefat striped" style="max-width:900px">
				<tr><th style="width:40%"><?php esc_html_e( 'Transport', 'reklamo-core' ); ?></th><td><?php echo esc_html( Reklamo_Mail::transport() ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Sender', 'reklamo-core' ); ?></th><td><?php echo esc_html( sprintf( '%s <%s>', get_option( 'woocommerce_email_from_name' ), get_option( 'woocommerce_email_from_address' ) ) ); ?></td></tr>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:.8em 0 1.6em">
				<?php wp_nonce_field( 'reklamo_mail_test' ); ?>
				<input type="hidden" name="action" value="reklamo_mail_test">
				<button class="button"><?php echo esc_html( sprintf( /* translators: %s: email address */ __( 'Send a test email to %s', 'reklamo-core' ), wp_get_current_user()->user_email ) ); ?></button>
				<span class="description" style="margin-left:.6em"><?php esc_html_e( 'Every failed customer email is also recorded as a note on the order.', 'reklamo-core' ); ?></span>
			</form>

			<h2><?php esc_html_e( 'Server limits', 'reklamo-core' ); ?></h2>
			<table class="widefat striped" style="max-width:900px">
				<?php foreach ( $rows as $k => $v ) : ?>
					<tr><th style="width:40%"><?php echo esc_html( $k ); ?></th><td><?php echo esc_html( (string) $v ); ?></td></tr>
				<?php endforeach; ?>
			</table>

			<h2><?php esc_html_e( 'Active upload probe', 'reklamo-core' ); ?></h2>
			<p><?php esc_html_e( 'Sends test bodies of increasing size to this site and reports which arrive intact. Anything above the effective limit is normally cut by Apache or mod_security before PHP runs.', 'reklamo-core' ); ?></p>
			<p><button class="button button-primary" id="reklamo-probe"><?php esc_html_e( 'Run probe', 'reklamo-core' ); ?></button></p>
			<table class="widefat" style="max-width:900px"><tbody id="reklamo-probe-results"></tbody></table>
			<p id="reklamo-probe-summary" style="font-weight:600"></p>
		</div>
		<?php
	}
}
