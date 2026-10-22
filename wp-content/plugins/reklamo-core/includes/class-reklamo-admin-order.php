<?php
/**
 * Order-screen box "Mockup & approval": per product, the customer's logo and note, its
 * mockup revisions and their approval state, and a mockup upload; mockups chosen for
 * several products go out together in one email. Below that, the invoice/delivery details, and the buttons that move the order
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

	const FORM_MOCKUP   = 'reklamo-mockup-form';
	const FORM_ACTION   = 'reklamo-action-form';
	const FORM_DISPATCH = 'reklamo-dispatch-form';

	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ), 10, 2 );
		add_action( 'admin_footer', array( __CLASS__, 'print_forms' ) );
		add_action( 'admin_post_reklamo_send_mockup', array( __CLASS__, 'handle_send_mockup' ) );
		add_action( 'admin_post_reklamo_order_action', array( __CLASS__, 'handle_action' ) );
		add_action( 'admin_post_reklamo_dispatch', array( __CLASS__, 'handle_dispatch' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		// After WooCommerce's own order data (40), so a delivery change made here is not overwritten.
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save_details' ), 50, 2 );
	}

	/**
	 * The invoice fields the customer typed are custom meta WooCommerce's billing editor
	 * cannot reach; a typo in an ЕИК must be fixable from the order screen.
	 *
	 * @param int              $order_id      Order ID.
	 * @param WC_Order|WP_Post $post_or_order Unused.
	 */
	public static function save_details( int $order_id, $post_or_order ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! isset( $_POST['reklamo_d_customer_type'] ) || ! current_user_can( 'edit_shop_orders' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the order form nonce.
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$get    = static fn( string $k ): string => isset( $_POST[ 'reklamo_d_' . $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'reklamo_d_' . $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$type   = 'person' === $get( 'customer_type' ) ? 'person' : 'company';
		$before = Reklamo_Approval::details( $order );
		$order->update_meta_data( '_reklamo_customer_type', $type );
		$order->update_meta_data( '_reklamo_eik', 'company' === $type ? $get( 'eik' ) : '' );
		$order->update_meta_data( '_reklamo_vat', 'company' === $type ? strtoupper( str_replace( ' ', '', $get( 'vat' ) ) ) : '' );
		$order->update_meta_data( '_reklamo_mol', 'company' === $type ? $get( 'mol' ) : '' );
		$order->update_meta_data( '_reklamo_delivery_note', $get( 'note' ) );
		// Delivery: only what was changed in this box, compared with what it showed. The Shipping box
		// above posts the same address, and an edit made there must stand.
		$changed = static fn( string $k ): bool => isset( $_POST[ 'reklamo_d_' . $k ], $_POST[ 'reklamo_d_was_' . $k ] ) && $get( $k ) !== sanitize_text_field( wp_unslash( $_POST[ 'reklamo_d_was_' . $k ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $changed( 'delivery_type' ) ) {
			$order->update_meta_data( '_reklamo_delivery_type', in_array( $get( 'delivery_type' ), Reklamo_Approval::DELIVERY_TYPES, true ) ? $get( 'delivery_type' ) : 'address' );
		}
		if ( $changed( 'courier' ) ) {
			$order->update_meta_data( '_reklamo_courier', isset( Reklamo_Approval::COURIERS[ $get( 'courier' ) ] ) ? $get( 'courier' ) : '' );
		}
		foreach ( array( 'address_1', 'city', 'postcode' ) as $f ) {
			if ( $changed( $f ) ) {
				$order->{ 'set_shipping_' . $f }( $get( $f ) );
			}
		}
		$order->save();
		if ( Reklamo_Approval::details( $order ) !== $before ) {
			$order->add_order_note( __( 'Invoice and delivery details edited by the shop.', 'reklamo-core' ) );
		}
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
		$all     = array(
			Reklamo_Statuses::APPROVED     => array(
				/* translators: %s: deposit amount */
				'deposit_paid'   => array( sprintf( __( 'Deposit received (%s)', 'reklamo-core' ), $deposit ), Reklamo_Statuses::DEPOSIT_PAID ),
				'resend_deposit' => array( __( 'Re-send deposit request', 'reklamo-core' ), '' ),
				'recalc_deposit' => array( __( 'Recalculate deposit from the total', 'reklamo-core' ), '' ),
			),
			Reklamo_Statuses::MOCKUP_SENT  => array(
				'resend_mockup' => array( __( 'Re-send mockup email', 'reklamo-core' ), '' ),
			),
			Reklamo_Statuses::CHANGES      => array(
				'resend_mockup' => array( __( 'Re-send mockup email', 'reklamo-core' ), '' ),
			),
			Reklamo_Statuses::DEPOSIT_PAID => array(
				'production' => array( __( 'Start production', 'reklamo-core' ), Reklamo_Statuses::PRODUCTION ),
			),
			// Paid in full before production: nothing left to ask for, the order ends with the dispatch box.
			Reklamo_Statuses::PRODUCTION   => self::can_dispatch( $order ) ? array() : array( 'final_due' => array( __( 'Ready — request final payment', 'reklamo-core' ), Reklamo_Statuses::FINAL_DUE ) ),
		);
		if ( $order->has_status( 'cancelled' ) && $order->get_meta( Reklamo_Payment_Deadline::META_EXPIRED ) ) {
			/* translators: %s: deposit amount */
			$all['cancelled'] = array( 'deposit_paid' => array( sprintf( __( 'Late payment received (%s) — restore the order', 'reklamo-core' ), $deposit ), Reklamo_Statuses::DEPOSIT_PAID ) );
		}
		$offer = $all[ $order->get_status() ] ?? array();
		if ( isset( $offer['resend_mockup'] ) && ! Reklamo_Items::counts( $order )['sent'] ) {
			unset( $offer['resend_mockup'] ); // nothing awaits the customer
		}
		return $offer;
	}

	/** @param WC_Order|WP_Post $post_or_order */
	public static function render( $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$tokens = array();
		foreach ( Reklamo_Approval::for_order( $order->get_id() ) as $t ) {
			if ( 'approval' === $t->purpose ) {
				$tokens[ (int) $t->file_id ] = $tokens[ (int) $t->file_id ] ?? $t; // newest first → first wins
			}
		}
		$comments = Reklamo_Items::change_requests( $order );
		$details  = Reklamo_Approval::details( $order );
		$can_send = $order->has_status( array( Reklamo_Statuses::RECEIVED, Reklamo_Statuses::CHANGES, Reklamo_Statuses::MOCKUP_SENT, Reklamo_Statuses::APPROVED ) );
		$deposit  = (float) $order->get_meta( '_reklamo_deposit_amount' );
		$fmt_date = static fn( string $utc ): string => $utc ? wc_format_datetime( new WC_DateTime( $utc . ' UTC' ), 'd.m.Y H:i' ) : '';
		?>
		<div class="reklamo-admin">
			<p><strong><?php esc_html_e( 'Status', 'reklamo-core' ); ?>:</strong> <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></p>
			<?php $deadline = $order->has_status( Reklamo_Statuses::APPROVED ) && Reklamo_Payment_Deadline::enabled() ? Reklamo_Payment_Deadline::for_order( $order ) : null; ?>
			<?php if ( $deadline ) : ?>
				<p class="reklamo-deadline">
					<?php if ( $deadline['remind'] ) : ?>
						<?php /* translators: %s: date and time */ echo esc_html( sprintf( __( 'Payment due by %s (reminder then).', 'reklamo-core' ), Reklamo_Payment_Deadline::format( $deadline['remind'] ) ) ); ?><br>
					<?php endif; ?>
					<strong><?php /* translators: %s: date and time */ echo esc_html( sprintf( __( 'Cancelled automatically at %s if still unpaid.', 'reklamo-core' ), Reklamo_Payment_Deadline::format( $deadline['cancel'] ) ) ); ?></strong>
				</p>
			<?php endif; ?>

			<?php $actions = self::actions_for( $order ); ?>
			<?php if ( $actions ) : ?>
				<p class="reklamo-actions">
					<?php foreach ( $actions as $key => $a ) : ?>
						<button type="submit" class="button <?php echo $a[1] ? 'button-primary' : ''; ?>" name="reklamo_action" value="<?php echo esc_attr( $key ); ?>" form="<?php echo esc_attr( self::FORM_ACTION ); ?>"><?php echo esc_html( $a[0] ); ?></button>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<?php if ( self::can_dispatch( $order ) ) : ?>
				<?php self::render_dispatch( $order ); ?>
			<?php endif; ?>

			<?php $track_url = Reklamo_Tracking::url( $order ); ?>
			<?php if ( $track_url ) : ?>
				<p class="description"><?php esc_html_e( "Customer's order page (in every email they get):", 'reklamo-core' ); ?> <a href="<?php echo esc_url( $track_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'open', 'reklamo-core' ); ?></a></p>
			<?php endif; ?>

			<?php
			$labels = array(
				Reklamo_Items::WAITING  => __( 'Waiting for the first mockup', 'reklamo-core' ),
				Reklamo_Items::SENT     => __( 'Mockup sent — awaiting the customer', 'reklamo-core' ),
				Reklamo_Items::CHANGES  => __( 'Changes requested — your turn', 'reklamo-core' ),
				Reklamo_Items::APPROVED => __( 'Approved', 'reklamo-core' ),
			);
			?>
			<?php foreach ( Reklamo_Items::products( $order ) as $item_id => $item ) : ?>
				<?php
				$state   = Reklamo_Items::state( $item );
				$logos   = Reklamo_Items::logos( $item );
				$note    = Reklamo_Items::note( $item );
				$mockups = Reklamo_Items::mockups( $order->get_id(), $item_id );
				$current = Reklamo_Items::revision( $item );
				?>
				<div class="reklamo-item" data-item="<?php echo esc_attr( (string) $item_id ); ?>" style="border-top:1px solid #dcdcde;padding:.4em 0 .8em">
					<h4 style="margin:.8em 0 .3em"><?php echo esc_html( $item->get_name() ); ?> <span class="description" style="font-weight:400">— <?php echo esc_html( $labels[ $state ] ); ?>
						<?php
						$used = Reklamo_Items::corrections( $item );
						$free = (int) Reklamo_Settings::get( 'free_corrections', '2' );
						/* translators: 1: corrections asked for, 2: free corrections included */
						echo esc_html( ' · ' . sprintf( __( 'corrections: %1$d (free: %2$d)', 'reklamo-core' ), $used, $free ) );
						?>
					</span></h4>
					<?php if ( $used > $free ) : ?>
						<p class="description" style="margin:0 0 .4em;color:#9a5b00"><?php echo esc_html( sprintf( /* translators: 1: corrections over the free ones, 2: fee */ __( '%1$d over the free corrections — %2$s € each, unless they fixed our own mistakes.', 'reklamo-core' ), $used - $free, Reklamo_Settings::get( 'correction_fee', '15' ) ) ); ?></p>
					<?php endif; ?>
					<?php $inscription = (string) $item->get_meta( Reklamo_Items::META_TEXT ); ?>
					<?php $print = (string) $item->get_meta( Reklamo_Items::META_COLOUR ); ?>
					<?php if ( '' !== $inscription ) : ?>
						<p style="margin:.2em 0"><strong><?php esc_html_e( 'Inscription', 'reklamo-core' ); ?>:</strong></p>
						<p style="margin:.2em 0 .4em;padding:.4em .6em;background:#f6f7f7;white-space:pre-line;font-size:1.05em"><?php echo esc_html( $inscription ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $print ) : ?>
						<p style="margin:.2em 0"><strong><?php esc_html_e( 'Print colour', 'reklamo-core' ); ?>:</strong> <?php echo esc_html( Reklamo_Cart::print_colours()[ $print ] ?? $print ); ?></p>
					<?php endif; ?>
					<p style="margin:.2em 0"<?php echo '' !== $inscription && ! $logos ? ' hidden' : ''; ?>>
						<strong><?php echo esc_html( count( $logos ) > 1 ? __( 'Customer logos', 'reklamo-core' ) : __( 'Customer logo', 'reklamo-core' ) ); ?>:</strong>
						<?php if ( $logos ) : ?>
							<?php foreach ( $logos as $i => $logo ) : ?>
								<?php echo $i ? ', ' : ''; ?><a href="<?php echo esc_url( Reklamo_Storage::download_url( (int) $logo->id ) ); ?>"><?php echo esc_html( Reklamo_Storage::describe( $logo ) ); ?></a>
							<?php endforeach; ?>
						<?php else : ?>
							<span class="description"><?php esc_html_e( 'No logo attached to this product.', 'reklamo-core' ); ?></span>
						<?php endif; ?>
					</p>
					<?php if ( '' !== $note ) : ?>
						<p style="margin:.2em 0"><strong><?php esc_html_e( 'Note to designer', 'reklamo-core' ); ?>:</strong> <?php echo esc_html( $note ); ?></p>
					<?php endif; ?>
					<?php if ( $mockups ) : ?>
						<ol style="margin-top:.4em">
						<?php
						foreach ( $mockups as $m ) :
							$t       = $tokens[ (int) $m->id ] ?? null;
							$comment = (string) ( $comments[ $item_id ][ (int) $m->revision ] ?? '' );
							?>
							<li value="<?php echo esc_attr( (string) $m->revision ); ?>">
								<a href="<?php echo esc_url( Reklamo_Storage::download_url( (int) $m->id ) ); ?>"><?php echo esc_html( Reklamo_Storage::describe( $m ) ); ?></a>
								<span class="description">
								<?php
								if ( $t && $t->used_at ) {
									echo esc_html( 'approve' === $t->used_action ? __( '— approved', 'reklamo-core' ) : __( '— changes requested', 'reklamo-core' ) );
									echo ' ' . esc_html( $fmt_date( $t->used_at ) );
								} elseif ( (int) $m->revision === $current && Reklamo_Items::APPROVED === $state ) {
									echo esc_html__( '— approved', 'reklamo-core' );
								} elseif ( $t && ! Reklamo_Token::is_expired( $t->expires_at, time() ) ) {
									echo esc_html__( '— awaiting the customer', 'reklamo-core' );
									/* translators: %s: expiry date */
									echo ' ' . esc_html( sprintf( __( '(link valid until %s)', 'reklamo-core' ), wc_format_datetime( new WC_DateTime( $t->expires_at . ' UTC' ), 'd.m.Y' ) ) );
								} elseif ( $t ) {
									echo esc_html__( '— link expired', 'reklamo-core' );
								}
								?>
								</span>
								<?php
								if ( '' !== $comment ) :
									// The open request keeps full contrast; the ones already answered recede.
									$open = (int) $m->revision === $current && Reklamo_Items::CHANGES === $state;
									?>
									<p class="<?php echo $open ? '' : 'description'; ?>" style="margin:.2em 0 .8em">
										<strong><?php esc_html_e( 'Requested changes', 'reklamo-core' ); ?>:</strong>
										<?php echo esc_html( $comment ); ?>
									</p>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
						</ol>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No mockup sent yet.', 'reklamo-core' ); ?></p>
					<?php endif; ?>
					<?php if ( $can_send ) : ?>
						<p style="margin:.4em 0 0">
							<label>
								<?php echo esc_html( sprintf( /* translators: %d: next mockup revision number */ __( 'Mockup #%d', 'reklamo-core' ), max( $current, count( $mockups ) ) + 1 ) ); ?>:
								<input type="file" name="reklamo_mockup[<?php echo esc_attr( (string) $item_id ); ?>]" form="<?php echo esc_attr( self::FORM_MOCKUP ); ?>" accept=".pdf,.png,.jpg,.jpeg">
							</label>
						</p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<?php if ( $can_send ) : ?>
				<p class="description"><?php echo esc_html( sprintf( /* translators: 1: size limit, 2: days */ __( 'PDF, PNG or JPG, up to %1$s each. Choose a file for one product or several: they go out together in one email, each with its own one-time approval link (valid %2$d days).', 'reklamo-core' ), size_format( Reklamo_Storage::MOCKUP_MAX_BYTES ), Reklamo_Approval::TTL_DAYS ) ); ?></p>
				<p><button type="submit" class="button" form="<?php echo esc_attr( self::FORM_MOCKUP ); ?>"><?php esc_html_e( 'Send to customer', 'reklamo-core' ); ?></button></p>
			<?php endif; ?>

			<?php if ( $order->get_meta( '_reklamo_approved_at' ) ) : ?>
				<h4><?php esc_html_e( 'Approval & payments', 'reklamo-core' ); ?></h4>
				<?php
				$expected = Reklamo_Money::deposit( (float) $order->get_total(), (int) Reklamo_Settings::get( 'deposit_pct', '100' ) );
				if ( ! $order->get_meta( '_reklamo_deposit_paid_at' ) && abs( $expected - $deposit ) >= 0.01 ) :
					?>
					<p class="notice notice-warning inline" style="margin:0 0 .6em;padding:.4em .8em"><?php echo esc_html( sprintf( /* translators: 1: stored deposit, 2: expected deposit */ __( 'The deposit (%1$s) no longer matches the order total (expected %2$s). Use "Recalculate deposit" if the total was changed on purpose.', 'reklamo-core' ), wp_strip_all_tags( wc_price( $deposit, array( 'currency' => $order->get_currency() ) ) ), wp_strip_all_tags( wc_price( $expected, array( 'currency' => $order->get_currency() ) ) ) ) ); ?></p>
				<?php endif; ?>
				<ul>
					<li><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Every mockup approved %s', 'reklamo-core' ), $fmt_date( $order->get_meta( '_reklamo_approved_at' ) ) ) ); ?></li>
					<li><?php echo esc_html( sprintf( /* translators: 1: deposit amount, 2: percentage */ __( 'Deposit: %1$s (%2$s%%)', 'reklamo-core' ), wp_strip_all_tags( wc_price( $deposit, array( 'currency' => $order->get_currency() ) ) ), Reklamo_Settings::get( 'deposit_pct', '100' ) ) ); ?>
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

			<?php if ( $order->get_meta( '_reklamo_consent_at' ) ) : ?>
				<p class="description">
					<?php echo esc_html( sprintf( /* translators: 1: date/time, 2: version of the legal texts */ __( 'Consent to the Terms and the Privacy Policy: %1$s (version %2$s).', 'reklamo-core' ), $fmt_date( $order->get_meta( '_reklamo_consent_at' ) ), $order->get_meta( '_reklamo_consent_version' ) ? $order->get_meta( '_reklamo_consent_version' ) : '-' ) ); ?>
					<?php if ( $order->get_meta( '_reklamo_waiver_at' ) ) : ?>
						<?php esc_html_e( 'Withdrawal-right waiver accepted.', 'reklamo-core' ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<h4><?php esc_html_e( 'Invoice & delivery details', 'reklamo-core' ); ?></h4>
			<?php if ( $details['submitted_at'] ) : ?>
				<?php // Inside the order form, so "Update" saves them (see save_details()). Company and phone are WooCommerce billing fields, edited in their own box. ?>
				<table class="widefat striped reklamo-details" style="max-width:600px">
					<tr><th style="width:40%"><?php esc_html_e( 'Type', 'reklamo-core' ); ?></th><td>
						<select name="reklamo_d_customer_type">
							<option value="company" <?php selected( 'company', $details['customer_type'] ); ?>><?php esc_html_e( 'Company', 'reklamo-core' ); ?></option>
							<option value="person" <?php selected( 'person', $details['customer_type'] ); ?>><?php esc_html_e( 'Private person', 'reklamo-core' ); ?></option>
						</select></td></tr>
					<tr><th><?php esc_html_e( 'Company ID (ЕИК)', 'reklamo-core' ); ?></th><td><input type="text" name="reklamo_d_eik" value="<?php echo esc_attr( $details['eik'] ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'VAT no.', 'reklamo-core' ); ?></th><td><input type="text" name="reklamo_d_vat" value="<?php echo esc_attr( $details['vat'] ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'Responsible person (МОЛ)', 'reklamo-core' ); ?></th><td><input type="text" name="reklamo_d_mol" value="<?php echo esc_attr( $details['mol'] ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'Delivery note', 'reklamo-core' ); ?></th><td><input type="text" name="reklamo_d_note" value="<?php echo esc_attr( $details['note'] ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'Company', 'reklamo-core' ); ?></th><td><?php echo esc_html( $details['company'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Phone', 'reklamo-core' ); ?></th><td><?php echo esc_html( $details['phone'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Delivery', 'reklamo-core' ); ?></th><td>
						<select name="reklamo_d_delivery_type">
							<?php foreach ( Reklamo_Approval::DELIVERY_TYPES as $reklamo_dt ) : ?>
								<option value="<?php echo esc_attr( $reklamo_dt ); ?>" <?php selected( $reklamo_dt, $details['delivery_type'] ); ?>><?php echo esc_html( Reklamo_Approval::delivery_type_label( $reklamo_dt ) ); ?></option>
							<?php endforeach; ?>
						</select></td></tr>
					<tr><th><?php esc_html_e( 'Courier', 'reklamo-core' ); ?></th><td>
						<select name="reklamo_d_courier">
							<option value=""><?php esc_html_e( 'No preference', 'reklamo-core' ); ?></option>
							<?php foreach ( Reklamo_Approval::COURIERS as $reklamo_ck => $reklamo_cn ) : ?>
								<option value="<?php echo esc_attr( $reklamo_ck ); ?>" <?php selected( $reklamo_ck, $details['courier'] ); ?>><?php echo esc_html( $reklamo_cn ); ?></option>
							<?php endforeach; ?>
						</select></td></tr>
					<tr><th><?php esc_html_e( 'Address, office or parcel locker', 'reklamo-core' ); ?></th><td><input type="text" name="reklamo_d_address_1" value="<?php echo esc_attr( $details['address_1'] ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'City', 'reklamo-core' ); ?></th><td><input type="text" name="reklamo_d_city" value="<?php echo esc_attr( $details['city'] ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'Postcode', 'reklamo-core' ); ?></th><td><input type="text" name="reklamo_d_postcode" value="<?php echo esc_attr( $details['postcode'] ); ?>" class="small-text"></td></tr>
					<?php if ( $details['billing_address'] ) : ?>
						<tr><th><?php esc_html_e( 'Company address (for the invoice)', 'reklamo-core' ); ?></th><td><?php echo esc_html( $details['billing_address'] ); ?></td></tr>
					<?php endif; ?>
					<?php
					foreach ( array( 'delivery_type', 'courier', 'address_1', 'city', 'postcode' ) as $reklamo_f ) {
						printf( '<input type="hidden" name="reklamo_d_was_%1$s" value="%2$s">', esc_attr( $reklamo_f ), esc_attr( $details[ $reklamo_f ] ) );
					}
					?>
					<tr><th><?php esc_html_e( 'Submitted', 'reklamo-core' ); ?></th><td><?php echo esc_html( $fmt_date( $details['submitted_at'] ) ); ?></td></tr>
				</table>
				<p class="description"><?php esc_html_e( 'Saved with the order\'s "Update" button, e.g. when the customer calls to change the delivery. Company name and phone are edited in the Billing box.', 'reklamo-core' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Not submitted yet — the customer gets the form after approving the mockup.', 'reklamo-core' ); ?></p>
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
		printf( '<form id="%s" method="post" enctype="multipart/form-data" action="%s"></form>', esc_attr( self::FORM_DISPATCH ), esc_url( admin_url( 'admin-post.php' ) ) );
	}

	/** The last step: paid in full and in production, or the balance requested. */
	private static function can_dispatch( WC_Order $order ): bool {
		if ( $order->has_status( Reklamo_Statuses::FINAL_DUE ) ) {
			return true;
		}
		return $order->has_status( Reklamo_Statuses::PRODUCTION ) && Reklamo_Money::balance( (float) $order->get_total(), (float) $order->get_meta( '_reklamo_deposit_amount' ) ) <= 0;
	}

	private static function render_dispatch( WC_Order $order ): void {
		$f       = self::FORM_DISPATCH;
		$courier = (string) $order->get_meta( Reklamo_Dispatch::META_COURIER );
		$courier = '' !== $courier ? $courier : (string) ( Reklamo_Approval::details( $order )['courier'] ?? '' );
		$final   = $order->has_status( Reklamo_Statuses::FINAL_DUE );
		$balance = wp_strip_all_tags( wc_price( Reklamo_Money::balance( (float) $order->get_total(), (float) $order->get_meta( '_reklamo_deposit_amount' ) ), array( 'currency' => $order->get_currency() ) ) );
		?>
		<div class="reklamo-dispatch">
			<h4><?php esc_html_e( 'Dispatch', 'reklamo-core' ); ?></h4>
			<p class="description"><?php esc_html_e( 'The waybill and the invoice go to the customer in the "Order completed" email, the last one they get; the order page shows them too.', 'reklamo-core' ); ?></p>
			<table class="form-table reklamo-details">
				<tr>
					<th><label for="reklamo-courier"><?php esc_html_e( 'Courier', 'reklamo-core' ); ?></label></th>
					<td>
						<select id="reklamo-courier" name="courier" form="<?php echo esc_attr( $f ); ?>">
							<option value=""><?php esc_html_e( '— none —', 'reklamo-core' ); ?></option>
							<?php foreach ( Reklamo_Approval::COURIERS as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $courier, $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="reklamo-waybill"><?php esc_html_e( 'Waybill number', 'reklamo-core' ); ?></label></th>
					<td><input type="text" id="reklamo-waybill" name="waybill" maxlength="40" value="<?php echo esc_attr( (string) $order->get_meta( Reklamo_Dispatch::META_WAYBILL ) ); ?>" form="<?php echo esc_attr( $f ); ?>"></td>
				</tr>
				<tr>
					<th><label for="reklamo-invoice"><?php esc_html_e( 'Invoice', 'reklamo-core' ); ?></label></th>
					<td>
						<?php $invoice = Reklamo_Dispatch::invoice( $order ); ?>
						<?php if ( $invoice ) : ?>
							<a href="<?php echo esc_url( Reklamo_Storage::download_url( (int) $invoice->id ) ); ?>"><?php echo esc_html( $invoice->orig_name ); ?></a> — <?php esc_html_e( 'a new file replaces it', 'reklamo-core' ); ?><br>
						<?php endif; ?>
						<input type="file" id="reklamo-invoice" name="invoice" accept=".pdf,.png,.jpg,.jpeg" form="<?php echo esc_attr( $f ); ?>">
					</td>
				</tr>
			</table>
			<p>
				<button type="submit" class="button button-primary" form="<?php echo esc_attr( $f ); ?>">
					<?php
					echo esc_html(
						$final
							/* translators: %s: balance amount */
							? sprintf( __( 'Final payment received (%s) — complete', 'reklamo-core' ), $balance )
							: __( 'Dispatched — complete the order', 'reklamo-core' )
					);
					?>
				</button>
			</p>
			<?php
			printf(
				'<input type="hidden" name="order_id" value="%1$d" form="%2$s"><input type="hidden" name="_wpnonce" value="%3$s" form="%2$s"><input type="hidden" name="action" value="reklamo_dispatch" form="%2$s">',
				(int) $order->get_id(),
				esc_attr( $f ),
				esc_attr( wp_create_nonce( 'reklamo_dispatch' ) )
			);
			?>
		</div>
		<?php
	}

	/** Store the waybill and the invoice, then complete: WooCommerce's "Completed order" email carries both. */
	public static function handle_dispatch(): void {
		$order = self::order_from_request( 'reklamo_dispatch' );
		$back  = $order->get_edit_order_url();
		if ( ! self::can_dispatch( $order ) ) {
			self::redirect( $back, 'error', __( 'That action is not available for the current status.', 'reklamo-core' ) );
		}
		$courier = isset( $_POST['courier'] ) ? sanitize_key( wp_unslash( $_POST['courier'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in order_from_request().
		$courier = isset( Reklamo_Approval::COURIERS[ $courier ] ) ? $courier : '';
		$waybill = Reklamo_Dispatch::clean_waybill( isset( $_POST['waybill'] ) ? sanitize_text_field( wp_unslash( $_POST['waybill'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( null === $waybill ) {
			self::redirect( $back, 'error', __( 'The waybill number may hold only letters, digits, spaces and dashes (4–40 characters).', 'reklamo-core' ) );
		}
		$file = isset( $_FILES['invoice'] ) && is_array( $_FILES['invoice'] ) ? $_FILES['invoice'] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- validated in store_upload().
		$note = array();
		if ( $file && UPLOAD_ERR_NO_FILE !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			$row = Reklamo_Storage::store_upload( $file, Reklamo_Dispatch::INVOICE_KIND, Reklamo_Dispatch::INVOICE_EXTENSIONS, Reklamo_Storage::MOCKUP_MAX_BYTES );
			if ( is_wp_error( $row ) ) {
				/* translators: %s: error message */
				self::redirect( $back, 'error', sprintf( __( 'Invoice: %s', 'reklamo-core' ), $row->get_error_message() ) );
			}
			Reklamo_Storage::claim( $row['token'], $order->get_id() );
			/* translators: %s: file name */
			$note[] = sprintf( __( 'invoice %s attached', 'reklamo-core' ), $row['orig_name'] ?? '' );
		}
		$order->update_meta_data( Reklamo_Dispatch::META_COURIER, $courier );
		$order->update_meta_data( Reklamo_Dispatch::META_WAYBILL, $waybill );
		if ( $order->has_status( Reklamo_Statuses::FINAL_DUE ) ) {
			$order->update_meta_data( '_reklamo_final_paid_at', current_time( 'mysql', true ) );
		}
		$order->save();
		if ( '' !== $waybill ) {
			array_unshift(
				$note,
				'' !== $courier
					/* translators: 1: waybill number, 2: courier */
					? sprintf( __( 'waybill %1$s (%2$s)', 'reklamo-core' ), $waybill, Reklamo_Approval::COURIERS[ $courier ] )
					/* translators: %s: waybill number */
					: sprintf( __( 'waybill %s', 'reklamo-core' ), $waybill )
			);
		}
		$order->update_status(
			'completed',
			$order->has_status( Reklamo_Statuses::FINAL_DUE )
				? __( 'Final payment received; order completed.', 'reklamo-core' )
				: __( 'Order dispatched; it was paid in full before production.', 'reklamo-core' )
		);
		if ( $note ) {
			/* translators: %s: waybill and invoice summary */
			$order->add_order_note( sprintf( __( 'Dispatch: %s.', 'reklamo-core' ), implode( '; ', $note ) ) );
		}
		self::redirect( $back, 'success', __( 'Order completed; the customer got the final email.', 'reklamo-core' ) );
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
		if ( ! $order->has_status( Reklamo_Items::LOOP_STATUSES ) ) {
			self::redirect( $back, 'error', __( 'Mockups can no longer be sent: the deposit step has passed.', 'reklamo-core' ) );
		}

		// PHP spreads reklamo_mockup[<item>] across name[], tmp_name[], …; put each file back together.
		$posted = isset( $_FILES['reklamo_mockup'] ) && is_array( $_FILES['reklamo_mockup']['name'] ?? null ) ? $_FILES['reklamo_mockup'] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- nonce checked in order_from_request(); each file is validated in store_upload().
		$files  = array();
		foreach ( array_keys( (array) ( $posted['name'] ?? array() ) ) as $item_id ) {
			$file = array();
			foreach ( array( 'name', 'type', 'tmp_name', 'error', 'size' ) as $key ) {
				$file[ $key ] = $posted[ $key ][ $item_id ] ?? '';
			}
			if ( UPLOAD_ERR_NO_FILE !== (int) $file['error'] && Reklamo_Items::item( $order, (int) $item_id ) ) {
				$files[ (int) $item_id ] = $file;
			}
		}
		if ( ! $files ) {
			self::redirect( $back, 'error', __( 'Choose a mockup file for at least one product.', 'reklamo-core' ) );
		}
		$stored = array();
		foreach ( $files as $item_id => $file ) {
			$row = Reklamo_Storage::store_upload( $file, 'mockup', Reklamo_Storage::MOCKUP_EXTENSIONS, Reklamo_Storage::MOCKUP_MAX_BYTES );
			if ( is_wp_error( $row ) ) {
				foreach ( $stored as $done ) {
					Reklamo_Storage::delete( (object) $done['row'] );
				}
				/* translators: 1: product name, 2: error message */
				self::redirect( $back, 'error', sprintf( __( '%1$s: %2$s', 'reklamo-core' ), Reklamo_Items::item( $order, $item_id )->get_name(), $row->get_error_message() ) );
			}
			$stored[ $item_id ] = array( 'row' => $row );
		}

		$entries = array();
		foreach ( $stored as $item_id => $s ) {
			$item     = Reklamo_Items::item( $order, $item_id );
			$revision = max( Reklamo_Items::revision( $item ), count( Reklamo_Items::mockups( $order->get_id(), $item_id ) ) ) + 1;
			Reklamo_Storage::claim( $s['row']['token'], $order->get_id(), $item_id, $revision );
			Reklamo_Items::mark_sent( $item, $revision );
			$entries[] = array(
				'name'     => $item->get_name(),
				'revision' => $revision,
				'url'      => Reklamo_Approval::issue( $order, (int) $s['row']['id'], $revision, 'approval', $item_id ),
			);
		}

		$list = implode( ', ', array_map( static fn( array $e ): string => sprintf( /* translators: 1: product name, 2: mockup revision */ __( '%1$s (mockup #%2$d)', 'reklamo-core' ), $e['name'], $e['revision'] ), $entries ) );
		$sent = Reklamo_Emails::send_mockup( $order, $entries );
		$note = $sent
			/* translators: %s: products with their mockup revisions */
			? sprintf( __( 'Sent to the customer for approval: %s.', 'reklamo-core' ), $list )
			/* translators: %s: products with their mockup revisions */
			: sprintf( __( 'Mockups stored but the approval email could not be sent (see the note above): %s.', 'reklamo-core' ), $list );
		$before = $order->get_status();
		Reklamo_Items::sync_status( $order, $note );
		if ( wc_get_order( $order->get_id() )->get_status() === $before ) {
			Reklamo_Reminders::schedule_for( $order ); // no status change → reschedule by hand for the new revisions
		}
		if ( ! $sent ) {
			self::redirect( $back, 'error', self::mail_failure_message( $list ) );
		}
		/* translators: %s: products with their mockup revisions */
		self::redirect( $back, 'success', sprintf( __( 'Sent: %s. The customer has the approval links by email.', 'reklamo-core' ), $list ) );
	}

	/** Shown when the mockups were saved but the customer email did not go out. */
	private static function mail_failure_message( string $what ): string {
		return sprintf(
			/* translators: 1: products with their mockup revisions, 2: error message */
			__( 'Saved: %1$s. But the email to the customer was NOT sent: %2$s. Check WooCommerce → Reklamo diagnostics → Email, then use "Re-send mockup email".', 'reklamo-core' ),
			$what,
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
				$entries = array();
				foreach ( Reklamo_Items::products( $order ) as $item_id => $item ) {
					$revision = Reklamo_Items::revision( $item );
					$file     = Reklamo_Items::mockup( $order->get_id(), $item_id, $revision );
					if ( Reklamo_Items::SENT === Reklamo_Items::state( $item ) && $file ) {
						$entries[] = array(
							'name'     => $item->get_name(),
							'revision' => $revision,
							'url'      => Reklamo_Approval::issue( $order, (int) $file->id, $revision, 'approval', $item_id ),
						);
					}
				}
				if ( ! $entries ) {
					self::redirect( $back, 'error', __( 'No mockup on this order awaits the customer.', 'reklamo-core' ) );
				}
				if ( ! Reklamo_Emails::send_mockup( $order, $entries ) ) {
					self::redirect( $back, 'error', self::mail_failure_message( implode( ', ', wp_list_pluck( $entries, 'name' ) ) ) );
				}
				Reklamo_Reminders::schedule_for( $order );
				$order->add_order_note( __( 'Mockup email re-sent by the shop with a fresh approval link.', 'reklamo-core' ) );
				self::redirect( $back, 'success', __( 'Mockup email re-sent.', 'reklamo-core' ) );
				break;
			case 'recalc_deposit':
				$new = Reklamo_Money::deposit( (float) $order->get_total(), (int) Reklamo_Settings::get( 'deposit_pct', '100' ) );
				$order->update_meta_data( '_reklamo_deposit_amount', wc_format_decimal( $new, 2 ) );
				$order->save();
				/* translators: %s: amount */
				$order->add_order_note( sprintf( __( 'Deposit recalculated from the current total: %s.', 'reklamo-core' ), wp_strip_all_tags( wc_price( $new, array( 'currency' => $order->get_currency() ) ) ) ) );
				self::redirect( $back, 'success', __( 'Deposit recalculated. Re-send the deposit request if the customer already has the old amount.', 'reklamo-core' ) );
				break;
			case 'resend_deposit':
				$url = Reklamo_Approval::issue( $order, 0, 0, 'details' );
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
				$order->update_status( Reklamo_Statuses::DEPOSIT_PAID, $order->has_status( 'cancelled' ) ? __( 'Late payment received: the automatically cancelled order is restored.', 'reklamo-core' ) : __( 'Deposit marked as received by the shop.', 'reklamo-core' ) );
				break;
			case 'production':
				$order->update_status( Reklamo_Statuses::PRODUCTION, __( 'Production started.', 'reklamo-core' ) );
				break;
			case 'final_due':
				$order->update_status( Reklamo_Statuses::FINAL_DUE, __( 'Order ready; final payment requested.', 'reklamo-core' ) );
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
