<?php
/**
 * Bulgarian gap-fillers for WooCommerce strings that its own bg_BG pack leaves in English
 * on customer-facing pages and emails. Applied by Reklamo_I18n ONLY when WooCommerce has
 * no translation of its own (so upstream translations always win). Found by the
 * translation audit (docs/PLAN.md); add to this list when the audit finds more.
 *
 * Key: exact WooCommerce source string. Value: Bulgarian.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

return array(
	// Emails.
	'Good things are heading your way!'         => 'Поръчката Ви е завършена!',
	'We have finished processing your order.'   => 'Приключихме обработката на Вашата поръчка.',
	'Here’s a reminder of what you’ve ordered:' => 'Ето какво поръчахте:',
	// Order-received page, guest verification.
	'To view this page, you must either %1$slogin%2$s or verify the email address associated with the order.' => 'За да видите тази страница, трябва да %1$sвлезете%2$s или да потвърдите имейл адреса, свързан с поръчката.',
	'We were unable to verify the email address you provided. Please try again.' => 'Не успяхме да потвърдим предоставения имейл адрес. Моля, опитайте отново.',
	'Verify'                                    => 'Потвърди',
	'Email address'                             => 'Имейл адрес',
	// Product gallery lightbox.
	'Close (Esc)'                               => 'Затвори (Esc)',
	'Full screen image'                         => 'Изображение на цял екран',
	'Next (arrow right)'                        => 'Следваща (стрелка надясно)',
	'Previous (arrow left)'                     => 'Предишна (стрелка наляво)',
	'Share'                                     => 'Сподели',
	'Toggle fullscreen'                         => 'Цял екран',
	'Zoom in/out'                               => 'Увеличи/намали',
	// Block cart / checkout fallback pages.
	'Your cart is currently empty!'             => 'Кошницата Ви е празна.',
	'New in store'                              => 'Ново в магазина',
	'Browse store'                              => 'Разгледай магазина',
	'Return to shop'                            => 'Обратно към магазина',
);
