<?php

namespace App\Models;

use App\Http\Helpers\CivitAIConnector;
use App\Models\DataStructures\ModelFileBaseClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class LoraFile extends ModelFileBaseClass
{
    public $timestamps = false;

    public string $diskname = 'loras';

    public $fillable = [
        'base_id',
        'version_name',
        'filepath',
        'civitai_version',
        'civitai_description',
        'baseModelType',
        'trained_words'
    ];

    // Relations
    public function parentModel() : BelongsTo
    {
        return $this->belongsTo(Lora::class, 'base_id');
    }

    // -> images via parentClass
}
