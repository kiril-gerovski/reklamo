<?php
/**
 * Chunked upload REST API — the "no size limit" promise.
 *
 * A 250 MB PSD as one POST dies on shared hosting long before PHP runs (post_max_size,
 * max_input_time, Apache LimitRequestBody, mod_security). So the browser slices the file
 * into small multipart chunks, each far under any of those limits, and the server
 * reassembles them. This endpoint is public and logged-out-writable, so it is treated as
 * hostile: server-issued ticket, strict path handling, per-IP rate limits, size caps,
 * free-space check, and the same content validation as the plain upload at the end.
 *
 *   POST   /wp-json/reklamo/v1/upload/init      {filename, size}          → {ticket, chunk_size}
 *   POST   /wp-json/reklamo/v1/upload/chunk     multipart ticket,index,chunk
 *   POST   /wp-json/reklamo/v1/upload/complete  {ticket, chunks}          → {token, name, size}
 *   DELETE /wp-json/reklamo/v1/upload/{ticket}
 *   POST   /wp-json/reklamo/v1/probe            multipart blob → {received}   (diagnostics)
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Upload {

	const NS         = 'reklamo/v1';
	const CHUNK      = 2 * 1024 * 1024; // safely under post_max_size and mod_security body limits.
	const TICKET_TTL = 2 * HOUR_IN_SECONDS;
	const RATE_INIT  = 10;   // tickets per IP per hour.
	const RATE_CHUNK = 3000; // chunks per IP per hour (≈ 6 GB).

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes(): void {
		register_rest_route(
			self::NS,
			'/upload/init',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_init' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
				'args'                => array(
					'filename' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_file_name',
					),
					'size'     => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/upload/chunk',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_chunk' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);
		register_rest_route(
			self::NS,
			'/upload/complete',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_complete' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);
		register_rest_route(
			self::NS,
			'/upload/(?P<ticket>[a-f0-9]{32})',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'route_delete' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);
		register_rest_route(
			self::NS,
			'/probe',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'route_probe' ),
				'permission_callback' => static fn(): bool => current_user_can( 'manage_woocommerce' ),
			)
		);
	}

	/**
	 * Public endpoint. The REST nonce (X-WP-Nonce) is still required as CSRF defence — it
	 * verifies for logged-out visitors too, because WordPress issues it for user 0.
	 */
	public static function permission( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'reklamo_nonce', __( 'The page has expired. Please reload and try again.', 'reklamo-core' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/* ----------------------------------------------------------------- helpers */

	private static function tmp_dir( string $ticket ): string {
		return Reklamo_Storage::base_dir() . '/tmp/' . $ticket;
	}

	private static function meta( string $ticket ): ?array {
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $ticket ) ) {
			return null;
		}
		$file = self::tmp_dir( $ticket ) . '/meta.json';
		if ( ! file_exists( $file ) ) {
			return null;
		}
		$meta = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $meta ) || (int) $meta['created'] < time() - self::TICKET_TTL ) {
			return null;
		}
		return $meta;
	}


	/** All public rate limiters honour REKLAMO_DISABLE_RATE_LIMITS (local/E2E only — never in production). */
	private static function limits_enabled(): bool {
		return ! ( defined( 'REKLAMO_DISABLE_RATE_LIMITS' ) && REKLAMO_DISABLE_RATE_LIMITS );
	}

	private static function rate( string $bucket, int $limit ): bool {
		if ( ! self::limits_enabled() ) {
			return true;
		}
		$key = 'reklamo_up_' . $bucket . '_' . md5( Reklamo_Storage::client_ip() );
		$n   = (int) get_transient( $key );
		if ( $n >= $limit ) {
			return false;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return true;
	}

	private static function error( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	public static function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$entries = glob( $dir . '/*' );
		foreach ( is_array( $entries ) ? $entries : array() as $f ) {
			wp_delete_file( $f );
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/* ------------------------------------------------------------------ routes */

	public static function route_init( WP_REST_Request $r ) {
		if ( ! self::rate( 'init', self::RATE_INIT ) ) {
			return self::error( 'reklamo_rate', __( 'Too many uploads from this connection. Please try again in an hour.', 'reklamo-core' ), 429 );
		}
		$name = (string) $r['filename'];
		$size = (int) $r['size'];
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, Reklamo_Storage::LOGO_EXTENSIONS, true ) ) {
			/* translators: %s: allowed extensions */
			return self::error( 'reklamo_bad_type', sprintf( __( 'This file type is not accepted. Allowed: %s.', 'reklamo-core' ), strtoupper( implode( ', ', Reklamo_Storage::LOGO_EXTENSIONS ) ) ) );
		}
		if ( $size <= 0 ) {
			return self::error( 'reklamo_empty', __( 'The file is empty.', 'reklamo-core' ) );
		}
		if ( $size > Reklamo_Storage::max_bytes() ) {
			/* translators: %s: size limit */
			return self::error( 'reklamo_too_large', sprintf( __( 'The file must be smaller than %s.', 'reklamo-core' ), size_format( Reklamo_Storage::max_bytes() ) ) );
		}
		Reklamo_Storage::ensure_base_dir();
		$free = @disk_free_space( Reklamo_Storage::base_dir() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false !== $free && $free < 3 * $size ) {
			return self::error( 'reklamo_disk', __( 'Our storage is temporarily full. Please try again later or send the file by email.', 'reklamo-core' ), 507 );
		}

		$ticket = bin2hex( random_bytes( 16 ) );
		$dir    = self::tmp_dir( $ticket );
		if ( ! wp_mkdir_p( $dir ) ) {
			return self::error( 'reklamo_store_failed', __( 'The file could not be saved. Please try again.', 'reklamo-core' ), 500 );
		}
		$meta = array(
			'name'    => $name,
			'size'    => $size,
			'ext'     => $ext,
			'chunk'   => self::CHUNK,
			'chunks'  => (int) ceil( $size / self::CHUNK ),
			'created' => time(),
			'ip'      => Reklamo_Storage::client_ip(),
		);
		file_put_contents( $dir . '/meta.json', wp_json_encode( $meta ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		return rest_ensure_response(
			array(
				'ticket'     => $ticket,
				'chunk_size' => self::CHUNK,
				'chunks'     => $meta['chunks'],
			)
		);
	}

	public static function route_chunk( WP_REST_Request $r ) {
		if ( ! self::rate( 'chunk', self::RATE_CHUNK ) ) {
			return self::error( 'reklamo_rate', __( 'Too many uploads from this connection. Please try again in an hour.', 'reklamo-core' ), 429 );
		}
		$ticket = (string) $r->get_param( 'ticket' );
		$index  = (int) $r->get_param( 'index' );
		$meta   = self::meta( $ticket );
		if ( ! $meta ) {
			return self::error( 'reklamo_ticket', __( 'The upload session has expired. Please choose the file again.', 'reklamo-core' ), 410 );
		}
		if ( $index < 0 || $index >= $meta['chunks'] ) {
			return self::error( 'reklamo_index', __( 'Invalid upload chunk.', 'reklamo-core' ) );
		}
		$files = $r->get_file_params();
		$chunk = $files['chunk'] ?? null;
		if ( ! $chunk || UPLOAD_ERR_OK !== (int) $chunk['error'] || ! is_uploaded_file( $chunk['tmp_name'] ) ) {
			return self::error( 'reklamo_chunk', __( 'The upload did not complete. Please try again.', 'reklamo-core' ) );
		}
		$expected = $index === $meta['chunks'] - 1 ? $meta['size'] - $index * $meta['chunk'] : $meta['chunk'];
		if ( (int) $chunk['size'] !== (int) $expected ) {
			return self::error( 'reklamo_chunk', __( 'Invalid upload chunk.', 'reklamo-core' ) );
		}
		$dest = self::tmp_dir( $ticket ) . '/' . sprintf( '%06d.part', $index );
		if ( ! move_uploaded_file( $chunk['tmp_name'], $dest ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_move_uploaded_file
			return self::error( 'reklamo_store_failed', __( 'The file could not be saved. Please try again.', 'reklamo-core' ), 500 );
		}
		return rest_ensure_response(
			array(
				'ok'    => true,
				'index' => $index,
			)
		);
	}

	public static function route_complete( WP_REST_Request $r ) {
		$ticket = (string) $r->get_param( 'ticket' );
		$meta   = self::meta( $ticket );
		if ( ! $meta ) {
			return self::error( 'reklamo_ticket', __( 'The upload session has expired. Please choose the file again.', 'reklamo-core' ), 410 );
		}
		$dir   = self::tmp_dir( $ticket );
		$whole = $dir . '/assembled.bin';
		$out   = fopen( $whole, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $out ) {
			return self::error( 'reklamo_store_failed', __( 'The file could not be saved. Please try again.', 'reklamo-core' ), 500 );
		}
		$total = 0;
		for ( $i = 0; $i < $meta['chunks']; $i++ ) {
			$part = $dir . '/' . sprintf( '%06d.part', $i );
			if ( ! file_exists( $part ) ) {
				fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				wp_delete_file( $whole );
				/* translators: %d: chunk number */
				return self::error( 'reklamo_missing', sprintf( __( 'Part %d of the upload is missing. Please try again.', 'reklamo-core' ), $i + 1 ), 409 );
			}
			$in     = fopen( $part, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$total += stream_copy_to_stream( $in, $out ); // never file_get_contents() a 250 MB file
			fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( $total !== (int) $meta['size'] ) {
			self::remove_dir( $dir );
			return self::error( 'reklamo_size', __( 'The upload did not complete. Please try again.', 'reklamo-core' ), 409 );
		}

		$stored = Reklamo_Storage::finalize( $whole, $meta['name'], 'logo', Reklamo_Storage::LOGO_EXTENSIONS, 0, false );
		self::remove_dir( $dir );
		if ( is_wp_error( $stored ) ) {
			$stored->add_data( array( 'status' => 422 ) );
			return $stored;
		}
		return rest_ensure_response(
			array(
				'token' => $stored['token'],
				'name'  => $stored['orig_name'],
				'size'  => (int) $stored['bytes'],
				'ext'   => $stored['ext'],
			)
		);
	}

	public static function route_delete( WP_REST_Request $r ) {
		$ticket = (string) $r['ticket'];
		if ( self::meta( $ticket ) ) {
			self::remove_dir( self::tmp_dir( $ticket ) );
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/** Diagnostics: echo how many bytes actually arrived (admin only). */
	public static function route_probe( WP_REST_Request $r ) {
		$files = $r->get_file_params();
		$blob  = $files['blob'] ?? null;
		return rest_ensure_response(
			array(
				'received' => $blob ? (int) $blob['size'] : 0,
				'error'    => $blob ? (int) $blob['error'] : UPLOAD_ERR_NO_FILE,
			)
		);
	}
}
