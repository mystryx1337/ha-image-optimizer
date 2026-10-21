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
		$items       = [];

		foreach ( $attachment_ids as $id ) {
			$id = absint( $id );
			if ( $id <= 0 ) {
				continue;
			}

			try {
				AttachmentProcessor::process( $id, null, $force );
				$data     = get_post_meta( $id, '_ha_io_data', true );
				$file     = get_attached_file( $id );
				$filename = $file ? wp_basename( $file ) : ( 'ID ' . $id );

				$before_b = (int) ( $data['total']['before'] ?? 0 );
				$after_b  = (int) ( $data['total']['after'] ?? 0 );
				$saved_b  = (int) ( $data['total']['saved'] ?? 0 );
				$percent  = (float) ( $data['total']['percent'] ?? 0.0 );

				if ( $saved_b > 0 ) {
					$saved_bytes += $saved_b;
				}

				$items[] = [
					'id'          => $id,
					'filename'    => $filename,
					'before'      => size_format( $before_b ),
					'after'       => size_format( $after_b ),
					'saved'       => size_format( $saved_b ),
					'percent'     => number_format_i18n( $percent, 1 ),
					'has_webp'    => ! empty( $data['original']['webp_size'] ),
					'has_avif'    => ! empty( $data['original']['avif_size'] ),
				];

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
			'items'       => $items,
			'errors'      => $errors,
		];
	}
}
