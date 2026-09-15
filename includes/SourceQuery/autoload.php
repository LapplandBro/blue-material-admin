<?php
/**
 * Bootstrap for vendored xPaw PHP-Source-Query 6.x (no upstream bootstrap.php).
 *
 * @license GNU Lesser General Public License, version 2.1
 */

spl_autoload_register( static function ( string $class ) : void {
	$prefix = 'xPaw\\SourceQuery\\';
	$baseDir = __DIR__ . '/';

	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$relativeClass = substr( $class, strlen( $prefix ) );
	$file = $baseDir . str_replace( '\\', '/', $relativeClass ) . '.php';

	if ( is_file( $file ) ) {
		require $file;
	}
} );
