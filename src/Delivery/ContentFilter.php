<?php
/**
 * Content Filter for Picture Tags and CSS image-set.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer\Delivery;

use ha\ImageOptimizer\Common\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ContentFilter
 */
class ContentFilter {

	/**
	 * Register content filters.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'the_content', [ __CLASS__, 'filter_html' ], 999 );
		add_filter( 'post_thumbnail_html', [ __CLASS__, 'filter_html' ], 999 );
		add_filter( 'wp_get_attachment_image', [ __CLASS__, 'filter_html' ], 999 );
		add_filter( 'widget_text', [ __CLASS__, 'filter_html' ], 999 );
	}

	/**
	 * Filter raw HTML content and replace <img> with <picture>.
	 *
	 * @param string $content HTML content.
	 * @return string Modified HTML.
	 */
	public static function filter_html( string $content ): string {
		if ( empty( $content ) || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $content;
		}

		// 1. Process <img> tags
		$content = (string) preg_replace_callback( '/<img\b([^>]*?)>/i', [ __CLASS__, 'replace_img_tag' ], $content );

		// 2. Process inline background-image styles
		$content = (string) preg_replace_callback(
			'/style\s*=\s*["\']([^"\']*?background(?:-image)?\s*:\s*url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)[^"\']*?)["\']/i',
			[ __CLASS__, 'replace_inline_style' ],
			$content
		);

		return $content;
	}

	/**
	 * Replace a single <img> tag with a <picture> element if WebP/AVIF exists.
	 *
	 * @param array<int, string> $matches Regex matches.
	 * @return string Replacement HTML.
	 */
	public static function replace_img_tag( array $matches ): string {
		$img_tag    = $matches[0];
		$attributes = $matches[1];

		// Skip if already inside a picture tag, or marked to skip
		if ( stripos( $attributes, 'no-webp' ) !== false || stripos( $attributes, 'data-no-webp' ) !== false ) {
			return $img_tag;
		}

		// Extract src
		if ( ! preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $attributes, $src_matches ) ) {
			return $img_tag;
		}

		$src = $src_matches[1];

		// Check if image is an upload from this site
		$upload_dir = wp_upload_dir();
		$base_url   = $upload_dir['baseurl'];
		$base_dir   = $upload_dir['basedir'];

		if ( ! str_starts_with( $src, $base_url ) ) {
			return $img_tag; // External image or not in uploads
		}

		// Calculate local disk path
		$local_path = str_replace( $base_url, $base_dir, $src );

		$has_avif = file_exists( $local_path . '.avif' );
		$has_webp = file_exists( $local_path . '.webp' );

		if ( ! $has_avif && ! $has_webp ) {
			return $img_tag;
		}

		// Extract srcset if present
		$srcset = '';
		if ( preg_match( '/\bsrcset\s*=\s*["\']([^"\']+)["\']/i', $attributes, $srcset_matches ) ) {
			$srcset = $srcset_matches[1];
		}

		// Extract sizes if present
		$sizes_attr = '';
		if ( preg_match( '/\bsizes\s*=\s*["\']([^"\']+)["\']/i', $attributes, $sizes_matches ) ) {
			$sizes_attr = ' sizes="' . esc_attr( $sizes_matches[1] ) . '"';
		}

		$picture = "<picture>\n";

		// AVIF source
		if ( $has_avif ) {
			$avif_srcset = self::convert_srcset( $srcset, '.avif', $base_url, $base_dir );
			if ( empty( $avif_srcset ) ) {
				$avif_srcset = esc_url( $src . '.avif' );
			}
			$picture .= "\t<source type=\"image/avif\" srcset=\"{$avif_srcset}\"{$sizes_attr}>\n";
		}

		// WebP source
		if ( $has_webp ) {
			$webp_srcset = self::convert_srcset( $srcset, '.webp', $base_url, $base_dir );
			if ( empty( $webp_srcset ) ) {
				$webp_srcset = esc_url( $src . '.webp' );
			}
			$picture .= "\t<source type=\"image/webp\" srcset=\"{$webp_srcset}\"{$sizes_attr}>\n";
		}

		$picture .= "\t{$img_tag}\n</picture>";

		return $picture;
	}

	/**
	 * Convert an existing srcset string to use .webp or .avif suffixes for matching existing files.
	 *
	 * @param string $srcset Original srcset.
	 * @param string $ext Extension suffix (e.g. '.webp' or '.avif').
	 * @param string $base_url Base upload URL.
	 * @param string $base_dir Base upload directory.
	 * @return string Converted srcset.
	 */
	private static function convert_srcset( string $srcset, string $ext, string $base_url, string $base_dir ): string {
		if ( empty( $srcset ) ) {
			return '';
		}

		$entries = explode( ',', $srcset );
		$converted = [];

		foreach ( $entries as $entry ) {
			$entry = trim( $entry );
			if ( empty( $entry ) ) {
				continue;
			}

			$parts = preg_split( '/\s+/', $entry );
			if ( empty( $parts[0] ) ) {
				continue;
			}

			$url = $parts[0];
			$descriptor = $parts[1] ?? '';

			if ( str_starts_with( $url, $base_url ) ) {
				$local_path = str_replace( $base_url, $base_dir, $url );
				if ( file_exists( $local_path . $ext ) ) {
					$converted[] = esc_url( $url . $ext ) . ( $descriptor ? ' ' . $descriptor : '' );
					continue;
				}
			}

			$converted[] = $entry;
		}

		return implode( ', ', $converted );
	}

	/**
	 * Convert inline background-image to modern CSS image-set.
	 *
	 * @param array<int, string> $matches Matches.
	 * @return string Replacement style attribute.
	 */
	public static function replace_inline_style( array $matches ): string {
		$full_style = $matches[0];
		$style_body = $matches[1];
		$img_url    = $matches[2];

		$upload_dir = wp_upload_dir();
		$base_url   = $upload_dir['baseurl'];
		$base_dir   = $upload_dir['basedir'];

		if ( ! str_starts_with( $img_url, $base_url ) ) {
			return $full_style;
		}

		$local_path = str_replace( $base_url, $base_dir, $img_url );
		$has_webp   = file_exists( $local_path . '.webp' );
		$has_avif   = file_exists( $local_path . '.avif' );

		if ( ! $has_webp && ! $has_avif ) {
			return $full_style;
		}

		$webp_url = $img_url . '.webp';
		$avif_url = $img_url . '.avif';

		$sets = [];
		if ( $has_avif ) {
			$sets[] = "url('{$avif_url}') type('image/avif')";
		}
		if ( $has_webp ) {
			$sets[] = "url('{$webp_url}') type('image/webp')";
		}
		$sets[] = "url('{$img_url}') type('image/jpeg')";

		$set_str = implode( ', ', $sets );

		// Append modern image-set rule
		$enhanced_style = rtrim( $style_body, ';' ) . "; background-image: -webkit-image-set({$set_str}); background-image: image-set({$set_str});";

		return 'style="' . esc_attr( $enhanced_style ) . '"';
	}
}
