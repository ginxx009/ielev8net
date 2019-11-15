<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    public function binary() {
        return $this->hasOne(Binary::class);
    }

    public function matrix() {
        return $this->hasMany(Matrix::class);
    }
}
