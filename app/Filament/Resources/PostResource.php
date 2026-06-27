<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Models\Post;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Artículos del Blog';

    protected static ?string $modelLabel = 'Artículo';

    protected static ?string $pluralModelLabel = 'Artículos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Contenido')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Título')
                        ->required()
                        ->maxLength(255)
                        ->live(debounce: 500)
                        ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                            if ($operation === 'create') {
                                $set('slug', Str::slug($state));
                            }
                        }),

                    Forms\Components\TextInput::make('slug')
                        ->label('URL (slug)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText('Se genera automáticamente del título. Puedes cambiarlo.')
                        ->rules(['alpha_dash']),

                    Forms\Components\Textarea::make('excerpt')
                        ->label('Extracto')
                        ->rows(3)
                        ->maxLength(500)
                        ->helperText('Breve resumen que aparece en la lista del blog.'),

                    Forms\Components\RichEditor::make('body')
                        ->label('Contenido')
                        ->required()
                        ->columnSpanFull()
                        ->toolbarButtons([
                            'attachFiles',
                            'blockquote',
                            'bold',
                            'bulletList',
                            'codeBlock',
                            'h2',
                            'h3',
                            'italic',
                            'link',
                            'orderedList',
                            'redo',
                            'strike',
                            'underline',
                            'undo',
                        ])
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('blog/attachments'),
                ])->columns(2),

            Forms\Components\Section::make('Imagen destacada')
                ->schema([
                    Forms\Components\FileUpload::make('featured_image')
                        ->label('Imagen destacada')
                        ->image()
                        ->disk('public')
                        ->directory('blog/images')
                        ->imageResizeMode('cover')
                        ->imageCropAspectRatio('16:9')
                        ->imageResizeTargetWidth('1200')
                        ->imageResizeTargetHeight('675')
                        ->helperText('Recomendado: 1200×675px (ratio 16:9).')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Publicación')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->options([
                            'draft' => 'Borrador',
                            'published' => 'Publicado',
                        ])
                        ->default('draft')
                        ->required(),

                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Fecha de publicación')
                        ->helperText('Deja vacío para publicar inmediatamente al cambiar el estado a "Publicado".')
                        ->seconds(false),
                ])->columns(2),

            Forms\Components\Section::make('SEO')
                ->schema([
                    Forms\Components\TextInput::make('meta_title')
                        ->label('Meta título')
                        ->maxLength(70)
                        ->helperText('Si está vacío se usa el título del artículo. Máx. 70 caracteres.'),

                    Forms\Components\Textarea::make('meta_description')
                        ->label('Meta descripción')
                        ->rows(3)
                        ->maxLength(160)
                        ->helperText('Descripción para Google. Si está vacío se usa el extracto. Máx. 160 caracteres.'),
                ])->columns(1)->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->label('')
                    ->disk('public')
                    ->width(60)
                    ->height(40)
                    ->defaultImageUrl(asset('assets/icon.svg')),

                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(60),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'published',
                    ])
                    ->formatStateUsing(fn ($state) => $state === 'published' ? 'Publicado' : 'Borrador'),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Publicado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'published' => 'Publicado',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit'   => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
