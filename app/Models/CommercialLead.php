<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommercialLead extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_name',
        'contact_name',
        'email',
        'phone',
        'whatsapp',
        'website',
        'google_maps_place_id',
        'country',
        'city',
        'segment',
        'employees_count',
        'source',
        'affiliate_id',
        'current_step_id',
        'assigned_to_user_id',
        'created_by_user_id',
        'status',
        'priority',
        'score',
        'general_notes',
        'next_action_type',
        'next_action_at',
        'next_action_user_id',
        'converted_at',
        'customer_id',
        'lost_reason',
    ];

    protected $casts = [
        'employees_count' => 'integer',
        'score' => 'integer',
        'next_action_at' => 'datetime',
        'converted_at' => 'datetime',
        'deleted_at' => 'datetime',
        'current_step_started_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $lead) {
            if ($lead->isDirty('current_step_id')) {
                $lead->current_step_started_at = now();
            }

            if ($lead->isDirty('email') && $lead->email) {
                $lead->email = strtolower(trim($lead->email));
            }

            if ($lead->isDirty('phone')) {
                $lead->phone_normalized = self::normalizePhone($lead->phone);
            }

            if ($lead->isDirty('google_maps_place_id') && $lead->google_maps_place_id) {
                $lead->google_maps_place_id = trim($lead->google_maps_place_id);
            }
        });
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits !== '' ? $digits : null;
    }

    public function getTable(): string
    {
        return CommercialSchema::table('leads');
    }

    public function pipelineStatus(): array
    {
        $step = $this->currentStep;
        $next = $step?->nextActive();

        $dueAt = null;
        $isOverdue = false;

        if ($step && $this->current_step_started_at && ! empty($step->default_due_days)) {
            $dueAt = $this->current_step_started_at->copy()->addDays($step->default_due_days);
            $isOverdue = now()->greaterThan($dueAt);
        }

        $warning = null;

        if ($isOverdue && $step && ! $step->is_final && $next) {
            $warning = "Tempo padrão desta etapa encerrado. Tente avançar para a próxima etapa do pipeline: {$next->name}.";
        }

        return [
            'current_stage_name' => $step?->name,
            'current_stage_default_days' => $step?->default_due_days,
            'current_stage_started_at' => $this->current_step_started_at?->toIso8601String(),
            'current_stage_due_at' => $dueAt?->toIso8601String(),
            'current_stage_is_overdue' => $isOverdue,
            'current_stage_warning_message' => $warning,
            'next_stage_id' => $next?->id,
            'next_stage_name' => $next?->name,
        ];
    }

    public function scopeOverdue(Builder $query, bool $overdue = true): Builder
    {
        $leadsTable = $this->getTable();
        $stepsTable = CommercialSchema::table('lead_steps');
        $comparator = $overdue ? '<' : '>=';

        $dueDateExpression = CommercialSchema::isPgsql()
            ? "{$leadsTable}.current_step_started_at + (overdue_step.default_due_days * INTERVAL '1 day')"
            : "datetime({$leadsTable}.current_step_started_at, '+' || overdue_step.default_due_days || ' days')";

        return $query
            ->whereNotNull("{$leadsTable}.current_step_started_at")
            ->whereExists(function ($sub) use ($stepsTable, $leadsTable, $dueDateExpression, $comparator) {
                $sub->selectRaw('1')
                    ->from("{$stepsTable} as overdue_step")
                    ->whereColumn('overdue_step.id', "{$leadsTable}.current_step_id")
                    ->where('overdue_step.default_due_days', '>', 0)
                    ->whereRaw("{$dueDateExpression} {$comparator} ?", [now()]);
            });
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(CommercialAffiliate::class, 'affiliate_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(CommercialLeadStep::class, 'current_step_id');
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function nextActionUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'next_action_user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'customer_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CommercialLeadNote::class, 'lead_id');
    }

    public function stepLogs(): HasMany
    {
        return $this->hasMany(CommercialLeadStepLog::class, 'lead_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(CommercialCommission::class, 'lead_id');
    }

    public function emailSequenceEnrollments(): HasMany
    {
        return $this->hasMany(CommercialEmailSequenceEnrollment::class, 'lead_id');
    }

    public function emailSends(): HasMany
    {
        return $this->hasMany(CommercialEmailSend::class, 'lead_id');
    }

    public function activeEmailEnrollment(): ?CommercialEmailSequenceEnrollment
    {
        return $this->emailSequenceEnrollments()
            ->where('status', CommercialEmailSequenceEnrollment::STATUS_ACTIVE)
            ->latest('enrolled_at')
            ->first();
    }

    public function isEmailSuppressed(): bool
    {
        return CommercialEmailSuppression::isSuppressed($this->email);
    }
}
