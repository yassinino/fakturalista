{{-- Billing address + optional shipping (invoices only). Template flags control layout. --}}
<table class="addresses-table" style="margin-top: 8px;">
    <tr>
        @if(!empty($design['billing_address_right']))
            {{-- Shipping left, billing right --}}
            @if($docType === 'invoice' && !empty($design['show_shipping_address']))
                <td style="width:50%; padding-right:20px;">
                    <div class="addr-label">{{ __('invoice.shipping_address') }}</div>
                    <div class="addr-name">{{ $document->customer?->name ?? '' }}</div>
                    <div class="addr-sub" style="white-space: pre-line;">{!! nl2br(e($document->customer?->address_delivery ?? $document->customer?->address_billing ?? '')) !!}</div>
                </td>
            @endif
            <td style="{{ ($docType === 'invoice' && !empty($design['show_shipping_address'])) ? 'width:50%;' : 'width:100%;' }}">
                <div class="addr-label">
                    {{ $docType === 'quote' ? __('quote.bill_to') : __('invoice.billing_address') }}
                </div>
                <div class="addr-name">{{ ($docType === 'invoice' ? ($customerIdentity['name'] ?? null) : $document->customer?->name) ?? '-' }}</div>
                @if($docType === 'invoice')
                    {{-- Country-aware, snapshot-first (Morocco Phase 1B, docs/morocco-phase-1b-identity.md §8) --}}
                    @if(strtoupper($companyIdentity['country_code'] ?? '') === 'MA')
                        @if(!empty($customerIdentity['ice']))
                            <div class="addr-sub">{{ __('invoice.ice', ['id' => $customerIdentity['ice']]) }}</div>
                        @endif
                    @else
                        @if(!empty($customerIdentity['tax_id']))
                            <div class="addr-sub">{{ __('invoice.tax_id', ['id' => $customerIdentity['tax_id']]) }}</div>
                        @endif
                    @endif
                @endif
                @if(!empty($design['show_customer_number']) && !empty($customerIdentity['reference'] ?? $document->customer?->reference))
                    <div class="addr-sub">{{ __('invoice.customer_number', ['number' => $customerIdentity['reference'] ?? $document->customer->reference]) }}</div>
                @endif
                @if($customerIdentity['address_billing'] ?? $document->customer?->address_billing)
                    <div class="addr-sub" style="white-space: pre-line;">{!! nl2br(e($customerIdentity['address_billing'] ?? $document->customer->address_billing)) !!}</div>
                @endif
                @if(!empty($design['show_customer_phone']) && !empty($customerIdentity['phone'] ?? $document->customer?->phone))
                    <div class="addr-sub">{{ __('invoice.phone', ['phone' => $customerIdentity['phone'] ?? $document->customer->phone]) }}</div>
                @endif
                @if($docType === 'quote' && !empty($document->customer?->email))
                    <div class="addr-sub">{{ $document->customer->email }}</div>
                @endif
            </td>
        @else
            {{-- Billing left, shipping right (default) --}}
            <td style="{{ ($docType === 'invoice' && !empty($design['show_shipping_address'])) ? 'width:50%; padding-right:20px;' : 'width:100%;' }}">
                <div class="addr-label">
                    {{ $docType === 'quote' ? __('quote.bill_to') : __('invoice.billing_address') }}
                </div>
                <div class="addr-name">{{ ($docType === 'invoice' ? ($customerIdentity['name'] ?? null) : $document->customer?->name) ?? '-' }}</div>
                @if($docType === 'invoice')
                    @if(strtoupper($companyIdentity['country_code'] ?? '') === 'MA')
                        @if(!empty($customerIdentity['ice']))
                            <div class="addr-sub">{{ __('invoice.ice', ['id' => $customerIdentity['ice']]) }}</div>
                        @endif
                    @else
                        @if(!empty($customerIdentity['tax_id']))
                            <div class="addr-sub">{{ __('invoice.tax_id', ['id' => $customerIdentity['tax_id']]) }}</div>
                        @endif
                    @endif
                @endif
                @if(!empty($design['show_customer_number']) && !empty($customerIdentity['reference'] ?? $document->customer?->reference))
                    <div class="addr-sub">{{ __('invoice.customer_number', ['number' => $customerIdentity['reference'] ?? $document->customer->reference]) }}</div>
                @endif
                @if($customerIdentity['address_billing'] ?? $document->customer?->address_billing)
                    <div class="addr-sub" style="white-space: pre-line;">{!! nl2br(e($customerIdentity['address_billing'] ?? $document->customer->address_billing)) !!}</div>
                @endif
                @if(!empty($design['show_customer_phone']) && !empty($customerIdentity['phone'] ?? $document->customer?->phone))
                    <div class="addr-sub">{{ __('invoice.phone', ['phone' => $customerIdentity['phone'] ?? $document->customer->phone]) }}</div>
                @endif
                @if($docType === 'quote' && !empty($document->customer?->email))
                    <div class="addr-sub">{{ $document->customer->email }}</div>
                @endif
            </td>
            @if($docType === 'invoice' && !empty($design['show_shipping_address']))
                <td style="width:50%;">
                    <div class="addr-label">{{ __('invoice.shipping_address') }}</div>
                    <div class="addr-name">{{ $document->customer?->name ?? '' }}</div>
                    <div class="addr-sub" style="white-space: pre-line;">{!! nl2br(e($document->customer?->address_delivery ?? $document->customer?->address_billing ?? '')) !!}</div>
                </td>
            @endif
        @endif
    </tr>
</table>
