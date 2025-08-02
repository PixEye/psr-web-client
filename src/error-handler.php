<?php
/** error-handler.php
 * @link https://www.php.net/manual/en/function.set-error-handler#112291
 * @since 2024.04.25
 */

/** Error handler, passes flow over the exception logger with new \ErrorException
 * @param int $severity
 * @param string $message
 * @param string $fileName
 * @param int $lineNum
 * @param Throwable $previous Context of previous exception
 */
function log_error( $severity, $message, $fileName, $lineNum, $previous = null ) {
	if (isSet($previous)) {
		if (is_array($previous)) $previous = null;
		elseIf (is_object($previous)) {
			$reflect = new \ReflectionClass($previous);
			if ( ! $reflect->implementsInterface('Throwable') ) $previous = null;
		} else $previous = null;
	}
	log_exception( new \ErrorException( $message, 0, $severity, $fileName, $lineNum, $previous ) );
}

/** Uncaught exception handler
 * @param Throwable $exc
 */
function log_exception( \Throwable $exc ) {
	$file = $exc->getFile();
	$line = $exc->getLine();
	$msg  = $exc->getMessage();

	$className = get_class($exc);
	$message = "$className in $file:$line:".PHP_EOL
		." $msg".PHP_EOL
		.$exc->getTraceAsString().PHP_EOL;

	$in_HTML = !isSet($argv);
	$newLine = $in_HTML? "<br/>\n": PHP_EOL;
	$prefix = $in_HTML? "\t<div class=\"error log\">": '';
	$suffix = $in_HTML? "</div>\n": PHP_EOL;
	echo $newLine, $prefix, $message, $suffix;

	exit(55);
}

/** Checks for a fatal error, work around for set_error_handler not working on fatal errors */
function check_for_fatal() {
	$error = error_get_last();
	if ( isSet($error) && is_array($error) && $error['type']===E_ERROR ) {
		log_error( $error['type'], $error['message'], $error['file'], $error['line'] );
	}
}

register_shutdown_function( 'check_for_fatal' );
set_error_handler( 'log_error' );
set_exception_handler( 'log_exception' );
ini_set( 'display_errors', 'off' );
error_reporting(E_ALL & ~E_NOTICE); // report all except notices

// vim: noexpandtab
