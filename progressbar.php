<?php
/**
 * Generates a GIF image showing a bar chart of the current months
 * donation progress
 * Data is passed by $_GET parameters, expense and income
 *
 * This script requires PHP to be compiled with the gd library
 * (provided by the PHP5-GD library be installed (ubuntu/debian))
 */

# Setup image
header( 'Content-type: image/gif' );
$im = imagecreate( 300, 20 );

# Setup colours
$background = imagecolorallocate( $im, 255, 255, 255 ); // first defined colour becomes background
$fill = imagecolorallocate( $im, 128, 255, 128 );
$grid = imagecolorallocate( $im, 200, 200, 200 );
$text = imagecolorallocate( $im, 0, 0, 0 );

# Setup fonts
$font = imagepsloadfont( 'font.pfb' );

# Draw fill
$progress = $_GET['income'] / $_GET['expense'] * ( 300 - 1 );
imagefilledrectangle( $im, 0, 0, $progress, 20 - 1, $fill );

# draw grid
foreach( range( 1, 8 ) as $i ) {
	$w = $i * ( 300 - 1 ) / 8;
	imagerectangle( $im, 0, 0, $w, 20 - 1, $grid );
}

# Add text
drawText( $im, '$' . number_format( $_GET['income'], 2 ), $font, 14, $text, $background, $progress + 10, 0, 0, 0, 0 );

# send image and clean up 
imagegif( $im );
imagedestroy( $im );

# Displays text on the image.
# text is centered vertically (offset possible via $y)
# text is displayed to the right of $x, but moved to the left if
#   $x is too close to the right side
# text is also kept within the image if $x is off the image.
function drawText( $image, $text, $font, $size, $foreground, $background, $x, $y, $space, $tightness, $angle ) {
	list( $lx, $ly, $rx, $ry ) = imagepsbbox( $text, $font, $size );
	if( $x + $rx > ( 300-1 ) ) {
		$x- = $rx + 20;
	}
	if( $x + $rx > ( 300-1 ) ) {
		$x = 300 - $rx - 10;
	}
	imagepstext(
		$image, $text, $font, $size, $foreground, $background, $x,
		$y + ( 20 + $ry ) / 2, $space, $tightness, $angle, 16
	);
}