<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CompanyScoped;

class AnnouncementRead extends Model
{
    use HasFactory, HasUuids, CompanyScoped;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'announcement_id',
        'user_id',
        'seen_at',
    ];

    protected $casts = [
        'seen_at' => 'datetime',
    ];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}
