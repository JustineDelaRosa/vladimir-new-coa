<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrinterIP extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip',
        'name',
        'is_active'
    ];
}
