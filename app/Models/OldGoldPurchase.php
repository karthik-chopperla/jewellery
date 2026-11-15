<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OldGoldPurchase extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'customer_declaration' => 'boolean',
            'refinery_transfer_date' => 'date',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}

