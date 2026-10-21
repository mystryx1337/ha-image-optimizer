<?php
/**
 * Media Auditor - Detects orphan, unused, and attached inactive media.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Auditor;

use ha\ImageOptimizer\Common\Settings;
use ha\ImageOptimizer\Media\BackupManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MediaAuditor
 */
class MediaAuditor {

	/**
	 * Option key for caching audit results.
	 */
	public const OPTION_RESULTS = 'ha_io_audit_results';

	/**
	 * Status constants.
	 */
	public const STATUS_USED              = 'used';
	public const STATUS_ATTACHED_UNUSED   = 'attached_unused';
	public const STATUS_UNATTACHED_UNUSED = 'unattached_unused';

	/**
	 * Get list of all eligible media attachment IDs to audit.
	 *
	 * @return array<int>
	 */
	public static function get_audit_queue(): array {
		global $wpdb;

		$sql = "SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'attachment'
			AND post_mime_type LIKE 'image/%'
			AND post_status != 'trash'
			ORDER BY ID DESC";

		$results = $wpdb->get_col( $sql );
		return array_map( 'absint', (array) $results );
	}

	/**
	 * Audit a single attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, mixed>
	 */
	public static function audit_attachment( int $attachment_id ): array {
		global $wpdb;

		$attachment_id = absint( $attachment_id );
		$post          = get_post( $attachment_id );

		if ( ! $post || 'attachment' !== $post->post_type ) {
			return [
				'id'     => $attachment_id,
				'error'  => __( 'Attachment nicht gefunden.', 'ha-image-optimizer' ),
				'status' => self::STATUS_UNATTACHED_UNUSED,
			];
		}

		$file      = get_attached_file( $attachment_id );
		$filename  = $file ? wp_basename( $file ) : ( 'ID ' . $attachment_id );
		$parent_id = (int) $post->post_parent;
		$parent_title = '';

		if ( $parent_id > 0 ) {
			$parent_post = get_post( $parent_id );
			$parent_title = $parent_post ? get_the_title( $parent_id ) : '';
		}

		$thumb_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		if ( ! $thumb_url && function_exists( 'wp_get_attachment_url' ) ) {
			$thumb_url = wp_get_attachment_url( $attachment_id );
		}

		$disk_bytes  = self::calculate_disk_bytes( $attachment_id, $file );
		$references  = [];

		// -------------------------------------------------------------
		// 1. Featured Image (_thumbnail_id)
		// -------------------------------------------------------------
		$featured_sql = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_type FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON (pm.post_id = p.ID)
			WHERE pm.meta_key = '_thumbnail_id'
			AND pm.meta_value = %d
			AND p.post_status != 'trash'
			LIMIT 5",
			$attachment_id
		);
		$featured_posts = $wpdb->get_results( $featured_sql );

		if ( ! empty( $featured_posts ) ) {
			foreach ( $featured_posts as $fp ) {
				$title = $fp->post_title ? $fp->post_title : ( 'ID ' . $fp->ID );
				$references[] = sprintf(
					/* translators: 1: post title, 2: post ID, 3: post type */
					__( 'Beitragsbild in "%1$s" (#%2$d, %3$s)', 'ha-image-optimizer' ),
					$title,
					(int) $fp->ID,
					$fp->post_type
				);
			}
		}

		// -------------------------------------------------------------
		// 2. WooCommerce Product Gallery (_product_image_gallery)
		// -------------------------------------------------------------
		$id_str      = (string) $attachment_id;
		$gallery_sql = $wpdb->prepare(
			"SELECT p.ID, p.post_title FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON (pm.post_id = p.ID)
			WHERE pm.meta_key = '_product_image_gallery'
			AND (
				pm.meta_value = %s
				OR pm.meta_value LIKE %s
				OR pm.meta_value LIKE %s
				OR pm.meta_value LIKE %s
			)
			AND p.post_status != 'trash'
			LIMIT 5",
			$id_str,
			$id_str . ',%',
			'%,' . $id_str,
			'%,' . $id_str . ',%'
		);
		$gallery_posts = $wpdb->get_results( $gallery_sql );

