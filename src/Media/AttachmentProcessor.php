<?php
/**
 * Attachment Processor Pipeline ("In einem Rutsch").
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Media;

use ha\ImageOptimizer\Common\Settings;
use ha\ImageOptimizer\Common\CompressionLevel;
use ha\ImageOptimizer\Engine\OptimizerFactory;
use ha\ImageOptimizer\Engine\SizeGuard;
use ha\ImageOptimizer\NextGen\WebpConverter;
use ha\ImageOptimizer\NextGen\AvifConverter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AttachmentProcessor
 */
class AttachmentProcessor {

	/**
	 * Supported MIME types for optimization.
	 */
	public const SUPPORTED_MIMES = [
		'image/jpeg',
		'image/jpg',
		'image/png',
		'image/gif',
		'image/webp',
	];

	/**
	 * Hook callback for wp_generate_attachment_metadata.
	 *
	 * @param array<string, mixed> $metadata Attachment metadata.
	 * @param int                  $attachment_id Attachment ID.
	 * @return array<string, mixed>
	 */
	public static function filter_generate_metadata( array $metadata, int $attachment_id ): array {
		return self::process( $attachment_id, $metadata );
	}

	/**
	 * Process an attachment: Original + Sub-sizes + WebP/AVIF.
	 *
	 * @param int                        $attachment_id Attachment ID.
	 * @param array<string, mixed>|null  $metadata Optional metadata if already loaded.
	 * @param bool                       $force Whether to force re-optimization.
	 * @param string|null                $custom_level Optional custom compression level ('ultra'/'hoch', 'smart'/'mittel', 'lossless'/'niedrig', 'default').
	 * @return array<string, mixed>
	 */
	public static function process( int $attachment_id, ?array $metadata = null, bool $force = false, ?string $custom_level = null ): array {
		@set_time_limit( 60 );
		wp_raise_memory_limit( 'image' );

		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return $metadata ?? [];
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, self::SUPPORTED_MIMES, true ) ) {
			return $metadata ?? [];
		}

		// Check if already optimized
		$existing_data = get_post_meta( $attachment_id, '_ha_io_data', true );
		if ( ! empty( $existing_data ) && ! $force ) {
			return $metadata ?? wp_get_attachment_metadata( $attachment_id ) ?? [];
		}

		if ( null === $metadata ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( ! is_array( $metadata ) ) {
				$metadata = [];
			}
		}

		$settings = Settings::get_all();

		// 1. If forcing re-optimization and backup exists, restore master so we compress from pristine original
		if ( $force && BackupManager::has_backup( $attachment_id ) ) {
			$backup_file = BackupManager::get_backup_file_path( $attachment_id, $file );
			if ( file_exists( $backup_file ) ) {
				@copy( $backup_file, $file );
				// Clean existing Next-Gen formats before re-generating
				BackupManager::clean_nextgen_files( $attachment_id );
				// Re-create intermediate sizes from uncompressed master if supported
				if ( function_exists( 'wp_create_image_subsizes' ) ) {
					$fresh_subsizes = wp_create_image_subsizes( $file, $attachment_id );
					if ( ! empty( $fresh_subsizes['sizes'] ) ) {
						$metadata['sizes'] = $fresh_subsizes['sizes'];
						wp_update_attachment_metadata( $attachment_id, $metadata );
					}
				}
			}
		}

		// 2. Backup original before any modification (if not already backed up)
		if ( ! empty( $settings['backup_originals'] ) ) {
			BackupManager::backup( $attachment_id, $file );
		}

		// 3. Downscale if oversized
		if ( ! empty( $settings['resize_large_images'] ) ) {
			Resizer::maybe_downscale( $file, (int) $settings['max_width'], (int) $settings['max_height'] );
		}

		try {
			$optimizer = OptimizerFactory::create();
		} catch ( \Throwable $e ) {
			return $metadata;
		}

		// Resolve compression levels: Custom Level vs. Global Settings
		$resolved_custom_level = null;
		if ( null !== $custom_level ) {
			if ( 'default' === $custom_level || '' === trim( $custom_level ) ) {
				delete_post_meta( $attachment_id, '_ha_io_custom_level' );
			} else {
				$parsed = CompressionLevel::fromString( $custom_level );
				if ( $parsed ) {
					$resolved_custom_level = $parsed->value;
					update_post_meta( $attachment_id, '_ha_io_custom_level', $resolved_custom_level );
				}
			}
		} else {
			$saved_custom = (string) get_post_meta( $attachment_id, '_ha_io_custom_level', true );
			if ( ! empty( $saved_custom ) ) {
				$parsed = CompressionLevel::fromString( $saved_custom );
				if ( $parsed ) {
					$resolved_custom_level = $parsed->value;
				}
			}
		}

		if ( $resolved_custom_level ) {
			$level_enum  = CompressionLevel::from( $resolved_custom_level );
			$orig_level  = $level_enum;
			$sizes_level = $level_enum;
		} else {
			$orig_level_key  = $settings['compression_original'] ?? 'lossless';
			$sizes_level_key = $settings['compression_sizes'] ?? 'smart';
			$orig_level      = CompressionLevel::tryFrom( $orig_level_key ) ?? CompressionLevel::LOSSLESS;
			$sizes_level     = CompressionLevel::tryFrom( $sizes_level_key ) ?? CompressionLevel::SMART;
		}

		$keep_exif = ! empty( $settings['keep_exif'] );

		$stats = [
			'status'         => 'optimized',
			'optimized_at'   => current_time( 'mysql' ),
			'engine'         => $optimizer->get_name(),
			'level_original' => $orig_level->value,
			'level_sizes'    => $sizes_level->value,
			'custom_level'   => $resolved_custom_level,
			'original'       => [],
			'sizes'          => [],
			'total'          => [
				'before'  => 0,
				'after'   => 0,
				'saved'   => 0,
				'percent' => 0.0,
			],
		];

		// 4. Optimize Original Master File
		$orig_before = (int) filesize( $file );
		if ( $force && ! empty( $existing_data['original']['before'] ) && (int) $existing_data['original']['before'] > $orig_before ) {
			$orig_before = (int) $existing_data['original']['before'];
		}
		$stats['total']['before'] += $orig_before;

		try {
			$optimizer->optimize( $file, $orig_level, $keep_exif );
		} catch ( \Throwable $e ) {
			// Continue gracefully if image optimization fails
		}
		$orig_after = (int) filesize( $file );
		$stats['total']['after'] += $orig_after;

		$stats['original'] = [
			'before'  => $orig_before,
			'after'   => $orig_after,
			'saved'   => max( 0, $orig_before - $orig_after ),
			'percent' => SizeGuard::calculate_savings_percent( $orig_before, $orig_after ),
		];

		// Next-Gen for Original
		if ( ! empty( $settings['generate_webp'] ) ) {
			try {
				$webp_path = WebpConverter::convert( $file, $orig_level->quality( 'image/webp' ) );
				if ( $webp_path ) {
					$stats['original']['webp_size'] = (int) filesize( $webp_path );
				}
			} catch ( \Throwable $e ) {}
		}

		if ( ! empty( $settings['generate_avif'] ) ) {
			try {
				$avif_path = AvifConverter::convert( $file, $orig_level->quality( 'image/avif' ) );
				if ( $avif_path ) {
					$stats['original']['avif_size'] = (int) filesize( $avif_path );
				}
			} catch ( \Throwable $e ) {}
		}

		// 5. Optimize Sub-Sizes (Thumbnails)
		$base_dir = dirname( $file );
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size_name => $size_info ) {
				@set_time_limit( 60 );

				if ( empty( $size_info['file'] ) ) {
					continue;
				}

				$sub_file = trailingslashit( $base_dir ) . $size_info['file'];
				if ( ! file_exists( $sub_file ) ) {
					continue;
				}

				$sub_before = (int) filesize( $sub_file );
				if ( $force && ! empty( $existing_data['sizes'][ $size_name ]['before'] ) && (int) $existing_data['sizes'][ $size_name ]['before'] > $sub_before ) {
					$sub_before = (int) $existing_data['sizes'][ $size_name ]['before'];
				}
				$stats['total']['before'] += $sub_before;

				try {
					$optimizer->optimize( $sub_file, $sizes_level, false );
				} catch ( \Throwable $e ) {
					// Skip sub-size if compression fails
				}
				$sub_after = (int) filesize( $sub_file );
				$stats['total']['after'] += $sub_after;

				$size_stat = [
					'before'  => $sub_before,
					'after'   => $sub_after,
					'saved'   => max( 0, $sub_before - $sub_after ),
					'percent' => SizeGuard::calculate_savings_percent( $sub_before, $sub_after ),
				];

				// Next-Gen for sub-size
				if ( ! empty( $settings['generate_webp'] ) ) {
					try {
						$sub_webp = WebpConverter::convert( $sub_file, $sizes_level->quality( 'image/webp' ) );
						if ( $sub_webp ) {
							$size_stat['webp_size'] = (int) filesize( $sub_webp );
						}
					} catch ( \Throwable $e ) {}
				}

				if ( ! empty( $settings['generate_avif'] ) ) {
					try {
						$sub_avif = AvifConverter::convert( $sub_file, $sizes_level->quality( 'image/avif' ) );
						if ( $sub_avif ) {
							$size_stat['avif_size'] = (int) filesize( $sub_avif );
						}
					} catch ( \Throwable $e ) {}
				}

				$stats['sizes'][ $size_name ] = $size_stat;
			}
		}

		// 5. Total Calculations
		$total_before = $stats['total']['before'];
		$total_after  = $stats['total']['after'];
		$stats['total']['saved']   = max( 0, $total_before - $total_after );
		$stats['total']['percent'] = SizeGuard::calculate_savings_percent( $total_before, $total_after );

		// 6. Save Stats in Post-Meta
		update_post_meta( $attachment_id, '_ha_io_data', $stats );

		/**
		 * Action triggered after an attachment has been optimized.
		 *
		 * @param int                  $attachment_id Attachment ID.
		 * @param array<string, mixed> $stats Optimization metrics.
		 */
		do_action( 'ha_io_attachment_optimized', $attachment_id, $stats );

		return $metadata;
	}
}
