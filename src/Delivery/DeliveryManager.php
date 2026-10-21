<?php
/**
 * Delivery Manager Dispatcher.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Delivery;

use ha\ImageOptimizer\Common\Settings;
use ha\ImageOptimizer\Common\DeliveryMode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DeliveryManager
 */
class DeliveryManager {

	/**
	 * Initialize delivery subsystem.
	 *
	 * @return void
	 */
	public static function init(): void {
		$mode = Settings::get( 'delivery_mode', DeliveryMode::CONTENT_FILTER->value );

		CachePurger::register();

		if ( DeliveryMode::HTACCESS->value === $mode ) {
			if ( ! HtaccessManager::are_rules_active() ) {
				HtaccessManager::apply_rules();
			}
		} else {
			if ( HtaccessManager::are_rules_active() ) {
				HtaccessManager::remove_rules();
			}

			if ( DeliveryMode::CONTENT_FILTER->value === $mode ) {
				ContentFilter::register();

				if ( Settings::get( 'enable_full_buffer', false ) ) {
					BufferParser::register();
				}
			}
		}
	}
}
