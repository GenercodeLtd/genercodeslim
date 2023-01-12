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
        $this->commands[] = \GenerCodeCmd\DictionaryCommand::class;
        $this->commands[] = \GenerCodeCmd\DownloadCommand::class;
        $this->commands[] = \GenerCodeCmd\PublishCommand::class;
        $this->commands[] = \GenerCodeCmd\UploadCommand::class;
        $this->commands[] = \GenerCodeCmd\CdnCommand::class;
        
    }

}