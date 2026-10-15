<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlogGenerationResource\Pages;
use App\Models\BlogGeneration;
use App\Services\BlogAutomation\BlogAutomationService;
use Filament\Actions\Action as PageAction;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * AI blog automation - generation history (topic, outcome, quality report,
 * sources, token usage) and the review actions: preview, edit the draft,
 * approve & publish, reject, retry. Rows are written by
 * BlogAutomationService only; nothing is created or edited here directly.
 */
class BlogGenerationResource extends Resource
{
    protected static ?string $model = BlogGeneration::class;

    protected static ?string $slug             = 'blog-generations';
    protected static ?string $navigationIcon   = 'heroicon-o-queue-list';
    protected static ?string $navigationGroup  = 'Blog IA';
    protected static ?string $navigationLabel  = 'Historique des générations';
    protected static ?string $modelLabel       = 'Génération';
    protected static ?string $pluralModelLabel = 'Générations d\'articles';
    protected static ?int    $navigationSort   = 2;

    public static function canAccess(): bool
    {
        return auth('admin')->check();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = BlogGeneration::where('status', BlogGeneration::STATUS_DRAFT)->whereNotNull('post_id')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Articles à relire';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('run_date')->label('Jour')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn ($state) => BlogGeneration::STATUS_LABELS[$state] ?? $state)
                    ->color(fn ($state) => BlogGeneration::STATUS_COLORS[$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('country')
                    ->label('Marché')
                    ->formatStateUsing(fn ($state) => config("blog_automation.country_labels.{$state}", $state)),
                Tables\Columns\TextColumn::make('article_title')
                    ->label('Article / sujet')
                    ->default(fn (BlogGeneration $record) => $record->topic_title)
                    ->description(fn (BlogGeneration $record) => $record->primary_keyword)
                    ->limit(70)
                    ->wrap()
                    ->searchable(['article_title', 'topic_title', 'primary_keyword']),
                Tables\Columns\IconColumn::make('regulatory')->label('Régl.')->boolean()->trueColor('warning')->falseColor('gray'),
                Tables\Columns\TextColumn::make('word_count')->label('Mots')->numeric()->toggleable(),
                Tables\Columns\TextColumn::make('total_tokens')->label('Tokens')->numeric()->toggleable(),
                Tables\Columns\TextColumn::make('trigger')
                    ->label('Déclenchement')
                    ->formatStateUsing(fn ($state) => BlogGeneration::TRIGGER_LABELS[$state] ?? $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Statut')->options(BlogGeneration::STATUS_LABELS),
                Tables\Filters\SelectFilter::make('country')->label('Marché')->options(config('blog_automation.country_labels')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\ActionGroup::make([
                    self::previewAction(TableAction::make('preview')),
                    self::editPostAction(TableAction::make('editPost')),
                    self::approveAction(TableAction::make('approve')),
                    self::rejectAction(TableAction::make('reject')),
                    self::retryAction(TableAction::make('retry')),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        $list = fn (?array $items) => $items ? new HtmlString('<ul style="list-style:disc;padding-left:1.2rem">' . collect($items)->map(fn ($i) => '<li>' . e(is_array($i) ? json_encode($i, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $i) . '</li>')->implode('') . '</ul>') : '—';

        return $infolist->schema([
            Infolists\Components\Section::make('Résultat')->schema([
                Infolists\Components\TextEntry::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn ($state) => BlogGeneration::STATUS_LABELS[$state] ?? $state)
                    ->color(fn ($state) => BlogGeneration::STATUS_COLORS[$state] ?? 'gray'),
                Infolists\Components\TextEntry::make('article_title')->label('Article')->placeholder('—'),
                Infolists\Components\TextEntry::make('article_slug')->label('URL')->placeholder('—')
                    ->formatStateUsing(fn ($state) => '/blog/' . $state),
                Infolists\Components\TextEntry::make('word_count')->label('Mots')->placeholder('—'),
                Infolists\Components\TextEntry::make('error_message')->label('Erreur')->color('danger')->placeholder('—')->columnSpanFull(),
                Infolists\Components\TextEntry::make('draft_reasons')->label('Raisons du brouillon')->columnSpanFull()
                    ->state(fn (BlogGeneration $r) => $list($r->draft_reasons)),
            ])->columns(4),

            Infolists\Components\Section::make('Contrôle qualité')->schema([
                Infolists\Components\TextEntry::make('validation_errors')->label('Erreurs bloquantes')->color('danger')
                    ->state(fn (BlogGeneration $r) => $list($r->validation_errors)),
                Infolists\Components\TextEntry::make('validation_warnings')->label('Avertissements')
                    ->state(fn (BlogGeneration $r) => $list($r->validation_warnings)),
                Infolists\Components\TextEntry::make('ai_review')->label('Relecture IA')
                    ->state(fn (BlogGeneration $r) => $r->ai_review
                        ? new HtmlString('<strong>' . e($r->ai_review['verdict'] ?? '?') . '</strong>' . $list($r->ai_review['issues'] ?? []))
                        : 'Non effectuée'),
            ])->columns(3),

            Infolists\Components\Section::make('Sujet')->schema([
                Infolists\Components\TextEntry::make('country')->label('Marché')
                    ->formatStateUsing(fn ($state) => config("blog_automation.country_labels.{$state}", $state)),
                Infolists\Components\TextEntry::make('topic_category')->label('Catégorie')
                    ->formatStateUsing(fn ($state, BlogGeneration $r) => config("blog_automation.categories.{$r->country}.{$state}", $state)),
                Infolists\Components\TextEntry::make('topic_title')->label('Sujet de travail'),
                Infolists\Components\TextEntry::make('primary_keyword')->label('Mot-clé principal'),
                Infolists\Components\TextEntry::make('search_intent')->label('Intention'),
                Infolists\Components\IconEntry::make('regulatory')->label('Réglementaire')->boolean(),
                Infolists\Components\IconEntry::make('sources_verified')->label('Sources officielles vérifiées')->boolean(),
                Infolists\Components\TextEntry::make('topic_key')->label('Clé du sujet'),
            ])->columns(4)->collapsible(),

            Infolists\Components\Section::make('Sources')->schema([
                Infolists\Components\TextEntry::make('sources_official')->label('Sources officielles citées')
                    ->state(fn (BlogGeneration $r) => $list($r->sources['official'] ?? [])),
                Infolists\Components\TextEntry::make('sources_grounding')->label('Pages consultées par Gemini (recherche Google)')
                    ->state(fn (BlogGeneration $r) => $list(array_map(fn ($s) => $s['title'] ?? $s['uri'], $r->sources['grounding'] ?? []))),
                Infolists\Components\TextEntry::make('sources_queries')->label('Recherches effectuées')
                    ->state(fn (BlogGeneration $r) => $list($r->sources['queries'] ?? [])),
            ])->columns(3)->collapsible(),

            Infolists\Components\Section::make('Exécution')->schema([
                Infolists\Components\TextEntry::make('trigger')->label('Déclenchement')
                    ->formatStateUsing(fn ($state) => BlogGeneration::TRIGGER_LABELS[$state] ?? $state),
                Infolists\Components\TextEntry::make('attempts')->label('Tentatives'),
                Infolists\Components\TextEntry::make('model')->label('Modèle'),
                Infolists\Components\TextEntry::make('total_tokens')->label('Tokens')
                    ->formatStateUsing(fn ($state, BlogGeneration $r) => number_format($state, 0, ',', ' ') . " (entrée {$r->prompt_tokens}, sortie {$r->output_tokens})"),
                Infolists\Components\TextEntry::make('estimated_cost_usd')->label('Coût estimé')
                    ->formatStateUsing(fn ($state) => $state !== null ? '$' . number_format((float) $state, 4) : 'Non configuré')
                    ->placeholder('Non configuré'),
                Infolists\Components\TextEntry::make('started_at')->label('Début')->dateTime('d/m/Y H:i:s')->placeholder('—'),
                Infolists\Components\TextEntry::make('finished_at')->label('Fin')->dateTime('d/m/Y H:i:s')->placeholder('—'),
                Infolists\Components\TextEntry::make('reviewer.name')->label('Relu par')->placeholder('—'),
            ])->columns(4)->collapsible(),
        ]);
    }

    // ── Actions shared by the table and the view page ───────────────────

    public static function previewAction(TableAction|PageAction $action): TableAction|PageAction
    {
        return $action->label('Aperçu')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->url(fn (BlogGeneration $record) => $record->post_id ? route('filament.backoffice.blog.preview', ['post' => $record->post_id]) : null, shouldOpenInNewTab: true)
            ->visible(fn (BlogGeneration $record) => $record->post_id !== null);
    }

    public static function editPostAction(TableAction|PageAction $action): TableAction|PageAction
    {
        return $action->label('Modifier l\'article')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->url(fn (BlogGeneration $record) => $record->post_id ? PostResource::getUrl('edit', ['record' => $record->post_id]) : null)
            ->visible(fn (BlogGeneration $record) => $record->post_id !== null);
    }

    public static function approveAction(TableAction|PageAction $action): TableAction|PageAction
    {
        return $action->label('Approuver et publier')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('L\'article sera publié immédiatement sur le blog et ajouté au sitemap. Vérifiez les points signalés par le contrôle qualité avant de confirmer.')
            ->visible(fn (BlogGeneration $record) => $record->isAwaitingReview())
            ->action(function (BlogGeneration $record) {
                try {
                    app(BlogAutomationService::class)->approve($record, auth('admin')->user());
                } catch (Throwable $e) {
                    Notification::make()->title('Publication impossible')->body($e->getMessage())->danger()->send();

                    return;
                }
                Notification::make()->title('Article publié')->success()->send();
            });
    }

    public static function rejectAction(TableAction|PageAction $action): TableAction|PageAction
    {
        return $action->label('Rejeter')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Le brouillon est supprimé. Le sujet reste dans l\'historique et ne sera pas proposé à nouveau.')
            ->visible(fn (BlogGeneration $record) => $record->status === BlogGeneration::STATUS_DRAFT)
            ->action(function (BlogGeneration $record) {
                try {
                    app(BlogAutomationService::class)->reject($record, auth('admin')->user());
                } catch (Throwable $e) {
                    Notification::make()->title('Rejet impossible')->body($e->getMessage())->danger()->send();

                    return;
                }
                Notification::make()->title('Article rejeté')->success()->send();
            });
    }

    public static function retryAction(TableAction|PageAction $action): TableAction|PageAction
    {
        return $action->label('Relancer')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('La génération est relancée en arrière-plan sur le même créneau (pas d\'article supplémentaire pour la journée).')
            ->visible(fn (BlogGeneration $record) => $record->status === BlogGeneration::STATUS_FAILED)
            ->action(function (BlogGeneration $record) {
                $ok = app(BlogAutomationService::class)->retry($record);
                Notification::make()
                    ->title($ok ? 'Génération relancée' : 'Cette génération n\'est plus en échec')
                    ->{$ok ? 'success' : 'warning'}()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogGenerations::route('/'),
            'view'  => Pages\ViewBlogGeneration::route('/{record}'),
        ];
    }
}
