<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StockMovement;
use Illuminate\Validation\ValidationException;

class InvoiceItemWriter
{
    public function create(
        Invoice $invoice,
        array $pricedItems,
        array $calculatedItems,
        int $branchId,
        int $userId
    ): void {
        foreach ($pricedItems as $index => $preparedItem) {
            $rawItem = $preparedItem['source'];
            $calculation = $calculatedItems[$index];
            $inventoryItemId = $rawItem['inventory_item_id'] ?? null;

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'inventory_item_id' => $inventoryItemId,
                'product_name' => $rawItem['product_name'] ?? 'Jewellery Item',
                'metal_type' => $rawItem['metal_type'] ?? 'Gold',
                'purity' => $rawItem['purity'] ?? '22K',
                'quantity' => $calculation['quantity'],
                'sku' => $rawItem['sku'] ?? null,
                'barcode' => $rawItem['barcode'] ?? null,
                'huid' => $rawItem['huid'] ?? null,
                'category_name' => $rawItem['category_name'] ?? null,
                'hsn_code' => $rawItem['hsn_code'] ?? null,
                'gross_weight' => $calculation['gross_weight'],
                'stone_weight' => $calculation['stone_weight'],
                'diamond_weight' => (float) ($rawItem['diamond_weight'] ?? 0),
                'other_deductions' => $calculation['other_deductions'],
                'net_weight' => $calculation['net_weight'],
                'metal_rate' => $calculation['metal_rate'],
                'metal_value' => $calculation['metal_value'],
                'wastage_pct' => $calculation['wastage_pct'],
                'wastage_weight' => $calculation['wastage_weight'],
                'wastage_value' => $calculation['wastage_value'],
                'making_charge_type' => $calculation['making_charge_type'],
                'making_charge_rate' => $calculation['making_charge_rate'],
                'making_charge_amount' => $calculation['making_charge_amount'],
                'stone_charge' => $calculation['stone_charge'],
                'diamond_charge' => $calculation['diamond_charge'],
                'hallmark_charge' => $calculation['hallmark_charge'],
                'other_charge' => $calculation['other_charge'],
                'discount' => $calculation['discount'],
                'invoice_discount' => $calculation['invoice_discount'],
                'taxable_value' => $calculation['taxable_value'],
                'gst_rate' => $calculation['gst_rate'],
                'gst_amount' => $calculation['gst_amount'],
                'line_total' => $calculation['line_total'],
            ]);

            if ($inventoryItemId && $invoice->invoice_type !== 'estimate') {
                $updated = InventoryItem::whereKey($inventoryItemId)
                    ->where('status', 'available')
                    ->update(['status' => 'sold']);
                if ($updated !== 1) {
                    throw ValidationException::withMessages(['items' => 'An item in this cart is no longer available. Refresh POS and try again.']);
                }

                StockMovement::create([
                    'inventory_item_id' => $inventoryItemId,
                    'branch_id' => $branchId,
                    'type' => 'sale',
                    'reference' => $invoice->invoice_no,
                    'notes' => "Sold via POS {$invoice->invoice_no}",
                    'user_id' => $userId,
                ]);
            }
        }
    }
}
