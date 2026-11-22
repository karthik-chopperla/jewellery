<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;

class InventoryQrController extends Controller
{
    public function show(string $token)
    {
        $inventoryItem = InventoryItem::with('product.category')
            ->where('qr_token', $token)
            ->firstOrFail();

        return response()
            ->view('inventory.qr-lookup', [
                'item' => [
                    'item_code' => $inventoryItem->item_code,
                    'barcode' => $inventoryItem->barcode,
                    'name' => $inventoryItem->product->name ?? $inventoryItem->item_code,
                    'category' => $inventoryItem->product->category->name ?? 'Jewellery',
                    'metal_type' => $inventoryItem->product->metal_type ?? '',
                    'purity' => $inventoryItem->purity,
                    'gross_weight' => $inventoryItem->gross_weight,
                    'stone_weight' => $inventoryItem->stone_weight,
                    'net_weight' => $inventoryItem->net_weight,
                    'diamond_weight' => $inventoryItem->diamond_weight,
                    'huid' => $inventoryItem->huid,
                    'status' => $inventoryItem->status,
                ],
            ])
            ->header('Cache-Control', 'no-store, private');
    }
}
