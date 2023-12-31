<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TagResource\Pages;
use App\Models\Tag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class TagResource extends Resource
{
    protected static ?int $navigationSort = 10;

    protected static ?string $model = Tag::class;

    protected static ?string $navigationIcon = 'heroicon-s-tag';

    protected static ?string $navigationLabel = 'Tagmanager';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('tagname')
                    ->label('Tagname'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tagname')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->button(),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->action(function ($record){
                        self::deleteTag($record);
                    })
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records){
                            foreach ($records as $record){
                                self::deleteTag($record);
                            }
                        }),
                ]),
            ])
            ->defaultSort('tagname', 'asc')
            ->poll('60s');
    }

    public static function deleteTag(Tag $tag)
    {
        DB::table('checkpoint_tag')->where('tag_id', $tag->id)->delete();
        DB::table('lora_tag')->where('tag_id', $tag->id)->delete();
        DB::table('embedding_tag')->where('tag_id', $tag->id)->delete();
        $tag->delete();
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
            'index' => Pages\ListTags::route('/'),
        ];
    }
}
