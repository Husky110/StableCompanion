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
                    ->searchable()
                    ->getStateUsing(function ($record){
                        if(strlen($record->checkpoint_name) > 40){
                            return substr($record->checkpoint_name).'...';
                        } else {
                            return $record->checkpoint_name;
                        }
                    })
                    ->tooltip(fn($record) => $record->checkpoint_name),
                Tables\Columns\TextColumn::make('checkpoint_baseModel')
                    ->label('Base Models')
                    ->getStateUsing(function ($record){
                       $bases = [];
                       foreach ($record->files as $file){
                           if(!in_array($file->baseModel, $bases)){
                               $bases[] = $file->baseModel;
                           }
                       }
                       return implode(', ', $bases);
                    }),
                Tables\Columns\TextColumn::make('files')
                    ->label('Files')
                    ->getStateUsing(function ($record){
                        return new HtmlString('Imported Versions: '.$record->files()->count().'<br>Downloads:'.$record->activedownloads()->count());
                    }),
                Tables\Columns\TextColumn::make('tags.tagname')
                    ->label('Tags')
                    ->getStateUsing(function ($record){
                        $tagNames = '';
                        $count = 0;
                        foreach ($record->tags->sortBy('tagname') as $tag){
                            $count++;
                            $tagNames .= $tag->tagname.', ';
                            if($count == 5){
                                $tagNames.= '<br>';
                                $count = 0;
                            }
                        }
                        if(str_ends_with($tagNames, '<br>')){
                            $tagNames = substr($tagNames, 0, -6);
                        } else {
                            $tagNames = substr($tagNames, 0, -2);
                        }
                        return new HtmlString($tagNames);
                    })
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
                Tables\Actions\ViewAction::make('view')
                    ->button(),
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
            ->poll('60s')
            ->defaultSort('checkpoint_name');
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
