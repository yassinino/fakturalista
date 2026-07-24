<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Models\Feature;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;
    protected static ?string $navigationIcon  = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Abonnements';
    protected static ?int    $navigationSort  = 1;

    public static function getNavigationLabel(): string { return 'Plans tarifaires'; }
    public static function getModelLabel(): string       { return 'Plan'; }
    public static function getPluralModelLabel(): string { return 'Plans'; }

    // ── Form ──────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── Identification ─────────────────────────────────────
            Forms\Components\Section::make('Identification')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->maxLength(64)
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('icon')
                        ->placeholder('heroicon-o-star')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('color')
                        ->default('#fa7070')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->columnSpan(1),

                    Forms\Components\Toggle::make('is_featured')
                        ->label('Plan mis en avant')
                        ->columnSpan(1),

                    Forms\Components\Toggle::make('active')
                        ->label('Actif')
                        ->default(true)
                        ->columnSpan(1),
                ]),

            // ── Traductions ────────────────────────────────────────
            Forms\Components\Section::make('Contenus (multilingue)')
                ->schema([
                    Forms\Components\Tabs::make('Langues')
                        ->tabs([
                            Forms\Components\Tabs\Tab::make('🇫🇷 Français')
                                ->schema([
                                    Forms\Components\TextInput::make('name_fr')
                                        ->label('Nom du plan')
                                        ->required()
                                        ->maxLength(80),
                                    Forms\Components\TextInput::make('badge_fr')
                                        ->label('Badge (ex: Populaire)'),
                                    Forms\Components\Textarea::make('short_description_fr')
                                        ->label('Description courte')
                                        ->rows(2),
                                    Forms\Components\Textarea::make('full_description_fr')
                                        ->label('Description longue')
                                        ->rows(4),
                                    Forms\Components\TextInput::make('button_text_fr')
                                        ->label('Texte du bouton CTA'),
                                ]),
                            Forms\Components\Tabs\Tab::make('🇬🇧 English')
                                ->schema([
                                    Forms\Components\TextInput::make('name_en')
                                        ->label('Plan name')
                                        ->maxLength(80),
                                    Forms\Components\TextInput::make('badge_en')
                                        ->label('Badge (e.g. Popular)'),
                                    Forms\Components\Textarea::make('short_description_en')
                                        ->label('Short description')
                                        ->rows(2),
                                    Forms\Components\Textarea::make('full_description_en')
                                        ->label('Full description')
                                        ->rows(4),
                                    Forms\Components\TextInput::make('button_text_en')
                                        ->label('Button CTA text'),
                                ]),
                            Forms\Components\Tabs\Tab::make('🇪🇸 Español')
                                ->schema([
                                    Forms\Components\TextInput::make('name_es')
                                        ->label('Nombre del plan')
                                        ->maxLength(80),
                                    Forms\Components\TextInput::make('badge_es')
                                        ->label('Badge (ej: Popular)'),
                                    Forms\Components\Textarea::make('short_description_es')
                                        ->label('Descripción corta')
                                        ->rows(2),
                                    Forms\Components\Textarea::make('full_description_es')
                                        ->label('Descripción larga')
                                        ->rows(4),
                                    Forms\Components\TextInput::make('button_text_es')
                                        ->label('Texto del botón CTA'),
                                ]),
                        ]),
                ]),

            // ── Tarification ───────────────────────────────────────
            Forms\Components\Section::make('Tarification')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('monthly_price')
                        ->label('Prix mensuel (centimes)')
                        ->numeric()
                        ->default(0)
                        ->helperText('Ex: 490 = 4,90€')
                        ->required(),

                    Forms\Components\TextInput::make('yearly_price')
                        ->label('Prix annuel (centimes)')
                        ->numeric()
                        ->nullable()
                        ->helperText('Laisser vide si non proposé'),

                    Forms\Components\TextInput::make('trial_days')
                        ->label('Jours d\'essai gratuit')
                        ->numeric()
                        ->default(14),

                    Forms\Components\TextInput::make('currency')
                        ->label('Devise')
                        ->default('eur')
                        ->maxLength(3),

                    Forms\Components\TextInput::make('stripe_price_id_monthly')
                        ->label('Stripe Price ID (mensuel)')
                        ->placeholder('price_xxx'),

                    Forms\Components\TextInput::make('stripe_price_id_yearly')
                        ->label('Stripe Price ID (annuel)')
                        ->placeholder('price_xxx'),
                ]),

            // ── CTA ────────────────────────────────────────────────
            Forms\Components\Section::make('Bouton d\'action')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('button_url')
                        ->label('URL du bouton')
                        ->url()
                        ->nullable(),

                    Forms\Components\Select::make('button_action')
                        ->label('Action du bouton')
                        ->options([
                            'checkout'    => 'Checkout Stripe',
                            'free_trial'  => 'Essai gratuit',
                            'contact'     => 'Contacter le commercial',
                        ])
                        ->default('checkout'),
                ]),

            // ── Limites ────────────────────────────────────────────
            Forms\Components\Section::make('Limites du plan')
                ->description('Laisser vide = illimité. 0 = accès bloqué.')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('limit_invoices_per_month')
                        ->label('Factures / mois')
                        ->numeric()
                        ->nullable()
                        ->placeholder('Illimité'),

                    Forms\Components\TextInput::make('limit_customers')
                        ->label('Clients')
                        ->numeric()
                        ->nullable()
                        ->placeholder('Illimité'),

                    Forms\Components\TextInput::make('limit_users')
                        ->label('Utilisateurs')
                        ->numeric()
                        ->nullable()
                        ->placeholder('Illimité'),

                    Forms\Components\TextInput::make('limit_products')
                        ->label('Produits')
                        ->numeric()
                        ->nullable()
                        ->placeholder('Illimité'),

                    Forms\Components\TextInput::make('limit_quotes')
                        ->label('Devis')
                        ->numeric()
                        ->nullable()
                        ->placeholder('Illimité'),
                ]),

            // ── Fonctionnalités ────────────────────────────────────
            Forms\Components\Section::make('Fonctionnalités incluses')
                ->schema([
                    Forms\Components\CheckboxList::make('features')
                        ->relationship('features', 'name_fr')
                        ->searchable()
                        ->columns(2),
                ]),

            // ── Marketing items ────────────────────────────────────
            Forms\Components\Section::make('Arguments marketing')
                ->description('Lignes affichées sur la page pricing. Glissez-déposez pour réordonner.')
                ->schema([
                    Forms\Components\Repeater::make('marketingItems')
                        ->relationship()
                        ->reorderableWithButtons()
                        ->orderColumn('sort_order')
                        ->addActionLabel('Ajouter une ligne')
                        ->columns(4)
                        ->schema([
                            Forms\Components\TextInput::make('text_fr')
                                ->label('Texte FR')
                                ->required()
                                ->columnSpan(2),
                            Forms\Components\TextInput::make('text_en')
                                ->label('Texte EN')
                                ->columnSpan(1),
                            Forms\Components\TextInput::make('text_es')
                                ->label('Texte ES')
                                ->columnSpan(1),
                            Forms\Components\TextInput::make('icon')
                                ->label('Icône')
                                ->default('✓')
                                ->columnSpan(1),
                            Forms\Components\Toggle::make('is_highlighted')
                                ->label('Mis en avant')
                                ->columnSpan(1),
                        ]),
                ]),

        ]);
    }

    // ── Table ─────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name_fr_label')
                    ->label('Nom')
                    ->getStateUsing(fn (Plan $record) => $record->translate('name', 'fr'))
                    ->searchable(query: fn ($query, $search) => $query->whereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(name, '$.fr')) LIKE ?", ["%{$search}%"]
                    )),

                Tables\Columns\TextColumn::make('monthly_price')
                    ->label('Prix / mois')
                    ->formatStateUsing(fn ($state) => number_format((int) $state / 100, 2, ',', '') . ' €')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Mis en avant')
                    ->boolean(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Actif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('subscriptions_count')
                    ->label('Abonnés')
                    ->counts('subscriptions')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('active')->label('Statut'),
                Tables\Filters\TernaryFilter::make('is_featured')->label('Mis en avant'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit'   => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
