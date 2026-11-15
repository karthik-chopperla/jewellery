<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $guarded = ['id'];
    public function branch() { return $this->belongsTo(Branch::class); }
    public function inventoryItem() { return $this->belongsTo(InventoryItem::class); }
}

