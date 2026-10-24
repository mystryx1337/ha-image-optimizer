<?php
/**
 * Automated CI Test Suite for HA Image Optimizer WordPress Plugin.
 *
 * Covers:
 * 1. PHP Syntax, BOM/Header safety, and strict standards (including .l10n.php)
 * 2. PSR-4 Autoloader resolution for ha\ImageOptimizer
 * 3. Settings defaults, sanitization, and traversal protection
 * 4. SizeGuard negative savings protection (original and next-gen)
 * 5. Environment capability checks
 * 6. BackupManager path calculation, .htaccess directory shielding, and custom backup handling
 * 7. CustomFolderProcessor directory traversal prevention, recursive image discovery, and queue building
 * 8. ContentFilter URL resolution (uploads and themes), <picture> rewriting, and CSS image-set()
 * 9. HtaccessManager Apache rewrite rules and safe marker manipulation
 * 10. Localization integrity across 26 languages (.po, .mo, and WP 6.5+ .l10n.php)
 *
 * @package HA_Image_Optimizer
 */

declare(strict_types=1);

namespace {

	// Minimal WordPress environment stubs for standalone CI execution
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	}
	if ( ! defined( 'WP_CONTENT_DIR' ) ) {
		define( 'WP_CONTENT_DIR', dirname( __DIR__ ) . '/tests/fixtures/wp-content' );
	}

	$GLOBALS['wp_test_options']  = [];
	$GLOBALS['wp_test_postmeta'] = [];
	$GLOBALS['wp_test_actions']  = [];
	$GLOBALS['wp_test_filters']  = [];

	if ( ! function_exists( 'absint' ) ) {
		function absint( $val ): int {
			return abs( (int) $val );
		}
	}

	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( $key ): string {
			return preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key );
		}
	}

	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $str ): string {
			return strip_tags( trim( (string) $str ) );
		}
	}

	if ( ! function_exists( 'wp_unslash' ) ) {
		function wp_unslash( $val ) {
			return is_string( $val ) ? stripslashes( $val ) : $val;
		}
	}

	if ( ! function_exists( 'wp_is_writable' ) ) {
		function wp_is_writable( $path ): bool {
			return is_writable( $path );
		}
	}

	if ( ! function_exists( 'wp_convert_hr_to_bytes' ) ) {
		function wp_convert_hr_to_bytes( $value ): int {
			$value = trim( (string) $value );
			if ( '-1' === $value || -1 === (int) $value ) {
				return -1;
			}
			$last = strtolower( $value[ strlen( $value ) - 1 ] );
			$val  = (int) $value;
			switch ( $last ) {
				case 'g':
					$val *= 1024 * 1024 * 1024;
					break;
				case 'm':
					$val *= 1024 * 1024;
					break;
				case 'k':
					$val *= 1024;
					break;
			}
			return $val;
		}
	}

	if ( ! function_exists( 'trailingslashit' ) ) {
		function trailingslashit( $string ): string {
			return rtrim( (string) $string, '/\\' ) . '/';
		}
	}

	if ( ! function_exists( 'wp_normalize_path' ) ) {
		function wp_normalize_path( $path ): string {
			$path = str_replace( '\\', '/', (string) $path );
			$path = (string) preg_replace( '|(?<=.)/+|', '/', $path );
			if ( ':' === substr( $path, 1, 1 ) ) {
				$path = ucfirst( $path );
			}
			return $path;
		}
	}

	if ( ! function_exists( 'wp_mkdir_p' ) ) {
		function wp_mkdir_p( $target ): bool {
			return is_dir( $target ) || @mkdir( $target, 0777, true );
		}
	}

	if ( ! function_exists( 'wp_parse_args' ) ) {
		function wp_parse_args( $args, $defaults = [] ): array {
			if ( is_object( $args ) ) {
				$r = get_object_vars( $args );
			} elseif ( is_array( $args ) ) {
				$r =& $args;
			} else {
				$r = [];
			}
			return array_merge( $defaults, $r );
		}
	}

	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( $text ): string {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( $text ): string {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_url' ) ) {
		function esc_url( $url ): string {
			return (string) filter_var( $url, FILTER_SANITIZE_URL );
		}
	}

	if ( ! function_exists( 'esc_html__' ) ) {
		function esc_html__( $text, $domain = 'default' ): string {
			return (string) $text;
		}
	}

	if ( ! function_exists( 'esc_sql' ) ) {
		function esc_sql( $data ) {
			return is_string( $data ) ? addslashes( $data ) : $data;
		}
	}

	if ( ! function_exists( '__' ) ) {
		function __( $text, $domain = 'default' ): string {
			return (string) $text;
		}
	}

	if ( ! function_exists( 'size_format' ) ) {
		function size_format( $bytes, $decimals = 0 ): string {
			$bytes = (int) $bytes;
			if ( $bytes <= 0 ) {
				return '0 B';
			}
			$units = [ 'B', 'KB', 'MB', 'GB' ];
			$i     = (int) floor( log( $bytes, 1024 ) );
			return round( $bytes / pow( 1024, $i ), $decimals ) . ' ' . ( $units[ $i ] ?? 'B' );
		}
	}

	if ( ! function_exists( 'number_format_i18n' ) ) {
		function number_format_i18n( $number, $decimals = 0 ): string {
			return number_format( (float) $number, $decimals, ',', '.' );
		}
	}

	if ( ! function_exists( 'wp_basename' ) ) {
		function wp_basename( $path ): string {
			return basename( str_replace( '\\', '/', (string) $path ) );
		}
	}

	if ( ! function_exists( 'is_feed' ) ) {
		function is_feed(): bool {
			return false;
		}
	}

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( $name, $default = false ) {
			return $GLOBALS['wp_test_options'][ $name ] ?? $default;
		}
	}

	if ( ! function_exists( 'update_option' ) ) {
		function update_option( $name, $value, $autoload = null ): bool {
			$GLOBALS['wp_test_options'][ $name ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'delete_option' ) ) {
		function delete_option( $name ): bool {
			unset( $GLOBALS['wp_test_options'][ $name ] );
			return true;
		}
	}

	if ( ! function_exists( 'add_action' ) ) {
		function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
			$GLOBALS['wp_test_actions'][ $hook ][] = $callback;
		}
	}

	if ( ! function_exists( 'add_filter' ) ) {
		function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
			$GLOBALS['wp_test_filters'][ $hook ][] = $callback;
		}
	}

	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( $hook, $value, ...$args ) {
			if ( ! empty( $GLOBALS['wp_test_filters'][ $hook ] ) ) {
				foreach ( $GLOBALS['wp_test_filters'][ $hook ] as $callback ) {
					$value = call_user_func( $callback, $value, ...$args );
				}
			}
			return $value;
		}
	}

	$GLOBALS['wp_test_transients'] = [];
	if ( ! function_exists( 'get_transient' ) ) {
		function get_transient( $transient ) {
			return $GLOBALS['wp_test_transients'][ $transient ] ?? false;
		}
	}
	if ( ! function_exists( 'set_transient' ) ) {
		function set_transient( $transient, $value, $expiration = 0 ): bool {
			$GLOBALS['wp_test_transients'][ $transient ] = $value;
			return true;
		}
	}
	if ( ! function_exists( 'delete_transient' ) ) {
		function delete_transient( $transient ): bool {
			unset( $GLOBALS['wp_test_transients'][ $transient ] );
			return true;
		}
	}

	if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
		define( 'MINUTE_IN_SECONDS', 60 );
	}
	if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
		define( 'HOUR_IN_SECONDS', 3600 );
	}
	if ( ! defined( 'DAY_IN_SECONDS' ) ) {
		define( 'DAY_IN_SECONDS', 86400 );
	}

	if ( ! function_exists( 'wp_upload_dir' ) ) {
		function wp_upload_dir(): array {
			$base = wp_normalize_path( WP_CONTENT_DIR . '/uploads' );
			return [
				'path'    => $base,
				'url'     => 'https://example.com/wp-content/uploads',
				'subdir'  => '',
				'basedir' => $base,
				'baseurl' => 'https://example.com/wp-content/uploads',
				'error'   => false,
			];
		}
	}

	if ( ! function_exists( 'content_url' ) ) {
		function content_url( $path = '' ): string {
			return 'https://example.com/wp-content' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
		}
	}

	if ( ! function_exists( 'admin_url' ) ) {
		function admin_url( $path = '', $scheme = 'admin' ): string {
			return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
		}
	}

	if ( ! function_exists( 'current_user_can' ) ) {
		function current_user_can( $capability, ...$args ): bool {
			return true;
		}
	}

	if ( ! function_exists( 'is_serialized' ) ) {
		function is_serialized( $data, $strict = true ): bool {
			if ( ! is_string( $data ) ) {
				return false;
			}
			$data = trim( $data );
			if ( 'N;' === $data ) {
				return true;
			}
			if ( strlen( $data ) < 4 || ':' !== $data[1] ) {
				return false;
			}
			return true;
		}
	}

	if ( ! function_exists( 'maybe_unserialize' ) ) {
		function maybe_unserialize( $original ) {
			if ( is_serialized( $original ) ) {
				return @unserialize( $original );
			}
			return $original;
		}
	}

	if ( ! function_exists( 'check_ajax_referer' ) ) {
		function check_ajax_referer( $action = -1, $query_arg = false, $die = true ): int {
			return 1;
		}
	}

	if ( ! function_exists( 'wp_send_json_success' ) ) {
		function wp_send_json_success( $data = null, $status_code = null, $options = 0 ): void {
			echo json_encode( [ 'success' => true, 'data' => $data ] );
		}
	}

	if ( ! function_exists( 'wp_send_json_error' ) ) {
		function wp_send_json_error( $data = null, $status_code = null, $options = 0 ): void {
			echo json_encode( [ 'success' => false, 'data' => $data ] );
		}
	}

	if ( ! function_exists( 'get_post_meta' ) ) {
		function get_post_meta( $post_id, $key = '', $single = false ) {
			return $GLOBALS['wp_test_postmeta'][ $post_id ][ $key ] ?? ( $single ? '' : [] );
		}
	}

	if ( ! function_exists( 'update_post_meta' ) ) {
		function update_post_meta( $post_id, $key, $value, $prev_value = '' ): bool {
			$GLOBALS['wp_test_postmeta'][ $post_id ][ $key ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'delete_post_meta' ) ) {
		function delete_post_meta( $post_id, $key, $value = '' ): bool {
			unset( $GLOBALS['wp_test_postmeta'][ $post_id ][ $key ] );
			return true;
		}
	}

	if ( ! function_exists( 'get_attached_file' ) ) {
		function get_attached_file( $attachment_id, $unfiltered = false ) {
			return $GLOBALS['wp_test_attached_file'][ $attachment_id ] ?? false;
		}
	}

	if ( ! function_exists( 'wp_get_attachment_metadata' ) ) {
		function wp_get_attachment_metadata( $attachment_id, $unfiltered = false ) {
			return $GLOBALS['wp_test_metadata'][ $attachment_id ] ?? [];
		}
	}

	if ( ! function_exists( 'wp_update_attachment_metadata' ) ) {
		function wp_update_attachment_metadata( $attachment_id, $data ): bool {
			$GLOBALS['wp_test_metadata'][ $attachment_id ] = $data;
			return true;
		}
	}

	$GLOBALS['wp_test_posts'] = [];
	if ( ! function_exists( 'get_post' ) ) {
		function get_post( $post = null ) {
			if ( is_object( $post ) ) {
				return $post;
			}
			$id = (int) $post;
			return $GLOBALS['wp_test_posts'][ $id ] ?? (object) [
				'ID'             => $id,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => 0,
				'post_status'    => 'inherit',
				'post_title'     => 'Mock Attachment ' . $id,
				'post_date'      => '2020-01-01 12:00:00',
				'post_date_gmt'  => '2020-01-01 12:00:00',
			];
		}
	}

	if ( ! function_exists( 'get_the_title' ) ) {
		function get_the_title( $post = 0 ): string {
			$p = get_post( $post );
			return $p ? (string) ( $p->post_title ?? '' ) : '';
		}
	}

	if ( ! function_exists( 'get_the_date' ) ) {
		function get_the_date( $format = '', $post = null ): string {
			return '2026-10-21 12:00';
		}
	}

	if ( ! function_exists( 'get_theme_mod' ) ) {
		function get_theme_mod( $name, $default_val = false ) {
			return $GLOBALS['wp_test_theme_mods'][ $name ] ?? $default_val;
		}
	}

	if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
		function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
			return "https://example.com/wp-content/uploads/image-{$id}-150x150.jpg";
		}
	}

	if ( ! function_exists( 'wp_get_attachment_url' ) ) {
		function wp_get_attachment_url( $id ) {
			return "https://example.com/wp-content/uploads/image-{$id}.jpg";
		}
	}

	if ( ! function_exists( 'wp_delete_attachment' ) ) {
		function wp_delete_attachment( $post_id, $force_delete = false ) {
			return true;
		}
	}

	if ( ! function_exists( 'wp_untrash_post' ) ) {
		function wp_untrash_post( $post_id = 0 ) {
			return true;
		}
	}

	if ( ! function_exists( 'get_stylesheet_directory' ) ) {
		function get_stylesheet_directory(): string {
			return sys_get_temp_dir();
		}
	}

	if ( ! function_exists( 'get_template_directory' ) ) {
		function get_template_directory(): string {
			return sys_get_temp_dir();
		}
	}

	class MockWpdb {
		public string $prefix        = 'wp_';
		public string $posts         = 'wp_posts';
		public string $postmeta      = 'wp_postmeta';
		public string $termmeta      = 'wp_termmeta';
		public string $terms         = 'wp_terms';
		public string $term_taxonomy = 'wp_term_taxonomy';
		public string $options       = 'wp_options';

		public function get_var( $query ) {
			if ( is_string( $query ) && str_contains( $query, 'SHOW TABLES LIKE' ) ) {
				if ( str_contains( $query, 'smartslider%slides' ) ) {
					return 'wp_nextend2_smartslider3_slides';
				}
				if ( str_contains( $query, 'smartslider%sliders' ) ) {
					return 'wp_nextend2_smartslider3_sliders';
				}
				return null;
			}
			return 10;
		}

		public function get_results( $query ) {
			if ( str_contains( $query, 'SHOW COLUMNS FROM `wp_wpforms_entries`' ) ) {
				return [
					(object) [ 'Field' => 'entry_id', 'Type' => 'int(11)' ],
					(object) [ 'Field' => 'fields',   'Type' => 'longtext' ],
				];
			}
			if ( str_contains( $query, 'FROM `wp_wpforms_entries`' ) ) {
				if ( str_contains( $query, '20' ) ) {
					return [
						(object) [ 'entry_id' => 88, 'fields' => '{"imageId":20}' ],
					];
				}
				return [];
			}
			if ( str_contains( $query, 'meta_key NOT IN' ) && str_contains( $query, "meta_key NOT LIKE '_acf_%'" ) ) {
				if ( str_contains( $query, '25' ) ) {
					return [
						(object) [ 'ID' => 55, 'post_title' => 'Landingpage', 'post_type' => 'page', 'meta_key' => 'custom_hero_image' ],
					];
				}
				return [];
			}
			if ( str_contains( $query, '_thumbnail_id' ) ) {
				return [
					(object) [ 'ID' => 42, 'post_title' => 'Beispielbeitrag', 'post_type' => 'post' ],
				];
			}
			if ( str_contains( $query, 'nextend2_smartslider3_slides' ) ) {
				return [
					(object) [
						'id'           => 101,
						'slider'       => 5,
						'title'        => 'Hero Slide',
						'slider_title' => 'Startseiten Slider',
					],
				];
			}
			if ( str_contains( $query, '_ha_io_data' ) ) {
				return [
					(object) [
						'post_id'    => 1,
						'meta_value' => serialize( [
							'total' => [ 'before' => 100000, 'after' => 60000, 'saved' => 40000, 'percent' => 40.0 ],
						] ),
					],
				];
			}
			return [];
		}

		public function get_col( $query ) {
			if ( is_string( $query ) && str_contains( $query, "SHOW TABLES LIKE 'wp_%'" ) ) {
				return [
					'wp_posts',
					'wp_postmeta',
					'wp_options',
					'wp_nextend2_smartslider3_slides',
					'wp_nextend2_smartslider3_sliders',
					'wp_wpforms_entries',
				];
			}
			return [ 1, 2, 3 ];
		}

		public function prepare( $query, ...$args ) {
			if ( empty( $args ) ) {
				return $query;
			}
			if ( isset( $args[0] ) && is_array( $args[0] ) && 1 === count( $args ) ) {
				$args = $args[0];
			}
			$res = $query;
			foreach ( $args as $arg ) {
				$val   = is_scalar( $arg ) ? (string) $arg : '';
				$pos_s = strpos( $res, '%s' );
				$pos_d = strpos( $res, '%d' );
				if ( false !== $pos_s && ( false === $pos_d || $pos_s < $pos_d ) ) {
					$res = substr_replace( $res, "'" . $val . "'", $pos_s, 2 );
				} elseif ( false !== $pos_d ) {
					$res = substr_replace( $res, (string) (int) $val, $pos_d, 2 );
				}
			}
			return $res;
		}

		public function esc_like( $text ) {
			return addcslashes( (string) $text, '_%\\' );
		}
	}
	$GLOBALS['wpdb'] = new MockWpdb();
}

