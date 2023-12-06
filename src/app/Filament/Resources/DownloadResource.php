<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DownloadResource\Pages;
use App\Filament\Resources\DownloadResource\RelationManagers;
use App\Http\Helpers\Aria2Connector;
use App\Http\Helpers\CivitAIConnector;
use App\Models\CivitDownload;
use Filament\Actions\DeleteAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class DownloadResource extends Resource
{

    protected static ?int $navigationSort = 0;

    protected static ?string $model = CivitDownload::class;

    protected static ?string $navigationLabel = 'CivitAI-Downloads';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-on-square-stack';

    public static function getNavigationBadge(): ?string
    {
        $total = CivitDownload::count();
        return $total > 0 ? $total : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $total = CivitDownload::count();
        $errors = CivitDownload::where('status', 'error')->count();
        if($errors == 0){
            return 'success';
        } else if($errors < $total){
            return 'warning';
        } else {
            return 'danger';
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->getStateUsing(fn($record) => CivitAIConnector::getModelMetaByID($record->civit_id)['name'].' - '.CivitAIConnector::getSpecificModelVersionByModelIDAndVersionID($record->civit_id, $record->version)['name']),
                Tables\Columns\TextColumn::make('link')
                    ->label('CivitAI-URL')
                    ->getStateUsing(fn($record) => new HtmlString('<a href="civitai.com/models/'.$record->civit_id.'?modelVersionId='.$record->version.'" target="_blank">Link</a>')),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->getStateUsing(fn($record) => strtoupper($record->type)),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn($record) => strtoupper($record->status)),
                Tables\Columns\TextColumn::make('Progress')
                    ->label('Progress')
                    ->getStateUsing(function ($record){
                        if($record->status == 'error' && $record->error_message != null){
                            return new HtmlString($record->error_message);
                        }
                        if($record->aria_id == null || $record->status != 'active'){
                            return '0 %';
                        } else {
                            $result = Aria2Connector::getInstance()->tellStatus($record->aria_id);
                            $downloadSpeed = $result['result']['downloadSpeed'];
                            if($downloadSpeed > 0){
                                $eta = round(($result['result']['totalLength'] - $result['result']['completedLength']) / $downloadSpeed, 0, PHP_ROUND_HALF_UP);
                                $etaMod = $eta % 60;
                                $etaString = ($eta - $etaMod) / 60 .' min '.$etaMod.' sec';
                                $retval = (round($result['result']['completedLength'] / $result['result']['totalLength'], '4') * 100).' % @ '.round($result['result']['downloadSpeed'] / 1024 /1024, 2).' MB/s <br>ETA: '.$etaString;
                                return new HtmlString($retval);
                            } else if($result['result']['totalLength'] == $result['result']['completedLength']){
                                return '100% - doing post-process';
                            } else {
                                return 'Error';
                            }
                        }
                    })
                    ->alignCenter()
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('retry_action')
                    ->label('Retry')
                    ->button()
                    ->action(function ($record){
                        $record->status = 'pending';
                        $record->save();
                        Aria2Connector::sendDownloadToAria2($record);
                    })
                    ->requiresConfirmation()
                    ->modalDescription('Have you checked that the error is resolved? (Like enough diskspace, and connection is stable.)')
                    ->visible(fn($record) => $record->status == 'error' && str_contains($record->error_message, 'Login') == false),
                Tables\Actions\DeleteAction::make('delete')
                    ->button()
                    ->action(function ($record){
                        if($record->status != 'error'){
                            Aria2Connector::abortDownloadInAria2($record);
                        }
                        if($record->existingModel != null){
                            if($record->existingModel->files->count() == 0 && $record->existingModel->activedownloads->count() == 0){
                                $record->existingModel->deleteModel();
                            }
                        }
                        $record->delete();
                    }),
            ])
            ->bulkActions([

            ])
            ->emptyStateHeading('No current downloads')
            ->defaultSort('id', 'asc')
            ->poll();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDownloads::route('/'),
        ];
    }
}
