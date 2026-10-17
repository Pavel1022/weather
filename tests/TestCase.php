<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Monolog\Handler\NullHandler;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'logging.default' => 'null',
            'logging.channels.import' => [
                'driver' => 'monolog',
                'handler' => NullHandler::class,
            ],
        ]);
    }
}
