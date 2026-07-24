<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeatureResource\Pages;
use App\Models\Feature;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FeatureResource extends Resource
{
    protected static ?string $model = Feature::class;
    protected static ?string $navigationIcon  = 'heroicon-o-sparkles';
    protected static ?string $navigationGroup = 'Abonnements';
    protected static ?int    $navigationSort  = 2;

    public static function getNavigationLabel(): string { return 'Fonctionnalités'; }
    public static function getModelLabel(): string       { return 'Fonctionnalité'; }
    public static function getPluralModelLabel(): string { return 'Fonctionnalités'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identification')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->maxLength(64)
                        ->helperText('Ex: stripe, pdf_export, api_access'),

                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),

            Forms\Components\Section::make('Noms (multilingue)')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('name_fr')
                        ->label('Nom FR')
                        ->required(),
                    Forms\Components\TextInput::make('name_en')
                        ->label('Nom EN'),
                    Forms\Components\TextInput::make('name_es')
                        ->label('Nom ES'),
                ]),

            Forms\Components\Section::make('Description')
                ->schema([
                    Forms\Components\Textarea::make('description')
                        ->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name_fr')->label('Nom FR')->searchable(),
                Tables\Columns\TextColumn::make('name_en')->label('Nom EN'),
                Tables\Columns\TextColumn::make('plans_count')
                    ->label('Plans')
                    ->counts('plans'),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Ordre')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Statut'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFeatures::route('/'),
            'create' => Pages\CreateFeature::route('/create'),
            'edit'   => Pages\EditFeature::route('/{record}/edit'),
        ];
    }
}
