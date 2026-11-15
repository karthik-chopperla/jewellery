<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiamondStone extends Model
{
    protected $guarded = ['id'];
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function inventoryItem() { return $this->belongsTo(InventoryItem::class); }
}

