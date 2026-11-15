<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\MetalRate;
use App\Models\OldGoldPurchase;
use App\Services\AuditService;
use App\Services\JewelleryPriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OldGoldController extends Controller
{
    public function index()
    {
        $purchases = OldGoldPurchase::with(['customer', 'branch'])->latest()->paginate(20);
        $customers = Customer::orderBy('name')->get();
        $branches = Branch::where('is_active', true)->get();
        $rate24k = MetalRate::latestRate('Gold', '24K');

        return view('old_gold.index', compact('purchases', 'customers', 'branches', 'rate24k'));
    }

    public function store(Request $request, JewelleryPriceCalculator $calculator)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'customer_id' => 'nullable|exists:customers,id',
            'item_description' => 'required|string',
            'gross_weight' => 'required|numeric|min:0.01',
            'stone_weight' => 'nullable|numeric|min:0',
            'tested_purity' => 'required|string',
            'purity_pct' => 'required|numeric|min:1|max:100',
            'applied_rate' => 'required|numeric|min:1',
            'deduction_pct' => 'nullable|numeric|min:0|max:50',
            'melting_loss_pct' => 'nullable|numeric|min:0|max:25',
            'settlement_type' => 'required|in:exchange,cash,bank',
            'customer_declaration' => 'accepted',
        ]);

        $calc = $calculator->calculateOldGold($data);
        $isManager = Auth::user()->hasRole(['admin', 'manager']);

        $record = OldGoldPurchase::create([
            'receipt_no' => 'OGP-' . strtoupper(Str::random(6)),
            'branch_id' => $data['branch_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'item_description' => $data['item_description'],
            'gross_weight' => $calc['gross_weight'],
            'stone_weight' => $calc['stone_weight'],
            'net_weight' => $calc['net_weight'],
            'tested_purity' => $data['tested_purity'],
            'purity_pct' => $calc['purity_pct'],
            'fine_gold_weight' => $calc['fine_gold_weight'],
            'applied_rate' => $calc['applied_rate'],
            'deduction_pct' => $calc['deduction_pct'],
            'melting_loss_pct' => $calc['melting_loss_pct'],
            'valuation_amount' => $calc['valuation_amount'],
            'settlement_type' => $data['settlement_type'],
            'customer_declaration' => true,
            'status' => $isManager ? 'approved' : 'pending_approval',
            'approved_by' => $isManager ? Auth::id() : null,
        ]);

        AuditService::log('OldGold', 'create', "Registered old gold purchase {$record->receipt_no} (₹{$record->valuation_amount})", $record);

        return back()->with('success', "Old gold receipt {$record->receipt_no} created (Valuation: ₹" . number_format($record->valuation_amount, 2) . ").");
    }

    public function transferRefinery(OldGoldPurchase $oldGoldPurchase, Request $request)
    {
        $request->validate(['refinery_name' => 'required|string']);
        $oldGoldPurchase->update([
            'status' => 'refinery_transferred',
            'refinery_name' => $request->refinery_name,
            'refinery_transfer_date' => now(),
        ]);

        return back()->with('success', "{$oldGoldPurchase->receipt_no} transferred to {$request->refinery_name}.");
    }

    public function destroy(OldGoldPurchase $oldGoldPurchase)
    {
        $rcpt = $oldGoldPurchase->receipt_no;
        $oldGoldPurchase->delete();

        AuditService::log('OldGold', 'delete', "Deleted old gold purchase {$rcpt}");
        return back()->with('success', "Old gold record {$rcpt} deleted.");
    }
}

