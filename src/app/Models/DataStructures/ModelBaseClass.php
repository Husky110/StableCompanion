<?php

namespace App\Models\DataStructures;

use App\Http\Helpers\CivitAIConnector;
use App\Models\CheckpointFile;
use App\Models\CivitDownload;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

abstract class ModelBaseClass extends Model
{
    // relevant Relations
    abstract function tags() : BelongsToMany;
    abstract function files() : HasMany;

    public function activedownloads() : HasMany
    {
        return $this->hasMany(CivitDownload::class, 'civit_id', 'civitai_id');
    }

    // Functions
    public function deleteModel()
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

    public function queueCivitAIForOtherVersionsOfThisModel(string $modelFileClass) : array
    {
        if($this->civitai_id == null){
            return [];
        }
        $metaData = CivitAIConnector::getModelMetaByID($this->civitai_id);
        $versionsNotInCollection = [];
        foreach ($metaData['modelVersions'] as $modelVersion){
            if($modelFileClass::where('civitai_version', $modelVersion['id'])->count() == 0){
                $versionsNotInCollection[$modelVersion['id']] = $modelVersion['name'];
            }
        }
        return $versionsNotInCollection;
    }

    abstract function checkIfOtherVersionsExistOnCivitAi() : array;

    abstract static function createNewModelFromCivitAI(array $civitAIModelData, bool $syncTags) : static;

    abstract static function checkModelFolderForNewFiles() : void;

    abstract static function getModelFileClass() : string;
}
