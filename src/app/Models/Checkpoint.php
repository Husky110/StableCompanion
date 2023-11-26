<?php

namespace App\Models;

use App\Http\Helpers\CivitAIConnector;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class Checkpoint extends Model
{
    public $timestamps = false;

    public $fillable = [
        'image_name',
        'checkpoint_name',
        'civitai_id',
        'civit_notes',
        'user_notes',
    ];

    // Relations
    public function tags() : BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'checkpoint_tag');
    }

    public function checkpointTags() : HasMany
    {
        return  $this->hasMany(CheckpointTag::class, 'checkpoint_id');
    }

    public function files() : HasMany
    {
        return $this->hasMany(CheckpointFile::class);
    }

    public function activedownloads() : HasMany
    {
        return $this->hasMany(CivitDownload::class, 'civit_id', 'civitai_id');
    }

    // Functions
    public static function scanCheckpointFolderForNewFiles()
    {
        $disk = Storage::disk('checkpoints');
        foreach ($disk->allFiles() as $file){
            if(
                !CheckpointFile::checkWeitherFilesIsPossiblyACheckpointFile($file) ||
                str_contains($file, '/no_scan/')
            ){
                continue;
            }
            $existingCheckpointFile = CheckpointFile::where('filepath', $file)->first();
            if($existingCheckpointFile == null){
                $newCheckpoint = new Checkpoint([
                    'checkpoint_name' => basename($file),
                ]);
                $newCheckpoint->save();
                $newCheckpointFile = new CheckpointFile([
                    'checkpoint_id' => $newCheckpoint->id,
                    'filepath' => $file,
                    'baseModel' => $disk->size($file) < 6442450944 ? 'Some SD-Model' : 'Some XL-Model',
                ]);
                $newCheckpointFile->save();
            }
        }
    }

    public function deleteCheckpoint()
    {
        if($this->image_name != 'placeholder.png'){
            Storage::disk('modelimages')->delete($this->image_name);
        }
        $this->tags()->sync([]);
        $this->delete();
    }

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
                Select::make('version')
                    ->label('Select Version')
                    ->options(function ($get){
                        return json_decode($get('versions'), true);
                    })
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
                    Toggle::make('remove_duplicates')
                        ->label('Delete duplicates')
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

    public static function createNewCheckpointFromCivitAI(array $civitAIModelData, bool $syncTags) : Checkpoint
    {
        $modelImageDisk = Storage::disk('modelimages');
        $imageURL = '';
        $imagename = '';
        foreach ($civitAIModelData['modelVersions'][0]['images'] as $image){
            if($image['type'] == 'image'){
                $imageURL = $image['url'];
                break;
            }
        }
        if($imageURL){
            $imagename = basename($imageURL);
            $modelImageDisk->put($imagename, file_get_contents($imageURL));
        }
        $checkpoint = new Checkpoint([
            'checkpoint_name' => $civitAIModelData['name'],
            'civitai_id' => $civitAIModelData['id'],
        ]);
        if($imageURL){
            $checkpoint->image_name = $imagename;
        }
        $checkpoint->civit_notes = $civitAIModelData['description'];
        $checkpoint->save();
        if($syncTags){
            $syncArray = [];
            foreach ($civitAIModelData['tags'] as $civitTag){
                $tag = Tag::where('tagname', $civitTag)->first();
                if(!$tag){
                    $tag = new Tag([
                        'tagname' => $civitTag
                    ]);
                    $tag->save();
                }
                $syncArray[] = $tag->id;
            }
            $checkpoint->tags()->syncWithoutDetaching($syncArray);
        }
        return $checkpoint;
    }
}
