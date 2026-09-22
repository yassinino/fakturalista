{{-- Footer: validity bar (quotes) · payment terms (invoices) · notes · payment note · Stripe link --}}

{{-- Validity bar - quotes only --}}
@if($docType === 'quote' && !empty($document->expiration_date) && $expiryDate)
    <div class="validity-bar">
        {{ __('quote.valid_through', ['date' => $expiryDate]) }}
    </div>
@endif

{{-- Payment terms - invoices only --}}
@if($docType === 'invoice' && !empty($design['show_payment_terms']) && !empty($document->payment_terms))
    <div class="payment-terms">
        <div>{{ __('invoice.payment_terms') }}</div>
        <div style="text-align:right;">{{ $document->payment_terms }}</div>
    </div>
@endif

{{-- Bank/payment details - invoices only, reusing the existing company
     bank_name/iban/swift fields (Morocco Phase 1C.3, docs/morocco-phase-1c3-invoice-readiness.md §11).
     Read from the live CompanyProfile, not the identity snapshot: this is
     payment-routing information, not the invoice's frozen fiscal identity
     (company name/ICE/IF/RC), and a tenant may legitimately switch bank
     accounts between issuing invoices. No Moroccan bank field is invented;
     only the three fields Settings already collects for every country are
     shown, and only when at least one is actually configured. --}}
@php
    $bankDetails = $docType === 'invoice' ? array_filter([
        'name'  => $company?->bank_name,
        'iban'  => $company?->iban,
        'swift' => $company?->swift,
    ]) : [];
@endphp
@if(!empty($bankDetails))
    <div class="notes-section">
        <hr class="divider-light">
        <div class="notes-title">
            {{ match($locale) { 'fr' => 'Coordonnées bancaires', 'es' => 'Datos bancarios', default => 'Bank details' } }}
        </div>
        <div class="notes-body">
            @if(!empty($bankDetails['name'])){{ $bankDetails['name'] }}<br>@endif
            @if(!empty($bankDetails['iban']))IBAN: {{ $bankDetails['iban'] }}<br>@endif
            @if(!empty($bankDetails['swift']))SWIFT/BIC: {{ $bankDetails['swift'] }}@endif
        </div>
    </div>
@endif

{{-- Notes section --}}
@if(!empty($document->note))
    <div class="notes-section">
        <hr class="divider-light">
        <div class="notes-title">
            {{ $docType === 'quote' ? __('quote.notes') : __('invoice.notes') }}
        </div>
        <div class="notes-body">{!! nl2br(e($document->note)) !!}</div>
    </div>
@endif

{{-- Payment note (from template settings) --}}
@if(!empty($design['show_payment_note']) && !empty($design['payment_note']))
    <div style="margin-top: 12px;">
        <div style="font-weight:700;">{{ __('invoice.payment_info') }}</div>
        <div class="muted" style="white-space: pre-line;">{!! nl2br(e($design['payment_note'])) !!}</div>
    </div>
@endif

{{-- Stripe payment link - invoices only --}}
@if($pdfPaymentUrl)
    <hr class="divider-light" style="margin-top: 20px;">
    <div class="stripe-section">
        <div class="stripe-label">{{ __('invoice.pay_online_label') }}</div>
        <a href="{{ $pdfPaymentUrl }}"
           style="display:inline-block; background:{{ $design['primary'] }}; color:#ffffff; font-weight:700; font-size:13px; padding:9px 22px; text-decoration:none;">
            {{ __('invoice.pay_online_btn') }} →
        </a>
        <div class="stripe-url">{{ $pdfPaymentUrl }}</div>
    </div>
@endif
