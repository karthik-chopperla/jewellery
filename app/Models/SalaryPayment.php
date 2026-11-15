<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryPayment extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['paid_at' => 'date']; }
    public function user() { return $this->belongsTo(User::class); }
}

