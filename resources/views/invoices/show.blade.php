<?php
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\InvoiceTemplate;
use App\Models\CompanyProfile;

$locale = app()->getLocale() ?: config('app.locale', 'es');
$supportedLocales = config('app.supported_locales', ['es', 'fr', 'en']);
if (! in_array($locale, $supportedLocales, true)) {
    $locale = config('app.locale', 'es');
}

[$decimalSeparator, $thousandsSeparator, $dateFormat] = match ($locale) {
    'en' => ['.', ',', 'm/d/Y'],
    'fr' => [',', ' ', 'd/m/Y'],
    default => [',', '.', 'd-m-Y'],
};

$formatNumber = fn($value, $decimals = 2) => number_format((float) $value, $decimals, $decimalSeparator, $thousandsSeparator);
$formatMoney = fn($value) => $formatNumber($value, 2) . ' €';

$date_invoice = $invoice->date
    ? Carbon::parse($invoice->date)->format($dateFormat)
    : Carbon::now()->format($dateFormat);

$defaults = [
    'primary' => '#dad7d2',
    'text' => '#1f1c1a',
    'muted' => '#6b6764',
    'table_header_bg' => '#dedad5',
    'table_header_text' => '#4a4745',
    'table_border' => '#cfcac5',
    'font_family' => 'Arial, sans-serif',
    'font_size' => 'medium',
    'logo_width_mm' => 50,
    'logo_position' => 'above', // above|left
    'show_payment_terms' => true,
    'show_customer_number' => false,
    'show_customer_phone' => false,
    'show_shipping_address' => true,
    'billing_address_right' => false,
    'show_discount' => false,
    'show_tax_column' => true,
    'show_subtotal' => true,
    'show_tax_breakdown' => true,
    'bold_total' => true,
    'payment_note' => '',
    'show_payment_note' => true,
];

$template = InvoiceTemplate::where('is_active', true)
    ->orderByDesc('is_default')
    ->latest('created_at')
    ->first();

$settings = $template?->settings ?? [];

$design = $defaults;
foreach ($settings as $key => $val) {
    if (array_key_exists($key, $design)) {
        $design[$key] = $val;
    }
}

if ($template) {
    foreach ($defaults as $key => $defaultVal) {
        if ($template->$key !== null) {
            $design[$key] = $template->$key;
        }
    }
}

$fontSize = match($design['font_size'] ?? 'medium') {
    'small' => 13,
    'large' => 16,
    default => 14,
};

$mmToPx = fn($mm) => $mm ? $mm * 3.78 : 0;
$logoWidthPx = $mmToPx($design['logo_width_mm'] ?? 50);
$isLogoAbove = ($design['logo_position'] ?? 'above') === 'above';

// Logo: template first, fallback to company
$company = CompanyProfile::first();
$logoPath = $design['logo_path'] ?? null;
if (empty($logoPath) && $company && $company->logo_path) {
    $logoPath = $company->logo_path;
}

$logoSrc = null;
if (!empty($logoPath)) {
    $logoDisk = Storage::disk('public');
    $candidate = $logoPath;

    if (preg_match('~^https?://~i', $candidate)) {
        $candidate = parse_url($candidate, PHP_URL_PATH) ?? '';
    }

    if (!empty($candidate) && is_file($candidate)) {
        $logoSrc = 'file://' . $candidate;
    } else {
        $candidate = ltrim($candidate, '/');
        if (str_starts_with($candidate, 'storage/')) {
            $candidate = substr($candidate, strlen('storage/'));
        }
        if (!empty($candidate) && $logoDisk->exists($candidate)) {
            $logoSrc = 'file://' . $logoDisk->path($candidate);
        }
    }
}

?>

