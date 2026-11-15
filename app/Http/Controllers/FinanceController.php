<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\PurchaseInvoice;
use App\Models\Voucher;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FinanceController extends Controller
{
    public function index()
    {
        $accounts = FinancialAccount::all();
        $vouchers = Voucher::with(['account', 'contraAccount', 'branch'])->latest('voucher_date')->take(25)->get();
        $expenses = Expense::with('branch')->latest('expense_date')->take(20)->get();
        $branches = Branch::where('is_active', true)->get();

        $totalSales = Invoice::where('status', '!=', 'cancelled')->sum('grand_total');
        $totalMakingRevenue = Invoice::where('status', '!=', 'cancelled')->sum('making_total');
        $totalPurchases = PurchaseInvoice::sum('total_amount');
        $totalExpenses = Expense::sum('amount');
        $totalCgst = Invoice::where('status', '!=', 'cancelled')->sum('cgst_amount');
        $totalSgst = Invoice::where('status', '!=', 'cancelled')->sum('sgst_amount');
        $totalIgst = Invoice::where('status', '!=', 'cancelled')->sum('igst_amount');
        $inputGst = PurchaseInvoice::sum('gst_amount');
        $netReceivables = Invoice::where('status', '!=', 'cancelled')->sum('balance_due');
        $netPayables = PurchaseInvoice::selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as due')->value('due');

        return view('finance.index', compact(
            'accounts', 'vouchers', 'expenses', 'branches',
            'totalSales', 'totalMakingRevenue', 'totalPurchases', 'totalExpenses',
            'totalCgst', 'totalSgst', 'totalIgst', 'inputGst', 'netReceivables', 'netPayables'
        ));
    }

    public function storeVoucher(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'type' => 'required|in:payment,receipt,journal,contra',
            'account_id' => 'required|exists:financial_accounts,id',
            'contra_account_id' => 'nullable|exists:financial_accounts,id',
            'amount' => 'required|numeric|min:1',
            'voucher_date' => 'required|date',
            'narration' => 'required|string',
        ]);

        $voucher = Voucher::create($data + [
            'voucher_no' => 'VCH-' . strtoupper(Str::random(6)),
            'created_by' => Auth::id(),
        ]);

        $acc = FinancialAccount::find($data['account_id']);
        if ($data['type'] === 'receipt') {
            $acc->increment('balance', $data['amount']);
        } elseif ($data['type'] === 'payment') {
            $acc->decrement('balance', $data['amount']);
        }

        return back()->with('success', "Voucher {$voucher->voucher_no} posted.");
    }

    public function storeExpense(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'category' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'expense_date' => 'required|date',
            'paid_via' => 'required|string',
            'description' => 'required|string',
        ]);

        Expense::create($data + ['created_by' => Auth::id()]);
        return back()->with('success', 'Expense recorded.');
    }

    public function destroyVoucher(Voucher $voucher)
    {
        $vchNo = $voucher->voucher_no;
        $voucher->delete();
        AuditService::log('Finance', 'delete_voucher', "Deleted voucher {$vchNo}");

        return back()->with('success', "Voucher {$vchNo} deleted.");
    }

    public function destroyExpense(Expense $expense)
    {
        $category = $expense->category;
        $amount = $expense->amount;
        $expense->delete();
        AuditService::log('Finance', 'delete_expense', "Deleted expense {$category} (₹{$amount})");

        return back()->with('success', "Expense {$category} (₹{$amount}) deleted.");
    }
}

