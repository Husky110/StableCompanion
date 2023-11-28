<?php

namespace App\Models;

use App\Http\Helpers\CivitAIConnector;
use App\Models\DataStructures\ModelBaseClass;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Checkpoint extends ModelBaseClass
{
    public $timestamps = false;

    public $fillable = [
        'image_name',
        'checkpoint_name',
        'civitai_id',
        'civit_notes',
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
        return $this->hasMany(CheckpointFile::class);
    }

    // -> activedownloads are here by parentclass

    // Functions

    public function checkIfOtherVersionsExistOnCivitAi() : array
    {
        if($this->civitai_id == null){
            return [];
        }
        $metaData = CivitAIConnector::getModelMetaByID($this->civitai_id);
        $versionsNotInCollection = [];
        foreach ($metaData['modelVersions'] as $modelVersion){
            if(CheckpointFile::where('civitai_version', $modelVersion['id'])->count() == 0){
                $versionsNotInCollection[$modelVersion['id']] = $modelVersion['name'];
            }
        }
        return $versionsNotInCollection;
    }

    public static function createNewModelFromCivitAI(array $civitAIModelData, bool $syncTags) : static
    {
        $checkpoint = new Checkpoint([
            'checkpoint_name' => $civitAIModelData['name'],
            'civitai_id' => $civitAIModelData['id'],
        ]);
        $checkpoint->setModelImage($civitAIModelData);

        $checkpoint->civit_notes = $civitAIModelData['description'];
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
                    'checkpoint_name' => basename($file),
                ]);
                $newCheckpoint->save();
                $newCheckpointFile = new CheckpointFile([
                    'checkpoint_id' => $newCheckpoint->id,
                    'filepath' => $file,
                    'baseModel' => $disk->size($file) < 6442450944 ? 'Some SD-Model' : 'Some XL-Model',
                ]);
                $newCheckpointFile->save();
            }
        }
    }
}
