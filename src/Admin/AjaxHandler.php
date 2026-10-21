<?php
/**
 * AJAX Handler with strict Nonce & Capability Verification.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Admin;

use ha\ImageOptimizer\Media\AttachmentProcessor;
use ha\ImageOptimizer\Media\BackupManager;
use ha\ImageOptimizer\Bulk\BulkProcessor;
use ha\ImageOptimizer\Bulk\ProgressTracker;
use ha\ImageOptimizer\Common\Settings;

use ha\ImageOptimizer\Media\CustomFolderProcessor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AjaxHandler
 */
class AjaxHandler {

	/**
	 * Register secure AJAX actions.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_ajax_ha_io_optimize_single', [ __CLASS__, 'handle_optimize_single' ] );
		add_action( 'wp_ajax_ha_io_restore_single', [ __CLASS__, 'handle_restore_single' ] );
		add_action( 'wp_ajax_ha_io_bulk_batch', [ __CLASS__, 'handle_bulk_batch' ] );
		add_action( 'wp_ajax_ha_io_get_bulk_queue', [ __CLASS__, 'handle_get_bulk_queue' ] );
		add_action( 'wp_ajax_ha_io_get_custom_queue', [ __CLASS__, 'handle_get_custom_queue' ] );
		add_action( 'wp_ajax_ha_io_bulk_custom_batch', [ __CLASS__, 'handle_bulk_custom_batch' ] );
		add_action( 'wp_ajax_ha_io_restore_custom', [ __CLASS__, 'handle_restore_custom' ] );
	}

	/**
	 * Single image optimization AJAX.
	 *
	 * @return void
	 */
	public static function handle_optimize_single(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine ausreichenden Berechtigungen.', 'ha-image-optimizer' ) ], 403 );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( $attachment_id <= 0 ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Ungültige Bild-ID.', 'ha-image-optimizer' ) ], 400 );
		}

		try {
			AttachmentProcessor::process( $attachment_id, null, true );
			$data = get_post_meta( $attachment_id, '_ha_io_data', true );

			wp_send_json_success( [
				'message' => esc_html__( 'Bild erfolgreich optimiert.', 'ha-image-optimizer' ),
				'data'    => $data,
			] );
		} catch ( \Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Single image restore AJAX.
	 *
	 * @return void
	 */
	public static function handle_restore_single(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine ausreichenden Berechtigungen.', 'ha-image-optimizer' ) ], 403 );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( $attachment_id <= 0 ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Ungültige Bild-ID.', 'ha-image-optimizer' ) ], 400 );
		}

		if ( BackupManager::restore( $attachment_id ) ) {
			wp_send_json_success( [
				'message' => esc_html__( 'Originalbild erfolgreich wiederhergestellt.', 'ha-image-optimizer' ),
			] );
		} else {
			wp_send_json_error( [
				'message' => esc_html__( 'Wiederherstellung fehlgeschlagen (kein Backup vorhanden).', 'ha-image-optimizer' ),
			], 500 );
		}
	}

	/**
	 * Retrieve queue of IDs for the bulk optimizer.
	 *
	 * @return void
	 */
	public static function handle_get_bulk_queue(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$all   = ! empty( $_POST['all'] );
		$queue = ProgressTracker::get_queue( 999999, $all );

		wp_send_json_success( [
			'queue' => $queue,
			'total' => count( $queue ),
		] );
	}

	/**
	 * Process a single batch chunk from the bulk runner.
	 *
	 * @return void
	 */
	public static function handle_bulk_batch(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$ids   = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : [];
		$force = ! empty( $_POST['force'] );

		if ( empty( $ids ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine IDs übergeben.', 'ha-image-optimizer' ) ], 400 );
		}

		$result = BulkProcessor::process_batch( $ids, $force );
		wp_send_json_success( $result );
	}

	/**
	 * Retrieve queue of custom folder relative paths for bulk runner.
	 *
	 * @return void
	 */
	public static function handle_get_custom_queue(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$all   = ! empty( $_POST['all'] );
		$queue = CustomFolderProcessor::get_queue( $all );

		wp_send_json_success( [
			'queue' => $queue,
			'total' => count( $queue ),
		] );
	}

	/**
	 * Process a batch of custom folder images.
	 *
	 * @return void
	 */
	public static function handle_bulk_custom_batch(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$files = isset( $_POST['files'] ) ? (array) $_POST['files'] : [];
		$force = ! empty( $_POST['force'] );

		if ( empty( $files ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Dateien übergeben.', 'ha-image-optimizer' ) ], 400 );
		}

		$result = BulkProcessor::process_custom_batch( $files, $force );
		wp_send_json_success( $result );
	}

	/**
	 * Single custom file restore AJAX.
	 *
	 * @return void
	 */
	public static function handle_restore_custom(): void {
		check_ajax_referer( 'ha_io_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Berechtigung.', 'ha-image-optimizer' ) ], 403 );
		}

		$file = isset( $_POST['file'] ) ? sanitize_text_field( (string) $_POST['file'] ) : '';
		if ( empty( $file ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Keine Datei angegeben.', 'ha-image-optimizer' ) ], 400 );
		}

		if ( CustomFolderProcessor::restore_file( $file ) ) {
			wp_send_json_success( [
				'message' => esc_html__( 'Datei erfolgreich aus Backup wiederhergestellt.', 'ha-image-optimizer' ),
			] );
		} else {
			wp_send_json_error( [
				'message' => esc_html__( 'Wiederherstellung fehlgeschlagen (kein Backup vorhanden).', 'ha-image-optimizer' ),
			], 500 );
		}
	}
}

