<?php
/** Simple TestCase class
 * @since 2025.06.02
 */

/** This class simulate PHP Unit without having to install it */
abstract class TestCase {

	protected
		$argsParsed = false, /** @property  bool  $argsParsed   True if arguments were already parsed */
		$expectExcept = '',  /** @property string $expectExcept Exception class name to expect */
		$failCount = 0,      /** @property  int   $failCount    Failed test counter */
		$logger,             /** @property Logger $logger       A logger object */
		$testCount = 0,      /** @property  int   $testCount    Global test counter */
		$start = 0;          /** @property  int   $start        Starting time */

	public function __construct(Logger $logger) {
		$this->logger = $logger;
	}

	/** Make self properties accessible to read
	 * @throws \InvalidArgumentException If the requested key is unknown
	 * @return mixed
	 */
	public function __get(string $key): mixed {
		if (!property_exists($this, $key)) {
			throw new \InvalidArgumentException("Unknown property '$key'");
		}

		return $this->$key;
	}

	/** Check that a test result is false, otherwise print optional comment
	 * @param bool $testResult The test
	 */
	public function assertFalse(bool $testResult, string $comment = ''): void {
		$this->assertTrue(!$testResult, $comment);
	}

	/** Check that a first value is equal to another, otherwise print optional comment
	 * @param mixed $a First value
	 * @param mixed $b Second value
	 */
	public function assertSame(mixed $a, mixed $b, string $comment = ''): void {
		$this->assertTrue($a === $b, $comment);
	}

	/** Check that a test result is true, otherwise print optional comment
	 * @param bool $testResult The test
	 */
	public function assertTrue(bool $testResult, string $comment = ''): void {
		$className = get_class($this);
		$testNum = sprintf('%04d', ++ $this->testCount);
		if (!$testResult) {
			++$this->failCount;
			$comment = rTrim($comment);
			if (!$comment) $comment = 'failed (NOK)';
			$this->logger->warning("$className #$testNum/ $comment");
		} else {
			$this->logger->debug("$className #$testNum passed (ok)");
		}
	}

	/** Prepare to receive an exception of the given class name
	 * @param string $exceptionClassName The exception class name that shall be caught in a coming test
	 */
	public function expectException(string $exceptionClassName) {
		$this->expectExcept = $exceptionClassName;
	}

	/** Parse arguments, only once (next runs do nothing)
	 * @param array $args Parameters given to the test: $argv or explode('&', $_SERVER['QUERY_STRING'])
	 */
	protected function parseArgs(array $args) {
		if ($this->argsParsed) return; // parse them only once

		$traces = debug_backTrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
		$caller = end($traces);
		$callerScript = basename($caller['file']);

		forEach ($args as $arg) {
			$arg = basename($arg);
			switch ($arg) {
				case '-h':
				case '--help':
					$this->logger->info('Possible options are:');
					$this->logger->info("\t-h or --help to show this help message");
					$this->logger->info("\t-v or --verbose for a more verbose output (to debug)");
					exit(9);

				case '-v':
				case '--verbose':
					$n = count($args);
					$this->logger->setDebugMode();
					$this->logger->debug("Detected $n arguments: ".implode(' ', $args));
					break;

				case 'php': // ignore those arguments
				case $callerScript:
					break;

				default:
					$this->logger->warning('Unknown option: '.$arg);
			}
		}
		$this->logger->debug('Caller full script name is: '.$caller['file']);

		$this->argsParsed = true;
	}

	/** Run all tests from child class
	 * @param array $args Parameters given to the test: $argv or explode('&', $_SERVER['QUERY_STRING'])
	 * @return int Return code is 0 if all right, 1 if any test fails and 2 if no test detected
	 */
	public function run(array $args = []): int {
		$this->parseArgs($args);

		$className = get_class($this);
		$methods = get_class_methods($this);
		if (!$this->start) $this->start = hrTime(true);
		forEach ($methods as $method) {
			if (!str_starts_with($method, 'test')) continue; // ignore non test methods

			$this->logger->info("Running $className->$method()...");
			try {
				$this->$method();
			}
			catch (\Throwable $error) {
				++ $this->testCount;
				$className = $this->expectExcept;
				$code = $error->getCode();
				$file = $error->getFile();
				$line = $error->getLine();
				$file = basename($file);
				$reason = $error->getMessage();

				if ($className && $className===get_class($error)) {
					$msg = 'Just caught %s in %s:%d (code %d) as expected: ';
					$msg = sprintf($msg, $className, $file, $line, $code);
					$this->logger->info($msg.$reason);
					$this->expectExcept = ''; // reset
				} else {
					++ $this->failCount;
					$className = get_class($error);
					$msg = 'Just caught %s in %s:%d (code %d): ';
					$msg = sprintf($msg, $className, $file, $line, $code);
					$this->logger->warning($msg.$reason);
				}
				$this->logger->insertBlankLine();
			}
		}

		$duration = round((hrTime(true) - $this->start)/1e6, 0); // nanoseconds to milliseconds
		if ($duration < 1000) {
			$timeUnit = 'ms';
		} else {
			$duration /= 1000; // ms to seconds
			$timeUnit = 's';
		}

		$n = $this->testCount;
		$failPercent = $n? round(100 * $this->failCount / $n): 0;
		$successPercent = 100 - $failPercent;
		if ( ! $n ) {
			$this->logger->error('No test detected');
		} else
		if ($this->failCount) {
			$s = $this->failCount === 1? '': 's';
			$msg = "%s: %d test$s failed out of %d (%d%% passed) in $duration $timeUnit";
			$msg = sprintf($msg, $className, $this->failCount, $n, $successPercent);
			$this->logger->warning($msg);
		} else {
			$msg = "Success about $className: $n tests passed correctly out of $n (100%)";
			$this->logger->info($msg." in $duration $timeUnit");
		}

		return $n? ($this->failCount? 1: 0): 2;
	}
}

// vim: noexpandtab sw=4 sts=4 tabstop=4
