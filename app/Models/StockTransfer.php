<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['transferred_at' => 'datetime']; }
    public function fromBranch() { return $this->belongsTo(Branch::class, 'from_branch_id'); }
    public function toBranch() { return $this->belongsTo(Branch::class, 'to_branch_id'); }
    public function items() { return $this->hasMany(StockTransferItem::class); }
}

