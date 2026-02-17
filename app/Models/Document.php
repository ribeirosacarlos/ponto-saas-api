<?php

namespace App\Models;

use App\Models\Company;
use App\Models\DocumentAudit;
use App\Models\DocumentNotification;
use App\Models\User;
use App\Traits\CompanyScoped;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory, HasUuid, CompanyScoped;

    public const STORAGE_DISK = 'local';

    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEW = 'review';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_REVIEW,
        self::STATUS_AVAILABLE,
        self::STATUS_EXPIRED,
    ];

    public const CATEGORY_PAYROLL = 'payroll';
    public const CATEGORY_COURSES = 'courses';
    public const CATEGORY_PERSONAL = 'personal';
    public const CATEGORY_OTHERS = 'others';

    public const CATEGORIES = [
        self::CATEGORY_PAYROLL,
        self::CATEGORY_COURSES,
        self::CATEGORY_PERSONAL,
        self::CATEGORY_OTHERS,
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'user_id',
        'title',
        'category',
        'status',
        'mime_type',
        'ext',
        'size_bytes',
        'path',
        'storage_disk',
        'original_name',
        'uploaded_by',
        'notes',
        'rejected_comment',
        'rejected_by',
        'rejected_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(DocumentAudit::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(DocumentNotification::class);
    }
}
