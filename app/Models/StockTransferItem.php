<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    protected $guarded = ['id'];
    public function transfer() { return $this->belongsTo(StockTransfer::class, 'stock_transfer_id'); }
    public function inventoryItem() { return $this->belongsTo(InventoryItem::class); }
}