<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        :root {
            --primary: {{ $design['primary'] }};
            --text: {{ $design['text'] }};
            --muted: {{ $design['muted'] }};
            --table-header-bg: {{ $design['table_header_bg'] }};
            --table-header-text: {{ $design['table_header_text'] }};
            --table-border: {{ $design['table_border'] }};
            --font-family: {{ $design['font_family'] }};
            --font-size: {{ $fontSize }}px;
        }
        body {
            max-width: 900px;
            margin: auto;
            padding: 30px 12px 24px;
            font-size: var(--font-size);
            line-height: 24px;
            font-family: var(--font-family);
            color: var(--text);
            background: #ffffff;
        }
        .muted { color: var(--muted); }
        .header-row {
            display: flex;
            align-items: {{ $isLogoAbove ? 'flex-start' : 'center' }};
            gap: 12px;
            flex-direction: {{ $isLogoAbove ? 'column' : 'row' }};
            margin-bottom: 10px;
        }
        .logo img { max-width: 100%; height: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; }
        th {
            background: var(--table-header-bg);
            color: var(--table-header-text);
            border-bottom: 1px solid var(--table-border);
            text-align: left;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        td {
            
            vertical-align: top;
        }

        .rows td{
            border-bottom: 1px solid var(--table-border);
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .section-title { font-weight: 800; font-size: {{ $fontSize + 6 }}px; color: var(--primary); }
        .subheading { font-weight: 700; color: var(--text); }
        .total-row td { font-weight: {{ ($design['bold_total'] ?? true) ? '800' : '500' }}; }
        .summary td { border: none; padding: 4px 10px; }
        .summary tr td:first-child { text-align: right; }
        .mt-2 { margin-top: 12px; }
        .mb-2 { margin-bottom: 12px; }
        .hr-light { border: 0; border-top: 1px solid var(--table-border); margin: 14px 0; }
        .totals-wrapper { display: flex; justify-content: flex-end; width: 100%; }
    </style>
</head>
<body>
        <div class="header-row">
            @if($logoSrc)
                <div class="logo" style="max-width: {{ $logoWidthPx }}px;">
                    <img src="{{ $logoSrc }}" alt="Logo">
                </div>
            @endif
            <div style="flex:1; width:100%; text-align: right;">
                <div class="section-title">{{ __('invoice.title', ['number' => $invoice->reference]) }}</div>
                <div class="muted">{{ $date_invoice }}</div>
            </div>
        </div>

        <table style="margin-top: 8px;">
            <thead>
                <tr>
                    <td> 
                        <div class="muted" style="font-size: {{ $fontSize + 2 }}px;">{{ __('invoice.billing_address') }}</div>
                        <div class="subheading" style="font-size: {{ $fontSize + 2 }}px;">{{ $invoice->customer->name ?? '' }}</div>
                        @if(!empty($design['show_customer_number']) && !empty($invoice->customer->reference))
                            <div class="muted">{{ __('invoice.customer_number', ['number' => $invoice->customer->reference]) }}</div>
                        @endif
                        <div class="muted" style="white-space: pre-line;">{!! nl2br(e($invoice->customer->address_billing ?? '')) !!}</div>
                        @if(!empty($design['show_customer_phone']) && !empty($invoice->customer->phone))
                            <div class="muted">{{ __('invoice.phone', ['phone' => $invoice->customer->phone]) }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="muted" style="font-size: {{ $fontSize + 2 }}px;">{{ __('invoice.shipping_address') }}</div>
                        <div class="subheading" style="font-size: {{ $fontSize + 2 }}px;">{{ $invoice->customer->name ?? '' }}</div>
                        <div class="muted" style="white-space: pre-line;">{!! nl2br(e($invoice->customer->address_delivery ?? $invoice->customer->address_billing ?? '')) !!}</div>
                    </td>
                </tr>
            </thead>
        </table>

        <table style="margin-top: 30px;" class="rows">
            <thead>
                <tr>
                    <th style="width:40%; background: {{ $design['table_header_bg'] }}; color: {{ $design['table_header_text'] }}; border-bottom: 1px solid {{ $design['table_border'] }}; border-top: 1px solid {{ $design['table_border'] }};">
                        {{ __('invoice.item_description') }}
                    </th>
                    @if(!empty($design['show_tax_column']))
                        <th class="text-center" style="width:10%; background: {{ $design['table_header_bg'] }}; color: {{ $design['table_header_text'] }}; border-bottom: 1px solid {{ $design['table_border'] }}; border-top: 1px solid {{ $design['table_border'] }};">
                            {{ __('invoice.tax') }}
                        </th>
                    @endif
                    @if(!empty($design['show_discount']))
                        <th class="text-center" style="width:10%; background: {{ $design['table_header_bg'] }}; color: {{ $design['table_header_text'] }}; border-bottom: 1px solid {{ $design['table_border'] }}; border-top: 1px solid {{ $design['table_border'] }};">
                            {{ __('invoice.discount') }}
                        </th>
                    @endif
                    <th class="text-center" style="width:12%; background: {{ $design['table_header_bg'] }}; color: {{ $design['table_header_text'] }}; border-bottom: 1px solid {{ $design['table_border'] }}; border-top: 1px solid {{ $design['table_border'] }};">
                        {{ __('invoice.quantity') }}
                    </th>
                    <th class="text-center" style="width:12%; background: {{ $design['table_header_bg'] }}; color: {{ $design['table_header_text'] }}; border-bottom: 1px solid {{ $design['table_border'] }}; border-top: 1px solid {{ $design['table_border'] }};">
                        {{ __('invoice.unit_price') }}
                    </th>
                    <th class="text-right" style="width:16%; background: {{ $design['table_header_bg'] }}; color: {{ $design['table_header_text'] }}; border-bottom: 1px solid {{ $design['table_border'] }}; border-top: 1px solid {{ $design['table_border'] }};">
                        {{ __('invoice.amount') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->carts as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->product->name ?? '' }}</strong>
                            @if(!empty($item->description))
                                <div class="muted" style="font-size: {{ $fontSize - 1 }}px;">{!! nl2br(e($item->description)) !!}</div>
                            @endif
                        </td>
                        @if(!empty($design['show_tax_column']))
                            <td class="text-center">{{ $item->vta ?? 0 }}%</td>
                        @endif
                        @if(!empty($design['show_discount']))
                            <td class="text-center"></td>
                        @endif
                        <td class="text-center">{{ $formatNumber($item->qty) }}</td>
                        <td class="text-center">{{ $formatMoney($item->price) }}</td>
                        <td class="text-right">{{ $formatMoney($item->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals-wrapper">
            <table class="summary" style="margin-top: 8px; width: auto; min-width: 360px; margin-left: auto; text-align: right;">
                <tbody>
                    @if(!empty($design['show_subtotal']))
                    <tr>
                        <td style="font-weight:700; text-align:right;">{{ __('invoice.subtotal') }}</td>
                        <td class="text-right">{{ $formatMoney($invoice->sub_total) }}</td>
                    </tr>
                    @endif
                    @if(!empty($design['show_tax_breakdown']))
                        @php $base = $invoice->sub_total; @endphp
                        @if($invoice->vta4)
                            <tr>
                                <td class="muted" style="text-align:right;">{{ __('invoice.tax_line', ['base' => $formatMoney($base), 'rate' => 4]) }}</td>
                                <td class="text-right">{{ $formatMoney($invoice->vta4) }}</td>
                            </tr>
                        @endif
                        @if($invoice->vta10)
                            <tr>
                                <td class="muted" style="text-align:right;">{{ __('invoice.tax_line', ['base' => $formatMoney($base), 'rate' => 10]) }}</td>
                                <td class="text-right">{{ $formatMoney($invoice->vta10) }}</td>
                            </tr>
                        @endif
                        @if($invoice->vta21)
                            <tr>
                                <td class="muted" style="text-align:right;">{{ __('invoice.tax_line', ['base' => $formatMoney($base), 'rate' => 21]) }}</td>
                                <td class="text-right">{{ $formatMoney($invoice->vta21) }}</td>
                            </tr>
                        @endif
                    @endif
                    <tr class="total-row">
                        <td style="font-weight:700; text-align:right;">{{ __('invoice.total') }}</td>
                        <td class="text-right">{{ $formatMoney($invoice->total) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if(!empty($design['show_payment_terms']) && !empty($invoice->payment_terms))
            <div class="mt-2" style="display:flex; justify-content: center; gap: 28px; color: var(--muted); font-weight: 700;">
                <div>{{ __('invoice.payment_terms') }}</div>
                <div style="text-align: right;">{{ $invoice->payment_terms }}</div>
            </div>
        @endif

        @if(!empty($design['show_payment_note']) && !empty($design['payment_note']))
            <div class="mt-2">
                <div style="font-weight:700;">{{ __('invoice.payment_info') }}</div>
                <div class="muted" style="white-space: pre-line;">{!! nl2br(e($design['payment_note'])) !!}</div>
            </div>
        @endif
</body>
</html>
