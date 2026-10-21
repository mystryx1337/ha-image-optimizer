<?php
/**
 * Media Library Column & Attachment Detail Box.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Admin;

use ha\ImageOptimizer\Media\BackupManager;
use ha\ImageOptimizer\Media\AttachmentProcessor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MediaColumn
 */
class MediaColumn {

	/**
	 * Register media library hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'manage_upload_columns', [ __CLASS__, 'add_columns' ] );
		add_action( 'manage_media_custom_column', [ __CLASS__, 'render_column_content' ], 10, 2 );
		add_filter( 'attachment_fields_to_edit', [ __CLASS__, 'add_attachment_fields' ], 10, 2 );
	}

	/**
	 * Add "HA Optimizer" column header.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function add_columns( array $columns ): array {
		$columns['ha_io_optimization'] = esc_html__( 'HA Optimizer', 'ha-image-optimizer' );
		return $columns;
	}

	/**
	 * Render custom column cell content.
	 *
	 * @param string $column_name Column key.
	 * @param int    $post_id Attachment ID.
	 * @return void
	 */
	public static function render_column_content( string $column_name, int $post_id ): void {
		if ( 'ha_io_optimization' !== $column_name ) {
			return;
		}

		$mime = get_post_mime_type( $post_id );
		if ( ! in_array( $mime, AttachmentProcessor::SUPPORTED_MIMES, true ) ) {
			echo '<span class="ha-io-na">—</span>';
			return;
		}

		$data = get_post_meta( $post_id, '_ha_io_data', true );

		if ( empty( $data ) || ! is_array( $data ) ) {
			?>
			<div class="ha-io-col-unoptimized">
				<span class="ha-io-badge ha-io-badge-gray"><?php esc_html_e( 'Unoptimiert', 'ha-image-optimizer' ); ?></span>
				<div class="ha-io-actions">
					<button type="button" class="button button-small ha-io-btn-optimize" data-id="<?php echo esc_attr( $post_id ); ?>">
						<?php esc_html_e( 'Optimieren', 'ha-image-optimizer' ); ?>
					</button>
				</div>
			</div>
			<?php
			return;
		}

		$percent     = (float) ( $data['total']['percent'] ?? 0.0 );
		$saved_bytes = (int) ( $data['total']['saved'] ?? 0 );
		$has_backup  = BackupManager::has_backup( $post_id );
		$has_webp    = ! empty( $data['original']['webp_size'] );
		$has_avif    = ! empty( $data['original']['avif_size'] );

		?>
		<div class="ha-io-col-optimized" data-id="<?php echo esc_attr( $post_id ); ?>">
			<div class="ha-io-badge-group">
				<span class="ha-io-badge ha-io-badge-green">
					-<?php echo esc_html( number_format_i18n( $percent, 1 ) ); ?>%
					<span class="ha-io-bytes">(<?php echo esc_html( size_format( $saved_bytes ) ); ?>)</span>
				</span>
				<?php if ( $has_webp ) : ?>
					<span class="ha-io-badge ha-io-badge-blue">WebP</span>
				<?php endif; ?>
				<?php if ( $has_avif ) : ?>
					<span class="ha-io-badge ha-io-badge-purple">AVIF</span>
				<?php endif; ?>
			</div>

			<div class="ha-io-actions">
				<a href="#" class="ha-io-btn-optimize" data-id="<?php echo esc_attr( $post_id ); ?>">
					<?php esc_html_e( 'Neu optimieren', 'ha-image-optimizer' ); ?>
				</a>
				<?php if ( $has_backup ) : ?>
					| <a href="#" class="ha-io-btn-restore" data-id="<?php echo esc_attr( $post_id ); ?>">
						<?php esc_html_e( 'Wiederherstellen', 'ha-image-optimizer' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Add Vorher-Nachher detail section to the edit attachment view.
	 *
	 * @param array<string, mixed> $form_fields Form fields.
	 * @param \WP_Post             $post Attachment post object.
	 * @return array<string, mixed>
	 */
	public static function add_attachment_fields( array $form_fields, \WP_Post $post ): array {
		$mime = get_post_mime_type( $post->ID );
		if ( ! in_array( $mime, AttachmentProcessor::SUPPORTED_MIMES, true ) ) {
			return $form_fields;
		}

		$data       = get_post_meta( $post->ID, '_ha_io_data', true );
		$has_backup = BackupManager::has_backup( $post->ID );

		ob_start();
		$template_path = HA_IO_PATH . 'templates/media-details-meta-box.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
		$html = ob_get_clean();

		$form_fields['ha_io_details'] = [
			'label' => esc_html__( 'HA Optimizer Details', 'ha-image-optimizer' ),
			'input' => 'html',
			'html'  => $html,
		];

		return $form_fields;
	}
}
