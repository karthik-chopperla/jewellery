@extends('layouts.app')

@section('header', 'Karigar Manufacturing, Custom Orders & Jewellery Repairs')

@section('content')
<div class="space-y-6">
    <!-- 3 Creation Forms -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Manufacturing Job -->
        @if(auth()->user()->hasPermission('manufacturing'))
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-3">New Karigar Manufacturing Job</h2>
            <form action="{{ route('workshop.mfg.store') }}" method="POST" class="space-y-2 text-xs">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <select name="branch_id" class="border border-slate-300 rounded-lg px-2 py-2">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                    <select name="artisan_id" class="border border-slate-300 rounded-lg px-2 py-2">
                        <option value="">Assign Artisan</option>
                        @foreach($artisans as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                    </select>
                </div>
                <input type="text" name="design_name" required placeholder="Design Name" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                <div class="grid grid-cols-3 gap-2">
                    <select name="metal_type" class="border border-slate-300 rounded-lg px-2 py-2"><option>Gold</option><option>Silver</option></select>
                    <input type="text" name="purity" value="22K" class="border border-slate-300 rounded-lg px-2 py-2">
                    <input type="number" step="0.001" name="issued_weight" required placeholder="Issue Wt (g)" class="border border-slate-300 rounded-lg px-2 py-2">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <input type="number" step="1" name="labour_charge" placeholder="Labour ₹" class="border border-slate-300 rounded-lg px-2 py-2">
                    <input type="date" name="expected_date" class="border border-slate-300 rounded-lg px-2 py-2">
                </div>
                <button class="w-full bg-amber-600 text-white py-2 rounded-lg font-semibold">Issue Metal & Start Job</button>
            </form>
        </div>
        @endif

        <!-- Custom Order -->
        @if(auth()->user()->hasPermission('custom_orders'))
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-3">New Custom Jewellery Order</h2>
            <form action="{{ route('workshop.custom.store') }}" method="POST" class="space-y-2 text-xs">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <select name="branch_id" class="border border-slate-300 rounded-lg px-2 py-2">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                    <select name="customer_id" required class="border border-slate-300 rounded-lg px-2 py-2">@foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                </div>
                <input type="text" name="title" required placeholder="Custom Design Title" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                <div class="grid grid-cols-3 gap-2">
                    <select name="metal_type" class="border border-slate-300 rounded-lg px-2 py-2"><option>Gold</option><option>Silver</option><option>Platinum</option></select>
                    <input type="text" name="purity" value="22K" class="border border-slate-300 rounded-lg px-2 py-2">
                    <input type="number" step="0.001" name="estimated_weight" required placeholder="Est Wt (g)" class="border border-slate-300 rounded-lg px-2 py-2">
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <input type="number" step="1" name="locked_rate" value="{{ $rate22k }}" placeholder="Locked Rate" class="border border-slate-300 rounded-lg px-2 py-2">
                    <input type="number" step="1" name="estimated_amount" required placeholder="Est Amt ₹" class="border border-slate-300 rounded-lg px-2 py-2">
                    <input type="number" step="1" name="advance_paid" value="0" placeholder="Advance ₹" class="border border-slate-300 rounded-lg px-2 py-2">
                </div>
                <button class="w-full bg-slate-900 text-white py-2 rounded-lg font-semibold">Create Custom Order</button>
            </form>
        </div>
        @endif

        <!-- Repair Job -->
        @if(auth()->user()->hasPermission('repairs'))
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-3">Register Jewellery Repair</h2>
            <form action="{{ route('workshop.repair.store') }}" method="POST" class="space-y-2 text-xs">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <select name="branch_id" class="border border-slate-300 rounded-lg px-2 py-2">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                    <select name="customer_id" required class="border border-slate-300 rounded-lg px-2 py-2">@foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                </div>
                <input type="text" name="item_description" required placeholder="Jewellery & Repair Description" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                <div class="grid grid-cols-3 gap-2">
                    <input type="number" step="0.001" name="receiving_weight" required placeholder="Recv Wt (g)" class="border border-slate-300 rounded-lg px-2 py-2">
                    <input type="number" step="1" name="repair_charges" required placeholder="Charges ₹" class="border border-slate-300 rounded-lg px-2 py-2">
                    <input type="date" name="expected_delivery" class="border border-slate-300 rounded-lg px-2 py-2">
                </div>
                <select name="artisan_id" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                    <option value="">Assign Artisan</option>
                    @foreach($artisans as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                </select>
                <button class="w-full bg-slate-800 text-white py-2 rounded-lg font-semibold">Log Repair Job</button>
            </form>
        </div>
        @endif
    </div>

    <!-- Lists -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @if(auth()->user()->hasPermission('manufacturing'))
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">Manufacturing Job Work</h3>
            <div class="space-y-3 text-xs">
                @foreach($manufacturingOrders as $m)
                    <div class="border border-slate-200 rounded-lg p-3">
                        <div class="flex justify-between font-bold"><span>{{ $m->job_no }}</span><span class="text-amber-700">{{ $m->stage }}</span></div>
                        <div class="font-medium mt-0.5">{{ $m->design_name }} ({{ $m->purity }})</div>
                        <div class="text-slate-500">Issued: {{ $m->issued_weight }}g · Artisan: {{ $m->artisan->name ?? '-' }}</div>
                        <div class="mt-2 flex items-center gap-1">
                            <form action="{{ route('workshop.mfg.stage', $m) }}" method="POST" class="flex gap-1 flex-1">
                                @csrf
                                <select name="stage" class="border rounded px-1.5 py-1 flex-1">
                                    @foreach(['metal_issued','stone_setting','polishing','finishing','qc','completed'] as $st)
                                        <option value="{{ $st }}" {{ $m->stage === $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                                    @endforeach
                                </select>
                                <button class="bg-slate-800 text-white px-2 py-1 rounded">Update</button>
                            </form>
                            @if(auth()->user()->hasPermission('workshop_manage'))
                            <form action="{{ route('workshop.mfg.destroy', $m) }}" method="POST" class="inline" onsubmit="return confirm('Delete manufacturing order {{ $m->job_no }}?');">
                                @csrf
                                @method('DELETE')
                                <button class="text-rose-600 hover:underline px-1 py-1">Delete</button>
                            </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @if(auth()->user()->hasPermission('custom_orders'))
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">Custom Jewellery Orders</h3>
            <div class="space-y-3 text-xs">
                @foreach($customOrders as $co)
                    <div class="border border-slate-200 rounded-lg p-3">
                        <div class="flex justify-between font-bold"><span>{{ $co->order_no }}</span><span class="text-amber-700 uppercase">{{ $co->status }}</span></div>
                        <div class="font-medium mt-0.5">{{ $co->title }}</div>
                        <div class="text-slate-500">{{ $co->customer->name ?? '' }} · Est: ₹{{ number_format($co->estimated_amount, 0) }} (Adv: ₹{{ number_format($co->advance_paid, 0) }})</div>
                        <div class="mt-2 flex items-center gap-1">
                            <form action="{{ route('workshop.custom.status', $co) }}" method="POST" class="flex gap-1 flex-1">
                                @csrf
                                <select name="status" class="border rounded px-1.5 py-1 flex-1">
                                    @foreach(['pending_approval','approved','in_production','ready','delivered','cancelled'] as $st)
                                        <option value="{{ $st }}" {{ $co->status === $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                                    @endforeach
                                </select>
                                <button class="bg-slate-800 text-white px-2 py-1 rounded">Save</button>
                            </form>
                            @if(auth()->user()->hasPermission('workshop_manage'))
                            <form action="{{ route('workshop.custom.destroy', $co) }}" method="POST" class="inline" onsubmit="return confirm('Delete custom order {{ $co->order_no }}?');">
                                @csrf
                                @method('DELETE')
                                <button class="text-rose-600 hover:underline px-1 py-1">Delete</button>
                            </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @if(auth()->user()->hasPermission('repairs'))
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">Repair & Service Jobs</h3>
            <div class="space-y-3 text-xs">
                @foreach($repairJobs as $rj)
                    <div class="border border-slate-200 rounded-lg p-3">
                        <div class="flex justify-between font-bold"><span>{{ $rj->job_no }}</span><span class="text-emerald-700 uppercase">{{ $rj->status }}</span></div>
                        <div class="font-medium mt-0.5">{{ $rj->item_description }}</div>
                        <div class="text-slate-500">{{ $rj->customer->name ?? '' }} · Wt: {{ $rj->receiving_weight }}g · ₹{{ number_format($rj->repair_charges, 0) }}</div>
                        <div class="mt-2 flex items-center gap-1">
                            <form action="{{ route('workshop.repair.status', $rj) }}" method="POST" class="flex gap-1 flex-1">
                                @csrf
                                <select name="status" class="border rounded px-1.5 py-1 flex-1">
                                    @foreach(['received','in_progress','qc','ready','delivered'] as $st)
                                        <option value="{{ $st }}" {{ $rj->status === $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                                    @endforeach
                                </select>
                                <button class="bg-slate-800 text-white px-2 py-1 rounded">Save</button>
                            </form>
                            @if(auth()->user()->hasPermission('workshop_manage'))
                            <form action="{{ route('workshop.repair.destroy', $rj) }}" method="POST" class="inline" onsubmit="return confirm('Delete repair job {{ $rj->job_no }}?');">
                                @csrf
                                @method('DELETE')
                                <button class="text-rose-600 hover:underline px-1 py-1">Delete</button>
                            </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

