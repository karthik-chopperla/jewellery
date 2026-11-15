<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManufacturingOrder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expected_date' => 'date', 'completed_date' => 'date'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function artisan()
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }
}

