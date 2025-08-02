<?php
/** Request.php
 * @since		2024.10.28 First commit of this file on 2024-10-28
 * @author		<jmoreau@pixeye.net>
 */

namespace Http;

use Helpers;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/** Simple Request class
 * @link      https://www.php-fig.org/psr/psr-7/
 * @since     2024.08.08
 * @author    <jmoreau@pixeye.net>
 */
class Request implements RequestInterface
{
	protected
        $body = null,
		$durationInMs = 0,
	    $headers = [],
	    $headersByKey = [],
	    $method = 'GET',
	    $protocol = 'HTTP',
	    $protVersion = '1.1',
	    $preserveHost = false,
	    $requestTarget = '/',
	    $uri = null,
		$webContextArr = [];

	/** Build a request object
	 * @param Uri $uri
	 * @param array $webContext See following link
	 * @link https://www.php.net/manual/en/context.http.php
	 */
    public function __construct(UriInterface $uri, array $webContextArr = []) {
		if (isSet($webContextArr['header']) && is_string($webContextArr['header']))
			$webContextArr['header'] = explode("\r\n", trim($webContextArr['header']));

        $this->body     = new MemoryStream($webContextArr['content'] ?? '');
        $this->headers  = $webContextArr['header'] ?? [];
        $this->method   = $webContextArr['method'] ?? 'GET';
        $this->protocol = strToUpper($uri->getScheme());
        $this->requestTarget = $uri->getPath().'?'.$uri->getQuery().'#'.$uri->getFragment();
        $this->uri           = $uri;
		$this->webContextArr = $webContextArr;

		forEach ($this->headers as $header) {
			$Tmp = explode(': ', $header);
			if (count($Tmp)>1) {
				$k = array_shift($Tmp);
				$v = implode(': ', $Tmp);
				if (!isSet($this->headersByKey[$k]))
					$this->headersByKey[$k] = [];
				$this->headersByKey[$k][] = $v;
			}
		}
    }

	/** Magic accessor
	 * @param string $propName The name of the wanted property
	 * @throws \RuntimeException
	 * @return mixed Matching property value
	 */
	public function __get(string $propName) {
		if (!property_exists($this, $propName))
			throw new \RuntimeException('Unknown property: '.$propName);

		return $this->$propName;
	}

	public function __toString() {
		$ms = $this->durationInMs;
		$addon = $ms? " took {$ms}ms": '';
		return $this->method.' '.$this->uri->__toString().$addon;
	}

	/** Get request body
	 * @return StreamInterface
	 */
	public function getBody(): StreamInterface {
		return $this->body;
	}

	/** Get web context array
	 * @return array
	 */
	public function getContextArray(): array {
		return $this->webContextArr;
	}

	/** Get request+response duration in milliseconds
	 * @return int
	 */
	public function getDurationInMs(): int {
		return $this->durationInMs;
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
		return implode(',', $this->getHeader($name));
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

	public function getRequestTarget(): string {
	    return $this->requestTarget;
	}

	public function getMethod(): string {
	    return $this->method;
	}

	public function getUri(): UriInterface {
	    return $this->uri;
	}

	public function hasHeader($name): bool {
		$name = Helpers::standardizeHeaderKey($name);

		return isSet($this->headersByKey[$name]);
	}

	/** Remember request duration in milliseconds
	 * @param float $ms
	 */
	public function setDuration(float $ms) {
		$this->durationInMs = (int) round($ms);
	}

	public function withBody(StreamInterface $body) {
	    $this->body = $body;
        $this->withAddedHeader('Content-length', $body->getSize());

	    return $this;
	}

	public function withAddedHeader($name, $value) {
	    if (!isSet($name) || !is_string($name) || !trim($name))
	        throw new \InvalidArgumentException('Invalid header name: '.var_export($name, true));

	    if (!isSet($value)) $value = '';

	    $name = Helpers::standardizeHeaderKey($name);
	    if (!isSet($this->headersByKey[$name]))
	        $this->headersByKey[$name] = [];

	    if (!is_array($value)) $value = [$value];
	    forEach ($value as $val) {
			if (!is_int($val) && !is_string($val))
				throw new \InvalidArgumentException("Invalid '$name' value: ".var_export($val, true));

	        $this->headersByKey[$name][] = $val;
	    }
		$this->headers[] = "$name: ".implode(',', $value);

	    return $this;
	}

	public function withHeader($name, $value) {
	    if (!isSet($name) || !is_string($name) || !trim($name))
	        throw new \InvalidArgumentException('Invalid header name: '.var_export($name, true));

	    if (!isSet($value)) $value = '';

	    $name = Helpers::standardizeHeaderKey($name);
	    // if (!isSet($this->headersByKey[$name])) // preserve old value(s)
	        $this->headersByKey[$name] = [];

	    if (!is_array($value)) $value = [$value];
	    forEach ($value as $val) {
			if (!is_int($val) && !is_string($val))
				throw new \InvalidArgumentException("Invalid '$name' value: ".var_export($val, true));

	        $this->headersByKey[$name][] = $val;
	    }

		forEach ($this->headers as $i => $header) {
			if (str_starts_with($header, $name.': '))
				unset($this->headers[$i]); // forget potential previous value(s)
		}
		$this->headers[] = "$name: ".implode(',', $value);

	    return $this;
	}

	public function withoutHeader($name) {
	    $name = Helpers::standardizeHeaderKey($name);
	    unset($this->headersByKey[$name]);

		forEach ($this->headers as $i => $header) {
			if (str_starts_with($header, $name.': '))
				unset($this->headers[$i]);
		}

	    return $this;
	}

	public function withMethod($method) {
	    $this->method = $method;

	    return $this;
	}

	public function withProtocolVersion($version) {
	    $this->protVersion = $version;

	    return $this;
	}

	public function withRequestTarget(string $requestTarget): RequestInterface {
	    $this->requestTarget = $requestTarget;

	    return $this;
	}

	public function withUri(UriInterface $uri, $preserveHost = false) {
	    $this->preserveHost = $preserveHost;
	    $this->uri = $uri;

	    return $this;
	}
}

// vim: noexpandtab
