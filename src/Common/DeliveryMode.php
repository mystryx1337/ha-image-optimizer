<?php
/**
 * Delivery Mode Enum.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enum DeliveryMode
 */
enum DeliveryMode: string {
	case CONTENT_FILTER = 'content_filter';
	case HTACCESS       = 'htaccess';
	case OFF            = 'off';

	/**
	 * Get human-readable label.
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::CONTENT_FILTER => __( '<picture>-Tag Ersetzung (Universell & CDN-sicher)', 'ha-image-optimizer' ),
			self::HTACCESS       => __( '.htaccess Server-Rewrite (0 ms PHP-Overhead, Apache & LiteSpeed)', 'ha-image-optimizer' ),
			self::OFF            => __( 'Deaktiviert (Bilder werden nicht automatisch ersetzt)', 'ha-image-optimizer' ),
		};
	}
}
