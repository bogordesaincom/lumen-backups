<?php

namespace Bogordesain\Tests;

use Carbon\Carbon;
use PharData;
use RecursiveIteratorIterator;
use Symfony\Component\Finder\Finder;

class RunCommandsTest extends TestCase
{
    /**
     * Testing backup file path.
     *
     * @var string
     */
    protected $path;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $timestamp = mt_rand(1, 2147483647);

        Carbon::setTestNow(date("Y-m-d H:i:s", $timestamp));

        $this->app['config']->set('juice-backups.destination', __DIR__ . '/temp');

        $this->artisan('backup:setup');

        $this->path = sprintf(
            '%s/temp/%s-%s.tar.gz',
            __DIR__,
            config('juice-backups.name'),
            date("Y-m-d-H-i-s", $timestamp)
        );
    }

    public function test_run_command()
    {
        $this->artisan('backup:run')
            ->expectsOutput('Application and database backup successfully.')
            ->assertExitCode(0);

        $this->assertFileExists($this->path);
        $this->assertGreaterThan(0, filesize($this->path));

        $except = (new Finder)->in(base_path())
            ->files()
            ->ignoreDotFiles(false)
            ->exclude(array_map('basename', config('juice-backups.excludes')))
            ->count(); // database backup will count 1

        $this->assertSame($except, iterator_count(new RecursiveIteratorIterator(new PharData($this->path))));
    }

    public function test_backup_only_files()
    {
        $this->app['config']->set('juice-backups.includes', [
            base_path('composer.json'),
            base_path('database/database.sqlite.example'),
        ]);

        $this->app['config']->set('juice-backups.excludes', []);

        $this->artisan('backup:run')->assertExitCode(0);

        foreach (new RecursiveIteratorIterator(new PharData($this->path)) as $file) {
            $this->assertContains(
                $file->getFilename(),
                ['composer.json', 'database.sqlite.example', ':memory:.sql']
            );
        }
    }

    public function test_subdirectory_will_not_repeat_archive()
    {
        $this->app['config']->set('juice-backups.includes', [
            base_path('resources/lang'),
            base_path('resources/lang/en'),
            base_path('resources/lang/en/auth.php'),
        ]);

        $this->app['config']->set('juice-backups.excludes', []);

        $this->artisan('backup:run')->assertExitCode(0);

        $this->assertSame(4, iterator_count(new RecursiveIteratorIterator(new PharData($this->path))));
    }

    public function test_database_backup()
    {
        $this->loadLaravelMigrations();

        $this->artisan('migrate')->run();

        $this->app['config']->set('juice-backups.includes', []);
        $this->app['config']->set('juice-backups.excludes', []);

        $this->artisan('backup:run')->assertExitCode(0);

        foreach (new PharData($this->path) as $file) {
            $this->assertSame(':memory:.sql', $file->getFilename());
        }
    }

    public function test_not_support_database_warning_message()
    {
        $this->app['config']->set('database.default', 'juice');

        $this->app['config']->set('juice-backups.includes', []);
        $this->app['config']->set('juice-backups.excludes', []);

        $this->artisan('backup:run')
            ->expectsOutput('Not supported database type: "juice"')
            ->assertExitCode(0);
    }
}
