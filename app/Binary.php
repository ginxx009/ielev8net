<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Binary extends Model
{
    protected $guarded = [];

    public function account() {
        return $this->belongsTo(Account::class);
    }

    public function package() {
        return $this->belongsTo(Package::class);
    }

    public function matrix() {
        return $this->hasMany(Matrix::class);
    }
    
    public function binary() {
        return $this->hasMany(Binary::class);
    }

    public function binarylogs() {
        return $this->hasMany(BinaryLogs::class);
    }

    public function binaryearnings() {
        return $this->hasMany(BinaryEarnings::class);
    }
}