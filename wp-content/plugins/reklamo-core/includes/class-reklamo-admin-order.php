<?php
/**
 * Order-screen metabox: customer logos, mockup revisions, approval state, and the
 * "send mockup" upload.
 *
 * The metaboxes sit inside the order edit <form>; a nested <form> would be ignored by
 * browsers, so the upload inputs carry the HTML5 form="…" attribute pointing at a
 * separate form printed in the admin footer.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Admin_Order {

	const FORM_ID = 'reklamo-mockup-form';

	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ), 10, 2 );
		add_action( 'admin_footer', array( __CLASS__, 'print_form' ) );
		add_action( 'admin_post_reklamo_send_mockup', array( __CLASS__, 'handle_send_mockup' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	private static function is_order_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && function_exists( 'wc_get_page_screen_id' ) && wc_get_page_screen_id( 'shop-order' ) === $screen->id;
	}

	/** @param string $screen_id HPOS passes its screen id and the order; legacy passes the post type and post. */
	public static function add_meta_box( $screen_id, $post_or_order = null ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! function_exists( 'wc_get_page_screen_id' ) ) {
			return;
		}
		add_meta_box(
			'reklamo_mockup',
			__( 'Mockup & approval', 'reklamo-core' ),
			array( __CLASS__, 'render' ),
			wc_get_page_screen_id( 'shop-order' ),
			'normal',
			'high'
		);
	}

	/** @param WC_Order|WP_Post $post_or_order HPOS passes the order, legacy the post. */
	public static function render( $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$logos   = Reklamo_Storage::for_order( $order->get_id(), 'logo' );
		$mockups = Reklamo_Storage::for_order( $order->get_id(), 'mockup' );
		$tokens  = array();
		foreach ( Reklamo_Approval::for_order( $order->get_id() ) as $t ) {
			$tokens[ (int) $t->file_id ] = $t;
		}
		$next_rev = count( $mockups ) + 1;
		?>
		<div class="reklamo-admin">
			<p><strong><?php esc_html_e( 'Status', 'reklamo-core' ); ?>:</strong> <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></p>

			<h4><?php esc_html_e( 'Customer logo', 'reklamo-core' ); ?></h4>
			<?php if ( $logos ) : ?>
				<ul>
				<?php foreach ( $logos as $f ) : ?>
					<li><a href="<?php echo esc_url( Reklamo_Storage::download_url( (int) $f->id ) ); ?>"><?php echo esc_html( Reklamo_Storage::describe( $f ) ); ?></a></li>
				<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No logo attached to this order.', 'reklamo-core' ); ?></p>
			<?php endif; ?>

			<h4><?php esc_html_e( 'Mockups sent', 'reklamo-core' ); ?></h4>
			<?php if ( $mockups ) : ?>
				<ol>
				<?php
				foreach ( $mockups as $m ) :
					$t = $tokens[ (int) $m->id ] ?? null;
					?>
					<li>
						<a href="<?php echo esc_url( Reklamo_Storage::download_url( (int) $m->id ) ); ?>"><?php echo esc_html( Reklamo_Storage::describe( $m ) ); ?></a>
						<span class="description">
						<?php
						if ( $t && $t->used_at ) {
							echo esc_html( 'approve' === $t->used_action ? __( '— approved', 'reklamo-core' ) : __( '— changes requested', 'reklamo-core' ) );
							echo ' ' . esc_html( wc_format_datetime( new WC_DateTime( $t->used_at . ' UTC' ), 'd.m.Y H:i' ) );
						} elseif ( $t ) {
							echo esc_html__( '— awaiting the customer', 'reklamo-core' );
							/* translators: %s: expiry date */
							echo ' ' . esc_html( sprintf( __( '(link valid until %s)', 'reklamo-core' ), wc_format_datetime( new WC_DateTime( $t->expires_at . ' UTC' ), 'd.m.Y' ) ) );
						}
						?>
						</span>
					</li>
				<?php endforeach; ?>
				</ol>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No mockup sent yet.', 'reklamo-core' ); ?></p>
			<?php endif; ?>

			<?php if ( $order->get_meta( '_reklamo_approved_at' ) ) : ?>
				<p><strong><?php esc_html_e( 'Approved', 'reklamo-core' ); ?>:</strong>
					<?php echo esc_html( wc_format_datetime( new WC_DateTime( $order->get_meta( '_reklamo_approved_at' ) . ' UTC' ), 'd.m.Y H:i' ) ); ?>
					<?php /* translators: %d: mockup revision */ echo esc_html( sprintf( __( '(mockup #%d)', 'reklamo-core' ), (int) $order->get_meta( '_reklamo_approved_revision' ) ) ); ?>
				</p>
			<?php endif; ?>

			<h4>
				<?php
				/* translators: %d: next mockup revision number */
				echo esc_html( sprintf( __( 'Send mockup #%d', 'reklamo-core' ), $next_rev ) );
				?>
			</h4>
			<p class="description"><?php esc_html_e( 'PDF, PNG or JPG, up to 20 MB. The customer receives an email with a one-time approval link (valid 14 days).', 'reklamo-core' ); ?></p>
			<p>
				<input type="file" name="reklamo_mockup" form="<?php echo esc_attr( self::FORM_ID ); ?>" accept=".pdf,.png,.jpg,.jpeg" required>
			</p>
			<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" form="<?php echo esc_attr( self::FORM_ID ); ?>">
			<?php
			// wp_nonce_field() would print inside the outer form; build the input with form="" by hand.
			printf(
				'<input type="hidden" name="_wpnonce" value="%s" form="%s"><input type="hidden" name="action" value="reklamo_send_mockup" form="%s">',
				esc_attr( wp_create_nonce( 'reklamo_send_mockup' ) ),
				esc_attr( self::FORM_ID ),
				esc_attr( self::FORM_ID )
			);
			?>
			<p>
				<button type="submit" class="button button-primary" form="<?php echo esc_attr( self::FORM_ID ); ?>"><?php esc_html_e( 'Send to customer', 'reklamo-core' ); ?></button>
			</p>
		</div>
		<?php
	}

	/** The real form element lives outside the order form so the browser honours it. */
	public static function print_form(): void {
		if ( ! self::is_order_screen() ) {
			return;
		}
		printf(
			'<form id="%s" method="post" enctype="multipart/form-data" action="%s"></form>',
			esc_attr( self::FORM_ID ),
			esc_url( admin_url( 'admin-post.php' ) )
		);
	}

	public static function handle_send_mockup(): void {
		check_admin_referer( 'reklamo_send_mockup' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'reklamo-core' ), 403 );
		}
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'reklamo-core' ), 404 );
		}
		$back = $order->get_edit_order_url();

		$stored = isset( $_FILES['reklamo_mockup'] )
			? Reklamo_Storage::store_upload( $_FILES['reklamo_mockup'], 'mockup', Reklamo_Storage::MOCKUP_EXTENSIONS, Reklamo_Storage::MOCKUP_MAX_BYTES ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated inside store_upload().
			: new WP_Error( 'reklamo_no_file', __( 'Please choose a file.', 'reklamo-core' ) );
		if ( is_wp_error( $stored ) ) {
			self::redirect( $back, 'error', $stored->get_error_message() );
		}

		$revision = count( Reklamo_Storage::for_order( $order_id, 'mockup' ) ) + 1;
		Reklamo_Storage::claim( $stored['token'], $order_id, 0, $revision );

		$url = Reklamo_Approval::issue( $order, (int) $stored['id'], $revision );
		Reklamo_Emails::send_mockup( $order, $url, $revision );

		/* translators: %d: mockup revision */
		$note = sprintf( __( 'Mockup #%d sent to the customer for approval.', 'reklamo-core' ), $revision );
		if ( $order->has_status( Reklamo_Statuses::MOCKUP_SENT ) ) {
			$order->add_order_note( $note );
		} else {
			$order->update_status( Reklamo_Statuses::MOCKUP_SENT, $note );
		}

		/* translators: %d: mockup revision */
		self::redirect( $back, 'success', sprintf( __( 'Mockup #%d sent. The customer has the approval link by email.', 'reklamo-core' ), $revision ) );
	}

	private static function redirect( string $back, string $type, string $message ): void {
		set_transient( 'reklamo_notice_' . get_current_user_id(), array( $type, $message ), 60 );
		wp_safe_redirect( $back );
		exit;
	}

	public static function notices(): void {
		if ( ! self::is_order_screen() ) {
			return;
		}
		$n = get_transient( 'reklamo_notice_' . get_current_user_id() );
		if ( ! $n ) {
			return;
		}
		delete_transient( 'reklamo_notice_' . get_current_user_id() );
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $n[0] ), esc_html( $n[1] ) );
	}
}
