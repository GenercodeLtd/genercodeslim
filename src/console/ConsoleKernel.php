<?php

namespace GenerCodeSlim\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Illuminate\Foundation\Console\Kernel;

class ConsoleKernel extends Kernel
{
    protected Container $app;
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    function __construct(Container $app) {
        $this->app = $app;
    }


    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
    }


    protected function registerCommand($cls) {
        $obj = new $cls($this->app);
        $app->addCommand($obj);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->registerCommand(\GenerCodeCmd\DictionaryCommand::class);
        $this->registerCommand(\GenerCodeCmd\DownloadCommand::class);
        $this->registerCommand(\GenerCodeCmd\MigrationCommand::class);
        $this->registerCommand(\GenerCodeCmd\PublishCommand::class);
        $this->registerCommand(\GenerCodeCmd\UploadCommand::class);
        $this->registerCommand(\GenerCodeCmd\CdnCommand::class);
    }
}
