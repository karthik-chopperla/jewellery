<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Branch;
use App\Models\DiamondRateConfig;
use App\Models\MetalRate;
use App\Services\AuditService;
use App\Services\JewelleryPriceCalculator;
use App\Services\LiveMetalRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class MetalRateController extends Controller
{
    public function index()
    {
        $rates = MetalRate::with(['branch', 'creator', 'approver'])->latest('effective_at')->paginate(25);
        $branches = Branch::where('is_active', true)->get();
        $diamondConfigs = DiamondRateConfig::all();

        $currentRates = [
            'Gold 24K' => MetalRate::latestRate('Gold', '24K'),
            'Gold 22K' => MetalRate::latestRate('Gold', '22K'),
            'Gold 20K' => MetalRate::latestRate('Gold', '20K'),
            'Gold 18K' => MetalRate::latestRate('Gold', '18K'),
            'Gold 14K' => MetalRate::latestRate('Gold', '14K'),
            'Silver 999' => MetalRate::latestRate('Silver', '999'),
            'Platinum 950' => MetalRate::latestRate('Platinum', '950'),
        ];

        $cities = LiveMetalRateService::CITIES;
        $selectedCity = request('city', 'guwahati');

        return view('rates.index', compact('rates', 'branches', 'diamondConfigs', 'currentRates', 'cities', 'selectedCity'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'metal_type' => 'required|string',
            'purity' => 'required|string',
            'rate_per_gram' => 'required|numeric|min:1',
            'effective_at' => 'nullable|date',
            'auto_calculate_purities' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $isAdminOrManager = Auth::user()->hasRole(['admin', 'manager']);
        $status = $isAdminOrManager ? 'approved' : 'pending';
        $effectiveAt = $data['effective_at'] ?? now();

        $rate = MetalRate::create([
            'branch_id' => $data['branch_id'] ?? null,
            'metal_type' => $data['metal_type'],
            'purity' => $data['purity'],
            'rate_per_gram' => $data['rate_per_gram'],
            'effective_at' => $effectiveAt,
            'status' => $status,
            'created_by' => Auth::id(),
            'approved_by' => $isAdminOrManager ? Auth::id() : null,
            'notes' => $data['notes'] ?? null,
        ]);

        // Auto-derive 22K, 20K, 18K, 14K, 9K if 24K Gold is entered
        if ($data['metal_type'] === 'Gold' && $data['purity'] === '24K' && $request->boolean('auto_calculate_purities')) {
            foreach (['22K', '20K', '18K', '14K', '9K'] as $purity) {
                MetalRate::create([
                    'branch_id' => $data['branch_id'] ?? null,
                    'metal_type' => 'Gold',
                    'purity' => $purity,
                    'rate_per_gram' => JewelleryPriceCalculator::deriveRateFrom24K((float) $data['rate_per_gram'], $purity),
                    'effective_at' => $effectiveAt,
                    'status' => $status,
                    'created_by' => Auth::id(),
                    'approved_by' => $isAdminOrManager ? Auth::id() : null,
                    'notes' => 'Auto-calculated from 24K rate',
                ]);
            }
        }

        if (!$isAdminOrManager) {
            ApprovalRequest::create([
                'branch_id' => Auth::user()->branch_id,
                'type' => 'rate_change',
                'subject_type' => MetalRate::class,
                'subject_id' => $rate->id,
                'requested_by' => Auth::id(),
                'reason' => "Rate update request: {$data['metal_type']} {$data['purity']} @ ₹{$data['rate_per_gram']}/g",
                'payload' => $data,
            ]);
        }

        AuditService::log('MetalRates', 'create', "Updated {$data['metal_type']} {$data['purity']} rate to ₹{$data['rate_per_gram']}/g", $rate);

        return back()->with('success', $isAdminOrManager ? 'Metal rate published.' : 'Rate change submitted for admin approval.');
    }

    public function approve(MetalRate $metalRate)
    {
        $metalRate->update(['status' => 'approved', 'approved_by' => Auth::id()]);
        AuditService::log('MetalRates', 'approve', "Approved rate #{$metalRate->id}", $metalRate);
        return back()->with('success', 'Rate approved.');
    }

    public function destroy(MetalRate $metalRate)
    {
        $info = "{$metalRate->metal_type} {$metalRate->purity} @ ₹{$metalRate->rate_per_gram}";
        $metalRate->delete();
        AuditService::log('MetalRates', 'delete', "Deleted rate {$info} (#{$metalRate->id})");

        return back()->with('success', "Rate entry for {$info} deleted.");
    }

    public function syncLiveRates(Request $request)
    {
        try {
            $city = $request->input('city', 'guwahati');
            $branchId = $request->input('branch_id') ?: null;

            $result = LiveMetalRateService::sync($city, $branchId, Auth::id());

            AuditService::log('MetalRates', 'sync_api', "Live rates synced for {$result['city']}: 24K @ ₹{$result['gold_24k']}/g, 22K @ ₹{$result['gold_22k']}/g, Silver @ ₹{$result['silver_999']}/g");

            return back()->with('success', "Live market rates successfully updated for {$result['city']}: 24K Gold @ ₹" . number_format($result['gold_24k'], 2) . "/g, 22K Gold @ ₹" . number_format($result['gold_22k'], 2) . "/g, Silver 999 @ ₹" . number_format($result['silver_999'], 2) . "/g.");
        } catch (\Throwable $e) {
            return back()->withErrors(['api' => 'Live market sync failed: ' . $e->getMessage()]);
        }
    }
}

