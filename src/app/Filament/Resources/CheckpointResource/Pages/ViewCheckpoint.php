<?php

namespace App\Filament\Resources\CheckpointResource\Pages;

use App\Filament\Helpers\GeneralFrontendHelper;
use App\Filament\Helpers\ViewModelHelper;
use App\Filament\Resources\CheckpointResource;
use App\Http\Helpers\CivitAIConnector;
use App\Models\Checkpoint;
use App\Models\CheckpointFile;
use App\Models\CivitDownload;
use App\Models\DataStructures\CivitAIModelType;
use Filament\Actions;
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

class ViewCheckpoint extends ViewRecord
{
    protected static string $resource = CheckpointResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'SC - '.$this->record->model_name;
    }

    public function getHeading(): string|Htmlable
    {
        return 'Checkpoint: '.$this->record->model_name;
    }

    public function getSubheading(): string|Htmlable|null
    {
        if($this->record->files->count() == 0){
            return 'This checkpoint has no files or they have been deleted.';
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
            ViewModelHelper::buildCivitAILinkingAction($this->record),
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
                            GeneralFrontendHelper::buildImageEntryForDetailedViews($this->record),
                            Section::make()
                                ->schema([
                                    TextEntry::make('model_name')
                                        ->label('Checkpointname:')
                                        ->inlineLabel(),
                                    TextEntry::make('link')
                                        ->label('CivitAI-Link: ')
                                        ->inlineLabel()
                                        ->getStateUsing(fn($record) => new HtmlString('<a href="'.CivitAIConnector::buildCivitAILinkByModelAndVersionID($record->civitai_id).'" target="_blank">'.CivitAIConnector::buildCivitAILinkByModelAndVersionID($record->civitai_id).'</a>'))
                                        ->visible(fn($record) => $record->civitai_id != null),
                                    ViewModelHelper::buildChangeNameAction($this->record),
                                    Section::make('Tags')
                                        ->extraAttributes(['style' => 'max-height: 200px; overflow-y: scroll;'])
                                        ->schema(ViewModelHelper::buildTagManagement($this->record)),
                                    Section::make('Your Notes')
                                        ->schema(ViewModelHelper::buildUserNoteManagement($this->record)),
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
                ]) as $checkpointFile){
                    $civitImages = [];
                    foreach ($checkpointFile->images->where('source', 'CivitAI') as $aiImage){
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
                    $retval[] = Section::make(basename($checkpointFile->filepath))
                        ->schema([
                            Section::make('CivitAI-Images')
                                ->schema($civitImages)
                                ->columns(10)
                                ->visible(fn() => count($civitImages) > 0),
                            Section::make('Metadata')
                                ->schema([
                                    Section::make()
                                        ->schema(ViewModelHelper::buildModelFileMetaData($checkpointFile)),
                                    Section::make('CivitAI-Description')
                                        ->schema([
                                            TextEntry::make('civitai_notes')
                                                ->getStateUsing(fn() => $checkpointFile->civitai_description ? GeneralFrontendHelper::wrapHTMLStringToImplementBreaks($checkpointFile->civitai_description) : 'No additional informations given.')
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
