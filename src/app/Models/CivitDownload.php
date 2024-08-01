<?php

namespace App\Models;

use App\Http\Helpers\CivitAIConnector;
use App\Models\DataStructures\CivitAIModelType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CivitDownload extends Model
{
    public $timestamps = false;

    public $fillable = [
        'civit_id',
        'version',
        'url',
        'type',
        'status',
        'error_message',
        'aria_id',
        'load_examples'
    ];

    // Relations
    public function existingModel() : BelongsTo
    {
        switch ($this->type){
            case 'checkpoint_sd':
            case 'checkpoint_xl':
                return $this->belongsTo(Checkpoint::class, 'civit_id', 'civitai_id');
                break;
            case 'lora_sd':
            case 'lora_xl':
                return $this->belongsTo(Lora::class, 'civit_id', 'civitai_id');
                break;
            case 'embedding_sd':
            case 'embedding_xl':
                return $this->belongsTo(Embedding::class, 'civit_id', 'civitai_id');
                break;
            default:
                throw new \Exception('Unknown downloadtype!');
        }
    }
}
