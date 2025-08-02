<?php
/** Logger.php
 * @see       PHP Standard Recommendation #3: https://www.php-fig.org/psr/psr-3/
 * @since     2021.12-21 First commit of this file on 2021-12-21
 * @author    <jmoreau@pixeye.net>
 */

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;

/** Logger class compliant with PSR-3 (see following lines)
 * @see       PHP Standard Recommendation #3: https://www.php-fig.org/psr/psr-3/
 * @since     2021.12-21 First commit of this file on 2021-12-21
 * @author    <jmoreau@pixeye.net>
 */
class Logger implements LoggerInterface
{
	const
		LOG_PREFIX_DATE_FORMAT = 'H:i:s',
		MAX_CHARS_PER_LOG = 500,
		MAX_CHARS_PER_LOG_LINE = 120;

	// ========== Protected properties ==========

	protected
		$baseUrl = '',        /** @var string $baseUrl */
		$dir = '',            /** @var string $dir */
		$error_count = 0,     /** @var int $error_count */
		$filename = '',       /** @var string $filename */
		$logURL = '',         /** @var string $logURL */
		$html_buffer = '',    /** @var string $html_buffer */
		$log_size = 0,        /** @var int $log_size Size in bytes */
		$stream,              /** @var resource $stream */
		$isDebugging = false; /** @var boolean $isDebugging */

	// ========== Public methods ==========

