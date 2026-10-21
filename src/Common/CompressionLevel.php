<?php
/**
 * Compression Level Enum.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enum CompressionLevel
 */
enum CompressionLevel: string {
	case LOSSLESS = 'lossless';
	case SMART    = 'smart';
	case ULTRA    = 'ultra';

	/**
	 * Get human-readable label.
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::LOSSLESS => __( 'Verlustfrei (Lossless - 100% Schärfe)', 'ha-image-optimizer' ),
			self::SMART    => __( 'Smart (Ausgewogen - ca. 82% Qualität)', 'ha-image-optimizer' ),
			self::ULTRA    => __( 'Ultra (Aggressiv - ca. 72% Qualität)', 'ha-image-optimizer' ),
		};
	}

	/**
	 * Get JPEG/WebP/AVIF quality value (0-100).
	 *
	 * @param string $mime_type MIME type of the image.
	 * @return int
	 */
	public function quality( string $mime_type = 'image/jpeg' ): int {
		return match ( $this ) {
			self::LOSSLESS => 100,
			self::SMART    => match ( $mime_type ) {
				'image/avif' => 75, // AVIF is denser, 75 achieves exceptional fidelity
				default      => 82,
			},
			self::ULTRA    => match ( $mime_type ) {
				'image/avif' => 65,
				default      => 72,
			},
		};
	}
}
