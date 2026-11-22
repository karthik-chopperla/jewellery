<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoiceExchange;
use App\Models\InvoiceItem;
use App\Models\InvoiceModification;
use App\Models\InvoicePayment;
use App\Models\InventoryItem;
use App\Models\MetalRate;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Services\AuditService;
use App\Services\InvoiceItemPricingService;
use App\Services\InvoiceItemWriter;
use App\Services\InvoiceSnapshot;
use App\Services\JewelleryPriceCalculator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceExchangeController extends Controller
{
    public function create(Invoice $invoice)
    {
        abort_if(in_array($invoice->status, ['cancelled', 'estimate'], true), 404);
        $invoice->load(['branch', 'customer', 'items.inventoryItem']);
        $returnQuantities = SalesReturn::selectRaw('invoice_item_id, SUM(quantity) as quantity')
            ->whereIn('invoice_item_id', $invoice->items->pluck('id'))
            ->whereNotIn('status', ['rejected'])
            ->groupBy('invoice_item_id')
            ->pluck('quantity', 'invoice_item_id');
        $returnableItems = $invoice->items->map(function ($item) use ($returnQuantities) {
            $item->returnable_quantity = max(0, (int) $item->quantity - (int) $returnQuantities->get($item->id, 0));
            return $item;
        })->filter(fn ($item) => $item->returnable_quantity > 0);

        $replacementItems = InventoryItem::with('product.category')
            ->where('branch_id', $invoice->branch_id)
            ->where('status', 'available')
            ->orderBy('item_code')
            ->get()
            ->map(function ($item) use ($invoice) {
                $product = $item->product;
                return [
                    'id' => $item->id,
                    'item_code' => $item->item_code,
                    'sku' => $product->sku,
                    'barcode' => $item->barcode,
                    'huid' => $item->huid,
                    'name' => $product->name,
                    'metal_type' => $product->metal_type,
                    'purity' => $item->purity,
                    'gross_weight' => (float) $item->gross_weight,
                    'stone_weight' => (float) $item->stone_weight,
                    'metal_rate' => MetalRate::latestRate($product->metal_type, $item->purity, (int) $invoice->branch_id),
                    'making_charge_type' => $product->making_charge_type,
                    'making_charge_rate' => (float) $product->making_charge_value,
                    'wastage_pct' => (float) $product->wastage_pct,
                    'stone_charge' => (float) $item->stone_charge,
                    'diamond_charge' => (float) $item->diamond_charge,
                    'hallmark_charge' => (float) $item->hallmark_charge,
                    'other_charge' => (float) $item->other_charge,
                    'gst_rate' => (float) ($product->gst_rate ?? $product->category->gst_rate),
                ];
            });
        $returnCatalog = $returnableItems->map(fn ($item) => [
            'id' => $item->id,
            'product_name' => $item->product_name,
            'metal_type' => $item->metal_type,
            'purity' => $item->purity,
            'quantity' => (int) $item->quantity,
            'returnable_quantity' => $item->returnable_quantity,
            'gross_weight' => (float) $item->gross_weight,
            'stone_weight' => (float) $item->stone_weight,
            'taxable_value' => (float) $item->taxable_value,
            'gst_amount' => (float) $item->gst_amount,
            'line_total' => (float) $item->line_total,
            'item_code' => $item->sku ?: $item->inventoryItem?->item_code,
        ])->values();

        return view('pos.exchange', [
            'invoice' => $invoice,
            'returnableItems' => $returnCatalog,
            'replacementItems' => $replacementItems,
            'requestToken' => (string) Str::uuid(),
        ]);
    }

    public function store(
        Request $request,
        Invoice $invoice,
        InvoiceItemPricingService $itemPricing,
        InvoiceItemWriter $itemWriter,
        JewelleryPriceCalculator $calculator
    ) {
        $data = $request->validate([
            'request_token' => 'required|uuid',
            'return_invoice_item_id' => 'required|integer|exists:invoice_items,id',
            'return_quantity' => 'required|integer|min:1|max:1000',
            'condition' => 'required|string|max:30',
            'reason' => 'required|string|max:255',
            'gst_type' => 'required|in:intra,inter',
            'discount_amount' => 'nullable|numeric|min:0',
            'replacement_items' => 'required|array|min:1|max:50',
            'replacement_items.*.inventory_item_id' => 'required|integer|distinct|exists:inventory_items,id',
            'payments' => 'nullable|array|max:10',
            'payments.*.method' => 'required_with:payments|in:cash,upi,card,bank_transfer',
            'payments.*.amount' => 'required_with:payments|numeric|min:0',
            'payments.*.reference_no' => 'nullable|string|max:80',
            'refund_method' => 'nullable|in:cash,upi,card,bank_transfer',
        ]);

        $existing = InvoiceExchange::where('request_token', $data['request_token'])->first();
        if ($existing) {
            return redirect()->route('pos.invoice', $existing->replacement_invoice_id);
        }

        try {
            $exchange = DB::transaction(function () use ($invoice, $data, $itemPricing, $itemWriter, $calculator) {
                $original = Invoice::with(['branch', 'customer'])
                    ->whereKey($invoice->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if (in_array($original->status, ['cancelled', 'estimate'], true)) {
                    throw ValidationException::withMessages(['invoice' => 'This invoice is not eligible for exchange.']);
                }

                $returnItem = InvoiceItem::whereKey($data['return_invoice_item_id'])
                    ->where('invoice_id', $original->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $alreadyReturned = (int) SalesReturn::where('invoice_item_id', $returnItem->id)
                    ->whereNotIn('status', ['rejected'])
                    ->sum('quantity');
                $remaining = max(0, (int) $returnItem->quantity - $alreadyReturned);
                if ($data['return_quantity'] > $remaining) {
                    throw ValidationException::withMessages(['return_quantity' => "Only {$remaining} item(s) remain eligible for exchange."]);
                }

                $pricedItems = $itemPricing->prepare($data['replacement_items'], (int) $original->branch_id);
                $options = [
                    'discount_amount' => (float) ($data['discount_amount'] ?? 0),
                    'gst_type' => $data['gst_type'],
                ];
                try {
                    $calculation = $calculator->calculateInvoice(array_column($pricedItems, 'calculation'), $options);
                } catch (\InvalidArgumentException $exception) {
                    throw ValidationException::withMessages(['replacement_items' => $exception->getMessage()]);
                }

                $returnQuantity = max(1, (int) $returnItem->quantity);
                $returnTaxable = round(((float) $returnItem->taxable_value / $returnQuantity) * $data['return_quantity'], 2);
                $returnGst = round(((float) $returnItem->gst_amount / $returnQuantity) * $data['return_quantity'], 2);
                $returnValue = round($returnTaxable + $returnGst, 2);
                $replacementTotal = (float) $calculation['grand_total'];
                $creditApplied = min($returnValue, $replacementTotal);
                $refundAmount = round(max(0, $returnValue - $replacementTotal), 2);
                if ($refundAmount > 0 && empty($data['refund_method'])) {
                    throw ValidationException::withMessages(['refund_method' => 'Select a method for the exchange refund.']);
                }

                $payments = array_values(array_filter($data['payments'] ?? [], fn ($payment) => (float) $payment['amount'] > 0));
                $additionalReceived = round(array_sum(array_map(fn ($payment) => (float) $payment['amount'], $payments)), 2);
                $paidAmount = min($replacementTotal, $creditApplied + $additionalReceived);
                $balanceDue = round(max(0, $replacementTotal - $paidAmount), 2);
                $changeReturn = round(max(0, $creditApplied + $additionalReceived - $replacementTotal), 2);
                $customer = $original->customer;
                $branch = Branch::findOrFail($original->branch_id);
                $businessSnapshot = InvoiceSnapshot::forBranch($branch);

                $replacementInvoice = Invoice::create(array_merge([
                    'invoice_no' => 'INV-' . now()->format('Y') . '-' . Str::upper((string) Str::ulid()),
                    'branch_id' => $original->branch_id,
                    'customer_id' => $customer?->id,
                    'customer_name_snapshot' => $original->customer_name_snapshot ?: $customer?->name,
                    'customer_phone_snapshot' => $original->customer_phone_snapshot ?: $customer?->phone,
                    'customer_gstin' => $original->customer_gstin ?: $customer?->gstin,
                    'customer_address' => $original->customer_address ?: $customer?->address,
                    'salesperson_id' => Auth::id(),
                    'cashier_id' => Auth::id(),
                    'invoice_type' => 'sale',
                    'gst_type' => $data['gst_type'],
                    'subtotal' => $calculation['subtotal'],
                    'making_total' => $calculation['making_total'],
                    'stone_total' => $calculation['stone_total'],
                    'discount_amount' => $calculation['discount_amount'],
                    'cgst_amount' => $calculation['cgst_amount'],
                    'sgst_amount' => $calculation['sgst_amount'],
                    'igst_amount' => $calculation['igst_amount'],
                    'round_off' => $calculation['round_off'],
                    'old_gold_deduction' => 0,
                    'advance_adjusted' => 0,
                    'scheme_adjusted' => 0,
                    'grand_total' => $replacementTotal,
                    'paid_amount' => $paidAmount,
                    'balance_due' => $balanceDue,
                    'amount_received' => $additionalReceived,
                    'change_return' => $changeReturn,
                    'status' => $balanceDue <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'credit'),
                    'notes' => 'Replacement sale for invoice ' . $original->invoice_no,
                    'billed_at' => now(),
                ], $businessSnapshot));

                $returnCgst = $original->gst_type === 'intra' ? round($returnGst / 2, 2) : 0;
                $returnSgst = $original->gst_type === 'intra' ? round($returnGst - $returnCgst, 2) : 0;
                $creditNoteNo = 'CN-' . now()->format('Y') . '-' . Str::upper((string) Str::ulid());
                $salesReturn = SalesReturn::create([
                    'return_no' => 'RET-' . now()->format('Y') . '-' . Str::upper((string) Str::ulid()),
                    'credit_note_no' => $creditNoteNo,
                    'invoice_id' => $original->id,
                    'invoice_item_id' => $returnItem->id,
                    'customer_id' => $original->customer_id,
                    'branch_id' => $original->branch_id,
                    'return_type' => 'exchange',
                    'condition' => $data['condition'],
                    'return_weight' => round((float) $returnItem->gross_weight * $data['return_quantity'], 3),
                    'metal_type' => $returnItem->metal_type,
                    'stone_weight' => round((float) $returnItem->stone_weight * $data['return_quantity'], 3),
                    'tested_purity' => $returnItem->purity,
                    'quantity' => $data['return_quantity'],
                    'return_value' => $returnValue,
                    'taxable_value' => $returnTaxable,
                    'cgst_amount' => $returnCgst,
                    'sgst_amount' => $returnSgst,
                    'igst_amount' => $original->gst_type === 'inter' ? $returnGst : 0,
                    'refund_amount' => $refundAmount,
                    'refund_method' => $refundAmount > 0 ? $data['refund_method'] : null,
                    'reason' => $data['reason'],
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                ]);

                if ($returnItem->inventory_item_id) {
                    $inventoryItem = InventoryItem::whereKey($returnItem->inventory_item_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $updated = InventoryItem::whereKey($inventoryItem->id)
                        ->where('status', 'sold')
                        ->update(['status' => 'available']);
                    if ($updated !== 1) {
                        throw ValidationException::withMessages(['return_invoice_item_id' => 'The returned inventory item is not in sold status.']);
                    }
                    StockMovement::create([
                        'inventory_item_id' => $inventoryItem->id,
                        'branch_id' => $original->branch_id,
                        'type' => 'return_in',
                        'reference' => $creditNoteNo,
                        'notes' => "Exchange return against {$original->invoice_no}",
                        'user_id' => Auth::id(),
                    ]);
                }

                $itemWriter->create($replacementInvoice, $pricedItems, $calculation['items'], (int) $original->branch_id, (int) Auth::id());

                InvoicePayment::create([
                    'invoice_id' => $replacementInvoice->id,
                    'method' => 'exchange_credit',
                    'amount' => $creditApplied,
                    'reference_no' => $creditNoteNo,
                    'paid_at' => now(),
                    'received_by' => Auth::id(),
                ]);
                foreach ($payments as $payment) {
                    InvoicePayment::create([
                        'invoice_id' => $replacementInvoice->id,
                        'method' => $payment['method'],
                        'amount' => $payment['amount'],
                        'reference_no' => $payment['reference_no'] ?? null,
                        'paid_at' => now(),
                        'received_by' => Auth::id(),
                    ]);
                }
                if ($refundAmount > 0) {
                    InvoicePayment::create([
                        'invoice_id' => $original->id,
                        'method' => 'refund_' . $data['refund_method'],
                        'amount' => $refundAmount,
                        'reference_no' => $creditNoteNo,
                        'paid_at' => now(),
                        'received_by' => Auth::id(),
                    ]);
                }

                InvoiceModification::create([
                    'invoice_id' => $original->id,
                    'user_id' => Auth::id(),
                    'action' => 'exchange',
                    'details' => "Exchange {$creditNoteNo}; replacement invoice {$replacementInvoice->invoice_no}",
                ]);
                InvoiceModification::create([
                    'invoice_id' => $replacementInvoice->id,
                    'user_id' => Auth::id(),
                    'action' => 'exchange_replacement',
                    'details' => "Replacement for original invoice {$original->invoice_no}",
                ]);

                $exchange = InvoiceExchange::create([
                    'exchange_no' => 'EX-' . now()->format('Y') . '-' . Str::upper((string) Str::ulid()),
                    'request_token' => $data['request_token'],
                    'original_invoice_id' => $original->id,
                    'sales_return_id' => $salesReturn->id,
                    'replacement_invoice_id' => $replacementInvoice->id,
                    'customer_id' => $original->customer_id,
                    'branch_id' => $original->branch_id,
                    'created_by' => Auth::id(),
                    'return_value' => $returnValue,
                    'replacement_taxable_value' => max(0, (float) $calculation['subtotal'] - (float) $calculation['discount_amount']),
                    'replacement_cgst' => $calculation['cgst_amount'],
                    'replacement_sgst' => $calculation['sgst_amount'],
                    'replacement_igst' => $calculation['igst_amount'],
                    'replacement_total' => $replacementTotal,
                    'credit_applied' => $creditApplied,
                    'additional_paid' => $additionalReceived,
                    'balance_due' => $balanceDue,
                    'refund_amount' => $refundAmount,
                    'status' => 'completed',
                    'reason' => $data['reason'],
                ]);

                AuditService::log('InvoiceExchange', 'completed', "Completed {$exchange->exchange_no} from {$original->invoice_no} to {$replacementInvoice->invoice_no}", $exchange);

                return $exchange;
            });
        } catch (QueryException $exception) {
            $existing = InvoiceExchange::where('request_token', $data['request_token'])->first();
            if ($existing) {
                return redirect()->route('pos.invoice', $existing->replacement_invoice_id);
            }

            throw $exception;
        }

        return redirect()->route('pos.invoice', $exchange->replacement_invoice_id)
            ->with('success', "Exchange {$exchange->exchange_no} completed. Replacement invoice generated.");
    }
}
