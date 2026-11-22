<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Setting;

class InvoiceSnapshot
{
    public static function forBranch(Branch $branch): array
    {
        $gstin = $branch->gstin ?: Setting::get('gstin', '');

        return [
            'business_name_snapshot' => Setting::get('business_name', $branch->name) ?: $branch->name,
            'business_address_snapshot' => $branch->address ?: Setting::get('address', ''),
            'business_phone_snapshot' => $branch->phone ?: Setting::get('phone', ''),
            'business_gstin_snapshot' => $gstin,
            'business_state_snapshot' => $branch->state,
            'business_state_code_snapshot' => preg_match('/^\d{2}/', (string) $gstin, $matches) ? $matches[0] : null,
            'business_logo_snapshot' => Setting::get('business_logo_path'),
        ];
    }
}
