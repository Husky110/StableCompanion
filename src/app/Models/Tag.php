<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    public $timestamps = false;

    public $fillable = [
        'tagname',
        'checkpoint_tag',
        'lora_tag',
        'embedding_tag',
    ];

    // Relations

    public function checkpoints() : BelongsToMany
    {
        return $this->belongsToMany(Checkpoint::class, 'checkpoint_tag');
    }
}
