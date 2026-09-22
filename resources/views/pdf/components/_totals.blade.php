{{-- Totals: right-aligned via 2-column table (DomPDF has no flexbox support). --}}
<table style="width: 100%; border-collapse: collapse; margin-top: 8px;">
    <tr>
        <td style="width: 50%;"></td>
        <td style="width: 50%; vertical-align: top;">
            <table class="totals-table">
                <tbody>
                    @if(!empty($design['show_subtotal']))
                        <tr>
                            <td>{{ $isMoroccanTax ? 'Sous-total HT' : ($docType === 'quote' ? __('quote.subtotal') : __('invoice.subtotal')) }}</td>
                            <td>{{ $formatMoney($subTotal) }}</td>
                        </tr>
                    @endif

                    @if(!empty($design['show_tax_breakdown']))
                        @foreach($taxGroups as $group)
                            {{-- Morocco Phase 1C.3: per-rate taxable base ("Base TVA 20%"),
                                 ahead of its own tax amount row - the persisted, authoritative
                                 base from invoice_tax_lines, never recomputed here. Gated to
                                 Morocco only so Spain's existing totals block is unchanged;
                                 also absent for the legacy vta4/vta10/vta21 fallback (no
                                 per-rate base exists there), which is intentional. --}}
                            @if($isMoroccanTax && isset($group['base']))
                                <tr>
                                    <td>Base {{ $taxLabel($group['rate'], $group['treatment']) }}</td>
                                    <td>{{ $formatMoney($group['base']) }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td>
                                    {{ $taxLabel($group['rate'], $group['treatment']) }}
                                </td>
                                <td>{{ $formatMoney($group['amount']) }}</td>
                            </tr>
                        @endforeach

                        @if(count($taxGroups) > 0)
                            <tr>
                                <td>{{ $isMoroccanTax ? 'Total TVA' : ($docType === 'quote' ? __('quote.total_tax') : __('invoice.total_tax')) }}</td>
                                <td>{{ $formatMoney($totalTaxAmount ?? 0) }}</td>
                            </tr>
                        @endif
                    @endif

                    @if($discountAmount > 0)
                        <tr>
                            <td>{{ $docType === 'quote' ? __('quote.discount') : __('invoice.discount') }}</td>
                            <td>– {{ $formatMoney($discountAmount) }}</td>
                        </tr>
                    @endif

                    <tr class="total-final">
                        <td>{{ $isMoroccanTax ? 'Total TTC' : ($docType === 'quote' ? __('quote.total') : __('invoice.total')) }}</td>
                        <td>{{ $formatMoney($grandTotal) }}</td>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>
</table>
