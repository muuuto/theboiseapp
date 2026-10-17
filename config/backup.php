<?php

return [

    // Where database backups are written (never inside public/)
    'path' => storage_path('app/backups'),

    // How many backup files to keep before deleting the oldest
    'keep' => (int) env('BACKUP_KEEP', 30),

    // If set, `backup:database --mail` sends the backup to this address
    'mail_to' => env('BACKUP_MAIL_TO'),

];
