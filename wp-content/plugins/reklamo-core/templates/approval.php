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
$is_image = $file && in_array( $file->ext, array( 'png', 'jpg', 'jpeg' ), true );
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
<style>
	body { margin: 0; font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1a1a1a; background: #faf8f4; }
	.wrap { max-width: 760px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
	.brand { font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #9a7020; margin-bottom: 1.5rem; }
	.card { background: #fff; border: 1px solid #e8e2d6; border-radius: 8px; padding: 1.5rem; }
	h1 { font: 400 1.75rem/1.2 Georgia, "Times New Roman", serif; margin: 0 0 .75rem; }
	.meta { color: #555; font-size: .9375rem; margin-bottom: 1rem; }
	.preview { margin: 1.25rem 0; text-align: center; }
	.preview img { max-width: 100%; height: auto; border: 1px solid #e8e2d6; border-radius: 6px; }
	.actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.25rem; }
	button, .btn { font: 600 .8125rem/1 inherit; letter-spacing: .06em; text-transform: uppercase; padding: .9rem 1.4rem; border-radius: 4px; border: 1px solid #b8892b; background: #b8892b; color: #fff; cursor: pointer; text-decoration: none; }
	button.secondary { background: #fff; color: #9a7020; }
	textarea { width: 100%; box-sizing: border-box; min-height: 6rem; padding: .6rem; border: 1px solid #e8e2d6; border-radius: 4px; font: inherit; }
	.error { color: #a3222b; margin: .5rem 0; }
	.ok { color: #2f7a3b; }
	.muted { color: #555; font-size: .875rem; }
	details { margin-top: 1rem; }
</style>
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
