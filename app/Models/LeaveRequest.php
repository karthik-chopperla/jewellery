<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['start_date' => 'date', 'end_date' => 'date']; }
    public function user() { return $this->belongsTo(User::class); }
}

