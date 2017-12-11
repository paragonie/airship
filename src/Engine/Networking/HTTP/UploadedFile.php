<?php
declare(strict_types=1);
namespace Airship\Engine\Networking\HTTP;

use Psr\Http\Message\{
    StreamInterface,
    UploadedFileInterface
};

/**
 * Class UploadedFile
 *
 * Based on Guzzle/PSR7's code
 *
 * Copyright (c) 2015 Michael Dowling, https://github.com/mtdowling <mtdowling@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package Airship\Engine\Networking\HTTP
 */
class UploadedFile implements UploadedFileInterface
{
    /**
     * @var int[]
     */
    protected static $errors = [
        UPLOAD_ERR_OK,
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE,
        UPLOAD_ERR_PARTIAL,
        UPLOAD_ERR_NO_FILE,
        UPLOAD_ERR_NO_TMP_DIR,
        UPLOAD_ERR_CANT_WRITE,
        UPLOAD_ERR_EXTENSION,
    ];

    /**
     * @var string
     */
    protected $clientFilename = '';

    /**
     * @var string
     */
    protected $clientMediaType = '';

    /**
     * @var int
     */
    protected $error;

    /**
     * @var null|string
     */
    protected $file;

    /**
     * @var bool
     */
    protected $moved = false;

    /**
     * @var int
     */
    protected $size;

    /**
     * @var StreamInterface|null
     */
    protected $stream;

    /**
     * @param StreamInterface|string|resource $streamOrFile
     * @param int $size
     * @param int $errorStatus
     * @param string|null $clientFilename
     * @param string|null $clientMediaType
     */
    public function __construct(
        $streamOrFile,
        int $size,
        int $errorStatus,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ) {
        $this->setError($errorStatus)
            ->setSize($size)
            ->setClientFilename($clientFilename)
            ->setClientMediaType($clientMediaType);

        if ($this->isOk()) {
            $this->setStreamOrFile($streamOrFile);
        }
    }

    /**
     * Depending on the value set file or stream variable
     *
     * @param mixed $streamOrFile
     * @return self
     * @throws \InvalidArgumentException
     */
    private function setStreamOrFile($streamOrFile): self
    {
        if (is_string($streamOrFile)) {
            $this->file = $streamOrFile;
        } elseif (is_resource($streamOrFile)) {
            $this->stream = new Stream($streamOrFile);
        } elseif ($streamOrFile instanceof StreamInterface) {
            $this->stream = $streamOrFile;
        } else {
            throw new \InvalidArgumentException(
                'Invalid stream or file provided for UploadedFile'
            );
        }
        return $this;
    }

    /**
     * @param int $error
     * @return self
     * @throws \InvalidArgumentException
     */
    private function setError(int $error): self
    {
        $this->error = $error;
        return $this;
    }

    /**
     * @param int $size
     * @return self
     */
    private function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    /**
     * @param mixed $param
     * @return bool
     */
    protected function isStringOrNull($param): bool
    {
        return \in_array(\gettype($param), ['string', 'NULL']);
    }

    /**
     * @param string $param
     * @return bool
     */
    private function isStringNotEmpty(string $param): bool
    {
        return !empty($param);
    }

    /**
     * @param string|null $clientFilename
     * @return self
     */
    private function setClientFilename(?string $clientFilename): self
    {
        $this->clientFilename = (string) $clientFilename;
        return $this;
    }

    /**
     * @param string|null $clientMediaType
     * @return self
     */
    private function setClientMediaType(?string $clientMediaType): self
    {
        $this->clientMediaType = (string) $clientMediaType;
        return $this;
    }

    /**
     * Return true if there is no upload error
     *
     * @return bool
     */
    private function isOk(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }

    /**
     * @return bool
     */
    public function isMoved(): bool
    {
        return $this->moved;
    }

    /**
     * @throws \RuntimeException if is moved or not ok
     * @return void
     */
    private function validateActive(): void
    {
        if (!$this->isOk()) {
            throw new \RuntimeException('Cannot retrieve stream due to upload error');
        }

        if ($this->isMoved()) {
            throw new \RuntimeException('Cannot retrieve stream after it has already been moved');
        }
    }

    /**
     * {@inheritdoc}
     * @throws \RuntimeException if the upload was not successful.
     * @psalm-suppress InvalidArgument as fopen can theoretically return false
     */
    public function getStream(): StreamInterface
    {
        $this->validateActive();

        if ($this->stream instanceof StreamInterface) {
            return $this->stream;
        }

        if (\is_null($this->file)) {
            throw new \RuntimeException('Could not open temporary file to create stream');
        }

        $resource = \fopen($this->file, 'r+');
        if (!\is_resource($resource)) {
            throw new \RuntimeException('Could not open temporary file to create stream');
        }
        return new Stream($resource);
    }

    /**
     * {@inheritdoc}
     *
     * @see http://php.net/is_uploaded_file
     * @see http://php.net/move_uploaded_file
     * @param string $targetPath Path to which to move the uploaded file.
     * @throws \RuntimeException if the upload was not successful.
     * @throws \InvalidArgumentException if the $path specified is invalid.
     * @throws \RuntimeException on any error during the move operation, or on
     *     the second or subsequent call to the method.
     *
     * @psalm-suppress InvalidArgument as fopen can theoretically return false
     */
    public function moveTo($targetPath): self
    {
        $this->validateActive();

        if (!$this->isStringNotEmpty($targetPath)) {
            throw new \InvalidArgumentException(
                'Invalid path provided for move operation; must be a non-empty string'
            );
        }

        if ($this->file) {
            $this->moved = (
                (\php_sapi_name() === 'cli')
                    ? \rename($this->file, $targetPath)
                    : \move_uploaded_file($this->file, $targetPath)
            );
        } else {
            $resource = \fopen($targetPath, 'w');
            if (!\is_resource($resource)) {
                throw new \RuntimeException('Could not open output file for writing');
            }
            Stream::copyToStream(
                $this->getStream(),
                new Stream($resource)
            );
            $this->moved = true;
        }

        if (!$this->moved) {
            throw new \RuntimeException(
                sprintf('Uploaded file could not be moved to %s', $targetPath)
            );
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return int The file size in bytes or null if unknown.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * {@inheritdoc}
     *
     * @see http://php.net/manual/en/features.file-upload.errors.php
     * @return int One of PHP's UPLOAD_ERR_XXX constants.
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * {@inheritdoc}
     *
     * @return string The filename sent by the client or null if none
     *     was provided.
     */
    public function getClientFilename(): string
    {
        return $this->clientFilename;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientMediaType(): string
    {
        return $this->clientMediaType;
    }
}
