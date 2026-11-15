<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'hallmark_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function hallmarkRecords()
    {
        return $this->hasMany(HallmarkRecord::class);
    }
}

