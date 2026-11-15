@extends('layouts.app')

@section('header', 'Multi-Branch Showroom Management')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5 h-fit">
        <h2 class="text-base font-semibold text-slate-800 mb-3">Register New Showroom</h2>
        <form action="{{ route('admin.branches.store') }}" method="POST" class="space-y-2.5 text-xs">
            @csrf
            <input type="text" name="name" required placeholder="Showroom Name" class="w-full border border-slate-300 rounded-lg px-3 py-2">
            <div class="grid grid-cols-2 gap-2">
                <input type="text" name="code" required placeholder="Code (e.g. DEL-CP)" class="border border-slate-300 rounded-lg px-2.5 py-2">
                <input type="text" name="phone" placeholder="Phone" class="border border-slate-300 rounded-lg px-2.5 py-2">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input type="text" name="city" placeholder="City" class="border border-slate-300 rounded-lg px-2.5 py-2">
                <input type="text" name="state" value="Maharashtra" placeholder="State" class="border border-slate-300 rounded-lg px-2.5 py-2">
            </div>
            <input type="text" name="gstin" placeholder="Branch GSTIN" class="w-full border border-slate-300 rounded-lg px-3 py-2">
            <textarea name="address" rows="2" placeholder="Address..." class="w-full border border-slate-300 rounded-lg px-3 py-2"></textarea>
            <button class="w-full bg-amber-600 text-white font-semibold py-2 rounded-lg">Add Branch</button>
        </form>
    </div>

    <div class="lg:col-span-2 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($branches as $b)
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-bold text-slate-900">{{ $b->name }}</div>
                            <div class="text-xs text-slate-500">{{ $b->code }} · {{ $b->city }}, {{ $b->state }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($b->is_head_office)
                                <span class="px-2 py-0.5 text-[10px] rounded-full bg-amber-100 text-amber-800 font-bold">HEAD OFFICE</span>
                            @else
                                <form action="{{ route('admin.branches.destroy', $b) }}" method="POST" class="inline" onsubmit="return confirm('Delete branch {{ $b->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-[11px] text-rose-600 hover:underline">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-slate-100 text-xs">
                        <div><div class="text-slate-400">Sales</div><div class="font-bold">₹{{ number_format($b->total_sales ?? 0, 0) }}</div></div>
                        <div><div class="text-slate-400">Stock Wt</div><div class="font-bold">{{ number_format($b->total_net_weight ?? 0, 2) }}g</div></div>
                        <div><div class="text-slate-400">Staff</div><div class="font-bold">{{ $b->users_count }}</div></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

