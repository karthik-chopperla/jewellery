<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['voucher_date' => 'date']; }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function account() { return $this->belongsTo(FinancialAccount::class, 'account_id'); }
    public function contraAccount() { return $this->belongsTo(FinancialAccount::class, 'contra_account_id'); }
}

