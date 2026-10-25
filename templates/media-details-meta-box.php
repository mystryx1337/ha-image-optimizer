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

use ha\ImageOptimizer\Common\CompressionLevel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$custom_level = (string) get_post_meta( $post->ID, '_ha_io_custom_level', true );
if ( empty( $custom_level ) && ! empty( $data['custom_level'] ) ) {
	$custom_level = (string) $data['custom_level'];
}
$custom_enum = CompressionLevel::fromString( $custom_level );

if ( empty( $data ) || ! is_array( $data ) ) : ?>
	<div class="ha-io-meta-card ha-io-meta-unoptimized" data-id="<?php echo esc_attr( $post->ID ); ?>">
		<p style="margin-top: 0;"><?php esc_html_e( 'Dieses Bild wurde noch nicht optimiert.', 'ha-image-optimizer' ); ?></p>
		<div class="ha-io-meta-level-choice" style="margin-bottom: 12px;">
			<label for="ha-io-meta-level-<?php echo esc_attr( $post->ID ); ?>" style="display:block; font-weight: 600; margin-bottom: 6px;">
				<?php esc_html_e( 'Komprimierung für dieses Bild:', 'ha-image-optimizer' ); ?>
			</label>
			<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
				<select id="ha-io-meta-level-<?php echo esc_attr( $post->ID ); ?>" class="ha-io-level-select" data-id="<?php echo esc_attr( $post->ID ); ?>" style="max-width: 280px; height: 32px; font-size: 13px;">
					<option value="default"><?php esc_html_e( 'Standard (Globale Einstellung)', 'ha-image-optimizer' ); ?></option>
					<option value="ultra"><?php esc_html_e( 'Hoch (Maximal - ca. 72% Qualität)', 'ha-image-optimizer' ); ?></option>
					<option value="smart"><?php esc_html_e( 'Mittel (Ausgewogen - ca. 82% Qualität)', 'ha-image-optimizer' ); ?></option>
					<option value="lossless"><?php esc_html_e( 'Niedrig (Verlustfrei - 100% Schärfe)', 'ha-image-optimizer' ); ?></option>
				</select>
				<button type="button" class="button button-primary ha-io-btn-optimize" data-id="<?php echo esc_attr( $post->ID ); ?>">
					⚡ <?php esc_html_e( 'Jetzt optimieren', 'ha-image-optimizer' ); ?>
				</button>
			</div>
		</div>
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
			<?php if ( $custom_enum ) : ?>
				<span class="ha-io-badge ha-io-badge-orange" title="<?php echo esc_attr( $custom_enum->selection_label() ); ?>">
					<?php echo esc_html( $custom_enum->badge_label() ); ?>
				</span>
			<?php else : ?>
				<span class="ha-io-badge ha-io-badge-gray" title="<?php esc_attr_e( 'Verwendet globale Standardeinstellungen', 'ha-image-optimizer' ); ?>">
					<?php esc_html_e( 'Standard-Stufe', 'ha-image-optimizer' ); ?>
				</span>
			<?php endif; ?>
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

	<div class="ha-io-meta-level-choice" style="margin: 14px 0; padding: 12px; background: #fff; border: 1px solid #dcdcde; border-radius: 6px;">
		<label for="ha-io-meta-level-<?php echo esc_attr( $post->ID ); ?>" style="display:block; font-weight: 600; margin-bottom: 6px;">
			<?php esc_html_e( 'Individuelle Komprimierungsstufe ändern:', 'ha-image-optimizer' ); ?>
		</label>
		<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
			<select id="ha-io-meta-level-<?php echo esc_attr( $post->ID ); ?>" class="ha-io-level-select" data-id="<?php echo esc_attr( $post->ID ); ?>" style="max-width: 280px; height: 32px; font-size: 13px;">
				<option value="default" <?php selected( empty( $custom_level ) ); ?>><?php esc_html_e( 'Standard (Globale Einstellung)', 'ha-image-optimizer' ); ?></option>
				<option value="ultra" <?php selected( $custom_level, 'ultra' ); ?>><?php esc_html_e( 'Hoch (Maximal - ca. 72% Qualität)', 'ha-image-optimizer' ); ?></option>
				<option value="smart" <?php selected( $custom_level, 'smart' ); ?>><?php esc_html_e( 'Mittel (Ausgewogen - ca. 82% Qualität)', 'ha-image-optimizer' ); ?></option>
				<option value="lossless" <?php selected( $custom_level, 'lossless' ); ?>><?php esc_html_e( 'Niedrig (Verlustfrei - 100% Schärfe)', 'ha-image-optimizer' ); ?></option>
			</select>
			<button type="button" class="button button-primary ha-io-btn-optimize" data-id="<?php echo esc_attr( $post->ID ); ?>">
				🔄 <?php esc_html_e( 'Erneut optimieren', 'ha-image-optimizer' ); ?>
			</button>
		</div>
		<p class="description" style="margin-top: 6px; margin-bottom: 0;">
			<?php
			if ( $custom_enum ) {
				printf(
					/* translators: %s: Level label */
					esc_html__( 'Aktuell aktiv: %s. Beim erneuten Optimieren wird das Bild aus dem Original-Backup verlustfrei mit dieser Stufe neu berechnet.', 'ha-image-optimizer' ),
					'<strong>' . esc_html( $custom_enum->selection_label() ) . '</strong>'
				);
			} else {
				esc_html_e( 'Aktuell gilt die globale Standard-Einstellung. Wähle eine individuelle Stufe (Hoch, Mittel oder Niedrig), um dieses Bild gezielt abweichend zu komprimieren.', 'ha-image-optimizer' );
			}
			?>
		</p>
	</div>

	<div class="ha-io-meta-actions">
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
