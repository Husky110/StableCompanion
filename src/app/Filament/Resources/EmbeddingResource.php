<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmbeddingResource\Pages;
use App\Models\Embedding;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class EmbeddingResource extends Resource
{
    protected static ?string $model = Embedding::class;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
                Tables\Columns\TextColumn::make('model_name')
                    ->label('Name')
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(function ($record){
                        if(strlen($record->model_name) > 20){
                            return substr($record->model_name, 0, 20).'...';
                        } else {
                            return $record->model_name;
                        }
                    })
                    ->tooltip(fn($record) => $record->model_name),
                Tables\Columns\TextColumn::make('baseModels')
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
                            if($count == 4){
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
                Tables\Filters\TernaryFilter::make('modeltype')
                    ->placeholder('All')
                    ->trueLabel('SD-Models')
                    ->falseLabel('XL-Models')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('files', fn($query) => $query->whereNot('baseModelType', 'like', '%XL%')),
                        false: fn (Builder $query) => $query->whereHas('files', fn($query) => $query->where('baseModelType', 'like', '%XL%')),
                        blank: fn (Builder $query) => $query,
                    ),
                Tables\Filters\Filter::make('only_unliked')
                    ->label('Only unlinked models')
                    ->toggle()
                    ->query(fn($query) => $query->whereNull('civitai_id')),
                Tables\Filters\Filter::make('only_downloading')
                    ->label('Only downloading')
                    ->toggle()
                    ->query(fn($query) => $query->has('activedownloads')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->button(),
                Tables\Actions\DeleteAction::make('delete')
                    ->button()
                    ->action(function ($record){
                        foreach ($record->files as $embeddingFile){
                            $embeddingFile->deleteModelFile();
                        }
                        $record->deleteModel();
                    })
                    ->modalDescription('Are you sure you want to delete this Embedding? This will also delete all versions and images! Continue?')
            ])
            ->bulkActions([

            ])
            ->poll('60s')
            ->defaultSort('model_name');
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
            'index' => Pages\ListEmbeddings::route('/'),
            'view' => Pages\ViewEmbedding::route('/{record}'),
        ];
    }
}