	/** Constructor */
	public function __construct() {
		if (!isSet($_SERVER) || !is_array($_SERVER) || !isSet($_SERVER['SERVER_NAME'])) {
            $this->baseUrl = '';
        } else {
            $this->baseUrl =
                $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].':'.
                $_SERVER['SERVER_PORT'].$_SERVER['REQUEST_URI'];
        }
		$this->stream = defined('STDOUT')? constant('STDOUT'): null;
		$this->info('New '.__CLASS__.' created');
	}

	/** Destructor (called when this is unset) */
	public function __destruct() {
		$l = $this->log_size; // was: mb_strLen($this->html_buffer, 'UTF-8');
		// $nbl = substr_count($this->html_buffer, "\n");
		$this->html_buffer = ''; // free some memory
		$context = [
			'mt' => __METHOD__,
			'nbe' => $this->error_count,
			// 'nbl' => $nbl,
			'size' => Helpers::eng_format($l),
		];
		// $this->info('{mt}() buffer contained {nbl} line(s) & {nbe} error(s) in a total of {size}B.', $context);
		$this->info('{mt}() buffer contained {nbe} error(s) in a total of {size}B.', $context);
	}

	/** Magic getter is utilized for reading data from inaccessible properties
	 * @param string $k The name of the property
	 * @throws \InvalidArgumentException
	 * @return mixed Matching value
	 */
	public function __get($k) {
		switch($k) {
			case 'errCnt':
			case 'warnCnt':
				return $this->error_count;

			case 'full_filename':
				return $this->dir.DIRECTORY_SEPARATOR.$this->filename;

			case 'logURL':
				return $this->logURL;

			case 'filename':
			case 'error_count':
			case 'log_size':
				return $this->$k;

			default:
				throw new \InvalidArgumentException('Invalid key to get value of: '.$k);
		}
	}

	/** Add a paragraph to the (HTML) buffer
	 * @param string $html The new (HTML) paragraph
	 */
	public function addToBuffer(string $html): void {
		$this->html_buffer .= $html;
	}

	/** System is unusable.
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function emergency($message, array $context = []): void {
		$this->log(LogLevel::EMERGENCY, 'EMERGENCY: '.$message, $context);
	}

	/** Action must be taken immediately.
	 * Example: Entire website down, database unavailable, etc. This should
	 * trigger the SMS alerts and wake you up.
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function alert($message, array $context = []): void {
		$this->log(LogLevel::ALERT, 'ALERT: '.$message, $context);
	}

	/** Critical conditions.
	 * Example: Application component unavailable, unexpected exception.
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function critical($message, array $context = []): void {
		$this->log(LogLevel::CRITICAL, 'CRITICAL: '.$message, $context);
	}

	/** Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function error($message, array $context = []): void {
		$this->log(LogLevel::ERROR, 'ERROR: '.$message, $context);
	}

	/** Exceptional occurrences that are not errors.
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things
	 * that are not necessarily wrong.
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function warning($message, array $context = []): void {
		$this->log(LogLevel::WARNING, 'WARN: '.$message, $context);
	}

	/** Normal but significant events.
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function notice($message, array $context = []): void {
		$this->log(LogLevel::NOTICE, 'Success: '.$message, $context);
	}

	/** Interesting events (examples: User logs in, SQL logs...)
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function info($message, array $context = []): void {
		$this->log(LogLevel::INFO, $message, $context);
	}

	/** Detailed debug information.
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function debug($message, array $context = []): void {
		if ($this->isDebugging) {
			$this->log(LogLevel::DEBUG, 'Debug: '.$message, $context);
		}
	}

	/** Insert a blank line in the output & in the potential email */
	public function insertBlankLine(): void {
		if (isSet($_SERVER['TERM']) || isSet($_SERVER['TERM_PROGRAM'])) { // if ran from a terminal (not from cron)
			print(PHP_EOL);
		}
		$this->html_buffer.= "\n";
		$this->log_size += 1;
	}

	/** Get internal HTML buffer
	 * @return string
	 */
	public function getBuffer(): string {
		return $this->html_buffer;
	}

	/** Logs with an arbitrary level.
	 * @param mixed $level
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function log($level, $message, array $context = []): void {
		$html = rtrim(Helpers::interpolate($message, $context));
		if ( !$html ) return; // do not write an empty message. See $this->insertBlankLine() if needed

		// $allowed_tags = ['http', 'uuid']; // allow TTL tags
		$html = str_replace('000 milli', ' ', $html); // simplify
		$html = str_replace('000ms'    , 's', $html); // turn milliseconds into seconds
		$txt = $html; // was: strip_tags($timedHtml, $allowed_tags); // cannot work with tags like: <uuid:...>
		$timedHtml = date(self::LOG_PREFIX_DATE_FORMAT).' - '.$html;
		$timedHtml = wordWrap($timedHtml, self::MAX_CHARS_PER_LOG_LINE);

		$cssClass = '';
		$this->stream = (isSet($this->stream) && defined('STDOUT'))? constant('STDOUT'): null;

		switch($level) {
			case LogLevel::EMERGENCY:
			case LogLevel::ALERT:
			case LogLevel::CRITICAL:
				if (!$cssClass) $cssClass = 'fatal';
				error_log($txt);
				// no 'break' here on purpose

			case LogLevel::ERROR:
				if (!$cssClass) $cssClass = 'error';
				// no 'break' here on purpose

			case LogLevel::WARNING:
				++ $this->error_count;
				if (!$cssClass) $cssClass = 'warn';
				if (isSet($this->stream) && PHP_SAPI==='cli' && defined('STDERR')) {
					$this->stream = constant('STDERR');
				}
				// no 'break' here on purpose

			case LogLevel::NOTICE:
				if (!$cssClass) $cssClass = 'ok'; // successes
				// no 'break' here on purpose

			case LogLevel::INFO:
			case LogLevel::DEBUG:
				// if (striPos($txt, '<http') !== false) $txt = Helpers::txtToHtml($txt);

				$timedHtml = htmlSpecialChars($timedHtml, ENT_NOQUOTES);
				if ($cssClass) {
					$timedHtml = '<span class="'. $cssClass. '">' . $timedHtml . '</span>';
				}
				$this->html_buffer.= $timedHtml."\n";
				$this->log_size += strLen($timedHtml) + 1;

				if (isSet($_SERVER['TERM']) || isSet($_SERVER['TERM_PROGRAM'])) { // if ran from a terminal (not from cron)
					$txt = html_entity_decode($txt);
					$timedTxt = date(self::LOG_PREFIX_DATE_FORMAT).' - '.$txt;
					$timedTxt = subStr($timedTxt, 0, self::MAX_CHARS_PER_LOG);
					if (isSet($this->stream) && $this->stream) {
						$nbWrittenBytes = @fwrite($this->stream, $timedTxt.PHP_EOL);
						if ($nbWrittenBytes===false) unset($this->stream); // prevent other warnings
					} else {
						print($timedTxt.PHP_EOL);
					}
				} elseIf (isSet($_SERVER) && is_array($_SERVER) && isSet($_SERVER['HTTP_HOST'])) {
					$timedHtml = Helpers::addLinks($timedHtml);
					print("\t  ".'<div class="log">'.$timedHtml.'</div>'.PHP_EOL);
				}

				if ($this->filename!=='') {
					$oldDir = getcwd();
					$baseDir = realPath(__DIR__.'/../../..');
					$is_ok = chDir($baseDir);
					if (!$is_ok) {
						throw new \UnexpectedValueException('Could not chDir() to: '.$baseDir);
					}

					umask(0022); // protect created files from the beginning
					$dir = implode(DIRECTORY_SEPARATOR, ['var', 'log', 'www']);
					if (!is_dir($dir)) {
						$is_ok = mkdir($dir, 0755, true);
						if (!$is_ok) {
							throw new \UnexpectedValueException('Cannot make dir: '.$dir);
						}
					}
					$this->dir = realpath($dir);

					$logFnWithPath = $dir.DIRECTORY_SEPARATOR.$this->filename;
					$bytes_written = file_put_contents($logFnWithPath, $this->html_buffer, FILE_APPEND);
					if (!$bytes_written) {
						error_log('Could not write to: '.$logFnWithPath);
					} else {
						$this->html_buffer = '';
					}
					chDir($oldDir);
				}
				break;

			default:
				throw new \InvalidArgumentException('Unknown log level!');
		}
	}

	/** Prefix a message with the current date string & return the whole string
	 * @param string $msg The message to print
	 * @return string
	 */
	public static function prefixWithTime($msg) {
		return date(self::LOG_PREFIX_DATE_FORMAT).' - '.rtrim($msg);
	}

	/** Set debug mode (or not)
	 * @param bool $debug
	 */
	public function setDebugMode(bool $debug = true) {
		$this->info('Set debug mode to: '.var_export($debug, true));
		$this->isDebugging = $debug;
	}

	/** Set the output file name
	 * @throws \InvalidArgumentException if the given filename is invalid
	 * @throws \UnexpectedValueException if the filename was already set
	 */
	public function setFilename(string $filename) {
		if (rtrim($filename)==='') {
			throw new \InvalidArgumentException('Empty log filename given');
		}

		if (rtrim($this->filename)!=='') {
			throw new \UnexpectedValueException('Log filename was already set');
		}

		$this->filename = $filename;
		$this->info("Log filename set to: '$filename'");

		$this->logURL = $this->baseUrl."log/$filename";
		$this->info("Log URL set to:\n ".$this->logURL);
	}
}

// vim: noexpandtab
