<?php

namespace App\Models\DataStructures;

interface ModelBaseClassInterface
{
    public static function createNewModelFromCivitAI(array $civitAIModelData, bool $syncTags) : ModelBaseClassInterface;

    public function getCivitAIModelType() : CivitAIModelType;
}
