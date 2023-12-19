<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AIImage extends Model
{
    public $timestamps = false;

    public $table = 'ai_images';

    public $fillable = [
        'model_file_id',
        'model_file_type',
        'filename',
        'positive',
        'negative',
        'sampler',
        'cfg',
        'steps',
        'seed',
        'initial_size',
        'source',
        'model_name'
    ];

    // Relations

    public function modelfile() : MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'model_file_type', 'model_file_id');
    }
}
