<?php

namespace Kodus\TempKit;

use League\Flysystem\File;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use RuntimeException;

/**
 * This class represents an uploaded file previously collected by {@see TempFileService}.
 *
 * @see TempFileService::collect()
 * @see TempFileService::recover()
 */
class TempFile
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var string
     */
    private $temp_path;

    /**
     * @var string
     */
    private $json_path;

    /**
     * @var string
     */
    private $filename;

    /**
     * @var string
     */
    private $media_type;

    /**
     * @param FilesystemInterface $filesystem
     * @param string              $temp_path
     * @param string              $json_path
     * @param string              $filename
     * @param string              $media_type
     */
    public function __construct(
        FilesystemInterface $filesystem,
        string $temp_path,
        string $json_path,
        string $filename,
        string $media_type
    ) {
        $this->temp_path = $temp_path;
        $this->json_path = $json_path;
        $this->filename = $filename;
        $this->media_type = $media_type;
        $this->filesystem = $filesystem;
    }

    /**
     * Move this temporary file to a new, permanent location.
     *
     * The full destination path must be specified, including the filename.
     *
     * Alternatively, use {@see getTempFile()} if you intend to move the temporary file by other means.
     *
     * @param string $target_path absolute destination path, including filename
     *
     * @throws RuntimeException on failure to rename file
     */
    public function moveTo(string $target_path)
    {
        if (! $this->filesystem->rename($this->temp_path, $target_path)) {
            throw new RuntimeException("unable to move '{$this->temp_path}' to '{$target_path}'");
        }

        $this->delete($this->json_path);
    }

    /**
     * Obtain the absolute temporary path.
     *
     * Use this if you intend to manually move the temporary file to a permanent location.
     *
     * If you copied the file, consider using {@see flush()} to garbage-collect it immediately after.
     *
     * Alternatively, use {@see moveTo()} to move the file from it's temporary location.
     *
     * @return string temporary file path
     */
    public function getTempPath(): string
    {
        return $this->temp_path;
    }

    /**
     * Immediately garbage-collect this temporary file.
     *
     * Use this to clean up e.g. after copying the temporary file.
     */
    public function flush()
    {
        $this->delete($this->temp_path);
        $this->delete($this->json_path);
    }

    /**
     * @return string original filename, as specified by the client when this file was collected.
     */
    public function getClientFilename()
    {
        return $this->filename;
    }

    /**
     * @return string MIME media type, as specified by the client when this file was collected.
     */
    public function getClientMediaType()
    {
        return $this->media_type;
    }

    private function delete(string $path)
    {
        try {
            $this->filesystem->delete($path);
        } catch (FileNotFoundException $e) {
            return;
        }
    }

    /**
     * @internal
     */
    public function __destruct()
    {
        if (! $this->filesystem->has($this->temp_path)) {
            // temp file was removed - garbage-collect the JSON meta-data file:

            $this->delete($this->json_path);
        }
    }
}
