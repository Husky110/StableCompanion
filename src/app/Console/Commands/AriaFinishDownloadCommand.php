<?php

namespace App\Console\Commands;

use App\Http\Helpers\Aria2Connector;
use App\Http\Helpers\CivitAIConnector;
use App\Models\AIImage;
use App\Models\Checkpoint;
use App\Models\CheckpointFile;
use App\Models\CivitDownload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AriaFinishDownloadCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stablecompanion:aria-finish {ariaID} {downloadPath}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Script that\'s beeing run, when Aria2c finishes a download.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ariaID = $this->argument('ariaID');
        $downloadPath = $this->argument('downloadPath');
        $civitAIDownload = CivitDownload::where('aria_id', $ariaID)->first();
        if(!$civitAIDownload){
            return;
        }
        if(str_ends_with($downloadPath, 'login')){
            $civitAIDownload->status = 'error';
            $civitAIDownload->error_message = 'Error: Model requires Login to download!<br>Please open <a href="'.$civitAIDownload->url.'" target="_blank">'.$civitAIDownload->url.'</a> and download the model manually to the correct folder.<br>Afterwards let StableCompanion rescan the according folder and link the model manually.<br>You can delete this download once you are done.';
            $civitAIDownload->save();
            unlink($downloadPath);
            return;
        }
        switch ($civitAIDownload->type){
            case 'checkpoint_sd':
                $filePath = Storage::disk('checkpoints')->path('').'sd/'.$civitAIDownload->civit_id.'_'.$civitAIDownload->version.'_'.basename($downloadPath);
                if(!is_dir(dirname($filePath))){
                    mkdir(dirname($filePath));
                }
                $this->info($filePath);
                if(rename($downloadPath, $filePath)){
                    $checkpointFile = $this->createCheckpointFile($civitAIDownload, 'sd/'.basename($filePath));
                    if($civitAIDownload->load_examples){
                        $this->loadImagesForCheckpointFile($civitAIDownload, $checkpointFile);
                    }
                    $civitAIDownload->delete();
                } else {
                    $civitAIDownload->status = 'error_moving_file';
                    $civitAIDownload->save();
                }
                break;
            case 'checkpoint_xl':
                $filePath = Storage::disk('checkpoints')->path('').'xl/'.$civitAIDownload->civit_id.'_'.$civitAIDownload->version.'_'.basename($downloadPath);
                if(!is_dir(dirname($filePath))){
                    mkdir(dirname($filePath));
                }
                if(rename($downloadPath, $filePath)){
                    $checkpointFile = $this->createCheckpointFile($civitAIDownload, 'xl/'.basename($filePath));
                    // loading Images for Checkpoint
                    if($civitAIDownload->load_examples){
                        $this->loadImagesForCheckpointFile($civitAIDownload, $checkpointFile);
                    }
                    $civitAIDownload->delete();
                } else {
                    $civitAIDownload->status = 'error_moving_file';
                    $civitAIDownload->save();
                }
                break;

            default:
                return;
        }
        $this->info($ariaID);
        $this->info('File imported successfully.');

        // Start next download...
        $possibleNextDownload = CivitDownload::where('status', 'pending')->first();
        if($possibleNextDownload != null){
            Aria2Connector::sendDownloadToAria2($possibleNextDownload);
        }

    }

    private function createCheckpointFile(CivitDownload $download, string $filepath) : CheckpointFile
    {
        $metaData = CivitAIConnector::getSpecificModelVersionByModelIDAndVersionID($download->civit_id, $download->version);
        $checkpointFile = new CheckpointFile([
            'checkpoint_id' => Checkpoint::where('civitai_id', $download->civit_id)->firstOrFail()->id,
            'filepath' => $filepath,
            'civitai_version' => $download->version,
            'civitai_description' => $metaData['description'],
            'baseModel' => $metaData['baseModel']
        ]);
        $checkpointFile->save();
        return $checkpointFile;
    }

    private function loadImagesForCheckpointFile(CivitDownload $download, CheckpointFile $checkpointFile)
    {
        $meta = CivitAIConnector::getSpecificModelVersionByModelIDAndVersionID($download->civit_id, $download->version);
        $counter = 0;
        foreach ($meta['images'] as $metaImage){
            if(
                $metaImage['type'] != 'image' ||
                isset($metaImage['meta']['prompt']) == false ||
                isset($metaImage['meta']['negativePrompt']) == false ||
                isset($metaImage['meta']['sampler']) == false ||
                isset($metaImage['meta']['cfgScale']) == false ||
                isset($metaImage['meta']['steps']) == false ||
                isset($metaImage['meta']['seed']) == false ||
                isset($metaImage['meta']['Size']) == false
            ){
                continue;
            }
            $counter++;
            $filename = $download->civit_id.'_'.$download->version.'_'.basename($metaImage['url']);
            Storage::disk('ai_images')->put($filename, file_get_contents($metaImage['url']));
            $image = new AIImage([
                'checkpoint_file_id' => $checkpointFile->id,
                'filename' => $filename,
                'positive' => $metaImage['meta']['prompt'],
                'negative' => $metaImage['meta']['negativePrompt'],
                'sampler' => $metaImage['meta']['sampler'],
                'cfg' => number_format($metaImage['meta']['cfgScale'], 1),
                'steps' => $metaImage['meta']['steps'],
                'seed' => $metaImage['meta']['seed'],
                'initial_size' => $metaImage['meta']['Size'],
                'source' => 'CivitAI',
            ]);
            $image->save();
            if($counter == 10){
                break;
            }
        }
    }
}
