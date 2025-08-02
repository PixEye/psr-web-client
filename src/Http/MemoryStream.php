<?php
/** MemoryStream.php
 * @since		2024.10.29 First commit of this file on 2024-10-29
 * @author		<jmoreau@pixeye.net>
 */

namespace Http;

use Psr\Http\Message\StreamInterface;

class MemoryStream implements StreamInterface {
	const WRITE_ONLY_MODES = ['w', 'a', 'x', 'c'];

	protected
		$blocked = false, /** @property boolean $blocked Tells if this stream is blocked */
		$body = '', /** @property string $body Stream body (in memory) */
		$mode = 'r', /** @property string $mode Default is read only */
		$pos = 0; /** @property int $pos Current position */

	public function __construct(string $body, string $mode = 'r') {
		$this->body = $body;
		$this->mode = $mode;
		$this->pos = 0;
	}

	public function __destruct() {
		if (!$this->blocked) $this->close();
	}

	public function __toString() {
		if (!$this->isReadable()) return 'not readable stream';

		return $this->body;
	}

	public function close() {
		$this->body = '';
		$this->blocked = true;
	}

	public function detach() {
		$this->close();

		return null;
	}

	public function eof(): bool {
		return $this->pos >= $this->getSize();
	}

	public function getContents() {
		if (!$this->isReadable())
			throw new \RuntimeException('not readable stream');

		$ret = subStr($this->body, $this->pos);
		$this->pos += strLen($ret);

		return $ret;
	}

	public function getMetadata($key = null) {
		$meta = [
			'blocked' => $this->blocked,
			'eof' => $this->eof(),
			'mode' => $this->mode,
			'stream_type' => 'simple/memory',
			'seekable' => $this->isSeekable(),
			'timed_out' => false,
			'unread_bytes' => $this->getSize() - $this->pos,
			'uri' => 'php://memory',
			'wrapper_data' => [], // Can be HTTP headers
			'wrapper_type' => 'file',
		];

		if (isSet($key) && !isSet($meta[$key])) return null;

		return isSet($key)? $meta[$key]: $meta;
	}

	public function getSize(): int { return strLen($this->body); }

	public function isReadable(): bool {
		return !$this->blocked && !in_array($this->mode, self::WRITE_ONLY_MODES, true);
	}

	public function isSeekable(): bool { return !$this->blocked && true; }
	public function isWritable(): bool { return !$this->blocked && $this->mode !== 'r'; }

	public function read($length): string {
		if (!$this->isReadable())
			throw new \RuntimeException('not readable stream');

		$length = (int) $length;
		$ret = subStr($this->body, $this->pos, $length);
		$this->pos += strLen($ret);

		return $ret;
	}

	public function rewind() {
		if (!$this->isSeekable())
			throw new \RuntimeException('not seekable stream');

		$this->pos = 0;
	}

	public function seek($offset, $whence = SEEK_SET) {
		if (!$this->isSeekable())
			throw new \RuntimeException('not seekable stream');

		switch ($whence) {
			case SEEK_CUR:
				$this->pos+= $offset;
				break;
			case SEEK_END:
				$this->pos = $this->getSize() + $offset;
				break;
			case SEEK_SET:
				$this->pos = $offset;
				break;
			default:
				throw new \RuntimeException('Invalid seek mode');
		}

		return 0;
	}

	public function tell(): int { return $this->pos; }

	public function write($string): int {
		if ( ! $this->isWritable() )
			throw new \RuntimeException('Cannot write to this stream');

		if (!isSet($string)) return 0;

		if (!is_string($string))
			throw new \RuntimeException('Cannot write a '.getType($string));

		$this->body .= $string;

		return strLen($string);
	}
}

// vim: noexpandtab
