<?php

namespace App\Console\Commands;

use App\Filament\Helpers\CivitAIDownloadHelper;
use App\Http\Helpers\Aria2Connector;
use App\Http\Helpers\CivitAIConnector;
use App\Models\Checkpoint;
use App\Models\CheckpointFile;
use App\Models\CivitDownload;
use App\Models\Embedding;
use App\Models\EmbeddingFile;
use App\Models\Lora;
use App\Models\LoraFile;
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

    protected string $downloadPath = '';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ariaID = $this->argument('ariaID');
        $downloadPath = $this->argument('downloadPath');
        $this->downloadPath = $downloadPath;
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
        $modelFile = null;
        $storageSuccessfull = false;

        switch (strtolower($civitAIDownload->type)){
            case 'checkpoint_sd':
                $filePath = CivitAIDownloadHelper::generateFileNameForDownloadedModel(basename($downloadPath), Storage::disk('checkpoints')->path('').'sd/'.basename($downloadPath));
                if(rename($downloadPath, $filePath)){
                    $modelFile = $this->createCheckpointFile($civitAIDownload, 'sd/'.basename($filePath));
                    $storageSuccessfull = true;
                }
                break;
            case 'checkpoint_xl':
                $filePath = CivitAIDownloadHelper::generateFileNameForDownloadedModel(basename($downloadPath), Storage::disk('checkpoints')->path('').'xl/'.basename($downloadPath));
                if(rename($downloadPath, $filePath)){
                    $modelFile = $this->createCheckpointFile($civitAIDownload, 'xl/'.basename($filePath));
                    $storageSuccessfull = true;
                }
                break;
            case 'lora_sd':
                $filePath = CivitAIDownloadHelper::generateFileNameForDownloadedModel(basename($downloadPath), Storage::disk('loras')->path('').'sd/'.basename($downloadPath));
                if(rename($downloadPath, $filePath)){
                    $modelFile = $this->createLoraFile($civitAIDownload, 'sd/'.basename($filePath));
                    $storageSuccessfull = true;
                }
                break;
            case 'lora_xl':
                $filePath = CivitAIDownloadHelper::generateFileNameForDownloadedModel(basename($downloadPath), Storage::disk('loras')->path('').'xl/'.basename($downloadPath));
                if(rename($downloadPath, $filePath)){
                    $modelFile = $this->createLoraFile($civitAIDownload, 'xl/'.basename($filePath));
                    $storageSuccessfull = true;
                }
                break;
            case 'embedding_sd':
                $filePath = CivitAIDownloadHelper::generateFileNameForDownloadedModel(basename($downloadPath), Storage::disk('embeddings')->path('').'sd/'.basename($downloadPath));
                if(rename($downloadPath, $filePath)){
                    $modelFile = $this->createEmebeddingFile($civitAIDownload, 'sd/'.basename($filePath));
                    $storageSuccessfull = true;
                }
                break;
            case 'embedding_xl':
                $filePath = CivitAIDownloadHelper::generateFileNameForDownloadedModel(basename($downloadPath), Storage::disk('embeddings')->path('').'xl/'.basename($downloadPath));
                if(rename($downloadPath, $filePath)){
                    $modelFile = $this->createEmebeddingFile($civitAIDownload, 'sd/'.basename($filePath));
                    $storageSuccessfull = true;
                }
                break;
            default:
                return;
        }
        if(!is_dir(dirname($filePath))){
            mkdir(dirname($filePath));
        }
        if($storageSuccessfull){
            $civitAIDownload->delete();
            if($civitAIDownload->load_examples){
                $modelFile->loadImagesFromCivitAIForThisFile();
            }
        } else {
            $civitAIDownload->status = 'error_moving_file';
            $civitAIDownload->save();
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
            'base_id' => Checkpoint::where('civitai_id', $download->civit_id)->firstOrFail()->id,
            'filepath' => $filepath,
            'version_name' => $metaData['name'] ?? null,
            'civitai_version' => $download->version,
            'civitai_description' => $metaData['description'],
            'baseModel' => $metaData['baseModel'],
            'trained_words' => isset($metaData['trainedWords']) ? json_encode($metaData['trainedWords'], JSON_UNESCAPED_UNICODE) : null,
        ]);
        $checkpointFile->save();
        return $checkpointFile;
    }

    private function createLoraFile(CivitDownload $download, string $filepath) : LoraFile
    {
        $metaData = CivitAIConnector::getSpecificModelVersionByModelIDAndVersionID($download->civit_id, $download->version);
        $lorafile = new LoraFile([
            'base_id' => Lora::where('civitai_id', $download->civit_id)->firstOrFail()->id,
            'version_name' => $metaData['name'] ?? null,
            'filepath' => $filepath,
            'civitai_version' => $download->version,
            'civitai_description' => $metaData['description'],
            'baseModelType' => $metaData['baseModel'],
            'trained_words' => isset($metaData['trainedWords']) ? json_encode($metaData['trainedWords'], JSON_UNESCAPED_UNICODE) : null
        ]);
        $lorafile->save();
        return $lorafile;
    }

    private function createEmebeddingFile(CivitDownload $download, string $filepath) : EmbeddingFile
    {
        $metaData = CivitAIConnector::getSpecificModelVersionByModelIDAndVersionID($download->civit_id, $download->version);
        $embeddingfile = new EmbeddingFile([
            'base_id' => Embedding::where('civitai_id', $download->civit_id)->firstOrFail()->id,
            'version_name' => $metaData['name'] ?? null,
            'filepath' => $filepath,
            'civitai_version' => $download->version,
            'civitai_description' => $metaData['description'],
            'baseModelType' => $metaData['baseModel'],
            'trained_words' => json_encode([explode('.',basename($this->downloadPath))[0]], JSON_UNESCAPED_UNICODE)
        ]);
        $embeddingfile->save();
        return $embeddingfile;
    }
}
