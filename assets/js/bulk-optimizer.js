/**
 * HA Image Optimizer Frontend & Bulk Runner Script.
 */

(function ($) {
	'use strict';

	$(function () {
		// -------------------------------------------------------------
		// 1. Single Image Actions (Media Library & Detail Modal)
		// -------------------------------------------------------------
		$(document).on('click', '.ha-io-btn-optimize', function (e) {
			e.preventDefault();
			const $btn = $(this);
			const attachmentId = $btn.data('id');
			if (!attachmentId) return;

			const origText = $btn.text();
			$btn.prop('disabled', true).text(ha_io_vars.i18n.optimizing || 'Optimiere...');

			$.ajax({
				url: ha_io_vars.ajax_url,
				method: 'POST',
				data: {
					action: 'ha_io_optimize_single',
					nonce: ha_io_vars.nonce,
					attachment_id: attachmentId
				},
				success: function (res) {
					if (res.success) {
						location.reload();
					} else {
						alert(res.data.message || ha_io_vars.i18n.error);
						$btn.prop('disabled', false).text(origText);
					}
				},
				error: function () {
					alert(ha_io_vars.i18n.error);
					$btn.prop('disabled', false).text(origText);
				}
			});
		});

		$(document).on('click', '.ha-io-btn-restore', function (e) {
			e.preventDefault();
			const $btn = $(this);
			const attachmentId = $btn.data('id');
			if (!attachmentId) return;

			if (!confirm(ha_io_vars.i18n.confirm_rest || 'Original wiederherstellen?')) {
				return;
			}

			const origText = $btn.text();
			$btn.prop('disabled', true).text(ha_io_vars.i18n.restoring || 'Stelle wieder her...');

			$.ajax({
				url: ha_io_vars.ajax_url,
				method: 'POST',
				data: {
					action: 'ha_io_restore_single',
					nonce: ha_io_vars.nonce,
					attachment_id: attachmentId
				},
				success: function (res) {
					if (res.success) {
						location.reload();
					} else {
						alert(res.data.message || ha_io_vars.i18n.error);
						$btn.prop('disabled', false).text(origText);
					}
				},
				error: function () {
					alert(ha_io_vars.i18n.error);
					$btn.prop('disabled', false).text(origText);
				}
			});
		});

		// -------------------------------------------------------------
		// 2. Bulk Optimization Runner
		// -------------------------------------------------------------
		let bulkQueue = [];
		let bulkTotal = 0;
		let bulkProcessed = 0;
		let isPaused = false;
		let isRunning = false;
		let forceAll = false;
		const batchSize = 2; // Process 2 images per request to prevent PHP timeouts

		$('#ha-io-btn-start-bulk').on('click', function () {
			forceAll = false;
			startBulk();
		});

		$('#ha-io-btn-start-bulk-all').on('click', function () {
			forceAll = true;
			startBulk();
		});

		$('#ha-io-btn-pause-bulk').on('click', function () {
			if (!isPaused) {
				isPaused = true;
				$(this).text(ha_io_vars.i18n.resume || 'Fortsetzen');
				$('#ha-io-progress-status').text('Pausiert...');
			} else {
				isPaused = false;
				$(this).text(ha_io_vars.i18n.pause || 'Pausieren');
				$('#ha-io-progress-status').text('Verarbeite...');
				processNextChunk();
			}
		});

		function startBulk() {
			if (isRunning) return;
			isRunning = true;
			isPaused = false;

			$('#ha-io-btn-start-bulk, #ha-io-btn-start-bulk-all').prop('disabled', true);
			$('#ha-io-btn-pause-bulk').show().text(ha_io_vars.i18n.pause || 'Pausieren');
			$('#ha-io-progress-container, #ha-io-log-container').slideDown();
			$('#ha-io-progress-bar').css('width', '0%');
			$('#ha-io-progress-status').text('Ermittle Bild-Queue...');
			$('#ha-io-log-list').empty();

			// Fetch queue
			$.ajax({
				url: ha_io_vars.ajax_url,
				method: 'POST',
				data: {
					action: 'ha_io_get_bulk_queue',
					nonce: ha_io_vars.nonce,
					all: forceAll ? 1 : 0
				},
				success: function (res) {
					if (res.success && res.data.queue) {
						bulkQueue = res.data.queue;
						bulkTotal = bulkQueue.length;
						bulkProcessed = 0;

						if (bulkTotal === 0) {
							$('#ha-io-progress-status').text('Keine Bilder zum Optimieren gefunden.');
							finishBulk();
							return;
						}

						updateProgress();
						processNextChunk();
					} else {
						alert(res.data ? res.data.message : 'Fehler beim Laden der Queue.');
						finishBulk();
					}
				},
				error: function () {
					alert(ha_io_vars.i18n.error);
					finishBulk();
				}
			});
		}

		function processNextChunk() {
			if (isPaused || bulkQueue.length === 0) {
				if (bulkQueue.length === 0) {
					finishBulk();
				}
				return;
			}

			const chunk = bulkQueue.splice(0, batchSize);

			$.ajax({
				url: ha_io_vars.ajax_url,
				method: 'POST',
				data: {
					action: 'ha_io_bulk_batch',
					nonce: ha_io_vars.nonce,
					ids: chunk,
					force: forceAll ? 1 : 0
				},
				success: function (res) {
					if (res.success) {
						bulkProcessed += chunk.length;
						const saved = res.data.saved_bytes || 0;
						appendLog('Batch verarbeitet (' + chunk.length + ' Bilder). Gespart: ' + formatBytes(saved));
					} else {
						appendLog('Fehler bei Batch (' + chunk.join(',') + '): ' + (res.data ? res.data.message : 'Unbekannt'));
					}
					updateProgress();
					processNextChunk();
				},
				error: function () {
					appendLog('Server-Fehler bei Batch (' + chunk.join(',') + '). Fahre fort...');
					bulkProcessed += chunk.length;
					updateProgress();
					processNextChunk();
				}
			});
		}

		function updateProgress() {
			const percent = bulkTotal > 0 ? Math.min(100, Math.round((bulkProcessed / bulkTotal) * 100)) : 100;
			$('#ha-io-progress-bar').css('width', percent + '%');
			$('#ha-io-progress-counts').text(bulkProcessed + ' / ' + bulkTotal + ' (' + percent + '%)');
			$('#ha-io-progress-status').text('Verarbeite Bilder...');
		}

		function finishBulk() {
			isRunning = false;
			isPaused = false;
			$('#ha-io-btn-pause-bulk').hide();
			$('#ha-io-btn-start-bulk, #ha-io-btn-start-bulk-all').prop('disabled', false);
			$('#ha-io-progress-bar').css('width', '100%');
			$('#ha-io-progress-status').text(ha_io_vars.i18n.completed || 'Fertig!');
			appendLog('🎉 Optimierung vollständig abgeschlossen.');
		}

		function appendLog(text) {
			const time = new Date().toLocaleTimeString();
			const $item = $('<li>').text('[' + time + '] ' + text);
			$('#ha-io-log-list').prepend($item);
		}

		function formatBytes(bytes) {
			if (bytes === 0) return '0 B';
			const k = 1024;
			const sizes = ['B', 'KB', 'MB', 'GB'];
			const i = Math.floor(Math.log(bytes) / Math.log(k));
			return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
		}
	});
})(jQuery);
