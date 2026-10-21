<?php
/**
 * WP-CLI Command for HA Image Optimizer.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Cli;

use ha\ImageOptimizer\Common\Environment;
use ha\ImageOptimizer\Bulk\ProgressTracker;
use ha\ImageOptimizer\Bulk\BulkProcessor;
use ha\ImageOptimizer\Media\BackupManager;
use ha\ImageOptimizer\Media\CustomFolderProcessor;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptimizerCliCommand
 */
class OptimizerCliCommand {

	/**
	 * Register the command with WP-CLI.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'ha-optimizer', __CLASS__ );
		}
	}

	/**
	 * Display current optimization status and server diagnostics.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ha-optimizer status
	 *
	 * @param array<string> $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function status( array $args, array $assoc_args ): void {
		$caps  = Environment::get_capabilities();
		$stats        = ProgressTracker::get_stats();
		$custom_stats = CustomFolderProcessor::get_stats();

		WP_CLI::line( WP_CLI::colorize( '%G=== HA Image Optimizer: System-Status ===%n' ) );
		WP_CLI::line( sprintf( 'PHP-Version: %s (%s)', $caps['php']['version'], $caps['php']['compatible'] ? 'OK' : 'Inkompatibel' ) );
		WP_CLI::line( sprintf( 'Aktiver Treiber: %s', strtoupper( $caps['best_driver'] ) ) );
		WP_CLI::line( sprintf( 'WebP-Unterstützung: %s', $caps['supports_webp'] ? 'Ja' : 'Nein' ) );
		WP_CLI::line( sprintf( 'AVIF-Unterstützung: %s', $caps['supports_avif'] ? 'Ja' : 'Nein' ) );
		WP_CLI::line( sprintf( 'Server: %s (Rewrite-Unterstützung: %s)', $caps['server']['software'], $caps['server']['supports_htaccess'] ? 'Ja' : 'Nein' ) );
		WP_CLI::line( sprintf( 'Speicherlimit: %s', $caps['memory']['limit_string'] ) );

		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%G=== Mediathek-Statistik ===%n' ) );
		WP_CLI::line( sprintf( 'Bilder Gesamt: %d', $stats['total_images'] ) );
		WP_CLI::line( sprintf( 'Bereits Optimiert: %d', $stats['optimized_images'] ) );
		WP_CLI::line( sprintf( 'Noch Unoptimiert: %d', $stats['unoptimized'] ) );
		WP_CLI::line( sprintf( 'Gesparter Speicherplatz: %s (%s%%)', size_format( $stats['saved_bytes'] ), $stats['saved_percent'] ) );

		if ( ! empty( $custom_stats['total_images'] ) ) {
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( '%G=== Theme- & Custom-Ordner-Statistik ===%n' ) );
			WP_CLI::line( sprintf( 'Dateien Gesamt: %d', $custom_stats['total_images'] ) );
			WP_CLI::line( sprintf( 'Bereits Optimiert: %d', $custom_stats['optimized_images'] ) );
			WP_CLI::line( sprintf( 'Noch Unoptimiert: %d', $custom_stats['unoptimized'] ) );
			WP_CLI::line( sprintf( 'Gesparter Speicherplatz: %s (%s%%)', size_format( $custom_stats['saved_bytes'] ), $custom_stats['saved_percent'] ) );
		}
	}

	/**
	 * Optimize images via CLI.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Process all images including already optimized ones.
	 *
	 * [--force]
	 * : Force re-optimization even if already processed.
	 *
	 * [--batch-size=<int>]
	 * : Number of images per batch (default: 25).
	 *
	 * ## EXAMPLES
	 *
	 *     wp ha-optimizer optimize
	 *     wp ha-optimizer optimize --all --force
	 *
	 * @param array<string> $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function optimize( array $args, array $assoc_args ): void {
		$all        = ! empty( $assoc_args['all'] );
		$force      = ! empty( $assoc_args['force'] ) || $all;
		$batch_size = isset( $assoc_args['batch-size'] ) ? absint( $assoc_args['batch-size'] ) : 25;

		WP_CLI::log( 'Ermittle zu optimierende Bilder...' );

		$ids = ProgressTracker::get_queue( 999999, $all );
		$total = count( $ids );

		if ( 0 === $total ) {
			WP_CLI::success( 'Keine unoptimierten Bilder gefunden. Alles auf dem neuesten Stand!' );
			return;
		}

		WP_CLI::log( sprintf( 'Starte Optimierung von %d Bildern...', $total ) );
		$progress = \WP_CLI\Utils\make_progress_bar( 'Fortschritt', $total );

		$total_saved = 0;
		$chunks      = array_chunk( $ids, $batch_size );

		foreach ( $chunks as $chunk ) {
			$res = BulkProcessor::process_batch( $chunk, $force );
			$total_saved += $res['saved_bytes'];

			foreach ( $chunk as $id ) {
				$progress->tick();
			}
		}

		$progress->finish();
		WP_CLI::success( sprintf( 'Optimierung abgeschlossen! Insgesamt wurden %s Speicherplatz gespart.', size_format( $total_saved ) ) );
	}

	/**
	 * Restore original images from backups.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Restore all backed-up images.
	 *
	 * [--id=<int>]
	 * : Restore a specific attachment ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ha-optimizer restore --id=123
	 *     wp ha-optimizer restore --all
	 *
	 * @param array<string> $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function restore( array $args, array $assoc_args ): void {
		if ( ! empty( $assoc_args['id'] ) ) {
			$id = absint( $assoc_args['id'] );
			if ( BackupManager::restore( $id ) ) {
				WP_CLI::success( sprintf( 'Bild ID %d erfolgreich wiederhergestellt.', $id ) );
			} else {
				WP_CLI::error( sprintf( 'Konnte Bild ID %d nicht wiederherstellen (kein Backup vorhanden oder Fehler).', $id ) );
			}
			return;
		}

		if ( ! empty( $assoc_args['all'] ) ) {
			global $wpdb;
			$all_ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ha_io_data'" );
			$count   = 0;

			foreach ( $all_ids as $id ) {
				if ( BackupManager::restore( (int) $id ) ) {
					$count++;
				}
			}

			WP_CLI::success( sprintf( '%d Originalbilder wurden erfolgreich wiederhergestellt.', $count ) );
			return;
		}

		WP_CLI::error( 'Bitte gib entweder --id=<ID> oder --all an.' );
	}

	/**
	 * Optimize images in theme and custom folders via CLI.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Process all custom folder images including already optimized ones.
	 *
	 * [--force]
	 * : Force re-optimization even if already processed.
	 *
	 * [--batch-size=<int>]
	 * : Number of files per batch (default: 25).
	 *
	 * ## EXAMPLES
	 *
	 *     wp ha-optimizer optimize-custom
	 *     wp ha-optimizer optimize-custom --all --force
	 *
	 * @subcommand optimize-custom
	 *
	 * @param array<string> $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function optimize_custom( array $args, array $assoc_args ): void {
		$all        = ! empty( $assoc_args['all'] );
		$force      = ! empty( $assoc_args['force'] ) || $all;
		$batch_size = isset( $assoc_args['batch-size'] ) ? absint( $assoc_args['batch-size'] ) : 25;

		WP_CLI::log( 'Ermittle zu optimierende Theme- und Custom-Dateien...' );

		$files = CustomFolderProcessor::get_queue( $all );
		$total = count( $files );

		if ( 0 === $total ) {
			WP_CLI::success( 'Keine unoptimierten Dateien in Custom-Ordnern gefunden. Alles auf dem neuesten Stand!' );
			return;
		}

		WP_CLI::log( sprintf( 'Starte Optimierung von %d Dateien...', $total ) );
		$progress = \WP_CLI\Utils\make_progress_bar( 'Fortschritt', $total );

		$total_saved = 0;
		$chunks      = array_chunk( $files, $batch_size );

		foreach ( $chunks as $chunk ) {
			$res = BulkProcessor::process_custom_batch( $chunk, $force );
			$total_saved += $res['saved_bytes'];

			foreach ( $chunk as $file ) {
				$progress->tick();
			}
		}

		$progress->finish();
		WP_CLI::success( sprintf( 'Custom-Optimierung abgeschlossen! Insgesamt wurden %s Speicherplatz gespart.', size_format( $total_saved ) ) );
	}

	/**
	 * Restore original images in custom folders from backups.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Restore all backed-up custom files.
	 *
	 * [--file=<path>]
	 * : Relative file path from wp-content to restore.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ha-optimizer restore-custom --file="themes/my-theme/assets/hero.jpg"
	 *     wp ha-optimizer restore-custom --all
	 *
	 * @subcommand restore-custom
	 *
	 * @param array<string> $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function restore_custom( array $args, array $assoc_args ): void {
		if ( ! empty( $assoc_args['file'] ) ) {
			$file = sanitize_text_field( (string) $assoc_args['file'] );
			if ( CustomFolderProcessor::restore_file( $file ) ) {
				WP_CLI::success( sprintf( 'Datei %s erfolgreich aus Backup wiederhergestellt.', $file ) );
			} else {
				WP_CLI::error( sprintf( 'Konnte Datei %s nicht wiederherstellen (kein Backup vorhanden oder Fehler).', $file ) );
			}
			return;
		}

		if ( ! empty( $assoc_args['all'] ) ) {
			$stored = get_option( CustomFolderProcessor::OPTION_NAME, [] );
			$count  = 0;

			if ( is_array( $stored ) ) {
				foreach ( $stored as $item ) {
					if ( ! empty( $item['file'] ) && CustomFolderProcessor::restore_file( (string) $item['file'] ) ) {
						$count++;
					}
				}
			}

			WP_CLI::success( sprintf( '%d Custom-Originaldateien wurden erfolgreich wiederhergestellt.', $count ) );
			return;
		}

		WP_CLI::error( 'Bitte gib entweder --file=<rel_path> oder --all an.' );
	}
}

