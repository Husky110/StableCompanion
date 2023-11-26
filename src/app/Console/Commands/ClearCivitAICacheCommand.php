<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ClearCivitAICacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stablecompanion:clear-civitaicache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears the CivitAI-Cache';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cache = Storage::disk('civitai_cache');
        foreach ($cache->allFiles() as $cacheFile){
            if(!in_array($cacheFile, ['.', '..', '.gitkeep'])){
                $cache->delete($cacheFile);
            }
        }
    }
}
