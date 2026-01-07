<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    @php
        $bloggerFontPath = public_path('fonts/blogger-sans.regular.ttf');
    @endphp
    <style>

    @font-face {
        font-family: 'blogger';
        font-style: normal;
        font-weight: 400;
        src: url('{{ 'file://' . $bloggerFontPath }}') format('truetype');
    }

    body {
        max-width: 800px;
        margin: auto;
        padding: 30px;
        font-size: 11px;
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
        font-size: 16px;
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
        border-bottom: 0.4px dashed #c4c4c4;
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
                <td colspan="4" style="padding-bottom: 50px;">
                    <table>
                        <tr>
                            <td class="tx-left" style="width:30%">
                                <b>Yassine AIT TOUIJAR</b><br>
                                RUE ELEC APP 3 N 5190<br> Hay Mohammadi<br> Agadir<br> Maroc<br>
                                T : +212 (6) 01 87 81 55<br>
                                E : aittouijar.yassine@gmail.ma ; contact@aittouijardev.com<br>
                            </td>
                            <td class="tx-left">
                                TP : 49875002 <br>
                                IF : 24822443 <br>
                                ICE : 001967892000024
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="top">
                <td colspan="4" style="padding-bottom: 40px;padding-left: 5px;">
                   <p style="color: #000000;font-size: 16px;"><span style="font-weight: bold;">Facture </span>N° {{ $invoice->reference  }}</p>
                </td>
            </tr>
            
            <tr class="information" style="padding-top:20px;">
                <td colspan="4">
                    <table>
                        <tr class="titres" style="font-size: 15px;">
                            <td style="width: 40%;">

                                <table>
                                    <tr>
                                        <td>Date</td>
                                        <td style="color: gray;">{{ \Carbon\Carbon::parse($invoice->date)->format('d-m-Y') }}</td>
                                    </tr>

                                </table>

                            </td>

                            <td style="width: 30%;"></td>
                            <td>
                                
                                    <span style="font-size: 15px;color: black;">Client</span> <br /> 
                                    <span style="font-size: 15px;color: gray;">{{$invoice->customer->name}} <br /> 
                                        {!! nl2br(e($invoice->customer->address_billing ?? '')) !!}
                                    
                                     {{$invoice->customer->ice ? 'ICE : ' . $invoice->customer->ice : ''}}<br> 
                                    </span>
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
                                <span style="">Details</span>
                            </td>
                            <td style="width:40%; text-align: right;"></td>
                            <td style="text-align: right;">Somme exprimée en euros (€)</td>
                        </tr>
                    </table>
                </td>
            </tr>
            
            <tr class="heading">
                <td>
                    Description
                </td>
                
                <td style="width: 20%">
                    Qté
                </td>

                <td style="text-align: right;">
                    Prix unitaire
                </td>

                <td style="text-align: right;">
                    Total
                </td>
            </tr>

            @foreach($invoice['carts'] as $item)

            <tr class="item">
                <td style="width: 50%">
                    <label for="">{{ $item->product ? $item->product->name : '' }}</label><br>
                    <span style="font-size: 9px;">{!! nl2br(e($item->description)) !!}</span>
                </td>
                <td style="text-align: center;">
                   {{number_format($item->qty, 2,",",".") }}
                </td>

                <td style="text-align: right;">
                    {{number_format($item->price, 2,",",".") }}<br>
                </td>
                
                <td style="text-align: right;padding-right: 5px;">
                    {{number_format($item->total, 2,",",".") }}
                </td>
            </tr>

            @endforeach


            <tr class="total tx-total">
<!--                 <td colspan="2"></td>
                <td style="text-align:right;padding-top: 5px;font-weight: bold;">Total</td>
                <td style="text-align:right;padding-top: 5px;padding-right: 5px;">{{number_format($invoice->sub_total, 2,",",".") }}</td> -->
            </tr>
            <tr class="total_final tx-total">
<!--                 <td colspan="2"></td>
                <td style="text-align:right;padding-top: 5px;font-weight: bold;">Montant payé</td>
                <td style="text-align:right;padding-top: 5px;padding-right: 5px;"></td> -->
            </tr>
             <tr class="tx-total">
                <td colspan="2" style="padding-top: 10px;"></td>
                <td style="text-align:right;
        margin-top: 10px;padding-top: 10px;padding-bottom: 10px;font-weight: bold;background-color: #E3E0DC;">Total</td>
                <td style="text-align:right;
        margin-top: 10px;padding-top: 10px;padding-bottom: 10px;font-weight: bold;background-color: #E3E0DC;padding-right: 5px;">{{number_format($invoice->total, 2,",",".")}}</td>
            </tr>
        </table>
        
    </div>

<!--         <div style="position: absolute;bottom: 300px;line-height: 15px;">
            <p>Arrété la présente Note d'Honoraires à la somme totale TTC de :</p>
            {{-- <p>{{$model['montant_lettre']}} EUR, Seulement.</p> --}}
          </div> -->
          <div style="position: absolute;bottom: 100px;line-height: 15px;">
            <span style="color: #909090; font-size:12px;">Coordonnées bancaires: </span>
            <p style="line-height: 14px;">Name : Yassine AIT TOUIJAR<br>
              IBAN: BE43 9671 9028 8401<br>
              BIC: TRWIBEB1XXX <br>
              Wise, Rue du Trône 100, 3rd floor, Brussels, 1050, Belgium
            </p><br>

            {{-- <p style="line-height: 14px;">Paypal ID:  aittouijar.yassine@gmail.com  <br> --}}
            </p>
          </div>


    {{-- <htmlpagefooter name="page-footer">
        <p style="text-align: center;color: gray">Page {PAGENO}</p>
  </htmlpagefooter> --}}


</body>
</html>
