<?php
declare(strict_types=1);
namespace Airship\Engine\Networking\HTTP;

use ParagonIE\ConstantTime\Binary;
use ParagonIE\Halite\HiddenString;
use Psr\Http\Message\StreamInterface;

/**
 * Class Stream
 * @package Airship\Engine\Networking\HTTP
 */
class Stream implements StreamInterface
{
    /**
     * @var resource
     */
    protected $stream;

    /**
     * @var bool
     */
    protected $seekable = false;

    /**
     * @var bool
     */
    protected $readable = false;

    /**
     * @var bool
     */
    protected $writable = false;

    /**
     * Stream constructor.
     */
    public function __construct(resource $stream)
    {
        $this->stream = $stream;
        $meta = \stream_get_meta_data($stream);

        $this->seekable = !empty($meta['seekable']);
        $mode = \preg_replace('#(b|t)$#', '', $meta['mode']);
        switch ($mode) {
            case 'r':
                $this->readable = true;
                break;
            case 'a+':
            case 'c+':
            case 'r+':
            case 'w+':
            case 'x+':
                $this->readable = true;
                $this->writable = true;
                break;
            case 'a':
            case 'c':
            case 'w':
            case 'x':
                $this->writable = true;
                break;
        }
    }

    /**
     *
     */
    public function __destruct()
    {
        \fclose($this->stream);
    }

    /**
     * Convert a string into a memory string.
     *
     * @param string $data
     * @return Stream
     */
    public static function fromString(string $data): self
    {
        $resource = \fopen('php://memory', 'r+');
        \fwrite($resource, $data);
        \rewind($resource);
        return new static($resource);
    }

    /**
     * Convert a string into a memory string.
     *
     * @param HiddenString $data
     * @return Stream
     */
    public static function fromHiddenString(HiddenString $data): self
    {
        $resource = \fopen('php://memory', 'r+');
        \fwrite($resource, $data->getString());
        \rewind($resource);
        return new static($resource);
    }

    /**
     * Reads all data from the stream into a string, from the beginning to end.
     *
     * This method MUST attempt to seek to the beginning of the stream before
     * reading data and read the stream until the end is reached.
     *
     * Warning: This could attempt to load a large amount of data into memory.
     *
     * This method MUST NOT raise an exception in order to conform with PHP's
     * string casting operations.
     *
     * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
     * @return string
     */
    public function __toString()
    {
        try {
            if ($this->isSeekable()) {
                $position = $this->tell();
                $this->rewind();
                $string = $this->getContents();
                $this->seek($position);
                return $string;
            }
            return $this->getContents();
        } catch (\Throwable $ex) {
            return '';
        }
    }

    /**
     * Closes the stream and any underlying resources.
     *
     * @return void
     */
    public function close(): void
    {
        \fclose($this->stream);
    }

    /**
     * Separates any underlying resources from the stream.
     *
     * After the stream has been detached, the stream is in an unusable state.
     *
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach(): ?resource
    {
        return $this->stream;
    }

    /**
     * Get the size of the stream if known.
     *
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    public function getSize(): ?int
    {
        $stat = \fstat($this->stream);
        return $stat['size'];
    }

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return int Position of the file pointer
     * @throws \RuntimeException on error.
     */
    public function tell(): int
    {
        if (!$this->isSeekable()) {
            return 0;
        }
        $told = \ftell($this->stream);
        if ($told === false) {
            throw new \RuntimeException('ftell() failed');
        }
        return $told;
    }

    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     */
    public function eof(): bool
    {
        return \feof($this->stream);
    }

    /**
     * Returns whether or not the stream is seekable.
     *
     * @return bool
     */
    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    /**
     * Seek to a position in the stream.
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *     offset bytes SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     * @throws \RuntimeException on failure.
     * @return Stream
     */
    public function seek($offset, $whence = SEEK_SET): self
    {
        if (!$this->isSeekable()) {
            throw new \RuntimeException('Stream is not seekable');
        }
        \fseek($this->stream, $whence);
        return $this;
    }

