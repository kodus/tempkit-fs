<?php

namespace Kodus\TempKit;

use InvalidArgumentException;
use Kodus\Helpers\UUID;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * This class provides a facility for temporary collection and recovery of uploaded files.
 */
class TempFileService
{
    const TEMP_EXT = "tmp";
    const JSON_EXT = "json";

    /**
     * @var string
     */
    private $temp_path;

    /**
     * @var int
     */
    private $flush_frequency;

    /**
     * @var int
     */
    private $expiration_mins;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @param FilesystemInterface $filesystem      the Filesystem instance to use
     * @param string              $temp_path       absolute path of temporary storage folder (within the given Filesystem)
     * @param int                 $expiration_mins expiration time in minutes (defaults to 120 minutes)
     * @param int                 $flush_frequency defaults to 5, meaning flush expired files during 5% of calls to collect()
     */
    public function __construct(
        FilesystemInterface $filesystem,
        string $temp_path = "temp",
        int $expiration_mins = 120,
        int $flush_frequency = 5
    ) {
        if ($flush_frequency < 1 || $flush_frequency > 100) {
            throw new InvalidArgumentException("invalid flush frequency: {$flush_frequency} (must be in range 1..100)");
        }

        $this->filesystem = $filesystem;
        $this->temp_path = $temp_path;
        $this->flush_frequency = $flush_frequency;
        $this->expiration_mins = $expiration_mins;
    }

    /**
     * Collect an uploaded file for temporary storage.
     *
     * The returned UUID can be used to {@see recover} the file at a later time.
     *
     * @param UploadedFileInterface $file
     *
     * @return string temporary file UUID
     */
    public function collect(UploadedFileInterface $file): string
    {
        if (rand(0, 99) < $this->flush_frequency) {
            $this->flushExpiredFiles();
        }

        $uuid = UUID::create();

        $json = json_encode([
            "filename"   => $file->getClientFilename(),
            "media_type" => $file->getClientMediaType(),
        ]);

        $this->filesystem->writeStream($this->getTempPath($uuid), $file->getStream()->detach());

        $this->filesystem->write($this->getJSONPath($uuid), $json);

        return $uuid;
    }

    /**
     * Recover a collected temporary file.
     *
     * Use {@see collect()} to collect an uploaded file for temporary storage.
     *
     * @param string $uuid temporary file UUID
     *
     * @return TempFile
     *
     * @throws TempFileRecoveryException if the specified UUID is invalid/expired.
     */
    public function recover(string $uuid): TempFile
    {
        $temp_path = $this->getTempPath($uuid);
        $json_path = $this->getJSONPath($uuid);

        if ($this->filesystem->has($temp_path) && $this->filesystem->has($json_path)) {
            $json = json_decode($this->filesystem->read($json_path), true);

            return new TempFile($this->filesystem, $temp_path, $json_path, $json["filename"], $json["media_type"]);
        }

        throw new TempFileRecoveryException($uuid);
    }

    /**
     * @param string $uuid
     *
     * @return string
     */
    private function getTempPath(string $uuid): string
    {
        return "{$this->temp_path}/{$uuid}." . self::TEMP_EXT;
    }

    /**
     * @param string $uuid
     *
     * @return string
     */
    private function getJSONPath(string $uuid): string
    {
        return "{$this->temp_path}/{$uuid}." . self::JSON_EXT;
    }

    private function flushExpiredFiles()
    {
        foreach ($this->filesystem->listPaths($this->temp_path) as $temp_path) {
            if (fnmatch("*." . self::TEMP_EXT, $temp_path)) {
                $uuid = basename($temp_path, "." . self::TEMP_EXT);

                if (UUID::isValid($uuid)) {
                    if ($this->getTime() - $this->filesystem->getTimestamp($temp_path) > 60 * $this->expiration_mins) {
                        $json_path = $this->getJSONPath($uuid);

                        $this->delete($temp_path);
                        $this->delete($json_path);
                    }
                }
            }
        }
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
     * @return int
     */
    protected function getTime(): int
    {
        return time();
    }
}
