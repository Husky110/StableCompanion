<?php

namespace App\Console\Commands;

use App\Http\Helpers\Aria2Connector;
use App\Http\Helpers\CivitAIConnector;
use App\Models\CivitDownload;
use Illuminate\Console\Command;

class AriaErrorDownloadCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stablecompanion:aria-error {ariaID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set\'s running download as error.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $download = CivitDownload::where('aria_id', $this->argument('ariaID'))->first();
        $download->status = 'error';
        $download->aria_id = null;
        $download->save();
        $ariaResult = Aria2Connector::getInstance()->tellStatus($this->argument('ariaID'));
        $basefile = $ariaResult['result']['files'][0]['path'];
        if(file_exists($basefile)){
            unlink($basefile);
        }
        if(file_exists($basefile.'.aria2')){
            unlink($basefile.'.aria2');
        }
    }
}
