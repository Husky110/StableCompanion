<?php

namespace App\Models\DataStructures;

use App\Http\Helpers\CivitAIConnector;
use App\Http\Helpers\GeneralHelper;
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
        return $this->morphMany(AIImage::class, 'model_file');
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
                'model_file_type' => static::class,
                'model_file_id' => $this->id,
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
        $this->changePreviewImage(true, 0, false);
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
        $disk = Storage::disk($this->diskname);
        $disk->delete($this->filepath);
        $filePathForPreviewImage = GeneralHelper::getFilePathWithoutExtension($this->filepath).'.preview.png';
        if($disk->exists($filePathForPreviewImage)){
            $disk->delete($filePathForPreviewImage);
        }
        $this->delete();
    }

    public function changePreviewImage(bool $keepOldImage = true, int $aiImageID = 0, bool $forceUseModelImage = false)
    {
        $disk = Storage::disk($this->diskname);
        $filename = GeneralHelper::getFilePathWithoutExtension($this->filepath).'.preview.png';
        $imageResource = null;
        if($aiImageID > 0){
            $imageResource = imagecreatefromstring(Storage::disk('ai_images')->get(AIImage::findOrFail($aiImageID)->filename));
        } else {
            $imageToUse = AIImage::where([
                ['model_file_type' , '=', static::class],
                ['model_file_id', '=', $this->id]
            ])->first();
        }

        if($imageToUse == null || $forceUseModelImage == true){
            $imageResource = imagecreatefromstring(Storage::disk('modelimages')->get($this->parentModel->image_name));
        } else {
            $imageResource = imagecreatefromstring(Storage::disk('ai_images')->get($imageToUse->filename));
        }

        if($imageResource != null){
            $imageDestination = imagecreatetruecolor(imagesx($imageResource), imagesy($imageResource));
            if($disk->exists($filename)){
                if($keepOldImage == false){
                    imagecopy($imageDestination, $imageResource, 0, 0, 0, 0, imagesx($imageResource), imagesy($imageResource));
                    imagepng($imageDestination, $disk->path($filename));
                    imagedestroy($imageDestination);
                    imagedestroy($imageResource);
                } // no else
            } else {
                imagecopy($imageDestination, $imageResource, 0, 0, 0, 0, imagesx($imageResource), imagesy($imageResource));
                imagepng($imageDestination, $disk->path($filename));
                imagedestroy($imageDestination);
                imagedestroy($imageResource);
            }
        }
    }
}

