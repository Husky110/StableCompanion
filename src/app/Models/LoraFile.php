<?php

namespace App\Models;

use App\Http\Helpers\CivitAIConnector;
use App\Http\Helpers\WebUIConnector;
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

    // Functions

    public static function determineLoraTypeByFilename(string $filename) : string
    {
        $loras = WebUIConnector::getInstance()->getLoras();
        $foundLora = [];
        foreach ($loras as $lora){
            if(str_ends_with($lora['path'], $filename)){
                $foundLora = $lora;
                break;
            }
        }
        if(count($foundLora) == 0){
            return 'unknown';
        }

        /**
         * Okay - here it is a bit tricky... Actually we need A1111 to help us out a bit, since there is no know way we can determine
         * the type of a LoRA just with the file in PHP.
         * The good news: There are some metadata-parameters that can help us figure it out!
         * The bad news: Not all LoRAs have them, or when they have them - they are incomplete and what-not.
         * So we see if at least some of them are set to get the maximum we can, but there is no guarantee that this will work.
         * For docs:
         * There were other parameters than the one I use here, that seemed feasible, but were not after thinking about them a bit...
         * - via "ss_sd_model_name" -> will trigger "xl" on "My_awesome_XL_MODELMERGE" even tho it is SD...
         * - via the filename itself -> will trigger on "xl" in any way - so an XL-BOOBS-Lora will trigger it...
         */

        // first way is via ss_base_model_version
        if(isset($foundLora['metadata']['ss_base_model_version'])){
            if(str_contains(strtolower($foundLora['metadata']['ss_base_model_version']), 'xl')){
                return 'xl';
            } else {
                return 'sd';
            }
        }

        // second way is via modelspec.architecture
        if(isset($foundLora['metadata']['modelspec.architecture'])){
            if(str_contains(strtolower($foundLora['metadata']['modelspec.architecture']), 'xl')){
                return 'xl';
            } else {
                return 'sd';
            }
        }

        // third way is via the resolution -> let's hope we don't get XXL-Models soon...
        if(isset($foundLora['metadata']['ss_resolution'])){
            if(str_contains($foundLora['metadata']['ss_resolution'], '1024')){
                return 'xl';
            } else if(str_contains($foundLora['metadata']['ss_resolution'], '512')){
                return 'sd';
            }
        }
        if(isset($foundLora['metadata']['modelspec.resolution'])){
            if(str_contains($foundLora['metadata']['modelspec.resolution'], '1024')){
                return 'xl';
            } else {
                return 'sd';
            }
        }

        return 'unknown';
    }
}
