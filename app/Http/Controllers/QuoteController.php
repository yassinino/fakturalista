<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\Customer;
use App\Models\Cart;
use App\Http\Requests\QuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QuoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $quotes = Quote::with('customer', 'carts')->orderBy('created_at', 'desc')->get();

        $quotes = $quotes->map(function($quote){
            return [
                'checked' => false,
                'uuid' => $quote->uuid,
                'customer' => $quote->customer->name,
                'reference' => $quote->reference,
                'date' => $quote->date,
                'expiration_date' => $quote->expiration_date,
                'status' => $quote->status,
                'carts' => $quote->carts,
                'total' => $quote->total,
                'vta' => $quote->vta,
                'sub_total' => $quote->sub_total,
            ];
        });
        return response(['quotes' => $quotes], 200);
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
    public function store(QuoteRequest $request)
    {
        $quote = Quote::orderBy('created_at', 'desc')->first();
        $customer = Customer::where('uuid', $request->customer_id)->first();
        $reference = isset($quote) ? $quote->id + 1 : 1;

        $new_quote = Quote::create([
            'uuid' => Str::uuid()->toString(),
            'reference' => 'QUO-'. $reference,
            'customer_id' => $customer->id,
            'date' => $request->date,
            'status' => $request->status,
            'expiration_date' => $request->expiration_date,
            'payment_terms' => $request->payment_terms,
            'sub_total' => $request->sub_total,
            'discount_rate' => $request->discount_rate,
            'discount_amount' => $request->discount_amount,
            'vta' => $request->vta,
            'total' => $request->total,
            'note' => $request->note,
        ]);


        if(count($request->carts) > 0){
            foreach ($request->carts as $cart) {
                Cart::create([
                    'cartable_type' => 'App\Models\Quote',
                    'cartable_id' => $new_quote->id,
                    'description' => $cart['description'],
                    'qty' => $cart['qty'],
                    'price' => $cart['price'],
                    'discount' => $cart['discount'],
                    'total' => $cart['total'],
                    'vta' => $cart['vta'],
                ]);
            }
        }

        return response(['message' => 'Quote added!'], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Quote $quote)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Quote $quote)
    {
        $customer = Customer::where('id', $quote->customer_id)->first();

        $quote = [
            'id' => $quote->id,
            'uuid' => $quote->uuid,
            'customer_id' => $customer->uuid,
            'date' => $quote->date,
            'status' => $quote->status,
            'expiration_date' => $quote->expiration_date,
            'payment_terms' => $quote->payment_terms,
            'sub_total' => $quote->sub_total,
            'discount_rate' => $quote->discount_rate,
            'discount_amount' => $quote->discount_amount,
            'vta' => $quote->vta,
            'total' => $quote->total,
            'note' => $quote->note,
            'carts' => $quote->carts,
        ];
        return response(['quote' => $quote], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(QuoteRequest $request, Quote $quote)
    {

        $customer = Customer::where('uuid', $request->customer_id)->first();

        Quote::where('id', $quote->id)->update([
            'customer_id' => $customer->id,
            'date' => $request->date,
            'status' => $request->status,
            'expiration_date' => $request->expiration_date,
            'payment_terms' => $request->payment_terms,
            'sub_total' => $request->sub_total,
            'discount_rate' => $request->discount_rate,
            'discount_amount' => $request->discount_amount,
            'vta' => $request->vta,
            'total' => $request->total,
            'note' => $request->note,
        ]);


        if(count($request->carts) > 0){

            Cart::where('cartable_type' ,'App\Models\Quote')
            ->where('cartable_id', $quote->id)
            ->delete();

            foreach ($request->carts as $cart) {

                Cart::create([
                    'cartable_type' => 'App\Models\Quote',
                    'cartable_id' => $quote->id,
                    'description' => $cart['description'],
                    'qty' => $cart['qty'],
                    'price' => $cart['price'],
                    'discount' => $cart['discount'],
                    'total' => $cart['total'],
                    'vta' => $cart['vta'],
                ]);
            }
        }

        return response(['message' => 'Quote updated!'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Quote $quote)
    {
        //
    }
}
