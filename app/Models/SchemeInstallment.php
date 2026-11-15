<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchemeInstallment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'paid_date' => 'date'];
    }

    public function scheme()
    {
        return $this->belongsTo(GoldScheme::class, 'gold_scheme_id');
    }
}

