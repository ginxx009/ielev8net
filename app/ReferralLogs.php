<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReferralLogs extends Model
{
    protected $guarded = [];
    
    public function referral() {
        return $this->belongsTo(Referral::class);
    }
}
