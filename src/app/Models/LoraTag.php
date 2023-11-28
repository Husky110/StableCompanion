<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * This models solely purpose is to use it in a repeater...
 * See https://filamentphp.com/docs/3.x/forms/fields/repeater#integrating-with-a-belongstomany-eloquent-relationship
 */
class LoraTag extends Model
{
    public $timestamps = false;

    public $table = 'lora_tag';

    public $fillable = [
        'lora_id',
        'tag_id'
    ];

    // Relations

    public function loras() : BelongsTo
    {
        return $this->belongsTo(Lora::class, 'lora_id');
    }

    public function tag() : BelongsTo
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }
}
