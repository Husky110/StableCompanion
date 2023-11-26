<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CheckpointResource\Pages;
use App\Filament\Resources\CheckpointResource\RelationManagers;
use App\Models\Checkpoint;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class CheckpointResource extends Resource
{
    protected static ?string $model = Checkpoint::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

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
                Tables\Columns\ImageColumn::make('image_name')
                    ->disk('modelimages')
                    ->height(200)
                    ->label(false),
                Tables\Columns\TextColumn::make('checkpoint_name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('files')
                    ->label('Files')
                    ->getStateUsing(function ($record){
                        return new HtmlString('Imported Versions: '.$record->files()->count().'<br>Downloads:'.$record->activedownloads()->count());
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tags')
                    ->label('Tags')
                    ->multiple()
                    ->relationship('tags', 'tagname')
                    ->preload(),
                Tables\Filters\Filter::make('only_downloading')
                    ->label('Only downloading')
                    ->toggle()
                    ->query(fn($query) => $query->has('activedownloads'))
            ])
            ->actions([
                Tables\Actions\DeleteAction::make('delete')
                    ->button()
                    ->action(function ($record){
                        foreach ($record->files as $checkpointFile){
                            $checkpointFile->deleteCheckpointFile();
                        }
                        $record->deleteCheckpoint();
                    })
                    ->modalDescription('Are you sure you want to delete this checkpoint? This will also delete all versions and images! Continue?')
            ])
            ->bulkActions([

            ])
            ->poll('60s');
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
            'index' => Pages\ListCheckpoints::route('/'),
            'view' => Pages\ViewCheckpoint::route('/{record}')
        ];
    }
}
