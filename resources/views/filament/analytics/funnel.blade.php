<x-filament-widgets::widget>
    <x-filament::section heading="Tunnel d'inscription" description="Visiteurs uniques (estimés, par jour) à chaque étape ; inscriptions confirmées par le serveur.">
        @include('filament.analytics._styles')
        @php $top = max(1, $rows[0]['value'] ?? 0); @endphp
        <table class="fk-an-table">
            <tbody>
                @foreach ($rows as $i => $step)
                    <tr>
                        <td>{{ $step['label'] }}
                            <div class="fk-an-bar"><span style="width: {{ min(100, round($step['value'] / $top * 100, 1)) }}%"></span></div></td>
                        <td class="num">{{ number_format($step['value'], 0, ',', ' ') }}</td>
                        <td class="num fk-an-muted">{{ $i === 0 ? '' : number_format($step['value'] / $top * 100, 1, ',', ' ') . ' %' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="fk-an-muted" style="margin-top:.75rem">Les inscriptions sont comptées depuis l'activation de la mesure d'audience. Un visiteur qui change d'appareil ou revient un autre jour compte à nouveau : le taux de conversion est une estimation.</p>
    </x-filament::section>
</x-filament-widgets::widget>
