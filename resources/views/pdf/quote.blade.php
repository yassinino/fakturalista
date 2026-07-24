<?php
use Carbon\Carbon;

// ── Locale & formatting ────────────────────────────────────────────────────
$locale           = app()->getLocale() ?: config('app.locale', 'es');
$supportedLocales = config('app.supported_locales', ['es', 'fr', 'en']);
if (!in_array($locale, $supportedLocales, true)) {
    $locale = config('app.locale', 'es');
}

[$decimalSep, $thousandsSep, $dateFmt] = match ($locale) {
    'en'    => ['.', ',', 'm/d/Y'],
    'fr'    => [',', ' ', 'd/m/Y'],
    default => [',', '.', 'd-m-Y'],
};

$formatMoney = fn($v) => number_format((float) $v, 2, $decimalSep, $thousandsSep) . ' €';
$formatQty   = fn($v) => number_format((float) $v, 2, $decimalSep, $thousandsSep);

// ── Dates ─────────────────────────────────────────────────────────────────
$quoteDate  = $quote->date            ? Carbon::parse($quote->date)->format($dateFmt)            : '-';
$expiryDate = $quote->expiration_date ? Carbon::parse($quote->expiration_date)->format($dateFmt) : '-';

// ── Brand color ────────────────────────────────────────────────────────────
$primary = !empty($company?->brand_color) ? $company->brand_color : '#E91E63';

// ── Status badge ───────────────────────────────────────────────────────────
$statusLabels = match ($locale) {
    'fr'    => ['draft' => 'Brouillon', 'sent' => 'Envoyé',  'converted' => 'Converti',  'cancelled' => 'Annulé'],
    'es'    => ['draft' => 'Borrador',  'sent' => 'Enviado', 'converted' => 'Convertido', 'cancelled' => 'Cancelado'],
    default => ['draft' => 'Draft',     'sent' => 'Sent',    'converted' => 'Converted',  'cancelled' => 'Cancelled'],
};
$statusColors = ['draft' => '#6b7280', 'sent' => '#0284c7', 'converted' => '#16a34a', 'cancelled' => '#dc2626'];
$statusLabel  = $statusLabels[$quote->status ?? 'draft'] ?? ucfirst($quote->status ?? 'draft');
$statusColor  = $statusColors[$quote->status  ?? 'draft'] ?? '#6b7280';

// ── Company ────────────────────────────────────────────────────────────────
$companyName = $company?->trade_name ?: ($company?->legal_name ?: config('app.name'));
$addressParts = array_filter([
    $company?->address_line1,
    $company?->address_line2,
    trim(implode(' ', array_filter([$company?->postal_code, $company?->city]))),
    $company?->country,
]);
$companyAddress = implode("\n", $addressParts);

// ── Tax breakdown from cart lines ──────────────────────────────────────────
$taxGroups = [];
foreach ($quote->carts as $cart) {
    $rate = (int) ($cart->vta ?? 0);
    if ($rate <= 0) continue;
    $lineBase = (float) $cart->qty * (float) $cart->price;
    $taxGroups[$rate] = ($taxGroups[$rate] ?? 0) + round($lineBase * $rate / 100, 2);
}
ksort($taxGroups);

