<?php
/** Helpers.php script contains several static method for general use
 * @since		2021.03.21 First commit of this file on 2021-36-21
 * @author		<jmoreau@pixeye.net>
 */

/** Helpers class
 * @since		2021.03.21 First commit of this file on 2021-36-21
 * @author		<jmoreau@pixeye.net>
 */
class Helpers {

	/** Add HTML links around URLs
	 * @param string $inputTxt
	 * @return string
	 */
	public static function addLinks(string $inputTxt) {
		return preg_replace('/([a-z]+:\/\/[^\s"\'\(\)<>]+)/i', '<a href="$1">$1</a>', $inputTxt);
	}

	/** Remove '@' character from the beginning of the array keys
	 * @param array $arr Array to cleanup
	 * @return array
	 */
	public static function array_cleanup(array $arr): array {
		if (isSet($arr['msg']) && isSet($arr['message']) && $arr['msg']==='Error: '.$arr['message']) {
			unset($arr['msg']);
		}

		forEach($arr as $k => $v) {
			if (subStr($k, 0, 1) === '@') {
				$nk = subStr($k, 1); // new key
				$arr[$nk] = $v;
				unset($arr[$k]);
			}
		}
		ksort($arr);

		return $arr;
	}

	/** Get first part of a path starting with a slash
	 * @param string $full_path
	 * @throws \UnexpectedValueException If the regExp is wrong (mainly for debugging)
	 * @return string Base path
	 */
	public static function base_path(string $full_path) {
		$ret = preg_replace('|^(/\w+).*$|', '${1}', $full_path);
		if (!isSet($ret)) {
			throw new \UnexpectedValueException('Invalid regular expression');
		}

		return $ret;
	}

	/** Center a string in a defined amount of character space
	 * @param string  $str
	 * @param integer $len
	 * @return string
	 */
	public static function center(string $str, int $len): string {
		$l = strLen($str);

		$nbSpaces = floor(($len - $l)/2); // left spaces
		if ($nbSpaces>0) $str = str_repeat(' ', $nbSpaces) . $str;

		$nbSpaces = $len - $l - $nbSpaces; // right spaces
		if ($nbSpaces>0) $str = $str . str_repeat(' ', $nbSpaces);

		return $str;
	}

	/** Convert a number into the engineer notation
	 * @param integer $number   A number
	 * @param integer $decimals Optional number of decimals. Negative values mean automatic (0 or 1)
	 * @return string
	 */
	public static function eng_format(int $number, int $decimals = -1): string {
		if ($decimals<0) $decimals = $number>1024? 1: 0;
		$factor = floor((strLen(abs($number)) - 1) / 3);
		$unit = subStr(' KMGTP', $factor, 1);

		return trim(sprintf("%.{$decimals}f", $number / pow(1024, $factor)) . $unit);
	}

	/** Count and format that count with number_format() and sprintf() to align on the right
	 * @param array $arr The array to count
	 * @param integer $maxLen Maximum string length expected
	 * @return string
	 */
	public static function formatCount(array $arr, int $maxLen = 6) {
		return self::intFormat(count($arr), $maxLen);
	}

	/** Return web user's preferred language as a string of 2 lower characters. Default is: 'en'
	 * @return string
	 */
	public static function getLanguage(): string {
		$language = empty($_SERVER['HTTP_X_LANG']) ? 'en': strToLower($_SERVER['HTTP_X_LANG']);

		if(!in_array($language, ['en', 'fr'])) { // TODO put the list of valid values in configuration file
			$language = 'en';
		}

		return $language;
	}

	/** Get a system group name by its GID
	 * @param integer $gid System GID
	 * @throws \UnexpectedValueException
	 * @return string Name of the matching system group
	 */
	public static function getSysGroupByGid(int $gid): string {
		$full_filename = '/etc/group';
		$result = self::grep(":$gid:", $full_filename);
		$result = rtrim($result);
		if (empty($result)) {
			throw new \UnexpectedValueException("GID $gid not found");
		}

		$nb_match = substr_count($result, "\n") + 1;
		if ($nb_match>1) {
			throw new \UnexpectedValueException('Too many matches: '.$nb_match);
		}

		$Part = explode(':', $result);

		return $Part[0];
	}

	/** Get a system user's login by its UID
	 * @param integer $uid System UID
	 * @throws \UnexpectedValueException
	 * @return string User's login
	 */
	public static function getSysUserByUid(int $uid): string {
		$full_filename = '/etc/passwd';
		$result = self::grep(":$uid:", $full_filename);
		$result = rtrim($result);
		if (empty($result)) {
			throw new \UnexpectedValueException("UID $uid not found");
		}

		$nb_match = substr_count($result, "\n") + 1;
		if ($nb_match>1) {
			throw new \UnexpectedValueException('Too many matches: '.$nb_match);
		}

		$Part = explode(':', $result);

		return $Part[0];
	}

