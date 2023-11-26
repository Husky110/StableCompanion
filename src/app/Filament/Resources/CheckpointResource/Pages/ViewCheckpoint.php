<?php

namespace App\Filament\Resources\CheckpointResource\Pages;

use App\Filament\Resources\CheckpointResource;
use App\Models\Checkpoint;
use App\Models\CheckpointFile;
use App\Models\Tag;
use Filament\Actions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
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
use Filament\Support\Components\ViewComponent;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Njxqlus\Filament\Components\Infolists\LightboxImageEntry;

class ViewCheckpoint extends ViewRecord
{
    protected static string $resource = CheckpointResource::class;

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
                ->modalDescription('Beware: You do this on your own accountability! If you link this to a wrong model, that\'s on you! I can\'t really check that what you do here is correct.')
                ->button()
                ->visible(!(bool)$this->record->civitai_id)
                ->form([Checkpoint::buildCivitAILinkingWizard($this->record)])
                ->action(function ($data){
                    dd($data);
                    $exisitingCheckpoint = Checkpoint::where('civitai_id', $data['modelID'])->first();
                    if($exisitingCheckpoint){
                        if($data['remove_duplicates']){
                            // now we must check for duplicates in the checkpointfiles
                            $foundDuplicates = [];
                            foreach ($exisitingCheckpoint->files as $existingCheckpointFile){
                                foreach ($data['files'] as $linkingCheckpointFileID => $linkingData){
                                    if($linkingData['version'] == 'custom'){
                                        continue;
                                    }
                                    if($linkingData['version'] == $existingCheckpointFile->version){
                                        $foundDuplicates[] = $linkingCheckpointFileID;
                                    }
                                }
                            }
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
                                ->columnSpan(1),
                            Section::make(null)
                                ->schema([
                                    TextEntry::make('checkpoint_name')
                                        ->label('Checkpointname:')
                                        ->inlineLabel(),
                                    TextEntry::make('link')
                                        ->label('CivitAI-Link: ')
                                        ->inlineLabel()
                                        ->getStateUsing(fn($record) => new HtmlString('<a href="https://civitai.com/models/'.$record->civitai_id.'" target="_blank">https://civitai.com/models/'.$record->civitai_id.'</a>'))
                                        ->visible(fn($record) => $record->civitai_id != null),
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
                                                ->getStateUsing($checkpointFile->baseModel)
                                        ]),
                                    Section::make('CivitAI-Description')
                                        ->schema([
                                            TextEntry::make('civit_notes')
                                                ->getStateUsing(fn() => $checkpointFile->civitai_description ? new HtmlString($checkpointFile->civitai_description) : 'No additional informations given.')
                                                ->extraAttributes(['style' => 'max-height: 200px; overflow-y: scroll;'])
                                                ->label(false)
                                        ])->visible((bool)$this->record->civitai_id),
                                    Section::make('Management')
                                        ->schema([
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
                                            ])->fullWidth()
                                        ])
                                ]),
                        ]);
                }
                return $retval;
            });
    }
}
