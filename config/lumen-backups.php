<?php

return [

    /*
     * Prefix of backup file filename.
     *
     * ＊：Do not contain "-" at the end.
     */

    'name' => env('APP_NAME', 'lumen'),

    /*
     * Target directory that stores the backup files.
     *
     * Please ensure this directory will not contain
     * other files, or it will result in unexpected
     * behavior.
     */

    'destination' => storage_path('lumen-backups'),

    /*
     * Files and directories that will be backup.
     *
     * ＊：If your backup destination is in includes path,
     * make sure add it to excludes path. We doesn't auto
     * exclude it.
     */

    'includes' => [
        base_path(),
    ],

    'excludes' => [
        base_path('storage'),
        base_path('vendor'),
    ],

];
