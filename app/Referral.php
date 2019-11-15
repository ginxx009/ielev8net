<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $guarded = [];
    
    public function user() {
        return $this->hasMany(User::class);
    }

    public function referrallogs() {
        return $this->hasMany(ReferralLogs::class);
    }
}
