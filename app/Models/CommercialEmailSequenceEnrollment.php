<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialEmailSequenceEnrollment extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'lead_id',
        'sequence_id',
        'status',
        'exit_reason',
        'current_step_id',
        'next_step_id',
        'next_send_at',
        'enrolled_at',
        'enrolled_by_user_id',
        'paused_at',
        'paused_by_user_id',
        'pause_reason',
        'replied_at',
        'replied_marked_by_user_id',
        'completed_at',
        'cancelled_at',
        'unsubscribe_token',
    ];

    protected $casts = [
        'next_send_at' => 'datetime',
        'enrolled_at' => 'datetime',
        'paused_at' => 'datetime',
        'replied_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return CommercialSchema::table('email_sequence_enrollments');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CommercialLead::class, 'lead_id');
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(CommercialEmailSequence::class, 'sequence_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(CommercialEmailSequenceStep::class, 'current_step_id');
    }

    public function nextStep(): BelongsTo
    {
        return $this->belongsTo(CommercialEmailSequenceStep::class, 'next_step_id');
    }

    public function enrolledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by_user_id');
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CommercialEmailSend::class, 'enrollment_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
