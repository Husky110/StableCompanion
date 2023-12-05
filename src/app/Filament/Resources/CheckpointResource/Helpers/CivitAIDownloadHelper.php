<?php

namespace App\Filament\Resources\CheckpointResource\Helpers;

use App\Http\Helpers\CivitAIConnector;
use App\Models\DataStructures\CivitAIModelType;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Components\Wizard;

class CivitAIDownloadHelper
{
    public static function buildURLStep(CivitAIModelType $typeForURL): Wizard\Step
    {
        return Wizard\Step::make('URL')
            ->description('Get the CivitAI-URL')
            ->schema([
                TextInput::make('url')
                    ->label('CivitAI-URL')
                    ->url()
                    ->required()
                    ->rule(fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get, $typeForURL) {
                        $modelID = CivitAIConnector::extractModelIDFromCivitAIURL($value);
                        if($modelID === false){
                            $fail('Non CivitAI-URL or general invalid URL');
                        }
                        $modeltype = CivitAIConnector::getModelTypeByModelID($modelID);
                        if($modeltype == 404){
                            $fail('Either your URL is invalid, or the model has been removed.');
                        }
                        if(str_starts_with($modeltype, 'Error')){
                            $fail($modeltype);
                        }
                        switch ($typeForURL){
                            case CivitAIModelType::CHECKPOINT:
                                if($modeltype != 'Checkpoint'){
                                    $fail('The given URL does not represent a checkpoint');
                                }
                                break;
                            case CivitAIModelType::LORA:
                                if(in_array($modeltype, ['LoCon', 'LORA']) == false){
                                    $fail('The given URL does not represent a LoRA/LyCORIS or LyCON');
                                }
                                break;
                            default:
                                throw new \Exception('Not implemented');
                                break;
                        }
                    })
            ])->afterValidation(function ($get, $set){
                $modelID = CivitAIConnector::extractModelIDFromCivitAIURL($get('url'));
                $set('versions', json_encode(CivitAIConnector::getModelVersionsByModelID($modelID), JSON_UNESCAPED_UNICODE));
                $set('modelID', $modelID);
                $set('model_name', CivitAIConnector::getModelMetaByID($modelID)['name']);
            });
    }

    public static function buildVersionSelectForDownloadWizardStep(CivitAIModelType $modelType) : Wizard\Step
    {
        return Wizard\Step::make('Selection')
            ->description('Details and Download')
            ->schema([
                Hidden::make('modelID'),
                Hidden::make('versions')->live(),
                TextInput::make('model_name')
                    ->label('Modelname')
                    ->live()
                    ->disabled(),
                Select::make('download_versions')
                    ->label('Select Versions')
                    ->options(function ($get){
                        return json_decode($get('versions'), true);
                    })
                    ->multiple()
                    ->required()
                    ->hint('Sorting is newest to oldest.'),
                Toggle::make('sync_tags')
                    ->label('Sync tags from CivitAI')
                    ->hint('Synchronizes the tags from CivitAI with the checkpoint.')
                    ->default(true),
                Toggle::make('sync_examples')
                    ->label('Download example-images')
                    ->default(true)
                    ->hint('The CivitAI-API provides up to 10 images. We sync only images and only those that have complete informations.'),
            ]);
    }
}