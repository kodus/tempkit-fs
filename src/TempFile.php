<?php

namespace Kodus\TempKit;

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
    private $mime_type;

    /**
     * @param string $temp_path
     * @param string $json_path
     * @param string $filename
     * @param string $mime_type
     */
    public function __construct(string $temp_path, string $json_path, string $filename, string $mime_type)
    {
        $this->temp_path = $temp_path;
        $this->json_path = $json_path;
        $this->filename = $filename;
        $this->mime_type = $mime_type;
    }

    /**
     * Move this temporary file to a new, permanent location.
     *
     * The full destination path must be specified, including the filename.
     *
     * The destination folder must already exist.
     *
     * @param string $target_path absolute destination path, including filename
     *
     * @throws RuntimeException on failure to rename file
     */
    public function moveTo(string $target_path)
    {
        if (@rename($this->temp_path, $target_path) !== true) {
            if (@copy($this->temp_path, $target_path) !== true) {
                throw new RuntimeException("unable to move '{$this->temp_path}' to '{$target_path}'");
            }

            @unlink($this->temp_path);
        }

        @unlink($this->json_path);
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
        return $this->mime_type;
    }
}
