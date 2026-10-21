<?php
/**
 * Image Resizer (Downscaling for oversized uploads).
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Resizer
 */
class Resizer {

	/**
	 * Downscale an image if its dimensions exceed the configured limits.
	 *
	 * @param string $file_path Absolute path to the image.
	 * @param int    $max_width Max allowed width.
	 * @param int    $max_height Max allowed height.
	 * @return bool True if image was resized or was already within limits.
	 */
	public static function maybe_downscale( string $file_path, int $max_width = 2560, int $max_height = 2560 ): bool {
		if ( ! file_exists( $file_path ) || ! function_exists( 'wp_get_image_editor' ) ) {
			return false;
		}

		$editor = wp_get_image_editor( $file_path );
		if ( is_wp_error( $editor ) ) {
			return false;
		}

		$size = $editor->get_size();
		if ( empty( $size['width'] ) || empty( $size['height'] ) ) {
			return false;
		}

		// Check if downscaling is actually necessary
		if ( $size['width'] <= $max_width && $size['height'] <= $max_height ) {
			return true; // Already within limits
		}

		wp_raise_memory_limit( 'image' );

		// Resize proportionally
		$resized = $editor->resize( $max_width, $max_height, false );
		if ( is_wp_error( $resized ) ) {
			return false;
		}

		$saved = $editor->save( $file_path );
		return ! is_wp_error( $saved );
	}
}
