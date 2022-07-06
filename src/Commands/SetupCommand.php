<?php

namespace Bogordesain\Backups\Commands;

use Illuminate\Console\Command;

class SetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup backup package, run this command after install or upgrade.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $path = config('lumen-backups.destination');

        if (!is_dir($path)) {
            mkdir($path);
        }

        $path = sprintf('%s/.gitignore', $path);

        if (!is_file($path)) {
            file_put_contents(
                $path,
                sprintf('*%s!.gitignore%s', PHP_EOL, PHP_EOL)
            );
        }

        $this->info('Setup backup package successfully.');
    }
}
