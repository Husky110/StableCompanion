<?php

namespace App\Models\DataStructures;

enum CivitAIModelType: string
{
    case CHECKPOINT = 'checkpoint';
    case EMBEDDING = 'embedding';
    case LORA = 'lora';

}
