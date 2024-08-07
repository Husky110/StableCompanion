<?php

namespace App\Console\Commands;

use App\Models\Checkpoint;
use App\Models\Embedding;
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
        Checkpoint::checkModelFolderForNewFiles();
        Lora::checkModelFolderForNewFiles();
        Embedding::checkModelFolderForNewFiles();
    }
}
