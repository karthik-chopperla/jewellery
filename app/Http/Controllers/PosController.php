<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceModification;
use App\Models\InvoicePayment;
use App\Models\LoyaltyTransaction;
use App\Models\MetalRate;
use App\Models\OldGoldPurchase;
use App\Models\SalesReturn;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CurrencyAmountInWords;
use App\Services\JewelleryPriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function index()
    {
        $branchId = Auth::user()->branch_id ?? Branch::first()?->id;
        $branches = Branch::where('is_active', true)->get();
        $customers = Customer::orderBy('name')->get(['id', 'name', 'phone', 'gstin', 'address']);
        $salespersons = User::whereIn('role', ['salesperson', 'manager', 'admin'])->get();
        $availableItems = InventoryItem::with('product')
            ->where('status', 'available')
            ->get()
            ->map(function ($item) {
                $metal = $item->product->metal_type ?? 'Gold';
                $rate = MetalRate::latestRate($metal, $item->purity, $item->branch_id);
                return [
                    'id' => $item->id,
                    'item_code' => $item->item_code,
                    'sku' => $item->product->sku ?? $item->item_code,
                    'barcode' => $item->barcode ?? '',
                    'huid' => $item->huid,
                    'name' => $item->product->name ?? $item->item_code,
                    'category_name' => $item->product->category->name ?? '',
                    'hsn_code' => $item->product->hsn_code ?? '',
                    'metal_type' => $metal,
                    'purity' => $item->purity,
                    'gross_weight' => (float) $item->gross_weight,
                    'stone_weight' => (float) $item->stone_weight,
                    'net_weight' => (float) $item->net_weight,
                    'metal_rate' => $rate,
                    'making_charge_type' => $item->product->making_charge_type ?? 'per_gram',
                    'making_charge_rate' => (float) ($item->product->making_charge_value ?? 600),
                    'wastage_pct' => (float) ($item->product->wastage_pct ?? 0),
                    'stone_charge' => (float) $item->stone_charge,
                    'diamond_charge' => (float) $item->diamond_charge,
                    'hallmark_charge' => (float) $item->hallmark_charge,
                    'other_charge' => (float) $item->other_charge,
                    'gst_rate' => (float) ($item->product->gst_rate ?? $item->product->category->gst_rate ?? 0),
                ];
            });

        $recentInvoices = Invoice::with(['customer', 'branch'])->latest('billed_at')->take(10)->get();
        $oldGoldAvailable = OldGoldPurchase::with('customer')->where('status', 'approved')->whereNull('invoice_id')->get();
        $categories = Category::orderBy('name')->get(['id', 'name', 'gst_rate']);
        $metalRateCatalog = MetalRate::where('status', 'approved')
            ->where('effective_at', '<=', now())
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->orderByDesc('effective_at')
            ->get()
            ->unique(fn ($rate) => $rate->metal_type . '|' . $rate->purity)
            ->values()
            ->map(fn ($rate) => [
                'metal_type' => $rate->metal_type,
                'purity' => $rate->purity,
                'rate' => (float) $rate->rate_per_gram,
            ]);

        return view('pos.index', compact(
            'branchId', 'branches', 'customers', 'salespersons',
            'availableItems', 'recentInvoices', 'oldGoldAvailable', 'categories', 'metalRateCatalog'
        ));
    }

    public function calculatePreview(Request $request, JewelleryPriceCalculator $calculator)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:100',
            'items.*.quantity' => 'nullable|integer|min:1|max:1000',
            'items.*.gross_weight' => 'required|numeric|min:0|max:100000',
            'items.*.stone_weight' => 'nullable|numeric|min:0|max:100000',
            'items.*.other_deductions' => 'nullable|numeric|min:0|max:100000',
            'items.*.metal_rate' => 'nullable|numeric|min:0|max:100000000',
            'items.*.wastage_pct' => 'nullable|numeric|min:0|max:100',
            'items.*.wastage_weight' => 'nullable|numeric|min:0|max:100000',
            'items.*.making_charge_type' => 'required|in:fixed,per_gram,percentage',
            'items.*.making_charge_rate' => 'required|numeric|min:0|max:100000000',
            'items.*.stone_charge' => 'nullable|numeric|min:0',
            'items.*.diamond_charge' => 'nullable|numeric|min:0',
            'items.*.hallmark_charge' => 'nullable|numeric|min:0',
            'items.*.other_charge' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.gst_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'old_gold_deduction' => 'nullable|numeric|min:0',
            'gst_type' => 'required|in:intra,inter',
            'rounding_mode' => 'nullable|in:nearest_rupee,nearest_5,nearest_10,none',
            'advance_adjusted' => 'nullable|numeric|min:0',
            'scheme_adjusted' => 'nullable|numeric|min:0',
        ]);
        try {
            return response()->json($calculator->calculateInvoice($validated['items'], $validated));
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['items' => $exception->getMessage()]);
        }
    }

    public function store(Request $request, JewelleryPriceCalculator $calculator)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'customer_gstin' => ['nullable', 'string', 'max:15'],
            'customer_address' => 'nullable|string|max:2000',
            'salesperson_id' => 'nullable|exists:users,id',
            'invoice_type' => 'required|in:sale,credit,estimate',
            'gst_type' => 'required|in:intra,inter',
            'discount_amount' => 'nullable|numeric|min:0',
            'advance_adjusted' => 'nullable|numeric|min:0',
            'scheme_adjusted' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1|max:100',
            'items.*.inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
            'items.*.category_id' => 'nullable|integer|exists:categories,id',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.metal_type' => 'required|in:Gold,Silver,Platinum,Diamond',
            'items.*.purity' => 'required|string|max:20',
            'items.*.quantity' => 'nullable|integer|min:1|max:1000',
            'items.*.gross_weight' => 'required|numeric|min:0|max:100000',
            'items.*.stone_weight' => 'nullable|numeric|min:0|max:100000',
            'items.*.other_deductions' => 'nullable|numeric|min:0|max:100000',
            'items.*.making_charge_type' => 'required|in:fixed,per_gram,percentage',
            'items.*.making_charge_rate' => 'required|numeric|min:0|max:100000000',
            'items.*.wastage_pct' => 'nullable|numeric|min:0|max:100',
            'items.*.wastage_weight' => 'nullable|numeric|min:0|max:100000',
            'items.*.stone_charge' => 'nullable|numeric|min:0',
            'items.*.diamond_charge' => 'nullable|numeric|min:0',
            'items.*.hallmark_charge' => 'nullable|numeric|min:0',
            'items.*.other_charge' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.gst_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.sku' => 'nullable|string|max:40',
            'items.*.barcode' => 'nullable|string|max:60',
            'items.*.huid' => 'nullable|string|max:30',
            'items.*.hsn_code' => 'nullable|string|max:20',
            'items.*.category_name' => 'nullable|string|max:255',
            'old_gold' => 'nullable|array',
            'old_gold.metal_type' => 'required_with:old_gold|in:Gold,Silver,Platinum',
            'old_gold.item_description' => 'required_with:old_gold|string|max:255',
            'old_gold.purity' => 'required_with:old_gold|string|max:20',
            'old_gold.purity_pct' => 'required_with:old_gold|numeric|min:0.01|max:100',
            'old_gold.gross_weight' => 'required_with:old_gold|numeric|gt:0|max:100000',
            'old_gold.stone_weight' => 'nullable|numeric|min:0|max:100000',
            'old_gold.other_deductions' => 'nullable|numeric|min:0|max:100000',
            'old_gold.deduction_pct' => 'nullable|numeric|min:0|max:50',
            'old_gold.melting_loss_pct' => 'nullable|numeric|min:0|max:25',
            'old_gold.acknowledgement' => 'exclude_without:old_gold.gross_weight|accepted',
            'payments' => 'nullable|array|max:10',
            'payments.*.method' => 'required_with:payments|in:cash,upi,card,bank_transfer',
            'payments.*.amount' => 'required_with:payments|numeric|min:0',
            'payments.*.reference_no' => 'nullable|string|max:80',
            'payment_method' => 'nullable|in:cash,upi,card,bank_transfer',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_reference' => 'nullable|string|max:80',
            'notes' => 'nullable|string|max:2000',
        ]);

        return DB::transaction(function () use ($request, $validated, $calculator) {
            $branchId = (int) $validated['branch_id'];
            $invoiceType = $validated['invoice_type'];
            $pricedItems = $this->prepareInvoiceItems($validated['items'], $branchId, $invoiceType);
            $oldGold = $this->prepareOldGold($validated['old_gold'] ?? null, $branchId, $calculator);
            $options = [
                'discount_amount' => (float) ($validated['discount_amount'] ?? 0),
                'gst_type' => $validated['gst_type'],
                'old_gold_deduction' => $oldGold['valuation_amount'] ?? 0,
                'advance_adjusted' => (float) ($validated['advance_adjusted'] ?? 0),
                'scheme_adjusted' => (float) ($validated['scheme_adjusted'] ?? 0),
            ];
            try {
                $calc = $calculator->calculateInvoice(array_column($pricedItems, 'calculation'), $options);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['items' => $exception->getMessage()]);
            }

            $payments = $validated['payments'] ?? [];
            if (! array_key_exists('payments', $validated) && (float) ($validated['paid_amount'] ?? 0) > 0) {
                if (empty($validated['payment_method'])) {
                    throw ValidationException::withMessages(['payment_method' => 'Select a payment method for the amount received.']);
                }
                $payments[] = [
                    'method' => $validated['payment_method'],
                    'amount' => $validated['paid_amount'],
                    'reference_no' => $validated['payment_reference'] ?? null,
                ];
            }
            if ($invoiceType === 'estimate') {
                $payments = [];
            }
            $payments = array_values(array_filter($payments, fn ($payment) => (float) $payment['amount'] > 0));
            $amountReceived = round(array_sum(array_map(
                fn ($payment) => (float) $payment['amount'],
                $payments
            )), 2);
            $grandTotal = (float) $calc['grand_total'];
            $paidAmount = min($grandTotal, $amountReceived);
            $balanceDue = round(max(0, $grandTotal - $paidAmount), 2);
            $changeReturn = round(max(0, $amountReceived - $grandTotal), 2);
            $status = $invoiceType === 'estimate' ? 'estimate' : ($balanceDue <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'credit'));
            $customer = isset($validated['customer_id']) ? Customer::find($validated['customer_id']) : null;

            $invoice = Invoice::create([
                'invoice_no' => 'INV-' . now()->format('Y') . '-' . Str::upper((string) Str::ulid()),
                'branch_id' => $branchId,
                'customer_id' => $customer?->id,
                'customer_name_snapshot' => $validated['customer_name'] ?? $customer?->name,
                'customer_phone_snapshot' => $validated['customer_phone'] ?? $customer?->phone,
                'customer_gstin' => $validated['customer_gstin'] ?? $customer?->gstin,
                'customer_address' => $validated['customer_address'] ?? $customer?->address,
                'salesperson_id' => $validated['salesperson_id'] ?? Auth::id(),
                'cashier_id' => Auth::id(),
                'invoice_type' => $invoiceType,
                'gst_type' => $validated['gst_type'],
                'subtotal' => $calc['subtotal'],
                'making_total' => $calc['making_total'],
                'stone_total' => $calc['stone_total'],
                'discount_amount' => $calc['discount_amount'],
                'cgst_amount' => $calc['cgst_amount'],
                'sgst_amount' => $calc['sgst_amount'],
                'igst_amount' => $calc['igst_amount'],
                'round_off' => $calc['round_off'],
                'old_gold_deduction' => $calc['old_gold_deduction'],
                'advance_adjusted' => $calc['advance_adjusted'],
                'scheme_adjusted' => $calc['scheme_adjusted'],
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'amount_received' => $amountReceived,
                'change_return' => $changeReturn,
                'status' => $status,
                'notes' => $validated['notes'] ?? null,
                'billed_at' => now(),
            ]);

            foreach ($pricedItems as $idx => $preparedItem) {
                $rawItem = $preparedItem['source'];
                $ci = $calc['items'][$idx];
                $invItemId = $rawItem['inventory_item_id'] ?? null;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'inventory_item_id' => $invItemId,
                    'product_name' => $rawItem['product_name'] ?? 'Jewellery Item',
                    'metal_type' => $rawItem['metal_type'] ?? 'Gold',
                    'purity' => $rawItem['purity'] ?? '22K',
                    'quantity' => $ci['quantity'],
                    'sku' => $rawItem['sku'] ?? null,
                    'barcode' => $rawItem['barcode'] ?? null,
                    'huid' => $rawItem['huid'] ?? null,
                    'category_name' => $rawItem['category_name'] ?? null,
                    'hsn_code' => $rawItem['hsn_code'] ?? null,
                    'gross_weight' => $ci['gross_weight'],
                    'stone_weight' => $ci['stone_weight'],
                    'other_deductions' => $ci['other_deductions'],
                    'net_weight' => $ci['net_weight'],
                    'metal_rate' => $ci['metal_rate'],
                    'metal_value' => $ci['metal_value'],
                    'wastage_pct' => $ci['wastage_pct'],
                    'wastage_weight' => $ci['wastage_weight'],
                    'wastage_value' => $ci['wastage_value'],
                    'making_charge_type' => $ci['making_charge_type'],
                    'making_charge_rate' => $ci['making_charge_rate'],
                    'making_charge_amount' => $ci['making_charge_amount'],
                    'stone_charge' => $ci['stone_charge'],
                    'diamond_charge' => $ci['diamond_charge'],
                    'hallmark_charge' => $ci['hallmark_charge'],
                    'other_charge' => $ci['other_charge'],
                    'discount' => $ci['discount'],
                    'invoice_discount' => $ci['invoice_discount'],
                    'taxable_value' => $ci['taxable_value'],
                    'gst_rate' => $ci['gst_rate'],
                    'gst_amount' => $ci['gst_amount'],
                    'line_total' => $ci['line_total'],
                ]);

                if ($invItemId && $invoiceType !== 'estimate') {
                    $updated = InventoryItem::whereKey($invItemId)
                        ->where('status', 'available')
                        ->update(['status' => 'sold']);
                    if ($updated !== 1) {
                        throw ValidationException::withMessages(['items' => 'An item in this cart is no longer available. Refresh POS and try again.']);
                    }
                    StockMovement::create([
                        'inventory_item_id' => $invItemId,
                        'branch_id' => $branchId,
                        'type' => 'sale',
                        'reference' => $invoice->invoice_no,
                        'notes' => "Sold via POS {$invoice->invoice_no}",
                        'user_id' => Auth::id(),
                    ]);
                }
            }

            foreach ($payments as $payment) {
                InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference_no' => $payment['reference_no'] ?? null,
                    'paid_at' => now(),
                    'received_by' => Auth::id(),
                ]);
            }

            if ($oldGold && $invoiceType !== 'estimate') {
                OldGoldPurchase::create([
                    'receipt_no' => 'OG-' . now()->format('Y') . '-' . Str::upper((string) Str::ulid()),
                    'branch_id' => $branchId,
                    'customer_id' => $customer?->id,
                    'invoice_id' => $invoice->id,
                    'item_description' => $oldGold['item_description'],
                    'metal_type' => $oldGold['metal_type'],
                    'gross_weight' => $oldGold['gross_weight'],
                    'stone_weight' => $oldGold['stone_weight'],
                    'other_deductions' => $oldGold['other_deductions'],
                    'net_weight' => $oldGold['net_weight'],
                    'tested_purity' => $oldGold['purity'],
                    'purity_pct' => $oldGold['purity_pct'],
                    'fine_gold_weight' => $oldGold['fine_gold_weight'],
                    'applied_rate' => $oldGold['applied_rate'],
                    'deduction_pct' => $oldGold['deduction_pct'],
                    'melting_loss_pct' => $oldGold['melting_loss_pct'],
                    'valuation_amount' => $oldGold['valuation_amount'],
                    'tax_treatment' => 'customer_exchange',
                    'settlement_type' => 'exchange',
                    'customer_declaration' => true,
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                ]);
            }

            if ($customer && $invoiceType !== 'estimate') {
                if ($balanceDue > 0) {
                    $customer->increment('outstanding_balance', $balanceDue);
                }
                $earnedPoints = (int) floor($grandTotal / 500);
                if ($earnedPoints > 0) {
                    $customer->increment('loyalty_points', $earnedPoints);
                    LoyaltyTransaction::create([
                        'customer_id' => $customer->id,
                        'points' => $earnedPoints,
                        'type' => 'earn',
                        'reference' => $invoice->invoice_no,
                        'description' => "Earned on invoice {$invoice->invoice_no}",
                    ]);
                }
            }

            AuditService::log('POS', 'create_invoice', "Generated invoice {$invoice->invoice_no} for ₹{$grandTotal}", $invoice);

            return redirect()->route('pos.invoice', ['invoice' => $invoice->id])
                ->with('success', "Invoice {$invoice->invoice_no} generated successfully!");
        });
    }

    private function prepareInvoiceItems(array $items, int $branchId, string $invoiceType): array
    {
        return collect($items)->map(function (array $input) use ($branchId, $invoiceType) {
            $inventoryItem = null;
            $product = null;

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
                    'other_deductions' => 0,
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

    private function prepareOldGold(?array $input, int $branchId, JewelleryPriceCalculator $calculator): ?array
    {
        if (! $input) {
            return null;
        }

        $metalType = $input['metal_type'];
        $basePurity = ['Gold' => '24K', 'Silver' => '999', 'Platinum' => '950'][$metalType];
        $rate = MetalRate::latestRate($metalType, $basePurity, $branchId);
        if ($rate <= 0) {
            throw ValidationException::withMessages(['old_gold' => "No approved {$basePurity} {$metalType} rate is available to value this exchange."]);
        }

        $grossWeight = (float) $input['gross_weight'];
        $stoneWeight = (float) ($input['stone_weight'] ?? 0);
        $otherDeductions = (float) ($input['other_deductions'] ?? 0);
        $netWeight = $grossWeight - $stoneWeight - $otherDeductions;
        if ($netWeight <= 0) {
            throw ValidationException::withMessages(['old_gold.gross_weight' => 'Gold net weight must be greater than zero after deductions.']);
        }

        return array_merge($input, $calculator->calculateOldGold([
            'gross_weight' => $grossWeight,
            'stone_weight' => $stoneWeight + $otherDeductions,
            'purity_pct' => $input['purity_pct'],
            'deduction_pct' => $input['deduction_pct'] ?? 0,
            'melting_loss_pct' => $input['melting_loss_pct'] ?? 0,
            'applied_rate' => $rate,
        ]), [
            'other_deductions' => $otherDeductions,
            'net_weight' => round($netWeight, 3),
            'stone_weight' => $stoneWeight,
            'applied_rate' => $rate,
        ]);
    }

    public function showInvoice(Invoice $invoice, Request $request)
    {
        $invoice->load(['branch', 'customer', 'salesperson', 'cashier', 'items.inventoryItem', 'payments', 'oldGoldPurchases']);
        $format = $request->get('format', 'a4');
        $amountInWords = CurrencyAmountInWords::inr((float) $invoice->grand_total);
        return view('pos.invoice', compact('invoice', 'format', 'amountInWords'));
    }

    public function invoices(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:120',
            'status' => 'nullable|in:paid,partial,credit,estimate,cancelled,returned',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);
        $invoices = Invoice::with(['customer', 'branch', 'salesperson'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('billed_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('billed_at', '<=', $date))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('invoice_no', 'like', "%{$search}%")
                        ->orWhere('customer_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('customer_phone_snapshot', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customer) => $customer
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->latest('billed_at')
            ->paginate(25);

        $returns = SalesReturn::with(['invoice', 'customer', 'branch'])->latest()->take(15)->get();
        $returnableItems = InvoiceItem::with('invoice')
            ->whereHas('invoice', fn ($query) => $query->whereNotIn('status', ['cancelled', 'estimate']))
            ->latest()
            ->take(300)
            ->get();
        $returnedQuantities = SalesReturn::selectRaw('invoice_item_id, SUM(quantity) as quantity')
            ->whereIn('invoice_item_id', $returnableItems->pluck('id'))
            ->whereNotIn('status', ['rejected'])
            ->groupBy('invoice_item_id')
            ->pluck('quantity', 'invoice_item_id');
        $returnableItems->each(function ($item) use ($returnedQuantities) {
            $item->returnable_quantity = max(0, (int) $item->quantity - (int) $returnedQuantities->get($item->id, 0));
        });

        return view('pos.invoices_list', compact('invoices', 'returns', 'returnableItems'));
    }

    public function cancelInvoice(Invoice $invoice, Request $request)
    {
        $invoice->update(['status' => 'cancelled']);
        foreach ($invoice->items as $item) {
            if ($item->inventory_item_id) {
                InventoryItem::where('id', $item->inventory_item_id)->update(['status' => 'available']);
            }
        }
        InvoiceModification::create([
            'invoice_id' => $invoice->id,
            'user_id' => Auth::id(),
            'action' => 'cancelled',
            'details' => $request->input('reason', 'Cancelled by user'),
        ]);
        AuditService::log('POS', 'cancel', "Cancelled invoice {$invoice->invoice_no}", $invoice);

        return back()->with('success', "Invoice {$invoice->invoice_no} cancelled and stock restored.");
    }

    public function storeReturn(Request $request)
    {
        $data = $request->validate([
            'invoice_id' => 'nullable|exists:invoices,id',
            'invoice_item_id' => 'required|exists:invoice_items,id',
            'return_type' => 'required|in:return,exchange,refund',
            'quantity' => 'required|integer|min:1',
            'refund_amount' => 'nullable|numeric|min:0',
            'refund_method' => 'nullable|in:cash,upi,card,bank_transfer',
            'condition' => 'required|string|max:30',
            'reason' => 'required|string|max:255',
        ]);

        return DB::transaction(function () use ($data) {
            $item = InvoiceItem::whereKey($data['invoice_item_id'])->firstOrFail();
            $invoiceId = $item->invoice_id;
            if (! empty($data['invoice_id']) && (int) $data['invoice_id'] !== (int) $invoiceId) {
                throw ValidationException::withMessages(['invoice_item_id' => 'Selected invoice item does not belong to the selected invoice.']);
            }
            $invoice = Invoice::whereKey($invoiceId)->lockForUpdate()->firstOrFail();
            if (in_array($invoice->status, ['cancelled', 'estimate'], true)) {
                throw ValidationException::withMessages(['invoice_id' => 'Returns are not available for cancelled invoices or estimates.']);
            }

            $previousQuantity = (int) SalesReturn::where('invoice_item_id', $item->id)
                ->whereNotIn('status', ['rejected'])
                ->sum('quantity');
            $remainingQuantity = max(0, (int) $item->quantity - $previousQuantity);
            if ($data['quantity'] > $remainingQuantity) {
                throw ValidationException::withMessages(['quantity' => "Only {$remainingQuantity} item(s) remain returnable on this invoice line."]);
            }

            $returnQuantity = max(1, (int) $item->quantity);
            $returnTaxable = round(((float) $item->taxable_value / $returnQuantity) * $data['quantity'], 2);
            $returnGst = round(((float) $item->gst_amount / $returnQuantity) * $data['quantity'], 2);
            $returnValue = round($returnTaxable + $returnGst, 2);
            $refundAmount = match ($data['return_type']) {
                'refund' => (float) ($data['refund_amount'] ?? $returnValue),
                default => 0.0,
            };
            if ($refundAmount > $returnValue) {
                throw ValidationException::withMessages(['refund_amount' => 'Refund cannot exceed the returned item value.']);
            }
            if ($refundAmount > 0 && empty($data['refund_method'])) {
                throw ValidationException::withMessages(['refund_method' => 'Select how the refund will be paid.']);
            }

            $approved = Auth::user()->hasRole(['admin', 'manager']);
            $returnCgst = $invoice->gst_type === 'intra' ? round($returnGst / 2, 2) : 0;
            $returnSgst = $invoice->gst_type === 'intra' ? round($returnGst - $returnCgst, 2) : 0;
            $salesReturn = SalesReturn::create([
                'return_no' => 'RET-' . now()->format('Y') . '-' . Str::upper((string) Str::ulid()),
                'credit_note_no' => 'CN-' . now()->format('Y') . '-' . Str::upper((string) Str::ulid()),
                'invoice_id' => $invoice->id,
                'invoice_item_id' => $item->id,
                'customer_id' => $invoice->customer_id,
                'branch_id' => $invoice->branch_id,
                'return_type' => $data['return_type'],
                'condition' => $data['condition'],
                'return_weight' => round((float) $item->gross_weight * $data['quantity'], 3),
                'tested_purity' => $item->purity,
                'quantity' => $data['quantity'],
                'return_value' => $returnValue,
                'taxable_value' => $returnTaxable,
                'cgst_amount' => $returnCgst,
                'sgst_amount' => $returnSgst,
                'igst_amount' => $invoice->gst_type === 'inter' ? $returnGst : 0,
                'refund_amount' => $refundAmount,
                'refund_method' => $data['refund_method'] ?? null,
                'reason' => $data['reason'],
                'status' => $approved ? 'approved' : 'pending_approval',
                'approved_by' => $approved ? Auth::id() : null,
            ]);

            if ($approved) {
                $this->applySalesReturn($salesReturn);
            }

            AuditService::log('SalesReturn', 'create', "Registered {$data['return_type']} {$salesReturn->return_no}", $salesReturn);
            return back()->with('success', "Sales return {$salesReturn->return_no} recorded.");
        });
    }

    public function approveReturn(SalesReturn $salesReturn)
    {
        if (! Auth::user()->hasRole(['admin', 'manager'])) {
            abort(403);
        }

        return DB::transaction(function () use ($salesReturn) {
            $salesReturn = SalesReturn::whereKey($salesReturn->id)->lockForUpdate()->firstOrFail();
            if ($salesReturn->status !== 'pending_approval') {
                throw ValidationException::withMessages(['return' => 'Only pending returns can be approved.']);
            }
            $salesReturn->update(['status' => 'approved', 'approved_by' => Auth::id()]);
            $this->applySalesReturn($salesReturn);
            AuditService::log('SalesReturn', 'approve', "Approved return {$salesReturn->return_no}", $salesReturn);

            return back()->with('success', "Return {$salesReturn->return_no} approved.");
        });
    }

    private function applySalesReturn(SalesReturn $salesReturn): void
    {
        $invoice = Invoice::whereKey($salesReturn->invoice_id)->lockForUpdate()->firstOrFail();
        $item = InvoiceItem::whereKey($salesReturn->invoice_item_id)->firstOrFail();
        if ($item->inventory_item_id) {
            $inventoryItem = InventoryItem::whereKey($item->inventory_item_id)->lockForUpdate()->firstOrFail();
            $updated = InventoryItem::whereKey($inventoryItem->id)
                ->where('status', 'sold')
                ->update(['status' => 'available']);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['return' => 'The linked inventory item is not in sold status and cannot be returned to stock.']);
            }
            StockMovement::create([
                'inventory_item_id' => $inventoryItem->id,
                'branch_id' => $invoice->branch_id,
                'type' => 'return_in',
                'reference' => $salesReturn->credit_note_no,
                'notes' => "Returned against {$invoice->invoice_no}",
                'user_id' => Auth::id(),
            ]);
        }

        $fullyReturned = $invoice->items->every(function ($invoiceItem) {
            $returned = (int) SalesReturn::where('invoice_item_id', $invoiceItem->id)
                ->where('status', 'approved')
                ->sum('quantity');
            return $returned >= (int) $invoiceItem->quantity;
        });
        if ($fullyReturned) {
            $invoice->update(['status' => 'returned']);
        }
        InvoiceModification::create([
            'invoice_id' => $invoice->id,
            'user_id' => Auth::id(),
            'action' => 'credit_note',
            'details' => "Credit note {$salesReturn->credit_note_no}; refund ₹{$salesReturn->refund_amount}",
        ]);
        if ((float) $salesReturn->refund_amount > 0) {
            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'method' => 'refund_' . $salesReturn->refund_method,
                'amount' => $salesReturn->refund_amount,
                'reference_no' => $salesReturn->credit_note_no,
                'paid_at' => now(),
                'received_by' => Auth::id(),
            ]);
        }
    }

    public function destroyInvoice(Invoice $invoice)
    {
        $invNo = $invoice->invoice_no;
        foreach ($invoice->items as $item) {
            if ($item->inventory_item_id) {
                InventoryItem::where('id', $item->inventory_item_id)->update(['status' => 'available']);
            }
        }
        $invoice->items()->delete();
        $invoice->payments()->delete();
        $invoice->modifications()->delete();
        $invoice->delete();

        AuditService::log('POS', 'delete_invoice', "Deleted invoice {$invNo}");
        return back()->with('success', "Invoice {$invNo} deleted and stock restored.");
    }

    public function destroyReturn(SalesReturn $salesReturn)
    {
        if ($salesReturn->status === 'approved') {
            throw ValidationException::withMessages(['return' => 'Approved return records cannot be deleted; preserve the credit note and stock audit trail.']);
        }
        $retNo = $salesReturn->return_no;
        $salesReturn->delete();

        AuditService::log('SalesReturn', 'delete', "Deleted return {$retNo}");
        return back()->with('success', "Sales return {$retNo} deleted.");
    }
}

