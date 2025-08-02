<?php
/** Client.php
 * @since		2024.10.28 First commit of this file on 2024-10-28
 * @author		<jmoreau@pixeye.net>
 */

namespace Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/** (web) Client class
 * @since		2024.10.28 First commit of this file on 2024-10-28
 * @author		<jmoreau@pixeye.net>
 */
class Client implements ClientInterface {

	const
		JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
		MAX_COOKIE_LENGTH = 1024;

	protected $cookieCouples = []; /** @var array  $cookieCouples Cookie key/value couples */
	protected $debugMode = false;  /** @var bool   $debugMode If activated (true), logs more information */
	protected $dnsCache = [];      /** @var array  $dnsCache FQDN/IP couples */
	protected $errors = [];        /** @var array  $errors Fatal error messages (if any) */
	protected $lastUrl = '';       /** @var string $lastUrl The last used URL */
	protected $logger;             /** @var Logger $logger A logger object or null */
	protected $requestCounter = 0; /** @var  int   $requestCounter Counts the number of requests */
	protected $warnings = [];      /** @var array  $warnings Warning messages (if any) */

	public function __construct(LoggerInterface & $logger = null) {
		if (isSet($logger)) {
			$this->logger = $logger;
			$logger->info('Web client created');
		}
	}

	public function __destruct() {
		if (isSet($this->logger)) {
			$this->resetDnsCache();

			$nbC = count($this->cookieCouples);
			$sc = $nbC===1? '': 's';
			$nbR = $this->requestCounter;
			$sr = $nbR===1? '': 's';
			$this->logger->info("Web client did $nbR request$sr and used $nbC cookie$sc");
		}
	}

	/** Magic getter
	 * @param string $key Property key name to get
	 * @throws \InvalidArgumentException If property name is unknown
	 * @return mixed The type depends on the property key name
	 */
	public function __get(string $key) {
		if (!property_exists($this, $key))
			throw new \InvalidArgumentException("property '$key' not found");

		return $this->$key; // all props are readable (but not editable outside of this class)
	}

	public function resetDnsCache() {
		$n = count($this->dnsCache);
		if (!$n) return;

		if (isSet($this->logger)) {
			$s = $n===1? '': 'es';
			$this->logger->debug("Clean up $n IP address$s from DNS cache");
		}
		$this->dnsCache = [];
	}

