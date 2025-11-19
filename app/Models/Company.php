<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Company extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'document',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'plan',
        'trial_ends_at',
        'subscription_ends_at',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function adjustments()
    {
        return $this->hasMany(Adjustment::class);
    }
}
