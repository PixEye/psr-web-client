<?php
/** Unit tests about Http\Client class
 * @since 2025.06.03
 */

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

// use Http\ClientException;
// use PHPUnit\Framework\TestCase;

/** Test Http\Client class
 * @link https://docs.phpunit.de/en/
 */
final class ClientTest extends TestCase {

	const URL = 'https://me:secret@example.com:443/path/To/page?p1=hello&subject=world#anchor-3.5';

	public function testSendRequest(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$webContextArray = [
			'header' => [
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
				'Accept-Language: en-US,en;q=0.5',
				'Connection: close',
				'User-agent: PSR Web Client Test/1.0',
			],
			'timeout' => 5,
		];

		$client  = new Http\Client($this->logger);

		$uri     = new Http\Uri('https://example.com/');
		$request = new Http\Request($uri, $webContextArray);
		$response = $client->sendRequest($request);

		$this->assertTrue(is_object($response), $entity.' should be an object');

		$msg = $entity.' should be an instance of Http\Response but got '.get_class($response);
		$this->assertTrue($response instanceof Http\Response, $msg);

		$result = $response->getStatusCode();
		$this->assertSame($result, 200, "response code should be 200 but got: '$result'");

		$result = $response->getReasonPhrase();
		$this->assertSame($result, 'OK', "response status phrase should be OK but got '$result'");

		$uri     = new Http\Uri(self::URL);
		$request = new Http\Request($uri, $webContextArray);
		// $this->expectException(ClientException::class);
		$response = $client->sendRequest($request);

		$result = getType($response);
		$this->assertTrue(is_object($response), $entity." should be an object but got '$result'");

		$result = get_class($response);
		$msg = $entity." should be an instance of Http\Response but got '$result'";
		$this->assertTrue($response instanceof Http\Response, $msg);
	}
}

// vim: noexpandtab sw=4 sts=4 tabstop=4
