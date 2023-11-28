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
        'model_file_id',
        'model_file_class',
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

    public function modelfile() : BelongsTo
    {
        return $this->belongsTo($this->model_file_class, 'model_file_id');
    }
}
