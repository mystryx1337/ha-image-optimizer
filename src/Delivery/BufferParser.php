<?php
/**
 * Full-Page Buffer Parser (Optional for Page Builders).
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Delivery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BufferParser
 */
class BufferParser {

	/**
	 * Register output buffering on frontend.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', [ __CLASS__, 'start_buffer' ], -9999 );
	}

	/**
	 * Start output buffering with ContentFilter callback.
	 *
	 * @return void
	 */
	public static function start_buffer(): void {
		if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		ob_start( [ ContentFilter::class, 'filter_html' ] );
	}
}
