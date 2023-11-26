<?php

namespace App\Console\Commands;

use App\Http\Helpers\Aria2Connector;
use App\Models\CivitDownload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
        DB::table('civit_downloads')
            ->where([['status', '=', 'active']])
            ->update(['aria_id' => null])
        ;
        $possibleNextDownloads = CivitDownload::where('status', 'active')->get();
        if(count($possibleNextDownloads) > 0){
            foreach ($possibleNextDownloads as $download){
                Aria2Connector::sendDownloadToAria2($download);
            }
        }
    }
}