		if ( ! empty( $gallery_posts ) ) {
			foreach ( $gallery_posts as $gp ) {
				$title = $gp->post_title ? $gp->post_title : ( 'ID ' . $gp->ID );
				$references[] = sprintf(
					/* translators: 1: product title, 2: product ID */
					__( 'WooCommerce Produktgalerie in "%1$s" (#%2$d)', 'ha-image-optimizer' ),
					$title,
					(int) $gp->ID
				);
			}
		}

		// -------------------------------------------------------------
		// 3. Site Identity & Options (Icon, Logo)
		// -------------------------------------------------------------
		$site_icon = (int) get_option( 'site_icon', 0 );
		if ( $site_icon === $attachment_id ) {
			$references[] = __( 'Website-Icon (Browser Favicon)', 'ha-image-optimizer' );
		}

		$custom_logo = (int) get_theme_mod( 'custom_logo', 0 );
		if ( $custom_logo === $attachment_id ) {
			$references[] = __( 'Offizielles Website-Logo (Theme Header)', 'ha-image-optimizer' );
		}

		$site_logo = (int) get_option( 'site_logo', 0 );
		if ( $site_logo === $attachment_id && $custom_logo !== $attachment_id ) {
			$references[] = __( 'Website-Logo (FSE Block Theme)', 'ha-image-optimizer' );
		}

		// -------------------------------------------------------------
		// 4. Taxonomy Meta (Term Thumbnails, e.g. WooCommerce Categories)
		// -------------------------------------------------------------
		if ( ! empty( $wpdb->termmeta ) ) {
			$term_sql = $wpdb->prepare(
				"SELECT t.term_id, t.name, tt.taxonomy FROM {$wpdb->termmeta} tm
				INNER JOIN {$wpdb->terms} t ON (tm.term_id = t.term_id)
				INNER JOIN {$wpdb->term_taxonomy} tt ON (t.term_id = tt.term_id)
				WHERE tm.meta_key IN ('thumbnail_id', 'product_cat_thumbnail_id')
				AND tm.meta_value = %d
				LIMIT 5",
				$attachment_id
			);
			$term_rows = $wpdb->get_results( $term_sql );

			if ( ! empty( $term_rows ) ) {
				foreach ( $term_rows as $tr ) {
					$references[] = sprintf(
						/* translators: 1: category name, 2: taxonomy name */
						__( 'Kategorie-Vorschaubild in "%1$s" (%2$s)', 'ha-image-optimizer' ),
						$tr->name,
						$tr->taxonomy
					);
				}
			}
		}

		// -------------------------------------------------------------
		// 5. Content References (Gutenberg blocks, HTML <img>, URLs)
		// -------------------------------------------------------------
		$upload_dir = wp_upload_dir();
		$rel_file   = '';
		if ( $file && ! empty( $upload_dir['basedir'] ) ) {
			$rel_file = ltrim( str_replace( wp_normalize_path( $upload_dir['basedir'] ), '', wp_normalize_path( $file ) ), '/' );
		}

		$content_patterns = [
			'wp-image-' . $attachment_id,
			'"id":' . $attachment_id,
			'"id": ' . $attachment_id,
		];

		if ( ! empty( $rel_file ) ) {
			$content_patterns[] = $rel_file;
		}

		// Build safe LIKE clauses for content searching
		$like_clauses = [];
		$like_params  = [];
		foreach ( $content_patterns as $pattern ) {
			$like_clauses[] = 'p.post_content LIKE %s';
			$like_params[]  = '%' . $wpdb->esc_like( $pattern ) . '%';
		}

