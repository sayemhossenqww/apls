<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Settings;
use App\Models\Supplier;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $purchases = Purchase::search(trim($request->search_query))
            ->with([
                'supplier' => function ($query) use ($request) {
                    $query->where('name', 'LIKE', "%{$request->supplier_name}%");
                },
                'purchase_details.product'
            ])
            ->where('date', 'LIKE', "%{$request->date}%")
            ->where('number', 'LIKE', "%{$request->purchase_number}%")
            ->orderBy('date', 'DESC')
            ->paginate(20);

        // Transform response structure
        $formattedData = [];

        foreach ($purchases as $purchase) {
            foreach ($purchase->purchase_details as $detail) {
                $product = $detail->product;

                // Skip if product is null
                if (!$product)
                    continue;

                // Find or create a category-like structure in the response
                $categoryIndex = array_search($product->category_id, array_column($formattedData, 'id'));

                if ($categoryIndex === false) {
                    // Create a new entry for this category
                    $formattedData[] = [
                        'id' => $product->category_id ?? null, // Use null if category_id is unavailable
                        'name' => $purchase->shipment_name ?? 'Unknown',
                        'image_url' => $product->image_url ?? url('/images/placeholder.webp'),
                        'products' => []
                    ];
                    $categoryIndex = count($formattedData) - 1;
                }

                // Add product details under the correct category
                $formattedData[$categoryIndex]['products'][] = [
                    'id' => $product->id,
                    'full_name' => $product->full_name ?? $product->name,
                    'name' => $product->name,
                    'price' => $detail->cost * $detail->quantity, // Assuming price = cost * quantity
                    'wholesale_price' => $product->wholesale_price,
                    'retailsale_price' => $product->retailsale_price,
                    'image_url' => $product->image_url ?? url('/images/placeholder.webp'),
                    'barcode' => $product->barcode ?? null,
                    'wholesale_barcode' => $product->wholesale_barcode ?? null,
                    'retail_barcode' => $product->retail_barcode ?? null,
                    'sku' => $product->sku ?? null,
                    'wholesale_sku' => $product->wholesale_sku ?? null,
                    'retail_sku' => $product->retail_sku ?? null,
                    'in_stock' => $product->in_stock,
                    'track_stock' => $product->track_stock,
                    'continue_selling_when_out_of_stock' => $product->continue_selling_when_out_of_stock,
                ];
            }
        }

        return response()->json([
            'data' => $formattedData,
            'status' => 'ok'
        ]);
    }


    public function create()
    {
        $suppliers = Supplier::orderBy('name')->get();
        $categories = Category::with('products')->orderBy('sort_order', 'ASC')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'suppliers' => $suppliers,
                'currency' => Settings::getValue(Settings::CURRENCY_SYMBOL),
            ]
        ]);
    }

    public function show(Purchase $purchase)
    {
        return response()->json([
            'success' => true,
            'data' => $purchase
        ]);
    }

    public function destroy(Purchase $purchase)
    {
        foreach ($purchase->purchase_details as $detail) {
            $item = Product::find($detail->product_id);
            if (!$item) {
                return response()->json(['success' => false, 'message' => 'Item Not Found'], 404);
            }

            $newCost = (($item->in_stock * $item->cost) - ($detail->quantity * $detail->cost)) / ($item->in_stock - $detail->quantity);
            $item->in_stock -= $detail->quantity;
            $item->cost = round($newCost, 2);
            $item->save();
        }

        $purchase->delete();

        return response()->json([
            'success' => true,
            'message' => 'Purchase deleted successfully'
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'item.*' => ['required', 'string'],
            'cost.*' => ['nullable', 'numeric', 'min:0'],
            'quantity.*' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string'],
            'reference_number' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'date' => ['required', 'date'],
        ]);

        $items = $request->item;
        if (!$items) {
            return response()->json(['success' => false, 'message' => 'No item selected'], 400);
        }

        $purchase = Purchase::create([
            'supplier_id' => $request->supplier,
            'reference_number' => $request->reference_number,
            'notes' => $request->notes,
            'date' => $request->date ?? now(),
        ]);

        $costs = $request->cost;
        $quantities = $request->quantity;

        for ($count = 0; $count < count($items); $count++) {
            $item = Product::find($items[$count]);
            if (!$item) {
                return response()->json(['success' => false, 'message' => 'Item Not Found'], 404);
            }

            $newCost = (($item->in_stock * $item->cost) + ($quantities[$count] * $costs[$count])) / ($item->in_stock + $quantities[$count]);
            $item->in_stock += $quantities[$count];
            $item->cost = $newCost;
            $item->save();

            PurchaseDetail::create([
                'purchase_id' => $purchase->id,
                'product_id' => $item->id,
                'cost' => $costs[$count],
                'quantity' => $quantities[$count],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Purchase created successfully',
            'data' => $purchase
        ]);
    }

    public function update(Request $request, Purchase $purchase)
    {
        $request->validate([
            'item.*' => ['required', 'string'],
            'cost.*' => ['nullable', 'numeric', 'min:0'],
            'quantity.*' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string'],
            'reference_number' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'date' => ['required', 'date'],
        ]);

        $items = $request->item;
        if (!$items) {
            return response()->json(['success' => false, 'message' => 'No item selected'], 400);
        }

        $purchase->update([
            'supplier_id' => $request->supplier,
            'reference_number' => $request->reference_number,
            'notes' => $request->notes,
            'date' => $request->date ?? now(),
        ]);

        foreach ($purchase->purchase_details as $detail) {
            $item = Product::find($detail->product_id);
            if (!$item) {
                return response()->json(['success' => false, 'message' => 'Item Not Found'], 404);
            }

            $newCost = (($item->in_stock * $item->cost) - ($detail->quantity * $detail->cost)) / ($item->in_stock - $detail->quantity);
            $item->in_stock -= $detail->quantity;
            $item->cost = round($newCost, 2);
            $item->save();

            $detail->delete();
        }

        $costs = $request->cost;
        $quantities = $request->quantity;

        for ($count = 0; $count < count($items); $count++) {
            $item = Product::find($items[$count]);
            if (!$item) {
                return response()->json(['success' => false, 'message' => 'Item Not Found'], 404);
            }

            $newCost = (($item->in_stock * $item->cost) + ($quantities[$count] * $costs[$count])) / ($item->in_stock + $quantities[$count]);
            $item->in_stock += $quantities[$count];
            $item->cost = $newCost;
            $item->save();

            PurchaseDetail::create([
                'purchase_id' => $purchase->id,
                'product_id' => $item->id,
                'cost' => $costs[$count],
                'quantity' => $quantities[$count],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Purchase updated successfully',
            'data' => $purchase
        ]);
    }
}
