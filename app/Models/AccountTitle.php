<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountTitle extends Model
{
    use HasFactory;
    protected $fillable = [
        'sync_id',
        'account_title_code',
        'is_active',
        'account_title_name'
    ];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function masterlists()
    {
        return $this->hasMany(Masterlist::class, 'account_sync_id', 'sync_id');
    }
}
