<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use App\Traits\CompanyScoped;

class Shift extends Model
{
    use HasUuid, CompanyScoped;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'name',
        'start_time',
        'end_time',
        'is_flexible',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
