@php
    $tz = config('blog_automation.timezone');
    $last = $stats['last'];
    $cards = [
        ['Articles générés', $stats['generated'], null],
        ['Publiés', $stats['published'], 'success'],
        ['À relire', $stats['awaiting'], $stats['awaiting'] ? 'warning' : null],
        ['Échecs', $stats['failed'], $stats['failed'] ? 'danger' : null],
    ];
    $statusLabels = [
        'published' => 'Article publié', 'draft' => 'Brouillon à relire', 'failed' => 'Échec', 'skipped' => 'Aucun sujet unique',
        'disabled' => 'Automatisation désactivée', 'already_done' => 'Déjà traité', 'queued' => 'Mise en file',
    ];
@endphp

<x-filament-panels::page>
    <style>
        .fk-ba-grid { display: grid; gap: 1rem; grid-template-columns: 1fr; }
        .fk-ba-cards { display: grid; gap: 1rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        @media (min-width: 1024px) { .fk-ba-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } .fk-ba-cards { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .fk-ba-dl { font-size: .875rem; }
        .fk-ba-dl > div { display: flex; justify-content: space-between; gap: 1rem; padding: .3rem 0; }
        .fk-ba-dl dt, .fk-ba-muted { color: rgb(var(--gray-500)); }
        .fk-ba-dl dd { text-align: end; }
        .fk-ba-muted { font-size: .75rem; }
        .fk-ba-num { font-size: 1.9rem; font-weight: 600; line-height: 1.2; margin-top: .25rem; font-variant-numeric: tabular-nums; }
        .fk-ba-success { color: rgb(var(--success-600)); }
        .fk-ba-warning { color: rgb(var(--warning-600)); }
        .fk-ba-danger { color: rgb(var(--danger-600)); }
        .fk-ba-actions { margin-top: 1.5rem; }
    </style>

    <div class="fk-ba-grid">
        <x-filament::section heading="État">
            <dl class="fk-ba-dl">
                <div><dt>Automatisation</dt>
                    <dd><x-filament::badge :color="$settings->enabled ? 'success' : 'gray'">{{ $settings->enabled ? 'Activée' : 'Désactivée' }}</x-filament::badge></dd>
                </div>
                <div><dt>Mode</dt><dd>{{ $settings->publishesAutomatically() ? 'Publication automatique' : 'Brouillons uniquement' }}</dd></div>
                <div><dt>Prochaine exécution</dt><dd>{{ $settings->enabled ? $nextRun->locale('fr')->isoFormat('ddd D MMM, HH:mm') : '—' }}</dd></div>
                <div><dt>Dernière exécution</dt>
                    <dd>
                        @if ($settings->last_run_at)
                            {{ $settings->last_run_at->timezone($tz)->locale('fr')->isoFormat('D MMM, HH:mm') }}<br>
                            <span class="fk-ba-muted">{{ $statusLabels[$settings->last_run_status] ?? $settings->last_run_status }}</span>
                        @else
                            Jamais
                        @endif
                    </dd>
                </div>
            </dl>
        </x-filament::section>

        <x-filament::section heading="Fournisseur IA">
            <dl class="fk-ba-dl">
                <div><dt>Fournisseur</dt><dd>Google Gemini</dd></div>
                <div><dt>Modèle</dt><dd>{{ $model }}</dd></div>
                <div><dt>Clé API</dt>
                    <dd><x-filament::badge :color="$configured ? 'success' : 'danger'">{{ $configured ? 'Configurée' : 'Manquante' }}</x-filament::badge></dd>
                </div>
                <div><dt>Vérification des sources</dt><dd>{{ $grounding ? 'Recherche Google' : 'Désactivée' }}</dd></div>
            </dl>
        </x-filament::section>

        <x-filament::section heading="Consommation (30 jours)">
            <dl class="fk-ba-dl">
                <div><dt>Tokens</dt><dd>{{ number_format($stats['tokens_30d'], 0, ',', ' ') }}</dd></div>
                <div><dt>Coût estimé</dt><dd>{{ $pricing ? '$' . number_format((float) $stats['cost_30d'], 2) : 'Non configuré' }}</dd></div>
            </dl>
            @unless ($pricing)
                <p class="fk-ba-muted">Renseignez BLOG_AI_PRICE_INPUT_PER_M et BLOG_AI_PRICE_OUTPUT_PER_M (USD par million de tokens, tarif Google en vigueur) pour afficher une estimation.</p>
            @endunless
        </x-filament::section>
    </div>

    <div class="fk-ba-cards">
        @foreach ($cards as [$label, $value, $color])
            <x-filament::section>
                <div class="fk-ba-muted">{{ $label }}</div>
                <div @class(['fk-ba-num', 'fk-ba-' . $color => $color])>{{ $value }}</div>
            </x-filament::section>
        @endforeach
    </div>

    @if ($last && $last->status === 'failed' && $last->error_message)
        <x-filament::section heading="Dernière erreur">
            <p class="fk-ba-danger" style="font-size:.875rem">{{ $last->error_message }}</p>
        </x-filament::section>
    @endif

    <form wire:submit="save">
        {{ $this->form }}

        <div class="fk-ba-actions">
            <x-filament::button type="submit">Enregistrer les réglages</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
