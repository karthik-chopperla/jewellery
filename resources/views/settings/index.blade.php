@extends('layouts.app')

@section('header', 'Business & Showroom Settings')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs">
        <div class="border-b border-slate-100 pb-4 mb-5">
            <h2 class="text-base font-bold text-slate-900">Showroom Identity & Business Profile</h2>
            <p class="text-xs text-slate-500 mt-0.5">Change your jewellery showroom name, branding, tax registration, and billing defaults.</p>
        </div>

        <form action="{{ route('settings.update') }}" method="POST" class="space-y-4 text-xs">
            @csrf

            <!-- Business Name & Tagline -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Showroom / Business Name *</label>
                    <input type="text" name="business_name" value="{{ old('business_name', $settings['business_name']) }}" required placeholder="e.g. Kalyan Jewellers / Shree Ganesh Jewels" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Brand Tagline</label>
                    <input type="text" name="tagline" value="{{ old('tagline', $settings['tagline']) }}" placeholder="e.g. Pure Gold & Diamond Craftsmanship" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
            </div>

            <!-- Contact & Legal -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                <div>
                    <label class="block font-medium text-slate-600 mb-1">Phone Number</label>
                    <input type="text" name="phone" value="{{ old('phone', $settings['phone']) }}" placeholder="+91 98200 11223" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs">
                </div>
                <div>
                    <label class="block font-medium text-slate-600 mb-1">Official Email</label>
                    <input type="email" name="email" value="{{ old('email', $settings['email']) }}" placeholder="info@jewellers.com" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs">
                </div>
                <div>
                    <label class="block font-medium text-slate-600 mb-1">GSTIN</label>
                    <input type="text" name="gstin" value="{{ old('gstin', $settings['gstin']) }}" placeholder="27AABCS1429B1Z5" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block font-medium text-slate-600 mb-1">PAN Number</label>
                    <input type="text" name="pan_number" value="{{ old('pan_number', $settings['pan_number']) }}" placeholder="ABCPS1234D" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs font-mono">
                </div>
                <div>
                    <label class="block font-medium text-slate-600 mb-1">BIS Hallmarking Center</label>
                    <input type="text" name="hallmark_center" value="{{ old('hallmark_center', $settings['hallmark_center']) }}" placeholder="BIS Assaying Center" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs">
                </div>
            </div>

            <div>
                <label class="block font-medium text-slate-600 mb-1">Showroom Address (Printed on Invoices)</label>
                <textarea name="address" rows="2" placeholder="Street, Market, City, State..." class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs">{{ old('address', $settings['address']) }}</textarea>
            </div>

            <!-- Defaults & Configuration -->
            <div class="border-t border-slate-100 pt-4 mt-2">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3">POS, City Market & Pricing Defaults</h3>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Live Rate City</label>
                        <select name="default_rate_city" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold">
                            @foreach(\App\Services\LiveMetalRateService::CITIES as $cSlug => $cName)
                                <option value="{{ $cSlug }}" {{ ($settings['default_rate_city'] ?? 'guwahati') === $cSlug ? 'selected' : '' }}>{{ $cName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Default Making Type</label>
                        <select name="default_making_type" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                            <option value="per_gram" {{ $settings['default_making_type'] === 'per_gram' ? 'selected' : '' }}>₹ / Gram</option>
                            <option value="percentage" {{ $settings['default_making_type'] === 'percentage' ? 'selected' : '' }}>% of Gold</option>
                            <option value="fixed" {{ $settings['default_making_type'] === 'fixed' ? 'selected' : '' }}>Fixed ₹</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Default Making Rate</label>
                        <input type="number" step="0.01" name="default_making_rate" value="{{ old('default_making_rate', $settings['default_making_rate']) }}" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Default Wastage %</label>
                        <input type="number" step="0.1" name="default_wastage_pct" value="{{ old('default_wastage_pct', $settings['default_wastage_pct']) }}" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Default GST %</label>
                        <input type="number" step="0.1" name="default_gst_rate" value="{{ old('default_gst_rate', $settings['default_gst_rate']) }}" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs">
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end">
                <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold px-5 py-2 rounded-lg text-xs transition">
                    Save Business Profile & Settings
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

