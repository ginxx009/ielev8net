<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Matrix extends Model
{
    protected $guarded = [];
    
    public function account() {
        return $this->belongsTo(Account::class);
    }
    
    public function binary() {
        return $this->belongsTo(Binary::class);
    }

    public function matrixlogs() {
        return $this->hasMany(MatrixLogs::class);
    }
}
