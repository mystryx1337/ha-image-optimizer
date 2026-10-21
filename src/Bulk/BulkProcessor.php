<?php
/**
 * Bulk Processor for batch optimizations.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Bulk;

use ha\ImageOptimizer\Media\AttachmentProcessor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BulkProcessor
 */
class BulkProcessor {

	/**
	 * Process a batch of attachment IDs.
	 *
	 * @param array<int> $attachment_ids Array of attachment IDs.
	 * @param bool       $force Whether to force re-optimization.
	 * @return array<string, mixed> Result summary.
	 */
	public static function process_batch( array $attachment_ids, bool $force = false ): array {
		$processed   = 0;
		$saved_bytes = 0;
		$errors      = [];

		foreach ( $attachment_ids as $id ) {
			$id = absint( $id );
			if ( $id <= 0 ) {
				continue;
			}

			try {
				AttachmentProcessor::process( $id, null, $force );
				$data = get_post_meta( $id, '_ha_io_data', true );

				if ( ! empty( $data['total']['saved'] ) ) {
					$saved_bytes += (int) $data['total']['saved'];
				}
				$processed++;
			} catch ( \Throwable $e ) {
				$errors[] = [
					'id'      => $id,
					'message' => $e->getMessage(),
				];
			}
		}

		return [
			'processed'   => $processed,
			'saved_bytes' => $saved_bytes,
			'errors'      => $errors,
		];
	}
}
