@extends('layouts.app')

@section('header', 'Customer CRM, Loyalty & Marketing Campaigns')

@section('content')
<div class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Register Customer -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Register Customer</h2>
            <form action="{{ route('crm.store') }}" method="POST" class="space-y-2.5 text-xs">
                @csrf
                <input type="text" name="name" required placeholder="Full Name" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="phone" required placeholder="Mobile Number" class="border border-slate-300 rounded-lg px-3 py-2">
                    <input type="email" name="email" placeholder="Email" class="border border-slate-300 rounded-lg px-3 py-2">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="text-slate-500">DOB</label><input type="date" name="dob" class="w-full border border-slate-300 rounded-lg px-2 py-1.5"></div>
                    <div><label class="text-slate-500">Anniversary</label><input type="date" name="anniversary" class="w-full border border-slate-300 rounded-lg px-2 py-1.5"></div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <input type="text" name="pan_number" placeholder="PAN Number" class="border border-slate-300 rounded-lg px-2.5 py-2">
                    <select name="group_tier" class="border border-slate-300 rounded-lg px-2 py-2">
                        <option value="standard">Standard</option>
                        <option value="silver">Silver</option>
                        <option value="gold">Gold</option>
                        <option value="vip">VIP</option>
                    </select>
                    <input type="number" name="credit_limit" value="0" placeholder="Credit Limit" class="border border-slate-300 rounded-lg px-2 py-2">
                </div>
                <input type="text" name="address" placeholder="Address" class="w-full border border-slate-300 rounded-lg px-3 py-2">
                <button class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 rounded-lg">Add Customer</button>
            </form>
        </div>

        <!-- Marketing & Festival Campaigns -->
        <div class="bg-white rounded-xl border border-slate-200 p-5" x-data="{
            occasion: 'birthday',
            target: 'birthday_7days',
            message: 'Dear {name}, {{ $businessName }} wishes you a very Happy {occasion}! Celebrate your special milestone with an exclusive 15% discount on jewellery making charges this week.',
            updateTemplate() {
                if (this.occasion === 'birthday') {
                    this.message = 'Dear {name}, {{ $businessName }} wishes you a very Happy Birthday! 🎂 Enjoy an exclusive 15% off on jewellery making charges on your special day. Visit our showroom!';
                } else if (this.occasion === 'anniversary') {
                    this.message = 'Dear {name}, {{ $businessName }} wishes you a very Happy Wedding Anniversary! 💍 Celebrate your love with a complimentary gift and 20% off making charges.';
                } else if (this.occasion === 'akshaya_tritiya') {
                    this.message = 'May this Akshaya Tritiya bring prosperity to you! Special zero making charge on select 22K gold jewellery today at {{ $businessName }}.';
                } else if (this.occasion === 'diwali' || this.occasion === 'dhanteras') {
                    this.message = 'Happy Dhanteras & Diwali from {{ $businessName }}! Book your Dhanteras gold coins and jewellery with locked live bullion rates.';
                }
            }
        }">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-base font-semibold text-slate-800">Launch Marketing Campaign</h2>
                <span class="text-[11px] bg-amber-100 text-amber-800 font-bold px-2 py-0.5 rounded-full">Targeted Outreach</span>
            </div>
            <p class="text-xs text-slate-500 mb-3">Send personalized greetings and promotional offers via WhatsApp, SMS, or Email.</p>

            <form action="{{ route('crm.campaigns.store') }}" method="POST" class="space-y-2.5 text-xs">
                @csrf
                <div>
                    <label class="block font-medium text-slate-600 mb-1">Campaign Title</label>
                    <input type="text" name="title" required value="Upcoming Birthday & Milestone Greeting" placeholder="Campaign Title" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Channel</label>
                        <select name="channel" class="w-full border border-slate-300 rounded-lg px-2 py-2 text-xs">
                            <option value="whatsapp">💬 WhatsApp</option>
                            <option value="sms">📱 SMS</option>
                            <option value="email">✉️ Email</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Occasion / Event</label>
                        <select name="occasion" x-model="occasion" @change="updateTemplate()" class="w-full border border-slate-300 rounded-lg px-2 py-2 text-xs">
                            <option value="birthday">🎂 Birthday</option>
                            <option value="anniversary">💍 Anniversary</option>
                            <option value="akshaya_tritiya">🪔 Akshaya Tritiya</option>
                            <option value="dhanteras">🪙 Dhanteras</option>
                            <option value="diwali">✨ Diwali</option>
                            <option value="collection">💎 New Collection</option>
                            <option value="loyalty">🎁 Loyalty Rewards</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-medium text-slate-600 mb-1">Audience Target Window</label>
                    <select name="target_group" x-model="target" class="w-full border border-slate-300 rounded-lg px-2 py-2 text-xs font-medium text-slate-800 bg-amber-50/50">
                        <optgroup label="🎂 Birthday Timing Windows">
                            <option value="birthday_today">🎂 Birthday Today (1 Day)</option>
                            <option value="birthday_7days">🎂 Birthday in Next 7 Days</option>
                            <option value="birthday_15days">🎂 Birthday in Next 15 Days</option>
                            <option value="birthday_30days">🎂 Birthday in Next 1 Month (30 Days)</option>
                            <option value="birthday_month">🎂 All Birthdays This Month</option>
                        </optgroup>
                        <optgroup label="💍 Anniversary Timing Windows">
                            <option value="anniversary_today">💍 Anniversary Today (1 Day)</option>
                            <option value="anniversary_7days">💍 Anniversary in Next 7 Days</option>
                            <option value="anniversary_15days">💍 Anniversary in Next 15 Days</option>
                            <option value="anniversary_30days">💍 Anniversary in Next 1 Month (30 Days)</option>
                            <option value="anniversary_month">💍 All Anniversaries This Month</option>
                        </optgroup>
                        <optgroup label="👥 Customer Loyalty Tiers">
                            <option value="all">👥 All Customers</option>
                            <option value="vip">⭐ VIP Tier Customers</option>
                            <option value="gold">🥇 Gold Tier Customers</option>
                            <option value="silver">🥈 Silver Tier Customers</option>
                        </optgroup>
                    </select>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="block font-medium text-slate-600">Message (supports {name}, {occasion})</label>
                        <button type="button" @click="updateTemplate()" class="text-[10px] text-amber-700 hover:underline">Reset Template</button>
                    </div>
                    <textarea name="message" x-model="message" rows="3" required placeholder="Promotional message..." class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs"></textarea>
                </div>

                <button class="w-full bg-slate-900 hover:bg-slate-800 text-white font-semibold py-2 rounded-lg text-xs transition">
                    🚀 Dispatch Campaign
                </button>
            </form>
        </div>

        <!-- Birthday & Anniversary Action Hub -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
            <div>
                <div class="flex flex-wrap items-center justify-between gap-1 mb-2">
                    <h3 class="text-sm font-bold text-slate-900">🎂 Birthdays & 💍 Anniversaries</h3>
                    <span class="text-[11px] font-semibold text-slate-500">{{ $upcomingEvents->count() }} upcoming</span>
                </div>

                <!-- Window Filters (Today, 7 days, 15 days, 1 month, this month) -->
                <div class="flex flex-wrap gap-1 mb-3">
                    <a href="{{ route('crm.index', ['window' => 'today', 'event_type' => $eventType]) }}" class="px-2 py-1 rounded text-[11px] font-semibold {{ $window === 'today' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        Today ({{ $stats['today'] }})
                    </a>
                    <a href="{{ route('crm.index', ['window' => '7days', 'event_type' => $eventType]) }}" class="px-2 py-1 rounded text-[11px] font-semibold {{ $window === '7days' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        7 Days ({{ $stats['seven_days'] }})
                    </a>
                    <a href="{{ route('crm.index', ['window' => '15days', 'event_type' => $eventType]) }}" class="px-2 py-1 rounded text-[11px] font-semibold {{ $window === '15days' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        15 Days ({{ $stats['fifteen_days'] }})
                    </a>
                    <a href="{{ route('crm.index', ['window' => '30days', 'event_type' => $eventType]) }}" class="px-2 py-1 rounded text-[11px] font-semibold {{ $window === '30days' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        1 Month ({{ $stats['one_month'] }})
                    </a>
                    <a href="{{ route('crm.index', ['window' => 'this_month', 'event_type' => $eventType]) }}" class="px-2 py-1 rounded text-[11px] font-semibold {{ $window === 'this_month' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        Month ({{ $stats['this_month'] }})
                    </a>
                </div>

                <!-- Event Type Switcher -->
                <div class="flex gap-2 text-xs mb-3 border-b border-slate-100 pb-2">
                    <a href="{{ route('crm.index', ['window' => $window, 'event_type' => 'all']) }}" class="{{ $eventType === 'all' ? 'text-amber-700 font-bold underline' : 'text-slate-500 hover:underline' }}">All ({{ $upcomingEvents->count() }})</a>
                    <span class="text-slate-300">|</span>
                    <a href="{{ route('crm.index', ['window' => $window, 'event_type' => 'birthday']) }}" class="{{ $eventType === 'birthday' ? 'text-amber-700 font-bold underline' : 'text-slate-500 hover:underline' }}">🎂 Birthdays</a>
                    <span class="text-slate-300">|</span>
                    <a href="{{ route('crm.index', ['window' => $window, 'event_type' => 'anniversary']) }}" class="{{ $eventType === 'anniversary' ? 'text-amber-700 font-bold underline' : 'text-slate-500 hover:underline' }}">💍 Anniversaries</a>
                </div>

                <!-- Upcoming Customers List -->
                <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                    @forelse($upcomingEvents as $evt)
                        @php
                            $c = $evt['customer'];
                            $greetingText = "Dear {$c->name}, {$businessName} wishes you a very Happy {$evt['event_name']}! Celebrate with an exclusive 15% discount on jewellery making charges this week. Visit us today!";
                            $waUrl = "https://wa.me/" . preg_replace('/[^0-9]/', '', $c->phone) . "?text=" . urlencode($greetingText);
                        @endphp
                        <div class="p-2.5 rounded-lg border border-slate-200 bg-slate-50/60 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <div>
                                <div class="font-semibold text-slate-900 flex items-center gap-1">
                                    <span>{{ $evt['icon'] }}</span>
                                    <span>{{ $c->name }}</span>
                                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 uppercase font-medium">{{ $c->group_tier }}</span>
                                </div>
                                <div class="text-[11px] text-slate-500 mt-0.5">
                                    {{ $evt['event_name'] }}: <strong class="text-amber-900">{{ $evt['next_date']->format('d M') }}</strong>
                                    · <span class="{{ $evt['days_until'] === 0 ? 'text-rose-600 font-bold' : 'text-emerald-700 font-medium' }}">{{ $evt['badge'] }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" class="bg-emerald-600 hover:bg-emerald-700 text-white px-2.5 py-1 rounded text-[11px] font-semibold flex items-center gap-1 shadow-xs transition" title="Open WhatsApp Chat with pre-filled greeting">
                                    <span>💬</span> WhatsApp
                                </a>
                                <form action="{{ route('crm.campaigns.direct') }}" method="POST" class="inline">
                                    @csrf
                                    <input type="hidden" name="customer_id" value="{{ $c->id }}">
                                    <input type="hidden" name="occasion" value="{{ $evt['type'] }}">
                                    <input type="hidden" name="channel" value="sms">
                                    <input type="hidden" name="message" value="{{ $greetingText }}">
                                    <button class="bg-slate-800 hover:bg-slate-900 text-white px-2 py-1 rounded text-[11px] transition">SMS</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 py-3 text-center border border-dashed rounded-lg">
                            No birthdays or anniversaries in this {{ $window === 'today' ? 'day' : ($window === '7days' ? 'week' : ($window === '30days' ? 'month' : 'window')) }}.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Recent Campaigns -->
            <div class="pt-3 border-t border-slate-100">
                <h3 class="text-sm font-semibold text-slate-800 mb-2">📣 Recent Dispatched Campaigns</h3>
                <div class="space-y-1.5">
                    @forelse($campaigns as $camp)
                        <div class="text-xs py-1.5 px-2 rounded border border-slate-100 bg-slate-50/50 flex justify-between items-center">
                            <div>
                                <div class="font-semibold text-slate-800">{{ $camp->title }}</div>
                                <div class="text-[11px] text-slate-500 uppercase">{{ $camp->channel }} · Sent to {{ $camp->sent_count }} · {{ ucfirst(str_replace('_', ' ', $camp->occasion)) }}</div>
                            </div>
                            <form action="{{ route('crm.campaigns.destroy', $camp) }}" method="POST" class="inline" onsubmit="return confirm('Delete campaign {{ $camp->title }}?');">
                                @csrf
                                @method('DELETE')
                                <button class="text-[11px] text-rose-600 hover:underline ml-2">Delete</button>
                            </form>
                        </div>
                    @empty
                        <div class="text-[11px] text-slate-400">No campaigns launched yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Customers Directory -->
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-slate-800">Customer Directory & Lifetime Value</h2>
            <form method="GET" class="flex gap-2 text-xs">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or phone..." class="border border-slate-300 rounded-lg px-3 py-1.5">
                <button class="bg-slate-800 text-white px-3 py-1.5 rounded-lg">Search</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                    <tr>
                        <th class="px-3 py-2 text-left">Customer</th>
                        <th class="px-3 py-2 text-left">Tier</th>
                        <th class="px-3 py-2 text-right">Purchases</th>
                        <th class="px-3 py-2 text-right">Lifetime Value</th>
                        <th class="px-3 py-2 text-right">Loyalty Pts</th>
                        <th class="px-3 py-2 text-right">Outstanding</th>
                        <th class="px-3 py-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($customers as $c)
                        <tr>
                            <td class="px-3 py-2">
                                <a href="{{ route('crm.show', $c) }}" class="font-semibold text-amber-700 hover:underline">{{ $c->name }}</a>
                                <div class="text-xs text-slate-500">{{ $c->customer_code }} · {{ $c->phone }}</div>
                            </td>
                            <td class="px-3 py-2"><span class="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800 uppercase font-semibold">{{ $c->group_tier }}</span></td>
                            <td class="px-3 py-2 text-right">{{ $c->invoices_count }}</td>
                            <td class="px-3 py-2 text-right font-semibold">₹{{ number_format($c->invoices_sum_grand_total ?? 0, 0) }}</td>
                            <td class="px-3 py-2 text-right font-semibold text-emerald-700">{{ $c->loyalty_points }} pts</td>
                            <td class="px-3 py-2 text-right text-rose-600">₹{{ number_format($c->outstanding_balance, 0) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <form action="{{ route('crm.customers.destroy', $c) }}" method="POST" class="inline" onsubmit="return confirm('Delete customer {{ $c->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs text-rose-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $customers->links() }}</div>
    </div>
</div>
@endsection

