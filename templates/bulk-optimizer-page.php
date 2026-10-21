<?php
/**
 * Template: Bulk Optimizer Page.
 *
 * @package HA_Image_Optimizer
 *
 * @var array<string, mixed> $stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap ha-io-wrap">
	<h1>⚡ <?php esc_html_e( 'HA Image Optimizer: Massenoptimierung (Bulk)', 'ha-image-optimizer' ); ?></h1>

	<div class="ha-io-stats-overview">
		<div class="ha-io-stat-box">
			<span class="ha-io-stat-number" id="ha-io-stat-total"><?php echo esc_html( $stats['total_images'] ); ?></span>
			<span class="ha-io-stat-label"><?php esc_html_e( 'Bilder Gesamt', 'ha-image-optimizer' ); ?></span>
		</div>
		<div class="ha-io-stat-box ha-io-stat-box-green">
			<span class="ha-io-stat-number" id="ha-io-stat-optimized"><?php echo esc_html( $stats['optimized_images'] ); ?></span>
			<span class="ha-io-stat-label"><?php esc_html_e( 'Bereits Optimiert', 'ha-image-optimizer' ); ?></span>
		</div>
		<div class="ha-io-stat-box ha-io-stat-box-yellow">
			<span class="ha-io-stat-number" id="ha-io-stat-unoptimized"><?php echo esc_html( $stats['unoptimized'] ); ?></span>
			<span class="ha-io-stat-label"><?php esc_html_e( 'Noch Unoptimiert', 'ha-image-optimizer' ); ?></span>
		</div>
		<div class="ha-io-stat-box ha-io-stat-box-blue">
			<span class="ha-io-stat-number" id="ha-io-stat-saved"><?php echo esc_html( size_format( $stats['saved_bytes'] ) ); ?></span>
			<span class="ha-io-stat-label">
				<?php esc_html_e( 'Gesparter Speicherplatz', 'ha-image-optimizer' ); ?>
				(-<span id="ha-io-stat-percent"><?php echo esc_html( $stats['saved_percent'] ); ?></span>%)
			</span>
		</div>
	</div>

	<div class="ha-io-card ha-io-bulk-card">
		<h2>🚀 <?php esc_html_e( 'Massenverarbeitung starten', 'ha-image-optimizer' ); ?></h2>
		<p>
			<?php esc_html_e( 'Die Massenoptimierung verarbeitet deine gesamte Mediathek lokal im Hintergrund. Für jedes Bild wird das Original verlustfrei optimiert, alle Zwischengrößen komprimiert und moderne WebP/AVIF-Versionen erzeugt.', 'ha-image-optimizer' ); ?>
		</p>

		<div class="ha-io-bulk-actions">
			<button type="button" class="button button-primary button-hero" id="ha-io-btn-start-bulk" <?php disabled( 0 === $stats['unoptimized'] ); ?>>
				⚡ <?php esc_html_e( 'Unoptimierte Bilder jetzt verarbeiten', 'ha-image-optimizer' ); ?>
			</button>

			<button type="button" class="button button-secondary" id="ha-io-btn-start-bulk-all">
				🔄 <?php esc_html_e( 'Alle Bilder erneut optimieren (Force All)', 'ha-image-optimizer' ); ?>
			</button>

			<button type="button" class="button" id="ha-io-btn-pause-bulk" style="display: none;">
				⏸️ <?php esc_html_e( 'Pausieren', 'ha-image-optimizer' ); ?>
			</button>
		</div>

		<!-- Progress Container -->
		<div id="ha-io-progress-container" style="display: none; margin-top: 25px;">
			<div class="ha-io-progress-bar-bg">
				<div class="ha-io-progress-bar-fill" id="ha-io-progress-bar" style="width: 0%;"></div>
			</div>
			<div class="ha-io-progress-info">
				<span id="ha-io-progress-status"><?php esc_html_e( 'Bereite Queue vor...', 'ha-image-optimizer' ); ?></span>
				<span id="ha-io-progress-counts">0 / 0</span>
			</div>
		</div>

		<!-- Live Log -->
		<div id="ha-io-log-container" style="display: none; margin-top: 20px;">
			<h3><?php esc_html_e( 'Aktivitäts-Protokoll', 'ha-image-optimizer' ); ?></h3>
			<ul class="ha-io-log-list" id="ha-io-log-list"></ul>
		</div>
	</div>
</div>
