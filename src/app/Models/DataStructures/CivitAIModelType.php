<?php

namespace App\Models\DataStructures;

use App\Models\Checkpoint;
use App\Models\Embedding;
use App\Models\Lora;

enum CivitAIModelType: string
{
    case CHECKPOINT = Checkpoint::class;
    case EMBEDDING = Embedding::class;
    case LORA = Lora::class;

}
