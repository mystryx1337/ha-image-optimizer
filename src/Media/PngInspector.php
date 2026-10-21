<?php
/**
 * PNG Transparency Inspector.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PngInspector
 */
class PngInspector {

	/**
	 * Check if a PNG image contains transparent pixels.
	 *
	 * @param string $file_path Path to PNG file.
	 * @return bool True if image has transparent pixels.
	 */
	public static function has_transparency( string $file_path ): bool {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		// Quick check on PNG color type from header (byte 25)
		$handle = @fopen( $file_path, 'rb' );
		if ( ! $handle ) {
			return false;
		}

		// Read PNG signature and IHDR
		$header = fread( $handle, 29 );
		fclose( $handle );

		if ( strlen( $header ) < 29 ) {
			return false;
		}

		// Color type is at offset 25
		$color_type = ord( $header[25] );

		// Color type 0 (grayscale), 2 (truecolor) do not have alpha by default unless tRNS chunk exists
		// Color type 4 (grayscale+alpha) and 6 (truecolor+alpha) have an alpha channel
		// Color type 3 (indexed palette) might have tRNS chunk
		if ( in_array( $color_type, [ 4, 6 ], true ) ) {
			// Deep check if any pixel is actually transparent
			return self::check_pixels_for_alpha( $file_path );
		}

		// If indexed or truecolor without alpha channel, check for tRNS chunk in file
		$content = @file_get_contents( $file_path );
		if ( false !== $content && str_contains( $content, 'tRNS' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Deep inspect pixels for alpha < 127 in GD.
	 *
	 * @param string $file_path Path to PNG file.
	 * @return bool
	 */
	private static function check_pixels_for_alpha( string $file_path ): bool {
		if ( ! function_exists( 'imagecreatefrompng' ) ) {
			return true; // Conservative fallback
		}

		wp_raise_memory_limit( 'image' );
		$img = @imagecreatefrompng( $file_path );
		if ( ! $img ) {
			return true;
		}

		$width  = imagesx( $img );
		$height = imagesy( $img );

		// Step sample for large images to save CPU
		$step_x = max( 1, (int) ( $width / 100 ) );
		$step_y = max( 1, (int) ( $height / 100 ) );

		for ( $x = 0; $x < $width; $x += $step_x ) {
			for ( $y = 0; $y < $height; $y += $step_y ) {
				$rgba  = imagecolorat( $img, $x, $y );
				$alpha = ( $rgba >> 24 ) & 0x7F;
				if ( $alpha > 0 ) {
					imagedestroy( $img );
					return true;
				}
			}
		}

		imagedestroy( $img );
		return false;
	}
}
