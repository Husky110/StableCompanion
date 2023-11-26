<?php

namespace App\Filament\Resources\CheckpointResource\Helpers;

use App\Http\Helpers\CivitAIConnector;
use App\Models\Checkpoint;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class CheckpointFilamentHelper
{
    private static function buildURLStep() : Wizard\Step
    {
        return Wizard\Step::make('URL')
            ->description('Get the CivitAI-URL')
            ->schema([
                TextInput::make('url')
                    ->label('CivitAI-URL')
                    ->url()
                    ->required()
                    ->rule(fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                        $modelID = CivitAIConnector::extractModelIDFromCivitAIURL($value);
                        if($modelID === false){
                            $fail('Non CivitAI-URL or general invalid URL');
                        }
                        if(CivitAIConnector::getModelTypeByModelID($modelID) != 'Checkpoint'){
                            $fail('The given URL does not represent a checkpoint');
                        }
                    })
            ])->afterValidation(function ($get, $set){
                $modelID = CivitAIConnector::extractModelIDFromCivitAIURL($get('url'));
                $set('versions', json_encode(CivitAIConnector::getModelVersionsByModelID($modelID), JSON_UNESCAPED_UNICODE));
                $set('modelID', $modelID);
                $set('checkpoint_name', CivitAIConnector::getModelMetaByID($modelID)['name']);
            });
    }

    private static function buildVersionSelectForDownloadWizardStep() : Wizard\Step
    {
        return Wizard\Step::make('Selection')
            ->description('Details and Download')
            ->schema([
                Hidden::make('modelID'),
                Hidden::make('versions')->live(),
                TextInput::make('checkpoint_name')
                    ->label('Checkpoint')
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

    private static function buildCheckpointFileVersionLinkingStep(Checkpoint $oldCheckpoint)
    {
        return Wizard\Step::make('Versions')
            ->description('Link exisiting files')
            ->schema(function () use ($oldCheckpoint){
                $retval = [
                    Hidden::make('modelID'),
                    Hidden::make('versions')->live(),
                ];
                foreach ($oldCheckpoint->files as $checkpointfile){
                    $retval[] =
                        Section::make(basename($checkpointfile->filepath))
                            ->schema([
                                Select::make('files.'.$checkpointfile->id.'.version')
                                    ->label('Select Version')
                                    ->options(function ($get){
                                        $knownVersions = json_decode($get('versions'), true);
                                        $knownVersions['custom'] = 'Old / Custom version';
                                        return $knownVersions;
                                    })
                                    ->required()
                                    ->hint('Sorting is newest to oldest.'),
                                Toggle::make('files.'.$checkpointfile->id.'.sync_examples')
                                    ->label('Download example-images')
                                    ->default(true)
                                    ->hint('The CivitAI-API provides up to 10 images. We sync only images and only those that have complete informations.'),
                            ]);
                }
                return $retval;
            });
    }

    public static function buildCivitAIDownloadWizard() : Wizard
    {
        return Wizard::make([
            self::buildURLStep(),
            self::buildVersionSelectForDownloadWizardStep()
        ])->submitAction(new HtmlString(Blade::render(<<<BLADE
                        <x-filament::button
                            type="submit"
                            size="sm"
                        >
                            Submit
                        </x-filament::button>
        BLADE)));
    }

    public static function buildCivitAILinkingWizard(Checkpoint $oldCheckpoint): Wizard
    {
        return Wizard::make([
            self::buildURLStep(),
            self::buildCheckpointFileVersionLinkingStep($oldCheckpoint),
            Wizard\Step::make('Closure')
                ->description('Manage Duplicates')
                ->schema([
                    Toggle::make('sync_tags')
                        ->label('Sync Tags')
                        ->hint('Syncs all tags from CivitAI to this checkpoint')
                        ->default(true),
                    Toggle::make('remove_duplicates')
                        ->label('Delete duplicate Checkpoint-Files')
                        ->hint('Weither to keep duplicates or delete them - also applies to your previous selection (except custom versions), so doublecheck! If active, StableCompanion will keep the already existing file and delete this one.')
                ])
        ])->submitAction(new HtmlString(Blade::render(<<<BLADE
                        <x-filament::button
                            type="submit"
                            size="sm"
                        >
                            Submit
                        </x-filament::button>
        BLADE)));
    }
}
