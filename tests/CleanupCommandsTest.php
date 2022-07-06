<?php

namespace Bogordesain\Tests;

use Carbon\Carbon;

class CleanupCommandsTest extends TestCase
{
    public function test_cleanup_empty_backup()
    {
        $this->artisan('backup:cleanup')
            ->expectsOutput('Backup directory does not exist.')
            ->assertExitCode(0);

        $this->artisan('backup:setup')
            ->assertExitCode(0);

        $this->artisan('backup:cleanup')
            ->expectsOutput('No backups need to be cleanup.')
            ->assertExitCode(0);
    }

    public function test_cleanup_a_lot_of_backups()
    {
        $files = require_once __DIR__.'/vendor/files.php';

        $dir = config('juice-backups.destination');

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        foreach ($files as $file) {
            touch(sprintf('%s/%s', $dir, $file));
        }

        Carbon::setTestNow('2018-12-31 23:59:59');

        $this->artisan('backup:cleanup')
            ->expectsOutput('Backup cleanup successfully.')
            ->assertExitCode(0);

        $this->artisan('backup:cleanup')
            ->assertExitCode(0);

        $exists = require_once __DIR__.'/vendor/exists.php';
        
        $deleted = require_once __DIR__.'/vendor/deleted.php';

        foreach ($exists as $file) {
            $this->assertFileExists(sprintf('%s/%s', $dir, $file));
        }

        foreach ($deleted as $file) {
            $this->assertFileNotExists(sprintf('%s/%s', $dir, $file));
        }

        $this->assertSame(count($files), count($exists) + count($deleted));
    }
}
