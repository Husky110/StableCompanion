<?php

namespace App\Console\Commands;

use App\Http\Helpers\Aria2Connector;
use App\Models\CivitDownload;
use Illuminate\Console\Command;

class ResumeActiveDownloadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stablecompanion:resume-active-downloads';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resumes active aria2-downloads.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Start next download...
        $possibleNextDownload = CivitDownload::where('status', 'active')->first();
        if($possibleNextDownload != null){
            Aria2Connector::sendDownloadToAria2($possibleNextDownload);
        }
    }
}
