<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = [
            'business_name' => Setting::get('business_name', 'Abharan ERP'),
            'tagline' => Setting::get('tagline', 'Fine Jewellery & Bullion Suite'),
            'phone' => Setting::get('phone', '+91 22 2345 6789'),
            'email' => Setting::get('email', 'contact@jewelerp.in'),
            'address' => Setting::get('address', '142 Sheikh Memon Street, Zaveri Bazaar, Mumbai'),
            'gstin' => Setting::get('gstin', '27AABCS1429B1Z5'),
            'pan_number' => Setting::get('pan_number', 'ABCPS1234D'),
            'hallmark_center' => Setting::get('hallmark_center', 'BIS Mumbai Assaying & Hallmarking Center'),
            'default_rate_city' => Setting::get('default_rate_city', 'guwahati'),
            'default_making_type' => Setting::get('default_making_type', 'per_gram'),
            'default_making_rate' => Setting::get('default_making_rate', '650'),
            'default_wastage_pct' => Setting::get('default_wastage_pct', '2.0'),
            'default_gst_rate' => Setting::get('default_gst_rate', '3.0'),
            'currency_symbol' => Setting::get('currency_symbol', '₹'),
            'barcode_prefix' => Setting::get('barcode_prefix', '890'),
            'business_logo_path' => Setting::get('business_logo_path'),
            'invoice_shop_copy' => Setting::get('invoice_shop_copy', '0') === '1',
        ];

        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'business_name' => 'required|string|max:120',
            'tagline' => 'nullable|string|max:200',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
            'gstin' => 'nullable|string|max:30',
            'pan_number' => 'nullable|string|max:30',
            'hallmark_center' => 'nullable|string|max:120',
            'default_rate_city' => 'nullable|string|max:50',
            'default_making_type' => 'nullable|string',
            'default_making_rate' => 'nullable|numeric|min:0',
            'default_wastage_pct' => 'nullable|numeric|min:0',
            'default_gst_rate' => 'nullable|numeric|min:0',
            'currency_symbol' => 'nullable|string|max:10',
            'barcode_prefix' => ['nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9_-]*$/'],
            'invoice_shop_copy' => 'nullable|boolean',
            'business_logo' => 'nullable|image|mimes:jpeg,png|max:2048',
        ]);

        $data['invoice_shop_copy'] = $data['invoice_shop_copy'] ?? false;
        foreach (collect($data)->except('business_logo')->all() as $key => $value) {
            Setting::set($key, (string) ($value ?? ''));
        }

        if ($request->hasFile('business_logo')) {
            $logoPath = $request->file('business_logo')->store('branding', 'public');
            Setting::set('business_logo_path', $logoPath);
        }

        AuditService::log('Settings', 'update', "Updated showroom settings, Business Name: {$data['business_name']}");

        return back()->with('success', 'Showroom settings & business profile updated successfully!');
    }
}

