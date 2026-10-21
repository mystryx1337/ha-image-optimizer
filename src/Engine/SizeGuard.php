<?php
/**
 * Size Guard - Prevents negative savings.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SizeGuard
 */
class SizeGuard {

	/**
	 * Verify if an optimized file is smaller than original.
	 *
	 * @param int $original_bytes Original size in bytes.
	 * @param int $new_bytes New size in bytes.
	 * @return bool True if new file is strictly smaller and valid.
	 */
	public static function is_smaller( int $original_bytes, int $new_bytes ): bool {
		return ( $new_bytes > 0 && $new_bytes < $original_bytes );
	}

	/**
	 * Calculate percentage saved.
	 *
	 * @param int $original_bytes Size before.
	 * @param int $new_bytes Size after.
	 * @return float Percent saved (e.g. 42.5).
	 */
	public static function calculate_savings_percent( int $original_bytes, int $new_bytes ): float {
		if ( $original_bytes <= 0 || $new_bytes >= $original_bytes ) {
			return 0.0;
		}

		$saved = $original_bytes - $new_bytes;
		return round( ( $saved / $original_bytes ) * 100, 2 );
	}
}
