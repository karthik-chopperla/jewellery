<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomOrder;
use App\Models\ManufacturingOrder;
use App\Models\MetalRate;
use App\Models\RepairJob;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkshopController extends Controller
{
    public function index()
    {
        $manufacturingOrders = ManufacturingOrder::with(['branch', 'artisan'])->latest()->get();
        $customOrders = CustomOrder::with(['branch', 'customer'])->latest()->get();
        $repairJobs = RepairJob::with(['branch', 'customer', 'artisan'])->latest()->get();
        $branches = Branch::where('is_active', true)->get();
        $customers = Customer::orderBy('name')->get();
        $artisans = User::where('role', 'artisan')->get();
        $rate22k = MetalRate::latestRate('Gold', '22K');

        return view('workshop.index', compact(
            'manufacturingOrders', 'customOrders', 'repairJobs',
            'branches', 'customers', 'artisans', 'rate22k'
        ));
    }

    public function storeManufacturing(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'artisan_id' => 'nullable|exists:users,id',
            'design_name' => 'required|string',
            'metal_type' => 'required|string',
            'purity' => 'required|string',
            'issued_weight' => 'required|numeric|min:0.1',
            'expected_weight' => 'nullable|numeric|min:0',
            'labour_charge' => 'nullable|numeric|min:0',
            'expected_date' => 'nullable|date',
        ]);

        $order = ManufacturingOrder::create($data + [
            'job_no' => 'MFG-' . strtoupper(Str::random(6)),
            'stage' => 'metal_issued',
            'status' => 'in_progress',
        ]);

        AuditService::log('Manufacturing', 'create', "Issued {$order->issued_weight}g for {$order->design_name}", $order);
        return back()->with('success', "Manufacturing job {$order->job_no} created.");
    }

    public function updateManufacturingStage(ManufacturingOrder $order, Request $request)
    {
        $data = $request->validate([
            'stage' => 'required|string',
            'actual_weight' => 'nullable|numeric|min:0',
        ]);

        $updates = ['stage' => $data['stage']];
        if (isset($data['actual_weight'])) {
            $updates['actual_weight'] = $data['actual_weight'];
            $updates['metal_loss'] = max(0, round((float) $order->issued_weight - (float) $data['actual_weight'], 3));
        }
        if ($data['stage'] === 'completed') {
            $updates['status'] = 'completed';
            $updates['completed_date'] = now();
        }
        $order->update($updates);

        return back()->with('success', "Job {$order->job_no} moved to {$data['stage']}.");
    }

    public function storeCustomOrder(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'customer_id' => 'required|exists:customers,id',
            'title' => 'required|string',
            'metal_type' => 'required|string',
            'purity' => 'required|string',
            'estimated_weight' => 'required|numeric|min:0.1',
            'locked_rate' => 'nullable|numeric|min:0',
            'estimated_amount' => 'required|numeric|min:1',
            'advance_paid' => 'nullable|numeric|min:0',
            'expected_delivery' => 'nullable|date',
            'design_notes' => 'nullable|string',
        ]);

        $order = CustomOrder::create($data + [
            'order_no' => 'ORD-' . strtoupper(Str::random(6)),
            'status' => 'in_production',
        ]);

        return back()->with('success', "Custom order {$order->order_no} created.");
    }

    public function updateCustomOrderStatus(CustomOrder $order, Request $request)
    {
        $order->update(['status' => $request->validate(['status' => 'required|string'])['status']]);
        return back()->with('success', "Custom order {$order->order_no} updated.");
    }

    public function storeRepair(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'customer_id' => 'required|exists:customers,id',
            'item_description' => 'required|string',
            'receiving_weight' => 'required|numeric|min:0.01',
            'artisan_id' => 'nullable|exists:users,id',
            'repair_charges' => 'required|numeric|min:0',
            'expected_delivery' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $job = RepairJob::create($data + [
            'job_no' => 'REP-' . strtoupper(Str::random(6)),
            'status' => 'received',
        ]);

        return back()->with('success', "Repair job {$job->job_no} registered.");
    }

    public function updateRepairStatus(RepairJob $repairJob, Request $request)
    {
        $data = $request->validate([
            'status' => 'required|string',
            'paid_amount' => 'nullable|numeric|min:0',
        ]);

        $repairJob->update($data);
        return back()->with('success', "Repair {$repairJob->job_no} updated.");
    }

    public function destroyManufacturing(ManufacturingOrder $order)
    {
        $jobNo = $order->job_no;
        $order->delete();

        AuditService::log('Manufacturing', 'delete', "Deleted manufacturing order {$jobNo}");
        return back()->with('success', "Manufacturing order {$jobNo} deleted.");
    }

    public function destroyCustomOrder(CustomOrder $order)
    {
        $orderNo = $order->order_no;
        $order->delete();

        AuditService::log('CustomOrder', 'delete', "Deleted custom order {$orderNo}");
        return back()->with('success', "Custom order {$orderNo} deleted.");
    }

    public function destroyRepair(RepairJob $repairJob)
    {
        $jobNo = $repairJob->job_no;
        $repairJob->delete();

        AuditService::log('RepairJob', 'delete', "Deleted repair job {$jobNo}");
        return back()->with('success', "Repair job {$jobNo} deleted.");
    }
}

