<?php

namespace App\Models\DataStructures;

use App\Http\Helpers\CivitAIConnector;
use App\Models\AIImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

abstract class ModelFileBaseClass extends Model
{
    // properties
    public string $diskname;

    // relevant relations
    public function images() : MorphMany
    {
        return $this->morphMany(AIImage::class, 'model_file', 'model_file_class');
    }

    abstract function parentModel() : BelongsTo;

    // Functions

    public function loadImagesFromCivitAIForThisFile() : void
    {
        if($this->civitai_version == null || $this->parentModel->civitai_id == null || $this->civitai_version == 'custom'){
            return;
        }
        $meta = CivitAIConnector::getSpecificModelVersionByModelIDAndVersionID($this->parentModel->civitai_id, $this->civitai_version);
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
            $filename = $this->parentModel->civitai_id.'_'.$this->civitai_version.'_'.$this->id.'_'.basename($metaImage['url']);
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

    protected function deleteAllAIImages()
    {
        foreach ($this->images as $image){
            Storage::disk('ai_images')->delete($image->filename);
            $image->delete();
        }
    }

    public function deleteModelFile()
    {
        $this->deleteAllAIImages();
        Storage::disk($this->diskname)->delete($this->filepath);
        $this->delete();
    }
}
