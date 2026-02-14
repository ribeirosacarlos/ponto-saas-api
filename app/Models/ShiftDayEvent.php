<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftDayEvent extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'shift_day_id',
        'kind',
        'expected_time',
        'day_offset',
        'expected_type',
        'sort_order',
    ];

    protected $casts = [
        'day_offset' => 'integer',
        'sort_order' => 'integer',
    ];

    public function shiftDay()
    {
        return $this->belongsTo(ShiftDay::class);
    }
}