    /**
     * Seek to the beginning of the stream.
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     *
     * @see seek()
     * @link http://www.php.net/manual/en/function.fseek.php
     * @throws \RuntimeException on failure.
     */
    public function rewind(): self
    {
        return $this->seek(0);
    }

    /**
     * Returns whether or not the stream is writable.
     *
     * @return bool
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * Write data to the stream.
     *
     * @param string $string The string that is to be written.
     * @return int Returns the number of bytes written to the stream.
     * @throws \RuntimeException on failure.
     */
    public function write($string): int
    {
        $ret = \fwrite($this->stream, $string);
        if ($ret === false) {
            throw new \RuntimeException('Could not write to stream');
        }
        return $ret;
    }

    /**
     * Returns whether or not the stream is readable.
     *
     * @return bool
     */
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * Read data from the stream.
     *
     * @param int $length Read up to $length bytes from the object and return
     *     them. Fewer than $length bytes may be returned if underlying stream
     *     call returns fewer bytes.
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     * @throws \RuntimeException if an error occurs.
     */
    public function read($length): string
    {
        return \fread($this->stream, $length);
    }

    /**
     * Returns the remaining contents in a string
     *
     * @return string
     * @throws \RuntimeException if unable to read or an error occurs while
     *     reading.
     */
    public function getContents(): string
    {
        return \stream_get_contents($this->stream);
    }

    /**
     * Returns the remaining contents in a string
     *
     * @return HiddenString
     * @throws \RuntimeException if unable to read or an error occurs while
     *     reading.
     */
    public function getContentsAsHiddenString(): HiddenString
    {
        return new HiddenString(\stream_get_contents($this->stream));
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @link http://php.net/manual/en/function.stream-get-meta-data.php
     * @param string $key Specific metadata to retrieve.
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     */
    public function getMetadata($key = null)
    {
        if ($key !== null) {
            return \stream_get_meta_data($this->stream)[$key];
        }
        return \stream_get_meta_data($this->stream);
    }

    /**
     * Copy the contents of a stream into a string until the given number of
     * bytes have been read.
     *
     * @param StreamInterface $stream Stream to read
     * @param int             $maxLen Maximum number of bytes to read. Pass -1
     *                                to read the entire stream.
     * @return string
     * @throws \RuntimeException on error.
     */
    public static function copyToString(
        StreamInterface $stream,
        int $maxLen = -1
    ): string {
        $buffer = '';
        if ($maxLen === -1) {
            while (!$stream->eof()) {
                $buf = $stream->read(1048576);
                // Using a loose equality here to match on '' and false.
                if ($buf == null) {
                    break;
                }
                $buffer .= $buf;
            }
            return $buffer;
        }
        $len = 0;
        while (!$stream->eof() && $len < $maxLen) {
            $buf = $stream->read($maxLen - $len);
            // Using a loose equality here to match on '' and false.
            if ($buf == null) {
                break;
            }
            $buffer .= $buf;
            $len = Binary::safeStrlen($buffer);
        }
        return $buffer;
    }

    /**
     * Copy the contents of a stream into another stream until the given number
     * of bytes have been read.
     *
     * @param StreamInterface $source Stream to read from
     * @param StreamInterface $dest   Stream to write to
     * @param int             $maxLen Maximum number of bytes to read. Pass -1
     *                                to read the entire stream.
     *
     * @throws \RuntimeException on error.
     */
    public static function copyToStream(
        StreamInterface $source,
        StreamInterface $dest,
        int $maxLen = -1
    ) {
        $bufferSize = 8192;
        if ($maxLen === -1) {
            while (!$source->eof()) {
                if (!$dest->write($source->read($bufferSize))) {
                    break;
                }
            }
        } else {
            $remaining = $maxLen;
            while ($remaining > 0 && !$source->eof()) {
                $buf = $source->read(\min($bufferSize, $remaining));
                $len = Binary::safeStrlen($buf);
                if (!$len) {
                    break;
                }
                $remaining -= $len;
                $dest->write($buf);
            }
        }
    }
}
