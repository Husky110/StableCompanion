<?php

namespace App\Console\Commands;

use App\Models\CivitDownload;
use Illuminate\Console\Command;

class AriaStartDownloadCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stablecompanion:aria-start {ariaID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set\'s started download as active.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $download = CivitDownload::where('aria_id', $this->argument('ariaID'))->first();
        $download->status = 'active';
        $download->save();
    }
}
