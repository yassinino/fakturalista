<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Cart;
use App\Models\Item;
use App\Models\CompanyProfile;
use App\Http\Requests\InvoiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Storage;
class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $perPage = request()->input('per_page', 2);
        $paginator = Invoice::with('customer')->orderBy('created_at', 'desc')->orderByDesc('date')
        ->paginate($perPage);


        $paginator->getCollection()->transform(function ($invoice) {
            return [
                'checked' => false,
                'uuid' => $invoice->uuid,
                'customer' => $invoice->customer?->name,
                'reference' => $invoice->reference,
                'date' => $invoice->date,
                'expiration_date' => $invoice->expiration_date,
                'status' => $invoice->status,
                'sub_total' => $invoice->sub_total,
                'total' => $invoice->total,
            ];
        });


        return response()->json([
            'invoices' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(InvoiceRequest $request)
    {
        $invoice = Invoice::orderBy('created_at', 'desc')->first();
        $customer = Customer::where('uuid', $request->customer_id)->first();
        $reference = isset($invoice) ? $invoice->id + 1 : 1;

        $new_invoice = Invoice::create([
            'uuid' => Str::uuid()->toString(),
            'reference' => 'INV-'. $reference,
            'customer_id' => $customer->id,
            'date' => $request->date,
            'status' => $request->status,
            'expiration_date' => date('Y-m-d', strtotime($request->expiration_date)),
            'payment_terms' => $request->payment_terms,
            'sub_total' => $request->sub_total,
            'discount_rate' => $request->discount_rate,
            'discount_amount' => $request->discount_amount,
            'vta' => $request->vta,
            'vta4' => $request->vta4,
            'vta10' => $request->vta10,
            'vta21' => $request->vta21,
            'total' => $request->total,
            'note' => $request->note,
        ]);


        if(count($request->carts) > 0){
            foreach ($request->carts as $cart) {

                Cart::create([
                    'cartable_type' => 'App\Models\Invoice',
                    'cartable_id' => $new_invoice->id,
                    'item_id' => $cart['item_id'],
                    'description' => $cart['description'],
                    'qty' => $cart['qty'],
                    'price' => $cart['price'],
                    'unite' => $cart['unite'],
                    'discount' => $cart['discount'],
                    'total' => $cart['total'],
                    'vta' => $cart['vta'],
                ]);
            }
        }

        return response(['message' => '¡Factura añadida!'], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Invoice $invoice)
    {
        $customer = Customer::where('id', $invoice->customer_id)->first();

        $invoice = [
            'id' => $invoice->id,
            'uuid' => $invoice->uuid,
            'reference' => $invoice->reference,
            'customer_id' => $customer->uuid,
            'address' => $customer->address_billing,
            'date' => $invoice->date,
            'status' => $invoice->status,
            'expiration_date' => $invoice->expiration_date,
            'payment_terms' => $invoice->payment_terms,
            'sub_total' => $invoice->sub_total,
            'discount_rate' => $invoice->discount_rate,
            'discount_amount' => $invoice->discount_amount,
            'vta' => $invoice->vta,
            'total' => $invoice->total,
            'note' => $invoice->note,
            'carts' => $invoice->carts,
        ];
        return response(['invoice' => $invoice], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(InvoiceRequest $request, Invoice $invoice)
    {

        $customer = Customer::where('uuid', $request->customer_id)->first();

        Invoice::where('id', $invoice->id)->update([
            'customer_id' => $customer->id,
            'date' => $request->date,
            'status' => $request->status,
            'expiration_date' => $request->expiration_date,
            'payment_terms' => $request->payment_terms,
            'sub_total' => $request->sub_total,
            'discount_rate' => $request->discount_rate,
            'discount_amount' => $request->discount_amount,
            'vta' => $request->vta,
            'vta4' => $request->vta4,
            'vta10' => $request->vta10,
            'vta21' => $request->vta21,
            'total' => $request->total,
            'note' => $request->note,
        ]);


        if(count($request->carts) > 0){

            Cart::where('cartable_type' ,'App\Models\Invoice')
            ->where('cartable_id', $invoice->id)
            ->delete();

            foreach ($request->carts as $cart) {

                Cart::create([
                    'cartable_type' => 'App\Models\Invoice',
                    'cartable_id' => $invoice->id,
                    'description' => $cart['description'],
                    'item_id' => $cart['item_id'],
                    'qty' => $cart['qty'],
                    'unite' => $cart['unite'],
                    'price' => $cart['price'],
                    'discount' => $cart['discount'],
                    'total' => $cart['total'],
                    'vta' => $cart['vta'],
                ]);
            }
        }

        return response(['message' => '¡Factura actualizada!'], 200);
    }


    public function print_invoice(Request $request)
    {
        $invoice = Invoice::where('uuid', $request->uuid)->with('customer', 'carts.product')->first();
        $locale = $request->user()?->locale
            ?? CompanyProfile::first()?->locale
            ?? config('app.locale', 'es');
        
        $host = request()->getHost();;

        if($host == 'client1.fakturalista.test'){
            $pdf = Pdf::loadView('invoices.yassine', [
                'invoice' => $invoice
            ])->setPaper('a4'); // 'a4', 'letter', etc.
        }
        else if($host == 'tachua.fakturalista.com'){
            $pdf = Pdf::loadView('invoices.tachua', [
                'invoice' => $invoice
            ])->setPaper('a4'); // 'a4', 'letter', etc.
        }else{
                    $pdf = Pdf::loadView('invoices.show', [
                'invoice' => $invoice
            ])->setPaper('a4'); // 'a4', 'letter', etc.
        }



        // 3. Define file name and path
        $fileName = 'invoice_' . $invoice->uuid . '.pdf';
        $filePath = 'invoices/' . $fileName;

        // 4. Save PDF to storage/app/public/invoices
        Storage::disk('public')->put($filePath, $pdf->output());

        // 5. Generate public URL
        $url = Storage::url($filePath);

        return response(['message' => 'Factura imprimida!', 'pdf_url' => $url], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice)
    {
        Invoice::where('uuid', $invoice->uuid)->delete();

        return response(['message' => '¡Factura eliminada!'], 200);
    }
}
