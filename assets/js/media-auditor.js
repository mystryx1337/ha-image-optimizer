/**
 * HA Image Optimizer - Media Auditor Frontend Runner.
 */

(function ($) {
	'use strict';

	$(function () {
		let auditQueue = [];
		let auditTotal = 0;
		let auditProcessed = 0;
		let isRunning = false;
		const batchSize = 15; // 15 attachments per batch query

		// -------------------------------------------------------------
		// 1. Start Audit Scan
		// -------------------------------------------------------------
		$('#ha-io-btn-start-audit').on('click', function () {
			if (isRunning) return;
			startAudit();
		});

		function startAudit() {
			isRunning = true;
			$('#ha-io-btn-start-audit, #ha-io-btn-clear-audit').prop('disabled', true);
			$('#ha-io-audit-progress-container').slideDown();
			$('#ha-io-audit-progress-bar').css('width', '0%');
			$('#ha-io-audit-progress-counts').text('0 / 0 (0%)');
			$('#ha-io-audit-current-item').text(ha_io_audit_vars.i18n.scanning || 'Scanne Mediathek...');

			$.ajax({
				url: ha_io_audit_vars.ajax_url,
				method: 'POST',
				data: {
					action: 'ha_io_audit_get_queue',
					nonce: ha_io_audit_vars.nonce
				},
				success: function (res) {
					if (res.success && res.data.queue) {
						auditQueue = res.data.queue;
						auditTotal = auditQueue.length;
						auditProcessed = 0;

						if (auditTotal === 0) {
							$('#ha-io-audit-progress-status').text('Keine Medien gefunden.');
							$('#ha-io-audit-progress-bar').css('width', '100%');
							finishAudit();
							return;
						}

						// If starting fresh scan, empty table
						$('#ha-io-audit-tbody').empty();
						processNextAuditBatch();
					} else {
						alert(res.data ? res.data.message : ha_io_audit_vars.i18n.error);
						finishAudit();
					}
				},
				error: function () {
					alert(ha_io_audit_vars.i18n.error);
					finishAudit();
				}
			});
		}

		function processNextAuditBatch() {
			if (auditQueue.length === 0) {
				finishAudit();
				return;
			}

			const chunk = auditQueue.splice(0, batchSize);
			$('#ha-io-audit-current-item').text('Prüfe IDs: ' + chunk.join(', '));

			$.ajax({
				url: ha_io_audit_vars.ajax_url,
				method: 'POST',
				data: {
					action: 'ha_io_audit_batch',
					nonce: ha_io_audit_vars.nonce,
					ids: chunk
				},
				success: function (res) {
					if (res.success && res.data.items) {
						auditProcessed += chunk.length;
						updateProgress();

						// Render items into table
						res.data.items.forEach(function (item) {
							renderAuditRow(item);
						});

						// Update metrics cards
						if (res.data.summary) {
							updateMetricCards(res.data.summary);
						}
					} else {
						auditProcessed += chunk.length;
						updateProgress();
					}

					processNextAuditBatch();
				},
				error: function () {
					auditProcessed += chunk.length;
					updateProgress();
					processNextAuditBatch();
				}
			});
		}

		function updateProgress() {
			const percent = auditTotal > 0 ? Math.min(100, Math.round((auditProcessed / auditTotal) * 100)) : 100;
			$('#ha-io-audit-progress-bar').css('width', percent + '%');
			$('#ha-io-audit-progress-counts').text(auditProcessed + ' / ' + auditTotal + ' (' + percent + '%)');
		}

		function finishAudit() {
			isRunning = false;
			$('#ha-io-btn-start-audit, #ha-io-btn-clear-audit').prop('disabled', false);
			$('#ha-io-audit-progress-status').text('🎉 ' + (ha_io_audit_vars.i18n.completed || 'Scan abgeschlossen!'));
			$('#ha-io-audit-progress-bar').css('width', '100%');
			$('#ha-io-audit-current-item').text('Alle ' + auditTotal + ' Medien wurden erfolgreich geprüft.');
			applyFilters();
		}

		function updateMetricCards(summary) {
			const counts = summary.counts || {};
			const reclaimable = summary.reclaimable_bytes || {};

			$('#ha-io-audit-stat-total').text(counts.total || 0);
			$('#ha-io-audit-stat-unattached').text(counts.unattached_unused || 0);
			$('#ha-io-audit-stat-attached').text(counts.attached_unused || 0);
			$('#ha-io-audit-stat-used').text(counts.used || 0);

			$('#ha-io-audit-stat-unattached-bytes').text(formatBytes(reclaimable.unattached || 0));
			$('#ha-io-audit-stat-attached-bytes').text(formatBytes(reclaimable.attached || 0));
			$('#ha-io-audit-stat-reclaimable').text(formatBytes(reclaimable.total || 0));

			$('.count-all').text(counts.total || 0);
			$('.count-unattached').text(counts.unattached_unused || 0);
			$('.count-attached').text(counts.attached_unused || 0);
			$('.count-used').text(counts.used || 0);
		}

		function renderAuditRow(item) {
			// Remove existing row with same ID if exists
			$('#ha-io-audit-tbody tr[data-id="' + item.id + '"]').remove();

			const status = item.status || 'unattached_unused';
			let badgeClass = 'ha-io-badge-gray';
			let badgeText = 'Unbekannt';

			if (status === 'used') {
				badgeClass = 'ha-io-badge-green';
				badgeText = '🟢 Aktiv verwendet';
			} else if (status === 'attached_unused') {
				badgeClass = 'ha-io-badge-yellow';
				badgeText = '🟡 Zugeordnet, inaktiv';
			} else if (status === 'unattached_unused') {
				badgeClass = 'ha-io-badge-red';
				badgeText = '🔴 Vollständig verwaist';
			}

			let refHtml = '';
			if (item.references && item.references.length) {
				refHtml = '<ul style="margin: 0; padding-left: 18px; font-size: 12px; color: #1a7f37;">';
				item.references.forEach(function (ref) {
					refHtml += '<li>' + escapeHtml(ref) + '</li>';
				});
				refHtml += '</ul>';
			} else if (item.parent_title) {
				refHtml = '<span style="font-size: 12px; color: #9a6700;">Zugeordnet zu: "' + escapeHtml(item.parent_title) + '" (#' + item.parent_id + '), aber im Text oder Beitragsbild nicht auffindbar.</span>';
			} else {
				refHtml = '<span style="font-size: 12px; color: #cf222e;"><em>Keine Verwendung oder Zuordnung gefunden. Kann sicher bereinigt werden.</em></span>';
			}

			let actionBtn = '';
			if (status !== 'used') {
				actionBtn = '<button type="button" class="button button-small button-link-delete ha-io-btn-trash-single" data-id="' + item.id + '">🗑️ Papierkorb</button>';
			} else {
				actionBtn = '<a href="post.php?post=' + item.id + '&action=edit" class="button button-small" target="_blank">Ansehen</a>';
			}

			const cbHtml = (status !== 'used') ? '<input type="checkbox" class="ha-io-audit-cb" value="' + item.id + '">' : '';
			const thumbImg = item.thumb_url
				? '<img src="' + escapeHtml(item.thumb_url) + '" alt="" style="width: 48px; height: 48px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">'
				: '<div style="width: 48px; height: 48px; background: #eee; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999;">—</div>';

			const rowHtml = '<tr data-id="' + item.id + '" data-status="' + status + '">' +
				'<th scope="row" class="check-column">' + cbHtml + '</th>' +
				'<td>' + thumbImg + '</td>' +
				'<td><strong><a href="post.php?post=' + item.id + '&action=edit" target="_blank">' + escapeHtml(item.filename) + '</a></strong>' +
				'<div style="font-size: 11px; color: #666; margin-top: 3px;">ID #' + item.id + ' &bull; ' + escapeHtml(item.date || '') + '</div></td>' +
				'<td><strong>' + escapeHtml(item.disk_human || '0 B') + '</strong><div style="font-size: 11px; color: #666;">Inkl. WebP/AVIF</div></td>' +
				'<td><span class="ha-io-badge ' + badgeClass + '">' + badgeText + '</span></td>' +
				'<td>' + refHtml + '</td>' +
				'<td style="text-align: right;">' + actionBtn + '</td>' +
				'</tr>';

			$('#ha-io-audit-tbody').prepend(rowHtml);
		}

		// -------------------------------------------------------------
		// 2. Filter Pills & Search
		// -------------------------------------------------------------
		$('#ha-io-audit-filters').on('click', '.ha-io-filter-btn', function () {
			$('#ha-io-audit-filters .ha-io-filter-btn').removeClass('active');
			$(this).addClass('active');
			applyFilters();
		});

		$('#ha-io-audit-search').on('input', function () {
			applyFilters();
		});

		function applyFilters() {
			const activeFilter = $('#ha-io-audit-filters .ha-io-filter-btn.active').data('filter') || 'all';
			const search = ($('#ha-io-audit-search').val() || '').toLowerCase().trim();

			$('#ha-io-audit-tbody tr').each(function () {
				const $row = $(this);
				const status = $row.data('status');
				const text = $row.text().toLowerCase();

				let matchesFilter = (activeFilter === 'all') || (status === activeFilter);
				let matchesSearch = (search === '') || (text.indexOf(search) !== -1);

				if (matchesFilter && matchesSearch) {
					$row.show();
				} else {
					$row.hide();
				}
			});

			updateBulkTrashButton();
		}

		// -------------------------------------------------------------
		// 3. Checkboxes & Bulk Trashing
		// -------------------------------------------------------------
		$('#ha-io-check-all').on('change', function () {
			const checked = $(this).prop('checked');
			$('#ha-io-audit-tbody tr:visible .ha-io-audit-cb').prop('checked', checked);
			updateBulkTrashButton();
		});

		$(document).on('change', '.ha-io-audit-cb', function () {
			updateBulkTrashButton();
		});

		function updateBulkTrashButton() {
			const count = $('#ha-io-audit-tbody .ha-io-audit-cb:checked').length;
			const $btn = $('#ha-io-btn-bulk-trash');
			if (count > 0) {
				$btn.prop('disabled', false).text('🗑️ ' + count + ' ausgewählte in Papierkorb');
			} else {
				$btn.prop('disabled', true).text('🗑️ Ausgewählte in Papierkorb');
			}
		}

		// Single trash action
		$(document).on('click', '.ha-io-btn-trash-single', function (e) {
			e.preventDefault();
			const $btn = $(this);
			const attachmentId = $btn.data('id');
			if (!attachmentId) return;

			if (!confirm(ha_io_audit_vars.i18n.confirm_trash || 'Dieses Bild wirklich in den Papierkorb verschieben?')) {
				return;
			}

			$btn.prop('disabled', true).text('Verschiebe...');

			$.ajax({
				url: ha_io_audit_vars.ajax_url,
				method: 'POST',
				data: {
					action: 'ha_io_audit_trash_single',
					nonce: ha_io_audit_vars.nonce,
					attachment_id: attachmentId,
					force_delete: 0
				},
				success: function (res) {
					if (res.success) {
						const $row = $('tr[data-id="' + attachmentId + '"]');
						$row.css('background', '#ffebe9').fadeOut(400, function () {
							$(this).remove();
							applyFilters();
						});
					} else {
						alert(res.data ? res.data.message : ha_io_audit_vars.i18n.error);
						$btn.prop('disabled', false).text('🗑️ Papierkorb');
					}
				},
				error: function () {
					alert(ha_io_audit_vars.i18n.error);
					$btn.prop('disabled', false).text('🗑️ Papierkorb');
				}
			});
		});

		// Bulk trash action
		$('#ha-io-btn-bulk-trash').on('click', function () {
			const $checked = $('#ha-io-audit-tbody .ha-io-audit-cb:checked');
			const ids = [];
			$checked.each(function () {
				ids.push(parseInt($(this).val(), 10));
			});

			if (!ids.length) return;

			if (!confirm(ha_io_audit_vars.i18n.confirm_bulk || 'Alle ausgewählten Bilder wirklich in den Papierkorb verschieben?')) {
				return;
			}

			const $btn = $(this);
			$btn.prop('disabled', true).text('Verschiebe ' + ids.length + ' Bilder...');

			let completed = 0;
			function trashNext() {
				if (ids.length === 0) {
					$btn.prop('disabled', true).text('🗑️ Ausgewählte in Papierkorb');
					applyFilters();
					return;
				}

				const id = ids.shift();
				$.ajax({
					url: ha_io_audit_vars.ajax_url,
					method: 'POST',
					data: {
						action: 'ha_io_audit_trash_single',
						nonce: ha_io_audit_vars.nonce,
						attachment_id: id,
						force_delete: 0
					},
					complete: function () {
						completed++;
						$('tr[data-id="' + id + '"]').remove();
						$btn.text('Verschiebe (' + completed + ' fertig)...');
						trashNext();
					}
				});
			}

			trashNext();
		});

		// -------------------------------------------------------------
		// 4. Clear Cache
		// -------------------------------------------------------------
		$('#ha-io-btn-clear-audit').on('click', function () {
			if (!confirm('Möchtest du den aktuellen Audit-Cache wirklich leeren?')) {
				return;
			}

			$.ajax({
				url: ha_io_audit_vars.ajax_url,
				method: 'POST',
				data: {
					action: 'ha_io_audit_clear',
					nonce: ha_io_audit_vars.nonce
				},
				success: function () {
					location.reload();
				}
			});
		});

		function formatBytes(bytes) {
			if (bytes === 0) return '0 B';
			const k = 1024;
			const sizes = ['B', 'KB', 'MB', 'GB'];
			const i = Math.floor(Math.log(bytes) / Math.log(k));
			return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
		}

		function escapeHtml(str) {
			return $('<div>').text(str).html();
		}
	});
})(jQuery);
