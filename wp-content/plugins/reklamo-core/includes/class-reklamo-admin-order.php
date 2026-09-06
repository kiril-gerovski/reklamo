<?php
/**
 * Order-screen box "Mockup & approval": the customer's logo, mockup revisions and their
 * approval state, the invoice/delivery details, and the buttons that move the order
 * through the flow (send mockup → deposit received → production → final payment →
 * completed). These buttons are the legitimate way forward; the status dropdown refuses
 * illegal jumps (Reklamo_Statuses::guard_admin_status_change).
 *
 * The metaboxes sit inside the order edit <form>; a nested <form> would be ignored by
 * browsers, so our inputs carry the HTML5 form="…" attribute pointing at forms printed
 * in the admin footer.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Admin_Order {

	const FORM_MOCKUP = 'reklamo-mockup-form';
	const FORM_ACTION = 'reklamo-action-form';

	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ), 10, 2 );
		add_action( 'admin_footer', array( __CLASS__, 'print_forms' ) );
		add_action( 'admin_post_reklamo_send_mockup', array( __CLASS__, 'handle_send_mockup' ) );
		add_action( 'admin_post_reklamo_order_action', array( __CLASS__, 'handle_action' ) );
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
		add_meta_box( 'reklamo_mockup', __( 'Mockup & approval', 'reklamo-core' ), array( __CLASS__, 'render' ), wc_get_page_screen_id( 'shop-order' ), 'normal', 'high' );
	}

	/** Actions offered per status: slug => [label, next status, confirm text]. */
	private static function actions_for( WC_Order $order ): array {
		$deposit = wp_strip_all_tags( wc_price( (float) $order->get_meta( '_reklamo_deposit_amount' ), array( 'currency' => $order->get_currency() ) ) );
		$balance = wp_strip_all_tags( wc_price( Reklamo_Money::balance( (float) $order->get_total(), (float) $order->get_meta( '_reklamo_deposit_amount' ) ), array( 'currency' => $order->get_currency() ) ) );
		$all     = array(
			Reklamo_Statuses::APPROVED     => array(
				/* translators: %s: deposit amount */
				'deposit_paid'   => array( sprintf( __( 'Deposit received (%s)', 'reklamo-core' ), $deposit ), Reklamo_Statuses::DEPOSIT_PAID ),
				'resend_deposit' => array( __( 'Re-send deposit request', 'reklamo-core' ), '' ),
			),
			Reklamo_Statuses::MOCKUP_SENT  => array(
				'resend_mockup' => array( __( 'Re-send mockup email', 'reklamo-core' ), '' ),
			),
			Reklamo_Statuses::DEPOSIT_PAID => array(
				'production' => array( __( 'Start production', 'reklamo-core' ), Reklamo_Statuses::PRODUCTION ),
			),
			Reklamo_Statuses::PRODUCTION   => array(
				'final_due' => array( __( 'Ready — request final payment', 'reklamo-core' ), Reklamo_Statuses::FINAL_DUE ),
			),
			Reklamo_Statuses::FINAL_DUE    => array(
				/* translators: %s: balance amount */
				'complete' => array( sprintf( __( 'Final payment received (%s) — complete', 'reklamo-core' ), $balance ), 'completed' ),
			),
		);
		return $all[ $order->get_status() ] ?? array();
	}

	/** @param WC_Order|WP_Post $post_or_order */
	public static function render( $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$logos   = Reklamo_Storage::for_order( $order->get_id(), 'logo' );
		$mockups = Reklamo_Storage::for_order( $order->get_id(), 'mockup' );
		$tokens  = array();
		foreach ( Reklamo_Approval::for_order( $order->get_id() ) as $t ) {
			if ( 'approval' === $t->purpose ) {
				$tokens[ (int) $t->file_id ] = $tokens[ (int) $t->file_id ] ?? $t; // newest first → first wins
			}
		}
		$next_rev = count( $mockups ) + 1;
		$details  = Reklamo_Approval::details( $order );
		$can_send = $order->has_status( array( Reklamo_Statuses::RECEIVED, Reklamo_Statuses::CHANGES, Reklamo_Statuses::MOCKUP_SENT, Reklamo_Statuses::APPROVED ) );
		$deposit  = (float) $order->get_meta( '_reklamo_deposit_amount' );
		$fmt_date = static fn( string $utc ): string => $utc ? wc_format_datetime( new WC_DateTime( $utc . ' UTC' ), 'd.m.Y H:i' ) : '';
		?>
		<div class="reklamo-admin">
			<p><strong><?php esc_html_e( 'Status', 'reklamo-core' ); ?>:</strong> <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></p>

			<?php $actions = self::actions_for( $order ); ?>
			<?php if ( $actions ) : ?>
				<p class="reklamo-actions">
					<?php foreach ( $actions as $key => $a ) : ?>
						<button type="submit" class="button <?php echo $a[1] ? 'button-primary' : ''; ?>" name="reklamo_action" value="<?php echo esc_attr( $key ); ?>" form="<?php echo esc_attr( self::FORM_ACTION ); ?>"><?php echo esc_html( $a[0] ); ?></button>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<?php $track_url = Reklamo_Tracking::url( $order ); ?>
			<?php if ( $track_url ) : ?>
				<p class="description"><?php esc_html_e( "Customer's order page (in every email they get):", 'reklamo-core' ); ?> <a href="<?php echo esc_url( $track_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'open', 'reklamo-core' ); ?></a></p>
			<?php endif; ?>

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
							echo ' ' . esc_html( $fmt_date( $t->used_at ) );
						} elseif ( $t && ! Reklamo_Token::is_expired( $t->expires_at, time() ) ) {
							echo esc_html__( '— awaiting the customer', 'reklamo-core' );
							/* translators: %s: expiry date */
							echo ' ' . esc_html( sprintf( __( '(link valid until %s)', 'reklamo-core' ), wc_format_datetime( new WC_DateTime( $t->expires_at . ' UTC' ), 'd.m.Y' ) ) );
						} elseif ( $t ) {
							echo esc_html__( '— link expired', 'reklamo-core' );
						}
						?>
						</span>
					</li>
				<?php endforeach; ?>
				</ol>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No mockup sent yet.', 'reklamo-core' ); ?></p>
			<?php endif; ?>
			<?php if ( $order->get_meta( '_reklamo_last_change_request' ) && $order->has_status( Reklamo_Statuses::CHANGES ) ) : ?>
				<p><strong><?php esc_html_e( 'Requested changes', 'reklamo-core' ); ?>:</strong> <?php echo esc_html( $order->get_meta( '_reklamo_last_change_request' ) ); ?></p>
			<?php endif; ?>

			<?php if ( $order->get_meta( '_reklamo_approved_at' ) ) : ?>
				<h4><?php esc_html_e( 'Approval & payments', 'reklamo-core' ); ?></h4>
				<ul>
					<li><?php echo esc_html( sprintf( /* translators: 1: date, 2: mockup revision */ __( 'Approved %1$s (mockup #%2$d)', 'reklamo-core' ), $fmt_date( $order->get_meta( '_reklamo_approved_at' ) ), (int) $order->get_meta( '_reklamo_approved_revision' ) ) ); ?></li>
					<li><?php echo esc_html( sprintf( /* translators: 1: deposit amount, 2: percentage */ __( 'Deposit: %1$s (%2$s%%)', 'reklamo-core' ), wp_strip_all_tags( wc_price( $deposit, array( 'currency' => $order->get_currency() ) ) ), Reklamo_Settings::get( 'deposit_pct', '50' ) ) ); ?>
						<?php
						if ( $order->get_meta( '_reklamo_deposit_paid_at' ) ) :
							?>
							— <?php echo esc_html( sprintf( /* translators: %s: date */ __( 'received %s', 'reklamo-core' ), $fmt_date( $order->get_meta( '_reklamo_deposit_paid_at' ) ) ) ); ?><?php endif; ?>
					</li>
					<li><?php echo esc_html( sprintf( /* translators: %s: balance amount */ __( 'Balance: %s', 'reklamo-core' ), wp_strip_all_tags( wc_price( Reklamo_Money::balance( (float) $order->get_total(), $deposit ), array( 'currency' => $order->get_currency() ) ) ) ) ); ?>
						<?php
						if ( $order->get_meta( '_reklamo_final_paid_at' ) ) :
							?>
							— <?php echo esc_html( sprintf( /* translators: %s: date */ __( 'received %s', 'reklamo-core' ), $fmt_date( $order->get_meta( '_reklamo_final_paid_at' ) ) ) ); ?><?php endif; ?>
					</li>
				</ul>
			<?php endif; ?>

			<h4><?php esc_html_e( 'Invoice & delivery details', 'reklamo-core' ); ?></h4>
			<?php if ( $details['submitted_at'] ) : ?>
				<table class="widefat striped" style="max-width:600px">
					<?php
					$rows = array(
						__( 'Type', 'reklamo-core' )      => 'company' === $details['customer_type'] ? __( 'Company', 'reklamo-core' ) : __( 'Private person', 'reklamo-core' ),
						__( 'Company', 'reklamo-core' )   => $details['company'],
						__( 'Company ID (ЕИК)', 'reklamo-core' ) => $details['eik'],
						__( 'VAT no.', 'reklamo-core' )   => $details['vat'],
						__( 'Responsible person (МОЛ)', 'reklamo-core' ) => $details['mol'],
						__( 'Phone', 'reklamo-core' )     => $details['phone'],
						__( 'Delivery address', 'reklamo-core' ) => trim( $details['address_1'] . ', ' . $details['postcode'] . ' ' . $details['city'], ', ' ),
						__( 'Delivery note', 'reklamo-core' ) => $details['note'],
						__( 'Submitted', 'reklamo-core' ) => $fmt_date( $details['submitted_at'] ),
					);
					foreach ( $rows as $label => $value ) :
						if ( '' === trim( (string) $value ) ) {
							continue;
						}
						?>
						<tr><th style="width:40%"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $value ); ?></td></tr>
					<?php endforeach; ?>
				</table>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Not submitted yet — the customer gets the form after approving the mockup.', 'reklamo-core' ); ?></p>
			<?php endif; ?>

			<?php if ( $can_send ) : ?>
				<h4><?php echo esc_html( sprintf( /* translators: %d: next mockup revision number */ __( 'Send mockup #%d', 'reklamo-core' ), $next_rev ) ); ?></h4>
				<p class="description"><?php esc_html_e( 'PDF, PNG or JPG, up to 20 MB. The customer receives an email with a one-time approval link (valid 14 days).', 'reklamo-core' ); ?></p>
				<p><input type="file" name="reklamo_mockup" form="<?php echo esc_attr( self::FORM_MOCKUP ); ?>" accept=".pdf,.png,.jpg,.jpeg" required></p>
				<p><button type="submit" class="button" form="<?php echo esc_attr( self::FORM_MOCKUP ); ?>"><?php esc_html_e( 'Send to customer', 'reklamo-core' ); ?></button></p>
			<?php endif; ?>

			<?php
			// Hidden inputs for both footer forms (wp_nonce_field() would land inside the outer form).
			printf(
				'<input type="hidden" name="order_id" value="%1$d" form="%2$s"><input type="hidden" name="_wpnonce" value="%3$s" form="%2$s"><input type="hidden" name="action" value="reklamo_send_mockup" form="%2$s">',
				(int) $order->get_id(),
				esc_attr( self::FORM_MOCKUP ),
				esc_attr( wp_create_nonce( 'reklamo_send_mockup' ) )
			);
			printf(
				'<input type="hidden" name="order_id" value="%1$d" form="%2$s"><input type="hidden" name="_wpnonce" value="%3$s" form="%2$s"><input type="hidden" name="action" value="reklamo_order_action" form="%2$s">',
				(int) $order->get_id(),
				esc_attr( self::FORM_ACTION ),
				esc_attr( wp_create_nonce( 'reklamo_order_action' ) )
			);
			?>
		</div>
		<?php
	}

	/** The real form elements live outside the order form so the browser honours them. */
	public static function print_forms(): void {
		if ( ! self::is_order_screen() ) {
			return;
		}
		printf( '<form id="%s" method="post" enctype="multipart/form-data" action="%s"></form>', esc_attr( self::FORM_MOCKUP ), esc_url( admin_url( 'admin-post.php' ) ) );
		printf( '<form id="%s" method="post" action="%s"></form>', esc_attr( self::FORM_ACTION ), esc_url( admin_url( 'admin-post.php' ) ) );
	}

	private static function order_from_request( string $nonce_action ): WC_Order {
		check_admin_referer( $nonce_action );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'reklamo-core' ), 403 );
		}
		$order = wc_get_order( isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0 );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'reklamo-core' ), 404 );
		}
		return $order;
	}

	public static function handle_send_mockup(): void {
		$order = self::order_from_request( 'reklamo_send_mockup' );
		$back  = $order->get_edit_order_url();

		$stored = isset( $_FILES['reklamo_mockup'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in order_from_request().
			? Reklamo_Storage::store_upload( $_FILES['reklamo_mockup'], 'mockup', Reklamo_Storage::MOCKUP_EXTENSIONS, Reklamo_Storage::MOCKUP_MAX_BYTES ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- validated inside store_upload(); nonce checked in order_from_request().
			: new WP_Error( 'reklamo_no_file', __( 'Please choose a file.', 'reklamo-core' ) );
		if ( is_wp_error( $stored ) ) {
			self::redirect( $back, 'error', $stored->get_error_message() );
		}

		$revision = Reklamo_Approval::latest_revision( $order->get_id() ) + 1;
		Reklamo_Storage::claim( $stored['token'], $order->get_id(), 0, $revision );

		$url  = Reklamo_Approval::issue( $order, (int) $stored['id'], $revision, 'approval' );
		$sent = Reklamo_Emails::send_mockup( $order, $url, $revision );

		$note = $sent
			/* translators: %d: mockup revision */
			? sprintf( __( 'Mockup #%d sent to the customer for approval.', 'reklamo-core' ), $revision )
			/* translators: %d: mockup revision */
			: sprintf( __( 'Mockup #%d stored; the approval email could not be sent (see the note above).', 'reklamo-core' ), $revision );
		if ( $order->has_status( Reklamo_Statuses::MOCKUP_SENT ) ) {
			$order->add_order_note( $note );
		} else {
			$order->update_status( Reklamo_Statuses::MOCKUP_SENT, $note );
		}
		if ( ! $sent ) {
			self::redirect( $back, 'error', self::mail_failure_message( $revision ) );
		}
		/* translators: %d: mockup revision */
		self::redirect( $back, 'success', sprintf( __( 'Mockup #%d sent. The customer has the approval link by email.', 'reklamo-core' ), $revision ) );
	}

	/** Shown when the mockup was saved but the customer email did not go out. */
	private static function mail_failure_message( int $revision ): string {
		return sprintf(
			/* translators: 1: mockup revision, 2: error message */
			__( 'Mockup #%1$d is saved, but the email to the customer was NOT sent: %2$s. Check WooCommerce → Reklamo diagnostics → Email, then use "Re-send mockup email".', 'reklamo-core' ),
			$revision,
			Reklamo_Mail::last_error()
		);
	}

	public static function handle_action(): void {
		$order  = self::order_from_request( 'reklamo_order_action' );
		$back   = $order->get_edit_order_url();
		$action = isset( $_POST['reklamo_action'] ) ? sanitize_key( wp_unslash( $_POST['reklamo_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in order_from_request().
		$offer  = self::actions_for( $order );
		if ( ! isset( $offer[ $action ] ) ) {
			self::redirect( $back, 'error', __( 'That action is not available for the current status.', 'reklamo-core' ) );
		}
		$now = current_time( 'mysql', true );
		switch ( $action ) {
			case 'resend_mockup':
				$mockups = Reklamo_Storage::for_order( $order->get_id(), 'mockup' );
				$last    = end( $mockups );
				if ( ! $last ) {
					self::redirect( $back, 'error', __( 'There is no mockup on this order yet.', 'reklamo-core' ) );
				}
				$url = Reklamo_Approval::issue( $order, (int) $last->id, (int) $last->revision, 'approval' );
				if ( ! Reklamo_Emails::send_mockup( $order, $url, (int) $last->revision ) ) {
					self::redirect( $back, 'error', self::mail_failure_message( (int) $last->revision ) );
				}
				$order->add_order_note( __( 'Mockup email re-sent by the shop with a fresh approval link.', 'reklamo-core' ) );
				self::redirect( $back, 'success', __( 'Mockup email re-sent.', 'reklamo-core' ) );
				break;
			case 'resend_deposit':
				$url = Reklamo_Approval::issue( $order, 0, (int) $order->get_meta( '_reklamo_approved_revision' ), 'details' );
				if ( ! Reklamo_Emails::send_deposit_request( $order, $url, true ) ) {
					/* translators: %s: error message */
					self::redirect( $back, 'error', sprintf( __( 'The deposit request was NOT sent: %s. Check WooCommerce → Reklamo diagnostics → Email.', 'reklamo-core' ), Reklamo_Mail::last_error() ) );
				}
				$order->add_order_note( __( 'Deposit request re-sent by the shop.', 'reklamo-core' ) );
				self::redirect( $back, 'success', __( 'Deposit request re-sent.', 'reklamo-core' ) );
				break;
			case 'deposit_paid':
				$order->update_meta_data( '_reklamo_deposit_paid_at', $now );
				$order->save();
				$order->update_status( Reklamo_Statuses::DEPOSIT_PAID, __( 'Deposit marked as received by the shop.', 'reklamo-core' ) );
				break;
			case 'production':
				$order->update_status( Reklamo_Statuses::PRODUCTION, __( 'Production started.', 'reklamo-core' ) );
				break;
			case 'final_due':
				$order->update_status( Reklamo_Statuses::FINAL_DUE, __( 'Order ready; final payment requested.', 'reklamo-core' ) );
				break;
			case 'complete':
				$order->update_meta_data( '_reklamo_final_paid_at', $now );
				$order->save();
				$order->update_status( 'completed', __( 'Final payment received; order completed.', 'reklamo-core' ) );
				break;
		}
		self::redirect( $back, 'success', sprintf( /* translators: %s: new status */ __( 'Order moved to "%s".', 'reklamo-core' ), wc_get_order_status_name( wc_get_order( $order->get_id() )->get_status() ) ) );
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
