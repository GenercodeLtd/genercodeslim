<?php

namespace GenerCodeSlim\Console;


class ConsoleKernel extends \Illuminate\Foundation\Console\Kernel
{
 
    
    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        //
        $this->commands[] = new \GenerCodeCmd\DictionaryCommand($this->app);
        $this->commands[] = new \GenerCodeCmd\DownloadCommand($this->app);
        $this->commands[] = new \GenerCodeCmd\PublishCommand($this->app);
        $this->commands[] = new \GenerCodeCmd\UploadCommand($this->app);
        $this->commands[] = new \GenerCodeCmd\CdnCommand($this->app);
        
    }

}