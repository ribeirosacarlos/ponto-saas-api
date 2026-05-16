<?php

namespace App\Models;

use App\Services\UserShiftResolver;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class UserShift extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'user_id',
        'shift_id',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    protected static function booted(): void
    {
        $clearCache = fn (self $model) => UserShiftResolver::forgetUserTodayCache($model->user_id);

        static::saved($clearCache);
        static::deleted($clearCache);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereDate('start_date', '<=', Carbon::today())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', Carbon::today());
            });
    }
}
