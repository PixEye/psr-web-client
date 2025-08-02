<?php
/** FileStream.php
 * @since		2024.11.04 First commit of this file on 2024-11-04
 * @author		<jmoreau@pixeye.net>
 */

namespace Http;

use Psr\Http\Message\StreamInterface;

class FileStream implements StreamInterface {
	const WRITE_ONLY_MODES = ['w', 'a', 'x', 'c'];

	protected
		$blocked = false, /** @property boolean $blocked Tells if this stream is blocked */
		$context = null, /** @property resource $context Stream context */
		$filePath = '', /** @property string $filePath Full file name (path + filename + extension) */
		$fp = null,   /** @property resource $fp The file pointer */
		$mode = 'r', /** @property string $mode Default is read only */
		$pos = 0; /** @property int $pos Current position */

	/** Build a file stream
	 * @param string $filePath
	 * @param string $mode
	 * @param resource $context
	 * @throws \RuntimeException in cas of trouble
	 */
	public function __construct(string $filePath = '', string $mode = 'r', $context = null) {
		$this->filePath = trim($filePath);
		$this->mode = $mode;
		$this->pos = 0;
		if ($this->filePath==='') {
			$tmpDir = sys_get_temp_dir().'/streams';
			if (!is_dir($tmpDir)) {
				$is_ok = mkDir($tmpDir);
				if (!$is_ok)
					throw new \RuntimeException('Could not make dir: '.$tmpDir);
			}

			$prefix = date('Ymd-H00-');
			$this->filePath = tempNam($tmpDir, $prefix);
			if ($this->filePath===false)
				throw new \RuntimeException('Could not make file in: '.$tmpDir);
		}

		$this->fp = fopen($this->filePath, $mode, false, $context);
		if ($this->fp === false)
			throw new \RuntimeException('Could not open file in: '.$this->filePath);
	}

	public function __destruct() {
		if (!$this->blocked) $this->close();

		if ($this->isWritable()) {
			$is_ok = unlink($this->filePath, $this->context);
			if (!$is_ok) error_log('Could not delete stream: '.$this->filePath);
		}
	}

	public function __toString() {
		if (!$this->isReadable()) return 'not readable stream';

		$ret = file_get_contents($this->filePath, false, $this->context, 0);
		if ($ret === false)
			throw new \RuntimeException('Could not open stream');

		return $ret;
	}

	public function close() {
		fclose($this->fp);
		$this->blocked = true;
		$this->fp = null;
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

		$ret = fread($this->fp, $this->getSize() - $this->pos);
		if ($ret === false)
			throw new \RuntimeException('Could not read stream');

		$this->pos += strLen($ret);

		return $ret;
	}

	public function getMetadata($key = null) {
		$meta = stream_get_meta_data($this->fp);
		if (isSet($key) && !isSet($meta[$key])) return null;

		return isSet($key)? $meta[$key]: $meta;
	}

	public function getSize() {
		$ret = fileSize($this->filePath);
		if ($ret === false) $ret = null;

		return $ret;
	}

	public function isReadable(): bool {
		return !$this->blocked && !in_array($this->mode, self::WRITE_ONLY_MODES, true);
	}

	public function isSeekable(): bool { return !$this->blocked && true; }
	public function isWritable(): bool { return !$this->blocked && $this->mode !== 'r'; }

	public function read($length): string {
		if (!$this->isReadable())
			throw new \RuntimeException('not readable stream');

		$length = (int) $length;
		$ret = fread($this->fp, $length);
		if ($ret === false)
			throw new \RuntimeException('Could not read stream');

		$this->pos += strLen($ret);

		return $ret;
	}

	public function rewind() {
		if (!$this->isSeekable())
			throw new \RuntimeException('not seekable stream');

		$is_ok = rewind($this->fp);
		if (!$is_ok)
			throw new \RuntimeException('Could not rewind stream');

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

		return fseek($this->fp, $offset, $whence);
	}

	public function tell(): int { return $this->pos; }

	public function write($string): int {
		if ( ! $this->isWritable() )
			throw new \RuntimeException('Cannot write to this stream');

		if (!isSet($string)) return 0;

		if (!is_string($string))
			throw new \RuntimeException('Cannot write a '.getType($string));

		$bytesWritten = fwrite($this->fp, $string);
		if ($bytesWritten === false)
			throw new \RuntimeException('Could not write to stream: '.$this->filePath);

		return $bytesWritten;
	}
}

// vim: noexpandtab
