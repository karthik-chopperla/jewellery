<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HallmarkRecord extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['dispatch_date' => 'date', 'receipt_date' => 'date']; }
    public function inventoryItem() { return $this->belongsTo(InventoryItem::class); }
}

