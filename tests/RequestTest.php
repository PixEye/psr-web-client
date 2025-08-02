<?php
/** Unit tests about Http\Request class
 * @since 2025.06.03
 */

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

// use PHPUnit\Framework\TestCase;

/** Test Http\Request class
 * @link https://docs.phpunit.de/en/
 */
final class RequestTest extends TestCase {

	const URL = 'https://me:secret@example.com:443/path/To/page?p1=hello&subject=world#anchor-3.5';

	public function testHeaderLine(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;

		$key = 'foo';
		$val = 'bar';

		$uri = new Http\Uri(self::URL);
		$req = new Http\Request($uri);
		$req = $req->withHeader($key, $val);

		$expected = $val;
		$result = $req->$method($key);
		$this->assertTrue(is_string($result), $entity.' should be a string');
		$this->assertSame($result, $expected, $entity." is wrong: '$result'");

		$key = 'FOO';
		$result = $req->$method($key);
		$this->assertTrue(is_string($result), $entity.' should be a string');
		$this->assertSame($result, $expected, $entity." is wrong: '$result'");

		$key = 'fOO';
		$val = 'baz';
		$req = $req->withHeader($key, $val);

		$expected = $val;
		$key = 'foo'; // not same case on purpose
		$result = $req->$method($key);
		$this->assertTrue(is_string($result), $entity.' should be a string');
		$this->assertSame($result, $expected, $entity." is wrong: '$result'");

		$expected = 'bar,baz';
		$req = $req
			->withHeader($key, 'bar')
			->withAddedHeader($key, 'baz');
		$result = $req->$method($key);
		$this->assertTrue(is_string($result), $entity.' should be a string');
		$this->assertSame($result, $expected, $entity." is wrong: '$result'");
	}

	public function testMethod(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;
		$uri = new Http\Uri(self::URL);
		$req = new Http\Request($uri);

		$expected = 'GET';
		$result = $req->$method();
		$this->assertTrue(is_string($result), $entity.' should be a string');
		$this->assertSame($result, $expected, $entity." is wrong: '$result'");
	}

	/* public function testQueryParams(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;
		$uri = new Http\Uri(self::URL);
		$req = new Http\Request($uri);

		$expected = explode('&', 'p1=hello&subject=world');
		$result = $req->$method();
		$this->assertTrue(is_array($result), $entity.' should be an array');
		$this->assertSame($result, $expected, $entity." is wrong: '$result'");
	} // */

	public function testRequestTarget(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;
		$uri = new Http\Uri(self::URL);
		$req = new Http\Request($uri);

		$expected = $uri->getPath().'?'.$uri->getQuery().'#'.$uri->getFragment();
		$result = $req->$method();
		$this->assertTrue(is_string($result), $entity.' should be a string');
		$this->assertSame($result, $expected, $entity." is wrong: '$result'");
	}

	public function testUri(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;
		$uri = new Http\Uri(self::URL);
		$req = new Http\Request($uri);

		$result = $req->$method();
		$this->assertTrue(is_object($result), $entity.' should be an object');
		$this->assertSame($result, $uri, $entity." is wrong: '$result'");
	}
}

// vim: noexpandtab sw=4 sts=4 tabstop=4
