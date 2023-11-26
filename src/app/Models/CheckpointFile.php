<?php

namespace App\Models;

use App\Http\Helpers\CivitAIConnector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class CheckpointFile extends Model
{
    public $timestamps = false;

    public $fillable = [
        'checkpoint_id',
        'filepath',
        'civitai_version',
        'civitai_description',
        'baseModel'
    ];

    // Relations
    public function checkpoint() : BelongsTo
    {
        return $this->belongsTo(Checkpoint::class);
    }

    public function images() : HasMany
    {
        return $this->hasMany(AIImage::class);
    }

    // Functions

    public function loadImagesFromCivitAIForThisFile() : void
    {
        if($this->civitai_version == null || $this->checkpoint->civitai_id == null || $this->civitai_version == 'custom'){
            return;
        }
        $meta = CivitAIConnector::getSpecificModelVersionByModelIDAndVersionID($this->checkpoint->civitai_id, $this->civitai_version);
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
            $filename = $this->checkpoint->civitai_id.'_'.$this->civitai_version.'_'.$this->id.'_'.basename($metaImage['url']);
            Storage::disk('ai_images')->put($filename, file_get_contents($metaImage['url']));
            $image = new AIImage([
                'checkpoint_file_id' => $this->id,
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

    private function deleteAllAIImages()
    {
        foreach ($this->images as $image){
            Storage::disk('ai_images')->delete($image->filename);
            $image->delete();
        }
    }

    public function deleteCheckpointFile()
    {
        $this->deleteAllAIImages();
        Storage::disk('checkpoints')->delete($this->filepath);
        $this->delete();
    }

    public static function checkWeitherFilesIsPossiblyACheckpointFile(string $filepath) : bool
    {
        $disk = Storage::disk('checkpoints');
        //check extension
        if(
            !str_ends_with($filepath, 'safetensors') == false &&
            !str_ends_with($filepath, 'ckpt') == false
        ){
            return false;
        }
        // check filesize is min. 1.9GB
        if($disk->size($filepath) < 2040109466){
            return false;
        }
        return true;
    }
}
