<?php
/**
 * Private file storage for customer logos and designer mockups.
 *
 * Files never enter the media library and are never publicly addressable: stored as
 * {32hex}.bin in a directory outside the web root (REKLAMO_PRIVATE_DIR) or a salted
 * fallback under uploads, served only through authenticated endpoints with
 * Content-Disposition: attachment. That single decision closes the SVG-XSS vector
 * (an SVG never rendered as image/svg+xml on our origin cannot execute against it)
 * and keeps brand assets out of search engines.
 *
 * Phase 1: plain multipart upload. Phase 2 adds chunking and a magic-byte sniffer.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Storage {

	const LOGO_EXTENSIONS   = array( 'ai', 'eps', 'pdf', 'psd', 'cdr', 'svg', 'png', 'jpg', 'jpeg' );
	const MOCKUP_EXTENSIONS = array( 'pdf', 'png', 'jpg', 'jpeg' );
	const MOCKUP_MAX_BYTES  = 20 * 1024 * 1024;

	/** MIME types that must never be stored regardless of extension. */
	const FORBIDDEN_MIMES = array( 'text/html', 'application/x-php', 'application/x-httpd-php', 'text/x-php', 'application/javascript', 'text/javascript' );

	public static function init(): void {
		add_action( 'admin_post_reklamo_download', array( __CLASS__, 'serve_download' ) );
		if ( ! is_dir( self::base_dir() . '/files' ) ) {
			self::ensure_base_dir();
		}
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'reklamo_files';
	}

	public static function base_dir(): string {
		if ( defined( 'REKLAMO_PRIVATE_DIR' ) && REKLAMO_PRIVATE_DIR ) {
			return untrailingslashit( REKLAMO_PRIVATE_DIR );
		}
		$salt = get_option( 'reklamo_dir_salt' );
		if ( ! $salt ) {
			$salt = bin2hex( random_bytes( 8 ) );
			update_option( 'reklamo_dir_salt', $salt, false );
		}
		return trailingslashit( wp_upload_dir()['basedir'] ) . 'reklamo-private-' . $salt;
	}

	/** Create the directory tree with deny-all .htaccess and silent index.php. */
	public static function ensure_base_dir(): void {
		$base = self::base_dir();
		foreach ( array( $base, $base . '/files', $base . '/tmp' ) as $dir ) {
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			if ( ! file_exists( $dir . '/index.php' ) ) {
				file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
		$htaccess = $base . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$htaccess,
				"<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\nOptions -Indexes -ExecCGI\nRemoveHandler .php .phtml .phar .php3 .php5 .php7 .php8 .cgi .pl .py\n<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n"
			);
		}
	}

	/**
	 * Validate and store one uploaded file ($_FILES entry).
	 *
	 * @param array    $file      One entry of $_FILES.
	 * @param string   $kind      'logo' or 'mockup'.
	 * @param string[] $allowed   Allowed extensions.
	 * @param int      $max_bytes Size limit; 0 = only the PHP limits apply.
	 * @return array|WP_Error Row as array on success.
	 */
	public static function store_upload( array $file, string $kind, array $allowed, int $max_bytes = 0 ) {
		$error = (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE );
		if ( UPLOAD_ERR_NO_FILE === $error ) {
			return new WP_Error( 'reklamo_no_file', __( 'Please choose a file.', 'reklamo-core' ) );
		}
		if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
			return new WP_Error(
				'reklamo_too_large',
				sprintf(
					/* translators: %s: server upload limit, e.g. 64 MB */
					__( 'The file is larger than the server currently accepts (%s). Please send it to us by email for now.', 'reklamo-core' ),
					size_format( wp_max_upload_size() )
				)
			);
		}
		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'reklamo_upload_error', __( 'The upload did not complete. Please try again.', 'reklamo-core' ) );
		}
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'reklamo_upload_error', __( 'The upload did not complete. Please try again.', 'reklamo-core' ) );
		}

		$orig = sanitize_file_name( wp_unslash( (string) $file['name'] ) );
		$ext  = strtolower( pathinfo( $orig, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed, true ) ) {
			return new WP_Error(
				'reklamo_bad_type',
				sprintf(
					/* translators: %s: comma-separated list of allowed extensions */
					__( 'This file type is not accepted. Allowed: %s.', 'reklamo-core' ),
					strtoupper( implode( ', ', $allowed ) )
				)
			);
		}

		$bytes = (int) filesize( $file['tmp_name'] );
		if ( $bytes <= 0 ) {
			return new WP_Error( 'reklamo_empty', __( 'The file is empty.', 'reklamo-core' ) );
		}
		if ( $max_bytes > 0 && $bytes > $max_bytes ) {
			/* translators: %s: size limit */
			return new WP_Error( 'reklamo_too_large', sprintf( __( 'The file must be smaller than %s.', 'reklamo-core' ), size_format( $max_bytes ) ) );
		}

		// Cheap sanity check on real content; the full per-format sniffer is Phase 2.
		$mime = '';
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime  = (string) finfo_file( $finfo, $file['tmp_name'] );
			finfo_close( $finfo );
		}
		if ( in_array( $mime, self::FORBIDDEN_MIMES, true ) ) {
			return new WP_Error( 'reklamo_bad_type', __( 'This file type is not accepted.', 'reklamo-core' ) );
		}
		if ( in_array( $ext, array( 'png', 'jpg', 'jpeg' ), true ) && ! str_starts_with( $mime, 'image/' ) ) {
			return new WP_Error( 'reklamo_bad_type', __( 'The file does not look like an image.', 'reklamo-core' ) );
		}

		self::ensure_base_dir();
		$token = bin2hex( random_bytes( 16 ) );
		$rel   = 'files/' . gmdate( 'Y/m' ) . '/' . $token . '.bin';
		$dest  = self::base_dir() . '/' . $rel;
		wp_mkdir_p( dirname( $dest ) );
		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_move_uploaded_file
			return new WP_Error( 'reklamo_store_failed', __( 'The file could not be saved. Please try again.', 'reklamo-core' ) );
		}
		chmod( $dest, 0640 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod

		global $wpdb;
		$row = array(
			'token'      => $token,
			'kind'       => $kind,
			'revision'   => 0,
			'orig_name'  => $orig,
			'path'       => $rel,
			'ext'        => $ext,
			'mime'       => $mime,
			'bytes'      => $bytes,
			'sha256'     => hash_file( 'sha256', $dest ),
			'created_ip' => self::client_ip(),
			'created_at' => current_time( 'mysql', true ),
		);
		$wpdb->insert( self::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row['id'] = (int) $wpdb->insert_id;
		return $row;
	}

	public static function by_token( string $token ): ?object {
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}reklamo_files WHERE token = %s", $token ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $row ? $row : null;
	}

	public static function by_id( int $id ): ?object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}reklamo_files WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $row ? $row : null;
	}

	/** @return object[] */
	public static function for_order( int $order_id, string $kind = '' ): array {
		global $wpdb;
		if ( $kind ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}reklamo_files WHERE order_id = %d AND kind = %s ORDER BY revision ASC, id ASC", $order_id, $kind ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}reklamo_files WHERE order_id = %d ORDER BY id ASC", $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return is_array( $rows ) ? $rows : array();
	}

	/** Bind an unclaimed upload to the order (and line item) it was placed with. */
	public static function claim( string $token, int $order_id, int $order_item_id = 0, int $revision = 0 ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'order_id'      => $order_id,
				'order_item_id' => $order_item_id > 0 ? $order_item_id : null,
				'revision'      => $revision,
			),
			array( 'token' => $token )
		);
	}

	public static function abs_path( object $row ): string {
		return self::base_dir() . '/' . ltrim( $row->path, '/' );
	}

	public static function download_url( int $file_id ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=reklamo_download&f=' . $file_id ), 'reklamo_dl_' . $file_id );
	}

	/** Admin-only download of any stored file. */
	public static function serve_download(): void {
		$id = isset( $_GET['f'] ) ? absint( $_GET['f'] ) : 0;
		check_admin_referer( 'reklamo_dl_' . $id );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You do not have permission to download this file.', 'reklamo-core' ), 403 );
		}
		$row = self::by_id( $id );
		if ( ! $row ) {
			wp_die( esc_html__( 'File not found.', 'reklamo-core' ), 404 );
		}
		self::serve_file( $row, false );
	}

	/**
	 * Stream a stored file. Inline only for raster images (mockups shown to the customer);
	 * everything else — SVG above all — is forced to download.
	 */
	public static function serve_file( object $row, bool $inline ): void {
		$real = realpath( self::abs_path( $row ) );
		$base = realpath( self::base_dir() );
		if ( ! $real || ! $base || ! str_starts_with( $real, $base . DIRECTORY_SEPARATOR ) ) {
			wp_die( esc_html__( 'File not found.', 'reklamo-core' ), 404 );
		}
		$is_raster = in_array( $row->ext, array( 'png', 'jpg', 'jpeg' ), true ) && str_starts_with( (string) $row->mime, 'image/' );
		$inline    = $inline && $is_raster;

		nocache_headers();
		header( 'Content-Type: ' . ( $inline ? $row->mime : 'application/octet-stream' ) );
		header( 'Content-Length: ' . filesize( $real ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; sandbox" );
		header( 'X-Robots-Tag: noindex, nofollow' );
		$ascii = preg_replace( '/[^\x20-\x7E]/', '_', $row->orig_name );
		header( sprintf( 'Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s', $inline ? 'inline' : 'attachment', $ascii, rawurlencode( $row->orig_name ) ) );

		while ( ob_get_level() ) {
			ob_end_clean();
		}
		$fh = fopen( $real, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		while ( ! feof( $fh ) ) {
			echo fread( $fh, 8192 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fread
			flush();
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	public static function describe( object $row ): string {
		return sprintf( '%s (%s, %s)', $row->orig_name, strtoupper( $row->ext ), size_format( (int) $row->bytes ) );
	}
}
