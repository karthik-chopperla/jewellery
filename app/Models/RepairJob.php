<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairJob extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expected_delivery' => 'date'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function artisan()
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }
}

