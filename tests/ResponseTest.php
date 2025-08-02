<?php
/** Unit tests about Http\Response class
 * @since 2025.06.03
 */

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Http\ClientException;

// use PHPUnit\Framework\TestCase;

/** Test Http\Response class
 * @link https://docs.phpunit.de/en/
 */
final class ResponseTest extends TestCase {

	const
		URL_200 = 'https://example.com/',
		URL_404 = 'https://me:secret@example.com:443/path/To/page?p1=hello&subject=world#anchor-3.5';

	protected
		$client,   /** @property object $client   PSR Web Client */
		$response; /** @property object $response PSR response */

	/** Run the web request only once
	 * @param array $args Parameters given to the test: $argv or explode('&', $_SERVER['QUERY_STRING'])
	 * @return int Return code is 0 if all right, 1 if any test fails and 2 if no test detected
	 */
	public function run(array $args = []): int {
		$this->parseArgs($args);

		$this->start = hrTime(true);

		$webContextArray = [
			'header' => [
				'Connection: close',
			],
			'timeout' => 1.5,
		];

		$this->client = new Http\Client($this->logger);
		$uri = new Http\Uri(self::URL_200);
		$request = new Http\Request($uri, $webContextArray);
		$this->expectException(ClientException::class);
		$this->response = $this->client->sendRequest($request);

		return parent::run($args);
	}

	public function testHeader(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;
		$param = 'Content-type';

		$result = $this->response->$method($param);
		$this->assertTrue(is_array($result), $entity.' should be an array');

		$expected = '["text/html"]';
		$result = json_encode($result, JSON_UNESCAPED_SLASHES); // no PRETTY_PRINT here on purpose
		$this->assertSame($result, $expected, $entity."($param) is wrong: '$result'");
	}

	public function testHeaderLine(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;
		$param = 'Content-type';

		$result = $this->response->$method($param);
		$this->assertTrue(is_string($result), $entity.' should be a string');

		$expected = 'text/html';
		$this->assertSame($result, $expected, $entity."($param) is wrong: '$result'");
	}

	public function testHeaders(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;

		$result = $this->response->$method();
		$this->assertTrue(is_array($result), $entity.' should be an array');

		/* $expected = '[]';
		$result = json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
		$this->assertSame($result, $expected, $entity."() is wrong: '$result'"); // shows all headers */
	}

	public function testReasonPhrase(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;

		$result = $this->response->$method();
		$this->assertTrue(is_string($result), $entity.' should be a string');

		$expected = 'OK'; // Could be: 'Not Found';
		$this->assertSame($result, $expected, $entity." is wrong: '$result'");
	}

	public function testSize(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;

		$result = $this->response->$method();
		$this->assertTrue(is_int($result), $entity.' should be a int');

		$expected = 0;
		$this->assertTrue($result > $expected, $entity." is wrong: '$result'");
	}

	public function testStatusCode(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;

		$result = $this->response->$method();
		$this->assertTrue(is_int($result), $entity.' should be a int');

		$expected = 200;
		$this->assertSame($result, $expected, $entity." is wrong: '$result'");
	}
}

// vim: noexpandtab sw=4 sts=4 tabstop=4
