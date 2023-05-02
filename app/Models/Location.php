<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;
    protected $fillable = [
        'sync_id',
        'location_code',
        'is_active',
        'location_name'
    ];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function masterlists()
    {
        return $this->hasMany(Masterlist::class, 'location_sync_id', 'sync_id');
    }
}
