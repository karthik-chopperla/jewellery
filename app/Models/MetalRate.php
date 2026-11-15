<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetalRate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effective_at' => 'datetime'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function latestRate(string $metalType, string $purity, ?int $branchId = null): float
    {
        $query = self::where('metal_type', $metalType)
            ->where('purity', $purity)
            ->where('status', 'approved')
            ->where('effective_at', '<=', now());

        if ($branchId) {
            $branchRate = (clone $query)->where('branch_id', $branchId)->latest('effective_at')->first();
            if ($branchRate) {
                return (float) $branchRate->rate_per_gram;
            }
        }

        $global = $query->whereNull('branch_id')->latest('effective_at')->first()
            ?? $query->latest('effective_at')->first();

        return $global ? (float) $global->rate_per_gram : 0.0;
    }
}