namespace HA_Image_Optimizer_Tests {

	use ha\ImageOptimizer\Autoloader;
	use ha\ImageOptimizer\Common\Settings;
	use ha\ImageOptimizer\Common\CompressionLevel;
	use ha\ImageOptimizer\Common\DeliveryMode;
	use ha\ImageOptimizer\Common\Environment;
	use ha\ImageOptimizer\Engine\SizeGuard;
	use ha\ImageOptimizer\Media\BackupManager;
	use ha\ImageOptimizer\Media\CustomFolderProcessor;
	use ha\ImageOptimizer\Delivery\ContentFilter;
	use ha\ImageOptimizer\Delivery\HtaccessManager;
	use ha\ImageOptimizer\Bulk\ProgressTracker;
	use ha\ImageOptimizer\Auditor\MediaAuditor;

	class TestRunner {

		private int $passed = 0;
		private int $failed = 0;
		private string $rootDir;

		public function __construct() {
			$this->rootDir = dirname( __DIR__ );
		}

		public function run(): void {
			echo "=========================================================\n";
			echo "   HA Image Optimizer - Standalone CI Test Suite\n";
			echo "=========================================================\n\n";

			$this->testPhpSyntaxAndBom();
			$this->testAutoloader();
			$this->testSettings();
			$this->testSizeGuard();
			$this->testEnvironment();
			$this->testBackupManager();
			$this->testCustomFolderProcessor();
			$this->testContentFilter();
			$this->testHtaccessManager();
			$this->testProgressTracker();
			$this->testMediaAuditor();
			$this->testTranslations();

			echo "\n---------------------------------------------------------\n";
			echo "TEST SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
			echo "---------------------------------------------------------\n";

			if ( $this->failed > 0 ) {
				exit( 1 );
			}
		}

