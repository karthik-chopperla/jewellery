<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabelPrintLog extends Model
{
    protected $guarded = ['id'];
    public function inventoryItem() { return $this->belongsTo(InventoryItem::class); }
    public function user() { return $this->belongsTo(User::class); }
}

