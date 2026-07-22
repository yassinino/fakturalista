{{-- Totals: right-aligned via 2-column table (DomPDF has no flexbox support). --}}
<table style="width: 100%; border-collapse: collapse; margin-top: 8px;">
    <tr>
        <td style="width: 50%;"></td>
        <td style="width: 50%; vertical-align: top;">
            <table class="totals-table">
                <tbody>
                    @if(!empty($design['show_subtotal']))
                        <tr>
                            <td>{{ $docType === 'quote' ? __('quote.subtotal') : __('invoice.subtotal') }}</td>
                            <td>{{ $formatMoney($subTotal) }}</td>
                        </tr>
                    @endif

                    @if(!empty($design['show_tax_breakdown']))
                        @foreach($taxGroups as $rate => $taxAmount)
                            <tr>
                                <td>
                                    {{ $docType === 'quote'
                                        ? __('quote.tax_line', ['rate' => $rate])
                                        : __('invoice.tax_line', ['base' => $formatMoney($subTotal), 'rate' => $rate]) }}
                                </td>
                                <td>{{ $formatMoney($taxAmount) }}</td>
                            </tr>
                        @endforeach
                    @endif

                    @if($discountAmount > 0)
                        <tr>
                            <td>{{ $docType === 'quote' ? __('quote.discount') : __('invoice.discount') }}</td>
                            <td>– {{ $formatMoney($discountAmount) }}</td>
                        </tr>
                    @endif

                    <tr class="total-final">
                        <td>{{ $docType === 'quote' ? __('quote.total') : __('invoice.total') }}</td>
                        <td>{{ $formatMoney($grandTotal) }}</td>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>
</table>
