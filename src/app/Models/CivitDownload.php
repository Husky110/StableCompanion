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
            default:
                throw new \Exception('Unknown downloadtype!');
        }
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
            'type' => strtolower($modelType->name).'_'.(str_contains($specificModelVersion['baseModel'], 'XL') ? 'xl' : 'sd'),
            'url' => $specificModelVersion['downloadUrl'],
            'status' => 'pending',
            'aria_id' => null,
            'load_examples' => $syncExamples
        ]);
        $download->save();
        Aria2Connector::sendDownloadToAria2($download);
        if($modelType == CivitAIModelType::EMBEDDING){ // sometimes we have more than one file and the other one is a negative - or even a positive...
            $negativeFound = false;
            if(count($specificModelVersion['files']) > 1 ){
                for($x = 1; $x <= count($specificModelVersion['files']) - 1; $x++){
                    if(
                        $specificModelVersion['files'][$x]['type'] != $specificModelVersion['files'][0]['type'] &&
                        in_array($specificModelVersion['files'][$x]['type'], ['Model', 'Negative'])
                    ){
                        $download = new CivitDownload([
                            'civit_id' => $modelID,
                            'version' => $modelVersion,
                            'type' => strtolower($modelType->name).'_'.(str_contains($specificModelVersion['baseModel'], 'XL') ? 'xl' : 'sd'),
                            'url' => $specificModelVersion['files'][$x]['downloadUrl'],
                            'status' => 'pending',
                            'aria_id' => null,
                            'load_examples' => $syncExamples
                        ]);
                        $download->save();
                    }
                }
            }
        }
    }
}
