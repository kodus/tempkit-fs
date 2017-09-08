<?php

namespace Kodus\TempKit\Test;

use Kodus\Helpers\UUID;
use Kodus\TempKit\TempFile;
use Kodus\TempKit\TempFileRecoveryException;
use Kodus\TempKit\TempFileService;
use League\Flysystem\Filesystem;
use League\Flysystem\Memory\MemoryAdapter;
use League\Flysystem\Plugin\ListPaths;
use UnitTester;
use Zend\Diactoros\Stream;
use Zend\Diactoros\UploadedFile;

class TempFileServiceCest
{
    const FILENAME   = "hello.txt";
    const MEDIA_TYPE = "text/plain";

    /**
     * @var string
     */
    private $temp_dir;

    /**
     * @var Filesystem
     */
    private $filesystem;

    public function _before(UnitTester $I)
    {
        $this->temp_dir = dirname(__DIR__) . "/_output";
        $this->filesystem = new Filesystem(new MemoryAdapter());
        $this->filesystem->addPlugin(new ListPaths());
    }

    public function collectAndRecoverUploadedFiles(UnitTester $I)
    {
        // create a "fake" uploaded file for testing:

        $file_contents = str_repeat("0123456789", 1000);

        $client_filename = self::FILENAME;

        $uploaded_file_path = "{$this->temp_dir}/{$client_filename}";

        file_put_contents($uploaded_file_path, $file_contents);

        $client_media_type = self::MEDIA_TYPE;

        $uploaded_file = new UploadedFile(
            $uploaded_file_path,
            strlen($file_contents),
            UPLOAD_ERR_OK,
            $client_filename,
            $client_media_type
        );

        $service = new TempFileService($this->filesystem, "tmp");

        // collect the uploaded file as a temporary file:

        $uuid = $service->collect($uploaded_file);

        $I->assertTrue(UUID::isValid($uuid));

        // recover the uploaded file:

        $recovered_file = $service->recover($uuid);

        $I->assertTrue($this->filesystem->has($recovered_file->getTempPath()), "uploaded file has been collected");

        $I->assertSame(self::FILENAME, $recovered_file->getClientFilename());
        $I->assertSame(self::MEDIA_TYPE, $recovered_file->getClientMediaType());

        $recovered_file->moveTo($client_filename);

        $I->assertFalse($this->filesystem->has($recovered_file->getTempPath()), "uploaded file has been moved");

        $I->assertSame($file_contents, $this->filesystem->read($client_filename));

        $I->assertSame([], $this->filesystem->listPaths("tmp"), "temp dir is empty (temporary file was recovered)");
    }

    public function flushesExpiredFiles(UnitTester $I)
    {
        // create a "fake" uploaded file for testing:

        $create_file = function () {
            $file_contents = str_repeat("0123456789", 1000);

            $uploaded_file_path = "{$this->temp_dir}/" . self::FILENAME;

            file_put_contents($uploaded_file_path, $file_contents);

            return new UploadedFile(
                new Stream($uploaded_file_path),
                strlen($file_contents),
                UPLOAD_ERR_OK,
                self::FILENAME,
                self::MEDIA_TYPE
            );
        };

        $uploaded_file = $create_file();

        // bootstrap a mock service for test:

        $service = new MockTempFileService($this->filesystem, "tmp", 1, 100); // always flush after 1 minute

        $service->time = time(); // fake time

        // collect the uploaded file as a temporary file:

        $uuid = $service->collect($uploaded_file);

        $I->assertInstanceOf(TempFile::class, $service->recover($uuid), "can recover temporary file");

        // advance time by 1 minute:

        $service->time += 61;

        // trigger flushing by collecting another file:

        $another_file = $create_file();

        $service->collect($another_file);

        // recover the uploaded file:

        $exception = null;

        try {
            $service->recover($uuid);
        } catch (TempFileRecoveryException $exception) {
            // caught!
        }

        $I->assertInstanceOf(TempFileRecoveryException::class, $exception);

        $I->assertCount(2, $this->filesystem->listPaths("tmp"), "temp dir contains only one json/tmp file pair");
    }
}
