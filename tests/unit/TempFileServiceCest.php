<?php

namespace Kodus\TempKit\Test;

use Codeception\Util\FileSystem;
use Kodus\Helpers\UUID;
use Kodus\TempKit\TempFile;
use Kodus\TempKit\TempFileRecoveryException;
use Kodus\TempKit\TempFileService;
use UnitTester;
use Zend\Diactoros\Stream;
use Zend\Diactoros\UploadedFile;

class TempFileServiceCest
{
    const FILENAME = "hello.txt";
    const MEDIA_TYPE = "text/plain";

    private $output_dir;

    public function _before(UnitTester $I)
    {
        $this->output_dir = dirname(__DIR__) . "/_output/TempFileServiceCest";

        if (! is_dir($this->output_dir)) {
            mkdir($this->output_dir);
        } else {
            FileSystem::doEmptyDir($this->output_dir);
        }
    }

    public function collectAndRecoverUploadedFiles(UnitTester $I)
    {
        // create a "fake" uploaded file for testing:

        $file_contents = str_repeat("0123456789", 1000);

        $client_filename = self::FILENAME;

        $uploaded_file_path = "{$this->output_dir}/{$client_filename}";

        file_put_contents($uploaded_file_path, $file_contents);

        $client_media_type = self::MEDIA_TYPE;

        $uploaded_file = new UploadedFile(
            new Stream($uploaded_file_path),
            strlen($file_contents),
            UPLOAD_ERR_OK,
            $client_filename,
            $client_media_type
        );

        // bootstrap the service for test:

        $temp_path = "{$this->output_dir}/temp";

        mkdir($temp_path);

        $service = new TempFileService($temp_path);

        // collect the uploaded file as a temporary file:

        $uuid = $service->collect($uploaded_file);

        $I->assertTrue(UUID::isValid($uuid));

        $I->assertFileExists($uploaded_file_path, "uploaded file has been collected");

        // recover the uploaded file:

        $recovered_file = $service->recover($uuid);

        $I->assertSame(self::FILENAME, $recovered_file->getClientFilename());
        $I->assertSame(self::MEDIA_TYPE, $recovered_file->getClientMediaType());

        $destination_path = "{$this->output_dir}/{$client_filename}";

        $recovered_file->moveTo($destination_path);

        $I->assertFileExists($destination_path);

        $I->assertSame($file_contents, file_get_contents($destination_path));

        $I->assertSame([], glob("{$temp_path}/*"), "temp dir is empty (temporary file was recovered)");
    }

    public function flushesExpiredFiles(UnitTester $I)
    {
        // create a "fake" uploaded file for testing:

        $create_file = function () {
            $file_contents = str_repeat("0123456789", 1000);

            $uploaded_file_path = "{$this->output_dir}/" . self::FILENAME;

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

        $temp_path = "{$this->output_dir}/temp";

        mkdir($temp_path);

        $service = new MockTempFileService($temp_path, 1, 100); // always flush after 1 minute

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

        $I->assertCount(1, glob("{$temp_path}/*.tmp"), "temp dir is empty (temporary file was flushed)");
    }
}
