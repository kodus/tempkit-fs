<?php

namespace Kodus\TempKit;

use InvalidArgumentException;
use Kodus\Helpers\UUID;
use Zend\Diactoros\UploadedFile;

/**
 * This class provides a facility for temporary collection and recovery of uploaded files.
 */
class TempFileService
{
    const TEMP_EXT = "tmp";

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
     * @param string $temp_path       absolute path of temporary storage folder
     * @param int    $expiration_mins expiration time in minutes (defaults to 120 minutes)
     * @param int    $flush_frequency defaults to 5, meaning flush expired files during 5% of calls to collect()
     */
    public function __construct(string $temp_path, int $expiration_mins = 120, int $flush_frequency = 5)
    {
        if (! is_dir($temp_path)) {
            throw new InvalidArgumentException("invalid temp dir path: {$temp_path}");
        }

        if (! is_writable($temp_path)) {
            throw new InvalidArgumentException("temp dir path is not writable: {$temp_path}");
        }

        if ($flush_frequency < 1 || $flush_frequency > 100) {
            throw new InvalidArgumentException("invalid flush frequency: {$flush_frequency} (must be in range 1..100)");
        }

        $this->temp_path = $temp_path;
        $this->flush_frequency = $flush_frequency;
        $this->expiration_mins = $expiration_mins;
    }

    /**
     * Collect an uploaded file for temporary storage.
     *
     * The returned UUID can be used to {@see recover} the file at a later time.
     *
     * @param UploadedFile $file
     *
     * @return string temporary file UUID
     */
    public function collect(UploadedFile $file): string
    {
        if (rand(0, 99) < $this->flush_frequency) {
            $this->flushExpiredFiles();
        }

        $uuid = UUID::create();

        $file->moveTo($this->getTempPath($uuid));

        $json = json_encode([
            "filename"  => $file->getClientFilename(),
            "mime_type" => $file->getClientMediaType(),
        ]);

        file_put_contents($this->getJSONPath($uuid), $json);

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
     * @throws InvalidArgumentException if the specified UUID is invalid/expired.
     */
    public function recover(string $uuid): TempFile
    {
        $temp_path = $this->getTempPath($uuid);
        $json_path = $this->getJSONPath($uuid);

        if (file_exists($temp_path) && file_exists($json_path)) {
            $json = json_decode(file_get_contents($json_path), true);

            return new TempFile($temp_path, $json_path, $json["filename"], $json["mime_type"]);
        }

        throw new InvalidArgumentException("temp file(s) not found: {$temp_path}");
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
        return "{$this->temp_path}/{$uuid}.json";
    }

    private function flushExpiredFiles()
    {
        foreach (glob("{$this->temp_path}/*." . self::TEMP_EXT, GLOB_NOSORT) as $path) {
            $uuid = basename($path, "." . self::TEMP_EXT);

            if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $uuid) === 1) {
                if (time() - filemtime($path) > 80 * $this->expiration_mins) {
                    @unlink($path);
                    @unlink($this->getJSONPath($uuid));
                }
            }
        }
    }
}
