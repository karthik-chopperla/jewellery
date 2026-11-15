@extends('layouts.app')

@section('header', 'Staff, Attendance, Shifts & Salary Management')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-3">Register Staff / Employee</h2>
            <form action="{{ route('admin.staff.store') }}" method="POST" class="space-y-2.5 text-xs">
                @csrf
                <input type="text" name="name" required placeholder="Full Name" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                <div class="grid grid-cols-2 gap-2">
                    <input type="email" name="email" required placeholder="Email" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    <input type="password" name="password" value="password" required placeholder="Password" class="border border-slate-300 rounded-lg px-2.5 py-2">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <select name="branch_id" class="border border-slate-300 rounded-lg px-2 py-2">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                    <select name="role" class="border border-slate-300 rounded-lg px-2 py-2">
                        <option value="salesperson">Salesperson</option>
                        <option value="cashier">Cashier</option>
                        <option value="manager">Manager</option>
                        <option value="artisan">Artisan (Karigar)</option>
                        <option value="accountant">Accountant</option>
                    </select>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <input type="number" name="salary" placeholder="Salary ₹" class="border border-slate-300 rounded-lg px-2 py-2">
                    <input type="number" step="0.1" name="commission_rate" placeholder="Comm %" class="border border-slate-300 rounded-lg px-2 py-2">
                    <input type="number" name="monthly_target" placeholder="Target ₹" class="border border-slate-300 rounded-lg px-2 py-2">
                </div>
                <button class="w-full bg-amber-600 text-white font-semibold py-2 rounded-lg">Add Staff</button>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-3">Mark Today's Attendance</h2>
            <form action="{{ route('admin.attendance') }}" method="POST" class="space-y-2.5 text-xs">
                @csrf
                <select name="user_id" class="w-full border border-slate-300 rounded-lg px-3 py-2">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }})</option>@endforeach</select>
                <div class="grid grid-cols-2 gap-2">
                    <select name="shift" class="border border-slate-300 rounded-lg px-2 py-2"><option value="general">General Shift</option><option value="morning">Morning</option><option value="evening">Evening</option></select>
                    <select name="status" class="border border-slate-300 rounded-lg px-2 py-2"><option value="present">Present</option><option value="half_day">Half Day</option><option value="absent">Absent</option></select>
                </div>
                <button class="w-full bg-slate-900 text-white font-semibold py-2 rounded-lg">Mark Attendance</button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-base font-semibold text-slate-800 mb-3">Staff Directory & Targets</h2>
        <table class="w-full text-xs">
            <thead class="bg-slate-50 text-slate-500 uppercase">
                <tr><th class="px-3 py-2 text-left">Emp Code / Name</th><th class="px-3 py-2 text-left">Role & Branch</th><th class="px-3 py-2 text-right">Salary</th><th class="px-3 py-2 text-right">Comm %</th><th class="px-3 py-2 text-right">Target</th><th class="px-3 py-2 text-right">Action</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($users as $u)
                    <tr>
                        <td class="px-3 py-2"><div class="font-semibold">{{ $u->name }}</div><div class="text-slate-400">{{ $u->employee_code }} · {{ $u->email }}</div></td>
                        <td class="px-3 py-2 capitalize">{{ $u->role }} · {{ $u->branch->code ?? 'HQ' }}</td>
                        <td class="px-3 py-2 text-right">₹{{ number_format($u->salary, 0) }}</td>
                        <td class="px-3 py-2 text-right">{{ $u->commission_rate }}%</td>
                        <td class="px-3 py-2 text-right font-semibold">₹{{ number_format($u->monthly_target, 0) }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            @if(auth()->id() !== $u->id)
                                <form action="{{ route('admin.staff.destroy', $u) }}" method="POST" class="inline" onsubmit="return confirm('Delete employee {{ $u->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-rose-600 hover:underline">Delete</button>
                                </form>
                            @else
                                <span class="text-slate-400">Current</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h3 class="text-sm font-semibold text-slate-800 mt-6 mb-2">Today's Attendance</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
            @forelse($attendances as $att)
                <div class="border rounded-lg p-2 flex justify-between">
                    <span>{{ $att->user->name ?? '' }}</span>
                    <span class="font-semibold text-emerald-700 uppercase">{{ $att->status }}</span>
                </div>
            @empty
                <div class="text-slate-400 col-span-3">No attendance marked today yet.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

