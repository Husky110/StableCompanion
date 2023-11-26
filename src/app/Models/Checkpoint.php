<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Checkpoint extends Model
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

    public function activedownloads() : HasMany
    {
        return $this->hasMany(CivitDownload::class, 'civit_id', 'civitai_id');
    }

    // Functions

    public function deleteCheckpoint()
    {
        if($this->image_name != 'placeholder.png'){
            Storage::disk('modelimages')->delete($this->image_name);
        }
        $this->tags()->sync([]);
        $this->delete();
    }


    public function setModelImage(array $civitAIModelData) : void
    {
        $modelImageDisk = Storage::disk('modelimages');
        $imageURL = '';
        $imagename = '';
        foreach ($civitAIModelData['modelVersions'][0]['images'] as $image){
            if($image['type'] == 'image'){
                $imageURL = $image['url'];
                break;
            }
        }
        if($imageURL){
            $imagename = basename($imageURL);
            $modelImageDisk->put($imagename, file_get_contents($imageURL));
        }

        if($imageURL){
            $this->image_name = $imagename;
        }
        $this->save();
    }

    public function syncCivitAITags(array $civitAIModelData) : void
    {
        $syncArray = [];
        foreach ($civitAIModelData['tags'] as $civitTag){
            $tag = Tag::where('tagname', $civitTag)->first();
            if(!$tag){
                $tag = new Tag([
                    'tagname' => $civitTag
                ]);
                $tag->save();
            }
            $syncArray[] = $tag->id;
        }
        $this->tags()->syncWithoutDetaching($syncArray);
    }

    public static function createNewCheckpointFromCivitAI(array $civitAIModelData, bool $syncTags) : Checkpoint
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

    public static function scanCheckpointFolderForNewFiles()
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
