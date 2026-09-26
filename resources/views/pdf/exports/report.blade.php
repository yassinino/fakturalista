<!DOCTYPE html>
<html lang="{{ $isRtl ? 'ar' : 'en' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 100px 32px 60px; }

        * { box-sizing: border-box; }
        body {
            font-family: {{ $isRtl ? "'DejaVu Sans', Arial, sans-serif" : "Arial, sans-serif" }};
            color: #1f1c1a;
            font-size: 11px;
            direction: {{ $isRtl ? 'rtl' : 'ltr' }};
        }
        h2 {
            font-size: 12.5px;
            font-weight: bold;
            color: #E91E63;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 22px 0 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #f0ece7;
        }

        /* ── Header / footer (repeat on every page) ──────────────── */
        header {
            position: fixed;
            top: -80px;
            left: 0; right: 0;
            height: 70px;
            border-bottom: 2px solid #E91E63;
            padding-bottom: 10px;
        }
        .hdr-table { width: 100%; }
        .hdr-logo { max-height: 42px; max-width: 160px; }
        .hdr-company { font-size: 14px; font-weight: bold; }
        .hdr-title { font-size: 18px; font-weight: bold; color: #E91E63; text-align: {{ $isRtl ? 'left' : 'right' }}; }
        .hdr-period { font-size: 10.5px; color: #6b6764; text-align: {{ $isRtl ? 'left' : 'right' }}; margin-top: 3px; }

        footer {
            position: fixed;
            bottom: -50px;
            left: 0; right: 0;
            height: 40px;
            border-top: 1px solid #e5e0db;
            padding-top: 6px;
            font-size: 9px;
            color: #9a958f;
        }
        .ftr-table { width: 100%; }
        .ftr-page:after { content: "{{ $labels['page'] }} " counter(page) " {{ $labels['of'] }} " counter(pages); }

        /* ── KPI grid ─────────────────────────────────────────────── */
        table.kpi { width: 100%; border-collapse: separate; border-spacing: 6px; margin-bottom: 4px; }
        table.kpi td {
            width: 25%;
            background: #fbf5f7;
            border: 1px solid #f3d9e2;
            border-radius: 4px;
            padding: 10px 12px;
            vertical-align: top;
        }
        .kpi-label { font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.4px; color: #9a6b7c; margin: 0 0 4px; }
        .kpi-value { font-size: 15px; font-weight: bold; color: #1f1c1a; margin: 0; }

        /* ── Data tables ──────────────────────────────────────────── */
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.data th {
            background: #E91E63;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            text-align: {{ $isRtl ? 'right' : 'left' }};
            padding: 6px 7px;
        }
        table.data td {
            font-size: 9.5px;
            padding: 5px 7px;
            border-bottom: 1px solid #e5e0db;
            text-align: {{ $isRtl ? 'right' : 'left' }};
        }
        table.data tr:nth-child(even) td { background: #faf9f7; }
        .num { text-align: {{ $isRtl ? 'left' : 'right' }} !important; }

        .two-col { width: 100%; }
        .two-col td { width: 50%; vertical-align: top; padding: 0 6px; }
        .two-col td:first-child { padding-{{ $isRtl ? 'left' : 'right' }}: 6px; padding-{{ $isRtl ? 'right' : 'left' }}: 0; }
    </style>
</head>
<body>

    <header>
        <table class="hdr-table">
            <tr>
                <td style="width: 50%;">
                    @if ($logoSrc)
                        <img src="{{ $logoSrc }}" class="hdr-logo" alt="{{ $companyName }}">
                    @else
                        <span class="hdr-company">{{ $companyName }}</span>
                    @endif
                </td>
                <td style="width: 50%;">
                    <div class="hdr-title">{{ $labels['report_title'] }}</div>
                    <div class="hdr-period">{{ $labels['period'] }}: {{ $periodLabel }}</div>
                </td>
            </tr>
        </table>
    </header>

    <footer>
        <table class="ftr-table">
            <tr>
                <td style="width: 50%;">{{ $labels['generated_on'] }} {{ $generatedAt->format('d/m/Y H:i') }}</td>
                <td style="width: 50%; text-align: {{ $isRtl ? 'left' : 'right' }};" class="ftr-page"></td>
            </tr>
        </table>
    </footer>

    {{-- ── KPI grid ─────────────────────────────────────────────── --}}
    <table class="kpi">
        <tr>
            <td>
                <p class="kpi-label">{{ $labels['revenue'] }}</p>
                <p class="kpi-value">{{ $formatMoney($summary['kpis']['revenue']['value']) }}</p>
            </td>
            <td>
                <p class="kpi-label">{{ $labels['collected'] }}</p>
                <p class="kpi-value">{{ $formatMoney($summary['kpis']['collected']['value']) }}</p>
            </td>
            <td>
                <p class="kpi-label">{{ $labels['outstanding'] }}</p>
                <p class="kpi-value">{{ $formatMoney($summary['kpis']['outstanding']['value']) }}</p>
            </td>
            <td>
                <p class="kpi-label">{{ $labels['invoices_count'] }}</p>
                <p class="kpi-value">{{ $summary['kpis']['invoices_count']['value'] }}</p>
            </td>
        </tr>
        <tr>
            <td>
                <p class="kpi-label">{{ $labels['avg_invoice_value'] }}</p>
                <p class="kpi-value">{{ $formatMoney($summary['performance']['avg_invoice_value']) }}</p>
            </td>
            <td>
                <p class="kpi-label">{{ $labels['collection_rate'] }}</p>
                <p class="kpi-value">{{ $summary['performance']['collection_rate'] }}%</p>
            </td>
            <td>
                <p class="kpi-label">{{ $labels['unpaid_amount'] }}</p>
                <p class="kpi-value">{{ $formatMoney($summary['performance']['unpaid_amount']) }}</p>
            </td>
            <td>
                <p class="kpi-label">{{ $labels['clients_invoiced'] }}</p>
                <p class="kpi-value">{{ $summary['performance']['clients_invoiced'] }}</p>
            </td>
        </tr>
    </table>

    {{-- ── Status + payment method breakdown, side by side ─────────── --}}
    <h2>{{ $labels['status_breakdown'] }} / {{ $labels['method_breakdown'] }}</h2>
    <table class="two-col">
        <tr>
            <td>
                <table class="data">
                    <thead><tr><th>{{ $labels['col_status'] }}</th><th class="num">{{ $labels['count'] }}</th><th class="num">{{ $labels['percent'] }}</th></tr></thead>
                    <tbody>
                        @foreach ($summary['invoice_status'] as $row)
                            <tr>
                                <td>{{ ucfirst($row['status']) }}</td>
                                <td class="num">{{ $row['count'] }}</td>
                                <td class="num">{{ $row['percent'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td>
                <table class="data">
                    <thead><tr><th>{{ $labels['col_paid_via'] }}</th><th class="num">{{ $labels['amount'] }}</th><th class="num">{{ $labels['percent'] }}</th></tr></thead>
                    <tbody>
                        @forelse ($summary['payment_methods'] as $row)
                            <tr>
                                <td>{{ ucfirst(str_replace('_', ' ', $row['method'])) }}</td>
                                <td class="num">{{ $formatMoney($row['total']) }}</td>
                                <td class="num">{{ $row['percent'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">{{ $labels['no_data'] }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    {{-- ── Top clients ──────────────────────────────────────────────── --}}
    <h2>{{ $labels['top_clients'] }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ $labels['col_customer'] }}</th>
                <th class="num">{{ $labels['col_invoices_count'] }}</th>
                <th class="num">{{ $labels['col_invoiced'] }}</th>
                <th class="num">{{ $labels['col_collected'] }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($clients as $c)
                <tr>
                    <td>{{ $c['client'] }}</td>
                    <td class="num">{{ $c['invoices_count'] }}</td>
                    <td class="num">{{ $formatMoney($c['invoiced']) }}</td>
                    <td class="num">{{ $formatMoney($c['collected']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4">{{ $labels['no_data'] }}</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Revenue evolution summary ────────────────────────────────── --}}
    <h2>{{ $labels['revenue_evolution'] }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ $labels['period_bucket'] }}</th>
                <th class="num">{{ $labels['col_invoiced'] }}</th>
                <th class="num">{{ $labels['col_collected'] }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary['chart']['labels'] as $i => $bucketLabel)
                <tr>
                    <td>{{ $bucketLabel }}</td>
                    <td class="num">{{ $formatMoney($summary['chart']['invoiced'][$i] ?? 0) }}</td>
                    <td class="num">{{ $formatMoney($summary['chart']['collected'][$i] ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>