	/** Sends a request and returns a response.
	 * @param Request $request
	 * @throws ClientException In case of trouble while doing the request
	 * @return ResponseInterface
	 */
	public function sendRequest(RequestInterface $request): Response {
		$this->errors   = []; // reset errors
		$this->warnings = []; // reset warnings

		$uri = $request->getUri();
		$fqdn = rTrim($uri->getHost()); // FQDN = Fully Qualified Domain Name
		if (!isSet($this->dnsCache[$fqdn]) && preg_match('/[a-z]/i', $fqdn)) { // if FQDN contains a letter
			if (isSet($this->logger)) {
				$this->logger->debug("Look up for '$fqdn' IP address...");
			}
			$ip_addr = getHostByName($fqdn); // check if the FQDN DNS resolve to a numerical IP address

			if ($ip_addr===$fqdn) {
				throw new ClientException("Cannot resolve '$fqdn' to an numerical IP address");
			}

			$this->dnsCache[$fqdn] = $ip_addr;
			if (isSet($this->logger)) {
				$this->logger->debug("Found numerical IP address for '$fqdn': '$ip_addr'");
			}
		}

		/* if (isSet($this->dnsCache[$fqdn])) {
			$uri = $uri->withHost($this->dnsCache[$fqdn]);
			$request = $request->withHeader('Host', $fqdn);
			$request = $request->withUri($uri);
		} // Commented because it does not work very well */
		$url = trim((string) $uri);

		$bodySize = $request->getBody()->getSize();
		if (isSet($bodySize) && $bodySize>0)
			$request = $request->withHeader('Content-length', $bodySize);

		$webContextArr = $request->getContextArray();

		// Present previous cookies to this new request
		if (count($this->cookieCouples)) {
			$cookieCouples = [];
			forEach ($this->cookieCouples as $k => $v) {
				$cookieCouples[] = "$k=$v";
			}

			$cookieValues = implode('; ', $cookieCouples);
			$cookieLen = strLen($cookieValues);
			if ($cookieLen>=self::MAX_COOKIE_LENGTH) {
				$this->warnings[] = 'Cookie length is: '.$cookieLen;
			}

			$webContextArr['header'][] = 'Cookie: '.$cookieValues;
		}

		if (isSet($this->logger)) {
			$userInfo = $uri->getUserInfo();
			$pos = strPos($userInfo, ':');
			if ($pos!==false) {
				$user = subStr($userInfo, 0, $pos);
				$uri2 = $uri->withUserInfo($user.':*private*');
				$pub_url = (string) $uri2;
			} else {
				$pub_url = $url;
			}
			$public_url = preg_replace('/(pass[a-z_0-9]*)=[^&]*/i', '$1=*private*', $pub_url); // hide passwords
			$public_url = preg_replace(  '/(pw[a-z_0-9]*)=[^&]*/i', '$1=*private*', $public_url);
			$this->logger->debug('Request (public) URL: '.$public_url);

			$reqOptions = json_encode($webContextArr, self::JSON_FLAGS);
			$pubReqOptions = preg_replace('/(pass[a-z_0-9]*)=[^&]*/i', '$1=*private*', $reqOptions); // hide passwords
			$pubReqOptions = preg_replace(  '/(pw[a-z_0-9]*)=[^&]*/i', '$1=*private*', $pubReqOptions);
			$this->logger->debug('Request (public) options: '.$pubReqOptions);
		}

		/* if (isSet($webContextArr['timeout']) && $webContextArr['timeout']) {
			$seconds = floor($webContextArr['timeout']);
			$microseconds = round(($webContextArr['timeout'] - $seconds) * 1e6);
			$stream = '???'; // <---
			$is_ok = stream_set_timeout($stream, $seconds, $microseconds);
			if (!$is_ok) {
				$msg = 'Could not set timeout';
				if (isSet($this->logger)) {
					$this->logger->warning($msg);
				} else {
					$warnings[] = $msg;
				}
			}
		} // */

		$webContext = stream_context_create(['http' => $webContextArr]);
		++ $this->requestCounter;
		$this->lastUrl = $url;

		$start = hrTime(true);
		try {
			if ($this->debugMode) {
				$responseBody = file_get_contents($url, false, $webContext); // <--
			} else {
				$responseBody = @file_get_contents($url, false, $webContext); // <--
			}
			// TODO: replace this by fopen() call and use a FileStream for response
		}
		catch (\Throwable $error) {
			$code = $error->getCode();
			$reason = $error->getMessage();
			if ($code<100 || $code>=600) {
				throw new ClientException($reason, $code, $error);
			}

			if (isSet($this->logger))
				$this->logger->error($reason);
			else
				$this->errors[] = $reason;

			$emptyStream = new MemoryStream('');
			$response = new Response([], $emptyStream);
			$response = $response->withStatus($code, $reason);

			return $response;
		}

		$duration = round((hrTime(true) - $start)/1e6); // nanoseconds to milliseconds
		$request->setDuration($duration);

		if ($responseBody===false) {
			if (isSet($this->logger))
				$this->logger->error('Request returned body: false');
			else
				$this->warnings[] = 'Request returned body: false';
			$responseBody = '';
		}

		if (!isSet($http_response_header)) {
			$this->errors[] = 'Request failed, no response headers';
			if (isSet($this->logger))
				$this->logger->error('Request failed, no response headers');

			$msg = implode("\n", $this->errors);
			$msg = rTrim($request->__toString()."\n ".$msg);
			$msg.= "\n Request headers were: ".json_encode($request->getHeaders(), self::JSON_FLAGS);

			$bodySize = $request->getBody()->getSize() ?? 1e6;
			if ($bodySize && $bodySize<1e3)
				$msg.= "\n\nPayload ($bodySize B): ".$request->getBody()->__toString();
			elseIf ($bodySize)
				$msg.= "\n\nPayload ($bodySize B) starts with: ".
					subStr($request->getBody()->__toString(), 0, 1e2);

			// throw new ClientException($msg, 0);
			$http_response_header = [];
		}

		$response = new Response($http_response_header, new MemoryStream($responseBody));
		unset($responseBody); // free some memory

		$httpCode = $response->getStatusCode();
		$reason = $response->getReasonPhrase();

		if ($httpCode >= 302) {
			if (isSet($this->logger))
				$this->logger->warning('HTTP response status: '.$httpCode.' '.$reason);
			else
				$this->warnings[] = 'HTTP response status: '.$httpCode.' '.$reason;
		}

		// Cookie management:
		$cookieStr = 'set-cookie: '; // headers key to look for
		$cookieStrLn = strLen($cookieStr);
		forEach ($response->headers as $header) {
			if (str_starts_with(strToLower($header), $cookieStr)) {
				$val = subStr($header, $cookieStrLn);
				$endPos = strPos($val, '; '); // ignore options, keep only key=val couples
				if ($endPos===false) $endPos = null;
				$couple = subStr($val, 0, $endPos);
				$pos = strPos($couple, '=');
				if ($pos===false) {
					if (isSet($this->logger))
						$this->logger->warning("Did not find '=' in cookie value: ".$couple);
					else
						$this->warnings[] = "Did not find '=' in cookie value: ".$couple;
					continue;
				}

				$key = subStr($couple, 0, $pos);
				$val = subStr($couple, $pos + 1);
				$this->cookieCouples[$key] = $val;
			}
		}

		if (isSet($this->logger) && count($this->errors)) {
			$msg = implode("\n", $this->errors);
			$msg = rTrim($request->__toString()."\n ".$msg);
			$msg.= "\n Request headers were: ". json_encode($request->getHeaders() , self::JSON_FLAGS);
			$msg.= "\n Response headers were: ".json_encode($response->getHeaders(), self::JSON_FLAGS);

			$bodySize = $request->getBody()->getSize() ?? 1e6;
			if ($bodySize && $bodySize<1e3)
				$msg.= "\n\nPayload ($bodySize B) was: ".$request->getBody()->__toString();
			elseIf ($bodySize)
				$msg.= "\n\nPayload ($bodySize B) starts with: ".
					subStr($request->getBody()->__toString(), 0, 1e2);

			$this->logger->error($msg);
		}

		return $response;
	}
}

// vim: noexpandtab
