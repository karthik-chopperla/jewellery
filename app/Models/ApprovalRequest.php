<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['payload' => 'array', 'decided_at' => 'datetime']; }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}

