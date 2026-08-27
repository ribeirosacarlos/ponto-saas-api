<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialEmailSend extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_OPENED = 'opened';

    public const STATUS_CLICKED = 'clicked';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUS_COMPLAINED = 'complained';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'enrollment_id',
        'lead_id',
        'sequence_step_id',
        'template_id',
        'idempotency_key',
        'to_email',
        'rendered_subject',
        'rendered_body_html',
        'resend_message_id',
        'status',
        'queued_at',
        'sent_at',
        'delivered_at',
        'opened_at',
        'first_clicked_at',
        'bounced_at',
        'complained_at',
        'failed_at',
        'cancelled_at',
        'failure_reason',
        'cancel_reason',
        'attempt_count',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'first_clicked_at' => 'datetime',
        'bounced_at' => 'datetime',
        'complained_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'attempt_count' => 'integer',
    ];

    public function getTable(): string
    {
        return CommercialSchema::table('email_sends');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CommercialEmailSequenceEnrollment::class, 'enrollment_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CommercialLead::class, 'lead_id');
    }

    public function sequenceStep(): BelongsTo
    {
        return $this->belongsTo(CommercialEmailSequenceStep::class, 'sequence_step_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommercialEmailTemplate::class, 'template_id');
    }
}
