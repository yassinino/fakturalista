<x-filament-widgets::widget>
    <x-filament::section heading="Pages les plus visitées" :description="'Pages publiques, par version linguistique — ' . $period->label()">
        @include('filament.analytics._styles')
        @if (empty($rows))
            <div class="fk-an-empty">Aucune page vue sur la période.</div>
        @else
            <div style="overflow-x:auto">
            <table class="fk-an-table">
                <thead><tr><th>Page</th><th class="num">Vues</th><th class="num">Visiteurs (est.)</th><th>Principaux pays</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>
                                <div style="font-weight:600">{{ \Illuminate\Support\Str::limit(preg_replace('/\s*[—-]\s*Fakturalista$/u', '', $row['title'] ?: $row['path']), 70) }}<span class="fk-an-badge">{{ strtoupper($row['locale'] ?? '?') }}</span></div>
                                <div class="fk-an-muted">{{ $row['path'] }}</div>
                            </td>
                            <td class="num">{{ number_format($row['views'], 0, ',', ' ') }}</td>
                            <td class="num">{{ number_format($row['visitors'], 0, ',', ' ') }}</td>
                            <td class="fk-an-muted">{{ $row['countries'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
