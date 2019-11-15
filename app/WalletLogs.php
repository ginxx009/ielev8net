<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WalletLogs extends Model
{
    protected $guarded = [];

    public function wallet() {
        return $this->belongsTo(Wallet::class);
    }
}
