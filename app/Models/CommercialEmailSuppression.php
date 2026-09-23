<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialEmailSuppression extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    public const REASON_UNSUBSCRIBED = 'unsubscribed';

    public const REASON_BOUNCED_HARD = 'bounced_hard';

    public const REASON_BOUNCED_SOFT_THRESHOLD = 'bounced_soft_threshold';

    public const REASON_COMPLAINED = 'complained';

    public const REASON_MANUAL_BLOCK = 'manual_block';

    protected $fillable = [
        'email',
        'lead_id',
        'reason',
        'source',
        'notes',
        'suppressed_by_user_id',
        'suppressed_at',
    ];

    protected $casts = [
        'suppressed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $suppression) {
            if ($suppression->isDirty('email') && $suppression->email) {
                $suppression->email = strtolower(trim($suppression->email));
            }
        });
    }

    public function getTable(): string
    {
        return CommercialSchema::table('email_suppressions');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CommercialLead::class, 'lead_id');
    }

    public static function isSuppressed(?string $email): bool
    {
        if (! $email) {
            return false;
        }

        return static::query()
            ->where('email', strtolower(trim($email)))
            ->exists();
    }
}
