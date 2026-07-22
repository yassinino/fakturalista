{{-- Company (left) + document title / dates / status (right) --}}
<table class="header-table">
    @if(!empty($isLogoAbove) && $logoSrc)
        <tr>
            <td colspan="2" style="text-align: center; padding-bottom: 14px;">
                <img src="{{ $logoSrc }}" style="max-height: 64px; max-width: {{ (int) $logoWidthPx }}px; display: inline-block;" alt="{{ $companyName }}">
            </td>
        </tr>
    @endif
    <tr>
        {{-- ── Left: logo (if not above) + company info ── --}}
        <td class="col-company">
            @if($logoSrc && empty($isLogoAbove))
                <img src="{{ $logoSrc }}" class="logo-img" alt="{{ $companyName }}">
            @endif
            <div class="company-name">{{ $companyName }}</div>
            @if($companyAddress)
                <div class="company-detail" style="white-space: pre-line;">{{ $companyAddress }}</div>
            @endif
            @if($company?->phone)
                <div class="company-detail">{{ __('invoice.phone', ['phone' => $company->phone]) }}</div>
            @endif
            @if($company?->email)
                <div class="company-detail">{{ $company->email }}</div>
            @endif
        </td>

        {{-- ── Right: doc type + reference + dates + status ── --}}
        <td class="col-meta">
            <div class="doc-label">
                @if($docType === 'quote')
                    {{ match($locale) { 'fr' => 'Devis', 'es' => 'Presupuesto', default => 'Quote' } }}
                @else
                    {{ match($locale) { 'fr' => 'Facture', 'es' => 'Factura', default => 'Invoice' } }}
                @endif
            </div>
            <div class="doc-number">
                {{ $docType === 'quote'
                    ? __('quote.title', ['number' => $document->reference])
                    : __('invoice.title', ['number' => $document->reference]) }}
            </div>

            <div class="meta-row">
                <span class="meta-key">
                    {{ $docType === 'quote' ? __('quote.quote_date') : __('invoice.date') }}
                </span>
                <span class="meta-val">{{ $docDate }}</span>
            </div>

            @if($expiryDate)
                <div class="meta-row">
                    <span class="meta-key">{{ __('quote.expiry_date') }}</span>
                    <span class="meta-val">{{ $expiryDate }}</span>
                </div>
            @endif

            @if($statusData)
                <div class="meta-row" style="margin-top: 8px;">
                    <span class="meta-key">
                        {{ $docType === 'quote' ? __('quote.status') : __('invoice.status') }}
                    </span>
                    <span class="meta-val">
                        <span class="status-badge" style="background: {{ $statusData['color'] }}22; color: {{ $statusData['color'] }}; border: 1px solid {{ $statusData['color'] }}55;">
                            {{ $statusData['label'] }}
                        </span>
                    </span>
                </div>
            @endif
        </td>
    </tr>
</table>

<hr class="divider">
