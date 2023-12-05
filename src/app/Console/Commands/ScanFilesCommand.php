<?php

namespace App\Console\Commands;

use App\Http\Helpers\WebUIConnector;
use App\Models\Checkpoint;
use App\Models\Lora;
use Illuminate\Console\Command;

class ScanFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stablecompanion:scan-for-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan for new files.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if(WebUIConnector::getInstance()->testConnection()){
            Checkpoint::checkModelFolderForNewFiles();
            Lora::checkModelFolderForNewFiles();
        }
    }
}