$discountAmount = (float) ($quote->discount_amount ?? 0);
$subTotal       = (float) ($quote->sub_total ?? 0);
$totalTax       = array_sum($taxGroups);
$grandTotal     = (float) ($quote->total ?? 0);
?>
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
            font-size: 13px;
            line-height: 1.55;
            font-family: Arial, sans-serif;
            color: #1f1c1a;
            background: #ffffff;
        }

        /* ── Header ── */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
        .header-table td { vertical-align: top; padding: 0; }
        .col-company  { width: 55%; padding-right: 24px; }
        .col-meta     { width: 45%; text-align: right; }

        .logo-img { max-height: 64px; max-width: 180px; margin-bottom: 10px; display: block; }
        .company-name {
            font-size: 16px;
            font-weight: 800;
            color: {{ $primary }};
            margin-bottom: 4px;
        }
        .company-detail { font-size: 11.5px; color: #6b6764; line-height: 1.65; }

        .doc-label {
            font-size: 26px;
            font-weight: 900;
            color: {{ $primary }};
            letter-spacing: 0.04em;
            text-transform: uppercase;
            line-height: 1;
            margin-bottom: 6px;
        }
        .doc-number {
            font-size: 14px;
            font-weight: 700;
            color: #1f1c1a;
            margin-bottom: 12px;
        }
        .meta-row { display: table; width: 100%; margin-bottom: 3px; }
        .meta-key {
            display: table-cell;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #9ca3af;
            padding-right: 8px;
            width: 50%;
        }
        .meta-val {
            display: table-cell;
            text-align: right;
            font-size: 12px;
            font-weight: 600;
            color: #1f1c1a;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: {{ $statusColor }}22;
            color: {{ $statusColor }};
            border: 1px solid {{ $statusColor }}55;
        }

        /* ── Divider ── */
        .divider { border: none; border-top: 2px solid {{ $primary }}; margin: 18px 0; }
        .divider-light { border: none; border-top: 1px solid #e5e0db; margin: 14px 0; }

        /* ── Client section ── */
        .bill-to-label {
            font-size: 9.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #9ca3af;
            margin-bottom: 5px;
        }
        .client-name  { font-size: 14px; font-weight: 800; color: #1f1c1a; }
        .client-sub   { font-size: 12px; color: #6b6764; line-height: 1.65; }

        /* ── Items table ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .items-table th {
            background: {{ $primary }};
            color: #ffffff;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 10px 10px;
            border: none;
        }
        .items-table th.text-right { text-align: right; }
        .items-table th.text-center { text-align: center; }
        .items-table td {
            padding: 10px 10px;
            border-bottom: 1px solid #f0ece9;
            vertical-align: top;
            font-size: 12.5px;
            color: #1f1c1a;
        }
        .items-table td.text-right  { text-align: right; }
        .items-table td.text-center { text-align: center; }
        .item-name { font-weight: 700; }
        .item-desc { font-size: 11px; color: #6b6764; margin-top: 2px; }
        .item-tax  { font-size: 11.5px; color: #6b6764; }

        /* ── Totals ── */
        .totals-wrapper { display: flex; justify-content: flex-end; margin-top: 8px; }
        .totals-table {
            border-collapse: collapse;
            min-width: 300px;
        }
        .totals-table td { padding: 5px 10px; font-size: 12.5px; }
        .totals-table td:first-child {
            text-align: right;
            color: #6b6764;
            font-weight: 600;
            padding-right: 24px;
        }
        .totals-table td:last-child { text-align: right; font-weight: 600; color: #1f1c1a; }
        .total-final td { border-top: 2px solid #1f1c1a; padding-top: 8px; }
        .total-final td:first-child { font-size: 13px; font-weight: 800; color: #1f1c1a; }
        .total-final td:last-child  { font-size: 16px; font-weight: 900; color: {{ $primary }}; }

        /* ── Notes footer ── */
        .notes-section { margin-top: 28px; }
        .notes-title {
            font-size: 9.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #9ca3af;
            margin-bottom: 6px;
        }
        .notes-body {
            font-size: 12px;
            color: #6b6764;
            line-height: 1.7;
            white-space: pre-line;
        }

        /* ── Validity footer ── */
        .validity-bar {
            margin-top: 24px;
            background: {{ $primary }}11;
            border-left: 3px solid {{ $primary }};
            padding: 10px 14px;
            font-size: 11.5px;
            color: #374151;
            border-radius: 0 4px 4px 0;
        }
    </style>
</head>
<body>

    {{-- ══════════════════════════════════════
         HEADER: Company (left) + Quote meta (right)
         ══════════════════════════════════════ --}}
    <table class="header-table">
        <tr>
            <td class="col-company">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" class="logo-img" alt="{{ $companyName }}">
                @endif
                <div class="company-name">{{ $companyName }}</div>
                @if($companyAddress)
                    <div class="company-detail" style="white-space: pre-line;">{{ $companyAddress }}</div>
                @endif
                @if($company?->phone)
                    <div class="company-detail">{{ __('quote.phone', ['phone' => $company->phone]) }}</div>
                @endif
                @if($company?->email)
                    <div class="company-detail">{{ __('quote.email', ['email' => $company->email]) }}</div>
                @endif
            </td>
            <td class="col-meta">
                <div class="doc-label">{{ match($locale) { 'fr' => 'Devis', 'es' => 'Presupuesto', default => 'Quote' } }}</div>
                <div class="doc-number">{{ __('quote.title', ['number' => $quote->reference]) }}</div>

                <div class="meta-row">
                    <span class="meta-key">{{ __('quote.quote_date') }}</span>
                    <span class="meta-val">{{ $quoteDate }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-key">{{ __('quote.expiry_date') }}</span>
                    <span class="meta-val">{{ $expiryDate }}</span>
                </div>
                <div class="meta-row" style="margin-top: 8px;">
                    <span class="meta-key">{{ __('quote.status') }}</span>
                    <span class="meta-val">
                        <span class="status-badge">{{ $statusLabel }}</span>
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <hr class="divider">

    {{-- ══════════════════════════════════════
         CLIENT SECTION
         ══════════════════════════════════════ --}}
    <div class="bill-to-label">{{ __('quote.bill_to') }}</div>
    <div class="client-name">{{ $quote->customer?->name ?? '-' }}</div>
    @if($quote->customer?->company_name)
        <div class="client-sub" style="font-weight: 600;">{{ $quote->customer->company_name }}</div>
    @endif
    @if($quote->customer?->address_billing)
        <div class="client-sub" style="white-space: pre-line;">{{ $quote->customer->address_billing }}</div>
    @endif
    @if($quote->customer?->email)
        <div class="client-sub">{{ $quote->customer->email }}</div>
    @endif
    @if($quote->customer?->phone)
        <div class="client-sub">{{ $quote->customer->phone }}</div>
    @endif

    {{-- ══════════════════════════════════════
         ITEMS TABLE
         ══════════════════════════════════════ --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:44%; text-align:left;">{{ __('quote.item_description') }}</th>
                <th class="text-center" style="width:10%;">{{ __('quote.quantity') }}</th>
                <th class="text-center" style="width:14%;">{{ __('quote.unit_price') }}</th>
                <th class="text-center" style="width:10%;">{{ __('quote.tax') }}</th>
                <th class="text-right"  style="width:22%;">{{ __('quote.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->carts as $cart)
                <tr>
                    <td>
                        @if(!empty($cart->product?->name))
                            <div class="item-name">{{ $cart->product->name }}</div>
                        @endif
                        @if(!empty($cart->description))
                            <div class="item-desc">{!! nl2br(e($cart->description)) !!}</div>
                        @endif
                        @if(empty($cart->product?->name) && empty($cart->description))
                            <div class="item-name" style="color:#9ca3af;">-</div>
                        @endif
                    </td>
                    <td class="text-center">{{ $formatQty($cart->qty) }}</td>
                    <td class="text-center">{{ $formatMoney($cart->price) }}</td>
                    <td class="text-center item-tax">{{ $cart->vta ?? 0 }}%</td>
                    <td class="text-right">{{ $formatMoney($cart->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ══════════════════════════════════════
         TOTALS
         ══════════════════════════════════════ --}}
    <div class="totals-wrapper">
        <table class="totals-table">
            <tbody>
                <tr>
                    <td>{{ __('quote.subtotal') }}</td>
                    <td>{{ $formatMoney($subTotal) }}</td>
                </tr>

                @foreach($taxGroups as $rate => $taxAmount)
                    <tr>
                        <td>{{ __('quote.tax_line', ['rate' => $rate]) }}</td>
                        <td>{{ $formatMoney($taxAmount) }}</td>
                    </tr>
                @endforeach

                @if($discountAmount > 0)
                    <tr>
                        <td>{{ __('quote.discount') }}</td>
                        <td>- {{ $formatMoney($discountAmount) }}</td>
                    </tr>
                @endif

                <tr class="total-final">
                    <td>{{ __('quote.total') }}</td>
                    <td>{{ $formatMoney($grandTotal) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ══════════════════════════════════════
         VALIDITY NOTICE
         ══════════════════════════════════════ --}}
    @if($quote->expiration_date)
        <div class="validity-bar">
            {{ __('quote.valid_through', ['date' => $expiryDate]) }}
        </div>
    @endif

    {{-- ══════════════════════════════════════
         NOTES
         ══════════════════════════════════════ --}}
    @if(!empty($quote->note))
        <div class="notes-section">
            <hr class="divider-light">
            <div class="notes-title">{{ __('quote.notes') }}</div>
            <div class="notes-body">{!! nl2br(e($quote->note)) !!}</div>
        </div>
    @endif

</body>
</html>
