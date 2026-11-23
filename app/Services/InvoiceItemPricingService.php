<?php

namespace App\Services;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\MetalRate;
use App\Models\Setting;
use Illuminate\Validation\ValidationException;

class InvoiceItemPricingService
{
    public function prepare(array $items, int $branchId, string $invoiceType = 'sale'): array
    {
        return collect($items)->map(function (array $input) use ($branchId, $invoiceType) {
            if (! empty($input['inventory_item_id'])) {
                $inventoryItem = InventoryItem::with('product.category')
                    ->whereKey($input['inventory_item_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $inventoryItem || (int) $inventoryItem->branch_id !== $branchId
                    || ($invoiceType !== 'estimate' && $inventoryItem->status !== 'available')) {
                    throw ValidationException::withMessages(['items' => 'A selected item is unavailable at the chosen branch. Refresh POS and try again.']);
                }

                $product = $inventoryItem->product;
                $metalType = $product->metal_type;
                $purity = $inventoryItem->purity;
                $rate = MetalRate::latestRate($metalType, $purity, $branchId);
                if ($metalType !== 'Diamond' && $rate <= 0) {
                    throw ValidationException::withMessages(['items' => "No approved {$metalType} rate is configured for {$purity}."]);
                }

                $source = [
                    'inventory_item_id' => $inventoryItem->id,
                    'product_name' => $product->name,
                    'metal_type' => $metalType,
                    'purity' => $purity,
                    'quantity' => 1,
                    'gross_weight' => (float) $inventoryItem->gross_weight,
                    'stone_weight' => (float) $inventoryItem->stone_weight,
                    'diamond_weight' => (float) $inventoryItem->diamond_weight,
                    'other_deductions' => (float) $inventoryItem->other_weight_deductions,
                    'metal_rate' => $rate,
                    'making_charge_type' => $product->making_charge_type,
                    'making_charge_rate' => (float) $product->making_charge_value,
                    'wastage_pct' => (float) $product->wastage_pct,
                    'stone_charge' => (float) $inventoryItem->stone_charge,
                    'diamond_charge' => (float) $inventoryItem->diamond_charge,
                    'hallmark_charge' => (float) $inventoryItem->hallmark_charge,
                    'other_charge' => (float) $inventoryItem->other_charge,
                    'discount' => (float) ($input['discount'] ?? 0),
                    'gst_rate' => (float) ($product->gst_rate ?? $product->category->gst_rate),
                    'sku' => $product->sku,
                    'barcode' => $inventoryItem->barcode,
                    'huid' => $inventoryItem->huid,
                    'category_name' => $product->category->name,
                    'hsn_code' => $product->hsn_code,
                ];
            } else {
                $quantity = (int) ($input['quantity'] ?? 1);
                $metalType = $input['metal_type'];
                $purity = $input['purity'];
                $rate = $metalType === 'Diamond' ? 0 : MetalRate::latestRate($metalType, $purity, $branchId);
                if ($metalType !== 'Diamond' && $rate <= 0) {
                    throw ValidationException::withMessages(['items' => "No approved {$metalType} rate is configured for {$purity}."]);
                }

                $category = ! empty($input['category_id']) ? Category::find($input['category_id']) : null;
                $source = array_merge($input, [
                    'quantity' => $quantity,
                    'metal_rate' => $rate,
                    'wastage_weight' => isset($input['wastage_weight']) && $input['wastage_weight'] !== ''
                        ? (float) $input['wastage_weight']
                        : null,
                    'gst_rate' => (float) ($input['gst_rate'] ?? $category?->gst_rate ?? Setting::get('default_gst_rate', '3')),
                    'category_name' => $category?->name ?? ($input['category_name'] ?? null),
                    'hsn_code' => $category?->hsn_code ?? ($input['hsn_code'] ?? null),
                ]);
            }

            return ['source' => $source, 'calculation' => $source];
        })->all();
    }
}
