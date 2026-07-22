{{-- Line items table. Columns toggled by template flags show_tax_column and show_discount. --}}
<table class="items-table">
    <thead>
        <tr>
            <th style="text-align:left; width:40%;">
                {{ $docType === 'quote' ? __('quote.item_description') : __('invoice.item_description') }}
            </th>
            @if(!empty($design['show_tax_column']))
                <th class="text-center" style="width:10%;">
                    {{ $docType === 'quote' ? __('quote.tax') : __('invoice.tax') }}
                </th>
            @endif
            @if(!empty($design['show_discount']))
                <th class="text-center" style="width:10%;">
                    {{ $docType === 'quote' ? __('quote.discount') : __('invoice.discount') }}
                </th>
            @endif
            <th class="text-center" style="width:12%;">
                {{ $docType === 'quote' ? __('quote.quantity') : __('invoice.quantity') }}
            </th>
            <th class="text-center" style="width:14%;">
                {{ $docType === 'quote' ? __('quote.unit_price') : __('invoice.unit_price') }}
            </th>
            <th class="text-right" style="width:14%;">
                {{ $docType === 'quote' ? __('quote.amount') : __('invoice.amount') }}
            </th>
        </tr>
    </thead>
    <tbody>
        @foreach($document->carts as $cart)
            <tr>
                <td>
                    @if(!empty($cart->product?->name))
                        <div class="item-name">{{ $cart->product->name }}</div>
                    @endif
                    @if(!empty($cart->description))
                        <div class="item-desc">{!! nl2br(e($cart->description)) !!}</div>
                    @endif
                    @if(empty($cart->product?->name) && empty($cart->description))
                        <div class="item-name muted">—</div>
                    @endif
                </td>
                @if(!empty($design['show_tax_column']))
                    <td class="text-center">{{ $cart->vta ?? 0 }}%</td>
                @endif
                @if(!empty($design['show_discount']))
                    <td class="text-center">
                        @if(!empty($cart->discount)){{ $cart->discount }}%@endif
                    </td>
                @endif
                <td class="text-center">{{ $formatNumber($cart->qty) }}</td>
                <td class="text-center">{{ $formatMoney($cart->price) }}</td>
                <td class="text-right">{{ $formatMoney($cart->total) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
