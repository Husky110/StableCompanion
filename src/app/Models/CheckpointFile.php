<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class CheckpointFile extends Model
{
    public $timestamps = false;

    public $fillable = [
        'checkpoint_id',
        'filepath',
        'civitai_version',
        'civitai_description',
        'baseModel'
    ];

    // Relations
    public function checkpoint() : BelongsTo
    {
        return $this->belongsTo(Checkpoint::class);
    }

    public function images() : HasMany
    {
        return $this->hasMany(AIImage::class);
    }

    // Functions

    private function deleteAllAIImages()
    {
        foreach ($this->images as $image){
            Storage::disk('ai_images')->delete($image->filename);
            $image->delete();
        }
    }

    public function deleteCheckpointFile()
    {
        $this->deleteAllAIImages();
        Storage::disk('checkpoints')->delete($this->filepath);
        $this->delete();
    }

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
