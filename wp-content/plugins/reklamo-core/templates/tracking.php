<?php
/**
 * The customer's order page. Self-contained: inline CSS only, no external requests.
 *
 * @var string        $view  not_found | rate_limited | expired | track
 * @var WC_Order|null $order
 * @var string        $selector
 * @var string        $secret
 * @var string        $url
 * @var string        $flash  ok | failed | wait | none | expired | ''
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
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<title><?php echo esc_html( get_bloginfo( 'name' ) . ' — ' . ( $order ? sprintf( /* translators: %s: order number */ __( 'Order %s', 'reklamo-core' ), $order->get_order_number() ) : __( 'Your order', 'reklamo-core' ) ) ); ?></title>
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
		<?php if ( 'ok' === $flash ) : ?>
			<p class="notice ok"><?php echo esc_html( sprintf( /* translators: %s: email address */ __( 'Sent. Check the inbox of %s (and the spam folder).', 'reklamo-core' ), $order->get_billing_email() ) ); ?></p>
		<?php elseif ( 'wait' === $flash ) : ?>
			<p class="notice err"><?php esc_html_e( 'The email was sent a few minutes ago. Please check your spam folder before asking again.', 'reklamo-core' ); ?></p>
		<?php elseif ( 'failed' === $flash ) : ?>
			<p class="notice err"><?php esc_html_e( 'The email could not be sent right now. Please contact us.', 'reklamo-core' ); ?></p>
		<?php elseif ( 'none' === $flash || 'expired' === $flash ) : ?>
			<p class="notice err"><?php esc_html_e( 'Nothing to send at the moment.', 'reklamo-core' ); ?></p>
		<?php endif; ?>

		<div class="head">
			<h1 data-order="<?php echo esc_attr( (string) $order->get_id() ); ?>"><?php echo esc_html( sprintf( /* translators: %s: order number */ __( 'Order %s', 'reklamo-core' ), $order->get_order_number() ) ); ?></h1>
			<span class="badge <?php echo $v['completed'] ? 'done' : ( $v['cancelled'] ? 'off' : '' ); ?>"><?php echo esc_html( $v['status_label'] ); ?></span>
		</div>
		<p class="meta">
			<?php echo esc_html( implode( ', ', $v['items'] ) ); ?>
			<?php if ( $v['created'] ) : ?>
				· <?php echo esc_html( sprintf( /* translators: %s: date */ __( 'requested on %s', 'reklamo-core' ), $v['created'] ) ); ?>
			<?php endif; ?>
			· <?php echo esc_html( sprintf( /* translators: %s: amount */ __( 'total %s incl. VAT', 'reklamo-core' ), $v['total'] ) ); ?>
		</p>

		<?php if ( ! $v['cancelled'] ) : ?>
			<ol class="steps" aria-label="<?php esc_attr_e( 'Progress', 'reklamo-core' ); ?>">
				<?php foreach ( $v['step_labels'] as $i => $label ) : ?>
					<?php
					$n     = $i + 1;
					$class = Reklamo_Progress::is_step_done( $n, $v['step'], $v['completed'] ) ? 'done' : ( $n === $v['step'] ? 'now' : '' );
					?>
					<li class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>

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
				?>
				<h2><?php echo esc_html( sprintf( /* translators: %d: mockup revision */ __( 'Mockup #%d is waiting for your decision', 'reklamo-core' ), $v['pending_rev'] ) ); ?></h2>
				<p><?php esc_html_e( 'Open the link in the email "Your mockup is ready" to approve it or ask for changes. That link is personal and works once.', 'reklamo-core' ); ?></p>
				<div><?php esc_html_e( 'Cannot find the email?', 'reklamo-core' ); ?>
					<form class="inline-form" method="post" action="<?php echo esc_url( home_url( '/' . Reklamo_Tracking::SLUG . '/' ) ); ?>">
						<input type="hidden" name="s" value="<?php echo esc_attr( $selector ); ?>"><input type="hidden" name="k" value="<?php echo esc_attr( $vars['secret'] ?? '' ); ?>">
						<?php wp_nonce_field( 'reklamo_track_' . $selector, '_reklamo_nonce' ); ?>
						<button type="submit" class="secondary" style="padding:.5rem .9rem"><?php esc_html_e( 'Send it again', 'reklamo-core' ); ?></button>
					</form>
				</div>
				<?php
				break;
			case Reklamo_Statuses::CHANGES:
				?>
				<h2><?php esc_html_e( 'We received your comments — a revised mockup is on the way', 'reklamo-core' ); ?></h2>
				<?php if ( $v['last_comment'] ) : ?>
					<p class="muted">„<?php echo esc_html( $v['last_comment'] ); ?>“</p>
				<?php endif; ?>
				<p><?php esc_html_e( 'You will get a new email when it is ready.', 'reklamo-core' ); ?></p>
				<?php
				break;
			case Reklamo_Statuses::APPROVED:
				?>
				<h2><?php esc_html_e( 'Approved — the deposit starts production', 'reklamo-core' ); ?></h2>
				<?php if ( $v['deposit'] ) : ?>
					<div class="amount"><?php echo esc_html( sprintf( /* translators: %s: deposit amount */ __( 'Deposit due: %s', 'reklamo-core' ), $v['deposit'] ) ); ?></div>
				<?php endif; ?>
				<?php echo wp_kses_post( $v['bank'] ); ?>
				<?php if ( empty( $v['details']['submitted_at'] ) ) : ?>
					<p><strong><?php esc_html_e( 'We still need your invoice and delivery details.', 'reklamo-core' ); ?></strong> <?php esc_html_e( 'Use the link in the email "Approved — deposit and details".', 'reklamo-core' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Your invoice and delivery details are in. We confirm the deposit manually on working days and let you know by email.', 'reklamo-core' ); ?></p>
				<?php endif; ?>
				<div><?php esc_html_e( 'Cannot find the email?', 'reklamo-core' ); ?>
					<form class="inline-form" method="post" action="<?php echo esc_url( home_url( '/' . Reklamo_Tracking::SLUG . '/' ) ); ?>">
						<input type="hidden" name="s" value="<?php echo esc_attr( $selector ); ?>"><input type="hidden" name="k" value="<?php echo esc_attr( $vars['secret'] ?? '' ); ?>">
						<?php wp_nonce_field( 'reklamo_track_' . $selector, '_reklamo_nonce' ); ?>
						<button type="submit" class="secondary" style="padding:.5rem .9rem"><?php esc_html_e( 'Send it again', 'reklamo-core' ); ?></button>
					</form>
				</div>
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
				<?php
				break;
			default:
				if ( $v['cancelled'] ) {
					?>
					<h2><?php esc_html_e( 'This order was cancelled', 'reklamo-core' ); ?></h2>
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

		<?php if ( $v['mockups'] ) : ?>
			<h3><?php esc_html_e( 'Mockups', 'reklamo-core' ); ?></h3>
			<?php foreach ( $v['mockups'] as $m ) : ?>
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
						<?php elseif ( $m['revision'] === $v['pending_rev'] && Reklamo_Statuses::MOCKUP_SENT === $status ) : ?>
							<p><?php esc_html_e( 'Awaiting your decision', 'reklamo-core' ); ?></p>
						<?php endif; ?>
						<?php if ( ! $m['gone'] ) : ?>
							<p class="muted"><a href="<?php echo esc_url( $file_url( (int) $m['file']->id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open', 'reklamo-core' ); ?></a> · <?php echo esc_html( Reklamo_Storage::describe( $m['file'] ) ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Payments', 'reklamo-core' ); ?></h3>
		<dl class="kv">
			<dt><?php esc_html_e( 'Total', 'reklamo-core' ); ?></dt><dd><?php echo esc_html( $v['total'] ); ?> <span class="muted"><?php esc_html_e( 'incl. VAT', 'reklamo-core' ); ?></span></dd>
			<?php if ( $v['deposit'] ) : ?>
				<dt><?php esc_html_e( 'Deposit', 'reklamo-core' ); ?></dt>
				<dd><?php echo esc_html( $v['deposit'] ); ?> — <?php echo $v['deposit_paid'] ? '<span class="ok">' . esc_html( sprintf( /* translators: %s: date */ __( 'received %s', 'reklamo-core' ), $v['deposit_paid'] ) ) . '</span>' : esc_html__( 'awaiting transfer', 'reklamo-core' ); ?></dd>
				<dt><?php esc_html_e( 'Balance', 'reklamo-core' ); ?></dt>
				<dd><?php echo esc_html( $v['balance'] ); ?> — <?php echo $v['final_paid'] ? '<span class="ok">' . esc_html( sprintf( /* translators: %s: date */ __( 'received %s', 'reklamo-core' ), $v['final_paid'] ) ) . '</span>' : esc_html__( 'due before dispatch', 'reklamo-core' ); ?></dd>
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
				<dt><?php esc_html_e( 'Delivery address', 'reklamo-core' ); ?></dt><dd><?php echo esc_html( trim( $v['details']['address_1'] . ', ' . $v['details']['postcode'] . ' ' . $v['details']['city'], ', ' ) ); ?></dd>
				<dt><?php esc_html_e( 'Phone', 'reklamo-core' ); ?></dt><dd><?php echo esc_html( $v['details']['phone'] ); ?></dd>
			</dl>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Your logo', 'reklamo-core' ); ?></h3>
		<?php if ( $v['logo'] && '' !== (string) $v['logo']->path ) : ?>
			<p><a href="<?php echo esc_url( $file_url( (int) $v['logo']->id ) ); ?>"><?php echo esc_html( Reklamo_Storage::describe( $v['logo'] ) ); ?></a></p>
		<?php elseif ( $v['logo'] ) : ?>
			<p class="muted"><?php echo esc_html( Reklamo_Storage::describe( $v['logo'] ) ); ?></p>
		<?php else : ?>
			<p class="muted"><?php esc_html_e( 'No file on record.', 'reklamo-core' ); ?></p>
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
