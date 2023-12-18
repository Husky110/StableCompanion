<?php

namespace App\Models;

use App\Http\Helpers\CivitAIConnector;
use App\Models\DataStructures\CivitAIModelType;
use App\Models\DataStructures\ModelBaseClass;
use App\Models\DataStructures\ModelBaseClassInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Lora extends ModelBaseClass implements ModelBaseClassInterface
{
    public $timestamps = false;

    public $fillable = [
        'model_name',
        'image_name',
        'civitai_id',
        'civitai_notes',
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
        return $this->hasMany(LoraFile::class, 'base_id');
    }

    // -> activedownloads via parent Class

    // Functions


    function checkIfOtherVersionsExistOnCivitAi(): array
    {
        return parent::queueCivitAIForOtherVersionsOfThisModel(LoraFile::class);
    }

    public static function createNewModelFromCivitAI(array $civitAIModelData, bool $syncTags): static
    {
        $lora = new Lora([
            'model_name' => $civitAIModelData['name'],
            'civitai_id' => $civitAIModelData['id'],
        ]);
        $lora->setModelImage($civitAIModelData);

        $lora->civitai_notes = $civitAIModelData['description'];
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
            $fileExtention = explode('.', $file);
            $fileExtention = $fileExtention[count($fileExtention) - 1];
            if(!in_array($fileExtention, ['safetensors', 'ckpt', 'pt', 'bin'])){
                continue;
            }
            $existingLoraFile = LoraFile::where('filepath', $file)->first();
            if($existingLoraFile == null){
                $newLora = new Lora([
                    'model_name' => basename($file),
                ]);
                $newLora->save();
                $newLoraFile = new LoraFile([
                    'base_id' => $newLora->id,
                    'filepath' => $file,
                    'baseModelType' => LoraFile::determineLoraTypeByFilename($file)
                ]);
                $newLoraFile->save();
            }
        }
    }

    public function getCivitAIModelType(): CivitAIModelType
    {
        return CivitAIModelType::LORA;
    }

    public static function getModelFileClass(): string
    {
        return LoraFile::class;
    }
}
