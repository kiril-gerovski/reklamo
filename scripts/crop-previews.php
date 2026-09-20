<?php
/**
 * One-off: cut the four package photos out of the flattened design mockup.
 * Run: docker compose run --rm -T cli php /var/www/html/scripts/crop-previews.php
 */

$src_path = __DIR__ . '/../design/preview.webp';
$out_dir  = __DIR__ . '/../wp-content/themes/reklamo/assets/img/packages';
// Inset past the card border. The red card carries the "most popular" badge over the photo's top
// left corner; it is painted out before trimming, since the theme draws that badge itself.
$boxes    = array(
	'red-business-pack'   => array( 58, 503, 184, 160, array( 0, 0, 90, 25 ) ),
	'office-starter-pack' => array( 259, 505, 180, 158 ),
	'event-pack'          => array( 458, 505, 180, 158 ),
	'premium-pack'        => array( 657, 505, 180, 158 ),
);

$src = imagecreatefromwebp( $src_path );
if ( ! $src ) {
	exit( "cannot read $src_path\n" );
}
@mkdir( $out_dir, 0755, true );

foreach ( $boxes as $slug => $b ) {
	list( $x, $y, $w, $h ) = $b;
	$cut = imagecreatetruecolor( $w, $h );
	imagecopy( $cut, $src, 0, 0, $x, $y, $w, $h );
	if ( isset( $b[4] ) ) {
		list( $mx, $my, $mw, $mh ) = $b[4];
		imagefilledrectangle( $cut, $mx, $my, $mx + $mw, $my + $mh, imagecolorallocate( $cut, 255, 255, 255 ) );
	}

	// Trim the card's white margin so every photo fills its frame the same way.
	$min_x = $w;
	$min_y = $h;
	$max_x = 0;
	$max_y = 0;
	for ( $py = 0; $py < $h; $py++ ) {
		for ( $px = 0; $px < $w; $px++ ) {
			$c = imagecolorat( $cut, $px, $py );
			$r = ( $c >> 16 ) & 0xFF;
			$g = ( $c >> 8 ) & 0xFF;
			$bl = $c & 0xFF;
			if ( $r < 242 || $g < 242 || $bl < 242 ) {
				$min_x = min( $min_x, $px );
				$min_y = min( $min_y, $py );
				$max_x = max( $max_x, $px );
				$max_y = max( $max_y, $py );
			}
		}
	}
	if ( $max_x <= $min_x ) {
		echo "$slug: nothing found in the box\n";
		continue;
	}
	$pad   = 6;
	$min_x = max( 0, $min_x - $pad );
	$min_y = max( 0, $min_y - $pad );
	$max_x = min( $w - 1, $max_x + $pad );
	$max_y = min( $h - 1, $max_y + $pad );
	$tw    = $max_x - $min_x + 1;
	$th    = $max_y - $min_y + 1;

	// The card's media box is --paper, so near-white pixels become that colour and the photo
	// stops reading as a white square pasted on the card.
	for ( $py = $min_y; $py <= $max_y; $py++ ) {
		for ( $px = $min_x; $px <= $max_x; $px++ ) {
			$c = imagecolorat( $cut, $px, $py );
			if ( ( ( $c >> 16 ) & 0xFF ) >= 246 && ( ( $c >> 8 ) & 0xFF ) >= 246 && ( $c & 0xFF ) >= 246 ) {
				imagesetpixel( $cut, $px, $py, imagecolorallocate( $cut, 0xFA, 0xF8, 0xF4 ) );
			}
		}
	}

	// Square canvas, photo centred, upscaled to the catalogue size.
	$side  = 600;
	$scale = min( $side / $tw, $side / $th );
	$dw    = (int) round( $tw * $scale );
	$dh    = (int) round( $th * $scale );
	$out   = imagecreatetruecolor( $side, $side );
	imagefill( $out, 0, 0, imagecolorallocate( $out, 0xFA, 0xF8, 0xF4 ) );
	imagecopyresampled( $out, $cut, (int) ( ( $side - $dw ) / 2 ), (int) ( ( $side - $dh ) / 2 ), $min_x, $min_y, $dw, $dh, $tw, $th );
	imagewebp( $out, "$out_dir/$slug.webp", 90 );
	echo "$slug: box {$tw}x{$th} → {$side}x{$side}\n";
}
