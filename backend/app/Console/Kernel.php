<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // 每天凌晨0点计算余额
        $schedule->command('balance:calculate-daily')
            ->daily()
            ->at('00:00')
            ->runInBackground();
        
        // 每天凌晨0点清理草稿
        $schedule->command('drafts:cleanup')
            ->daily()
            ->at('00:00')
            ->runInBackground();
        
        // 每小时清理过期Token
        $schedule->call(function () {
            \DB::table('personal_access_tokens')
                ->where('expires_at', '<', now())
                ->delete();
        })->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
