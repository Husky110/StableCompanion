<?php

namespace App\Models;

use App\Http\Helpers\CivitAIConnector;
use App\Models\DataStructures\CivitAIModelType;
use App\Models\DataStructures\ModelBaseClass;
use App\Models\DataStructures\ModelBaseClassInterface;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Checkpoint extends ModelBaseClass implements ModelBaseClassInterface
{
    public $timestamps = false;

    public $fillable = [
        'image_name',
        'model_name',
        'civitai_id',
        'civitai_notes',
        'user_notes',
    ];

    // Relations
    public function tags() : BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'checkpoint_tag');
    }

    public function checkpointTags() : HasMany
    {
        return  $this->hasMany(CheckpointTag::class, 'checkpoint_id');
    }

    public function files() : HasMany
    {
        return $this->hasMany(CheckpointFile::class, 'base_id');
    }

    // -> activedownloads are here by parentclass

    // Functions

    public function checkIfOtherVersionsExistOnCivitAi(): array
    {
        return parent::queueCivitAIForOtherVersionsOfThisModel(CheckpointFile::class);
    }

    public static function createNewModelFromCivitAI(array $civitAIModelData, bool $syncTags) : static
    {
        $checkpoint = new Checkpoint([
            'model_name' => $civitAIModelData['name'],
            'civitai_id' => $civitAIModelData['id'],
        ]);
        $checkpoint->setModelImage($civitAIModelData);

        $checkpoint->civitai_notes = $civitAIModelData['description'];
        $checkpoint->save();
        if($syncTags){
            $checkpoint->syncCivitAITags($civitAIModelData);
        }
        return $checkpoint;
    }

    public static function checkModelFolderForNewFiles() : void
    {
        $disk = Storage::disk('checkpoints');
        foreach ($disk->allFiles() as $file){
            if(
                !CheckpointFile::checkWeitherFilesIsPossiblyACheckpointFile($file) ||
                str_contains($file, '/no_scan/')
            ){
                continue;
            }
            $existingCheckpointFile = CheckpointFile::where('filepath', $file)->first();
            if($existingCheckpointFile == null){
                $newCheckpoint = new Checkpoint([
                    'model_name' => basename($file),
                ]);
                $newCheckpoint->save();
                $newCheckpointFile = new CheckpointFile([
                    'base_id' => $newCheckpoint->id,
                    'filepath' => $file,
                    'baseModel' => $disk->size($file) < 6442450944 ? 'Some SD-Model' : 'Some XL-Model',
                ]);
                $newCheckpointFile->save();
            }
        }
    }

    public function getCivitAIModelType(): CivitAIModelType
    {
        return CivitAIModelType::CHECKPOINT;
    }

    public static function getModelFileClass(): string
    {
        return CheckpointFile::class;
    }
}
