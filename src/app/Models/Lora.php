<?php

namespace App\Models;

use App\Http\Helpers\CivitAIConnector;
use App\Models\DataStructures\ModelBaseClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Lora extends ModelBaseClass
{
    public $timestamps = false;

    public $fillable = [
        'lora_name',
        'lora_type',
        'image_name',
        'civitai_id',
        'civit_notes',
        'user_notes',
    ];

    // Relations
    public function tags() : BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'lora_tag');
    }

    public function loraTags() : HasMany
    {
        return  $this->hasMany(LoraTag::class, 'lora_id');
    }

    public function files() : HasMany
    {
        return $this->hasMany(LoraFile::class);
    }

    // -> activedownloads via parent Class

    // Functions


    function checkIfOtherVersionsExistOnCivitAi(): array
    {
        if($this->civitai_id == null){
            return [];
        }
        $metaData = CivitAIConnector::getModelMetaByID($this->civitai_id);
        $versionsNotInCollection = [];
        foreach ($metaData['modelVersions'] as $modelVersion){
            if(LoraFile::where('civitai_version', $modelVersion['id'])->count() == 0){
                $versionsNotInCollection[$modelVersion['id']] = $modelVersion['name'];
            }
        }
        return $versionsNotInCollection;
    }

    static function createNewModelFromCivitAI(array $civitAIModelData, bool $syncTags): static
    {
        $lora = new Lora([
            'lora_name' => $civitAIModelData['name'],
            'civitai_id' => $civitAIModelData['id'],
        ]);
        $lora->setModelImage($civitAIModelData);

        $lora->civit_notes = $civitAIModelData['description'];
        $lora->save();
        if($syncTags){
            $lora->syncCivitAITags($civitAIModelData);
        }
        return $lora;
    }

    static function checkModelFolderForNewFiles(): void
    {
        $disk = Storage::disk('loras');
        foreach ($disk->allFiles() as $file){
            $existingLoraFile = LoraFile::where('filepath', $file)->first();
            if($existingLoraFile == null){
                $newLora = new Checkpoint([
                    'lora_name' => basename($file),
                ]);
                $newLora->save();
                $newLoraFile = new LoraFile([
                    'lora_id' => $newLora->id,
                    'filepath' => $file,
                    'baseModel' => $disk->size($file) < 6442450944 ? 'Some SD-Model' : 'Some XL-Model',
                ]);
                $newLoraFile->save();
            }
        }
    }
}
