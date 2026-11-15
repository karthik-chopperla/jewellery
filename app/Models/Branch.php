<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_head_office' => 'boolean', 'is_active' => 'boolean'];
    }

    public function counters()
    {
        return $this->hasMany(Counter::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}

