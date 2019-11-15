<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BinaryEarnings extends Model
{
    protected $guarded = [];

    public function binary() {
        return $this->belongsTo(Binary::class);
    }
}
