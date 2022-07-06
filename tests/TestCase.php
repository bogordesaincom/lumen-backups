<?php

namespace Bogordesain\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Bogordesain\Backups\BackupsServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * Clean up the testing environment before the next test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('jb-backups'));
        File::delete(File::files(__DIR__.'/temp'));

        parent::tearDown();
    }

    /**
     * Get package providers.
     *
     * @param Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [BackupsServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
    }
}