		$where_content = implode( ' OR ', $like_clauses );
		$content_sql   = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_type FROM {$wpdb->posts} p
			WHERE p.post_type NOT IN ('attachment', 'revision')
			AND p.post_status != 'trash'
			AND ({$where_content})
			LIMIT 5",
			...$like_params
		);

		$content_posts = $wpdb->get_results( $content_sql );
		if ( ! empty( $content_posts ) ) {
			foreach ( $content_posts as $cp ) {
				$title = $cp->post_title ? $cp->post_title : ( 'ID ' . $cp->ID );
				$references[] = sprintf(
					/* translators: 1: post title, 2: post ID, 3: post type */
					__( 'Eingebunden im Inhalt von "%1$s" (#%2$d, %3$s)', 'ha-image-optimizer' ),
					$title,
					(int) $cp->ID,
					$cp->post_type
				);
			}
		}

		// -------------------------------------------------------------
		// 6. Page Builder Postmeta (e.g. Elementor _elementor_data)
		// -------------------------------------------------------------
		$builder_sql = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_type FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON (pm.post_id = p.ID)
			WHERE pm.meta_key = '_elementor_data'
			AND (
				pm.meta_value LIKE %s
				OR pm.meta_value LIKE %s
			)
			AND p.post_status != 'trash'
			LIMIT 3",
			'%"id":' . $attachment_id . '%',
			'%"id":"' . $attachment_id . '"%'
		);
		$builder_posts = $wpdb->get_results( $builder_sql );

		if ( ! empty( $builder_posts ) ) {
			foreach ( $builder_posts as $bp ) {
				$title = $bp->post_title ? $bp->post_title : ( 'ID ' . $bp->ID );
				$references[] = sprintf(
					/* translators: 1: post title, 2: post ID */
					__( 'Elementor Layout in "%1$s" (#%2$d)', 'ha-image-optimizer' ),
					$title,
					(int) $bp->ID
				);
			}
		}

		// -------------------------------------------------------------
		// 7. Status Classification
		// -------------------------------------------------------------
		$references = array_unique( $references );

		if ( ! empty( $references ) ) {
			$status = self::STATUS_USED;
		} elseif ( $parent_id > 0 ) {
			$status = self::STATUS_ATTACHED_UNUSED;
		} else {
			$status = self::STATUS_UNATTACHED_UNUSED;
		}

		return [
			'id'             => $attachment_id,
			'filename'       => $filename,
			'mime'           => $post->post_mime_type,
			'thumb_url'      => (string) $thumb_url,
			'date'           => get_the_date( 'Y-m-d H:i', $attachment_id ),
			'parent_id'      => $parent_id,
			'parent_title'   => $parent_title,
			'disk_bytes'     => $disk_bytes,
			'disk_human'     => size_format( $disk_bytes ),
			'status'         => $status,
			'references'     => $references,
			'reference_desc' => ! empty( $references ) ? implode( ', ', $references ) : '',
			'scanned_at'     => time(),
		];
	}

	/**
	 * Calculate total disk bytes occupied by an attachment (master + thumbnails + webp + avif).
	 *
	 * @param int         $attachment_id Attachment ID.
	 * @param string|null $file Main file path.
	 * @return int Total bytes.
	 */
	public static function calculate_disk_bytes( int $attachment_id, ?string $file ): int {
		if ( ! $file || ! file_exists( $file ) ) {
			return 0;
		}

		$bytes    = (int) filesize( $file );
		$base_dir = dirname( $file );

		// Check master WebP and AVIF
		if ( file_exists( $file . '.webp' ) ) {
			$bytes += (int) filesize( $file . '.webp' );
		}
		if ( file_exists( $file . '.avif' ) ) {
			$bytes += (int) filesize( $file . '.avif' );
		}

		// Check thumbnails
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size_info ) {
				if ( empty( $size_info['file'] ) ) {
					continue;
				}

				$sub_path = trailingslashit( $base_dir ) . $size_info['file'];
				if ( file_exists( $sub_path ) ) {
					$bytes += (int) filesize( $sub_path );
				}
				if ( file_exists( $sub_path . '.webp' ) ) {
					$bytes += (int) filesize( $sub_path . '.webp' );
				}
				if ( file_exists( $sub_path . '.avif' ) ) {
					$bytes += (int) filesize( $sub_path . '.avif' );
				}
			}
		}

		return $bytes;
	}

	/**
	 * Move an attachment safely to trash or delete it.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $force_delete If true, permanently deletes files. If false, moves to trash.
	 * @return bool True on success.
	 */
	public static function trash_attachment( int $attachment_id, bool $force_delete = false ): bool {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id <= 0 ) {
			return false;
		}

		// Optional backup before permanent deletion
		if ( $force_delete ) {
			$file = get_attached_file( $attachment_id );
			if ( $file && file_exists( $file ) ) {
				BackupManager::backup( $attachment_id, $file );
			}
		}

		$result = wp_delete_attachment( $attachment_id, $force_delete );

		if ( $result ) {
			// Remove from cached audit results
			self::remove_from_cached_results( $attachment_id );
			return true;
		}

		return false;
	}

	/**
	 * Restore an attachment from trash.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function restore_attachment( int $attachment_id ): bool {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id <= 0 ) {
			return false;
		}

		if ( function_exists( 'wp_untrash_post' ) ) {
			$result = wp_untrash_post( $attachment_id );
			return ! empty( $result );
		}

		return false;
	}

	/**
	 * Get cached audit results from options table.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_cached_results(): array {
		$data = get_option( self::OPTION_RESULTS, [] );
		if ( ! is_array( $data ) ) {
			$data = [];
		}

		$defaults = [
			'last_scan'     => 0,
			'total_scanned' => 0,
			'counts'        => [
				'total'             => 0,
				'used'              => 0,
				'attached_unused'   => 0,
				'unattached_unused' => 0,
			],
			'reclaimable_bytes' => [
				'unattached' => 0,
				'attached'   => 0,
				'total'      => 0,
			],
			'items'         => [],
		];

		return wp_parse_args( $data, $defaults );
	}

	/**
	 * Update cached audit results with a batch of newly audited items.
	 *
	 * @param array<array<string, mixed>> $new_items List of audited item records.
	 * @return array<string, mixed> Updated full results summary.
	 */
	public static function update_cached_results( array $new_items ): array {
		$cache = self::get_cached_results();

		foreach ( $new_items as $item ) {
			$id = absint( $item['id'] ?? 0 );
			if ( $id > 0 ) {
				$cache['items'][ $id ] = $item;
			}
		}

		// Recalculate summary metrics
		$counts = [
			'total'             => count( $cache['items'] ),
			'used'              => 0,
			'attached_unused'   => 0,
			'unattached_unused' => 0,
		];

		$reclaimable = [
			'unattached' => 0,
			'attached'   => 0,
			'total'      => 0,
		];

		foreach ( $cache['items'] as $item ) {
			$status = $item['status'] ?? self::STATUS_UNATTACHED_UNUSED;
			$bytes  = (int) ( $item['disk_bytes'] ?? 0 );

			if ( self::STATUS_USED === $status ) {
				$counts['used']++;
			} elseif ( self::STATUS_ATTACHED_UNUSED === $status ) {
				$counts['attached_unused']++;
				$reclaimable['attached'] += $bytes;
			} else {
				$counts['unattached_unused']++;
				$reclaimable['unattached'] += $bytes;
			}
		}

		$reclaimable['total'] = $reclaimable['unattached'] + $reclaimable['attached'];

		$cache['last_scan']         = time();
		$cache['total_scanned']     = $counts['total'];
		$cache['counts']            = $counts;
		$cache['reclaimable_bytes'] = $reclaimable;

		update_option( self::OPTION_RESULTS, $cache, false );
		return $cache;
	}

	/**
	 * Remove a single ID from cached audit results.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function remove_from_cached_results( int $attachment_id ): void {
		$cache = self::get_cached_results();
		if ( isset( $cache['items'][ $attachment_id ] ) ) {
			unset( $cache['items'][ $attachment_id ] );
			self::update_cached_results( [] );
		}
	}

	/**
	 * Clear all cached audit results.
	 *
	 * @return bool
	 */
	public static function clear_results(): bool {
		return delete_option( self::OPTION_RESULTS );
	}
}
