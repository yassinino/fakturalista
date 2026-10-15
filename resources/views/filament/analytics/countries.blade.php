<x-filament-widgets::widget>
    <x-filament::section heading="Pays des visiteurs" description="Visites par pays (géolocalisation locale de l'adresse IP, jamais conservée).">
        @include('filament.analytics._styles')
        @if (empty($rows))
            <div class="fk-an-empty">Aucune visite sur la période.</div>
        @else
            <table class="fk-an-table">
                <thead><tr><th>Pays</th><th class="num">Visites</th><th class="num">Part</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['name'] }} <span class="fk-an-badge">{{ $row['code'] ?? '—' }}</span>
                                <div class="fk-an-bar"><span style="width: {{ $row['percent'] }}%"></span></div></td>
                            <td class="num">{{ number_format($row['visits'], 0, ',', ' ') }}</td>
                            <td class="num">{{ number_format($row['percent'], 1, ',', ' ') }} %</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
