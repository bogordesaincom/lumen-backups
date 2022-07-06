<?php

namespace Bogordesain\Backups\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;

class CleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup outdated backups.';

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
     */
    public function handle(): void
    {
        $this->config = config('lumen-backups');

        if (!is_dir($this->config['destination'])) {
            $this->warn('Backup directory does not exist.');
            return;
        }

        $backups = $this->backups();

        if ($backups->isEmpty()) {
            $this->info('No backups need to be cleanup.');
            return;
        }

        $groups = $backups->groupBy('in-month');

        $beforeLastMonth = $groups->get(0, collect());

        $inThePastMonth = $groups->get(1, collect());

        // backups that before last month will only preserve every 7 days
        if ($beforeLastMonth->count() > 1) {
            $preview = Carbon::createFromFormat('Y-m-d', $beforeLastMonth->shift()['date'])->startOfDay();

            foreach ($beforeLastMonth as $backup) {
                if ($preview->diffInDays($backup['date']) < 7) {
                    unlink($backup['path']);
                } else {
                    $preview->setDate(...explode('-', $backup['date']));
                }
            }
        }

        // in the past month backups will only preserve one backup per day
        if ($inThePastMonth->count() > 1) {
            $exists = [];

            foreach ($inThePastMonth as $backup) {
                if (isset($exists[$backup['date']])) {
                    unlink($backup['path']);
                } else {
                    $exists[$backup['date']] = true;
                }
            }
        }

        $this->info('Backup cleanup successfully.');
    }

    /**
     * Get backup files collection.
     *
     * @return Collection
     */
    protected function backups(): Collection
    {
        $backups = collect();

        foreach ($this->backupIterator() as $file) {
            $time = Carbon::createFromFormat('Y-m-d-H-i-s', substr(
                strstr($file->getFilename(), '.', true),
                strlen(rtrim($this->config['name'], '-')) + 1
            ));

            if ($time->diffInHours() < 24) {
                continue;
            }

            $backups->put($time->timestamp, [
                'date' => $time->toDateString(),
                'in-month' => $time->diffInDays() <= 31,
                'path' => $file->getPathname(),
            ]);
        }

        return $backups->sortKeys()->values();
    }

    /**
     * Get backup files iterator.
     *
     * @return \Iterator|\Symfony\Component\Finder\SplFileInfo[]
     */
    protected function backupIterator()
    {
        return (new Finder)
            ->files()
            ->depth(0)
            ->name(sprintf('%s-*', rtrim($this->config['name'], '-')))
            ->in($this->config['destination'])
            ->getIterator() ;
    }
}
