<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Http\Requests\ItemRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $items = Item::orderBy('created_at', 'desc')->get();

        $items = $items->map(function($item){
            return [
                'id' => (string)$item->id,
                'uuid' => $item->uuid,
                'name' => $item->name,
                'type' => $item->type,
                'unite' => $item->unite,
                'sales_price' => $item->sales_price,
                'purchase_price' => $item->purchase_price,
                'reference' => $item->reference,
                'description' => $item->description,
            ];
        });
        return response(['items' => $items], 200);
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
    public function store(ItemRequest $request)
    {

        $item = Item::orderBy('created_at', 'desc')->first();

        $reference = isset($item) ? $item->id + 1 : 1;

        $new_item = Item::create([
            'uuid' => Str::uuid()->toString(),
            'reference' => 'PS-' . $reference,
            'name' => $request->name,
            'family_id' => $request->family_id,
            'type' => $request->type,
            'sales_price' => $request->sales_price,
            'unite' => $request->unite,
            'purchase_price' => $request->purchase_price,
            'description' => $request->description,
        ]);

        return response(['message' => '¡Producto añadido!'], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Item $item)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Item $item)
    {
        $item = [
            'id' => $item->id,
            'uuid' => $item->uuid,
            'type' => $item->type,
            'reference' => $item->reference,
            'name' => $item->name,
            'unite' => $item->unite,
            'family_id' => $item->family_id,
            'sales_price' => $item->sales_price,
            'purchase_price' => $item->purchase_price,
            'description' => $item->description,
        ];
        return response(['item' => $item], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ItemRequest $request, Item $item)
    {
        Item::where('uuid', $item->uuid)->update([
            'name' => $request->name,
            'family_id' => $request->family_id,
            'type' => $request->type,
            'unite' => $request->unite,
            'sales_price' => $request->sales_price,
            'purchase_price' => $request->purchase_price,
            'description' => $request->description,
        ]);

        return response(['message' => '¡Producto actualizado!'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Item $item)
    {
        Item::where('uuid', $item->uuid)->delete();

        return response(['message' => '¡Producto eliminado!'], 200);
    }
}
