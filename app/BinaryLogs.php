<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BinaryLogs extends Model
{
    protected $guarded = [];
    
    public function binary() {
        return $this->belongsTo(Binary::class);
    }
}
