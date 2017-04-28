<?php

namespace Kodus\TempKit\Test;

use Kodus\TempKit\TempFileService;

class MockTempFileService extends TempFileService
{
    /**
     * @var int
     */
    public $time;

    protected function getTime(): int
    {
        return $this->time;
    }
}
