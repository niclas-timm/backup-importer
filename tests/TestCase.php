<?php

namespace NiclasTimm\LaravelDbImporter\Tests;


class TestCase extends \Orchestra\Testbench\TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // additional setup
    }

    protected function getPackageProviders($app)
    {
        return [
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // perform environment setup
    }
}