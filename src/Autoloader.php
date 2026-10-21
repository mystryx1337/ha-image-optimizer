<?php
/**
 * PSR-4 Autoloader for HA Image Optimizer.
 *
 * @package HA_Image_Optimizer
 */

namespace ha\ImageOptimizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Autoloader
 */
class Autoloader {

	/**
	 * Prefix to register.
	 *
	 * @var string
	 */
	private const PREFIX = 'ha\\ImageOptimizer\\';

	/**
	 * Base directory for class files.
	 *
	 * @var string
	 */
	private static string $base_dir = '';

	/**
	 * Register the autoloader.
	 *
	 * @param string $base_dir Absolute path to src/ directory.
	 * @return void
	 */
	public static function register( string $base_dir ): void {
		self::$base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;

		spl_autoload_register( [ __CLASS__, 'autoload' ] );
	}

	/**
	 * Autoload a class by its fully qualified name.
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	public static function autoload( string $class ): void {
		// Does the class use our namespace prefix?
		$len = strlen( self::PREFIX );
		if ( strncmp( self::PREFIX, $class, $len ) !== 0 ) {
			return;
		}

		// Get the relative class name.
		$relative_class = substr( $class, $len );

		// Replace namespace separators with directory separators in the relative class name, append .php
		$file = self::$base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
