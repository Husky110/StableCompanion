<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Config extends Model
{
    public $timestamps = false;

    public $fillable = [
        'key',
        'value',
    ];
}
