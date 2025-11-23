<?php
use Carbon\Carbon;
$date_invoice = Carbon::parse($invoice->date)->format('d-m-Y');
?>

<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    
    <style>

    body {
        max-width: 800px;
        margin: auto;
        padding: 30px;
        font-size: 11px;
        line-height: 24px;
        font-family: 'blogger',sans-serif;
        color: #000;
    }

    
    body table {
        width: 100%;
        line-height: inherit;
        text-align: left;
    }
    
    body table td {
        vertical-align: top;
    }

/*    
    body table tr td:nth-child(2) {
        text-align: right;
    }
    */
    body table tr.top table td {
        padding-bottom: 20px;
    }
    
    body table tr.top table td.title {
        font-size: 45px;
        line-height: 45px;
        color: #333;
    }

    body table tr.top table td.title img {
      width: auto;
    }
    
    body table tr.information td table{
        padding-bottom: 50px;
        padding-left: 0px;
    }

    body table tr.heading td {
        color: #909090;
        line-height: 22px;
        padding-top: 15px;
        padding-bottom: 8px;
        text-align: center;
        font-weight: bold;
        border-bottom: 0.4px dashed #c4c4c4;
    }

    body table .heading{
        margin-bottom: 5px;
    }
    
    body table tr.details td {
        padding-bottom: 20px;
    }
    
    body table tr.item td{
        border-bottom: 0.4px dashed #c4c4c4;
        padding-top: 10px;
        padding-bottom: 5px;
    }

    body table tr.item span{
        font-size: 12px;
    }
    
    body table tr.item.last td {
        border-bottom: none;
    }
    
    .tx-right {
        text-align: right;
    }

    .price {
        font-size: 20px;
        font-weight: bold;
    }
    
    @media only screen and (max-width: 600px) {
        body table tr.top table td {
            width: 100%;
            display: block;
            text-align: center;
        }
        
        body table tr.information table td {
            width: 100%;
            display: block;
            text-align: center;
        }
    }
    .information .titres span {
        margin-bottom: 0px;
        color: #909090;
        font-size: 15px;
    }
    
    /** RTL **/
    .rtl {
        direction: rtl;
        font-family: 'blogger', 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
    }
    
    .rtl table {
        text-align: right;
    }
    
    .rtl table tr td:nth-child(2) {
        text-align: left;
    }

    tr.s_total td{
        padding-top: 20px;
    }

    tr.total_final td {
        padding-bottom: 8px;
    }

    .tx-total {
        text-align: right;
        padding-right : 5px;
        margin-bottom: 10px;
    }

    .footer {
      list-style-type: none;
      margin: 0;
      padding: 0
    }

    .footer li {
      float: left;
    }

    #footer {
         position: absolute; 
         bottom:-100;
         right:0;
        /* margin-left: 500px;*/
        float: right;
    } 

    @page {
      header: page-header;
      footer: page-footer;
    }

    </style>
