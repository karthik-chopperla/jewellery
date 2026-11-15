@extends('layouts.app')

@section('header', 'Security, Approval Workflows & Audit Logs')

@section('content')
<div class="space-y-6">
    <!-- Pending & Recent Approvals -->
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-base font-semibold text-slate-800 mb-3">Manager Approval Workflows (Discounts, Rates, Adjustments, Refunds)</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase">
                    <tr><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-left">Requested By</th><th class="px-3 py-2 text-left">Reason</th><th class="px-3 py-2 text-center">Status</th><th class="px-3 py-2 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($approvals as $ap)
                        <tr>
                            <td class="px-3 py-2 font-bold uppercase">{{ str_replace('_', ' ', $ap->type) }}</td>
                            <td class="px-3 py-2">{{ $ap->requester->name ?? '' }}</td>
                            <td class="px-3 py-2">{{ $ap->reason }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 rounded-full {{ $ap->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($ap->status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800') }}">{{ ucfirst($ap->status) }}</span>
                            </td>
                            <td class="px-3 py-2 text-right space-x-1">
                                @if($ap->status === 'pending')
                                    <form action="{{ route('admin.approvals.decide', $ap) }}" method="POST" class="inline">
                                        @csrf<input type="hidden" name="status" value="approved">
                                        <button class="bg-emerald-600 text-white px-2.5 py-1 rounded">Approve</button>
                                    </form>
                                    <form action="{{ route('admin.approvals.decide', $ap) }}" method="POST" class="inline">
                                        @csrf<input type="hidden" name="status" value="rejected">
                                        <button class="bg-rose-600 text-white px-2.5 py-1 rounded">Reject</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Activity Logs -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">User Activity & Audit Trail</h3>
            <div class="space-y-2 text-xs max-h-80 overflow-y-auto">
                @foreach($activityLogs as $log)
                    <div class="border-b border-slate-100 pb-1.5">
                        <div class="flex justify-between">
                            <span class="font-semibold text-slate-800">[{{ $log->module }}] {{ $log->action }}</span>
                            <span class="text-slate-400">{{ $log->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="text-slate-600">{{ $log->description }} — <span class="text-slate-400">{{ $log->user->name ?? 'System' }}</span></div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Login History & Role Matrix -->
        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h3 class="text-sm font-semibold text-slate-800 mb-3">Role-Based Permissions Matrix</h3>
                <div class="space-y-2 text-xs">
                    @foreach($rolePermissions as $rp)
                        <div class="flex justify-between border-b border-slate-100 pb-1">
                            <span class="font-bold uppercase text-slate-800">{{ $rp->role }}</span>
                            <span class="text-slate-600">{{ implode(', ', $rp->permissions) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h3 class="text-sm font-semibold text-slate-800 mb-3">Login History</h3>
                <div class="space-y-1.5 text-xs max-h-48 overflow-y-auto">
                    @foreach($loginHistories as $lh)
                        <div class="flex justify-between border-b border-slate-100 pb-1">
                            <span>{{ $lh->email }} ({{ $lh->ip_address }})</span>
                            <span class="{{ $lh->status === 'success' ? 'text-emerald-700' : 'text-rose-600' }} font-semibold">{{ $lh->logged_in_at->format('d M, H:i') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

