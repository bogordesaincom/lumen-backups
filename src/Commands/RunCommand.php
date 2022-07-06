<?php

namespace Bogordesain\Backups\Commands;

use ArrayIterator;
use Carbon\Carbon;
use Exception;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PharData;
use Spatie\DbDumper\DbDumper;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class RunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run database and application backup.';

    /**
     * Juice backups config.
     *
     * @var array
     */
    protected $config;

    /**
     * Execute the console command.
     *
     * @return void
     *
     * @throws Exception
     */
    public function handle(): void
    {
        $this->config = config('lumen-backups');

        chdir('..');

        $name = sprintf(
            '%s/%s-%s.tar.gz',
            $this->config['destination'],
            trim($this->config['name'], '-'),
            Carbon::now()->format('Y-m-d-H-i-s')
        );

        $phar = new PharData(sprintf('%s.tar', tempnam(sys_get_temp_dir(), Str::random(6))));

        $paths = [];

        foreach ($this->paths() as $path) {
            is_dir($path) ? $phar->addEmptyDir($path) : ($paths[] = $path);
        }

        $phar->buildFromIterator(new ArrayIterator(array_combine($paths, $paths)));

        if (!is_null($db = $this->database())) {
            $phar->addFile($db['path'], $db['name']);
            unlink($db['path']);
        }

        if (!empty($paths) || !is_null($db)) {
            $archive = $this->compress($phar->getPath());

            if (is_null($archive)) {
                $this->error('Fail to use gzip to compress backup file.');
                return;
            }

            rename($archive, $name);
            unlink($phar->getPath());
        }

        $this->info('Application and database backup successfully.');
    }

    /**
     * Get backup files path.
     *
     * @return array
     */
    protected function paths(): array
    {
        $paths = [];

        foreach ($this->includes() as $directory) {
            $finder = (new Finder)
                ->ignoreDotFiles(false)
                ->in($directory)
                ->exclude($this->excludes($directory));

            foreach ($finder->getIterator() as $file) {
                $paths[] = realpath($file->getPathname());
            }
        }

        $files = array_filter($this->config['includes'], 'is_file');

        if (!empty($files)) {
            array_push($paths, ...array_map('realpath', $files));
        }

        return array_map(function ($path) {
            return Str::startsWith($path, getcwd())
                ? substr_replace($path, '', 0, strlen(getcwd()) + 1)
                : $path;
        }, array_values(array_diff(
            array_unique($paths),
            array_filter($this->config['excludes'], 'is_file')
        )));
    }

    /**
     * Get directories which are in include path.
     *
     * @return Generator
     */
    protected function includes(): Generator
    {
        $dirs = $this->directories('includes');

        // yield directories which are not subdirectory
        $offset = 0;

        foreach ($dirs as $dir) {
            ++$offset;

            foreach (array_slice($dirs, $offset) as $against) {
                if (Str::startsWith($dir, $against)) {
                    continue 2;
                }
            }

            yield $dir;
        }
    }

    /**
     * Get directories which are in exclude path and are subdirectory.
     *
     * @param string $parent
     *
     * @return array
     */
    public function excludes(string $parent): array
    {
        $dirs = $this->directories('excludes');

        foreach ($dirs as $dir) {
            if (Str::startsWith($dir, $parent)) {
                $result[] = trim(str_replace($parent, '', $dir), '/');
            }
        }

        return $result ?? [];
    }

    /**
     * Get directories from config and append "/" to the end of path.
     *
     * @param string $type
     *
     * @return array
     */
    protected function directories(string $type): array
    {
        $dirs = array_map(function ($dir) {
            return sprintf('%s/', rtrim($dir, '/'));
        }, array_filter($this->config[$type], 'is_dir'));

        // sort directory length using desc
        usort($dirs, function($a, $b) {
            return mb_strlen($b) <=> mb_strlen($a);
        });

        return $dirs;
    }

    /**
     * Backup database data and return file path.
     *
     * @return array|null
     */
    protected function database(): ?array
    {
        $dumper = $this->dumper();

        if (is_null($dumper)) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), Str::random(6));

        $key = sprintf('database.connections.%s', config('database.default'));

        $db = config($key);

        $dumper->setDbName($db['database'])
            ->setUserName($db['username'])
            ->setPassword($db['password'])
            ->dumpToFile($path);

        return [
            'path' => $path,
            'name' => sprintf('%s.sql', $db['database']),
        ];
    }

    /**
     * Get database dumper, return null if it is not supported.
     *
     * @return DbDumper|null
     */
    protected function dumper(): ?DbDumper
    {
        $mapping = [
            'mysql' => 'MySql',
            'mariadb' => 'MySql',
            'mongodb' => 'MongoDb',
            'sqlite' => 'Sqlite',
            'pgsql' => 'PostgreSql',
            'postgresql' => 'PostgreSql',
        ];

        $key = strtolower(config('database.default'));

        if (!isset($mapping[$key])) {
            $this->warn(sprintf('Not supported database type: "%s"', $key));

            return null;
        }

        $class = sprintf('\Spatie\DbDumper\Databases\%s', $mapping[$key]);

        return new $class;
    }

    /**
     * Compress tar file using gzip.
     *
     * @param string $path
     *
     * @return string|null
     */
    protected function compress(string $path): ?string
    {
        $process = new Process(['gzip', '--best', '--keep', $path]);

        $process->setTimeout(null);

        $process->start();

        $process->wait();

        if (!$process->isSuccessful()) {
            return null;
        }

        return sprintf('%s.gz', $path);
    }
}
