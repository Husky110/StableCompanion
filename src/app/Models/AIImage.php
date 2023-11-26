<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIImage extends Model
{
    public $timestamps = false;

    public $table = 'ai_images';

    public $fillable = [
        'checkpoint_file_id',
        'filename',
        'positive',
        'negative',
        'sampler',
        'cfg',
        'steps',
        'seed',
        'initial_size',
        'source',
    ];

    // Relations

    public function checkpointfile() : BelongsTo
    {
        return $this->belongsTo(CheckpointFile::class);
    }
}
