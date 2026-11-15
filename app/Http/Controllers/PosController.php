<?php

namespace App\Http\Controllers;

use App\Models\Branch;
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
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AuditService;
use App\Services\JewelleryPriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosController extends Controller
{
    public function index()
    {
        $branchId = Auth::user()->branch_id ?? Branch::first()?->id;
        $branches = Branch::where('is_active', true)->get();
        $customers = Customer::orderBy('name')->get();
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
                    'barcode' => $item->barcode,
                    'huid' => $item->huid,
                    'name' => $item->product->name ?? $item->item_code,
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
                    'gst_rate' => (float) ($item->product->gst_rate ?? 3.0),
                ];
            });

        $recentInvoices = Invoice::with(['customer', 'branch'])->latest('billed_at')->take(10)->get();
        $oldGoldAvailable = OldGoldPurchase::with('customer')->where('status', 'approved')->whereNull('invoice_id')->get();

        return view('pos.index', compact(
            'branchId', 'branches', 'customers', 'salespersons',
            'availableItems', 'recentInvoices', 'oldGoldAvailable'
        ));
    }

    public function calculatePreview(Request $request, JewelleryPriceCalculator $calculator)
    {
        $items = $request->input('items', []);
        $options = $request->only(['discount_amount', 'gst_type', 'rounding_mode', 'old_gold_deduction', 'advance_adjusted', 'scheme_adjusted']);
        return response()->json($calculator->calculateInvoice($items, $options));
    }

    public function store(Request $request, JewelleryPriceCalculator $calculator)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'items' => 'required|array|min:1',
            'payment_method' => 'required|string',
            'paid_amount' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request, $calculator) {
            $calc = $calculator->calculateInvoice(
                $request->input('items'),
                $request->only(['discount_amount', 'gst_type', 'rounding_mode', 'old_gold_deduction', 'advance_adjusted', 'scheme_adjusted'])
            );

            $paidAmount = (float) $request->input('paid_amount', 0);
            $grandTotal = (float) $calc['grand_total'];
            $balanceDue = max(0, round($grandTotal - $paidAmount, 2));
            $invoiceType = $request->input('invoice_type', 'sale');
            $status = $invoiceType === 'estimate' ? 'estimate' : ($balanceDue <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'credit'));

            $nextId = (Invoice::max('id') ?? 0) + 1;
            $invoiceNo = 'INV-' . date('Y') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'invoice_no' => $invoiceNo,
                'branch_id' => $request->branch_id,
                'customer_id' => $request->customer_id ?: null,
                'salesperson_id' => $request->salesperson_id ?: Auth::id(),
                'cashier_id' => Auth::id(),
                'invoice_type' => $invoiceType,
                'gst_type' => $request->input('gst_type', 'intra'),
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
                'status' => $status,
                'notes' => $request->notes,
                'billed_at' => now(),
            ]);

            foreach ($request->input('items') as $idx => $rawItem) {
                $ci = $calc['items'][$idx];
                $invItemId = $rawItem['inventory_item_id'] ?? null;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'inventory_item_id' => $invItemId,
                    'product_name' => $rawItem['product_name'] ?? 'Jewellery Item',
                    'metal_type' => $rawItem['metal_type'] ?? 'Gold',
                    'purity' => $rawItem['purity'] ?? '22K',
                    'gross_weight' => $ci['gross_weight'],
                    'stone_weight' => $ci['stone_weight'],
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
                    'taxable_value' => $ci['taxable_value'],
                    'gst_rate' => $ci['gst_rate'],
                    'gst_amount' => $ci['gst_amount'],
                    'line_total' => $ci['line_total'],
                ]);

                if ($invItemId && $invoiceType !== 'estimate') {
                    InventoryItem::where('id', $invItemId)->update(['status' => 'sold']);
                    StockMovement::create([
                        'inventory_item_id' => $invItemId,
                        'branch_id' => $request->branch_id,
                        'type' => 'sale',
                        'reference' => $invoiceNo,
                        'notes' => "Sold via POS {$invoiceNo}",
                        'user_id' => Auth::id(),
                    ]);
                }
            }

            if ($paidAmount > 0) {
                InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'method' => $request->payment_method,
                    'amount' => $paidAmount,
                    'reference_no' => $request->payment_reference,
                    'paid_at' => now(),
                    'received_by' => Auth::id(),
                ]);
            }

            if ($request->customer_id && $invoiceType !== 'estimate') {
                $customer = Customer::find($request->customer_id);
                if ($customer) {
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
                            'reference' => $invoiceNo,
                            'description' => "Earned on invoice {$invoiceNo}",
                        ]);
                    }
                }
            }

            AuditService::log('POS', 'create_invoice', "Generated invoice {$invoiceNo} for ₹{$grandTotal}", $invoice);

            return redirect()->route('pos.invoice', ['invoice' => $invoice->id])
                ->with('success', "Invoice {$invoiceNo} generated successfully!");
        });
    }

    public function showInvoice(Invoice $invoice, Request $request)
    {
        $invoice->load(['branch', 'customer', 'salesperson', 'cashier', 'items.inventoryItem', 'payments']);
        $format = $request->get('format', 'a4');
        return view('pos.invoice', compact('invoice', 'format'));
    }

    public function invoices(Request $request)
    {
        $invoices = Invoice::with(['customer', 'branch', 'salesperson'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('invoice_no', 'like', "%{$s}%"))
            ->latest('billed_at')
            ->paginate(25);

        $returns = SalesReturn::with(['invoice', 'customer', 'branch'])->latest()->take(15)->get();

        return view('pos.invoices_list', compact('invoices', 'returns'));
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
            'invoice_id' => 'required|exists:invoices,id',
            'return_type' => 'required|in:return,exchange,refund',
            'return_weight' => 'required|numeric|min:0',
            'tested_purity' => 'nullable|string',
            'return_value' => 'required|numeric|min:0',
            'refund_amount' => 'required|numeric|min:0',
            'condition' => 'required|string',
            'reason' => 'required|string',
        ]);

        $invoice = Invoice::findOrFail($data['invoice_id']);
        $ret = SalesReturn::create($data + [
            'return_no' => 'RET-' . strtoupper(Str::random(6)),
            'customer_id' => $invoice->customer_id,
            'branch_id' => $invoice->branch_id,
            'status' => Auth::user()->hasRole(['admin', 'manager']) ? 'approved' : 'pending_approval',
            'approved_by' => Auth::user()->hasRole(['admin', 'manager']) ? Auth::id() : null,
        ]);

        if ($ret->status === 'approved') {
            $invoice->update(['status' => 'returned']);
        }

        AuditService::log('SalesReturn', 'create', "Registered {$data['return_type']} {$ret->return_no}", $ret);
        return back()->with('success', "Sales return {$ret->return_no} recorded.");
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
        $retNo = $salesReturn->return_no;
        $salesReturn->delete();

        AuditService::log('SalesReturn', 'delete', "Deleted return {$retNo}");
        return back()->with('success', "Sales return {$retNo} deleted.");
    }
}

