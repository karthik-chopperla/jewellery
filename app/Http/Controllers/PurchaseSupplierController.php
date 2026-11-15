<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PurchaseSupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::withSum('purchaseInvoices', 'total_amount')
            ->withSum('purchaseInvoices', 'paid_amount')
            ->latest()
            ->get();
        $purchaseOrders = PurchaseOrder::with(['supplier', 'branch'])->latest()->take(15)->get();
        $purchaseInvoices = PurchaseInvoice::with(['supplier', 'branch'])->latest()->take(20)->get();
        $branches = Branch::where('is_active', true)->get();

        return view('purchases.index', compact('suppliers', 'purchaseOrders', 'purchaseInvoices', 'branches'));
    }

    public function storeSupplier(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'gstin' => 'nullable|string',
            'address' => 'nullable|string',
            'category' => 'required|string',
            'credit_days' => 'nullable|integer|min:0',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);

        $next = (Supplier::max('id') ?? 0) + 1;
        $data['code'] = 'SUP-' . str_pad($next, 3, '0', STR_PAD_LEFT);
        Supplier::create($data);

        return back()->with('success', 'Supplier registered.');
    }

    public function storePurchaseInvoice(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'branch_id' => 'required|exists:branches,id',
            'bill_date' => 'required|date',
            'metal_type' => 'required|string',
            'purity' => 'required|string',
            'metal_weight' => 'required|numeric|min:0',
            'stone_amount' => 'nullable|numeric|min:0',
            'making_amount' => 'nullable|numeric|min:0',
            'gst_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:1',
            'paid_amount' => 'nullable|numeric|min:0',
        ]);

        $paid = (float) ($data['paid_amount'] ?? 0);
        $total = (float) $data['total_amount'];
        $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

        $inv = PurchaseInvoice::create($data + [
            'bill_no' => 'PUR-' . strtoupper(Str::random(6)),
            'paid_amount' => $paid,
            'status' => $status,
        ]);

        if ($paid > 0) {
            SupplierPayment::create([
                'supplier_id' => $data['supplier_id'],
                'purchase_invoice_id' => $inv->id,
                'amount' => $paid,
                'method' => 'bank_transfer',
                'payment_date' => $data['bill_date'],
            ]);
        }

        AuditService::log('Purchases', 'create', "Recorded purchase bill {$inv->bill_no} for ₹{$total}", $inv);

        return back()->with('success', "Purchase bill {$inv->bill_no} recorded.");
    }

    public function paySupplier(Request $request)
    {
        $data = $request->validate([
            'purchase_invoice_id' => 'required|exists:purchase_invoices,id',
            'amount' => 'required|numeric|min:1',
            'method' => 'required|string',
            'reference_no' => 'nullable|string',
            'payment_date' => 'required|date',
        ]);

        $inv = PurchaseInvoice::findOrFail($data['purchase_invoice_id']);
        SupplierPayment::create([
            'supplier_id' => $inv->supplier_id,
            'purchase_invoice_id' => $inv->id,
            'amount' => $data['amount'],
            'method' => $data['method'],
            'reference_no' => $data['reference_no'] ?? null,
            'payment_date' => $data['payment_date'],
        ]);

        $newPaid = (float) $inv->paid_amount + (float) $data['amount'];
        $inv->update([
            'paid_amount' => $newPaid,
            'status' => $newPaid >= $inv->total_amount ? 'paid' : 'partial',
        ]);

        return back()->with('success', 'Supplier payment recorded.');
    }

    public function destroySupplier(Supplier $supplier)
    {
        $name = $supplier->name;
        $supplier->delete();

        AuditService::log('Suppliers', 'delete', "Deleted supplier {$name}");
        return back()->with('success', "Supplier {$name} deleted successfully.");
    }

    public function destroyPurchaseInvoice(PurchaseInvoice $purchaseInvoice)
    {
        $billNo = $purchaseInvoice->bill_no;
        $purchaseInvoice->payments()->delete();
        $purchaseInvoice->delete();

        AuditService::log('Purchases', 'delete', "Deleted purchase bill {$billNo}");
        return back()->with('success', "Purchase invoice {$billNo} deleted successfully.");
    }
}

