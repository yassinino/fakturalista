<x-filament-widgets::widget>
    <x-filament::section heading="Référents et campagnes" description="Origine des visites (première page de chaque visite).">
        @include('filament.analytics._styles')
        <div class="fk-an-grid">
            <div>
                <div class="fk-an-h">Principaux référents</div>
                @if (empty($rows))
                    <div class="fk-an-empty">Aucun référent sur la période.</div>
                @else
                    <table class="fk-an-table">
                        <thead><tr><th>Source</th><th>Type</th><th class="num">Visites</th></tr></thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $row['name'] ?: '—' }}</td>
                                    <td class="fk-an-muted">{{ \App\Services\Analytics\AnalyticsReport::SOURCE_LABELS[$row['source']] ?? $row['source'] }}</td>
                                    <td class="num">{{ number_format($row['visits'], 0, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div>
                <div class="fk-an-h">Campagnes (UTM)</div>
                @if (empty($campaigns))
                    <div class="fk-an-empty">Aucune campagne. Ajoutez utm_source, utm_medium et utm_campaign à vos liens.</div>
                @else
                    <table class="fk-an-table">
                        <thead><tr><th>Campagne</th><th>Source / support</th><th class="num">Visites</th></tr></thead>
                        <tbody>
                            @foreach ($campaigns as $c)
                                <tr>
                                    <td>{{ $c['utm_campaign'] ?: '(sans nom)' }}</td>
                                    <td class="fk-an-muted">{{ $c['utm_source'] ?: '—' }} / {{ $c['utm_medium'] ?: '—' }}</td>
                                    <td class="num">{{ number_format($c['visits'], 0, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
