<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Http\Requests\ItemRequest;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ItemController extends Controller
{
    public function __construct(private PlanService $planService) {}

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
                'vta' => $item->vta,
                'currency' => $item->currency,
                'active' => $item->active,
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
    public function store(ItemRequest $request): JsonResponse
    {
        if (!$this->planService->canCreateProduct()) {
            $plan  = $this->planService->currentPlan();
            $limit = $this->planService->getLimit('products');

            return response()->json([
                'error'     => 'plan_limit_reached',
                'resource'  => 'product',
                'limit'     => $limit,
                'used'      => $this->planService->totalProducts(),
                'plan_name' => $plan ? $plan->translate('name') : 'Starter',
                'plan_slug' => $plan?->slug ?? 'starter',
            ], 402);
        }

        // Users can supply their own SKU/reference; fall back to the existing
        // auto-numbered scheme (PS-1, PS-2, ...) when the field is left blank.
        $reference = $request->filled('reference')
            ? $request->reference
            : 'PS-' . ((Item::orderBy('created_at', 'desc')->first()?->id ?? 0) + 1);

        $new_item = Item::create([
            'uuid' => Str::uuid()->toString(),
            'reference' => $reference,
            'name' => $request->name,
            'family_id' => $request->family_id,
            'type' => $request->type,
            'sales_price' => $request->sales_price,
            'unite' => $request->unite,
            'purchase_price' => $request->purchase_price,
            'vta' => $request->vta ?? 0,
            'currency' => $request->currency,
            'active' => $request->boolean('active', true),
            'description' => $request->description,
        ]);

        return response()->json(['message' => '¡Producto añadido!'], 201);
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
            'vta' => $item->vta,
            'currency' => $item->currency,
            'active' => $item->active,
            'description' => $item->description,
        ];
        return response(['item' => $item], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ItemRequest $request, Item $item)
    {
        // vta/currency/active are only sent by the redesigned create form so
        // far - fall back to the item's current value when a caller (e.g.
        // the not-yet-updated edit form) doesn't send them, rather than
        // silently resetting them to a default on every unrelated edit.
        Item::where('uuid', $item->uuid)->update([
            'name' => $request->name,
            'family_id' => $request->family_id,
            'type' => $request->type,
            'unite' => $request->unite,
            'sales_price' => $request->sales_price,
            'purchase_price' => $request->purchase_price,
            'vta' => $request->input('vta', $item->vta),
            'currency' => $request->input('currency', $item->currency),
            'active' => $request->boolean('active', $item->active),
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

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $count = Item::whereIn('uuid', $validated['ids'])->delete();

        return response()->json(['message' => "$count item(s) deleted."]);
    }
}
