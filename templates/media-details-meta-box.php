<?php
/**
 * Template: Media Details Vorher-Nachher Meta Box.
 *
 * @package HA_Image_Optimizer
 *
 * @var array<string, mixed>|false $data
 * @var bool                       $has_backup
 * @var \WP_Post                   $post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $data ) || ! is_array( $data ) ) : ?>
	<div class="ha-io-meta-card ha-io-meta-unoptimized">
		<p><?php esc_html_e( 'Dieses Bild wurde noch nicht optimiert.', 'ha-image-optimizer' ); ?></p>
		<button type="button" class="button button-primary ha-io-btn-optimize" data-id="<?php echo esc_attr( $post->ID ); ?>">
			<?php esc_html_e( 'Jetzt optimieren', 'ha-image-optimizer' ); ?>
		</button>
	</div>
	<?php
	return;
endif;

$total_before  = (int) ( $data['total']['before'] ?? 0 );
$total_after   = (int) ( $data['total']['after'] ?? 0 );
$saved_bytes   = (int) ( $data['total']['saved'] ?? 0 );
$saved_percent = (float) ( $data['total']['percent'] ?? 0.0 );
$orig          = $data['original'] ?? [];
$sizes         = $data['sizes'] ?? [];
$engine        = $data['engine'] ?? 'Lokal';
?>

<div class="ha-io-meta-card" data-id="<?php echo esc_attr( $post->ID ); ?>">
	<div class="ha-io-meta-header">
		<div class="ha-io-meta-title">
			<strong>⚡ HA Image Optimizer</strong>
			<span class="ha-io-badge ha-io-badge-engine"><?php echo esc_html( $engine ); ?></span>
		</div>
		<div class="ha-io-meta-summary-badge">
			-<?php echo esc_html( number_format_i18n( $saved_percent, 1 ) ); ?>%
			<span class="ha-io-bytes">(<?php echo esc_html( size_format( $saved_bytes ) ); ?> gespart)</span>
		</div>
	</div>

	<table class="ha-io-stats-table widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Bildgröße', 'ha-image-optimizer' ); ?></th>
				<th><?php esc_html_e( 'Vorher', 'ha-image-optimizer' ); ?></th>
				<th><?php esc_html_e( 'Nachher', 'ha-image-optimizer' ); ?></th>
				<th><?php esc_html_e( 'Ersparnis', 'ha-image-optimizer' ); ?></th>
				<th><?php esc_html_e( 'NextGen', 'ha-image-optimizer' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><strong><?php esc_html_e( 'Originalbild (Master)', 'ha-image-optimizer' ); ?></strong></td>
				<td><?php echo esc_html( size_format( (int) ( $orig['before'] ?? 0 ) ) ); ?></td>
				<td><?php echo esc_html( size_format( (int) ( $orig['after'] ?? 0 ) ) ); ?></td>
				<td>
					<span class="ha-io-text-green">
						-<?php echo esc_html( number_format_i18n( (float) ( $orig['percent'] ?? 0 ), 1 ) ); ?>%
					</span>
				</td>
				<td>
					<?php if ( ! empty( $orig['webp_size'] ) ) : ?>
						<span class="ha-io-pill">WebP: <?php echo esc_html( size_format( $orig['webp_size'] ) ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $orig['avif_size'] ) ) : ?>
						<span class="ha-io-pill">AVIF: <?php echo esc_html( size_format( $orig['avif_size'] ) ); ?></span>
					<?php endif; ?>
				</td>
			</tr>

			<?php foreach ( $sizes as $size_name => $s ) : ?>
				<tr>
					<td><code><?php echo esc_html( $size_name ); ?></code></td>
					<td><?php echo esc_html( size_format( (int) ( $s['before'] ?? 0 ) ) ); ?></td>
					<td><?php echo esc_html( size_format( (int) ( $s['after'] ?? 0 ) ) ); ?></td>
					<td>
						<span class="ha-io-text-green">
							-<?php echo esc_html( number_format_i18n( (float) ( $s['percent'] ?? 0 ), 1 ) ); ?>%
						</span>
					</td>
					<td>
						<?php if ( ! empty( $s['webp_size'] ) ) : ?>
							<span class="ha-io-pill">WebP: <?php echo esc_html( size_format( $s['webp_size'] ) ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $s['avif_size'] ) ) : ?>
							<span class="ha-io-pill">AVIF: <?php echo esc_html( size_format( $s['avif_size'] ) ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<div class="ha-io-meta-actions">
		<button type="button" class="button ha-io-btn-optimize" data-id="<?php echo esc_attr( $post->ID ); ?>">
			🔄 <?php esc_html_e( 'Erneut optimieren', 'ha-image-optimizer' ); ?>
		</button>

		<?php if ( $has_backup ) : ?>
			<button type="button" class="button ha-io-btn-restore" data-id="<?php echo esc_attr( $post->ID ); ?>">
				↩️ <?php esc_html_e( 'Original wiederherstellen', 'ha-image-optimizer' ); ?>
			</button>
			<span class="description ha-io-backup-note">
				🔒 <?php esc_html_e( 'Originalbild ist sicher im Backup gespeichert.', 'ha-image-optimizer' ); ?>
			</span>
		<?php else : ?>
			<span class="description ha-io-backup-none">
				⚠️ <?php esc_html_e( 'Kein Backup vorhanden.', 'ha-image-optimizer' ); ?>
			</span>
		<?php endif; ?>
	</div>
</div>
