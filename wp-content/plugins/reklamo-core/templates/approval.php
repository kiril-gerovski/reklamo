<?php
/**
 * Mockup approval page. Self-contained: inline CSS only, no external requests.
 *
 * @var string        $view  not_found | rate_limited | expired | used | review | approved | changes
 * @var WC_Order|null $order
 * @var object|null   $file
 * @var object|null   $token
 * @var string        $selector
 * @var string        $secret
 * @var string        $error
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
<title><?php echo esc_html( get_bloginfo( 'name' ) . ' — ' . __( 'Mockup approval', 'reklamo-core' ) ); ?></title>
<?php require REKLAMO_PATH . 'templates/customer-style.php'; ?>
</head>
<body>
<div class="wrap">
	<div class="brand"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
	<div class="card">
	<?php if ( 'not_found' === $view ) : ?>
		<h1><?php esc_html_e( 'This link is not valid', 'reklamo-core' ); ?></h1>
		<p><?php esc_html_e( 'Please open the link exactly as it appears in the email, or contact us.', 'reklamo-core' ); ?></p>

	<?php elseif ( 'rate_limited' === $view ) : ?>
		<h1><?php esc_html_e( 'Too many attempts', 'reklamo-core' ); ?></h1>
		<p><?php esc_html_e( 'Please try again in an hour.', 'reklamo-core' ); ?></p>

	<?php elseif ( 'expired' === $view ) : ?>
		<h1><?php esc_html_e( 'This link has expired', 'reklamo-core' ); ?></h1>
		<p><?php esc_html_e( 'Approval links are valid for 14 days. Reply to our email and we will send you a new one.', 'reklamo-core' ); ?></p>

	<?php elseif ( 'used' === $view ) : ?>
		<h1><?php esc_html_e( 'This mockup has already been processed', 'reklamo-core' ); ?></h1>
		<p>
			<?php
			if ( 'approve' === ( $token->used_action ?? '' ) ) {
				esc_html_e( 'You approved it — thank you. We will be in touch about the next step.', 'reklamo-core' );
			} else {
				esc_html_e( 'You requested changes. Our designer is working on a new version.', 'reklamo-core' );
			}
			?>
		</p>

	<?php elseif ( 'approved' === $view ) : ?>
		<h1 class="ok"><?php esc_html_e( 'Approved — thank you!', 'reklamo-core' ); ?></h1>
		<p><?php esc_html_e( 'We will now send you our bank details and a request for the 50% deposit. Production starts once it arrives.', 'reklamo-core' ); ?></p>

	<?php elseif ( 'changes' === $view ) : ?>
		<h1><?php esc_html_e( 'Thank you — we received your comments', 'reklamo-core' ); ?></h1>
		<p><?php esc_html_e( 'Our designer will prepare a revised mockup and you will get a new email to review it.', 'reklamo-core' ); ?></p>

	<?php elseif ( 'details' === $view ) : ?>
		<h1><?php echo $saved ? esc_html__( 'Thank you — details saved', 'reklamo-core' ) : esc_html__( 'Approved — one more step', 'reklamo-core' ); ?></h1>
		<p class="meta">
			<?php
			printf(
				/* translators: %s: order number */
				esc_html__( 'Order %s · mockup approved. Fill in your invoice and delivery details and transfer the deposit; production starts when it arrives.', 'reklamo-core' ),
				esc_html( $order->get_order_number() )
			);
			?>
		</p>

		<?php if ( $deposit > 0 ) : ?>
			<div class="amount"><?php echo esc_html( sprintf( /* translators: %s: deposit amount */ __( 'Deposit due: %s', 'reklamo-core' ), wp_strip_all_tags( wc_price( $deposit, array( 'currency' => $order->get_currency() ) ) ) ) ); ?></div>
		<?php endif; ?>
		<?php echo wp_kses_post( $bank ); ?>

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
		<?php if ( $locked ) : ?>
			<p class="notice ok"><?php esc_html_e( 'The deposit is confirmed and your details are locked. Contact us if something needs to change.', 'reklamo-core' ); ?></p>
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
				<div class="full"><label class="f"><?php esc_html_e( 'Company name', 'reklamo-core' ); ?></label><input type="text" name="d_company" value="<?php echo esc_attr( $details['company'] ?? '' ); ?>"></div>
				<div><label class="f"><?php esc_html_e( 'Company ID (ЕИК)', 'reklamo-core' ); ?></label><input type="text" name="d_eik" inputmode="numeric" value="<?php echo esc_attr( $details['eik'] ?? '' ); ?>"></div>
				<div><label class="f"><?php esc_html_e( 'VAT no. (optional)', 'reklamo-core' ); ?></label><input type="text" name="d_vat" placeholder="BG123456789" value="<?php echo esc_attr( $details['vat'] ?? '' ); ?>"></div>
				<div class="full"><label class="f"><?php esc_html_e( 'Responsible person (МОЛ)', 'reklamo-core' ); ?></label><input type="text" name="d_mol" value="<?php echo esc_attr( $details['mol'] ?? '' ); ?>"></div>
				<div class="full"><label class="f"><?php esc_html_e( 'Phone', 'reklamo-core' ); ?></label><input type="tel" name="d_phone" value="<?php echo esc_attr( $details['phone'] ?? '' ); ?>" required></div>
				<div class="full"><label class="f"><?php esc_html_e( 'Delivery address', 'reklamo-core' ); ?></label><input type="text" name="d_address_1" value="<?php echo esc_attr( $details['address_1'] ?? '' ); ?>" required></div>
				<div><label class="f"><?php esc_html_e( 'City', 'reklamo-core' ); ?></label><input type="text" name="d_city" value="<?php echo esc_attr( $details['city'] ?? '' ); ?>" required></div>
				<div><label class="f"><?php esc_html_e( 'Postcode', 'reklamo-core' ); ?></label><input type="text" name="d_postcode" inputmode="numeric" value="<?php echo esc_attr( $details['postcode'] ?? '' ); ?>" required></div>
				<div class="full"><label class="f"><?php esc_html_e( 'Delivery note (optional)', 'reklamo-core' ); ?></label><input type="text" name="d_note" value="<?php echo esc_attr( $details['note'] ?? '' ); ?>"></div>
			</div>
			<div class="actions"><button type="submit"><?php echo $saved ? esc_html__( 'Update details', 'reklamo-core' ) : esc_html__( 'Save details', 'reklamo-core' ); ?></button></div>
		</form>
		<p class="muted"><?php esc_html_e( 'Prices include VAT. The invoice is issued from these details; the order number is your payment reference.', 'reklamo-core' ); ?></p>

	<?php else : /* review */ ?>
		<h1><?php esc_html_e( 'Your mockup is ready', 'reklamo-core' ); ?></h1>
		<div class="meta">
			<?php
			printf(
				/* translators: 1: order number, 2: mockup revision */
				esc_html__( 'Order %1$s · mockup #%2$d', 'reklamo-core' ),
				esc_html( $order->get_order_number() ),
				(int) $token->revision
			);
			?>
			<br>
			<?php
			foreach ( $order->get_items() as $item ) {
				echo esc_html( $item->get_name() ) . ' × ' . (int) $item->get_quantity() . '<br>';
			}
			?>
		</div>

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

			<details>
				<summary><?php esc_html_e( 'I would like changes', 'reklamo-core' ); ?></summary>
				<p><textarea name="message" placeholder="<?php esc_attr_e( 'Describe what should change…', 'reklamo-core' ); ?>"></textarea></p>
				<button type="submit" name="decision" value="changes" class="secondary"><?php esc_html_e( 'Request changes', 'reklamo-core' ); ?></button>
			</details>
		</form>
		<p class="muted"><?php esc_html_e( 'No payment is taken at this stage. After approval we will send bank details for a 50% deposit.', 'reklamo-core' ); ?></p>
	<?php endif; ?>
	</div>
</div>
</body>
</html>
