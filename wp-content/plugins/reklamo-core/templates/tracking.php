<?php
/**
 * The customer's order page. Self-contained: inline CSS only, no external requests.
 *
 * @var string        $view  not_found | rate_limited | expired | track
 * @var WC_Order|null $order
 * @var string        $selector
 * @var string        $secret
 * @var string        $url
 * @var string        $flash  approved | changes | message | none | nonce | ''
 * @var bool          $fresh  just created from the request form
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

// Included inside a closure by Reklamo_Tracking::render(): these are locals, not globals.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$view     = $vars['view'] ?? 'not_found';
$order    = $vars['order'] ?? null;
$selector = $vars['selector'] ?? '';
$url      = $vars['url'] ?? '';
$flash    = $vars['flash'] ?? '';
$fresh    = ! empty( $vars['fresh'] );
$v        = $vars;
$file_url = static fn( int $id ): string => add_query_arg( 'view', $id, $url );
$open     = isset( $_GET['item'] ) ? absint( $_GET['item'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which change form to reopen.
$counts   = $v['counts'] ?? array();
$multi    = ( $counts['total'] ?? 0 ) > 1;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<title><?php echo esc_html( get_bloginfo( 'name' ) . ' - ' . ( $order ? sprintf( /* translators: %s: order number */ __( 'Order %s', 'reklamo-core' ), $order->get_order_number() ) : __( 'Your order', 'reklamo-core' ) ) ); ?></title>
<?php require REKLAMO_PATH . 'templates/customer-style.php'; ?>
</head>
<body>
<div class="wrap">
	<div class="brand"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:inherit;text-decoration:none"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a></div>
	<div class="card">
	<?php if ( 'not_found' === $view ) : ?>
		<h1><?php esc_html_e( 'This link is not valid', 'reklamo-core' ); ?></h1>
		<p><?php esc_html_e( 'Please open the link exactly as it appears in the email, or contact us.', 'reklamo-core' ); ?></p>

	<?php elseif ( 'rate_limited' === $view ) : ?>
		<h1><?php esc_html_e( 'Too many attempts', 'reklamo-core' ); ?></h1>
		<p><?php esc_html_e( 'Please try again in an hour.', 'reklamo-core' ); ?></p>

	<?php elseif ( 'expired' === $view ) : ?>
		<h1><?php esc_html_e( 'This order page is no longer available', 'reklamo-core' ); ?></h1>
		<p>
			<?php
			printf(
				/* translators: %s: order number */
				esc_html__( 'Order %s was completed some time ago and its files have been removed under our retention policy. Write to us quoting the order number if you need anything about it.', 'reklamo-core' ),
				esc_html( $order ? $order->get_order_number() : '' )
			);
			?>
		</p>

	<?php else : ?>
		<?php if ( $fresh ) : ?>
			<p class="notice ok"><strong><?php esc_html_e( 'Your request has been received. No payment is taken at this stage.', 'reklamo-core' ); ?></strong> <?php esc_html_e( 'We have emailed you a confirmation with a link to this page — keep it, it is how you follow the order.', 'reklamo-core' ); ?></p>
		<?php endif; ?>
		<?php if ( 'approved' === $flash ) : ?>
			<p class="notice ok"><?php esc_html_e( 'Thank you — approved. The other products are waiting for their mockups or your decision; we ask for the deposit once everything is approved.', 'reklamo-core' ); ?></p>
		<?php elseif ( 'changes' === $flash ) : ?>
			<p class="notice ok"><?php esc_html_e( 'Thank you — we received your comments. Our designer will prepare a revised mockup and you will get an email when it is ready.', 'reklamo-core' ); ?></p>
		<?php elseif ( 'message' === $flash ) : ?>
			<p class="notice err"><?php esc_html_e( 'Please describe the changes you would like.', 'reklamo-core' ); ?></p>
		<?php elseif ( 'none' === $flash ) : ?>
			<p class="notice err"><?php esc_html_e( 'This step is no longer open — the page below shows the current state.', 'reklamo-core' ); ?></p>
		<?php elseif ( 'nonce' === $flash ) : ?>
			<p class="notice err"><?php esc_html_e( 'The page had been open for too long. Please try the button again.', 'reklamo-core' ); ?></p>
		<?php endif; ?>

		<?php require REKLAMO_PATH . 'templates/customer-head.php'; ?>

		<div class="next">
		<?php
		$status = $v['status'];
		switch ( $status ) {
			case Reklamo_Statuses::RECEIVED:
				?>
				<h2><?php esc_html_e( 'Our designer is preparing your mockup', 'reklamo-core' ); ?></h2>
				<p><?php echo esc_html( sprintf( /* translators: %s: hours */ __( 'You will get an email with a link to review it, usually within %s hours on working days. Nothing to do until then.', 'reklamo-core' ), $v['deadline_h'] ) ); ?></p>
				<?php
				break;
			case Reklamo_Statuses::MOCKUP_SENT:
			case Reklamo_Statuses::CHANGES:
				if ( $counts['sent'] > 0 ) {
					$heading = $multi
						/* translators: %d: number of mockups */
						? sprintf( _n( '%d mockup is waiting for your decision', '%d mockups are waiting for your decision', $counts['sent'], 'reklamo-core' ), $counts['sent'] )
						/* translators: %d: mockup revision */
						: sprintf( __( 'Mockup #%d is waiting for your decision', 'reklamo-core' ), $v['products'][0]['revision'] );
				} elseif ( Reklamo_Statuses::CHANGES === $status ) {
					$heading = __( 'We received your comments — a revised mockup is on the way', 'reklamo-core' );
				} else {
					$heading = __( 'Our designer is preparing the remaining mockups', 'reklamo-core' );
				}
				?>
				<h2><?php echo esc_html( $heading ); ?></h2>
				<?php if ( $multi ) : ?>
					<p><?php echo esc_html( sprintf( /* translators: 1: approved products, 2: all products */ __( 'Approved: %1$d of %2$d products.', 'reklamo-core' ), $counts['approved'], $counts['total'] ) ); ?></p>
				<?php endif; ?>
				<?php if ( $counts['sent'] > 0 ) : ?>
					<p><?php echo esc_html( $multi ? __( 'Open each product below and approve its mockup or tell us what to change.', 'reklamo-core' ) : __( 'Approve the mockup below or tell us what to change.', 'reklamo-core' ) ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'You will get a new email when it is ready.', 'reklamo-core' ); ?></p>
				<?php endif; ?>
				<p class="muted"><?php echo esc_html( sprintf( /* translators: %s: deposit percentage */ __( 'No payment is taken at this stage. Once every mockup is approved we will send bank details for a %s%% deposit.', 'reklamo-core' ), Reklamo_Settings::get( 'deposit_pct', '100' ) ) ); ?></p>
				<?php
				break;
			case Reklamo_Statuses::APPROVED:
				?>
				<h2><?php esc_html_e( 'Approved — the deposit starts production', 'reklamo-core' ); ?></h2>
				<?php if ( $v['deposit'] ) : ?>
					<div class="amount"><?php echo esc_html( sprintf( /* translators: %s: deposit amount */ __( 'Deposit due: %s', 'reklamo-core' ), $v['deposit'] ) ); ?></div>
				<?php endif; ?>
				<?php echo wp_kses_post( $v['bank'] ); ?>
				<?php $reklamo_deadline = Reklamo_Payment_Deadline::notice( $order, 'request' ); ?>
				<?php if ( $reklamo_deadline ) : ?>
					<p class="deadline"><strong><?php echo esc_html( $reklamo_deadline ); ?></strong></p>
				<?php endif; ?>
				<?php if ( empty( $v['details']['submitted_at'] ) ) : ?>
					<p><strong><?php esc_html_e( 'We still need your invoice and delivery details.', 'reklamo-core' ); ?></strong></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Your invoice and delivery details are in. We confirm the deposit manually on working days and let you know by email.', 'reklamo-core' ); ?></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( home_url( '/' . Reklamo_Tracking::SLUG . '/' ) ); ?>">
					<input type="hidden" name="s" value="<?php echo esc_attr( $selector ); ?>"><input type="hidden" name="k" value="<?php echo esc_attr( $vars['secret'] ?? '' ); ?>">
					<?php wp_nonce_field( 'reklamo_track_' . $selector, '_reklamo_nonce' ); ?>
					<div class="actions">
						<button type="submit" name="act" value="details" class="<?php echo empty( $v['details']['submitted_at'] ) ? '' : 'secondary'; ?>"><?php echo empty( $v['details']['submitted_at'] ) ? esc_html__( 'Fill in invoice and delivery details', 'reklamo-core' ) : esc_html__( 'Update my details', 'reklamo-core' ); ?></button>
					</div>
				</form>
				<?php
				break;
			case Reklamo_Statuses::DEPOSIT_PAID:
				?>
				<h2><?php esc_html_e( 'Deposit received — thank you', 'reklamo-core' ); ?></h2>
				<p><?php esc_html_e( 'Production is being scheduled. You will get an email when it starts.', 'reklamo-core' ); ?></p>
				<?php
				break;
			case Reklamo_Statuses::PRODUCTION:
				?>
				<h2><?php esc_html_e( 'Your order is in production', 'reklamo-core' ); ?></h2>
				<p><?php esc_html_e( 'We will email you when it is ready, together with the request for the final payment.', 'reklamo-core' ); ?></p>
				<?php
				break;
			case Reklamo_Statuses::FINAL_DUE:
				?>
				<h2><?php esc_html_e( 'Ready — final payment before dispatch', 'reklamo-core' ); ?></h2>
				<?php if ( $v['balance'] ) : ?>
					<div class="amount"><?php echo esc_html( sprintf( /* translators: %s: balance amount */ __( 'Balance due: %s', 'reklamo-core' ), $v['balance'] ) ); ?></div>
				<?php endif; ?>
				<?php echo wp_kses_post( $v['bank'] ); ?>
				<p><?php esc_html_e( 'We dispatch as soon as the transfer arrives.', 'reklamo-core' ); ?></p>
				<?php
				break;
			case 'completed':
				?>
				<h2><?php echo esc_html( $v['completed_on'] ? sprintf( /* translators: %s: date */ __( 'Completed on %s — thank you!', 'reklamo-core' ), $v['completed_on'] ) : __( 'Completed — thank you!', 'reklamo-core' ) ); ?></h2>
				<p><?php esc_html_e( 'Your order is paid in full and on its way or delivered. This page stays available for a while as your record.', 'reklamo-core' ); ?></p>
				<?php $reklamo_ship = Reklamo_Dispatch::shipment( $order ); ?>
				<?php $reklamo_invoice = Reklamo_Dispatch::invoice( $order ); ?>
				<?php if ( $reklamo_ship || $reklamo_invoice ) : ?>
					<dl class="kv shipment">
						<?php if ( $reklamo_ship ) : ?>
							<dt><?php esc_html_e( 'Waybill', 'reklamo-core' ); ?></dt>
							<dd>
								<?php echo esc_html( trim( $reklamo_ship['courier_name'] . ' ' . $reklamo_ship['waybill'] ) ); ?>
								<?php if ( $reklamo_ship['url'] ) : ?>
									- <a href="<?php echo esc_url( $reklamo_ship['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Track the parcel', 'reklamo-core' ); ?></a>
								<?php endif; ?>
							</dd>
						<?php endif; ?>
						<?php if ( $reklamo_invoice ) : ?>
							<dt><?php esc_html_e( 'Invoice', 'reklamo-core' ); ?></dt>
							<dd><a href="<?php echo esc_url( $file_url( (int) $reklamo_invoice->id ) ); ?>"><?php echo esc_html( Reklamo_Dispatch::invoice_name( $order, $reklamo_invoice ) ); ?></a></dd>
						<?php endif; ?>
					</dl>
				<?php endif; ?>
				<?php
				break;
			default:
				if ( ! empty( $v['refunded'] ) ) {
					?>
					<h2><?php esc_html_e( 'This order was refunded', 'reklamo-core' ); ?></h2>
					<p><?php esc_html_e( 'The amounts you paid have been returned. Contact us if anything is unclear.', 'reklamo-core' ); ?></p>
					<?php
				} elseif ( $v['cancelled'] ) {
					?>
					<h2><?php esc_html_e( 'This order was cancelled', 'reklamo-core' ); ?></h2>
					<?php $reklamo_expired = Reklamo_Payment_Deadline::notice( $order, 'cancelled' ); ?>
					<?php if ( $reklamo_expired ) : ?>
						<p><?php echo esc_html( $reklamo_expired ); ?></p>
					<?php endif; ?>
					<p><?php esc_html_e( 'If that is a surprise, please contact us.', 'reklamo-core' ); ?></p>
					<?php
				} else {
					?>
					<h2><?php echo esc_html( $v['status_label'] ); ?></h2>
					<?php
				}
		}
		?>
		</div>

		<h3><?php echo esc_html( $multi ? __( 'Your products', 'reklamo-core' ) : __( 'Your product', 'reklamo-core' ) ); ?></h3>
		<?php foreach ( $v['products'] as $p ) : ?>
			<?php
			$badge = array(
				Reklamo_Items::WAITING  => array( '', __( 'Mockup in preparation', 'reklamo-core' ) ),
				Reklamo_Items::SENT     => array( 'now', __( 'Awaiting your decision', 'reklamo-core' ) ),
				Reklamo_Items::CHANGES  => array( '', __( 'Revision in progress', 'reklamo-core' ) ),
				Reklamo_Items::APPROVED => array( 'done', __( 'Approved', 'reklamo-core' ) ),
			)[ $p['state'] ];
			?>
			<section class="product" id="item-<?php echo esc_attr( (string) $p['id'] ); ?>" data-item="<?php echo esc_attr( (string) $p['id'] ); ?>" data-state="<?php echo esc_attr( $p['state'] ); ?>">
				<div class="product__head">
					<h4><?php echo esc_html( $p['name'] . ( $p['qty'] > 1 ? ' × ' . $p['qty'] : '' ) ); ?></h4>
					<span class="pill <?php echo esc_attr( $badge[0] ); ?>"><?php echo esc_html( $badge[1] ); ?></span>
				</div>
				<p class="muted">
					<?php if ( $p['logos'] ) : ?>
						<?php echo esc_html( count( $p['logos'] ) > 1 ? __( 'Logos', 'reklamo-core' ) : __( 'Logo', 'reklamo-core' ) ); ?>:
						<?php foreach ( $p['logos'] as $i => $logo ) : ?>
							<?php echo $i ? ', ' : ''; ?>
							<?php if ( '' !== (string) $logo->path ) : ?>
								<a href="<?php echo esc_url( $file_url( (int) $logo->id ) ); ?>"><?php echo esc_html( Reklamo_Storage::describe( $logo ) ); ?></a>
							<?php else : ?>
								<?php echo esc_html( Reklamo_Storage::describe( $logo ) ); ?>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php else : ?>
						<?php esc_html_e( 'No file on record.', 'reklamo-core' ); ?>
					<?php endif; ?>
					<?php if ( '' !== $p['note'] ) : ?>
						<br><?php esc_html_e( 'Note to designer', 'reklamo-core' ); ?>: „<?php echo esc_html( $p['note'] ); ?>“
					<?php endif; ?>
				</p>

				<?php if ( $p['pending'] ) : ?>
					<?php if ( ! $p['pending']['gone'] ) : ?>
						<div class="preview">
							<?php if ( $p['pending']['is_image'] ) : ?>
								<a href="<?php echo esc_url( $file_url( (int) $p['pending']['file']->id ) ); ?>" target="_blank" rel="noopener noreferrer"><img src="<?php echo esc_url( $file_url( (int) $p['pending']['file']->id ) ); ?>" alt="<?php echo esc_attr( sprintf( /* translators: %s: product name */ __( 'Mockup of %s', 'reklamo-core' ), $p['name'] ) ); ?>"></a>
							<?php else : ?>
								<a class="btn" href="<?php echo esc_url( $file_url( (int) $p['pending']['file']->id ) ); ?>"><?php esc_html_e( 'Download the mockup (PDF)', 'reklamo-core' ); ?></a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( home_url( '/' . Reklamo_Tracking::SLUG . '/' ) ); ?>">
						<input type="hidden" name="s" value="<?php echo esc_attr( $selector ); ?>"><input type="hidden" name="k" value="<?php echo esc_attr( $vars['secret'] ?? '' ); ?>">
						<input type="hidden" name="item" value="<?php echo esc_attr( (string) $p['id'] ); ?>"><input type="hidden" name="rev" value="<?php echo esc_attr( (string) $p['revision'] ); ?>">
						<?php wp_nonce_field( 'reklamo_track_' . $selector, '_reklamo_nonce' ); ?>
						<div class="actions">
							<button type="submit" name="act" value="approve"><?php esc_html_e( 'Approve the mockup', 'reklamo-core' ); ?></button>
						</div>
						<details <?php echo 'message' === $flash && $open === $p['id'] ? 'open' : ''; ?>>
							<summary><?php esc_html_e( 'I would like changes', 'reklamo-core' ); ?></summary>
							<p class="muted rk-corrections">
								<?php echo esc_html( Reklamo_Items::corrections_rule() ); ?>
								<?php
								/* translators: %d: corrections asked for so far */
								echo esc_html( sprintf( _n( 'So far: %d correction.', 'So far: %d corrections.', $p['corrections'], 'reklamo-core' ), $p['corrections'] ) );
								?>
							</p>
							<p><textarea name="message" placeholder="<?php esc_attr_e( 'Describe what should change…', 'reklamo-core' ); ?>"></textarea></p>
							<button type="submit" name="act" value="changes" class="secondary"><?php esc_html_e( 'Request changes', 'reklamo-core' ); ?></button>
						</details>
					</form>
				<?php elseif ( '' !== $p['comment'] ) : ?>
					<p class="muted"><?php esc_html_e( 'Your comments', 'reklamo-core' ); ?>: „<?php echo esc_html( $p['comment'] ); ?>“</p>
				<?php endif; ?>

				<?php foreach ( $p['mockups'] as $m ) : ?>
					<?php
					if ( $p['pending'] && $m['revision'] === $p['pending']['revision'] ) {
						continue;
					}
					?>
					<div class="rev">
						<?php if ( $m['gone'] ) : ?>
							<div class="thumb"><?php esc_html_e( 'removed', 'reklamo-core' ); ?></div>
						<?php elseif ( $m['is_image'] ) : ?>
							<a href="<?php echo esc_url( $file_url( (int) $m['file']->id ) ); ?>" target="_blank" rel="noopener noreferrer"><img src="<?php echo esc_url( $file_url( (int) $m['file']->id ) ); ?>" alt=""></a>
						<?php else : ?>
							<a class="thumb" href="<?php echo esc_url( $file_url( (int) $m['file']->id ) ); ?>"><?php echo esc_html( strtoupper( $m['file']->ext ) ); ?></a>
						<?php endif; ?>
						<div>
							<p><strong><?php echo esc_html( sprintf( /* translators: %d: mockup revision */ __( 'Mockup #%d', 'reklamo-core' ), $m['revision'] ) ); ?></strong> <span class="muted">· <?php echo esc_html( $m['date'] ); ?></span></p>
							<?php if ( 'approve' === $m['outcome'] ) : ?>
								<p class="ok"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Approved on %s', 'reklamo-core' ), $m['decided'] ) ); ?></p>
							<?php elseif ( 'changes' === $m['outcome'] ) : ?>
								<p><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Changes requested on %s', 'reklamo-core' ), $m['decided'] ) ); ?></p>
								<?php if ( $m['comment'] ) : ?>
									<p class="muted">„<?php echo esc_html( $m['comment'] ); ?>“</p>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ( ! $m['gone'] ) : ?>
								<p class="muted"><a href="<?php echo esc_url( $file_url( (int) $m['file']->id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open', 'reklamo-core' ); ?></a> · <?php echo esc_html( Reklamo_Storage::describe( $m['file'] ) ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</section>
		<?php endforeach; ?>

		<h3><?php esc_html_e( 'Payments', 'reklamo-core' ); ?></h3>
		<dl class="kv">
			<dt><?php esc_html_e( 'Total', 'reklamo-core' ); ?></dt><dd><?php echo esc_html( $v['total'] ); ?> <span class="muted"><?php esc_html_e( 'incl. VAT', 'reklamo-core' ); ?></span></dd>
			<?php if ( $v['deposit'] ) : ?>
				<dt><?php esc_html_e( 'Deposit', 'reklamo-core' ); ?></dt>
				<dd><?php echo esc_html( $v['deposit'] ); ?> - <?php echo $v['deposit_paid'] ? '<span class="ok">' . esc_html( sprintf( /* translators: %s: date */ __( 'received %s', 'reklamo-core' ), $v['deposit_paid'] ) ) . '</span>' : esc_html__( 'awaiting transfer', 'reklamo-core' ); ?></dd>
				<?php if ( $v['balance'] ) : ?>
					<dt><?php esc_html_e( 'Balance', 'reklamo-core' ); ?></dt>
					<dd><?php echo esc_html( $v['balance'] ); ?> - <?php echo $v['final_paid'] ? '<span class="ok">' . esc_html( sprintf( /* translators: %s: date */ __( 'received %s', 'reklamo-core' ), $v['final_paid'] ) ) . '</span>' : esc_html__( 'due before dispatch', 'reklamo-core' ); ?></dd>
				<?php endif; ?>
			<?php else : ?>
				<dt><?php esc_html_e( 'Deposit', 'reklamo-core' ); ?></dt><dd class="muted"><?php esc_html_e( 'set after you approve the mockup', 'reklamo-core' ); ?></dd>
			<?php endif; ?>
		</dl>

		<?php if ( ! empty( $v['details']['submitted_at'] ) ) : ?>
			<h3><?php esc_html_e( 'Invoice & delivery', 'reklamo-core' ); ?></h3>
			<dl class="kv">
				<?php if ( $v['details']['company'] ) : ?>
					<dt><?php esc_html_e( 'Company', 'reklamo-core' ); ?></dt><dd><?php echo esc_html( $v['details']['company'] ); ?><?php echo $v['details']['eik'] ? ', ' . esc_html__( 'Company ID (ЕИК)', 'reklamo-core' ) . ' ' . esc_html( $v['details']['eik'] ) : ''; ?></dd>
				<?php endif; ?>
				<dt><?php esc_html_e( 'Delivery', 'reklamo-core' ); ?></dt><dd><?php echo esc_html( Reklamo_Approval::delivery_summary( $v['details'] ) ); ?></dd>
				<dt><?php esc_html_e( 'Phone', 'reklamo-core' ); ?></dt><dd><?php echo esc_html( $v['details']['phone'] ); ?></dd>
			</dl>
		<?php endif; ?>

		<?php if ( $v['contact'] ) : ?>
			<p class="muted" style="margin-top:1.75rem">
				<?php esc_html_e( 'Questions about this order?', 'reklamo-core' ); ?>
				<?php if ( ! empty( $v['contact']['email'] ) ) : ?>
					<a href="mailto:<?php echo esc_attr( $v['contact']['email'] ); ?>"><?php echo esc_html( $v['contact']['email'] ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $v['contact']['phone'] ) ) : ?>
					· <?php echo esc_html( $v['contact']['phone'] ); ?>
				<?php endif; ?>
				· <?php echo esc_html( sprintf( /* translators: %s: order number */ __( 'quote order %s', 'reklamo-core' ), $order->get_order_number() ) ); ?>
			</p>
		<?php endif; ?>
	<?php endif; ?>
	</div>
</div>
</body>
</html>
