<?php

namespace App\Models;

use App\Models\DataStructures\ModelFileBaseClass;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class CheckpointFile extends ModelFileBaseClass
{
    public $timestamps = false;

    public string $diskname = 'checkpoints';

    public $fillable = [
        'base_id',
        'version_name',
        'filepath',
        'civitai_version',
        'civitai_description',
        'baseModel',
        'trained_words'
    ];

    // Relations
    public function parentModel() : BelongsTo
    {
        return $this->belongsTo(Checkpoint::class, 'base_id');
    }

    // -> images via parentClass

    // Functions

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
