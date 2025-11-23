<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Contact;
use App\Models\Invoice;
use App\Http\Requests\CustomerRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $customers = Customer::orderBy('created_at', 'desc')->get();

        $customers = $customers->map(function($customer){
            return [
                'checked' => false,
                'uuid' => $customer->uuid,
                'name' => $customer->name,
                'type' => $customer->type,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address_billing' => $customer->address_billing,
                'reference' => $customer->reference,
            ];
        });
        return response(['customers' => $customers], 200);
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
    public function store(CustomerRequest $request)
    {
        $customer = Customer::orderBy('created_at', 'desc')->first();

        $reference = isset($customer) ? $customer->id + 1 : 1;

        $new_customer = Customer::create([
            'uuid' => Str::uuid()->toString(),
            'reference' => 'CUS-'. $reference,
            'company_name' => $request->name,
            'ice' => $request->ice,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'middle_name' => $request->middle_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'website' => $request->website,
            'type' => $request->type,
            'billing_country_id' => $request->billing_country_id,
            'delivery_country_id' => $request->delivery_country_id,
            'city_billing' => $request->city_billing,
            'address_billing' => $request->address_billing,
            'post_code_billing' => $request->post_code_billing,
            'is_same_address' => $request->is_same_address,
            'city_delivery' => $request->city_delivery,
            'address_delivery' => $request->address_delivery,
            'post_code_delivery' => $request->post_code_delivery,
        ]);


        if(count($request->contacts) > 0){
            foreach ($request->contacts as $contact) {
                Contact::create([
                    'contactable_type' => 'App\Models\Customer',
                    'contactable_id' => $new_customer->id,
                    'first_name' => $contact['first_name'],
                    'last_name' => $contact['last_name'],
                    'work_phone' => $contact['work_phone'],
                    'email' => $contact['email'],
                ]);
            }
        }

        return response(['message' => 'Customer added!'], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        $perPage = request()->input('per_page', 2);
        $paginator = Invoice::where('customer_id', $customer->id)->with('customer')->orderBy('created_at', 'desc')->orderByDesc('date')
        ->paginate($perPage);

        $paginator->getCollection()->transform(function ($invoice) {
            return [
                'checked' => false,
                'uuid' => $invoice->uuid,
                'customer' => $invoice->customer->name,
                'reference' => $invoice->reference,
                'date' => $invoice->date,
                'expiration_date' => $invoice->expiration_date,
                'status' => $invoice->status,
                'sub_total' => $invoice->sub_total,
                'total' => $invoice->total,
            ];
        });

        $customer = [
            'uuid' => $customer->uuid,
            'name' => $customer->company_name,
            'ice' => $customer->ice,
            'type' => $customer->type,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'middle_name' => $customer->middle_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address_billing' => $customer->address_billing,
        ];

        return response([
            'customer' => $customer,
            'invoices' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ]
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer)
    {
        $customer = [
            'uuid' => $customer->uuid,
            'name' => $customer->company_name,
            'ice' => $customer->ice,
            'type' => $customer->type,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'middle_name' => $customer->middle_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'website' => $customer->website,
            'billing_country_id' => $customer->billing_country_id,
            'delivery_country_id' => $customer->delivery_country_id,
            'uuid' => $customer->uuid,
            'reference' => $customer->reference,
            'city_billing' => $customer->city_billing,
            'address_billing' => $customer->address_billing,
            'post_code_billing' => $customer->post_code_billing,
            'is_same_address' => $customer->is_same_address,
            'city_delivery' => $customer->city_delivery,
            'address_delivery' => $customer->address_delivery,
            'post_code_delivery' => $customer->post_code_delivery,
            'contacts' => $customer->contacts,
        ];
        return response(['customer' => $customer], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CustomerRequest $request, Customer $customer)
    {
        Customer::where('id', $customer->id)->update([
            'company_name' => $request->name,
            'ice' => $request->ice,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'middle_name' => $request->middle_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'website' => $request->website,
            'type' => $request->type,
            'billing_country_id' => $request->billing_country_id,
            'delivery_country_id' => $request->delivery_country_id,
            'city_billing' => $request->city_billing,
            'address_billing' => $request->address_billing,
            'post_code_billing' => $request->post_code_billing,
            'is_same_address' => $request->is_same_address,
            'city_delivery' => $request->city_delivery,
            'address_delivery' => $request->address_delivery,
            'post_code_delivery' => $request->post_code_delivery,
        ]);


        if(count($request->contacts) > 0){

            Contact::where('contactable_type' ,'App\Models\Customer')
            ->where('contactable_id', $customer->id)
            ->delete();

            foreach ($request->contacts as $contact) {

                Contact::create(
                    [
                    'contactable_type' => 'App\Models\Customer',
                    'contactable_id' => $customer->id,
                    'first_name' => $contact['first_name'],
                    'last_name' => $contact['last_name'],
                    'work_phone' => $contact['work_phone'],
                    'email' => $contact['email'],
                    ]);

            }
        }

        return response(['message' => 'Customer updated!'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        Customer::where('id', $customer->id)->delete();

        return response(['message' => 'Cliente eliminado!'], 200);
    }
}
