<?php
/**
 * Mockup approval and deposit/details pages (emailed one-time links). Self-contained:
 * inline CSS only, no external requests. Whenever the order is known, the page opens with
 * the same order header and progress line as the order page, so the customer always sees
 * where the order stands; the step itself sits in the highlighted box below it.
 *
 * @var string        $view  not_found | rate_limited | expired | closed | used | review | approved | changes | details
 * @var WC_Order|null $order
 * @var object|null   $file
 * @var object|null   $token
 * @var WC_Order_Item_Product|null $item  the product this mockup belongs to
 * @var array|null    $counts    Reklamo_Items::counts() after a decision
 * @var string        $selector
 * @var string        $secret
 * @var string        $error
 * @var array|null    $progress  Reklamo_Tracking::view_data() when $order is set
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

// Included inside a closure by Reklamo_Approval::render(): these are locals, not globals.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$view     = $vars['view'] ?? 'not_found';
$order    = $vars['order'] ?? null;
$file     = $vars['file'] ?? null;
$token    = $vars['token'] ?? null;
$selector = $vars['selector'] ?? '';
$secret   = $vars['secret'] ?? '';
$error    = $vars['error'] ?? '';
$details  = $vars['details'] ?? array();
$errors   = $vars['errors'] ?? array();
$deposit  = $vars['deposit'] ?? 0.0;
$bank     = $vars['bank'] ?? '';
$locked   = ! empty( $vars['locked'] );
$saved    = ! empty( $vars['saved'] );
$track    = $vars['track_url'] ?? '';
$item     = $vars['item'] ?? null;
$counts   = $vars['counts'] ?? array();
$v        = $vars['progress'] ?? null;
$is_image = $file && in_array( $file->ext, array( 'png', 'jpg', 'jpeg' ), true );
$dtype    = ( $details['customer_type'] ?? '' ) ? $details['customer_type'] : 'company';
$self_url = home_url( '/' . Reklamo_Approval::SLUG . '/' );
$view_url = add_query_arg(
	array(
		's'    => $selector,
		'k'    => $secret,
		'view' => 'mockup',
	),
	$self_url
);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<title><?php echo esc_html( get_bloginfo( 'name' ) . ' — ' . ( $order ? sprintf( /* translators: %s: order number */ __( 'Order %s', 'reklamo-core' ), $order->get_order_number() ) : __( 'Mockup approval', 'reklamo-core' ) ) ); ?></title>
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

	<?php else : ?>
		<?php
		if ( $order && $v ) {
			require REKLAMO_PATH . 'templates/customer-head.php';
		}
		?>

		<div class="next">
		<?php if ( 'expired' === $view ) : ?>
			<h2><?php esc_html_e( 'This link has expired', 'reklamo-core' ); ?></h2>
			<p>
				<?php
				if ( 'details' === ( $token->purpose ?? '' ) ) {
					echo esc_html( sprintf( /* translators: %d: days */ __( 'The link to your invoice and delivery details is valid for %d days. Open your order page below to get a new one.', 'reklamo-core' ), Reklamo_Approval::DETAILS_TTL ) );
				} else {
					echo esc_html( sprintf( /* translators: %d: days */ __( 'Approval links are valid for %d days. You can still decide on the mockup from your order page below.', 'reklamo-core' ), Reklamo_Approval::TTL_DAYS ) );
				}
				?>
			</p>

		<?php elseif ( 'closed' === $view ) : ?>
			<h2><?php esc_html_e( 'This order is no longer waiting for this step', 'reklamo-core' ); ?></h2>
			<p><?php esc_html_e( 'The order has moved on or was cancelled since this email was sent, so this link cannot act any more. Your order page below shows the current state.', 'reklamo-core' ); ?></p>

		<?php elseif ( 'used' === $view ) : ?>
			<h2><?php esc_html_e( 'This mockup has already been processed', 'reklamo-core' ); ?></h2>
			<p>
				<?php
				if ( 'approve' === ( $token->used_action ?? '' ) ) {
					esc_html_e( 'You approved it — thank you. Your order page below shows the next step.', 'reklamo-core' );
				} else {
					esc_html_e( 'You requested changes. Our designer is working on a new version.', 'reklamo-core' );
				}
				?>
			</p>

		<?php elseif ( 'approved' === $view ) : ?>
			<h2 class="ok"><?php esc_html_e( 'Approved — thank you!', 'reklamo-core' ); ?></h2>
			<?php if ( $counts && $counts['approved'] < $counts['total'] ) : ?>
				<p><?php echo esc_html( sprintf( /* translators: 1: approved products, 2: all products */ __( 'Approved: %1$d of %2$d products. We ask for the deposit once every mockup is approved — your order page below shows the others.', 'reklamo-core' ), $counts['approved'], $counts['total'] ) ); ?></p>
			<?php else : ?>
				<p><?php echo esc_html( sprintf( /* translators: %s: deposit percentage */ __( 'We will now send you our bank details and a request for the %s%% deposit. Production starts once it arrives.', 'reklamo-core' ), Reklamo_Settings::get( 'deposit_pct', '100' ) ) ); ?></p>
			<?php endif; ?>

		<?php elseif ( 'changes' === $view ) : ?>
			<h2><?php esc_html_e( 'Thank you — we received your comments', 'reklamo-core' ); ?></h2>
			<p><?php esc_html_e( 'Our designer will prepare a revised mockup and you will get a new email to review it.', 'reklamo-core' ); ?></p>

		<?php elseif ( 'details' === $view ) : ?>
			<h2><?php esc_html_e( 'Deposit', 'reklamo-core' ); ?></h2>
			<?php if ( $locked ) : ?>
				<p><?php esc_html_e( 'The deposit is confirmed and your details are locked. Contact us if something needs to change.', 'reklamo-core' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Everything is approved. Transfer the deposit and fill in your invoice and delivery details below; production starts when the deposit arrives.', 'reklamo-core' ); ?></p>
				<?php if ( $deposit > 0 ) : ?>
					<div class="amount"><?php echo esc_html( sprintf( /* translators: %s: deposit amount */ __( 'Deposit due: %s', 'reklamo-core' ), wp_strip_all_tags( wc_price( $deposit, array( 'currency' => $order->get_currency() ) ) ) ) ); ?></div>
				<?php endif; ?>
				<?php echo wp_kses_post( $bank ); ?>
				<p class="muted"><?php esc_html_e( 'The order number is your payment reference. We confirm the deposit manually on working days and let you know by email.', 'reklamo-core' ); ?></p>
			<?php endif; ?>

		<?php else : /* review */ ?>
			<h2><?php echo esc_html( sprintf( /* translators: %d: mockup revision */ __( 'Mockup #%d is waiting for your decision', 'reklamo-core' ), (int) $token->revision ) ); ?></h2>
			<?php if ( $item ) : ?>
				<p class="product-name"><strong><?php echo esc_html( $item->get_name() ); ?></strong></p>
			<?php endif; ?>
			<div class="preview">
				<?php if ( $is_image ) : ?>
					<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener noreferrer"><img src="<?php echo esc_url( $view_url ); ?>" alt="<?php esc_attr_e( 'Mockup', 'reklamo-core' ); ?>"></a>
				<?php else : ?>
					<a class="btn" href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'Download the mockup (PDF)', 'reklamo-core' ); ?></a>
				<?php endif; ?>
			</div>

			<?php if ( $error ) : ?>
				<p class="error"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( $self_url ); ?>">
				<input type="hidden" name="s" value="<?php echo esc_attr( $selector ); ?>">
				<input type="hidden" name="k" value="<?php echo esc_attr( $secret ); ?>">
				<?php wp_nonce_field( 'reklamo_decide_' . $selector, '_reklamo_nonce' ); ?>

				<div class="actions">
					<button type="submit" name="decision" value="approve"><?php esc_html_e( 'Approve the mockup', 'reklamo-core' ); ?></button>
				</div>

				<details <?php echo $error ? 'open' : ''; ?>>
					<summary><?php esc_html_e( 'I would like changes', 'reklamo-core' ); ?></summary>
					<p class="muted rk-corrections">
						<?php echo esc_html( Reklamo_Items::corrections_rule() ); ?>
						<?php
						if ( $item ) {
							/* translators: %d: corrections asked for so far */
							echo esc_html( sprintf( _n( 'So far: %d correction.', 'So far: %d corrections.', Reklamo_Items::corrections( $item ), 'reklamo-core' ), Reklamo_Items::corrections( $item ) ) );
						}
						?>
					</p>
					<p><textarea name="message" placeholder="<?php esc_attr_e( 'Describe what should change…', 'reklamo-core' ); ?>"></textarea></p>
					<button type="submit" name="decision" value="changes" class="secondary"><?php esc_html_e( 'Request changes', 'reklamo-core' ); ?></button>
				</details>
			</form>
			<p class="muted"><?php echo esc_html( sprintf( /* translators: %s: deposit percentage */ __( 'No payment is taken at this stage. Once every mockup is approved we will send bank details for a %s%% deposit.', 'reklamo-core' ), Reklamo_Settings::get( 'deposit_pct', '100' ) ) ); ?></p>
		<?php endif; ?>
		</div>

		<?php if ( 'details' === $view ) : ?>
			<h3><?php esc_html_e( 'Invoice & delivery', 'reklamo-core' ); ?></h3>
			<?php if ( $saved ) : ?>
				<p class="notice ok"><?php esc_html_e( 'We received your details. You can still correct them from this link until the deposit is confirmed.', 'reklamo-core' ); ?></p>
			<?php endif; ?>
			<?php if ( $errors ) : ?>
				<div class="notice err"><ul style="margin:0;padding-left:1.1rem">
				<?php
				foreach ( $errors as $e ) :
					?>
					<li><?php echo esc_html( $e ); ?></li><?php endforeach; ?></ul></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( $self_url ); ?>" <?php echo $locked ? 'style="opacity:.6;pointer-events:none"' : ''; ?>>
				<input type="hidden" name="s" value="<?php echo esc_attr( $selector ); ?>">
				<input type="hidden" name="k" value="<?php echo esc_attr( $secret ); ?>">
				<?php wp_nonce_field( 'reklamo_details_' . $selector, '_reklamo_nonce' ); ?>

				<div class="seg">
					<label><input type="radio" name="d_customer_type" value="company" <?php checked( 'company', $dtype ); ?>><span><?php esc_html_e( 'Company (invoice)', 'reklamo-core' ); ?></span></label>
					<label><input type="radio" name="d_customer_type" value="person" <?php checked( 'person', $dtype ); ?>><span><?php esc_html_e( 'Private person', 'reklamo-core' ); ?></span></label>
				</div>

				<div class="grid">
					<div class="full only-person"><label class="f"><?php esc_html_e( 'Full name', 'reklamo-core' ); ?></label><input type="text" name="d_name" autocomplete="name" value="<?php echo esc_attr( $details['name'] ?? '' ); ?>"></div>
					<div class="full only-company"><label class="f"><?php esc_html_e( 'Company name', 'reklamo-core' ); ?></label><input type="text" name="d_company" autocomplete="organization" value="<?php echo esc_attr( $details['company'] ?? '' ); ?>"></div>
					<div class="only-company"><label class="f"><?php esc_html_e( 'Company ID (ЕИК)', 'reklamo-core' ); ?></label><input type="text" name="d_eik" inputmode="numeric" value="<?php echo esc_attr( $details['eik'] ?? '' ); ?>"></div>
					<div class="only-company"><label class="f"><?php esc_html_e( 'VAT no. (optional)', 'reklamo-core' ); ?></label><input type="text" name="d_vat" placeholder="BG123456789" value="<?php echo esc_attr( $details['vat'] ?? '' ); ?>"></div>
					<div class="full only-company"><label class="f"><?php esc_html_e( 'Responsible person (МОЛ)', 'reklamo-core' ); ?></label><input type="text" name="d_mol" value="<?php echo esc_attr( $details['mol'] ?? '' ); ?>"></div>
					<div class="full"><label class="f"><?php esc_html_e( 'Phone', 'reklamo-core' ); ?></label><input type="tel" name="d_phone" autocomplete="tel" value="<?php echo esc_attr( $details['phone'] ?? '' ); ?>" required></div>
					<div class="full"><span class="f"><?php esc_html_e( 'Delivery', 'reklamo-core' ); ?></span>
						<div class="seg seg--delivery">
							<?php foreach ( Reklamo_Approval::DELIVERY_TYPES as $reklamo_dt ) : ?>
								<label><input type="radio" name="d_delivery_type" value="<?php echo esc_attr( $reklamo_dt ); ?>" <?php checked( $reklamo_dt, $details['delivery_type'] ?? 'address' ); ?>><span><?php echo esc_html( Reklamo_Approval::delivery_type_label( $reklamo_dt ) ); ?></span></label>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="full"><label class="f"><?php esc_html_e( 'Courier', 'reklamo-core' ); ?></label>
						<select name="d_courier">
							<option value="" data-any><?php esc_html_e( 'No preference', 'reklamo-core' ); ?></option>
							<?php foreach ( Reklamo_Approval::COURIERS as $reklamo_ck => $reklamo_cn ) : ?>
								<option value="<?php echo esc_attr( $reklamo_ck ); ?>" <?php selected( $reklamo_ck, $details['courier'] ?? '' ); ?><?php echo 'boxnow' === $reklamo_ck ? ' data-locker-only' : ''; ?>><?php echo esc_html( $reklamo_cn ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="full"><label class="f" data-label-address="<?php esc_attr_e( 'Delivery address', 'reklamo-core' ); ?>" data-label-office="<?php esc_attr_e( 'Courier office (name or address)', 'reklamo-core' ); ?>" data-label-locker="<?php esc_attr_e( 'Parcel locker (name or address)', 'reklamo-core' ); ?>"><?php esc_html_e( 'Delivery address', 'reklamo-core' ); ?></label><input type="text" name="d_address_1" autocomplete="street-address" value="<?php echo esc_attr( $details['address_1'] ?? '' ); ?>" required></div>
					<div><label class="f"><?php esc_html_e( 'City', 'reklamo-core' ); ?></label><input type="text" name="d_city" autocomplete="address-level2" value="<?php echo esc_attr( $details['city'] ?? '' ); ?>" required></div>
					<div class="only-address"><label class="f"><?php esc_html_e( 'Postcode', 'reklamo-core' ); ?></label><input type="text" name="d_postcode" inputmode="numeric" autocomplete="postal-code" value="<?php echo esc_attr( $details['postcode'] ?? '' ); ?>" required></div>
					<div class="full only-company only-elsewhere"><label class="f"><?php esc_html_e( 'Company address (for the invoice)', 'reklamo-core' ); ?></label><input type="text" name="d_billing_address" value="<?php echo esc_attr( $details['billing_address'] ?? '' ); ?>"></div>
					<div class="full"><label class="f"><?php esc_html_e( 'Delivery note (optional)', 'reklamo-core' ); ?></label><input type="text" name="d_note" value="<?php echo esc_attr( $details['note'] ?? '' ); ?>"></div>
				</div>
				<div class="actions"><button type="submit"><?php echo $saved ? esc_html__( 'Update details', 'reklamo-core' ) : esc_html__( 'Save details', 'reklamo-core' ); ?></button></div>
			</form>
			<p class="muted"><?php esc_html_e( 'Prices include VAT. The invoice is issued from these details; the order number is your payment reference.', 'reklamo-core' ); ?></p>
			<script>
			// Company vs private person: show only the fields that apply. Inline — this page loads no external assets.
			( function () {
				var form = document.querySelector( 'form input[name="d_customer_type"]' );
				if ( ! form ) { return; }
				form = form.form;
				function sync() {
					var company = form.querySelector( 'input[name="d_customer_type"][value="company"]' ).checked;
					var picked = form.querySelector( 'input[name="d_delivery_type"]:checked' );
					var where = picked ? picked.value : 'address';
					form.querySelectorAll( '.only-company' ).forEach( function ( el ) { el.hidden = ! company || ( el.classList.contains( 'only-elsewhere' ) && 'address' === where ); } );
					form.querySelectorAll( '.only-person' ).forEach( function ( el ) { el.hidden = company; } );
					// Delivery: the address field names what goes in it; the postcode only for an address.
					var label = form.querySelector( '[data-label-address]' );
					if ( label ) { label.textContent = label.getAttribute( 'data-label-' + where ); }
					form.querySelectorAll( '.only-address' ).forEach( function ( el ) { el.hidden = 'address' !== where; el.querySelectorAll( 'input' ).forEach( function ( i ) { i.required = 'address' === where; } ); } );
					var billing = form.querySelector( 'input[name="d_billing_address"]' );
					if ( billing ) { billing.required = company && 'address' !== where; }
					// An office or locker needs a courier; BoxNow has lockers only.
					var courier = form.querySelector( 'select[name="d_courier"]' );
					if ( courier ) {
						courier.required = 'address' !== where;
						courier.querySelector( '[data-any]' ).hidden = 'address' !== where;
						var boxnow = courier.querySelector( '[data-locker-only]' );
						boxnow.disabled = 'locker' !== where;
						if ( boxnow.disabled && boxnow.selected ) { courier.value = ''; }
						if ( 'address' !== where && '' === courier.value ) { courier.selectedIndex = -1; }
					}
				}
				form.addEventListener( 'change', function ( e ) { if ( 'd_customer_type' === e.target.name || 'd_delivery_type' === e.target.name ) { sync(); } } );
				sync();
			} )();
			</script>
		<?php endif; ?>

		<?php if ( $track ) : ?>
			<p class="track"><a href="<?php echo esc_url( $track ); ?>">← <?php esc_html_e( 'Back to your order page', 'reklamo-core' ); ?></a> <span class="muted">· <?php esc_html_e( 'progress, mockups and payments', 'reklamo-core' ); ?></span></p>
		<?php endif; ?>
	<?php endif; ?>
	</div>
</div>
</body>
</html>
