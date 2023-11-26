<?php

namespace App\Models;

use App\Http\Helpers\Aria2Connector;
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
    public function existingCheckpoint() : BelongsTo
    {
        return $this->belongsTo(Checkpoint::class, 'civit_id', 'civitai_id');
    }

    public static function downloadFileFromCivitAI(CivitAIModelType $modelType, string $modelID, string $modelVersion, bool $syncExamples)
    {
        $specificModelVersion = CivitAIConnector::getSpecificModelVersionByModelIDAndVersionID(
            $modelID,
            $modelVersion
        );

        $download = new CivitDownload([
            'civit_id' => $modelID,
            'version' => $modelVersion,
            'type' => $modelType->value.'_'.(str_contains($specificModelVersion['baseModel'], 'XL') ? 'xl' : 'sd'),
            'url' => $specificModelVersion['downloadUrl'],
            'status' => 'pending',
            'aria_id' => null,
            'load_examples' => $syncExamples
        ]);
        $download->save();
        Aria2Connector::sendDownloadToAria2($download);
    }
}