	/** Search for a string in a file
	 * @param string  $searchStr     The string to search for
	 * @param string  $full_filename The full filename to search in
	 * @param array   $Option        Options keys can be: ignore_case (bool), max_length (int)
	 * @throws UnexpectedValueException
	 * @return string
	 */
	public static function grep(string $searchStr, string $full_filename, array $Option = []): string {
		if (strLen($searchStr)<2) {
			throw new \UnexpectedValueException("Search string ($searchStr) is too short!");
		}

		$fp = @fopen($full_filename, 'r');
		if (!$fp) {
			throw new \UnexpectedValueException("Cannot open($full_filename) for reading!");
		}

		$DefVal = [
			'ignore_case' => true,
			'max_length'  => 128,
		];
		foreach ($DefVal as $optName => $defVal) {
			$$optName = isSet($Option[$optName])? $Option[$optName]: $defVal;
		}

		$result = '';
		$searchFunc = $ignore_case? 'striPos': 'strPos';
		while (($line = fgets($fp, 4096)) !== false) {
			if ($searchFunc($line, $searchStr)!==false) {
				$line = strip_tags($line);
				if ($max_length) {
					$line = wordWrap($line, $max_length, "\n", true);
				}
				if (strPos($line, 'ERROR: ')!==false) {
					$line = '<span class="error">'.$line.'</span>';
				} else
				if (strPos($line, 'WARN: ')!==false) {
					$line = '<span class="warn">'.$line.'</span>';
				} else
				if (strPos($line, 'Success: ')!==false) {
					$line = '<span class="ok">'.$line.'</span>';
				}
				$result.= $line;
			}
		}
		if (!feof($fp)) {
			throw new \UnexpectedValueException("fgets() failed on '$full_filename'!");
		}
		fclose($fp);

		return $result;
	}

	/** Return a human readable file size
	 * @param string  $full_filename File name with its full path
	 * @param integer $decimals Optional number of decimals. Negative values mean automatic (0 or 1)
	 * @return string
	 * @throws \InvalidArgumentException in case of problem with any parameter
	 */
	public static function human_filesize(string $full_filename, int $decimals=-1): string {
		clearStatCache();
		$size = fileSize($full_filename);
		if ($size === false) {
			throw new \InvalidArgumentException('Unable to read file: '.$full_filename);
		}

		return self::eng_format($size, $decimals).'B';
	}

	/** Interpolates context values into the message placeholders wrapped into curly braces like: {this}.
	 * @param string $message
	 * @param array $context
	 * @return string
	 */
	public static function interpolate(string $message, array $context = []): string {
		// build a replacement array with braces around the context keys
		$substitutes = [];
		foreach ($context as $key => $val) {
			// check that the value can be cast to string
			if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
				$substitutes['{' . $key . '}'] = (string) $val;
			}
		}

