<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('listings:send-reminders')->twiceMonthly(1, 16, '9:00');;
        $schedule->command('notifications:anniversaries')->dailyAt('09:00');
        $schedule->command('notifications:birthday')->dailyAt('00:00');
        $schedule->command('backup:database')->dailyAt('03:00');
        $schedule->command('trips:remind')->dailyAt('09:30');
        $schedule->command('albums:import-elevations')->dailyAt('04:00');
        // TheBoise Wrapped: everyone's year, just after Christmas
        $schedule->command('wrapped:send')->yearlyOn(12, 27, '10:00');
        $schedule->command('backup:database --mail')->weeklyOn(0, '03:30');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
