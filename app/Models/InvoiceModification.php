<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceModification extends Model
{
    protected $guarded = ['id'];
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function user() { return $this->belongsTo(User::class); }
}