		// interpolate replacement values into the message and return
		return strTr($message, $substitutes);
	}

	/** Count and format that count with number_format() and sprintf() to align on the right
	 * @param integer $num    The number to format
	 * @param integer $maxLen Maximum string length expected
	 * @return string
	 */
	public static function intFormat(int $num, int $maxLen = 6) {
		return sprintf("%$maxLen".'s', number_format($num));
	}

	/** Test if a number is between min and max values
	 * @param float|int $n
	 * @param float|int $min
	 * @param float|int $max
	 * @param  boolean  $strict Is n can be equal to $min or $max?
	 */
	public static function isBetween($n, $min, $max, bool $strict=false) {
		if ($min > $max) { // switch min & max
			$tmp = $min;
			$min = $max;
			$max = $tmp;
		}

		if ($strict) {
			return $min <  $n && $n <  $max;
		} else {
			return $min <= $n && $n <= $max;
		}
	}

	/** Encode data into JSON format with some more spaces
	 * @link https://www.php.net/manual/en/json.constants.php#constant.json-force-object
	 * @param mixed $data
	 * @param  int  $flags JSON flags as described in the PHP manual (see link)
	 * @return string
	 */
	public static function json_space_encode($data, $flags = null): string {
		if (is_null($flags))
			$flags = JSON_INVALID_UTF8_SUBSTITUTE | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES;

		$json = json_encode($data, $flags);
		$json = self::space_json($json);

		return $json;
	}

	/** Convert an amount of milliseconds into human readable string
	 * @param float $nbMs Number of milliseconds
	 * @return string Same amount of time but in hours:minutes:seconds.ms format
	 */
	public static function msToHMSms($nbMs): string {
		$num = round($nbMs);
		$ms = $num%1000; // remaining part
		$ret = "$num ms";

		if ($num>999) { // 1 second or more
			$num = floor($num/1000); // to seconds
			$ret = sprintf('0:00:%02d', $num);
			$ret.= $ms? ('.'.sprintf('%03d', $ms)): '';

			if ($num>59) { // 1 minute or more
				$seconds = $num%60; // remaining part
				$num = floor($num/60); // to minutes

				$minutes = $num%60; // remaining part
				$hours = floor($num/60); // to hours

				$ret = sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
			}
		}

		return $ret;
	}

	/** Send content-type 'app/JSON', print data in JSON format and exit
	 * @param mixed $data Data to expose
	 */
	public static function printJSON($data) {
		$code = 200;
		$status = 'OK';
		$protocol = isSet($_SERVER['SERVER_PROTOCOL'])? $_SERVER['SERVER_PROTOCOL']: 'HTTP/1.1';
		$isVerbose = is_array($data) && array_key_exists('verbose', $data) && $data['verbose'];
		if ($isVerbose) {
			error_log(__METHOD__.'():'.__LINE__.': just called. Verbose: '.var_export($isVerbose, true));
		}

		if (is_array($data) && !isSet($data['links']) && isSet($_SERVER) && is_array($_SERVER)) {
			$selfUrl = $_SERVER['REQUEST_SCHEME'].'://'.
				$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].$_SERVER['REQUEST_URI'];
			$data['links'] = ['self' => $selfUrl];
			if ($isVerbose) error_log(__METHOD__.'():'.__LINE__.': just added self URL: '.$selfUrl);
		}

		$flags = JSON_INVALID_UTF8_SUBSTITUTE | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
		$json = json_encode($data, $flags);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$code = 500;
			$status = 'Internal Server Error';
			$msg = 'JSON encoding failed';
			error_log(__METHOD__.'():'.__LINE__.": $msg: ".json_last_error_msg());
			error_log(__METHOD__.'():'.__LINE__.': data to json_encode() was: '.var_export($data, true));
			header($protocol.' '.$code.' '.$status, true, $code);
			// http_response_code($code);
			header('X-Error: '.$msg);
			exit();
		}

		$json.= "\n";
		$length = strLen($json);
		if ($isVerbose) error_log(__METHOD__.'():'.__LINE__.': JSON length = '.$length);

		if (headers_sent()) {
			$has = 'HTTP headers already sent';
			error_log(__METHOD__.'():'.__LINE__.': '.$has.' for '.PHP_SAPI);
			throw new \UnexpectedValueException($has);
		}

		header($protocol.' '.$code.' '.$status, true, $code);
		header('Content-Type: application/json');
		header('Content-Length: '.$length);

		print($json);

		if (!headers_sent()) {
			if (is_array($data) && array_key_exists('code', $data) && is_numeric($data['code'])) {
				$code = $data['code'];
			}
			$msg = 'HTTP headers not sent for '.PHP_SAPI." on $protocol. Code was ".$code;
			error_log(__METHOD__.'():'.__LINE__.': '.$msg);
			// throw new \UnexpectedValueException($msg);
		}
	}

	/** Even works on path that not dot already exist
	 * @link https://www.php.net/manual/en/function.realpath.php#84012
	 * @param string $path
	 * @return string
	 */
	public static function realPath(string $path) {
		if (file_exists($path)) return realPath($path);

		$path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
		$parts = explode(DIRECTORY_SEPARATOR, $path);
		$absolutes = [];

		forEach ($parts as $part) {
			if ('.'===$part) continue;

			if ('..'===$part && count($absolutes)) {
				array_pop($absolutes);
			} else {
				$absolutes[] = $part;
			}
		}

		return implode(DIRECTORY_SEPARATOR, $absolutes);
	}

	/** Beautify a JSON string (mainly add spaces)
	 * @param string $json
	 * @return string
	 */
	public static function space_json(string $json): string {
		$json = str_replace('},{', "},\n{", $json); // add carriage returns at special places
		$json = preg_replace('/([,;])([^ ])/', '$1 $2', $json); // add spaces after commas & semicolons
		$json = preg_replace('/(":)([^ \/])/', '$1 $2', $json); // add spaces after quotes followed by colons
		// $json = str_replace('<uuid: ', '<uuid:', $json); // delete space from group tags
		$json = str_replace('[{', "[\n{", $json); // add carriage returns at special places
		// $json = str_replace('}]', "}\n]", $json); // add carriage returns at special places

		return $json;
	}

	/** To ignore (header keys) case
	 * @param string $key
	 */
	public static function standardizeHeaderKey(string $key) {
		return ucWords(strToLower($key), '-');
	}

	/** Transform plain text to HTML
	 * @param string  $txt
	 * @return string HTML
	 */
	public static function txtToHtml(string $txt): string {
		return htmlSpecialChars($txt, ENT_NOQUOTES);
	}
}

// vim: noexpandtab
