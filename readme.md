# Juice Backups Package

Backup your application and database data to local filesystem.

## Note

This package use [spatie/db-dumper](https://github.com/spatie/db-dumper) to dump database data. For supporting database type, please check [here](https://github.com/spatie/db-dumper#requirements) and make sure meets the requirement.

## Installation

1. run composer require command `composer require juice/backups`

2. register `\Juice\Backups\BackupsServiceProvider::class` service provider

3. copy config file and set it up

   - Laravel - `php artisan vendor:publish --provider="Juice\Backups\BackupsServiceProvider"`

   - Lumen - `cp vendor/juice/backups/config/juice-backups.php config/`

     (make sure config directory exist)

4. run setup command `php artisan backup:setup`

5. done

## Commands

- backup:setup - initialize package
- backup:run - backup application and database
- backup:cleanup - cleanup outdated backups

## Usage

All you need to do is add `run` and `cleanup` command to schedule method.

```php
/**
 * Define the application's command schedule.
 *
 * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
 * @return void
 */
protected function schedule(Schedule $schedule)
{
    $schedule->command('backup:run')->hourly();
    $schedule->command('backup:cleanup')->dailyAt('01:30');
}
```

You can find more schedule information [here](https://laravel.com/docs/5.7/scheduling).

## Backup Ｍechanism

1. hourly backups for the past 24 hours
2. daily backups for the past month
3. weekly backups for all previous months