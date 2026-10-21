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
	<!-- Navigation Tabs Header -->
	<nav class="nav-tab-wrapper ha-io-nav-tabs">
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ha-image-optimizer' ) ); ?>" class="nav-tab">
			⚙️ <?php esc_html_e( 'Einstellungen', 'ha-image-optimizer' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'upload.php?page=ha-image-optimizer-bulk' ) ); ?>" class="nav-tab nav-tab-active">
			⚡ <?php esc_html_e( 'Massenoptimierung', 'ha-image-optimizer' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'upload.php?page=ha-image-optimizer-auditor' ) ); ?>" class="nav-tab">
			🔍 <?php esc_html_e( 'Verwaiste Medien (Auditor)', 'ha-image-optimizer' ); ?>
		</a>
	</nav>

	<h1>⚡ <?php esc_html_e( 'HA Image Optimizer: Massenoptimierung (Bulk)', 'ha-image-optimizer' ); ?></h1>

	<div class="ha-io-stats-overview">
		<div class="ha-io-stat-box">
			<span class="ha-io-stat-number" id="ha-io-stat-total"><?php echo esc_html( $stats['total_images'] ); ?></span>
			<span class="ha-io-stat-label"><?php esc_html_e( 'Mediathek Gesamt', 'ha-image-optimizer' ); ?></span>
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

	<?php if ( ! empty( $custom_stats['total_images'] ) ) : ?>
		<div class="ha-io-custom-stats-bar">
			<span class="ha-io-badge ha-io-badge-purple">🎨 <?php esc_html_e( 'Theme- & Benutzerdefinierte Ordner:', 'ha-image-optimizer' ); ?></span>
			<strong><?php echo esc_html( $custom_stats['total_images'] ); ?></strong> <?php esc_html_e( 'Dateien gefunden', 'ha-image-optimizer' ); ?> &bull;
			<strong><?php echo esc_html( $custom_stats['optimized_images'] ); ?></strong> <?php esc_html_e( 'optimiert', 'ha-image-optimizer' ); ?> &bull;
			<strong><?php echo esc_html( $custom_stats['unoptimized'] ); ?></strong> <?php esc_html_e( 'noch offen', 'ha-image-optimizer' ); ?> &bull;
			<strong><?php echo esc_html( size_format( $custom_stats['saved_bytes'] ) ); ?></strong> <?php esc_html_e( 'gespart', 'ha-image-optimizer' ); ?>
		</div>
	<?php endif; ?>

	<!-- Main Execution Card -->
	<div class="ha-io-card ha-io-bulk-card">
		<h2>🚀 <?php esc_html_e( 'Massenverarbeitung starten', 'ha-image-optimizer' ); ?></h2>
		<p>
			<?php esc_html_e( 'Die Massenoptimierung verarbeitet deine gesamte Mediathek oder statische Theme-Bilder lokal im Hintergrund. Für jedes Bild wird das Original verlustfrei optimiert, alle Zwischengrößen komprimiert und moderne WebP/AVIF-Versionen erzeugt.', 'ha-image-optimizer' ); ?>
		</p>

		<!-- Visual Stepper Bar -->
		<div class="ha-io-stepper" id="ha-io-stepper">
			<div class="ha-io-step" id="ha-io-step-1">
				<span class="ha-io-step-num">1</span>
				<span class="ha-io-step-label"><?php esc_html_e( 'Dateien scannen', 'ha-image-optimizer' ); ?></span>
			</div>
			<div class="ha-io-step-connector"></div>
			<div class="ha-io-step" id="ha-io-step-2">
				<span class="ha-io-step-num">2</span>
				<span class="ha-io-step-label"><?php esc_html_e( 'Original komprimieren', 'ha-image-optimizer' ); ?></span>
			</div>
			<div class="ha-io-step-connector"></div>
			<div class="ha-io-step" id="ha-io-step-3">
				<span class="ha-io-step-num">3</span>
				<span class="ha-io-step-label"><?php esc_html_e( 'WebP & AVIF erzeugen', 'ha-image-optimizer' ); ?></span>
			</div>
			<div class="ha-io-step-connector"></div>
			<div class="ha-io-step" id="ha-io-step-4">
				<span class="ha-io-step-num">4</span>
				<span class="ha-io-step-label"><?php esc_html_e( 'Abgeschlossen', 'ha-image-optimizer' ); ?></span>
			</div>
		</div>

		<!-- Action Buttons -->
		<div class="ha-io-bulk-actions">
			<button type="button" class="button button-primary button-hero ha-io-btn-hero" id="ha-io-btn-start-bulk" <?php disabled( 0 === $stats['unoptimized'] ); ?>>
				⚡ <?php esc_html_e( 'Mediathek optimieren', 'ha-image-optimizer' ); ?>
			</button>

			<?php if ( ! empty( $custom_stats['total_images'] ) ) : ?>
				<button type="button" class="button button-primary ha-io-btn-hero" id="ha-io-btn-start-custom" <?php disabled( 0 === $custom_stats['unoptimized'] ); ?>>
					🎨 <?php esc_html_e( 'Theme-Ordner optimieren', 'ha-image-optimizer' ); ?> (<?php echo esc_html( $custom_stats['unoptimized'] ); ?>)
				</button>
			<?php endif; ?>

			<button type="button" class="button button-secondary" id="ha-io-btn-start-bulk-all">
				🔄 <?php esc_html_e( 'Mediathek Force All', 'ha-image-optimizer' ); ?>
			</button>

			<button type="button" class="button" id="ha-io-btn-pause-bulk" style="display: none;">
				⏸️ <?php esc_html_e( 'Pausieren', 'ha-image-optimizer' ); ?>
			</button>
		</div>

		<!-- Live Current Action Display -->
		<div class="ha-io-live-action-box" id="ha-io-live-action-box" style="display: none;">
			<div class="ha-io-live-action-header">
				<span class="ha-io-pulse-indicator"></span>
				<div class="ha-io-live-action-text-group">
					<strong class="ha-io-live-title" id="ha-io-current-action-title">
						<?php esc_html_e( 'Bereite Queue vor...', 'ha-image-optimizer' ); ?>
					</strong>
					<span class="ha-io-live-desc" id="ha-io-current-action-desc">
						<?php esc_html_e( 'Durchsuche die Datenbank nach Bildern...', 'ha-image-optimizer' ); ?>
					</span>
				</div>
			</div>
			<div class="ha-io-current-file-badge" id="ha-io-current-file-badge" style="display: none;">
				<span class="ha-io-cf-label"><?php esc_html_e( 'Aktuelle Datei:', 'ha-image-optimizer' ); ?></span>
				<code id="ha-io-current-filename">—</code>
			</div>
		</div>

		<!-- Animated Progress Bar -->
		<div id="ha-io-progress-container" style="display: none;">
			<div class="ha-io-progress-bar-bg">
				<div class="ha-io-progress-bar-fill" id="ha-io-progress-bar" style="width: 0%;"></div>
			</div>
			<div class="ha-io-progress-info">
				<span id="ha-io-progress-status"><?php esc_html_e( 'Verarbeite Bilder...', 'ha-image-optimizer' ); ?></span>
				<span id="ha-io-progress-counts">0 / 0 (0%)</span>
			</div>
		</div>

		<!-- Collapsible History Dropdown of processed files -->
		<details class="ha-io-history-dropdown" id="ha-io-history-dropdown" open style="display: none;">
			<summary class="ha-io-history-summary">
				<div class="ha-io-history-summary-left">
					<span class="ha-io-history-icon">📋</span>
					<strong><?php esc_html_e( 'Bereits verarbeitete Bilder in diesem Durchlauf', 'ha-image-optimizer' ); ?></strong>
					<span class="ha-io-badge ha-io-badge-green" id="ha-io-history-count">0</span>
				</div>
				<span class="ha-io-history-arrow">▾</span>
			</summary>

			<div class="ha-io-history-content">
				<table class="ha-io-history-table widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Dateiname', 'ha-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Vorher', 'ha-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Nachher', 'ha-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Ersparnis', 'ha-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Next-Gen', 'ha-image-optimizer' ); ?></th>
						</tr>
					</thead>
					<tbody id="ha-io-history-tbody">
						<!-- Dynamically appended by JavaScript -->
					</tbody>
				</table>
			</div>
		</details>

		<!-- Live Technical Log -->
		<details class="ha-io-log-details" id="ha-io-log-container" style="display: none;">
			<summary>
				<strong><?php esc_html_e( 'Detailliertes System-Protokoll anzeigen', 'ha-image-optimizer' ); ?></strong>
			</summary>
			<ul class="ha-io-log-list" id="ha-io-log-list"></ul>
		</details>
	</div>
</div>
