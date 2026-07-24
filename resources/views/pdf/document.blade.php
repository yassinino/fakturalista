{{-- All variables are pre-computed by TemplateRendererService::render() - no DB queries here. --}}
<!doctype html>
<html lang="{{ $locale }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            max-width: 900px;
            margin: auto;
            padding: 36px 32px 40px;
            font-size: {{ $fontSize }}px;
            line-height: 1.55;
            font-family: {{ $design['font_family'] }};
            color: {{ $design['text'] }};
            background: #ffffff;
        }
        .muted { color: {{ $design['muted'] }}; }

        /* Header */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
        .header-table td { vertical-align: top; padding: 0; }
        .col-company { width: 55%; padding-right: 24px; }
        .col-meta    { width: 45%; text-align: right; }

        .logo-img { max-height: 64px; max-width: {{ (int) $logoWidthPx }}px; margin-bottom: 10px; display: block; }
        .company-name   { font-size: {{ $fontSize + 2 }}px; font-weight: 800; color: {{ $design['primary'] }}; margin-bottom: 4px; }
        .company-detail { font-size: {{ $fontSize - 2 }}px; color: {{ $design['muted'] }}; line-height: 1.65; }

        .doc-label  { font-size: 26px; font-weight: 900; color: {{ $design['primary'] }}; letter-spacing: 0.04em; text-transform: uppercase; line-height: 1; margin-bottom: 6px; }
        .doc-number { font-size: {{ $fontSize }}px; font-weight: 700; color: {{ $design['text'] }}; margin-bottom: 12px; }
        .meta-row { display: table; width: 100%; margin-bottom: 3px; }
        .meta-key { display: table-cell; text-align: left; font-size: {{ $fontSize - 3 }}px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #9ca3af; padding-right: 8px; width: 50%; }
        .meta-val { display: table-cell; text-align: right; font-size: {{ $fontSize - 2 }}px; font-weight: 600; color: {{ $design['text'] }}; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: {{ $fontSize - 3 }}px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

        /* Dividers */
        .divider       { border: none; border-top: 2px solid {{ $design['primary'] }}; margin: 18px 0; }
        .divider-light { border: none; border-top: 1px solid {{ $design['table_border'] }}; margin: 14px 0; }

        /* Addresses */
        .addresses-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .addresses-table td { vertical-align: top; padding: 0; }
        .addr-label { font-size: {{ $fontSize - 4 }}px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #9ca3af; margin-bottom: 5px; }
        .addr-name  { font-size: {{ $fontSize }}px; font-weight: 800; color: {{ $design['text'] }}; }
        .addr-sub   { font-size: {{ $fontSize - 2 }}px; color: {{ $design['muted'] }}; line-height: 1.65; }

        /* Items table */
        .items-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .items-table th {
            background: {{ $design['table_header_bg'] }};
            color: {{ $design['table_header_text'] }};
            font-size: {{ $fontSize - 3 }}px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 10px;
            border-top: 1px solid {{ $design['table_border'] }};
            border-bottom: 1px solid {{ $design['table_border'] }};
        }
        .items-table th.text-right  { text-align: right; }
        .items-table th.text-center { text-align: center; }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid {{ $design['table_border'] }};
            vertical-align: top;
            font-size: {{ $fontSize - 2 }}px;
            color: {{ $design['text'] }};
        }
        .items-table td.text-right  { text-align: right; }
        .items-table td.text-center { text-align: center; }
        .item-name { font-weight: 700; }
        .item-desc { font-size: {{ $fontSize - 3 }}px; color: {{ $design['muted'] }}; margin-top: 2px; }

        /* Totals - table-based right-alignment (DomPDF has no flexbox support) */
        .totals-table { border-collapse: collapse; width: 100%; }
        .totals-table td { padding: 5px 10px; font-size: {{ $fontSize - 2 }}px; }
        .totals-table td:first-child { text-align: right; color: {{ $design['muted'] }}; font-weight: 600; padding-right: 24px; }
        .totals-table td:last-child  { text-align: right; font-weight: 600; color: {{ $design['text'] }}; }
        .total-final td { border-top: 2px solid {{ $design['text'] }}; padding-top: 8px; }
        .total-final td:first-child { font-size: {{ $fontSize }}px; font-weight: {{ ($design['bold_total'] ?? true) ? '800' : '500' }}; color: {{ $design['text'] }}; }
        .total-final td:last-child  { font-size: {{ $fontSize + 2 }}px; font-weight: 900; color: {{ $design['primary'] }}; }

        /* Footer */
        .validity-bar { margin-top: 24px; border-left: 3px solid {{ $design['primary'] }}; padding: 10px 14px; font-size: {{ $fontSize - 2 }}px; color: #374151; }
        .notes-section { margin-top: 28px; }
        .notes-title { font-size: {{ $fontSize - 4 }}px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #9ca3af; margin-bottom: 6px; }
        .notes-body  { font-size: {{ $fontSize - 2 }}px; color: {{ $design['muted'] }}; line-height: 1.7; white-space: pre-line; }
        .payment-terms { display: flex; justify-content: center; gap: 28px; color: {{ $design['muted'] }}; font-weight: 700; margin-top: 12px; }
        .stripe-section { text-align: center; padding: 14px 0; }
        .stripe-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 8px; font-weight: 700; color: {{ $design['muted'] }}; }
        .stripe-url   { font-size: 10px; margin-top: 8px; word-break: break-all; color: {{ $design['muted'] }}; }
    </style>
</head>
<body>
    @include('pdf.components._header')
    @include('pdf.components._addresses')
    @include('pdf.components._items')
    @include('pdf.components._totals')
    @include('pdf.components._footer')
</body>
</html>
