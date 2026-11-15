<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\LeaveRequest;
use App\Models\LoginHistory;
use App\Models\OldGoldPurchase;
use App\Models\PurchaseInvoice;
use App\Models\RolePermission;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminBranchController extends Controller
{
    public function staff()
    {
        $users = User::with('branch')->latest()->get();
        $branches = Branch::where('is_active', true)->get();
        $attendances = Attendance::with(['user', 'branch'])->whereDate('date', today())->get();
        $leaves = LeaveRequest::with('user')->latest()->take(15)->get();
        $salaries = SalaryPayment::with('user')->latest()->take(15)->get();

        return view('admin.staff', compact('users', 'branches', 'attendances', 'leaves', 'salaries'));
    }

    public function storeStaff(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'branch_id' => 'required|exists:branches,id',
            'role' => 'required|string',
            'phone' => 'nullable|string',
            'salary' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0',
            'monthly_target' => 'nullable|numeric|min:0',
        ]);

        $next = (User::max('id') ?? 0) + 1;
        User::create([
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'phone' => $data['phone'] ?? null,
            'employee_code' => 'EMP-' . str_pad($next, 3, '0', STR_PAD_LEFT),
            'salary' => $data['salary'] ?? 0,
            'commission_rate' => $data['commission_rate'] ?? 0,
            'monthly_target' => $data['monthly_target'] ?? 0,
        ]);

        return back()->with('success', 'Employee registered.');
    }

    public function destroyStaff(User $user)
    {
        if (Auth::id() === $user->id) {
            return back()->withErrors(['staff' => 'Cannot delete your own active account.']);
        }

        $name = $user->name;
        $user->delete();
        AuditService::log('Staff', 'delete', "Deleted employee {$name}");

        return back()->with('success', "Employee {$name} deleted successfully.");
    }

    public function markAttendance(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'shift' => 'required|string',
            'status' => 'required|in:present,absent,half_day',
        ]);

        $user = User::findOrFail($data['user_id']);
        Attendance::updateOrCreate(
            ['user_id' => $user->id, 'date' => today()],
            [
                'branch_id' => $user->branch_id ?? Branch::first()->id,
                'check_in' => now()->format('H:i:s'),
                'shift' => $data['shift'],
                'status' => $data['status'],
            ]
        );

        return back()->with('success', "Attendance marked for {$user->name}.");
    }

    public function branches()
    {
        $branches = Branch::withCount(['users', 'inventoryItems', 'invoices'])
            ->withSum(['invoices as total_sales' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'grand_total')
            ->withSum(['inventoryItems as total_net_weight' => fn ($q) => $q->where('status', 'available')], 'net_weight')
            ->get();
        $counters = Counter::with('branch')->get();

        return view('admin.branches', compact('branches', 'counters'));
    }

    public function storeBranch(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:branches,code',
            'phone' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'gstin' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        Branch::create($data);
        return back()->with('success', 'Showroom branch created.');
    }

    public function destroyBranch(Branch $branch)
    {
        if ($branch->is_head_office) {
            return back()->withErrors(['branch' => 'Head Office branch cannot be deleted.']);
        }

        $name = $branch->name;
        $branch->delete();
        AuditService::log('Branch', 'delete', "Deleted branch {$name}");

        return back()->with('success', "Branch {$name} deleted successfully.");
    }

    public function securityAndApprovals()
    {
        $approvals = ApprovalRequest::with(['requester', 'approver', 'branch'])->latest()->get();
        $loginHistories = LoginHistory::with('user')->latest('logged_in_at')->take(30)->get();
        $activityLogs = ActivityLog::with(['user', 'branch'])->latest()->take(40)->get();
        $rolePermissions = RolePermission::all();

        return view('admin.security', compact('approvals', 'loginHistories', 'activityLogs', 'rolePermissions'));
    }

    public function decideApproval(ApprovalRequest $approval, Request $request)
    {
        $status = $request->validate(['status' => 'required|in:approved,rejected'])['status'];
        $approval->update([
            'status' => $status,
            'approved_by' => Auth::id(),
            'decision_notes' => $request->input('decision_notes'),
            'decided_at' => now(),
        ]);

        AuditService::log('Approvals', $status, "Approval #{$approval->id} ({$approval->type}) {$status}", $approval);
        return back()->with('success', "Request #{$approval->id} {$status}.");
    }

    public function reports(Request $request)
    {
        $dailySales = Invoice::where('status', '!=', 'cancelled')->whereDate('billed_at', today())->sum('grand_total');
        $monthlySales = Invoice::where('status', '!=', 'cancelled')->whereMonth('billed_at', now()->month)->sum('grand_total');

        $puritySales = InvoiceItem::select('purity', DB::raw('SUM(net_weight) as weight'), DB::raw('SUM(line_total) as total'))
            ->groupBy('purity')->get();

        $metalStock = InventoryItem::join('products', 'inventory_items.product_id', '=', 'products.id')
            ->where('inventory_items.status', 'available')
            ->select('products.metal_type', 'inventory_items.purity', DB::raw('COUNT(*) as pieces'), DB::raw('SUM(inventory_items.net_weight) as net_weight'))
            ->groupBy('products.metal_type', 'inventory_items.purity')
            ->get();

        $branchComparison = Branch::withSum(['invoices as sales' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'grand_total')
            ->withSum(['invoices as making_revenue' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'making_total')
            ->get();

        $staffPerformance = User::whereIn('role', ['salesperson', 'manager', 'admin'])
            ->withCount(['salesInvoices as invoice_count' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->withSum(['salesInvoices as total_sales' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'grand_total')
            ->get();

        $oldGoldTotal = OldGoldPurchase::sum('valuation_amount');
        $purchaseTotal = PurchaseInvoice::sum('total_amount');

        return view('admin.reports', compact(
            'dailySales', 'monthlySales', 'puritySales', 'metalStock',
            'branchComparison', 'staffPerformance', 'oldGoldTotal', 'purchaseTotal'
        ));
    }
}