		private function assert( bool $condition, string $message ): void {
			if ( $condition ) {
				$this->passed++;
				echo "  [PASS] {$message}\n";
			} else {
				$this->failed++;
				echo "  [FAIL] {$message}\n";
			}
		}

		private function testPhpSyntaxAndBom(): void {
			echo "\n1. Checking PHP Syntax, BOM, and File Headers...\n";

			$files = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $this->rootDir, \FilesystemIterator::SKIP_DOTS )
			);

			$checkedCount = 0;
			foreach ( $files as $file ) {
				if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
					continue;
				}

				$path = $file->getPathname();
				if ( str_contains( $path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR ) ) {
					continue;
				}

				$content = (string) file_get_contents( $path );

				// Check BOM
				$hasBom = str_starts_with( $content, "\xEF\xBB\xBF" );
				if ( $hasBom ) {
					$this->assert( false, "File contains UTF-8 BOM: {$path}" );
				}

				// Check that file starts with <?php
				$trimmed = ltrim( $content );
				$validStart = str_starts_with( $trimmed, '<?php' );
				if ( ! $validStart ) {
					$this->assert( false, "PHP file does not start with <?php: {$path}" );
				}

				$checkedCount++;
			}

			$this->assert( $checkedCount > 20, "Validated syntax and headers on {$checkedCount} PHP files" );
		}

		private function testAutoloader(): void {
			echo "\n2. Testing PSR-4 Autoloader...\n";

			require_once $this->rootDir . '/src/Autoloader.php';
			Autoloader::register( $this->rootDir . '/src' );

			$classes = [
				Settings::class,
				CompressionLevel::class,
				DeliveryMode::class,
				Environment::class,
				SizeGuard::class,
				BackupManager::class,
				CustomFolderProcessor::class,
				ContentFilter::class,
				HtaccessManager::class,
				MediaAuditor::class,
			];

			foreach ( $classes as $class ) {
				$this->assert( class_exists( $class ) || enum_exists( $class ), "Autoloader resolved: {$class}" );
			}
		}

		private function testSettings(): void {
			echo "\n3. Testing Settings Repository & Sanitization...\n";

			$defaults = Settings::get_defaults();
			$this->assert( 'lossless' === $defaults['compression_original'], 'Default original compression is lossless' );
			$this->assert( 'smart' === $defaults['compression_sizes'], 'Default sub-size compression is smart' );
			$this->assert( true === $defaults['backup_originals'], 'Default backup_originals is true' );
			$this->assert( 'themes' === $defaults['custom_folders'], 'Default custom_folders is themes' );
			$this->assert( true === $defaults['auditor_generic_db_scan'], 'Default auditor_generic_db_scan is true' );
			$this->assert( 14 === $defaults['auditor_safety_buffer_days'], 'Default auditor_safety_buffer_days is 14' );

			// Test Sanitization and Path Traversal Prevention
			$dirty = [
				'compression_original'    => 'invalid_mode',
				'max_width'               => 999999, // Too large
				'custom_folders'          => "themes/my-theme\n../dangerous/path\nha-backups\nassets/images",
				'optimize_custom_folders' => '1',
			];

			$clean = Settings::sanitize( $dirty );
			$this->assert( 'lossless' === $clean['compression_original'], 'Invalid compression level fell back to default' );
			$this->assert( 2560 === $clean['max_width'], 'Out-of-bounds width clamped to 2560' );
			$this->assert( ! str_contains( $clean['custom_folders'], '..' ), 'Directory traversal path (..) stripped from custom_folders' );
			$this->assert( ! str_contains( $clean['custom_folders'], 'ha-backups' ), 'Internal backup path excluded from custom_folders' );
			$this->assert( str_contains( $clean['custom_folders'], 'themes/my-theme' ), 'Valid custom folder retained' );
		}

		private function testSizeGuard(): void {
			echo "\n4. Testing SizeGuard (Negative Savings Protection)...\n";

			$tmpFile = tempnam( sys_get_temp_dir(), 'ha_test_' );
			file_put_contents( $tmpFile, str_repeat( 'A', 500 ) ); // 500 bytes

			// Case 1: Optimized is smaller (400 bytes vs 500 original) -> Accept
			$originalSize = 500;
			file_put_contents( $tmpFile, str_repeat( 'B', 400 ) );
			$kept = SizeGuard::protect_file( $tmpFile, $originalSize );
			$this->assert( true === $kept, 'SizeGuard keeps optimized file when size decreases (500 -> 400)' );

			// Case 2: NextGen is larger than original -> Discard
			$nextGen = $tmpFile . '.webp';
			file_put_contents( $nextGen, str_repeat( 'C', 600 ) ); // 600 bytes vs 400 original
			$ngKept = SizeGuard::protect_nextgen( $nextGen, 400 );
			$this->assert( false === $ngKept, 'SizeGuard rejects NextGen file when larger than original' );
			$this->assert( ! file_exists( $nextGen ), 'NextGen file was deleted on negative saving' );

			@unlink( $tmpFile );
		}

		private function testEnvironment(): void {
			echo "\n5. Testing Environment Diagnostics...\n";

			$caps = Environment::get_capabilities();
			$this->assert( isset( $caps['php']['version'] ), 'Environment reports PHP version' );
			$this->assert( isset( $caps['best_driver'] ), 'Environment detects image driver (imagick or gd)' );
			$this->assert( is_bool( $caps['supports_webp'] ), 'Environment checks WebP capability' );
			$this->assert( is_bool( $caps['supports_avif'] ), 'Environment checks AVIF capability' );
		}

		private function testBackupManager(): void {
			echo "\n6. Testing BackupManager Safety & Security...\n";

			$contentDir = wp_normalize_path( WP_CONTENT_DIR );
			$fakeCustom = $contentDir . '/themes/test-theme/assets/hero.jpg';

			$backupPath = BackupManager::get_custom_backup_file_path( $fakeCustom );
			$this->assert( str_contains( $backupPath, 'ha-backups/custom/themes/test-theme/assets/hero.jpg' ), 'Custom backup path correctly mirrored inside ha-backups/custom/' );

			$backupDir = sys_get_temp_dir() . '/ha_backup_sec_test';
			wp_mkdir_p( $backupDir );
			BackupManager::secure_directory( $backupDir );

			$htaccess = $backupDir . '/.htaccess';
			$this->assert( file_exists( $htaccess ), 'Backup directory shielded with .htaccess' );
			$rules = (string) file_get_contents( $htaccess );
			$this->assert( str_contains( $rules, 'Require all denied' ), '.htaccess contains Apache 2.4 authz barrier' );
			$this->assert( str_contains( $rules, 'Deny from all' ), '.htaccess contains Apache 2.2 Deny barrier' );

			@unlink( $htaccess );
			@unlink( $backupDir . '/index.php' );
			@rmdir( $backupDir );
		}

		private function testCustomFolderProcessor(): void {
			echo "\n7. Testing CustomFolderProcessor & Scanning...\n";

			// Create a mock directory structure in tests/fixtures/wp-content
			$themeDir = WP_CONTENT_DIR . '/themes/ci-test-theme/images';
			wp_mkdir_p( $themeDir );

			$testImg1 = $themeDir . '/banner.jpg';
			$testImg2 = $themeDir . '/icon.png';
			$testSkip = $themeDir . '/banner.jpg.webp'; // Companion file that should be skipped

			file_put_contents( $testImg1, 'dummy_jpeg_content' );
			file_put_contents( $testImg2, 'dummy_png_content' );
			file_put_contents( $testSkip, 'dummy_webp_companion' );

			$images = CustomFolderProcessor::scan_images( [ $themeDir ] );
			$this->assert( in_array( 'themes/ci-test-theme/images/banner.jpg', $images, true ), 'Discovered banner.jpg in custom theme' );
			$this->assert( in_array( 'themes/ci-test-theme/images/icon.png', $images, true ), 'Discovered icon.png in custom theme' );
			$this->assert( ! in_array( 'themes/ci-test-theme/images/banner.jpg.webp', $images, true ), 'Correctly ignored companion .webp file during scan' );

			// Clean up fixtures
			@unlink( $testImg1 );
			@unlink( $testImg2 );
			@unlink( $testSkip );
			@rmdir( $themeDir );
			@rmdir( dirname( $themeDir ) );
		}

		private function testContentFilter(): void {
			echo "\n8. Testing ContentFilter (<picture> & CSS image-set)...\n";

			// Test URL resolving
			$uploadUrl = 'https://example.com/wp-content/uploads/2026/10/photo.jpg';
			$themeUrl  = 'https://example.com/wp-content/themes/my-theme/img/logo.png';

			$resolvedUpload = ContentFilter::resolve_local_path( $uploadUrl );
			$resolvedTheme  = ContentFilter::resolve_local_path( $themeUrl );

			$this->assert( str_contains( $resolvedUpload, 'wp-content/uploads/2026/10/photo.jpg' ), 'Resolved upload URL to uploads path' );
			$this->assert( str_contains( $resolvedTheme, 'wp-content/themes/my-theme/img/logo.png' ), 'Resolved theme URL to theme path' );

			// Mock image with companion webp on disk
			$mockFile = wp_normalize_path( WP_CONTENT_DIR . '/uploads/mock.jpg' );
			$mockWebp = $mockFile . '.webp';
			wp_mkdir_p( dirname( $mockFile ) );
			file_put_contents( $mockFile, 'original' );
			file_put_contents( $mockWebp, 'webp_version' );

			$htmlInput = '<p>Text <img src="https://example.com/wp-content/uploads/mock.jpg" alt="Test" class="wp-image-1"> more text</p>';
			$filtered  = ContentFilter::filter_html( $htmlInput );

			$this->assert( str_contains( $filtered, '<picture' ), '<img> tag converted into <picture> element' );
			$this->assert( str_contains( $filtered, '<source type="image/webp"' ), '<picture> includes WebP <source> tag' );
			$this->assert( str_contains( $filtered, '<img src="https://example.com/wp-content/uploads/mock.jpg"' ), 'Preserved original <img> as fallback' );

			@unlink( $mockFile );
			@unlink( $mockWebp );
		}

		private function testHtaccessManager(): void {
			echo "\n9. Testing HtaccessManager Rules & Markers...\n";

			$rules = HtaccessManager::get_rules();
			$this->assert( str_starts_with( $rules, HtaccessManager::MARKER_START ), 'Rules start with HA marker' );
			$this->assert( str_ends_with( trim( $rules ), HtaccessManager::MARKER_END ), 'Rules end with HA marker' );
			$this->assert( str_contains( $rules, 'image/avif' ), 'Rules support transparent AVIF rewrite' );
			$this->assert( str_contains( $rules, 'image/webp' ), 'Rules support transparent WebP rewrite' );
			$this->assert( str_contains( $rules, 'Header append Vary Accept' ), 'Rules include Vary Accept caching header' );
		}

		private function testProgressTracker(): void {
			echo "\n10. Testing ProgressTracker Query Performance & Stats...\n";

			$stats = ProgressTracker::get_stats();
			$this->assert( isset( $stats['total_images'] ) && 10 === $stats['total_images'], 'ProgressTracker counts total eligible images via COUNT query' );
			$this->assert( isset( $stats['saved_bytes'] ) && 40000 === $stats['saved_bytes'], 'ProgressTracker calculates total saved bytes from meta rows' );
			$this->assert( isset( $stats['saved_percent'] ) && 40.0 === $stats['saved_percent'], 'ProgressTracker calculates percentage correctly' );

			$queue = ProgressTracker::get_queue( 5, false );
			$this->assert( is_array( $queue ) && count( $queue ) === 3, 'ProgressTracker retrieves unoptimized queue items' );
		}

		private function testMediaAuditor(): void {
			echo "\n11. Testing MediaAuditor (Orphan & Inactive Detection)...\n";

			$queue = MediaAuditor::get_audit_queue();
			$this->assert( is_array( $queue ) && count( $queue ) === 3, 'MediaAuditor retrieved media queue' );

			// Mock attachment 1: featured image found in post 42 and Smart Slider 3 slide
			$auditUsed = MediaAuditor::audit_attachment( 1 );
			$this->assert( MediaAuditor::STATUS_USED === $auditUsed['status'], 'Detected used attachment with featured image reference' );
			$this->assert( ! empty( $auditUsed['references'] ), 'Contains detailed reference labels' );
			$this->assert( ! empty( $auditUsed['references'][0]['url'] ), 'Reference includes direct edit link' );
			$this->assert( str_contains( $auditUsed['references'][0]['label'], 'Beitragsbild' ), 'Reference label identifies featured image' );

			$hasSmartSlider = false;
			foreach ( $auditUsed['references'] as $ref ) {
				if ( str_contains( $ref['label'], 'Smart Slider 3' ) ) {
					$hasSmartSlider = true;
					$this->assert( str_contains( $ref['url'], 'page=smartslider3' ), 'Smart Slider reference contains direct slide link' );
					$this->assert( str_contains( $ref['label'], 'Hero Slide' ), 'Smart Slider reference identifies slide title' );
					break;
				}
			}
			$this->assert( $hasSmartSlider, 'Detected Smart Slider 3 usage and generated direct editor link' );

			// Test Universal Database Scanner (attachment 20 found in custom table wp_wpforms_entries)
			$auditGeneric = MediaAuditor::audit_attachment( 20 );
			$this->assert( MediaAuditor::STATUS_USED === $auditGeneric['status'], 'Detected used attachment in generic third-party table' );
			$hasGenericTable = false;
			foreach ( $auditGeneric['references'] as $ref ) {
				if ( str_contains( $ref['label'], 'wpforms_entries' ) ) {
					$hasGenericTable = true;
					$this->assert( str_contains( $ref['label'], '88' ), 'Generic table reference identifies entry ID' );
					break;
				}
			}
			$this->assert( $hasGenericTable, 'Universal database scanner detected usage in third-party table wp_wpforms_entries' );

			// Test Generic Postmeta Deep Scan (attachment 25 found in unknown postmeta key)
			$auditMeta = MediaAuditor::audit_attachment( 25 );
			$this->assert( MediaAuditor::STATUS_USED === $auditMeta['status'], 'Detected used attachment via generic postmeta deep scan' );
			$hasGenericMeta = false;
			foreach ( $auditMeta['references'] as $ref ) {
				if ( str_contains( $ref['label'], 'custom_hero_image' ) ) {
					$hasGenericMeta = true;
					$this->assert( str_contains( $ref['label'], 'Landingpage' ), 'Postmeta reference identifies post title' );
					$this->assert( str_contains( $ref['url'], 'post=55' ), 'Postmeta reference includes direct post edit link' );
					break;
				}
			}
			$this->assert( $hasGenericMeta, 'Generic postmeta scanner identified unknown custom postmeta reference' );

			// Test Safety Buffer: recently uploaded image (2 days old) protected from deletion
			$GLOBALS['wp_test_posts'][99] = (object) [
				'ID'             => 99,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => 0,
				'post_status'    => 'inherit',
				'post_title'     => 'Fresh Upload 99',
				'post_date'      => date( 'Y-m-d H:i:s', time() - 86400 * 2 ),
				'post_date_gmt'  => date( 'Y-m-d H:i:s', time() - 86400 * 2 ),
			];
			$auditRecent = MediaAuditor::audit_attachment( 99 );
			$this->assert( MediaAuditor::STATUS_USED === $auditRecent['status'], 'Safety buffer marks recently uploaded media as protected (used)' );
			$this->assert( ! empty( $auditRecent['references'] ), 'Recent upload has safety buffer reference' );
			$this->assert( str_contains( $auditRecent['references'][0]['label'], 'Schutzfrist aktiv' ), 'Safety buffer reference explains age and protection' );

			// Test Extensibility Hook: ha_io_audit_attachment_references
			add_filter( 'ha_io_audit_attachment_references', function( $refs, $id ) {
				if ( 999 === $id ) {
					$refs[] = [
						'label' => 'Plugin Extension Hook Ref',
						'url'   => 'https://example.com/custom-hook',
					];
				}
				return $refs;
			}, 10, 2 );

			$GLOBALS['wp_test_posts'][999] = (object) [
				'ID'             => 999,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => 0,
				'post_status'    => 'inherit',
				'post_title'     => 'Hook Attachment 999',
				'post_date'      => '2020-01-01 12:00:00',
				'post_date_gmt'  => '2020-01-01 12:00:00',
			];
			$auditHook = MediaAuditor::audit_attachment( 999 );
			$this->assert( MediaAuditor::STATUS_USED === $auditHook['status'], 'Extensibility filter successfully protects media' );
			$this->assert( str_contains( $auditHook['references'][0]['label'], 'Plugin Extension Hook Ref' ), 'Hook reference label matched' );

			// Test caching and metric calculation
			$mockItems = [
				[
					'id'         => 10,
					'filename'   => 'used.jpg',
					'status'     => MediaAuditor::STATUS_USED,
					'disk_bytes' => 50000,
					'references' => [ 'Beitragsbild in #42' ],
				],
				[
					'id'         => 11,
					'filename'   => 'orphan.jpg',
					'status'     => MediaAuditor::STATUS_UNATTACHED_UNUSED,
					'disk_bytes' => 120000,
					'references' => [],
				],
				[
					'id'           => 12,
					'filename'     => 'inactive.jpg',
					'status'       => MediaAuditor::STATUS_ATTACHED_UNUSED,
					'disk_bytes'   => 80000,
					'parent_id'    => 5,
					'parent_title' => 'Alter Post',
					'references'   => [],
				],
			];

			$summary = MediaAuditor::update_cached_results( $mockItems );
			$this->assert( 3 === $summary['counts']['total'], 'Summary counts total audited items' );
			$this->assert( 1 === $summary['counts']['unattached_unused'], 'Summary counts unattached unused items' );
			$this->assert( 1 === $summary['counts']['attached_unused'], 'Summary counts attached unused items' );
			$this->assert( 1 === $summary['counts']['used'], 'Summary counts active items' );
			$this->assert( 200000 === $summary['reclaimable_bytes']['total'], 'Calculated reclaimable storage accurately (120KB + 80KB = 200KB)' );

			$trashResult = MediaAuditor::trash_attachment( 11 );
			$this->assert( $trashResult, 'Successfully trashed orphan attachment' );

			$cached = MediaAuditor::get_cached_results();
			$this->assert( ! isset( $cached['items'][11] ), 'Trashed attachment removed from cached audit index' );

			$restoreResult = MediaAuditor::restore_attachment( 11 );
			$this->assert( $restoreResult, 'Successfully restored attachment' );

			MediaAuditor::clear_results();
			$cleared = MediaAuditor::get_cached_results();
			$this->assert( empty( $cleared['items'] ), 'Audit cache cleared successfully' );
		}

		private function testTranslations(): void {
			echo "\n12. Testing Language Files Integrity (26 Locales)...\n";

			$potFile = $this->rootDir . '/languages/ha-image-optimizer.pot';
			$this->assert( file_exists( $potFile ), 'Template ha-image-optimizer.pot exists' );

			$l10nFiles = glob( $this->rootDir . '/languages/*.l10n.php' );
			$this->assert( count( $l10nFiles ) >= 26, 'Found ' . count( $l10nFiles ) . ' modern WordPress .l10n.php language packs' );

			$allPresent = true;
			foreach ( $l10nFiles as $l10nFile ) {
				$moFile = preg_replace( '/\.l10n\.php$/', '.mo', $l10nFile );
				$poFile = preg_replace( '/\.l10n\.php$/', '.po', $l10nFile );

				if ( ! file_exists( $moFile ) || ! file_exists( $poFile ) ) {
					$allPresent = false;
					$this->assert( false, "Missing .mo or .po counterpart for {$l10nFile}" );
					break;
				}

				// Validate l10n.php structure
				$l10nContent = (string) file_get_contents( $l10nFile );
				if ( ! str_contains( $l10nContent, 'ABSPATH' ) ) {
					$allPresent = false;
					$this->assert( false, "Missing ABSPATH check in {$l10nFile}" );
					break;
				}
			}

			if ( $allPresent ) {
				$this->assert( true, 'All 26 locales possess valid .po, .mo, and WP 6.5+ .l10n.php files' );
			}
		}
	}

	$runner = new TestRunner();
	$runner->run();
}
