<?php
/**
 * Optimization Result Data Transfer Object.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptimizationResult
 */
class OptimizationResult {

	/**
	 * Constructor.
	 *
	 * @param bool                  $success         Whether optimization succeeded.
	 * @param string                $file_path       Path to target file.
	 * @param int                   $original_size   Size in bytes before optimization.
	 * @param int                   $optimized_size  Size in bytes after optimization.
	 * @param int                   $saved_bytes     Bytes saved.
	 * @param float                 $saved_percent   Percentage saved (0.0 to 100.0).
	 * @param string|null           $error_message   Optional error message.
	 * @param array<string, string> $nextgen_files   Generated NextGen files (format => path).
	 */
	public function __construct(
		public readonly bool $success,
		public readonly string $file_path,
		public readonly int $original_size,
		public readonly int $optimized_size,
		public readonly int $saved_bytes,
		public readonly float $saved_percent,
		public readonly ?string $error_message = null,
		public readonly array $nextgen_files = []
	) {}

	/**
	 * Create a failure result.
	 *
	 * @param string $file_path Target file.
	 * @param string $message Error message.
	 * @param int    $original_size File size before.
	 * @return self
	 */
	public static function failure( string $file_path, string $message, int $original_size = 0 ): self {
		return new self(
			success: false,
			file_path: $file_path,
			original_size: $original_size,
			optimized_size: $original_size,
			saved_bytes: 0,
			saved_percent: 0.0,
			error_message: $message,
			nextgen_files: []
		);
	}

	/**
	 * Create a skipped result (e.g. if file is already optimized or larger).
	 *
	 * @param string $file_path Target file.
	 * @param int    $size File size.
	 * @param string $reason Reason skipped.
	 * @return self
	 */
	public static function skipped( string $file_path, int $size, string $reason ): self {
		return new self(
			success: true,
			file_path: $file_path,
			original_size: $size,
			optimized_size: $size,
			saved_bytes: 0,
			saved_percent: 0.0,
			error_message: $reason,
			nextgen_files: []
		);
	}

	/**
	 * Convert to array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'success'        => $this->success,
			'file_path'      => $this->file_path,
			'original_size'  => $this->original_size,
			'optimized_size' => $this->optimized_size,
			'saved_bytes'    => $this->saved_bytes,
			'saved_percent'  => $this->saved_percent,
			'error_message'  => $this->error_message,
			'nextgen_files'  => $this->nextgen_files,
		];
	}
}
