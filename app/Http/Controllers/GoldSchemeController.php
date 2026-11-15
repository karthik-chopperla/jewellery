<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GoldScheme;
use App\Models\SchemeInstallment;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoldSchemeController extends Controller
{
    public function index()
    {
        $schemes = GoldScheme::with(['customer', 'branch', 'installments'])->latest()->get();
        $customers = Customer::orderBy('name')->get();
        $branches = Branch::where('is_active', true)->get();

        return view('schemes.index', compact('schemes', 'customers', 'branches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'customer_id' => 'required|exists:customers,id',
            'scheme_name' => 'required|string',
            'monthly_amount' => 'required|numeric|min:500',
            'duration_months' => 'required|integer|min:3|max:36',
            'bonus_amount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
        ]);

        $start = \Carbon\Carbon::parse($data['start_date']);
        $maturity = (clone $start)->addMonths((int) $data['duration_months']);

        $scheme = GoldScheme::create($data + [
            'scheme_no' => 'SCH-' . strtoupper(Str::random(6)),
            'bonus_amount' => $data['bonus_amount'] ?? $data['monthly_amount'],
            'maturity_date' => $maturity,
            'total_paid' => 0,
            'status' => 'active',
        ]);

        for ($i = 1; $i <= $data['duration_months']; $i++) {
            SchemeInstallment::create([
                'gold_scheme_id' => $scheme->id,
                'installment_no' => $i,
                'amount' => $data['monthly_amount'],
                'due_date' => (clone $start)->addMonths($i - 1),
                'status' => 'pending',
            ]);
        }

        return back()->with('success', "Gold scheme {$scheme->scheme_no} enrolled.");
    }

    public function payInstallment(SchemeInstallment $installment, Request $request)
    {
        if ($installment->status === 'paid') {
            return back()->with('info', 'Installment already paid.');
        }

        $installment->update([
            'paid_date' => now(),
            'method' => $request->input('method', 'upi'),
            'receipt_no' => 'SCH-RCP-' . strtoupper(Str::random(5)),
            'status' => 'paid',
        ]);

        $scheme = $installment->scheme;
        $scheme->increment('total_paid', $installment->amount);

        if ($scheme->installments()->where('status', 'pending')->count() === 0) {
            $scheme->update(['status' => 'matured']);
        }

        return back()->with('success', "Installment #{$installment->installment_no} collected.");
    }

    public function destroy(GoldScheme $scheme)
    {
        $no = $scheme->scheme_no;
        $scheme->installments()->delete();
        $scheme->delete();

        AuditService::log('GoldScheme', 'delete', "Deleted scheme {$no}");
        return back()->with('success', "Gold scheme {$no} deleted successfully.");
    }
}