</head>
<body>
    <div class="invoice-box">
        <table cellpadding="0" cellspacing="0">
            <tr class="top">
                <td colspan="4" style="padding-bottom: 40px;padding-left: 5px;">
                   <p style="color: #000000;font-size: 16px;"><span style="font-weight: bold;">Factura Núm. </span> {{ $invoice->reference  }}</p>
                </td>
            </tr>

             <tr class="information" style="padding-top:20px;">
                <td colspan="6">
                    <table>
                        <tr style="font-size: 14px;">
                            <td style="width: 50%; color: gray">
                                <img src="{{ public_path('assets/logo_tachua.png') }}" style="width:200px;">
                            </td>
                            <td style="color: gray; text-align: right;">
                                Ángel Jiménez Rodriguez<br>
                                NIF : 12321909-G<br>
                                Diseminat, 47 - Apt. Correos 261<br>
                                43480 VILA-SECA (Tarragona)<br>
                                TEL : 657 985 633 / 652 138 016<br>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            
            <tr class="information" style="padding-top:20px;">
                <td colspan="6">
                    <table>
                        <tr class="titres" style="font-size: 18px;">

                            <td style="width: 30%;">
                                <span style="color : black; font-weight: bold;">Fecha</span>
                                <div style="color: gray ;font-size: 14px;">
                                    {{ $date_invoice }}
                                </div>
                            </td>
                            <td style="width: 50%;">
                                <span style="color : black; font-weight: bold;">Cliente</span>
                                <div style="color: gray; font-size: 14px;">
                                    {{$invoice->customer->type == 1 ? $invoice->customer->name : $invoice->customer->first_name }} <br>
                                    <span>{!! nl2br(e($invoice->customer->address_billing)) !!}</span>
                                </div>
                            </td>
                            <td style="width: 20%;">
                                <span style="color : black; font-weight: bold;">NIF</span>
                                <div style="color: gray ;font-size: 14px;">{{$invoice->customer->ice}}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="detail">
                <td colspan="4">
                     <table>
                        <tr>
                            <td style="width:30%;background: #E3E0DC;padding: 10px;">
                                <span style="">Detalles de la factura</span>
                            </td>
                            <td style="width:40%;padding: 10px;"></td>
                        </tr>
                    </table>
                </td>
            </tr>
            
            <tr class="heading">
                <td style="text-align: left;">
                    Producto
                </td>
                
                <td style="width: 20%">
                    Cantidad
                </td>

                <td style="width: 10%">
                    Unidad
                </td>

                <td style="text-align: right;width: 25%;">
                    Precio
                </td>

                <td>
                    IVA%
                </td>

                <td style="text-align: right;">
                    IMPORTE
                </td>
            </tr>

            @foreach($invoice['carts'] as $item)

            <tr class="item">
                <td style="width: 50%">
                   {{ $item->product ? $item->product->name : '' }}
                    <span style="font-size: 9px;"> {{ $item->description ? ' - ' : '' }} {!! nl2br(e($item->description)) !!}</span>
                </td>
                <td style="text-align: center;">
                   {{number_format($item->qty, 2,",",".") }}
                </td>

                <td style="text-align: center;">
                   {{$item->unite == 'pc' ? 'pieza' : ($item->unite == 'hr' ? 'hora' : ($item->unite == 'kg' ? 'kg' : ($item->unite == 'm' ? 'metro' : ($item->unite == 'lt' ? 'litro' : ''))))}}
                </td>

                <td style="text-align: right;">
                    {{number_format($item->price, 2,",",".") }}<br>
                </td>

                <td style="text-align: center;">
                   {{$item->vta}}
                </td>
                
                <td style="text-align: right;padding-right: 5px;">
                    {{number_format($item->total, 2,",",".") }}
                </td>
            </tr>

            @endforeach

            <tr class="s_total tx-total">
                <td colspan="4"></td>
                <td style="text-align:right;font-weight: bold;">SUMA</td>
                <td style="text-align:right;padding-right: 5px;">{{number_format($invoice->sub_total, 2,",",".") }}</td>
            </tr>
            <tr class="tva tx-total">
                <td colspan="4"></td>
                <td style="text-align:right;padding-top: 5px;font-weight: bold;">IVA(4%)</td>
                <td style="text-align:right;padding-top: 5px;padding-right: 5px;">{{number_format($invoice->vta4, 2,",",".")}}</td>
            </tr>
            <tr class="tva tx-total">
                <td colspan="4"></td>
                <td style="text-align:right;padding-top: 5px;font-weight: bold;">IVA(10%)</td>
                <td style="text-align:right;padding-top: 5px;padding-right: 5px;">{{number_format($invoice->vta10, 2,",",".")}}</td>
            </tr>
            <tr class="tva tx-total">
                <td colspan="4"></td>
                <td style="text-align:right;padding-top: 5px;font-weight: bold;">IVA(21%)</td>
                <td style="text-align:right;padding-top: 5px;padding-right: 5px;">{{number_format($invoice->vta21, 2,",",".")}}</td>
            </tr>
            <tr class="total tx-total">
                <td colspan="4"></td>
                <td style="text-align:right;padding-top: 5px;font-weight: bold;">TOTAL</td>
                <td style="text-align:right;padding-top: 5px;padding-right: 5px;">{{number_format($invoice->total, 2,",",".")}}</td>
            </tr>
        </table>
        
    </div>

        {{-- <div style="position: absolute;bottom: 350px;line-height: 15px;">
            <p>Arrété la présente Note d'Honoraires à la somme totale TTC de :</p>
            <p>{{$invoice['montant_lettre']}}, Seulement.</p>
          </div> --}}



</body>
</html>