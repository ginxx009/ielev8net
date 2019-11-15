<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    public function user() {
        return $this->belongsTo(User::class);
    }

    public function binary() {
        return $this->hasMany(Binary::class);
    }
    
    public function matrix() {
        return $this->hasMany(Matrix::class);
    }

    public function accountlogs() {
        return $this->hasMany(AccountLogs::class);
    }
    
    public function cashout() {
        return $this->hasMany(Cashout::class);
    }
}
