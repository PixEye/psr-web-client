<?php
/** Unit tests about Http\Uri class
 * @since 2025.06.02
 */

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

// use PHPUnit\Framework\TestCase;

/** Test Http\Uri class
 * @link https://docs.phpunit.de/en/
 */
final class UriTest extends TestCase {

	const URL = 'HTTPS://me:secret@example.com:443/path/To/page?p1=hello&subject=world#anchor-3.5';

	public function testAuthority(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;
		$uri = new Http\Uri(self::URL);

		$result = $uri->$method();
		$this->assertTrue(is_string($result), $entity.' should be a string');
		$this->assertSame($result, 'me:secret@example.com', $entity." is wrong: '$result'");
	}

	public function testFragment(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;
		$uri = new Http\Uri(self::URL);

		$result = $uri->$method();
		$this->assertTrue(is_string($result), $entity.' should be a string');
		$this->assertSame($result, 'anchor-3.5', $entity." is wrong: '$result'");
	}

	public function testHost(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;
		$uri = new Http\Uri(self::URL);

		$result = $uri->$method();
		$this->assertTrue(is_string($result), $entity.' should be a string');
		$this->assertSame($result, 'example.com', $entity." is wrong: '$result'");
	}

	public function testPath(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;
		$uri = new Http\Uri(self::URL);

		$result = $uri->$method();
		$this->assertTrue(is_string($result), $entity.' should be a string');
		$this->assertSame($result, '/path/To/page', $entity." is wrong: '$result'");
	}

	public function testPort(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;
		$uri = new Http\Uri(self::URL);

		$result = $uri->$method();
		$this->assertTrue(is_null($result) || is_int($result), $entity.' should be null or an integer');
		$this->assertSame($result, null, $entity." is wrong: ".(isSet($result)? $result.'.': 'null'));
	}

	public function testQuery(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;
		$uri = new Http\Uri(self::URL);

		$result = $uri->$method();
		$this->assertTrue(is_string($result), $entity.' should be a string');
		$this->assertSame($result, 'p1=hello&subject=world', $entity." is wrong: '$result'");
	}

	public function testScheme(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;
		$uri = new Http\Uri(self::URL);

		$result = $uri->$method();
		$this->assertTrue(is_string($result), $entity.' should be a string');
		$this->assertSame($result, 'https', $entity." is wrong: '$result'");
	}

	public function testUserInfo(): void {
		$entity = subStr(__FUNCTION__, 4); // remove "test" prefix
		$method = 'get'.$entity;
		$uri = new Http\Uri(self::URL);

		$result = $uri->$method();
		$this->assertTrue(is_string($result), $entity.' should be a string');
		$this->assertSame($result, 'me:secret', $entity." is wrong: '$result'");
	}
}

// vim: noexpandtab sw=4 sts=4 tabstop=4
