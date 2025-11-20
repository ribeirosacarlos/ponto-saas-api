<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TimeEntry extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'clocked_at',
        'type',
        'latitude',
        'longitude',
        'source',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
