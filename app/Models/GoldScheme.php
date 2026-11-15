<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoldScheme extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'maturity_date' => 'date'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function installments()
    {
        return $this->hasMany(SchemeInstallment::class);
    }
}

