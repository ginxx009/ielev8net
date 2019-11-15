<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cashout extends Model
{
    protected $guarded = [];
    
    public function account() {
        return $this->belongsTo(Account::class);
    }
}
