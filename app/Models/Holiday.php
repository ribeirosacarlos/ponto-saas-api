<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Holiday extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'date',
        'name',
        'scope',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected static function booted(): void
    {
        $clearCache = function (self $model) {
            Cache::forget("company_holiday:{$model->company_id}:{$model->date->toDateString()}");
        };

        static::saved($clearCache);
        static::deleted($clearCache);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
