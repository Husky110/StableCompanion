<?php

namespace App\Models\DataStructures;

use App\Models\Checkpoint;
use App\Models\Lora;

enum CivitAIModelType: string
{
    case CHECKPOINT = Checkpoint::class;
    case EMBEDDING = 'embedding-class-TBD';
    case LORA = Lora::class;

}
