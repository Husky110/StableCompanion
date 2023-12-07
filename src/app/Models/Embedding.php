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

class Embedding extends ModelBaseClass implements ModelBaseClassInterface
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
        return $this->belongsToMany(Tag::class, 'embedding_tag');
    }

    public function embeddingTags() : HasMany
    {
        return  $this->hasMany(EmbeddingTag::class, 'embedding_tag');
    }

    public function files() : HasMany
    {
        return $this->hasMany(EmbeddingFile::class, 'base_id');
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
            if(EmbeddingFile::where('civitai_version', $modelVersion['id'])->count() == 0){
                $versionsNotInCollection[$modelVersion['id']] = $modelVersion['name'];
            }
        }
        return $versionsNotInCollection;
    }

    public static function createNewModelFromCivitAI(array $civitAIModelData, bool $syncTags): static
    {
        $embedding = new Embedding([
            'model_name' => $civitAIModelData['name'],
            'civitai_id' => $civitAIModelData['id'],
        ]);
        $embedding->setModelImage($civitAIModelData);

        $embedding->civitai_notes = $civitAIModelData['description'];
        $embedding->save();
        if($syncTags){
            $embedding->syncCivitAITags($civitAIModelData);
        }
        return $embedding;
    }

    static function checkModelFolderForNewFiles(): void
    {
        $disk = Storage::disk('embeddings');
        foreach ($disk->allFiles() as $file){
            $fileExtention = explode('.', $file);
            $fileExtention = $fileExtention[count($fileExtention) - 1];
            if(!in_array($fileExtention, ['pt', 'bin'])){
                continue;
            }
            $existingEmbeddingFile = EmbeddingFile::where('filepath', $file)->first();
            if($existingEmbeddingFile == null){
                $newEmbedding = new Embedding([
                    'model_name' => basename($file),
                ]);
                $newEmbedding->save();
                $newEmbeddingFile = new EmbeddingFile([
                    'base_id' => $newEmbedding->id,
                    'filepath' => $file,
                    'baseModelType' => 'unknown'
                ]);
                $newEmbeddingFile->save();
            }
        }
    }

    public function getCivitAIModelType(): CivitAIModelType
    {
        return CivitAIModelType::EMBEDDING;
    }

    public static function getModelFileClass(): string
    {
        return EmbeddingFile::class;
    }

}
