<?php

namespace App\Filament\Resources\CheckpointResource\Helpers;

use App\Models\AIImage;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\Actions\Action;
use Illuminate\Support\HtmlString;

class GeneralFrontendHelper
{
    public static function buildExampleImageViewAction(AIImage $aiImage) : Action
    {
        return Action::make('view')
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
            ->modalCancelAction(false);
    }

    public static function wrapHTMLStringToImplementBreaks(string $htmlString) : HtmlString
    {
        return new HtmlString('<div style="word-break: break-word">'.$htmlString.'</div>');
    }
}
