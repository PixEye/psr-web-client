#!/bin/env php
<?php
/** Script to run unit tests
 * @since  2025.06.04
 * @author <jmoreau@pixeye.net>
 */

declare(strict_types=1);

require(__DIR__.'/../vendor/autoload.php');

$args = $argv;
$cmd = array_shift($args);
$eol = PHP_EOL;
$pauseInSec = 1;

$argc = count($args);
$test_folder = realPath(__DIR__.'/..').DIRECTORY_SEPARATOR.'tests';
if (in_array('-h', $args, true) || in_array('--help', $args, true)) {
	$stream = STDERR ?? STDOUT;
	$test_folder = str_replace(getCwd().'/', '', $test_folder);

	fwrite($stream, 'Usage:'.$eol);
	fwrite($stream, "\t$cmd [options] <PHP test file> [...]".$eol);
	fwrite($stream, $eol);
	fwrite($stream, 'Available options:'.$eol);
	fwrite($stream, "\t-h or --help to show this help message".$eol);
	fwrite($stream, "\t-v or --verbose to show more logs (mainly to debug)".$eol);
	fwrite($stream, $eol);
	fwrite($stream, 'Example:'.$eol);
	fwrite($stream, "\t$cmd $test_folder/*Test.php".$eol);
	exit(1);
}

$debugMode = false;
$logger = new Logger();
$subArgs = [];
if (in_array('-v', $args, true) || in_array('--verbose', $args, true)) {
	$debugMode = true;
	$logger->setDebugMode();
	$subArgs = ['-v'];
}

$failedCount = 0;
$globalCount = 0;
$s = $argc===1? '': 's';
$logger->debug("Detected $argc argument$s from CLI: ".implode(' ', $argv));
$logger->debug("Test folder: '$test_folder'");
if ($debugMode) -- $argc;

if (!$argc) {
	$args = glob($test_folder.DIRECTORY_SEPARATOR.'*Test.php');
}

$argc = count($args);
$fileCount = 0;
$selfBaseFile = basename(__FILE__);
$start = hrTime(true);
forEach ($args as $arg) {
	$firstChar = subStr($arg, 0, 1);
    if (is_dir($arg) || in_array($firstChar, ['-', '.']) || basename($arg)===$selfBaseFile) {
		-- $argc;
		continue; // ignore folders and special args
	}

    ++ $fileCount;
	$file = $arg;
	$className = basename($file, '.php');
    $logger->info("#$fileCount: $className...");

	require_once $file;
	$myTests = new $className($logger);
	$returnCode = $myTests->run($subArgs);
    $logger->debug("Returned code for $className #$fileCount: ".$returnCode);

	$failedCount += $myTests->failCount;
	$globalCount += $myTests->testCount;
	unset($myTests);

	if ($pauseInSec && $fileCount<$argc) {
		$s = $pauseInSec===1? '': 's';
		$logger->debug("Wait $pauseInSec second$s before to process next argument...");
	}

	$logger->insertBlankLine();
}

$s = $fileCount===1? '': 's';
$logger->debug("$fileCount file$s tested");

$duration = round((hrTime(true) - $start)/1e6, 0); // nanoseconds to milliseconds
if ($duration < 1000) {
	$timeUnit = 'ms';
} else {
	$duration /= 1000; // ms to seconds
	$timeUnit = 's';
}

$n = $globalCount;
$failPercent = $n? round(100 * $failedCount / $n): 0;
$successPercent = 100 - $failPercent;
if ( ! $n ) {
	$logger->error('No test detected');
} elseIf ($failedCount) {
	$s = $failedCount === 1? '': 's';
	$msg = "%d test$s failed out of %d (%d%% passed) in $duration $timeUnit";
	$msg = sprintf($msg, $failedCount, $n, $successPercent);
	$logger->warning($msg);
} else {
	$logger->info("Success: $n tests passed correctly out of $n (100%) in $duration $timeUnit");
}
$returnCode = $n? ($failedCount? 1: 0): 2;
$logger->debug('Returning error code: '.$returnCode);

unset($logger);
exit($returnCode);

// vim: noexpandtab sw=4 sts=4 tabstop=4
