<!DOCTYPE html>
<html lang="{{ $isRtl ? 'ar' : 'en' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 90px 32px 60px; }

        * { box-sizing: border-box; }
        body {
            font-family: {{ $isRtl ? "'DejaVu Sans', Arial, sans-serif" : "Arial, sans-serif" }};
            color: #1f1c1a;
            font-size: 11px;
            direction: {{ $isRtl ? 'rtl' : 'ltr' }};
        }

        /* ── Header (repeats on every page) ─────────────────────── */
        header {
            position: fixed;
            top: -70px;
            left: 0;
            right: 0;
            height: 60px;
            border-bottom: 2px solid #E91E63;
            padding-bottom: 10px;
        }
        .hdr-table { width: 100%; }
        .hdr-logo { max-height: 42px; max-width: 160px; }
        .hdr-company { font-size: 14px; font-weight: bold; color: #1f1c1a; }
        .hdr-title { font-size: 16px; font-weight: bold; color: #E91E63; text-align: {{ $isRtl ? 'left' : 'right' }}; }
        .hdr-subtitle { font-size: 10px; color: #6b6764; text-align: {{ $isRtl ? 'left' : 'right' }}; margin-top: 3px; }

        /* ── Footer (repeats on every page) ──────────────────────── */
        footer {
            position: fixed;
            bottom: -50px;
            left: 0;
            right: 0;
            height: 40px;
            border-top: 1px solid #e5e0db;
            padding-top: 6px;
            font-size: 9px;
            color: #9a958f;
        }
        .ftr-table { width: 100%; }
        .ftr-page:after { content: "{{ $labels['page'] }} " counter(page) " {{ $labels['of'] }} " counter(pages); }

        /* ── Table ────────────────────────────────────────────────── */
        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data th {
            background: #E91E63;
            color: #ffffff;
            font-size: 9.5px;
            font-weight: bold;
            text-align: {{ $isRtl ? 'right' : 'left' }};
            padding: 7px 8px;
            border: 1px solid #E91E63;
        }
        table.data td {
            font-size: 10px;
            padding: 6px 8px;
            border: 1px solid #e5e0db;
            text-align: {{ $isRtl ? 'right' : 'left' }};
        }
        table.data tr:nth-child(even) td { background: #faf9f7; }

        .no-data { text-align: center; color: #9a958f; padding: 40px 0; font-size: 11px; }
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
                    <div class="hdr-title">{{ $title }}</div>
                    @if ($subtitle)
                        <div class="hdr-subtitle">{{ $subtitle }}</div>
                    @endif
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

    @if (empty($rows))
        <p class="no-data">{{ $labels['no_data'] }}</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    @foreach ($headings as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</body>
</html>
