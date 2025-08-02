<?php
/** Response.php
 * @link      https://www.php-fig.org/psr/psr-7/
 * @since     2024.08.08
 * @author    <jmoreau@pixeye.net>
 */

namespace Http;

use Helpers;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/** Simple Response class
 * @link      https://www.php-fig.org/psr/psr-7/
 * @since     2024.08.08
 * @author    <jmoreau@pixeye.net>
 */
class Response implements ResponseInterface
{
	public          int    $code = 0;
	public          array  $headersByKey = [];
	public          string $protocol = 'HTTPS';
	public readonly string $protVersion;
	public readonly string $reason; // reason phrase

	/** Constructor
	 * @param array $headers Advice is to pass the PHP variable: $http_response_header
	 * @param StreamInterface $body Response body
	 */
	public function __construct(
		public readonly array $headers,
		public StreamInterface $body
	) {
		forEach ($headers as $i => $header) {
			$Tmp = explode(': ', $header);
			if (count($Tmp)<2) {
				$this->headersByKey[$i] = $header; // Example: 0 => 'HTTP/1.1 503 Service Unavailable'
				if (!$i) {
					$words = explode(' ', $header);
					if (count($words)<2)
						throw new \UnexpectedValueException('Incomplete first response header: '.$header);

					$this->protocol = array_shift($words);
					$this->code     = array_shift($words);
					$this->reason   = implode(' ', $words);

					if (is_numeric($this->code)) $this->code = (int) $this->code;
					list($this->protocol, $this->protVersion) = explode('/', $this->protocol);
				}
			} else {
				$key = array_shift($Tmp);   // first part is the key
				$val = implode(': ', $Tmp); // remaining parts are the value

				$key = Helpers::standardizeHeaderKey($key);
				$this->headersByKey[$key] = $val;
			}
		}
	}

	/** To get an HTTP response header by its key, ignoring case
	 * @param string $key Example: 'content-type' or 'Content-Type' will produce same result (empty if not found)
	 * @return string
	 */
	public function header(string $key) {
		$key = Helpers::standardizeHeaderKey($key);

		return $this->headersByKey[$key] ?? '';
	}

	/** Get the title of an HTML or XML page or an empty string if not found
	 * @return string
	 */
	public function getPageTitle() {
		$title = '';
		$Line = explode("\n", subStr($this->body, 0, 10e3)); // in 10 first KB
		forEach ($Line as $line) {
			if (str_contains(strToLower($line), '<title')) {
				$title = html_entity_decode(trim(strip_tags($line)));
				$title = preg_replace('/&egrave;*/', 'è', $title);
				break;
			}
		}

		return $title;
	}

	/** Get request body
	 * @return StreamInterface
	 */
	public function getBody(): StreamInterface {
		return $this->body;
	}

	/** Get all HTTP response headers matching the given key
	 * @param string $name Header key
	 * @return array
	 */
	public function getHeader(string $name): array {
		$search = strToLower($name).': ';
		$len = strLen($search);

		$Match = [];
		forEach ($this->headers as $headerLine) {
			if (str_starts_with(strToLower($headerLine), $search)) {
				$Match[] = subStr($headerLine, $len);
			}
		}

		return $Match;
	}

	/** Return a comma separated list of response headers matching the given key
	 * @param string $name Header key
	 * @return string
	 */
	public function getHeaderLine(string $name): string {
		return implode(', ', $this->getHeader($name));
	}

	/** Return all HTTP response headers as an array with numerical keys
	 * @return array
	 */
	public function getHeaders(): array {
		return $this->headers;
	}

	/** Get HTTP version
	 * @return string
	 */
	public function getProtocolVersion(): string {
		return $this->protVersion;
	}

	public function getReasonPhrase(): string {
		return $this->reason ?? '';
	}

	/** Get request body size in bytes
	 * @return int|null
	 */
	public function getSize(): int {
		return $this->body->getSize();
	}

	/** Provide the HTTP response status code (examples: 200, 302, 400, 404, 500, 503, ...)
	 * @return int
	 */
	public function getStatusCode(): int {
		return $this->code;
	}

	/** Test wether a HTTP header key is present in the response or not
	 * @return bool
	 */
	public function hasHeader($name): bool {
		$name = Helpers::standardizeHeaderKey($name);

		return isSet($this->headersByKey[$name]);
	}

	/** Decode the JSON body and try to return a data object (or an array of objects)
	 * @throws \UnexpectedValueException in case of problem
	 * @return mixed
	 */
	public function jsonDecode() {
		$cType = $this->header('Content-type');
		if ($cType && !str_contains(strToUpper($cType), 'JSON'))
			throw new \UnexpectedValueException('Wrong response type (not JSON): '.$cType);

		if ( ! $this->body )
			throw new \UnexpectedValueException('Empty response body');

		$data = json_decode($this->body, false);
		if (json_last_error())
			throw new \UnexpectedValueException('JSON error: '.json_last_error_msg());

		return $data;
	}

	// Methods for PSR:

	public function withAddedHeader($name, $value): MessageInterface {
		$name = Helpers::standardizeHeaderKey($name);
		$this->headersByKey[$name] = $value;

		return $this;
	}

	public function withBody(StreamInterface $body): MessageInterface {
		$this->body = $body;

		return $this;
	}

	public function withHeader($name, $value): MessageInterface {
		$name = Helpers::standardizeHeaderKey($name);
		$this->headersByKey[$name] = $value;

		return $this;
	}

	public function withoutHeader($name): MessageInterface {
		$name = Helpers::standardizeHeaderKey($name);
		unset($this->headersByKey[$name]);

		return $this;
	}

	public function withProtocolVersion($version): MessageInterface {
		$this->protVersion = (string) $version;

		return $this;
	}

	public function withStatus($code, $reasonPhrase = ''): ResponseInterface {
		if (!isSet($code) || !is_int($code))
			throw new \InvalidArgumentException('Invalid code: '.var_export($code, true));

		if (!is_string($reasonPhrase))
			throw new \InvalidArgumentException('Invalid reason phrase: '.var_export($reasonPhrase, true));

		$this->code = $code;
		$this->reason = $reasonPhrase;

		return $this;
	}
}

// vim: noexpandtab
