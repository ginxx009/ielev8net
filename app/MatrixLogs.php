<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MatrixLogs extends Model
{
    public function matrix() {
        return $this->belongsTo(Matrix::class);
    }
}
