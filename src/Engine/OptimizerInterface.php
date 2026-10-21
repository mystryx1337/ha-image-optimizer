<?php
/**
 * Optimizer Interface.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Engine;

use ha\ImageOptimizer\Common\CompressionLevel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface OptimizerInterface
 */
interface OptimizerInterface {

	/**
	 * Check whether this driver is supported and operational on the server.
	 *
	 * @return bool
	 */
	public function is_available(): bool;

	/**
	 * Get the human-readable engine name (e.g. 'Imagick', 'GD').
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Optimize an existing image file (JPEG, PNG, GIF).
	 *
	 * @param string           $file_path Absolute path to the file.
	 * @param CompressionLevel $level Compression level.
	 * @param bool             $keep_exif Whether to keep EXIF/IPTC metadata.
	 * @return OptimizationResult
	 */
	public function optimize( string $file_path, CompressionLevel $level, bool $keep_exif = false ): OptimizationResult;

	/**
	 * Generate a WebP version of the given image.
	 *
	 * @param string $source_path Source image path.
	 * @param string $target_path Destination .webp path.
	 * @param int    $quality Quality (1-100).
	 * @return bool True on success.
	 */
	public function generate_webp( string $source_path, string $target_path, int $quality ): bool;

	/**
	 * Generate an AVIF version of the given image.
	 *
	 * @param string $source_path Source image path.
	 * @param string $target_path Destination .avif path.
	 * @param int    $quality Quality (1-100).
	 * @return bool True on success.
	 */
	public function generate_avif( string $source_path, string $target_path, int $quality ): bool;
}
