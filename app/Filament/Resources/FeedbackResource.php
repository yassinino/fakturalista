<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeedbackResource\Pages;
use App\Models\Feedback;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Backoffice inbox for the feedback widget (public site + tenant app).
 * Read-only except for status, internal notes and emailed replies - see
 * Pages\ViewFeedback for the reply/notes actions.
 */
class FeedbackResource extends Resource
{
    protected static ?string $model = Feedback::class;

    protected static ?string $slug                 = 'feedback';
    protected static ?string $navigationIcon       = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup      = 'Feedback';
    protected static ?string $navigationLabel      = 'Retours utilisateurs';
    protected static ?string $modelLabel           = 'Retour';
    protected static ?string $pluralModelLabel     = 'Retours utilisateurs';
    protected static ?string $recordTitleAttribute = 'id';
    protected static ?int    $navigationSort       = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = Feedback::new()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Nouveaux retours';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'new'         => 'danger',
            'in_progress' => 'warning',
            'resolved'    => 'success',
            default       => 'gray',
        };
    }

    public static function typeColor(?string $type): string
    {
        return match ($type) {
            'bug'         => 'danger',
            'suggestion'  => 'info',
            'improvement' => 'success',
            default       => 'gray',
        };
    }

    public static function attachmentUrl(Feedback $record): ?string
    {
        return $record->hasAttachment()
            ? route('filament.backoffice.feedback.attachment', ['feedback' => $record])
            : null;
    }

    // ── Table ────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Feedback::TYPE_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => static::typeColor($state)),

                Tables\Columns\TextColumn::make('message')
                    ->label('Message')
                    ->searchable()
                    ->limit(70)
                    ->tooltip(fn (Feedback $record) => Str::limit($record->message, 400))
                    ->wrap()
                    ->icon(fn (Feedback $record) => $record->hasAttachment() ? 'heroicon-m-photo' : null),

                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Feedback::SOURCE_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => $state === Feedback::SOURCE_TENANT ? 'primary' : 'gray'),

                Tables\Columns\TextColumn::make('company_name')
                    ->label('Entreprise')
                    ->searchable()
                    ->placeholder('-')
                    ->description(fn (Feedback $record) => $record->tenant_id),

                Tables\Columns\TextColumn::make('user_name')
                    ->label('Utilisateur')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('page_url')
                    ->label('Page')
                    ->formatStateUsing(fn (?string $state) => $state ? (parse_url($state, PHP_URL_PATH) ?: '/') : null)
                    ->limit(30)
                    ->tooltip(fn (Feedback $record) => $record->page_url)
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\SelectColumn::make('status')
                    ->label('Statut')
                    ->options(Feedback::STATUS_LABELS)
                    ->selectablePlaceholder(false)
                    ->rules(['required', 'in:' . implode(',', Feedback::STATUSES)])
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Reçu le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options(Feedback::TYPE_LABELS),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options(Feedback::STATUS_LABELS),

                Tables\Filters\SelectFilter::make('source')
                    ->label('Source')
                    ->options(Feedback::SOURCE_LABELS),

                Tables\Filters\Filter::make('created_at')
                    ->label('Date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Du'),
                        Forms\Components\DatePicker::make('until')->label('Au'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Depuis le ' . \Carbon\Carbon::parse($data['from'])->format('d/m/Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = "Jusqu'au " . \Carbon\Carbon::parse($data['until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Voir'),
                Tables\Actions\DeleteAction::make()->label('Supprimer'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('setStatus')
                        ->label('Changer le statut')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->label('Statut')
                                ->options(Feedback::STATUS_LABELS)
                                ->in(Feedback::STATUSES)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each->update(['status' => $data['status']]);

                            Notification::make()->title('Statut mis à jour')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Aucun retour pour le moment')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right');
    }

    // ── Detail page ──────────────────────────────────────────────────────

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Grid::make(3)->schema([

                Infolists\Components\Group::make([
                    Infolists\Components\Section::make('Message')
                        ->schema([
                            Infolists\Components\TextEntry::make('message')
                                ->hiddenLabel()
                                ->extraAttributes(['style' => 'white-space: pre-line; overflow-wrap: anywhere;']),
                        ]),

                    Infolists\Components\Section::make("Capture d'écran")
                        ->visible(fn (Feedback $record) => $record->hasAttachment())
                        ->schema([
                            Infolists\Components\ImageEntry::make('attachment_path')
                                ->hiddenLabel()
                                ->state(fn (Feedback $record) => static::attachmentUrl($record))
                                ->height('auto')
                                ->extraImgAttributes(['style' => 'max-width: 100%; max-height: 520px; border-radius: 8px; border: 1px solid rgba(0,0,0,.08);', 'alt' => "Capture d'écran"])
                                ->url(fn (Feedback $record) => static::attachmentUrl($record), shouldOpenInNewTab: true),
                            Infolists\Components\TextEntry::make('attachment_name')
                                ->hiddenLabel()
                                ->color('gray')
                                ->size(Infolists\Components\TextEntry\TextEntrySize::Small)
                                ->formatStateUsing(fn (Feedback $record) => sprintf(
                                    '%s · %s · %s Ko',
                                    $record->attachment_name,
                                    $record->attachment_mime,
                                    number_format(($record->attachment_size ?? 0) / 1024, 0, ',', ' ')
                                )),
                        ]),

                    Infolists\Components\Section::make('Historique des réponses')
                        ->description("Réponses envoyées par e-mail à l'expéditeur.")
                        ->schema([
                            Infolists\Components\TextEntry::make('no_replies')
                                ->hiddenLabel()
                                ->state('Aucune réponse envoyée pour le moment.')
                                ->color('gray')
                                ->visible(fn (Feedback $record) => $record->replies->isEmpty()),
                            Infolists\Components\RepeatableEntry::make('replies')
                                ->hiddenLabel()
                                ->visible(fn (Feedback $record) => $record->replies->isNotEmpty())
                                ->schema([
                                    Infolists\Components\TextEntry::make('sent_at')
                                        ->hiddenLabel()
                                        ->color('gray')
                                        ->size(Infolists\Components\TextEntry\TextEntrySize::Small)
                                        ->formatStateUsing(fn ($state, $record) => sprintf(
                                            'Envoyée le %s%s',
                                            $state?->format('d/m/Y à H:i'),
                                            $record->admin ? ' par ' . $record->admin->name : ''
                                        )),
                                    Infolists\Components\TextEntry::make('message')
                                        ->hiddenLabel()
                                        ->extraAttributes(['style' => 'white-space: pre-line; overflow-wrap: anywhere;']),
                                ]),
                        ]),
                ])->columnSpan(2),

                Infolists\Components\Group::make([
                    Infolists\Components\Section::make('Détails')
                        ->schema([
                            Infolists\Components\TextEntry::make('status')
                                ->label('Statut')
                                ->badge()
                                ->formatStateUsing(fn (string $state) => Feedback::STATUS_LABELS[$state] ?? $state)
                                ->color(fn (string $state) => static::statusColor($state)),
                            Infolists\Components\TextEntry::make('type')
                                ->label('Type')
                                ->badge()
                                ->formatStateUsing(fn (string $state) => Feedback::TYPE_LABELS[$state] ?? $state)
                                ->color(fn (string $state) => static::typeColor($state)),
                            Infolists\Components\TextEntry::make('source')
                                ->label('Source')
                                ->formatStateUsing(fn (string $state) => $state === Feedback::SOURCE_TENANT ? 'Application (tenant)' : 'Site public (visiteur)'),
                            Infolists\Components\TextEntry::make('created_at')
                                ->label('Reçu le')
                                ->dateTime('d/m/Y à H:i'),
                            Infolists\Components\TextEntry::make('page_url')
                                ->label('Page')
                                ->placeholder('-')
                                ->extraAttributes(['style' => 'overflow-wrap: anywhere;']),
                            Infolists\Components\TextEntry::make('locale')
                                ->label('Langue')
                                ->formatStateUsing(fn (?string $state) => Str::upper((string) $state))
                                ->placeholder('-'),
                        ]),

                    Infolists\Components\Section::make('Expéditeur')
                        ->schema([
                            Infolists\Components\TextEntry::make('email')
                                ->label('E-mail')
                                ->copyable(),
                            Infolists\Components\TextEntry::make('user_name')
                                ->label('Utilisateur')
                                ->placeholder('-')
                                ->helperText(fn (Feedback $record) => $record->user_id ? 'ID utilisateur : ' . $record->user_id : null),
                            Infolists\Components\TextEntry::make('company_name')
                                ->label('Entreprise')
                                ->placeholder('-')
                                ->helperText(fn (Feedback $record) => $record->tenant_id ? 'Tenant : ' . $record->tenant_id : null)
                                ->url(fn (Feedback $record) => $record->tenant
                                    ? TenantResource::getUrl('view', ['record' => $record->tenant])
                                    : null),
                            Infolists\Components\TextEntry::make('user_agent')
                                ->label('Navigateur')
                                ->size(Infolists\Components\TextEntry\TextEntrySize::Small)
                                ->color('gray')
                                ->placeholder('-'),
                        ]),

                    Infolists\Components\Section::make('Notes internes')
                        ->description("Privées - jamais envoyées à l'expéditeur.")
                        ->icon('heroicon-o-lock-closed')
                        ->schema([
                            Infolists\Components\TextEntry::make('admin_notes')
                                ->hiddenLabel()
                                ->placeholder('Aucune note.')
                                ->extraAttributes(['style' => 'white-space: pre-line; overflow-wrap: anywhere;']),
                        ]),
                ])->columnSpan(1),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedback::route('/'),
            'view'  => Pages\ViewFeedback::route('/{record}'),
        ];
    }
}
