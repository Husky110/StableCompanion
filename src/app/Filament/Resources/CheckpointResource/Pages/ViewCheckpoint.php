<?php

namespace App\Filament\Resources\CheckpointResource\Pages;

use App\Filament\Resources\CheckpointResource;
use App\Filament\Resources\CheckpointResource\Helpers\CheckpointFilamentHelper;
use App\Http\Helpers\CivitAIConnector;
use App\Models\Checkpoint;
use App\Models\CheckpointFile;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ViewCheckpoint extends ViewRecord
{
    protected static string $resource = CheckpointResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'SC - '.$this->record->checkpoint_name;
    }

    public function getHeading(): string|Htmlable
    {
        return 'Checkpoint: '.$this->record->checkpoint_name;
    }

    public function getSubheading(): string|Htmlable|null
    {
        if($this->record->files->count() == 0){
            return 'This checkpoint is still beeing downloaded! Please come back once it\'s finished.';
        } else {
            return null;
        }
    }

    public function getBreadcrumb(): string
    {
        return $this->record->checkpoint_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('link_to_civitai')
                ->label('Link this Checkpoint to CivitAI-Model')
                ->modalDescription('Beware: You do this on your own accountability! If you link this to a wrong model, that\'s on you! I can\'t really check that what you do here is correct. If you add an URL of an already existing model, it will be linked to that. (Sorry - can\'t really put a Checkpoint-Selector here...)')
                ->button()
                ->visible(!(bool)$this->record->civitai_id)
                ->form([CheckpointFilamentHelper::buildCivitAILinkingWizard($this->record)])
                ->action(function ($data){
                    $exisitingCheckpoint = Checkpoint::where('civitai_id', $data['modelID'])->first();
                    if($exisitingCheckpoint){
                        foreach ($exisitingCheckpoint->files as $existingFile){
                            $existingFile->checkpoint_id = $exisitingCheckpoint;
                            $existingFile->civitai_version = $data['files'][$existingFile->id]['version'];
                            if($data['remove_duplicates']){
                                foreach ($data['files'] as $linkingCheckpointFileID => $linkingData){
                                    if($linkingData['version'] == 'custom'){
                                        continue;
                                    }
                                    if($linkingData['version'] == $existingFile->version){
                                        $existingFile->deleteCheckpointFile();
                                        unset($data['files'][$linkingCheckpointFileID]);
                                        break;
                                    }
                                }
                            } else {
                                $existingFile->save();
                            }
                        }
                    } else {
                        $modelData = CivitAIConnector::getModelMetaByID($data['modelID']);
                        $this->record->checkpoint_name = $modelData['name'];
                        $this->record->civitai_id = $modelData['id'];
                        $this->record->setModelImage($modelData);
                        $this->record->civit_notes = $modelData['description'];
                        $this->record->save();
                        if($data['sync_tags']){
                            $this->record->syncCivitAITags($modelData);
                        }
                        foreach ($this->record->files as $checkpointFile){
                            $checkpointFile->civitai_version = $data['files'][$checkpointFile->id]['version'];
                            $checkpointFile->civitai_description = CivitAIConnector::getSpecificModelVersionByModelIDAndVersionID($data['modelID'], $checkpointFile->civitai_version)['description'];
                            $checkpointFile->save();
                        }
                    }
                    // okay, now we check if we have to load image-files from CivitAI...
                    foreach ($data['files'] as $dataCheckpointFileID => $dataFile){
                        if($dataFile['sync_examples']){
                            //Reload the CheckpointFile - just in case...
                            $checkpointFile = CheckpointFile::findOrFail($dataCheckpointFileID);
                            $checkpointFile->loadImagesFromCivitAIForThisFile();
                        }
                    }

                })
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema(function (){
                $retval = [
                    Section::make('Metadata')
                        ->columns(3)
                        ->schema([
                            ImageEntry::make('image_name')
                                ->disk('modelimages')
                                ->height(400)
                                ->label(false)
                                ->columnSpan(1)
                                ->action(
                                    Action::make('change_backgroundimage')
                                        ->form([
                                            FileUpload::make('image_replacement')
                                                ->disk('upload_temp')
                                                ->image()
                                        ])
                                        ->action(function ($data){
                                            $tempDisk = Storage::disk('upload_temp');
                                            $filename = $data['image_replacement'];
                                            Storage::disk('modelimages')->put(
                                                $filename,
                                                $tempDisk->get($filename)
                                            );
                                            $this->record->image_name = $filename;
                                            $this->record->save();
                                            // we clear the whole thing, cause we have no idea how many images the user tried here...
                                            $tempDisk->delete($tempDisk->allFiles());
                                        })
                                ),
                            Section::make()
                                ->schema([
                                    TextEntry::make('checkpoint_name')
                                        ->label('Checkpointname:')
                                        ->inlineLabel(),
                                    TextEntry::make('link')
                                        ->label('CivitAI-Link: ')
                                        ->inlineLabel()
                                        ->getStateUsing(fn($record) => new HtmlString('<a href="'.CivitAIConnector::buildCivitAILinkByModelAndVersionID($record->civitai_id).'" target="_blank">'.CivitAIConnector::buildCivitAILinkByModelAndVersionID($record->civitai_id).'</a>'))
                                        ->visible(fn($record) => $record->civitai_id != null),
                                    \Filament\Infolists\Components\Actions::make([
                                        Action::make('change_checkpointname')
                                            ->label('Change Checkpointname')
                                            ->button()
                                            ->form([
                                                TextInput::make('checkpoint_name')
                                                    ->label(false)
                                                    ->default($this->record->checkpoint_name)
                                            ])
                                            ->action(function ($data){
                                                $this->record->checkpoint_name = $data['checkpoint_name'];
                                                $this->record->save();
                                            }),
                                    ])
                                    ->fullWidth(),
                                    Section::make('Tags')
                                        ->extraAttributes(['style' => 'max-height: 200px; overflow-y: scroll;'])
                                        ->schema(function (){
                                            $schema = [
                                                TextEntry::make('tags.tagname')
                                                    ->label(false)
                                            ];
                                            $schema[] = \Filament\Infolists\Components\Actions::make([
                                                Action::make('manage_tags')
                                                    ->label('Manage Tags')
                                                    ->button()
                                                    ->modalHeading('Manage Tags')
                                                    ->fillForm([$this->record])
                                                    ->form([
                                                        Repeater::make('checkpointTags')
                                                            ->label(false)
                                                            ->relationship()
                                                            ->schema([
                                                                Select::make('tag_id')
                                                                    ->relationship('tag', 'tagname')
                                                            ])
                                                            ->addActionLabel('Add tag')
                                                            ->grid(3)
                                                    ]),
                                            ])->columnSpan(3)->fullWidth();
                                            return $schema;
                                        }),
                                    Section::make('Your Notes')
                                        ->schema([
                                            TextEntry::make('user_notes')
                                                ->getStateUsing(fn() => new HtmlString($this->record->user_notes ?? 'You noted nothing so far...'))
                                                ->label(false),
                                            \Filament\Infolists\Components\Actions::make([
                                                Action::make('change_usernotes')
                                                    ->form([
                                                        RichEditor::make('notes')
                                                            ->label(false)
                                                            ->default($this->record->user_notes)
                                                    ])
                                                    ->action(function($data){
                                                        $this->record->user_notes = $data['notes'];
                                                        $this->record->save();
                                                    }),
                                                Action::make('clear_usernotes')
                                                    ->requiresConfirmation()
                                                    ->label('Clear usernotes')
                                                    ->button()
                                                    ->color('danger')
                                                    ->action(function (){
                                                        $this->record->user_notes = null;
                                                        $this->record->save();
                                                    })
                                            ])->fullWidth()
                                        ]),
                                    Section::make('CivitAI-Description')
                                        ->schema([
                                            TextEntry::make('civit_notes')
                                                ->getStateUsing(fn() => new HtmlString($this->record->civit_notes))
                                                ->extraAttributes(['style' => 'max-height: 200px; overflow-y: scroll;'])
                                                ->label(false)
                                        ])
                                        ->visible(fn() => $this->record->civit_notes != null)
                                ])
                                ->columnSpan(2)
                        ]),

                ];
                foreach ($this->record->files->sortBy([
                    ['civitai_version', 'desc'],
                    ['id', 'desc']
                ]) as $checkpointFile){
                    $civitImages = [];
                    foreach ($checkpointFile->images->where('source', 'CivitAI') as $aiImage){
                        $civitImages[] = ImageEntry::make('aiImage_'.$aiImage->id)
                            ->label(false)
                            ->disk('ai_images')
                            ->getStateUsing($aiImage->filename)
                            ->columnSpan(1)
                            ->action(
                                Action::make('view')
                                    ->modalHeading($aiImage->filename)
                                    ->form([
                                        Grid::make(3)
                                            ->schema([
                                                ViewField::make('image')
                                                    ->columnSpan(1)
                                                    ->view('filament.showImage')
                                                    ->viewData(['src' => '/ai_images/'.$aiImage->filename]),
                                                \Filament\Forms\Components\Section::make('Metadata')
                                                    ->columnSpan(2)
                                                    ->columns()
                                                    ->schema([
                                                        Textarea::make($aiImage->id.'_positive')
                                                            ->default($aiImage->positive)
                                                            ->autosize()
                                                            ->label('Positive Prompt')
                                                            ->disabled(),
                                                        Textarea::make($aiImage->id.'_negative')
                                                            ->default($aiImage->negative)
                                                            ->autosize()
                                                            ->label('Negative Prompt')
                                                            ->disabled(),
                                                        TextInput::make($aiImage->id.'_sampler')
                                                            ->default($aiImage->sampler)
                                                            ->label('Sampler')
                                                            ->disabled(),
                                                        TextInput::make($aiImage->id.'_cfg')
                                                            ->default($aiImage->cfg)
                                                            ->label('CFG-Scale')
                                                            ->disabled(),
                                                        TextInput::make($aiImage->id.'_steps')
                                                            ->default($aiImage->steps)
                                                            ->label('Steps')
                                                            ->disabled(),
                                                        TextInput::make($aiImage->id.'_seed')
                                                            ->default($aiImage->seed)
                                                            ->label('Seed')
                                                            ->disabled(),
                                                        TextInput::make($aiImage->id.'_initial_size')
                                                            ->default($aiImage->initial_size)
                                                            ->label('Initial Size')
                                                            ->disabled(),
                                                    ])
                                            ])
                                    ])
                                    ->modalSubmitAction(false)
                                    ->modalCancelAction(false)
                            );
                    }
                    $retval[] = Section::make(basename($checkpointFile->filepath))
                        ->schema([
                            Section::make('CivitAI-Images')
                                ->schema($civitImages)
                                ->columns(10)
                                ->visible(fn() => count($civitImages) > 0),
                            Section::make('Metadata')
                                ->schema([
                                    Section::make()
                                        ->schema([
                                            TextEntry::make('filepath')
                                                ->inlineLabel()
                                                ->label('FilePath:')
                                                ->getStateUsing($checkpointFile->filepath),
                                            TextEntry::make('base_model')
                                                ->inlineLabel()
                                                ->label('Base Model:')
                                                ->getStateUsing($checkpointFile->baseModel),
                                            TextEntry::make('civitai_version')
                                                ->label('CivitAI-Version-ID/-Link')
                                                ->inlineLabel()
                                                ->getStateUsing($checkpointFile->civitai_version ? new HtmlString('<a href="'.CivitAIConnector::buildCivitAILinkByModelAndVersionID($this->record->civitai_id, $checkpointFile->civitai_version).'" target="_blank">'.$checkpointFile->civitai_version.'</a>') : '')
                                                ->visible((bool)$checkpointFile->civitai_version),
                                            \Filament\Infolists\Components\Actions::make([
                                                Action::make('rename_checkpointfile')
                                                    ->label('Change Filename')
                                                    ->button()
                                                    ->form([
                                                        Hidden::make('checkpointfile_id'),
                                                        TextInput::make('file_name')
                                                            ->label(false)
                                                            ->hint('Please make sure you keep the correct the fileextention!')
                                                    ])
                                                    ->fillForm(function () use ($checkpointFile){
                                                        $retval = [
                                                            'checkpointfile_id' => $checkpointFile->id,
                                                            'file_name' => basename($checkpointFile->filepath)
                                                        ];
                                                        return $retval;
                                                    })
                                                    ->action(function($data){
                                                        $checkpointFile = CheckpointFile::findOrFail($data['checkpointfile_id']);
                                                        $originalpath = Storage::disk('checkpoints')->path($checkpointFile->filepath);
                                                        $modifiedPath = explode('/', $originalpath);
                                                        $modifiedPath[count($modifiedPath) - 1] = $data['file_name'];
                                                        $modifiedPath = implode('/', $modifiedPath);
                                                        rename($originalpath, $modifiedPath);
                                                        $checkpointFile->filepath = str_replace(Storage::disk('checkpoints')->path(''), '', $modifiedPath);
                                                        $checkpointFile->save();
                                                    }),
                                                Action::make('delete_checkpointfile')
                                                    ->label('Delete checkpointfile')
                                                    ->button()
                                                    ->color('danger')
                                                    ->requiresConfirmation()
                                                    ->modalDescription('If this is the last file in that checkpoint, the checkpoint will also be deleted. Are you sure you would like to do this?')
                                                    ->form([Hidden::make('checkpoint_file_id')])
                                                    ->fillForm(['checkpoint_file_id' => $checkpointFile->id])
                                                    ->action(function ($data){
                                                        $checkpointFile = CheckpointFile::with(['images'])->findOrFail($data['checkpoint_file_id']);
                                                        $checkpointID = $checkpointFile->checkpoint_id;
                                                        $checkpointFile->deleteCheckpointFile();
                                                        $checkpoint = Checkpoint::with(['files'])->findOrFail($checkpointID);
                                                        if($checkpoint->files->count() == 0){
                                                            $checkpoint->deleteCheckpoint();
                                                        }

                                                    })
                                                    ->after(fn() => $this->redirect(route('filament.admin.resources.checkpoints.index'))),
                                            ])->fullWidth(),
                                        ]),
                                    Section::make('CivitAI-Description')
                                        ->schema([
                                            TextEntry::make('civit_notes')
                                                ->getStateUsing(fn() => $checkpointFile->civitai_description ? new HtmlString($checkpointFile->civitai_description) : 'No additional informations given.')
                                                ->extraAttributes(['style' => 'max-height: 200px; overflow-y: scroll;'])
                                                ->label(false)
                                        ])->visible((bool)$this->record->civitai_id),
                                ])
                        ]);
                }
                return $retval;
            });
    }
}
