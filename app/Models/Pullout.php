<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pullout extends Model
{
    use HasFactory;

    public function movementNumber(){
        return $this->morphOne(MovementNumber::class, 'movementTable');
    }
}
