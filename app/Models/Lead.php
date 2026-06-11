<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasUuids;

    protected $fillable = [
        'email',
        'lead_magnet_type',
        'page_slug',
        'consented_at',
        'ip_hash',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
    ];
}
