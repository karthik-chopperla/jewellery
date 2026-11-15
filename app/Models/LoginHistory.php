<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginHistory extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected function casts(): array { return ['logged_in_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
}

