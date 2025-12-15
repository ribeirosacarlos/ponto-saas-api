<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftDay extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'shift_id',
        'weekday',
        'is_working_day',
        'start_time',
        'end_time',
        'break_start_time',
        'break_end_time',
        'break_minutes',
    ];

    protected $casts = [
        'is_working_day' => 'boolean',
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
