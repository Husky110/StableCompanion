<?php

namespace App\Filament\Resources\LoraResource\Pages;

use App\Filament\Resources\CheckpointResource\Helpers\GeneralFrontendHelper;
use App\Filament\Resources\LoraResource;
use App\Http\Helpers\CivitAIConnector;
use App\Models\CivitDownload;
use App\Models\DataStructures\CivitAIModelType;
use App\Models\Lora;
use App\Models\LoraFile;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ViewLora extends ViewRecord
{
    protected static string $resource = LoraResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'SC - '.$this->record->model_name;
    }

    public function getHeading(): string|Htmlable
    {
        return 'LoRA/LyCON/LyCORIS: '.$this->record->model_name;
    }

    public function getSubheading(): string|Htmlable|null
    {
        if($this->record->files->count() == 0){
            return 'This model is still beeing downloaded! Please come back once it\'s finished.';
        } else {
            return null;
        }
    }

    public function getBreadcrumb(): string
    {
        return $this->record->model_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('link_to_civitai')
                ->label('Link this LoRA to CivitAI-Model')
                ->modalDescription('Beware: You do this on your own accountability! If you link this to a wrong model, that\'s on you! I can\'t really check that what you do here is correct. If you add an URL of an already existing model, it will be linked to that. (Sorry - can\'t really put a LoRA-Selector here...)')
                ->button()
                ->visible(!(bool)$this->record->civitai_id)
                ->form([GeneralFrontendHelper::buildCivitAILinkingWizard($this->record)])
                ->action(function ($data){
                    $redirect = GeneralFrontendHelper::runLinkingAction($data, $this->record);
                    if($redirect > 0){
                        $this->redirect('/loras/'.$redirect);
                    }
                })
                ->modalSubmitAction(false)
                ->modalCancelAction(false),
            Actions\Action::make('download_additional_versions')
                ->label('Download additional versions')
                ->button()
                ->modalDescription('With this you can add other/older versions of this model to your collection.')
                ->form(function ($record){
                    return [
                        Select::make('versions')
                            ->label('Pick your versions')
                            ->multiple()
                            ->options($record->checkIfOtherVersionsExistOnCivitAi()),
                        Toggle::make('sync_images')
                            ->label('Sync example-images'),
                    ];
                })
                ->action(function ($data){
                    foreach ($data['versions'] as $version){
                        CivitDownload::downloadFileFromCivitAI(
                            CivitAIModelType::LORA,
                            $this->record->civitai_id,
                            $version,
                            $data['sync_images']
                        );
                    }
                })
                ->visible(fn($record) => count($record->checkIfOtherVersionsExistOnCivitAi()) > 0)
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
                                                ->image(),
                                            Toggle::make('overwrite_existing')
                                                ->label('Overwrite exisiting preview-image')
                                                ->default(true)
                                                ->visible($this->record->image_name != 'placeholder.png')
                                        ])
                                        ->action(function ($data){
                                            $oldPreviewWasPlaceholder = false;
                                            if($data['overwrite_existing']){
                                                $oldPreviewWasPlaceholder = true;
                                            }
                                            $tempDisk = Storage::disk('upload_temp');
                                            $filename = $data['image_replacement'];
                                            Storage::disk('modelimages')->put(
                                                $filename,
                                                $tempDisk->get($filename)
                                            );
                                            if($this->record->image_name == 'placeholder.png'){
                                                $oldPreviewWasPlaceholder = true;
                                            }
                                            $this->record->image_name = $filename;
                                            $this->record->save();
                                            // we clear the whole thing, cause we have no idea how many images the user tried here...
                                            $tempDisk->delete($tempDisk->allFiles());
                                            foreach ($this->record->files as $file){
                                                $file->changePreviewImage(!$oldPreviewWasPlaceholder, 0, true);
                                            }
                                        })
                                ),
                            Section::make()
                                ->schema([
                                    TextEntry::make('model_name')
                                        ->label('Loraname:')
                                        ->inlineLabel(),
                                    TextEntry::make('link')
                                        ->label('CivitAI-Link: ')
                                        ->inlineLabel()
                                        ->getStateUsing(fn($record) => new HtmlString('<a href="'.CivitAIConnector::buildCivitAILinkByModelAndVersionID($record->civitai_id).'" target="_blank">'.CivitAIConnector::buildCivitAILinkByModelAndVersionID($record->civitai_id).'</a>'))
                                        ->visible(fn($record) => $record->civitai_id != null),
                                    \Filament\Infolists\Components\Actions::make([
                                        Action::make('change_loraname')
                                            ->label('Change Loraname')
                                            ->button()
                                            ->form([
                                                TextInput::make('model_name')
                                                    ->label(false)
                                                    ->default($this->record->model_name)
                                            ])
                                            ->action(function ($data){
                                                $this->record->model_name = $data['model_name'];
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
                                                        Repeater::make('loraTags')
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
                                                ->getStateUsing(fn() => $this->record->user_notes ? GeneralFrontendHelper::wrapHTMLStringToImplementBreaks($this->record->user_notes) : 'You noted nothing so far...')
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
                                            TextEntry::make('civitai_notes')
                                                ->getStateUsing(fn() => GeneralFrontendHelper::wrapHTMLStringToImplementBreaks($this->record->civitai_notes))
                                                ->extraAttributes(['style' => 'max-height: 200px; overflow-y: scroll;'])
                                                ->label(false)
                                        ])
                                        ->visible(fn() => $this->record->civitai_notes != null)
                                ])
                                ->columnSpan(2)
                        ]),

                ];
                foreach ($this->record->files->sortBy([
                    ['civitai_version', 'desc'],
                    ['id', 'desc']
                ]) as $loraFile){
                    $civitImages = [];
                    foreach ($loraFile->images->where('source', 'CivitAI') as $aiImage){
                        $civitImages[] = ImageEntry::make('aiImage_'.$aiImage->id)
                            ->label(false)
                            ->disk('ai_images')
                            ->getStateUsing($aiImage->filename)
                            ->columnSpan(function () use ($aiImage){
                                $dimensions = explode('x',$aiImage->initial_size);
                                if($dimensions[0] > $dimensions[1]){
                                    return 2;
                                } else {
                                    return 1;
                                }
                            })
                            ->action(
                                GeneralFrontendHelper::buildExampleImageViewAction($aiImage),
                            );
                    }
                    $retval[] = Section::make(basename($loraFile->filepath))
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
                                                ->getStateUsing($loraFile->filepath),
                                            TextEntry::make('version_name')
                                                ->label('Version:')
                                                ->inlineLabel()
                                                ->getStateUsing($loraFile->version_name)
                                                ->visible((bool)$loraFile->version_name),
                                            TextEntry::make('civitai_version')
                                                ->label('CivitAI-Version-ID/-Link')
                                                ->inlineLabel()
                                                ->getStateUsing($loraFile->civitai_version ? new HtmlString('<a href="'.CivitAIConnector::buildCivitAILinkByModelAndVersionID($this->record->civitai_id, $loraFile->civitai_version).'" target="_blank">'.$loraFile->civitai_version.'</a>') : '')
                                                ->visible((bool)$loraFile->civitai_version),
                                            TextEntry::make('baseModelType')
                                                ->inlineLabel()
                                                ->label('Base Model:')
                                                ->getStateUsing($loraFile->baseModelType),
                                            TextEntry::make('trained_words')
                                                ->label('Trained words:')
                                                ->inlineLabel()
                                                ->getStateUsing($loraFile->trained_words ? implode(', ', json_decode($loraFile->trained_words, true)) : '')
                                                ->visible((bool)$loraFile->trained_words),
                                            \Filament\Infolists\Components\Actions::make([
                                                Action::make('rename_lorafile_'.$loraFile->id)
                                                    ->label('Change Filename')
                                                    ->button()
                                                    ->form([
                                                        Hidden::make('lorafile_id'),
                                                        TextInput::make('file_name')
                                                            ->label(false)
                                                            ->hint('Please make sure you keep the correct the fileextention!')
                                                    ])
                                                    ->fillForm(function () use ($loraFile){
                                                        $retval = [
                                                            'lorafile_id' => $loraFile->id,
                                                            'file_name' => basename($loraFile->filepath)
                                                        ];
                                                        return $retval;
                                                    })
                                                    ->action(function($data){
                                                        $loraFile = LoraFile::findOrFail($data['lorafile_id']);
                                                        $disk = Storage::disk('loras');
                                                        $originalpath = $disk->path($loraFile->filepath);
                                                        $modifiedPath = $disk->path('').$data['file_name'];
                                                        rename($originalpath, $modifiedPath);
                                                        $loraFile->filepath = str_replace(Storage::disk('loras')->path(''), '', $modifiedPath);
                                                        $loraFile->save();
                                                    }),
                                                Action::make('delete_lorafile')
                                                    ->label('Delete Lorafile')
                                                    ->button()
                                                    ->color('danger')
                                                    ->requiresConfirmation()
                                                    ->modalDescription('If this is the last file in that lora, the lora will also be deleted. Are you sure you would like to do this?')
                                                    ->form([Hidden::make('lora_file_id')])
                                                    ->fillForm(['lora_file_id' => $loraFile->id])
                                                    ->action(function ($data){
                                                        $loraFile = LoraFile::with(['images'])->findOrFail($data['lora_file_id']);
                                                        $loraID = $loraFile->base_id;
                                                        $loraFile->deleteModelFile();
                                                        $lora = Lora::with(['files'])->findOrFail($loraID);
                                                        if($lora->files->count() == 0){
                                                            $lora->deleteModel();
                                                            $this->redirect(route('filament.admin.resources.loras.index'));
                                                        }

                                                    })
                                            ])->fullWidth(),
                                        ]),
                                    Section::make('CivitAI-Description')
                                        ->schema([
                                            TextEntry::make('civitai_notes')
                                                ->getStateUsing(fn() => $loraFile->civitai_description ? GeneralFrontendHelper::wrapHTMLStringToImplementBreaks($loraFile->civitai_description) : 'No additional informations given.')
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
