<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BlogGenerationResource;
use App\Models\BlogAutomationSetting;
use App\Services\Ai\GeminiClient;
use App\Services\BlogAutomation\BlogAutomationService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * AI blog automation: status, figures, settings and "generate now".
 * Settings live in the central blog_automation_settings row; the review
 * workflow is in BlogGenerationResource. Admin guard only.
 */
class BlogAutomation extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug            = 'blog-automation';
    protected static ?string $navigationIcon  = 'heroicon-o-sparkles';
    protected static ?string $navigationGroup = 'Blog IA';
    protected static ?string $navigationLabel = 'Automatisation blog IA';
    protected static ?string $title           = 'Automatisation du blog IA';
    protected static ?int    $navigationSort  = 1;

    protected static string $view = 'filament.pages.blog-automation';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth('admin')->check();
    }

    public function mount(): void
    {
        $settings = BlogAutomationSetting::current();
        $this->form->fill($settings->only([
            'enabled', 'auto_publish', 'require_manual_approval', 'allow_regulatory_autopublish',
            'ai_review', 'max_per_day', 'language', 'excluded_topics', 'notification_email',
        ]) + ['target_countries' => $settings->countries()]);
    }

    public function getSubheading(): ?string
    {
        return 'Un article SEO par jour au maximum, rédigé par Gemini et vérifié par un contrôle qualité déterministe. '
            . 'Par défaut, tout est enregistré en brouillon pour relecture.';
    }

    public function form(Form $form): Form
    {
        $cap = (int) config('blog_automation.max_per_day_cap', 1);

        return $form->statePath('data')->schema([
            Forms\Components\Section::make('Activation')->schema([
                Forms\Components\Toggle::make('enabled')
                    ->label('Génération automatique quotidienne')
                    ->helperText('Tous les jours à ' . config('blog_automation.schedule_time') . ' (' . config('blog_automation.timezone') . ').'),
                Forms\Components\Toggle::make('auto_publish')
                    ->label('Publication automatique')
                    ->helperText('Désactivée : chaque article est enregistré en brouillon.')
                    ->live(),
                Forms\Components\Toggle::make('require_manual_approval')
                    ->label('Validation manuelle obligatoire')
                    ->helperText('Activée : aucun article n\'est publié sans votre approbation, même si la publication automatique est active.'),
                Forms\Components\Toggle::make('allow_regulatory_autopublish')
                    ->label('Autoriser la publication automatique des sujets réglementaires')
                    ->helperText('Uniquement si des sources officielles ont été consultées et citées. Recommandé : désactivé.'),
                Forms\Components\Toggle::make('ai_review')
                    ->label('Relecture IA complémentaire')
                    ->helperText('Deuxième passage Gemini qui peut seulement bloquer une publication, jamais l\'autoriser.'),
            ])->columns(2),

            Forms\Components\Section::make('Ciblage')->schema([
                Forms\Components\Placeholder::make('frequency')
                    ->label('Fréquence')
                    ->content('Quotidienne'),
                Forms\Components\TextInput::make('max_per_day')
                    ->label('Articles maximum par jour')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue($cap)
                    ->required(),
                Forms\Components\Select::make('language')
                    ->label('Langue des articles')
                    ->options(['fr' => 'Français'])
                    ->selectablePlaceholder(false)
                    ->required(),
                Forms\Components\CheckboxList::make('target_countries')
                    ->label('Marchés ciblés')
                    ->options(collect(config('blog_automation.country_labels'))
                        ->map(fn ($label, $code) => $label . ' (' . config("blog_automation.country_weights.{$code}") . ' %)')
                        ->all())
                    ->required()
                    ->columns(2),
                Forms\Components\Textarea::make('excluded_topics')
                    ->label('Sujets exclus')
                    ->helperText('Un mot ou une expression par ligne. Tout sujet qui la contient est écarté (ex. : « TVA », « BTP »).')
                    ->rows(4)
                    ->maxLength(5000)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('notification_email')
                    ->label('E-mail de notification')
                    ->email()
                    ->placeholder(config('fakturalista.admin_email'))
                    ->helperText('Vide : adresse administrateur par défaut.'),
            ])->columns(3),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['max_per_day'] = max(1, min((int) $data['max_per_day'], (int) config('blog_automation.max_per_day_cap', 1)));
        $data['notification_email'] = $data['notification_email'] ?: null;

        BlogAutomationSetting::current()->update($data);

        Notification::make()->title('Réglages enregistrés')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateNow')
                ->label('Générer un article maintenant')
                ->icon('heroicon-o-bolt')
                ->requiresConfirmation()
                ->modalDescription('Un article est généré en arrière-plan (1 à 3 minutes) et compte dans la limite du jour. Il sera publié ou mis en brouillon selon les réglages enregistrés.')
                ->action(function () {
                    if (! app(GeminiClient::class)->isConfigured()) {
                        Notification::make()->title('GEMINI_API_KEY n\'est pas configurée')->danger()->send();

                        return;
                    }
                    $service = app(BlogAutomationService::class);
                    $generation = $service->claim('manual');
                    if (! $generation) {
                        Notification::make()
                            ->title('Limite quotidienne atteinte')
                            ->body('L\'article du jour a déjà été généré. Relancez une génération en échec depuis l\'historique si besoin.')
                            ->warning()
                            ->send();

                        return;
                    }
                    $service->dispatch($generation);

                    Notification::make()
                        ->title('Génération lancée')
                        ->body('Suivez-la dans l\'historique (génération #' . $generation->id . ').')
                        ->success()
                        ->send();
                }),
            Action::make('history')
                ->label('Historique')
                ->icon('heroicon-o-queue-list')
                ->color('gray')
                ->url(BlogGenerationResource::getUrl()),
        ];
    }

    public function nextRun(): Carbon
    {
        $tz = config('blog_automation.timezone');
        [$hour, $minute] = array_map('intval', explode(':', config('blog_automation.schedule_time')));
        $next = now($tz)->setTime($hour, $minute);

        return $next->isPast() ? $next->addDay() : $next;
    }

    protected function getViewData(): array
    {
        return [
            'settings'   => BlogAutomationSetting::current(),
            'stats'      => app(BlogAutomationService::class)->stats(),
            'model'      => app(GeminiClient::class)->model(),
            'configured' => app(GeminiClient::class)->isConfigured(),
            'grounding'  => (bool) config('blog_automation.gemini.grounding'),
            'nextRun'    => $this->nextRun(),
            'pricing'    => is_numeric(config('blog_automation.gemini.price_input_per_million')),
        ];
    }
}
