<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use App\Traits\CompanyScoped;

class TimeEntry extends Model
{
    use HasUuid, CompanyScoped;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'user_id',
        'clocked_at',
        'type',
        'latitude',
        'longitude',
        'source',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
